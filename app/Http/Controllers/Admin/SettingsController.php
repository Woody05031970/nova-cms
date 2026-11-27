<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public const FIELDS = ['site_name', 'tagline', 'industry', 'company_name', 'street', 'zip_city', 'phone', 'contact_email'];

    public function edit(): View
    {
        $values = collect(self::FIELDS)->mapWithKeys(fn ($f) => [$f => Setting::get($f)]);

        return view('admin.settings', [
            'values' => $values,
            'mail' => collect(['mail_mode', 'mail_host', 'mail_port', 'mail_user', 'mail_from'])->mapWithKeys(fn ($f) => [$f => Setting::get($f)]),
            'hasMailPassword' => Setting::get('mail_password') !== null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'industry' => ['nullable', 'string', 'max:120'],
            'company_name' => ['nullable', 'string', 'max:160'],
            'street' => ['nullable', 'string', 'max:160'],
            'zip_city' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:60'],
            'contact_email' => ['nullable', 'email', 'max:255'],
        ], ['site_name.required' => 'Bitte geben Sie einen Namen für Ihre Website an.']);

        Setting::putMany($data);

        return back()->with('status', 'Gespeichert.');
    }

    public function mail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mail_mode' => ['nullable', \Illuminate\Validation\Rule::in(['', 'sendmail', 'smtp'])],
            'mail_host' => ['nullable', 'required_if:mail_mode,smtp', 'string', 'max:190', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_user' => ['nullable', 'string', 'max:190'],
            'mail_password' => ['nullable', 'string', 'max:190'],
            'mail_from' => ['nullable', 'email', 'max:190'],
        ], ['mail_host.required_if' => 'Bitte geben Sie den Postausgangsserver an, z. B. smtp.ionos.de.']);

        Setting::putMany([
            'mail_mode' => $data['mail_mode'] ?: null,
            'mail_host' => $data['mail_host'] ?? null,
            'mail_port' => isset($data['mail_port']) ? (string) $data['mail_port'] : null,
            'mail_user' => $data['mail_user'] ?? null,
            'mail_from' => $data['mail_from'] ?? null,
        ]);
        if (! empty($data['mail_password'])) {
            \App\Shop\ShopSettings::putSecret('mail_password', $data['mail_password']);
        }

        return back()->with('status', 'E-Mail-Einstellungen gespeichert. Schicken Sie sich zur Probe eine Test-E-Mail.');
    }

    public function testMail(Request $request): RedirectResponse
    {
        $to = $request->user()->email;
        try {
            \Illuminate\Support\Facades\Mail::raw('Diese Test-E-Mail kommt von Ihrer Website. Der E-Mail-Versand funktioniert.', function ($m) use ($to) {
                $m->to($to)->subject('Test-E-Mail von Nova CMS');
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['mail' => 'Die E-Mail konnte nicht gesendet werden: '.\Illuminate\Support\Str::limit($e->getMessage(), 200)]);
        }

        return back()->with('status', "Test-E-Mail an {$to} wurde verschickt. Schauen Sie auch im Spam-Ordner nach.");
    }
}
