<?php

namespace App\Models;

use App\Plugins\Hooks;
use App\Shop\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUSES = [
        'new' => 'Neu',
        'processing' => 'In Bearbeitung',
        'shipped' => 'Versendet',
        'completed' => 'Abgeschlossen',
        'cancelled' => 'Storniert',
    ];

    public const PAYMENT_STATUSES = ['open' => 'Offen', 'paid' => 'Bezahlt', 'failed' => 'Fehlgeschlagen'];

    protected $fillable = [
        'number', 'token', 'status', 'payment_method', 'payment_status', 'payment_reference', 'currency',
        'email', 'name', 'company', 'street', 'zip', 'city', 'country', 'phone', 'notes',
        'subtotal_cents', 'shipping_cents', 'total_cents', 'taxes', 'small_business', 'tracking_number',
        'terms_accepted_at', 'paid_at', 'shipped_at',
    ];

    protected function casts(): array
    {
        return [
            'taxes' => 'array', 'small_business' => 'boolean',
            'terms_accepted_at' => 'datetime', 'paid_at' => 'datetime', 'shipped_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function money(int $cents): string
    {
        return Money::format($cents, $this->currency);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function markPaid(?string $reference = null): void
    {
        if ($this->isPaid()) {
            return;
        }
        $this->forceFill([
            'payment_status' => 'paid',
            'paid_at' => now(),
            'payment_reference' => $reference ?? $this->payment_reference,
            'status' => $this->status === 'new' ? 'processing' : $this->status,
        ])->save();
        app(Hooks::class)->doAction('shop.order.paid', $this);
    }

    public static function nextNumber(): string
    {
        $year = now()->format('Y');
        $last = static::where('number', 'like', $year.'-%')->orderByDesc('id')->value('number');
        $n = $last ? (int) substr($last, 5) + 1 : 1;

        return $year.'-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
