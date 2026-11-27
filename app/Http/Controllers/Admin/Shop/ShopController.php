<?php

namespace App\Http\Controllers\Admin\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Shop\Money;
use App\Shop\Payments\PaymentRegistry;
use App\Shop\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $q = trim((string) $request->query('suche'));
        $month = now()->startOfMonth();

        return view('admin.shop.index', [
            'enabled' => ShopSettings::enabled(),
            'orders' => Order::query()
                ->when($status && isset(Order::STATUSES[$status]), fn ($b) => $b->where('status', $status))
                ->when($status === 'unbezahlt', fn ($b) => $b->where('payment_status', '!=', 'paid')->where('status', '!=', 'cancelled'))
                ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('number', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
                ->latest('id')->paginate(30)->withQueryString(),
            'status' => $status,
            'search' => $q,
            'stats' => [
                'open' => Order::whereIn('status', ['new', 'processing'])->count(),
                'unpaid' => Order::where('payment_status', '!=', 'paid')->where('status', '!=', 'cancelled')->count(),
                'month' => (int) Order::where('status', '!=', 'cancelled')->where('created_at', '>=', $month)->sum('total_cents'),
                'products' => Product::active()->count(),
                'soldOut' => Product::active()->whereNotNull('stock')->where('stock', '<=', 0)->count(),
            ],
        ]);
    }

    public function settings(PaymentRegistry $payments): View
    {
        $s = fn ($k, $d = null) => Setting::get($k, $d);

        return view('admin.shop.settings', [
            's' => $s,
            'ships' => ShopSettings::shippingCountries(),
            'methods' => $payments->all(),
            'hasStripe' => ShopSettings::secret('shop_stripe_secret') !== null,
            'hasPaypal' => ShopSettings::secret('shop_paypal_secret') !== null,
            'legal' => collect(['terms' => 'AGB', 'withdrawal' => 'Widerrufsbelehrung', 'shipping' => 'Versand und Zahlung'])
                ->map(fn ($label, $purpose) => ['label' => $label, 'page' => ShopSettings::legalPage($purpose)]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge([
            'shipping_cents' => Money::parse($request->input('shipping')) ?? 0,
            'free_from_cents' => Money::parse($request->input('free_from')),
        ]);
        $data = $request->validate([
            'enabled' => ['boolean'],
            'country' => ['required', Rule::in(array_keys(array_filter(ShopSettings::COUNTRIES, fn ($c) => $c['rates'])))],
            'small_business' => ['boolean'],
            'vat_id' => ['nullable', 'string', 'max:30'],
            'ship_to' => ['required', 'array', 'min:1'],
            'ship_to.*' => [Rule::in(array_keys(ShopSettings::COUNTRIES))],
            'shipping_cents' => ['required', 'integer', 'min:0', 'max:1000000'],
            'free_from_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'delivery_time' => ['nullable', 'string', 'max:60'],
            'notify_email' => ['nullable', 'email', 'max:255'],
            'pay_prepayment' => ['boolean'],
            'account_holder' => ['nullable', 'string', 'max:120'],
            'iban' => ['nullable', 'string', 'max:40', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9 ]{10,32}$/i'],
            'bic' => ['nullable', 'string', 'max:11'],
            'pay_invoice' => ['boolean'],
            'pay_stripe' => ['boolean'],
            'stripe_secret' => ['nullable', 'string', 'max:255', 'regex:/^(sk|rk)_(test|live)_/'],
            'pay_paypal' => ['boolean'],
            'paypal_client' => ['nullable', 'string', 'max:255'],
            'paypal_secret' => ['nullable', 'string', 'max:255'],
            'paypal_sandbox' => ['boolean'],
        ], [
            'ship_to.required' => 'Wählen Sie mindestens ein Land, in das Sie liefern.',
            'iban.regex' => 'Die IBAN sieht nicht richtig aus.',
            'stripe_secret.regex' => 'Der Stripe-Schlüssel beginnt mit sk_live_ oder sk_test_.',
        ]);

        $bool = fn ($k) => $request->boolean($k) ? '1' : '0';
        Setting::putMany([
            'shop_enabled' => $bool('enabled'),
            'shop_country' => $data['country'],
            'shop_small_business' => $bool('small_business'),
            'shop_vat_id' => $data['vat_id'] ?? null,
            'shop_ship_to' => implode(',', $data['ship_to']),
            'shop_shipping_cents' => (string) $data['shipping_cents'],
            'shop_free_from_cents' => $data['free_from_cents'] === null ? null : (string) $data['free_from_cents'],
            'shop_delivery_time' => $data['delivery_time'] ?? null,
            'shop_notify_email' => $data['notify_email'] ?? null,
            'shop_pay_prepayment' => $bool('pay_prepayment'),
            'shop_account_holder' => $data['account_holder'] ?? null,
            'shop_iban' => isset($data['iban']) ? strtoupper(trim(chunk_split(str_replace(' ', '', $data['iban']), 4, ' '))) : null,
            'shop_bic' => isset($data['bic']) ? strtoupper($data['bic']) : null,
            'shop_pay_invoice' => $bool('pay_invoice'),
            'shop_pay_stripe' => $bool('pay_stripe'),
            'shop_pay_paypal' => $bool('pay_paypal'),
            'shop_paypal_client' => $data['paypal_client'] ?? null,
            'shop_paypal_sandbox' => $bool('paypal_sandbox'),
        ]);
        // Geheime Schlüssel nur überschreiben, wenn ein neuer eingegeben wurde
        if (! empty($data['stripe_secret'])) {
            ShopSettings::putSecret('shop_stripe_secret', $data['stripe_secret']);
        }
        if (! empty($data['paypal_secret'])) {
            ShopSettings::putSecret('shop_paypal_secret', $data['paypal_secret']);
        }

        return back()->with('status', 'Shop-Einstellungen gespeichert.');
    }
}
