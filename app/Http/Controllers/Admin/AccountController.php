<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use App\Support\Security\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * "Mein Konto": Name, E-Mail, Passwort und Zwei-Faktor-Anmeldung.
 */
class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        $setup = null;

        // Während der Einrichtung liegt das Geheimnis nur in der Sitzung, bis der erste Code stimmt.
        if (! $user->hasTwoFactor() && ($secret = $request->session()->get('nova.2fa_setup'))) {
            $uri = Totp::uri($secret, $user->email, (string) config('app.name', 'Nova CMS'));
            $setup = ['secret' => trim(chunk_split($secret, 4, ' ')), 'qr' => Totp::qrSvg($uri)];
        }

        return view('admin.account', [
            'user' => $user,
            'setup' => $setup,
            'recoveryCodes' => $request->session()->get('nova.recovery_codes'),
            'remaining' => count($user->two_factor_recovery_codes ?? []),
            'events' => SecurityEvent::where('user_id', $user->id)->latest('id')->limit(8)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);
        $user->update($data);

        return back()->with('status', 'Ihre Angaben wurden gespeichert.');
    }

    public function password(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ], [
            'current_password.current_password' => 'Das bisherige Passwort stimmt nicht.',
            'password.confirmed' => 'Die beiden neuen Passwörter stimmen nicht überein.',
            'password.min' => 'Das Passwort braucht mindestens 10 Zeichen.',
        ]);

        $request->user()->update(['password' => $request->input('password')]);
        // Andere angemeldete Geräte abmelden
        auth()->logoutOtherDevices($request->input('password'));
        SecurityEvent::record('password_changed', null, $request->user()->id);

        return back()->with('status', 'Ihr Passwort wurde geändert. Andere Geräte wurden abgemeldet.');
    }

    public function startTwoFactor(Request $request): RedirectResponse
    {
        if (! $request->user()->hasTwoFactor()) {
            $request->session()->put('nova.2fa_setup', Totp::generateSecret());
        }

        return redirect()->to(route('admin.account').'#zwei-faktor');
    }

    public function confirmTwoFactor(Request $request): RedirectResponse
    {
        $secret = $request->session()->get('nova.2fa_setup');
        $request->validate(['code' => ['required', 'string', 'max:12']]);

        if (! $secret || ! Totp::verify($secret, (string) $request->input('code'))) {
            return redirect()->to(route('admin.account').'#zwei-faktor')
                ->withErrors(['code' => 'Der Code stimmt nicht. Achten Sie darauf, den aktuell angezeigten Code einzugeben.']);
        }

        $user = $request->user();
        $codes = $this->newRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(fn ($c) => password_hash(str_replace('-', '', $c), PASSWORD_DEFAULT), $codes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget('nova.2fa_setup');
        $request->session()->flash('nova.recovery_codes', $codes);
        SecurityEvent::record('two_factor_enabled', null, $user->id);

        return redirect()->to(route('admin.account').'#zwei-faktor')->with('status', 'Die Zwei-Faktor-Anmeldung ist aktiv.');
    }

    public function recoveryCodes(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']],
            ['current_password.current_password' => 'Das Passwort stimmt nicht.']);

        $codes = $this->newRecoveryCodes();
        $request->user()->forceFill([
            'two_factor_recovery_codes' => array_map(fn ($c) => password_hash(str_replace('-', '', $c), PASSWORD_DEFAULT), $codes),
        ])->save();
        $request->session()->flash('nova.recovery_codes', $codes);

        return redirect()->to(route('admin.account').'#zwei-faktor')->with('status', 'Neue Notfall-Codes erstellt. Die alten gelten nicht mehr.');
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']],
            ['current_password.current_password' => 'Das Passwort stimmt nicht.']);

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        SecurityEvent::record('two_factor_disabled', null, $request->user()->id);

        return redirect()->to(route('admin.account').'#zwei-faktor')->with('status', 'Die Zwei-Faktor-Anmeldung wurde ausgeschaltet.');
    }

    /** @return list<string> z. B. "K7QM-2XRT" */
    private function newRecoveryCodes(): array
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        return array_map(function () use ($alphabet) {
            $c = '';
            for ($i = 0; $i < 8; $i++) {
                $c .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            return substr($c, 0, 4).'-'.substr($c, 4);
        }, range(1, 8));
    }
}
