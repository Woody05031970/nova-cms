<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Gespräch zwischen genau zwei Personen (user_one_id ist immer die kleinere Nummer) */
class CommunityConversation extends Model
{
    protected $fillable = ['user_one_id', 'user_two_id', 'one_read_at', 'two_read_at', 'last_message_at'];

    protected function casts(): array
    {
        return ['one_read_at' => 'datetime', 'two_read_at' => 'datetime', 'last_message_at' => 'datetime'];
    }

    public static function between(User $a, User $b): self
    {
        [$one, $two] = $a->id < $b->id ? [$a->id, $b->id] : [$b->id, $a->id];

        return self::firstOrCreate(['user_one_id' => $one, 'user_two_id' => $two]);
    }

    public function scopeFor(Builder $q, User $u): Builder
    {
        return $q->where(fn ($w) => $w->where('user_one_id', $u->id)->orWhere('user_two_id', $u->id));
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CommunityMessage::class, 'conversation_id');
    }

    public function involves(User $u): bool
    {
        return in_array($u->id, [$this->user_one_id, $this->user_two_id], true);
    }

    public function other(User $u): ?User
    {
        return $this->user_one_id === $u->id ? $this->userTwo : $this->userOne;
    }

    public function isUnreadFor(User $u): bool
    {
        $read = $this->user_one_id === $u->id ? $this->one_read_at : $this->two_read_at;

        return $this->last_message_at && (! $read || $read->lt($this->last_message_at));
    }

    public function markRead(User $u): void
    {
        $this->forceFill([$this->user_one_id === $u->id ? 'one_read_at' : 'two_read_at' => now()])->save();
    }
}
