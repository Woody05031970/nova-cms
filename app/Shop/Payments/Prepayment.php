<?php

namespace App\Shop\Payments;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\Request;

class Prepayment implements PaymentMethod
{
    public function key(): string
    {
        return 'prepayment';
    }

    public function label(): string
    {
        return 'Vorkasse (Überweisung)';
    }

    public function description(): string
    {
        return 'Sie erhalten unsere Bankverbindung nach der Bestellung. Wir versenden nach Zahlungseingang.';
    }

    public function isAvailable(): bool
    {
        return Setting::get('shop_pay_prepayment', '1') === '1' && Setting::get('shop_iban') !== null;
    }

    public function start(Order $order): ?string
    {
        return null;
    }

    public function complete(Order $order, Request $request): bool
    {
        return false;
    }

    public function instructions(Order $order): ?string
    {
        return '<p>Bitte überweisen Sie <strong>'.e($order->money($order->total_cents)).'</strong> innerhalb von 7 Tagen auf folgendes Konto:</p>'
            .'<p>Kontoinhaber: '.e(Setting::get('shop_account_holder', Setting::get('company_name', ''))).'<br>'
            .'IBAN: '.e(Setting::get('shop_iban')).'<br>'
            .(Setting::get('shop_bic') ? 'BIC: '.e(Setting::get('shop_bic')).'<br>' : '')
            .'Verwendungszweck: <strong>Bestellung '.e($order->number).'</strong></p>';
    }
}
