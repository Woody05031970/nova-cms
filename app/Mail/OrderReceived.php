<?php

namespace App\Mail;

use App\Models\Order;
use App\Shop\Payments\PaymentRegistry;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Benachrichtigung an den Shop-Betreiber */
class OrderReceived extends Mailable
{
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Neue Bestellung '.$this->order->number.' über '.$this->order->money($this->order->total_cents));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.order-confirmation', with: [
            'order' => $this->order,
            'forCustomer' => false,
            'payment' => app(PaymentRegistry::class)->get($this->order->payment_method),
            'instructions' => null, 'terms' => null, 'withdrawal' => null,
        ]);
    }
}
