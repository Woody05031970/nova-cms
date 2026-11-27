<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Eintrag im Redaktionsplan: Thema, Aktion oder Beitrag an einem Tag. */
class EditorialItem extends Model
{
    public const CHANNELS = [
        'website' => 'Website', 'newsletter' => 'Newsletter', 'facebook' => 'Facebook',
        'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'aktion' => 'Aktion',
    ];

    protected $fillable = ['title', 'date', 'channel', 'notes', 'done'];

    protected function casts(): array
    {
        return ['date' => 'date', 'done' => 'boolean'];
    }
}
