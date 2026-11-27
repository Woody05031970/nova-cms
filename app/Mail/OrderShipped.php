<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderShipped extends Mailable
{
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Ihre Bestellung '.$this->order->number.' ist unterwegs');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.order-shipped', with: ['order' => $this->order, 'shop' => Setting::get('site_name', config('app.name'))]);
    }
}
