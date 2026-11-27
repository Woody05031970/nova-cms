<?php

namespace App\Models;

use App\Shop\Money;
use App\Shop\ShopSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'name', 'slug', 'sku', 'short_description', 'description', 'price_cents', 'compare_price_cents',
        'tax_rate', 'stock', 'base_quantity', 'base_unit', 'delivery_time', 'images', 'is_active', 'sort', 'license_spec',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array', 'is_active' => 'boolean', 'price_cents' => 'integer', 'compare_price_cents' => 'integer',
            'tax_rate' => 'float', 'stock' => 'integer', 'base_quantity' => 'float', 'license_spec' => 'array',
        ];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function url(): string
    {
        return route('shop.product', $this->slug);
    }

    public function image(): ?string
    {
        return $this->images[0] ?? null;
    }

    public function inStock(int $quantity = 1): bool
    {
        return $this->stock === null || $this->stock >= $quantity;
    }

    public function price(): string
    {
        return Money::format($this->price_cents);
    }

    /** Grundpreis nach Preisangabenverordnung, z. B. "7,98 € / 1 kg" */
    public function basePrice(): ?string
    {
        if (! $this->base_quantity || ! $this->base_unit || $this->base_quantity <= 0) {
            return null;
        }
        // Kleine Einheiten werden auf 1 kg / 1 l umgerechnet
        [$qty, $unit] = match ($this->base_unit) {
            'g' => [$this->base_quantity / 1000, 'kg'],
            'ml' => [$this->base_quantity / 1000, 'l'],
            default => [$this->base_quantity, $this->base_unit],
        };
        $label = ShopSettings::BASE_UNITS[$unit] ?? $unit;

        return Money::format((int) round($this->price_cents / $qty)).' / 1 '.$label;
    }

    public function deliveryTime(): string
    {
        return $this->delivery_time ?: ShopSettings::deliveryTime();
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'produkt';
        $slug = $base;
        for ($i = 2; static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
