<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Eine Datei in der Mediathek (Bild oder Video unter public/uploads).
 */
class Media extends Model
{
    protected $table = 'media';

    protected $fillable = ['kind', 'path', 'thumb', 'original_name', 'mime', 'size', 'width', 'height', 'alt', 'title', 'user_id'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function url(): string
    {
        return '/'.$this->path;
    }

    public function thumbUrl(): string
    {
        return '/'.($this->thumb ?: $this->path);
    }

    public function isImage(): bool
    {
        return $this->kind === 'image';
    }

    public function humanSize(): string
    {
        return self::formatBytes($this->size);
    }

    public static function formatBytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 ** 3 => number_format($bytes / 1024 ** 3, 1, ',', '.').' GB',
            $bytes >= 1024 ** 2 => number_format($bytes / 1024 ** 2, 1, ',', '.').' MB',
            default => max(1, (int) round($bytes / 1024)).' KB',
        };
    }

    /**
     * Seiten, auf denen die Datei verwendet wird (auch Entwürfe und Papierkorb).
     *
     * @return Collection<int, Page>
     */
    public function usages(): Collection
    {
        // Seiten sind wenige; ein Vergleich in PHP ist zuverlässiger als LIKE auf JSON.
        $pages = Page::withTrashed()->get(['id', 'title', 'blocks', 'deleted_at'])
            ->filter(fn (Page $p) => str_contains(json_encode($p->blocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $this->url()));

        // Produktbilder (Produkt erscheint mit seinem Namen als Titel)
        $products = Product::query()->get(['id', 'name', 'images', 'description'])
            ->filter(fn (Product $p) => in_array($this->url(), $p->images ?? [], true) || str_contains((string) $p->description, $this->url()))
            ->each(fn (Product $p) => $p->setAttribute('title', 'Produkt: '.$p->name));

        return $pages->concat($products)->values();
    }

    /** @return array<string, mixed> */
    public function toPicker(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'url' => $this->url(),
            'thumb' => $this->thumbUrl(),
            'name' => $this->title ?: $this->original_name,
            'alt' => $this->alt,
            'width' => $this->width,
            'height' => $this->height,
            'size' => $this->humanSize(),
            'bytes' => $this->size,
        ];
    }
}
