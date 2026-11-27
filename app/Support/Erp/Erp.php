<?php

namespace App\Support\Erp;

use App\Licensing\Features;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Shop\Checkout;
use App\Shop\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Modul „Warenwirtschaft-Anbindung“: Abgleich mit Warenwirtschaft, Buchhaltung oder Lager
 * über CSV-Dateien (Excel-tauglich, Semikolon) und eine REST-Schnittstelle mit Zugangsschlüssel.
 */
class Erp
{
    public const PRODUCT_COLUMNS = ['artikelnummer', 'name', 'preis', 'statt_preis', 'mwst', 'bestand', 'aktiv', 'lieferzeit'];

    public static function on(): bool
    {
        return Features::on('warenwirtschaft');
    }

    /** Neuen Zugangsschlüssel erzeugen; gespeichert wird nur der Hash */
    public static function newToken(): string
    {
        $token = 'nww_'.Str::random(40);
        Setting::put('erp_token_hash', hash('sha256', $token));
        Setting::put('erp_token_hint', substr($token, 0, 8).'…'.substr($token, -4));

        return $token;
    }

    public static function revoke(): void
    {
        Setting::putMany(['erp_token_hash' => null, 'erp_token_hint' => null]);
    }

    public static function checkToken(?string $token): bool
    {
        $hash = Setting::get('erp_token_hash');

        return $hash && $token && hash_equals($hash, hash('sha256', $token));
    }

    public static function productRow(Product $p): array
    {
        return [
            'artikelnummer' => $p->sku, 'name' => $p->name, 'preis' => self::money($p->price_cents), 'statt_preis' => self::money($p->compare_price_cents),
            'mwst' => rtrim(rtrim(number_format((float) $p->tax_rate, 1, ',', ''), '0'), ','), 'bestand' => $p->stock, 'aktiv' => $p->is_active ? 'ja' : 'nein',
            'lieferzeit' => $p->delivery_time,
        ];
    }

    public static function productJson(Product $p): array
    {
        return ['sku' => $p->sku, 'name' => $p->name, 'slug' => $p->slug, 'price_cents' => $p->price_cents, 'compare_price_cents' => $p->compare_price_cents,
            'tax_rate' => (float) $p->tax_rate, 'stock' => $p->stock, 'active' => $p->is_active, 'delivery_time' => $p->delivery_time, 'updated_at' => $p->updated_at?->toIso8601String()];
    }

    public static function orderJson(Order $o): array
    {
        return [
            'number' => $o->number, 'created_at' => $o->created_at->toIso8601String(), 'status' => $o->status, 'payment_status' => $o->payment_status,
            'payment_method' => $o->payment_method, 'currency' => $o->currency,
            'customer' => ['name' => $o->name, 'company' => $o->company, 'email' => $o->email, 'phone' => $o->phone, 'street' => $o->street, 'zip' => $o->zip, 'city' => $o->city, 'country' => $o->country],
            'items' => $o->items->map(fn ($i) => ['sku' => $i->sku, 'name' => $i->name, 'quantity' => $i->quantity, 'price_cents' => $i->price_cents, 'tax_rate' => (float) $i->tax_rate])->all(),
            'subtotal_cents' => $o->subtotal_cents, 'discount_cents' => (int) $o->discount_cents, 'coupon_code' => $o->coupon_code,
            'shipping_cents' => $o->shipping_cents, 'total_cents' => $o->total_cents, 'taxes' => $o->taxes ?? [],
            'tracking_number' => $o->tracking_number, 'notes' => $o->notes,
        ];
    }

