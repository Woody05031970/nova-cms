<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BlockedIp extends Model
{
    protected $fillable = ['ip', 'reason', 'until'];

    protected function casts(): array
    {
        return ['until' => 'datetime'];
    }

    public function scopeActive($query)
    {
        return $query->where(fn ($q) => $q->whereNull('until')->orWhere('until', '>', now()));
    }

    public static function isBlocked(string $ip): bool
    {
        // Liste kurz zwischenspeichern, damit nicht jeder Seitenaufruf die Datenbank fragt
        $list = Cache::remember('nova.blocked_ips', 60, fn () => static::query()
            ->where(fn ($q) => $q->whereNull('until')->orWhere('until', '>', now()))
            ->pluck('until', 'ip')->map(fn ($u) => $u?->getTimestamp())->all());

        return array_key_exists($ip, $list) && ($list[$ip] === null || $list[$ip] > time());
    }

    public static function block(string $ip, string $reason, ?int $minutes = null): self
    {
        $entry = static::updateOrCreate(['ip' => $ip], ['reason' => $reason, 'until' => $minutes ? now()->addMinutes($minutes) : null]);
        Cache::forget('nova.blocked_ips');
        SecurityEvent::record('blocked', $reason.($minutes ? " (für {$minutes} Minuten)" : ' (dauerhaft)'), null, $ip);

        return $entry;
    }

    public static function unblock(string $ip): void
    {
        static::where('ip', $ip)->delete();
        Cache::forget('nova.blocked_ips');
    }
}
