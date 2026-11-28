<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityProfile extends Model
{
    /** Farben für den Kopf der eigenen Seite */
    public const COLORS = [
        'petrol' => ['Petrol', 'from-[#0a4b5a] to-[#19c3dc]'],
        'orange' => ['Orange', 'from-orange-500 to-amber-400'],
        'violett' => ['Violett', 'from-violet-600 to-fuchsia-500'],
        'gruen' => ['Grün', 'from-emerald-600 to-lime-500'],
        'blau' => ['Blau', 'from-sky-700 to-cyan-500'],
        'nacht' => ['Nacht', 'from-slate-900 to-slate-700'],
    ];

    protected $fillable = ['user_id', 'handle', 'headline', 'bio', 'avatar', 'cover', 'location', 'website', 'page_title', 'page_color',
        'page_sections', 'page_published', 'email_notifications', 'messages_open', 'last_seen_at', 'banned_at'];

    protected function casts(): array
    {
        return ['page_sections' => 'array', 'page_published' => 'boolean', 'email_notifications' => 'boolean', 'messages_open' => 'boolean',
            'last_seen_at' => 'datetime', 'banned_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRouteKeyName(): string
    {
        return 'handle';
    }

    public function url(): string
    {
        return route('community.profile', $this);
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(5));
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function colorClass(): string
    {
        return self::COLORS[$this->page_color][1] ?? self::COLORS['petrol'][1];
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->user?->name)) ?: [];

        return mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1).(count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
    }
}
