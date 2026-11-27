<?php

namespace App\Http\Controllers;

use App\Mail\NewsletterConfirm;
use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/** Öffentliche Seite des Newsletters: Anmeldung mit Bestätigung (Double-Opt-in) und Abmeldung. */
class NewsletterController extends Controller
{
    public function subscribe(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
            'consent' => ['accepted'],
            'block' => ['nullable', 'string', 'max:24'],
        ], ['consent.accepted' => 'Bitte bestätigen Sie den Hinweis zum Datenschutz.', 'email.email' => 'Bitte prüfen Sie die E-Mail-Adresse.']);

        $flash = ['newsletter_sent' => $data['block'] ?? 'x'];
        if ($request->filled('website') || ! $this->humanSpeed((string) $request->input('_t'))) {
            return back()->with($flash);
        }

        $email = mb_strtolower(trim($data['email']));
        $sub = NewsletterSubscriber::firstOrNew(['email' => $email]);
        if ($sub->exists && $sub->status === 'confirmed') {
            return back()->with($flash); // Kein Hinweis, ob jemand schon angemeldet ist
        }
        $sub->fill(['name' => $data['name'] ?? $sub->name, 'status' => 'pending', 'source' => mb_substr((string) $request->headers->get('referer'), 0, 250)]);
        $sub->save();

        try {
            Mail::to($sub->email)->send(new NewsletterConfirm($sub));
        } catch (Throwable $e) {
            report($e);
        }

        return back()->with($flash);
    }

    public function confirm(string $token): View
    {
        $sub = NewsletterSubscriber::where('token', $token)->firstOrFail();
        if ($sub->status !== 'unsubscribed') {
            $sub->confirm();
        }

        return $this->page('Danke für Ihre Anmeldung!', $sub->status === 'confirmed'
            ? 'Ihre E-Mail-Adresse ist bestätigt. Ab jetzt erhalten Sie unseren Newsletter. Abmelden können Sie sich jederzeit über den Link in jeder E-Mail.'
            : 'Diese Adresse wurde abgemeldet. Melden Sie sich bei Bedarf einfach neu an.');
    }

    public function unsubscribeForm(string $token): View
    {
        $sub = NewsletterSubscriber::where('token', $token)->firstOrFail();

        return $this->page('Newsletter abbestellen', $sub->status === 'unsubscribed'
            ? 'Sie sind bereits abgemeldet.'
            : 'Möchten Sie den Newsletter für '.$sub->email.' wirklich abbestellen?', $sub->status === 'unsubscribed' ? null : $token);
    }

    public function unsubscribe(string $token): View
    {
        NewsletterSubscriber::where('token', $token)->firstOrFail()->unsubscribe();

        return $this->page('Sie sind abgemeldet', 'Sie erhalten keine Newsletter mehr. Schade, dass Sie gehen!');
    }

    private function page(string $title, string $text, ?string $unsubscribeToken = null): View
    {
        return view('site.notice', ['title' => $title, 'text' => $text, 'unsubscribeToken' => $unsubscribeToken]);
    }

    private function humanSpeed(string $token): bool
    {
        try {
            return now()->getTimestamp() - (int) decrypt($token) >= 2;
        } catch (DecryptException) {
            return false;
        }
    }
}
