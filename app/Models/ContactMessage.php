<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Eine Anfrage aus dem Kontaktformular der Website */
class ContactMessage extends Model
{
    protected $fillable = ['name', 'email', 'subject', 'message', 'page_id', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class)->withTrashed();
    }

    public static function unread(): int
    {
        return static::whereNull('read_at')->count();
    }
}
