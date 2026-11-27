<?php

namespace App\Shop\Payments;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Eine Zahlungsart. Plugins können eigene über den Filter "shop.payment_methods" ergänzen.
 */
interface PaymentMethod
{
    public function key(): string;

    public function label(): string;

    /** Kurzer Hinweis in der Kasse */
    public function description(): string;

    /** Ist die Zahlungsart eingerichtet und eingeschaltet? */
    public function isAvailable(): bool;

    /** Startet die Zahlung. Liefert eine Adresse zum Zahlungsanbieter oder null (z. B. Vorkasse). */
    public function start(Order $order): ?string;

    /** Rückkehr vom Zahlungsanbieter: true, wenn die Zahlung bestätigt ist. */
    public function complete(Order $order, Request $request): bool;

    /** Hinweise auf der Danke-Seite und in der Bestätigungs-E-Mail (HTML) */
    public function instructions(Order $order): ?string;
}
