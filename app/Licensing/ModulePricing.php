<?php

namespace App\Licensing;

use App\Models\Product;
use App\Models\Setting;
use App\Support\Starter\NovaSalesSite;

/**
 * Hersteller-Einstellung auf dem Lizenzserver: welches Modul ist kostenlos, welches kostet wie viel.
 * Kostenlose Module stehen in jeder Lizenz automatisch mit drin (siehe LicenseServer::payload).
 */
class ModulePricing
{
    /** @return array<string, array{name: string, description: string, free: bool, price_cents: int}> */
    public static function all(): array
    {
        $saved = json_decode((string) Setting::get('module_pricing'), true) ?: [];
        $out = [];
        foreach (Features::ALL as $key => [$name, $description]) {
            $default = NovaSalesSite::PAID_MODULES['modul-'.$key][1] ?? 0;
            $out[$key] = [
                'name' => $name, 'description' => $description,
                'free' => (bool) ($saved[$key]['free'] ?? false),
                'price_cents' => (int) ($saved[$key]['price_cents'] ?? $default),
            ];
        }

        return $out;
    }

    /** @return list<string> */
    public static function freeKeys(): array
    {
        return array_keys(array_filter(self::all(), fn ($m) => $m['free']));
    }

    public static function isFree(string $key): bool
    {
        return self::all()[$key]['free'] ?? false;
    }

    public static function price(string $key): int
    {
        return self::all()[$key]['price_cents'] ?? 0;
    }

    /**
     * Speichert die Einstellung und gleicht die Modul-Produkte im Shop ab:
     * kostenlos = nicht mehr zu kaufen, kostenpflichtig = mit neuem Preis im Shop.
     *
     * @param  array<string, array{free: bool, price_cents: int}>  $pricing
     */
    public static function save(array $pricing): int
    {
        $clean = [];
        foreach (Features::ALL as $key => $_) {
            $clean[$key] = ['free' => (bool) ($pricing[$key]['free'] ?? false), 'price_cents' => max(0, (int) ($pricing[$key]['price_cents'] ?? 0))];
        }
        Setting::put('module_pricing', json_encode($clean));

        $changed = 0;
        foreach ($clean as $key => $m) {
            $product = Product::where('slug', 'modul-'.$key)->first();
            if (! $product) {
                continue;
            }
            $product->fill(['is_active' => ! $m['free'] && $m['price_cents'] > 0, 'price_cents' => $m['free'] ? $product->price_cents : $m['price_cents']]);
            if ($product->isDirty()) {
                $product->save();
                $changed++;
            }
        }
        if (NovaSalesSite::installed()) {
            app(NovaSalesSite::class)->refreshModuleBlocks();
        }

        return $changed;
    }
}
