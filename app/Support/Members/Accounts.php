<?php

namespace App\Support\Members;

use App\Licensing\Features;
use App\Models\User;

/** Modul „Kundenkonto“: Adressen eines Kundenkontos für die Kasse */
class Accounts
{
    public const ADDRESS_FIELDS = ['name', 'company', 'street', 'zip', 'city', 'country'];

    public static function on(): bool
    {
        return Features::on('kundenkonto');
    }

    public static function customer(): ?User
    {
        $u = auth()->user();

        return $u && $u->isCustomer() && self::on() ? $u : null;
    }

    /** Vorbelegung der Kasse: Lieferadresse, sonst Rechnungsadresse */
    public static function checkoutDefaults(?User $u): array
    {
        if (! $u) {
            return [];
        }
        $ship = array_filter($u->shipping_address ?? []);
        $bill = array_filter($u->billing_address ?? []);
        $addr = $ship ?: $bill;

        return ['email' => $u->email, 'name' => $addr['name'] ?? $u->name, 'company' => $addr['company'] ?? $u->company, 'street' => $addr['street'] ?? null,
            'zip' => $addr['zip'] ?? null, 'city' => $addr['city'] ?? null, 'country' => $addr['country'] ?? null, 'phone' => $u->phone,
            'billing' => $ship && $bill && $ship != $bill ? $bill : null];
    }

    public static function clean(array $a): ?array
    {
        $a = array_map(fn ($v) => is_string($v) ? trim($v) : $v, array_intersect_key($a, array_flip(self::ADDRESS_FIELDS)));

        return array_filter($a) ? $a : null;
    }
}
