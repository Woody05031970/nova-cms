<?php

namespace App\Mail;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\Setting;
use App\Support\Design\SiteDesign;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class NewsletterMail extends Mailable
{
    public function __construct(public NewsletterCampaign $campaign, public ?NewsletterSubscriber $subscriber = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->subscriber ? '' : '[Test] ').$this->campaign->subject);
    }

    public function headers(): Headers
    {
        $url = $this->subscriber?->unsubscribeUrl();

        return new Headers(text: $url ? ['List-Unsubscribe' => '<'.$url.'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'] : []);
    }

    public function content(): Content
    {
        $design = SiteDesign::values();

        return new Content(view: 'mail.newsletter', with: [
            'c' => $this->campaign,
            'site' => Setting::get('site_name', config('app.name')),
            'brand' => $design['brand'],
            'logo' => $design['logo'] ? url($design['logo']) : null,
            'unsubscribe' => $this->subscriber?->unsubscribeUrl() ?? '#',
            'address' => collect([Setting::get('company_name'), Setting::get('street'), Setting::get('zip_city')])->filter()->implode(' · '),
        ]);
    }
}
