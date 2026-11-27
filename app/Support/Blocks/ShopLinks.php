<?php

namespace App\Support\Blocks;

use App\Models\Product;
use App\Shop\ShopSettings;
use Illuminate\Support\Collection;

/** Verknüpft Preis- und Modul-Blöcke mit Produkten aus dem Shop. */
class ShopLinks
{
    /**
     * Aktive, kaufbare Produkte zu den angegebenen Adressen (Slugs).
     *
     * @param  iterable<string|null>  $slugs
     * @return Collection<string, Product>
     */
    public static function products(iterable $slugs): Collection
    {
        $slugs = collect($slugs)->filter()->unique()->values();
        if ($slugs->isEmpty() || ! ShopSettings::enabled()) {
            return collect();
        }

        return Product::active()->whereIn('slug', $slugs)->get()->filter->inStock()->keyBy('slug');
    }
}