    /** CSV im deutschen Excel-Format: UTF-8 mit BOM, Semikolon */
    public static function csv(array $header, iterable $rows): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header, ';', '"', '');
        foreach ($rows as $row) {
            // Formeln in Excel verhindern (CSV-Injection)
            fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v, array_values($row)), ';', '"', '');
        }
        rewind($out);

        return stream_get_contents($out);
    }

    /**
     * Produkte aus einer CSV-Datei aktualisieren, Zuordnung über die Artikelnummer.
     *
     * @return array{updated: int, skipped: list<string>}
     */
    public static function import(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\n|\r/', trim($content));
        $delimiter = substr_count($lines[0] ?? '', ';') >= substr_count($lines[0] ?? '', ',') ? ';' : ',';
        $header = array_map(fn ($h) => Str::slug(trim($h), '_'), str_getcsv(array_shift($lines) ?? '', $delimiter, '"', ''));
        if (! in_array('artikelnummer', $header, true)) {
            return ['updated' => 0, 'skipped' => ['Die erste Zeile braucht eine Spalte „artikelnummer“.']];
        }
        $products = Product::whereNotNull('sku')->get()->keyBy(fn ($p) => mb_strtolower(trim($p->sku)));
        $updated = 0;
        $skipped = [];
        foreach ($lines as $n => $line) {
            if (trim($line) === '') {
                continue;
            }
            $row = array_combine($header, array_pad(array_slice(str_getcsv($line, $delimiter, '"', ''), 0, count($header)), count($header), null));
            $sku = mb_strtolower(trim((string) $row['artikelnummer']));
            $product = $products->get($sku);
            if (! $product) {
                $skipped[] = 'Zeile '.($n + 2).': Artikelnummer „'.Str::limit($row['artikelnummer'] ?? '', 40).'“ nicht gefunden';

                continue;
            }
            $changes = self::changes($row);
            if ($changes === null) {
                $skipped[] = 'Zeile '.($n + 2).': ungültiger Preis oder Bestand';

                continue;
            }
            $product->fill($changes);
            if ($product->isDirty()) {
                $product->save();
                $updated++;
            }
        }

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    /** Änderungen aus einer Zeile oder einem API-Aufruf, null bei ungültigen Werten */
    public static function changes(array $row): ?array
    {
        $c = [];
        if (filled($row['name'] ?? null)) {
            $c['name'] = Str::limit(trim($row['name']), 160, '');
        }
        if (filled($row['preis'] ?? null)) {
            $cents = Money::parse($row['preis']);
            if ($cents === null || $cents < 0) {
                return null;
            }
            $c['price_cents'] = $cents;
        }
        if (array_key_exists('statt_preis', $row) && $row['statt_preis'] !== null) {
            $c['compare_price_cents'] = trim((string) $row['statt_preis']) === '' ? null : Money::parse($row['statt_preis']);
        }
        if (array_key_exists('bestand', $row) && $row['bestand'] !== null) {
            $v = trim((string) $row['bestand']);
            if ($v !== '' && ! preg_match('/^-?\d+$/', $v)) {
                return null;
            }
            $c['stock'] = $v === '' ? null : (int) $v;
        }
        if (filled($row['aktiv'] ?? null)) {
            $c['is_active'] = in_array(mb_strtolower(trim($row['aktiv'])), ['ja', '1', 'yes', 'true', 'x', 'aktiv'], true);
        }
        if (array_key_exists('lieferzeit', $row) && $row['lieferzeit'] !== null) {
            $c['delivery_time'] = trim((string) $row['lieferzeit']) ?: null;
        }

        return $c;
    }

    /** Status einer Bestellung aus der Warenwirtschaft setzen */
    public static function updateOrder(Order $order, array $data): Order
    {
        if (($data['payment_status'] ?? null) === 'paid') {
            $order->markPaid($data['payment_reference'] ?? null);
        }
        if (isset($data['tracking_number'])) {
            $order->tracking_number = $data['tracking_number'];
        }
        if (isset($data['status']) && $data['status'] !== $order->status && $order->status !== 'cancelled') {
            if ($data['status'] === 'cancelled') {
                Checkout::restock($order->load('items'));
            }
            $order->status = $data['status'];
            if ($data['status'] === 'shipped' && ! $order->shipped_at) {
                $order->shipped_at = now();
            }
        }
        $order->save();

        return $order->fresh('items');
    }

    /** @return Collection<int, Order> */
    public static function ordersSince(?string $since, int $limit = 200): Collection
    {
        return Order::with('items')->when($since, fn ($q) => $q->where('updated_at', '>=', Carbon::parse($since)))
            ->orderBy('id')->limit($limit)->get();
    }

    private static function money(?int $cents): ?string
    {
        return $cents === null ? null : number_format($cents / 100, 2, ',', '');
    }
}
