<?php

namespace App\Models;

use App\Shop\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Rabattcode oder Geschenkgutschein (Modul Gutscheine und Rabatte).
 * - percent: Prozent auf den Warenwert
 * - fixed: fester Betrag in Cent; Geschenkgutscheine behalten ihren Restwert
 * - shipping: versandkostenfrei
 */
class Coupon extends Model
{
    public const KINDS = ['percent' => 'Prozent', 'fixed' => 'Fester Betrag', 'shipping' => 'Versandkostenfrei'];

    protected $fillable = ['code', 'kind', 'value', 'min_order_cents', 'starts_at', 'ends_at', 'max_uses', 'used', 'is_gift', 'order_id', 'note', 'is_active'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_gift' => 'boolean', 'is_active' => 'boolean', 'value' => 'integer', 'used' => 'integer', 'min_order_cents' => 'integer', 'max_uses' => 'integer'];
    }

    public static function normalize(string $code): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9-]/', '', $code));
    }

    public static function generateCode(string $prefix = 'GUT'): string
    {
        do {
            $code = $prefix.'-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
            $code = strtr($code, ['0' => 'X', 'O' => 'Y', 'I' => 'Z', '1' => 'W', 'L' => 'V']);
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public static function findByCode(string $code): ?self
    {
        return self::where('code', self::normalize($code))->first();
    }

    /** Fehlermeldung, falls der Code für diesen Warenwert nicht gilt, sonst null */
    public function problem(int $subtotal): ?string
    {
        return match (true) {
            ! $this->is_active || ($this->is_gift && $this->value <= 0) => 'Dieser Gutschein ist nicht mehr gültig.',
            $this->starts_at !== null && $this->starts_at->isFuture() => 'Dieser Code gilt erst ab '.$this->starts_at->format('d.m.Y').'.',
            $this->ends_at !== null && $this->ends_at->isPast() => 'Dieser Code ist leider abgelaufen.',
            $this->max_uses !== null && $this->used >= $this->max_uses => 'Dieser Code wurde bereits eingelöst.',
            $this->min_order_cents !== null && $subtotal < $this->min_order_cents => 'Dieser Code gilt ab einem Bestellwert von '.Money::format($this->min_order_cents).'.',
            default => null,
        };
    }

    /** Rabatt auf den Warenwert in Cent */
    public function discountFor(int $subtotal): int
    {
        return match ($this->kind) {
            'percent' => (int) round($subtotal * min(100, $this->value) / 100),
            'fixed' => min($subtotal, $this->value),
            default => 0,
        };
    }

    public function label(): string
    {
        return match ($this->kind) {
            'percent' => $this->value.' %',
            'fixed' => Money::format($this->value).($this->is_gift ? ' Guthaben' : ''),
            default => 'Versandkostenfrei',
        };
    }
}
