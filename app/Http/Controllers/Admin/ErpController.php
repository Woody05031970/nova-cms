<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Support\Erp\Erp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Modul „Warenwirtschaft-Anbindung“: CSV-Austausch und Zugangsschlüssel für die Schnittstelle */
class ErpController extends Controller
{
    public function index(): View
    {
        return view('admin.erp', [
            'hint' => Setting::get('erp_token_hint'),
            'products' => Product::count(),
            'withoutSku' => Product::whereNull('sku')->orWhere('sku', '')->count(),
            'orders' => Order::count(),
        ]);
    }

    public function products(): Response
    {
        $csv = Erp::csv(Erp::PRODUCT_COLUMNS, Product::orderBy('sort')->orderBy('name')->get()->map(fn ($p) => Erp::productRow($p)));

        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="produkte-'.now()->format('Y-m-d').'.csv"']);
    }

    public function orders(Request $request): Response
    {
        $data = $request->validate(['von' => ['nullable', 'date'], 'bis' => ['nullable', 'date']]);
        $orders = Order::with('items')
            ->when($data['von'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($data['bis'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', Carbon::parse($v)->endOfDay()))
            ->orderBy('id')->get();
        $rows = [];
        foreach ($orders as $o) {
            foreach ($o->items as $i) {
                $rows[] = [$o->number, $o->created_at->format('d.m.Y H:i'), $o->statusLabel(), $o->isPaid() ? 'bezahlt' : 'offen', $o->name, $o->company, $o->email,
                    $o->street, $o->zip, $o->city, $o->country, $i->sku, $i->name, $i->quantity, number_format($i->price_cents / 100, 2, ',', ''),
                    rtrim(rtrim(number_format((float) $i->tax_rate, 1, ',', ''), '0'), ','), number_format((int) $o->discount_cents / 100, 2, ',', ''),
                    number_format($o->shipping_cents / 100, 2, ',', ''), number_format($o->total_cents / 100, 2, ',', ''), $o->tracking_number];
            }
        }
        $csv = Erp::csv(['bestellnummer', 'datum', 'status', 'zahlung', 'name', 'firma', 'email', 'strasse', 'plz', 'ort', 'land', 'artikelnummer', 'artikel',
            'menge', 'einzelpreis', 'mwst', 'rabatt', 'versand', 'gesamt', 'sendungsnummer'], $rows);

        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="bestellungen-'.now()->format('Y-m-d').'.csv"']);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:csv,txt']], ['file.required' => 'Bitte wählen Sie eine CSV-Datei.', 'file.mimes' => 'Bitte eine CSV-Datei hochladen.']);
        $result = Erp::import((string) file_get_contents($request->file('file')->getRealPath()));
        $msg = $result['updated'] === 1 ? '1 Produkt aktualisiert.' : $result['updated'].' Produkte aktualisiert.';

        return redirect()->route('admin.erp')->with('status', $msg)->with('erp_skipped', array_slice($result['skipped'], 0, 30));
    }

    public function token(): RedirectResponse
    {
        return redirect()->to(route('admin.erp').'#schnittstelle')->with('erp_token', Erp::newToken());
    }

    public function revoke(): RedirectResponse
    {
        Erp::revoke();

        return redirect()->to(route('admin.erp').'#schnittstelle')->with('status', 'Zugangsschlüssel gelöscht. Die Schnittstelle ist jetzt gesperrt.');
    }
}
