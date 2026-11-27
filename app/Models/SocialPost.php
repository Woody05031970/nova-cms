<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ein Beitrag für Facebook oder Instagram, sofort oder mit der Seite veröffentlicht. */
class SocialPost extends Model
{
    public const NETWORKS = ['facebook' => 'Facebook', 'instagram' => 'Instagram'];

    protected $fillable = ['network', 'text', 'link', 'image', 'status', 'external_id', 'error', 'page_id', 'scheduled_at', 'sent_at'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function networkName(): string
    {
        return self::NETWORKS[$this->network] ?? $this->network;
    }
}
