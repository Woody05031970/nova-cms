<?php

namespace App\Models;

use App\Shop\Money;
use Illuminate\Database\Eloquent\Model;

/** Buchbare Leistung, z. B. „Beratung, 30 Minuten“ (Modul Terminbuchung) */
class Service extends Model
{
    protected $fillable = ['name', 'description', 'duration', 'buffer', 'price_cents', 'is_active', 'sort'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'duration' => 'integer', 'buffer' => 'integer', 'price_cents' => 'integer'];
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('sort')->orderBy('name');
    }

    public function durationLabel(): string
    {
        $h = intdiv($this->duration, 60);
        $m = $this->duration % 60;

        return trim(($h ? $h.' Std. ' : '').($m ? $m.' Min.' : ''));
    }

    public function priceLabel(): ?string
    {
        return $this->price_cents === null ? null : Money::format($this->price_cents);
    }
}
