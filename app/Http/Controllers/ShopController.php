<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Shop\Cart;
use App\Shop\Checkout;
use App\Shop\Payments\PaymentRegistry;
use App\Shop\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * Öffentlicher Shop: Produkte, Warenkorb, Kasse und Danke-Seite.
 */
class ShopController extends Controller
{
    public function index(): View
    {
        return view('shop.index', ['products' => Product::active()->orderBy('sort')->orderBy('name')->get()]);
    }

    public function show(string $slug): View
    {
        $product = Product::active()->where('slug', $slug)->firstOrFail();

        return view('shop.product', ['product' => $product]);
    }

    public function cart(Cart $cart): View
    {
        $items = $cart->items();

        return view('shop.cart', ['items' => $items, 'totals' => $cart->totals($items)]);
    }

    public function add(Request $request, Cart $cart): RedirectResponse
    {
        $data = $request->validate(['product' => ['required', 'integer'], 'quantity' => ['nullable', 'integer', 'min:1', 'max:'.Cart::MAX_QUANTITY]]);
        $product = Product::active()->findOrFail($data['product']);
        if (! $product->inStock()) {
            return back()->withErrors(['quantity' => 'Dieser Artikel ist leider ausverkauft.']);
        }
        $cart->add($product, (int) ($data['quantity'] ?? 1));

        return redirect()->route('shop.cart')->with('status', "„{$product->name}“ liegt im Warenkorb.");
    }

    public function update(Request $request, Cart $cart): RedirectResponse
    {
        $data = $request->validate(['quantities' => ['array'], 'quantities.*' => ['integer', 'min:0', 'max:'.Cart::MAX_QUANTITY], 'remove' => ['nullable', 'integer']]);
        foreach ($data['quantities'] ?? [] as $id => $qty) {
            $cart->set((int) $id, (int) $qty);
        }
        if (! empty($data['remove'])) {
            $cart->set((int) $data['remove'], 0);
        }

        return redirect()->route('shop.cart')->with('status', 'Warenkorb aktualisiert.');
    }

    public function checkout(Cart $cart, PaymentRegistry $payments): View|RedirectResponse
    {
        $items = $cart->items();
        if ($items->isEmpty()) {
            return redirect()->route('shop.cart');
        }

        return view('shop.checkout', [
            'items' => $items,
            'totals' => $cart->totals($items),
            'methods' => $payments->available(),
            'countries' => collect(ShopSettings::shippingCountries())->mapWithKeys(fn ($c) => [$c => ShopSettings::COUNTRIES[$c]['label']]),
            'terms' => ShopSettings::legalPage('terms'),
            'withdrawal' => ShopSettings::legalPage('withdrawal'),
            'privacy' => ShopSettings::legalPage('privacy'),
        ]);
    }

    public function placeOrder(Request $request, Checkout $checkout, PaymentRegistry $payments): RedirectResponse
    {
        $methods = array_keys($payments->available());
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['required', 'string', 'max:160'],
            'company' => ['nullable', 'string', 'max:160'],
            'street' => ['required', 'string', 'max:160'],
            'zip' => ['required', 'string', 'max:12'],
            'city' => ['required', 'string', 'max:120'],
            'country' => ['required', Rule::in(ShopSettings::shippingCountries())],
            'phone' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['required', Rule::in($methods)],
            'accept_terms' => ['accepted'],
        ], [
            'email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse an.',
            'payment_method.required' => 'Bitte wählen Sie eine Zahlungsart.',
            'payment_method.in' => 'Bitte wählen Sie eine Zahlungsart.',
            'accept_terms.accepted' => 'Bitte bestätigen Sie die AGB und die Widerrufsbelehrung.',
            'country.in' => 'In dieses Land liefern wir leider nicht.',
        ]);

        try {
            $order = $checkout->place($data, $data['payment_method']);
        } catch (RuntimeException $e) {
            return redirect()->route('shop.cart')->withErrors(['cart' => $e->getMessage()]);
        }

        return $this->startPayment($order, $payments);
    }

    public function order(string $token, PaymentRegistry $payments): View
    {
        $order = Order::where('token', $token)->with('items')->firstOrFail();
        $method = $payments->get($order->payment_method);

        return view('shop.thanks', [
            'order' => $order,
            'method' => $method,
            'instructions' => $method?->instructions($order),
            // Online-Zahlung (Stripe, PayPal, Plugins) kann bei Abbruch erneut gestartet werden
            'canPayOnline' => $method && ! $order->isPaid() && $order->status !== 'cancelled'
                && ! in_array($order->payment_method, ['prepayment', 'invoice'], true),
        ]);
    }

    public function pay(string $token, PaymentRegistry $payments): RedirectResponse
    {
        $order = Order::where('token', $token)->firstOrFail();
        abort_if($order->isPaid() || $order->status === 'cancelled', 404);

        return $this->startPayment($order, $payments);
    }

    public function paymentReturn(Request $request, string $token, string $method, PaymentRegistry $payments): RedirectResponse
    {
        $order = Order::where('token', $token)->firstOrFail();
        $payment = $payments->get($method);
        abort_unless($payment && $order->payment_method === $method, 404);

        if (! $order->isPaid()) {
            try {
                $ok = $payment->complete($order, $request);
            } catch (Throwable $e) {
                report($e);
                $ok = false;
            }
            if (! $ok) {
                return redirect()->route('shop.order', $order->token)->withErrors(['payment' => 'Die Zahlung wurde nicht bestätigt. Sie können es erneut versuchen.']);
            }
            $order->markPaid();
        }

        return redirect()->route('shop.order', $order->token)->with('status', 'Vielen Dank, Ihre Zahlung ist eingegangen.');
    }

    private function startPayment(Order $order, PaymentRegistry $payments): RedirectResponse
    {
        $method = $payments->get($order->payment_method);
        try {
            $url = $method?->start($order);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('shop.order', $order->token)
                ->withErrors(['payment' => 'Der Zahlungsanbieter ist gerade nicht erreichbar. Ihre Bestellung ist gespeichert, Sie können die Zahlung hier erneut starten.']);
        }

        return $url ? redirect()->away($url) : redirect()->route('shop.order', $order->token);
    }
}
