<?php

namespace App\Models;

use App\Support\Blocks\BlockSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Page extends Model
{
    use SoftDeletes;

    public const MAX_REVISIONS = 20;

    protected $fillable = [
        'title', 'slug', 'status', 'blocks', 'meta_title', 'meta_description',
        'is_home', 'show_in_menu', 'menu_order', 'purpose', 'published_at', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'is_home' => 'boolean',
            'show_in_menu' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->latest('id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function url(): string
    {
        return $this->is_home ? route('home') : route('page.show', $this->slug);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public static function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value, '-', 'de') ?: 'seite';
        // Diese Pfade gehören dem System
        if (in_array($base, ['admin', 'install', 'uploads', 'build', 'up', 'storage'], true)) {
            $base .= '-seite';
        }

        $slug = $base;
        $i = 2;
        while (static::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /** Reiner Text aller Blöcke, z. B. für Beschreibungs-Vorschläge. */
    public function plainText(): string
    {
        return trim(preg_replace('/\s+/', ' ', collect($this->blocks ?? [])
            ->map(fn ($b) => match ($b['type'] ?? null) {
                'text' => strip_tags(str_replace('<', ' <', $b['data']['html'] ?? '')),
                'button' => $b['data']['label'] ?? '',
                default => '',
            })->implode(' ')));
    }

    public function saveRevision(?int $userId): void
    {
        $this->revisions()->create([
            'title' => $this->title,
            'blocks' => $this->blocks,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'user_id' => $userId,
            'created_at' => now(),
        ]);

        $keep = $this->revisions()->limit(self::MAX_REVISIONS)->pluck('id');
        $this->revisions()->whereNotIn('id', $keep)->delete();
    }

    /** @param array<int, mixed> $blocks */
    public static function cleanBlocks(array $blocks): array
    {
        return app(BlockSanitizer::class)->clean($blocks);
    }
}
