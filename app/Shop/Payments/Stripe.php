<?php

namespace App\Shop\Payments;

use App\Models\Order;
use App\Models\Setting;
use App\Shop\ShopSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Kreditkarte, Apple Pay, Google Pay, Klarna, SEPA … über Stripe Checkout.
 * Die Kartendaten berühren den eigenen Server nie.
 */
class Stripe implements PaymentMethod
{
    public const API = 'https://api.stripe.com/v1';

    public function key(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Kreditkarte, Apple Pay, Google Pay (Stripe)';
    }

    public function description(): string
    {
        return 'Sie werden zur sicheren Bezahlseite von Stripe weitergeleitet.';
    }

    public function isAvailable(): bool
    {
        return Setting::get('shop_pay_stripe') === '1' && ShopSettings::secret('shop_stripe_secret') !== null;
    }

    public function start(Order $order): ?string
    {
        $res = $this->http()->asForm()->post(self::API.'/checkout/sessions', [
            'mode' => 'payment',
            'client_reference_id' => $order->number,
            'customer_email' => $order->email,
            'locale' => 'de',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($order->currency),
                    'unit_amount' => $order->total_cents,
                    'product_data' => ['name' => 'Bestellung '.$order->number.' bei '.config('app.name')],
                ],
            ]],
            'metadata' => ['order' => $order->number],
            'success_url' => route('shop.payment.return', [$order->token, 'stripe']).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('shop.order', $order->token).'?abgebrochen=1',
        ]);

        if (! $res->successful() || ! $res->json('url')) {
            throw new RuntimeException('Stripe: '.($res->json('error.message') ?? 'Bezahlseite konnte nicht erstellt werden.'));
        }
        $order->forceFill(['payment_reference' => $res->json('id')])->save();

        return $res->json('url');
    }

    public function complete(Order $order, Request $request): bool
    {
        $id = (string) $request->query('session_id');
        // Nur die bei der Bestellung erzeugte Sitzung zählt
        if ($id === '' || $id !== $order->payment_reference) {
            return false;
        }
        $res = $this->http()->get(self::API.'/checkout/sessions/'.urlencode($id));

        return $res->successful()
            && $res->json('payment_status') === 'paid'
            && (int) $res->json('amount_total') === $order->total_cents
            && $res->json('client_reference_id') === $order->number;
    }

    public function instructions(Order $order): ?string
    {
        return null;
    }

    private function http()
    {
        return Http::withToken((string) ShopSettings::secret('shop_stripe_secret'))->timeout(20)->acceptJson();
    }
}
