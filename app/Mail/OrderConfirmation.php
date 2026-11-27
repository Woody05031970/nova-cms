<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Setting;
use App\Shop\Payments\PaymentRegistry;
use App\Shop\ShopSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Bestellbestätigung an den Kunden, mit AGB und Widerrufsbelehrung (dauerhafter Datenträger). */
class OrderConfirmation extends Mailable
{
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Ihre Bestellung '.$this->order->number.' bei '.Setting::get('site_name', config('app.name')));
    }

    public function content(): Content
    {
        $method = app(PaymentRegistry::class)->get($this->order->payment_method);

        return new Content(view: 'mail.order-confirmation', with: [
            'order' => $this->order,
            'forCustomer' => true,
            'payment' => $method,
            'instructions' => $method?->instructions($this->order),
            'terms' => ShopSettings::legalPage('terms'),
            'withdrawal' => ShopSettings::legalPage('withdrawal'),
        ]);
    }
}
