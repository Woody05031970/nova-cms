<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ForumBoard extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'position', 'is_locked'];

    protected function casts(): array
    {
        return ['is_locked' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function topics(): HasMany
    {
        return $this->hasMany(ForumTopic::class, 'board_id');
    }

    public static function uniqueSlug(string $name, ?int $ignore = null): string
    {
        $base = Str::slug($name) ?: 'forum';
        $slug = $base;
        for ($i = 2; self::where('slug', $slug)->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    public function url(): string
    {
        return route('community.board', $this);
    }

    /** Zähler neu berechnen (nach Löschen) */
    public function recount(): void
    {
        $this->forceFill([
            'topics_count' => $this->topics()->count(),
            'replies_count' => ForumReply::whereIn('topic_id', $this->topics()->select('id'))->count(),
        ])->save();
    }
}
