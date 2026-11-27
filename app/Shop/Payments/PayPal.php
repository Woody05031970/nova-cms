<?php

namespace App\Shop\Payments;

use App\Models\Order;
use App\Models\Setting;
use App\Shop\ShopSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * PayPal Checkout (Orders API v2): Weiterleitung zu PayPal, nach Rückkehr wird die Zahlung eingezogen.
 */
class PayPal implements PaymentMethod
{
    public function key(): string
    {
        return 'paypal';
    }

    public function label(): string
    {
        return 'PayPal';
    }

    public function description(): string
    {
        return 'Sie werden zu PayPal weitergeleitet.';
    }

    public function isAvailable(): bool
    {
        return Setting::get('shop_pay_paypal') === '1' && Setting::get('shop_paypal_client') !== null && ShopSettings::secret('shop_paypal_secret') !== null;
    }

    public static function base(): string
    {
        return Setting::get('shop_paypal_sandbox') === '1' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    public function start(Order $order): ?string
    {
        $res = Http::withToken($this->token())->acceptJson()->timeout(20)->post(self::base().'/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order->number,
                'invoice_id' => $order->number,
                'description' => 'Bestellung '.$order->number,
                'amount' => ['currency_code' => $order->currency, 'value' => number_format($order->total_cents / 100, 2, '.', '')],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'locale' => 'de-DE',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'return_url' => route('shop.payment.return', [$order->token, 'paypal']),
                'cancel_url' => route('shop.order', $order->token).'?abgebrochen=1',
            ]]],
        ]);

        $link = collect($res->json('links', []))->firstWhere('rel', 'payer-action')['href'] ?? null;
        if (! $res->successful() || ! $link) {
            throw new RuntimeException('PayPal: '.($res->json('message') ?? 'Zahlung konnte nicht gestartet werden.'));
        }
        $order->forceFill(['payment_reference' => $res->json('id')])->save();

        return $link;
    }

    public function complete(Order $order, Request $request): bool
    {
        $id = (string) $request->query('token');
        if ($id === '' || $id !== $order->payment_reference) {
            return false;
        }
        $res = Http::withToken($this->token())->acceptJson()->timeout(20)
            ->withBody('{}', 'application/json')
            ->post(self::base().'/v2/checkout/orders/'.urlencode($id).'/capture');

        $capture = $res->json('purchase_units.0.payments.captures.0');

        return $res->successful() && $res->json('status') === 'COMPLETED'
            && ($capture['amount']['value'] ?? null) === number_format($order->total_cents / 100, 2, '.', '');
    }

    public function instructions(Order $order): ?string
    {
        return null;
    }

    private function token(): string
    {
        $res = Http::asForm()->withBasicAuth((string) Setting::get('shop_paypal_client'), (string) ShopSettings::secret('shop_paypal_secret'))
            ->timeout(20)->post(self::base().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        if (! $res->successful()) {
            throw new RuntimeException('PayPal: Anmeldung fehlgeschlagen. Bitte Zugangsdaten prüfen.');
        }

        return (string) $res->json('access_token');
    }
}
