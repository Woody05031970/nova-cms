<?php

namespace App\Mail;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class VoucherMail extends Mailable
{
    /** @param list<Coupon> $coupons */
    public function __construct(public Order $order, public array $coupons) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: (count($this->coupons) === 1 ? 'Ihr Geschenkgutschein' : 'Ihre Geschenkgutscheine').' zur Bestellung '.$this->order->number);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.voucher', with: ['site' => Setting::get('site_name', config('app.name'))]);
    }
}
