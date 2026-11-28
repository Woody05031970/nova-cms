<?php

namespace App\Mail;

use App\Models\Setting;
use App\Models\User;
use App\Support\Community\Community;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Hinweis aus der Community: neue Nachricht, Antwort im Forum, Erwähnung */
class CommunityMail extends Mailable
{
    public function __construct(public User $user, public string $text, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->text);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.community', with: ['site' => Setting::get('site_name', config('app.name')), 'title' => Community::title()]);
    }
}
