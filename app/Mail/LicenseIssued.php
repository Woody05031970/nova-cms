<?php

namespace App\Mail;

use App\Models\IssuedLicense;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LicenseIssued extends Mailable
{
    /** @param list<IssuedLicense> $licenses */
    public function __construct(public Order $order, public array $licenses) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Ihr Lizenzschlüssel zur Bestellung '.$this->order->number);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.license-issued', with: ['shop' => Setting::get('site_name', config('app.name'))]);
    }
}
