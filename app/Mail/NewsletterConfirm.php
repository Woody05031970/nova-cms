<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use App\Models\Setting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewsletterConfirm extends Mailable
{
    public function __construct(public NewsletterSubscriber $subscriber) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Bitte bestätigen Sie Ihre Newsletter-Anmeldung');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.newsletter-confirm', with: [
            'site' => Setting::get('site_name', config('app.name')),
            'url' => route('newsletter.confirm', $this->subscriber->token),
        ]);
    }
}
