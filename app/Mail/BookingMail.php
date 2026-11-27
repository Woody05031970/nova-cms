<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Setting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** E-Mails zur Terminbuchung: an den Kunden (requested, confirmed, cancelled, reminder) oder an Sie (owner) */
class BookingMail extends Mailable
{
    public const SUBJECTS = [
        'requested' => 'Ihre Terminanfrage',
        'confirmed' => 'Ihr Termin ist bestätigt',
        'cancelled' => 'Ihr Termin wurde abgesagt',
        'reminder' => 'Erinnerung an Ihren Termin morgen',
        'owner' => 'Neue Terminbuchung',
    ];

    public function __construct(public Booking $booking, public string $kind) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::SUBJECTS[$this->kind].': '.$this->booking->service_name.', '.$this->booking->starts_at->format('d.m.Y H:i').' Uhr');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.booking', with: ['site' => Setting::get('site_name', config('app.name'))]);
    }

    public function attachments(): array
    {
        return in_array($this->kind, ['confirmed', 'reminder'], true)
            ? [Attachment::fromData(fn () => $this->booking->ics(), 'termin.ics')->withMime('text/calendar')]
            : [];
    }
}
