<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityReport extends Model
{
    public const TYPES = ['post' => 'Beitrag', 'comment' => 'Kommentar', 'topic' => 'Forenthema', 'reply' => 'Forenantwort', 'message' => 'Nachricht', 'profile' => 'Profil'];

    protected $fillable = ['user_id', 'type', 'target_id', 'reason', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Das gemeldete Objekt (oder null, wenn schon gelöscht) */
    public function target(): ?Model
    {
        $class = ['post' => CommunityPost::class, 'comment' => CommunityComment::class, 'topic' => ForumTopic::class,
            'reply' => ForumReply::class, 'message' => CommunityMessage::class, 'profile' => CommunityProfile::class][$this->type] ?? null;

        return $class ? $class::find($this->target_id) : null;
    }
}
