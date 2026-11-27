<?php

namespace App\Shop;

use App\Licensing\Features;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Warenkorb in der Sitzung des Besuchers. Preise kommen immer frisch aus der Datenbank.
 */
class Cart
{
    private const KEY = 'nova.cart';

    private const COUPON = 'nova.coupon';

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
        $this->session->forget([self::KEY, self::COUPON]);
    }

    /** Eingelöster Gutschein- oder Rabattcode (Modul Gutscheine und Rabatte) */
    public function coupon(): ?Coupon
    {
        $code = $this->session->get(self::COUPON);
        if (! $code || ! Features::on('gutscheine')) {
            return null;
        }

        return Coupon::findByCode($code);
    }

    public function applyCoupon(?Coupon $coupon): void
    {
        $coupon ? $this->session->put(self::COUPON, $coupon->code) : $this->session->forget(self::COUPON);
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
     * @return array{subtotal: int, discount: int, coupon: ?Coupon, shipping: int, total: int, taxes: list<array{rate: float, cents: int}>, free_shipping_missing: ?int}
     */
    public function totals(?Collection $items = null): array
    {
        $items ??= $this->items();
        $subtotal = (int) $items->sum('total');
        $coupon = $this->coupon();
        if ($coupon && $coupon->problem($subtotal) !== null) {
            $coupon = null; // Gilt (nicht mehr), z. B. Mindestbestellwert unterschritten
        }
        $discount = $coupon ? $coupon->discountFor($subtotal) : 0;
        $free = ShopSettings::freeShippingFromCents();
        $shipping = $items->isEmpty() || ($free !== null && $subtotal >= $free) || $coupon?->kind === 'shipping' ? 0 : ShopSettings::shippingCents();

        $taxes = [];
        if (! ShopSettings::smallBusiness() && $subtotal > 0) {
            $byRate = $items->groupBy(fn ($i) => (string) $i['product']->tax_rate)->map(fn ($g) => (int) $g->sum('total'));
            // Rabatt mindert den Warenwert je Steuersatz anteilig
            if ($discount > 0) {
                $left = $discount;
                $keys = $byRate->keys()->values();
                foreach ($keys as $idx => $rate) {
                    $share = $idx === $keys->count() - 1 ? $left : (int) round($discount * $byRate[$rate] / $subtotal);
                    $left -= $share;
                    $byRate[$rate] -= $share;
                }
            }
            $shippingLeft = $shipping;
            $rates = $byRate->keys()->values();
            foreach ($rates as $idx => $rate) {
                // Versandkosten werden im Verhältnis der Warenwerte auf die Steuersätze verteilt
                $share = $idx === $rates->count() - 1 ? $shippingLeft : (int) round($shipping * $byRate[$rate] / max(1, $subtotal - $discount));
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
            'discount' => $discount,
            'coupon' => $coupon,
            'shipping' => $shipping,
            'total' => $subtotal - $discount + $shipping,
            'taxes' => $taxes,
            'free_shipping_missing' => $free !== null && $shipping > 0 ? $free - $subtotal : null,
        ];
    }
}
