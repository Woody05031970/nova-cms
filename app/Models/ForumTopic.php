<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForumTopic extends Model
{
    protected $fillable = ['board_id', 'user_id', 'title', 'slug', 'body', 'is_pinned', 'is_locked', 'last_reply_at', 'last_user_id'];

    protected function casts(): array
    {
        return ['is_pinned' => 'boolean', 'is_locked' => 'boolean', 'last_reply_at' => 'datetime'];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(ForumBoard::class, 'board_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_user_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ForumReply::class, 'topic_id')->orderBy('id');
    }

    public function url(): string
    {
        return route('community.topic', ['topic' => $this->id, 'slug' => $this->slug ?: 'thema']);
    }
}
