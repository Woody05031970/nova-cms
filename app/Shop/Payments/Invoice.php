<?php

namespace App\Shop\Payments;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\Request;

class Invoice implements PaymentMethod
{
    public function key(): string
    {
        return 'invoice';
    }

    public function label(): string
    {
        return 'Rechnung';
    }

    public function description(): string
    {
        return 'Sie bezahlen nach Erhalt der Ware innerhalb von 14 Tagen.';
    }

    public function isAvailable(): bool
    {
        return Setting::get('shop_pay_invoice') === '1';
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
        return '<p>Die Rechnung liegt der Lieferung bei. Bitte überweisen Sie den Betrag innerhalb von 14 Tagen nach Erhalt'
            .(Setting::get('shop_iban') ? ' auf IBAN '.e(Setting::get('shop_iban')) : '').', Verwendungszweck <strong>'.e($order->number).'</strong>.</p>';
    }
}
