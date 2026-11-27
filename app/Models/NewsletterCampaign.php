<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterCampaign extends Model
{
    protected $fillable = ['subject', 'preheader', 'heading', 'body', 'image', 'button_label', 'button_url', 'status', 'cursor', 'sent_count', 'failed_count', 'total', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    /** Absätze aus dem Text (Leerzeile = neuer Absatz) */
    public function paragraphs(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', $this->body)), fn ($p) => $p !== ''));
    }

    public function statusLabel(): string
    {
        return ['draft' => 'Entwurf', 'sending' => 'Wird versendet', 'sent' => 'Versendet'][$this->status] ?? $this->status;
    }
}
