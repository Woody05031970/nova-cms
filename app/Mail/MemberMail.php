<?php

namespace App\Mail;

use App\Models\Setting;
use App\Models\User;
use App\Support\Members\Members;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** E-Mails im Mitgliederbereich: reset, invite, approved, owner (neue Registrierung) */
class MemberMail extends Mailable
{
    public function __construct(public User $user, public string $kind, public string $url) {}

    public function envelope(): Envelope
    {
        $site = Setting::get('site_name', config('app.name'));

        return new Envelope(subject: match ($this->kind) {
            'invite' => 'Ihre Einladung: '.Members::title().' von '.$site,
            'approved' => 'Ihr Zugang ist freigeschaltet: '.$site,
            'owner' => 'Neue Registrierung im '.Members::title(),
            default => 'Passwort zurücksetzen: '.$site,
        });
    }

    public function content(): Content
    {
        return new Content(view: 'mail.member', with: ['site' => Setting::get('site_name', config('app.name'))]);
    }
}
