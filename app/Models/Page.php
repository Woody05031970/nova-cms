<?php

namespace App\Models;

use App\Licensing\Features;
use App\Support\Blocks\BlockSanitizer;
use App\Support\I18n\Languages;
use App\Support\Seo\PageAnalyzer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Page extends Model
{
    use SoftDeletes;

    public const MAX_REVISIONS = 20;

    protected $fillable = [
        'title', 'slug', 'status', 'blocks', 'meta_title', 'meta_description', 'focus_keyword',
        'is_home', 'show_in_menu', 'menu_order', 'purpose', 'published_at', 'updated_by', 'locale', 'translation_of', 'visibility',
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

    protected static function booted(): void
    {
        // "Im Menü anzeigen" und die Menüverwaltung bleiben im Einklang
        static::saved(function (Page $page) {
            if ($page->wasRecentlyCreated || $page->wasChanged('show_in_menu')) {
                $page->show_in_menu ? MenuItem::addPage($page) : MenuItem::removePage($page);
            }
            // Neue Adresse einer veröffentlichten Seite: alte Links und Google-Treffer leiten weiter
            $previous = $page->getPrevious();
            if (isset($previous['slug']) && $page->locale === null && $page->wasChanged('slug') && ($previous['status'] ?? $page->status) === 'published' && ! $page->is_home) {
                Redirect::remember('/'.$previous['slug'], '/'.$page->slug);
            }
        });
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->latest('id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /** Zeitgesteuert: wird zum Termin in published_at automatisch veröffentlicht (Modul Redaktionsplan) */
    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function socialPosts(): HasMany
    {
        return $this->hasMany(SocialPost::class)->latest('id');
    }

    public function url(): string
    {
        if ($this->locale !== null) {
            return $this->source?->is_home ? route('home.locale', $this->locale) : route('page.locale', [$this->locale, $this->slug]);
        }

        return $this->is_home ? route('home') : route('page.show', $this->slug);
    }

    /** Deutsche Ausgangsseite einer Übersetzung (Modul Mehrsprachigkeit) */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'translation_of');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(Page::class, 'translation_of');
    }

    /** Nur für angemeldete Mitglieder sichtbar (Modul Mitgliederbereich) */
    public function isMembersOnly(): bool
    {
        return $this->visibility === 'members' && Features::on('mitgliederbereich');
    }

    /** Seiten in der Hauptsprache, ohne Übersetzungen */
    public function scopeMain($query)
    {
        return $query->whereNull('translation_of');
    }

    /** Für Gäste sichtbar: keine Seiten nur für Mitglieder */
    public function scopePublic($query)
    {
        return Features::on('mitgliederbereich') ? $query->where(fn ($q) => $q->whereNull('visibility')->orWhere('visibility', '!=', 'members')) : $query;
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public static function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value, '-', 'de') ?: 'seite';
        // Diese Pfade gehören dem System
        if (in_array($base, ['admin', 'install', 'uploads', 'build', 'up', 'storage', 'termine', 'mitglieder', 'konto', 'passwort-neu', 'api', 'de', ...array_keys(Languages::AVAILABLE)], true)) {
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
                'hero' => trim(($b['data']['title'] ?? '').' '.($b['data']['text'] ?? '')),
                'features', 'pricing', 'modules', 'compare', 'faq', 'cta' => PageAnalyzer::structuredText($b['data'] ?? []),
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
