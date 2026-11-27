<?php

namespace App\Shop;

use App\Mail\OrderConfirmation;
use App\Mail\OrderReceived;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Plugins\Hooks;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Legt eine Bestellung verbindlich an: prüft Preise und Bestand erneut, bucht den Bestand ab,
 * schickt Bestätigungen und meldet die Bestellung an Plugins (z. B. eine Warenwirtschaft).
 */
class Checkout
{
    public function __construct(private readonly Cart $cart, private readonly Hooks $hooks) {}

    /** @param array<string, mixed> $customer */
    public function place(array $customer, string $paymentMethod): Order
    {
        $items = $this->cart->items();
        if ($items->isEmpty()) {
            throw new RuntimeException('Ihr Warenkorb ist leer.');
        }

        $order = DB::transaction(function () use ($items, $customer, $paymentMethod) {
            $this->reserveStock($items);
            $totals = $this->cart->totals($items);
            $this->redeem($totals);

            $order = null;
            for ($try = 0; $try < 3 && ! $order; $try++) {
                try {
                    $order = Order::create([
                        ...collect($customer)->only(['email', 'name', 'company', 'street', 'zip', 'city', 'country', 'phone', 'notes', 'user_id', 'billing'])->all(),
                        'number' => Order::nextNumber(),
                        'token' => Str::random(40),
                        'payment_method' => $paymentMethod,
                        'currency' => ShopSettings::currency(),
                        'subtotal_cents' => $totals['subtotal'],
                        'shipping_cents' => $totals['shipping'],
                        'total_cents' => $totals['total'],
                        'discount_cents' => $totals['discount'],
                        'coupon_code' => $totals['coupon']?->code,
                        'taxes' => $totals['taxes'],
                        'small_business' => ShopSettings::smallBusiness(),
                        'terms_accepted_at' => now(),
                    ]);
                } catch (UniqueConstraintViolationException) {
                    // Gleichzeitige Bestellung hat die Nummer bekommen: neu versuchen
                }
            }
            if (! $order) {
                throw new RuntimeException('Die Bestellung konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.');
            }

            foreach ($items as $i) {
                $order->items()->create([
                    'product_id' => $i['product']->id,
                    'name' => $i['product']->name,
                    'sku' => $i['product']->sku,
                    'price_cents' => $i['product']->price_cents,
                    'quantity' => $i['quantity'],
                    'tax_rate' => ShopSettings::smallBusiness() ? 0 : $i['product']->tax_rate,
                    'total_cents' => $i['total'],
                ]);
            }

            return $order;
        });

        $this->cart->clear();
        $order->load('items');
        $this->hooks->doAction('shop.order.placed', $order);
        $this->sendMails($order);

        return $order;
    }

    /** Gutschein verbindlich einlösen: Nutzung zählen, bei Geschenkgutscheinen den Restwert mindern */
    private function redeem(array $totals): void
    {
        if (! $totals['coupon']) {
            return;
        }
        $c = Coupon::whereKey($totals['coupon']->id)->lockForUpdate()->first();
        if (! $c || $c->problem($totals['subtotal']) !== null) {
            throw new RuntimeException('Ihr Gutschein ist nicht mehr gültig. Bitte prüfen Sie Ihren Warenkorb.');
        }
        $c->used++;
        if ($c->is_gift) {
            $c->value = max(0, $c->value - $totals['discount']);
        }
        $c->save();
    }

    /** Storno: Bestand zurückbuchen */
    public static function restock(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product_id) {
                Product::whereKey($item->product_id)->whereNotNull('stock')->increment('stock', $item->quantity);
            }
        }
    }

    public function sendMails(Order $order): void
    {
        try {
            Mail::to($order->email)->send(new OrderConfirmation($order));
            if ($to = ShopSettings::notifyEmail()) {
                Mail::to($to)->send(new OrderReceived($order));
            }
        } catch (Throwable $e) {
            // Eine fehlgeschlagene E-Mail darf die Bestellung nicht verhindern
            report($e);
        }
    }

    /** @param Collection<int, array{product: Product, quantity: int}> $items */
    private function reserveStock(Collection $items): void
    {
        foreach ($items as $i) {
            $p = Product::whereKey($i['product']->id)->lockForUpdate()->first();
            if (! $p || ! $p->is_active) {
                throw new RuntimeException("„{$i['product']->name}“ ist nicht mehr erhältlich.");
            }
            if ($p->price_cents !== $i['product']->price_cents) {
                throw new RuntimeException("Der Preis von „{$p->name}“ hat sich gerade geändert. Bitte prüfen Sie Ihren Warenkorb.");
            }
            if ($p->stock !== null) {
                if ($p->stock < $i['quantity']) {
                    throw new RuntimeException("Von „{$p->name}“ sind nur noch {$p->stock} Stück vorrätig.");
                }
                $p->decrement('stock', $i['quantity']);
            }
        }
    }
}
