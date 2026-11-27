<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['title', 'blocks', 'meta_title', 'meta_description', 'user_id', 'created_at'];

    protected function casts(): array
    {
        return ['blocks' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
