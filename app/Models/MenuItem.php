<?php

namespace App\Models;

use App\Shop\ShopSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ein Punkt im Hauptmenü der Website: Seite, eigener Link oder Shop. Zwei Ebenen (Haupt- und Unterpunkte). */
class MenuItem extends Model
{
    public const TYPES = ['page', 'link', 'shop'];

    protected $fillable = ['parent_id', 'position', 'type', 'page_id', 'label', 'url', 'new_tab', 'is_visible'];

    protected function casts(): array
    {
        return ['new_tab' => 'boolean', 'is_visible' => 'boolean', 'position' => 'integer'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class)->withTrashed();
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function title(): string
    {
        return $this->label ?: match ($this->type) {
            'page' => $this->page?->title ?? 'Gelöschte Seite',
            'shop' => 'Shop',
            default => $this->url ?? 'Link',
        };
    }

    public function href(): string
    {
        return match ($this->type) {
            'page' => $this->page?->url() ?? '#',
            'shop' => route('shop.index'),
            default => (string) $this->url,
        };
    }

    /** Ist der Punkt auf der Website zu sehen? Seiten nur, wenn sie veröffentlicht sind. */
    public function isLive(): bool
    {
        return $this->is_visible && match ($this->type) {
            'page' => $this->page && ! $this->page->trashed() && $this->page->isPublished(),
            'shop' => ShopSettings::enabled(),
            default => (string) $this->url !== '',
        };
    }

    /** Hängt eine Seite unten an das Menü an, falls sie noch nicht drin ist. */
    public static function addPage(Page $page): void
    {
        if (! static::where('page_id', $page->id)->exists()) {
            static::create(['type' => 'page', 'page_id' => $page->id, 'position' => (int) static::whereNull('parent_id')->max('position') + 1]);
        }
    }

    /** Nimmt eine Seite aus dem Menü; ihre Unterpunkte rücken eine Ebene nach oben. */
    public static function removePage(Page $page): void
    {
        foreach (static::where('page_id', $page->id)->get() as $item) {
            static::where('parent_id', $item->id)->update(['parent_id' => $item->parent_id]);
            $item->delete();
        }
    }
}
