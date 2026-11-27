<?php

namespace App\Shop;

use App\Models\Page;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Einstellungen des Shops an einer Stelle, mit sinnvollen Vorgaben für Deutschland.
 */
class ShopSettings
{
    /** Land des Händlers => Steuersätze (Standard zuerst) und Währung */
    public const COUNTRIES = [
        'DE' => ['label' => 'Deutschland', 'rates' => [19, 7, 0], 'currency' => 'EUR'],
        'AT' => ['label' => 'Österreich', 'rates' => [20, 13, 10, 0], 'currency' => 'EUR'],
        'CH' => ['label' => 'Schweiz', 'rates' => [8.1, 3.8, 2.6, 0], 'currency' => 'CHF'],
        'LU' => ['label' => 'Luxemburg', 'rates' => [17, 8, 3, 0], 'currency' => 'EUR'],
        'LI' => ['label' => 'Liechtenstein', 'rates' => [8.1, 2.6, 0], 'currency' => 'CHF'],
        'BE' => ['label' => 'Belgien', 'rates' => [], 'currency' => 'EUR'],
        'NL' => ['label' => 'Niederlande', 'rates' => [], 'currency' => 'EUR'],
        'FR' => ['label' => 'Frankreich', 'rates' => [], 'currency' => 'EUR'],
        'IT' => ['label' => 'Italien', 'rates' => [], 'currency' => 'EUR'],
        'DK' => ['label' => 'Dänemark', 'rates' => [], 'currency' => 'EUR'],
        'PL' => ['label' => 'Polen', 'rates' => [], 'currency' => 'EUR'],
        'CZ' => ['label' => 'Tschechien', 'rates' => [], 'currency' => 'EUR'],
    ];

    public const BASE_UNITS = ['kg' => 'kg', 'g' => 'g', 'l' => 'l', 'ml' => 'ml', 'm' => 'm', 'm2' => 'm²', 'Stk' => 'Stück'];

    public static function enabled(): bool
    {
        return Setting::get('shop_enabled') === '1';
    }

    public static function country(): string
    {
        $c = Setting::get('shop_country', 'DE');

        return isset(self::COUNTRIES[$c]) && self::COUNTRIES[$c]['rates'] ? $c : 'DE';
    }

    public static function currency(): string
    {
        return self::COUNTRIES[self::country()]['currency'];
    }

    /** @return list<float|int> */
    public static function taxRates(): array
    {
        return self::COUNTRIES[self::country()]['rates'];
    }

    public static function smallBusiness(): bool
    {
        return Setting::get('shop_small_business') === '1';
    }

    /** @return list<string> Länder, in die geliefert wird */
    public static function shippingCountries(): array
    {
        $list = array_filter(explode(',', (string) Setting::get('shop_ship_to', self::country())));

        return array_values(array_intersect($list, array_keys(self::COUNTRIES))) ?: [self::country()];
    }

    public static function shippingCents(): int
    {
        return (int) Setting::get('shop_shipping_cents', '490');
    }

    public static function freeShippingFromCents(): ?int
    {
        $v = Setting::get('shop_free_from_cents');

        return $v === null ? null : (int) $v;
    }

    public static function deliveryTime(): string
    {
        return Setting::get('shop_delivery_time', '2–4 Werktage');
    }

    public static function notifyEmail(): ?string
    {
        return Setting::get('shop_notify_email', Setting::get('contact_email'));
    }

    public static function secret(string $key): ?string
    {
        $v = Setting::get($key);
        if (! $v) {
            return null;
        }
        try {
            return Crypt::decryptString($v);
        } catch (Throwable) {
            return null;
        }
    }

    public static function putSecret(string $key, ?string $value): void
    {
        Setting::put($key, $value ? Crypt::encryptString($value) : null);
    }

    /** Rechtstext-Seite (terms, withdrawal, shipping), falls veröffentlicht */
    public static function legalPage(string $purpose): ?Page
    {
        return Page::published()->where('purpose', $purpose)->first();
    }

    public static function taxNote(?float $rate = null): string
    {
        if (self::smallBusiness()) {
            return 'ohne MwSt. (Kleinunternehmer)';
        }

        return $rate === null ? 'inkl. MwSt.' : 'inkl. '.self::rate($rate).' % MwSt.';
    }

    /** Pflichthinweis auf Rechnung und in der Kasse bei Kleinunternehmern */
    public static function smallBusinessNote(): string
    {
        return match (self::country()) {
            'AT' => 'Umsatzsteuerbefreit, Kleinunternehmer gemäß § 6 Abs. 1 Z 27 UStG.',
            'CH', 'LI' => 'Nicht mehrwertsteuerpflichtig.',
            default => 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.',
        };
    }

    public static function rate(float $rate): string
    {
        return rtrim(rtrim(number_format($rate, 1, ',', ''), '0'), ',');
    }
}
