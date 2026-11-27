<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    protected $fillable = ['email', 'name', 'status', 'token', 'source', 'confirmed_at', 'unsubscribed_at'];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $s) => $s->token ??= Str::random(48));
    }

    public function scopeConfirmed(Builder $q): Builder
    {
        return $q->where('status', 'confirmed');
    }

    public function confirm(): void
    {
        $this->update(['status' => 'confirmed', 'confirmed_at' => $this->confirmed_at ?? now(), 'unsubscribed_at' => null]);
    }

    public function unsubscribe(): void
    {
        $this->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()]);
    }

    public function unsubscribeUrl(): string
    {
        return route('newsletter.unsubscribe', $this->token);
    }

    public function statusLabel(): string
    {
        return ['pending' => 'Wartet auf Bestätigung', 'confirmed' => 'Angemeldet', 'unsubscribed' => 'Abgemeldet'][$this->status] ?? $this->status;
    }
}
