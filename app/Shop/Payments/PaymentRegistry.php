<?php

namespace App\Shop\Payments;

use App\Plugins\Hooks;

class PaymentRegistry
{
    public function __construct(private readonly Hooks $hooks) {}

    /** @return array<string, PaymentMethod> alle bekannten Zahlungsarten */
    public function all(): array
    {
        $methods = $this->hooks->applyFilters('shop.payment_methods', [new Prepayment, new Invoice, new PayPal, new Stripe]);

        return collect($methods)->filter(fn ($m) => $m instanceof PaymentMethod)->keyBy(fn (PaymentMethod $m) => $m->key())->all();
    }

    /** @return array<string, PaymentMethod> */
    public function available(): array
    {
        return array_filter($this->all(), fn (PaymentMethod $m) => $m->isAvailable());
    }

    public function get(string $key): ?PaymentMethod
    {
        return $this->all()[$key] ?? null;
    }
}
