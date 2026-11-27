<?php

namespace App\Http\Controllers;

use App\Mail\ContactReceived;
use App\Models\ContactMessage;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Nimmt Nachrichten aus dem Kontaktformular-Block entgegen. */
class ContactController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:80'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
            'consent' => ['accepted'],
            'block' => ['nullable', 'string', 'max:24'],
            'page' => ['nullable', 'integer'],
        ], [
            'message.min' => 'Bitte schreiben Sie ein paar Worte mehr.',
            'consent.accepted' => 'Bitte bestätigen Sie den Hinweis zum Datenschutz.',
        ]);

        // Spam-Schutz: verstecktes Feld ausgefüllt oder Formular in unter 3 Sekunden abgeschickt
        if ($request->filled('website') || ! $this->humanSpeed((string) $request->input('_t'))) {
            return back()->with('contact_sent', $data['block'] ?? 'x');
        }

        $page = ! empty($data['page']) ? Page::find($data['page']) : null;
        $contact = ContactMessage::create([
            'name' => $data['name'], 'email' => $data['email'], 'subject' => $data['subject'] ?? null,
            'message' => $data['message'], 'page_id' => $page?->id,
        ]);

        if ($to = Setting::get('contact_email')) {
            try {
                Mail::to($to)->send(new ContactReceived($contact));
            } catch (Throwable $e) {
                report($e); // Die Nachricht bleibt in der Verwaltung gespeichert
            }
        }

        return back()->with('contact_sent', $data['block'] ?? 'x');
    }

    private function humanSpeed(string $token): bool
    {
        try {
            $sent = (int) decrypt($token);
        } catch (DecryptException) {
            return false;
        }

        return now()->getTimestamp() - $sent >= 3;
    }
}
