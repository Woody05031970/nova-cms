<?php

namespace App\Shop;

use App\Models\Product;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Warenkorb in der Sitzung des Besuchers. Preise kommen immer frisch aus der Datenbank.
 */
class Cart
{
    private const KEY = 'nova.cart';

    public const MAX_QUANTITY = 99;

    public function __construct(private readonly Session $session) {}

    /** @return array<int, int> Produkt-ID => Menge */
    public function raw(): array
    {
        return $this->session->get(self::KEY, []);
    }

    public function add(Product $product, int $quantity = 1): void
    {
        $cart = $this->raw();
        $cart[$product->id] = min(self::MAX_QUANTITY, ($cart[$product->id] ?? 0) + max(1, $quantity));
        $this->session->put(self::KEY, $cart);
    }

    public function set(int $productId, int $quantity): void
    {
        $cart = $this->raw();
        if ($quantity <= 0) {
            unset($cart[$productId]);
        } else {
            $cart[$productId] = min(self::MAX_QUANTITY, $quantity);
        }
        $this->session->put(self::KEY, $cart);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    public function count(): int
    {
        return array_sum($this->raw());
    }

    /**
     * Positionen mit aktuellem Produkt. Nicht mehr verfügbare Produkte fallen heraus,
     * Mengen werden auf den Lagerbestand begrenzt.
     *
     * @return Collection<int, array{product: Product, quantity: int, total: int, limited: bool}>
     */
    public function items(): Collection
    {
        $raw = $this->raw();
        if (! $raw) {
            return collect();
        }
        $products = Product::active()->whereIn('id', array_keys($raw))->get()->keyBy('id');

        return collect($raw)->map(function ($qty, $id) use ($products) {
            $p = $products->get($id);
            if (! $p || ($p->stock !== null && $p->stock <= 0)) {
                return null;
            }
            $quantity = $p->stock === null ? $qty : min($qty, $p->stock);

            return ['product' => $p, 'quantity' => $quantity, 'total' => $p->price_cents * $quantity, 'limited' => $quantity < $qty];
        })->filter()->values();
    }

    /**
     * Summen inklusive anteiliger Steuer auf die Versandkosten.
     *
     * @param  Collection|null  $items  Ergebnis von items()
     * @return array{subtotal: int, shipping: int, total: int, taxes: list<array{rate: float, cents: int}>, free_shipping_missing: ?int}
     */
    public function totals(?Collection $items = null): array
    {
        $items ??= $this->items();
        $subtotal = (int) $items->sum('total');
        $free = ShopSettings::freeShippingFromCents();
        $shipping = $items->isEmpty() || ($free !== null && $subtotal >= $free) ? 0 : ShopSettings::shippingCents();

        $taxes = [];
        if (! ShopSettings::smallBusiness() && $subtotal > 0) {
            $byRate = $items->groupBy(fn ($i) => (string) $i['product']->tax_rate)->map(fn ($g) => (int) $g->sum('total'));
            $shippingLeft = $shipping;
            $rates = $byRate->keys()->values();
            foreach ($rates as $idx => $rate) {
                // Versandkosten werden im Verhältnis der Warenwerte auf die Steuersätze verteilt
                $share = $idx === $rates->count() - 1 ? $shippingLeft : (int) round($shipping * $byRate[$rate] / $subtotal);
                $shippingLeft -= $share;
                $cents = Money::includedTax($byRate[$rate] + $share, (float) $rate);
                if ((float) $rate > 0) {
                    $taxes[] = ['rate' => (float) $rate, 'cents' => $cents];
                }
            }
            usort($taxes, fn ($a, $b) => $b['rate'] <=> $a['rate']);
        }

        return [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total' => $subtotal + $shipping,
            'taxes' => $taxes,
            'free_shipping_missing' => $free !== null && $shipping > 0 ? $free - $subtotal : null,
        ];
    }
}
