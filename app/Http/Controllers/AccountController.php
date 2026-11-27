<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\User;
use App\Shop\ShopSettings;
use App\Support\Members\Accounts;
use App\Support\Members\Members;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/** Modul „Kundenkonto“: Registrierung, Anmeldung, Adressen und Bestellungen der Shop-Kunden */
class AccountController extends Controller
{
    public function home(): View|RedirectResponse
    {
        if (! $u = Accounts::customer()) {
            return redirect()->route('account.login');
        }

        return view('site.account.home', ['user' => $u, 'orders' => $u->orders()->with('items')->limit(50)->get()]);
    }

    public function loginForm(): View|RedirectResponse
    {
        return Accounts::customer() ? redirect()->route('account.home') : view('site.account.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']],
            ['email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.', 'password.required' => 'Bitte geben Sie Ihr Passwort ein.']);
        $key = 'account-login:'.Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->onlyInput('email')->withErrors(['email' => 'Zu viele Versuche. Bitte warten Sie '.ceil(RateLimiter::availableIn($key) / 60).' Minuten.']);
        }
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! $user->isCustomer() || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 900);

            return back()->onlyInput('email')->withErrors(['email' => 'E-Mail-Adresse oder Passwort ist nicht korrekt.']);
        }
        RateLimiter::clear($key);
        // Warenkorb und Gutschein bleiben beim Anmelden erhalten
        $keep = $request->session()->only(['nova.cart', 'nova.coupon']);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->put($keep);

        return redirect()->intended(route('account.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($request->user()?->isCustomer()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('home')->with('status', 'Sie sind abgemeldet.');
    }

    public function registerForm(): View|RedirectResponse
    {
        return Accounts::customer() ? redirect()->route('account.home')
            : view('site.account.register', ['privacy' => Page::published()->where('purpose', 'privacy')->first(), 'countries' => $this->countries()]);
    }

    public function register(Request $request): RedirectResponse
    {
        if ($request->filled('website')) {
            abort(422);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)],
            'company' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:60'],
            'street' => ['nullable', 'string', 'max:160'],
            'zip' => ['nullable', 'string', 'max:12'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', Rule::in(array_keys(ShopSettings::COUNTRIES))],
            'consent' => ['accepted'],
        ], $this->messages());
        if (User::where('email', $data['email'])->exists()) {
            return redirect()->route('account.login')->onlyInput('email')
                ->withErrors(['email' => 'Mit dieser E-Mail-Adresse gibt es schon ein Konto. Bitte melden Sie sich an oder nutzen Sie „Passwort vergessen“.']);
        }
        $address = Accounts::clean(['name' => $data['name'], 'company' => $data['company'] ?? null, 'street' => $data['street'] ?? null,
            'zip' => $data['zip'] ?? null, 'city' => $data['city'] ?? null, 'country' => $data['country'] ?? 'DE']);
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'role' => 'customer',
            'company' => $data['company'] ?? null, 'phone' => $data['phone'] ?? null, 'billing_address' => ! empty($data['street']) ? $address : null]);
        $keep = $request->session()->only(['nova.cart', 'nova.coupon']);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put($keep);

        return redirect()->intended(route('account.home'))->with('status', 'Willkommen, Ihr Kundenkonto ist eingerichtet.');
    }

    public function forgotForm(): View
    {
        return view('site.account.forgot');
    }

    public function forgot(Request $request, Members $members): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']], ['email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.']);
        $user = User::where('email', $data['email'])->first();
        if ($user?->isCustomer()) {
            $members->sendResetLink($user);
        }

        return back()->with('status', 'Wenn es ein Konto mit dieser Adresse gibt, ist eine E-Mail mit einem Link unterwegs.');
    }

    public function edit(): View|RedirectResponse
    {
        if (! $u = Accounts::customer()) {
            return redirect()->route('account.login');
        }

        return view('site.account.edit', ['user' => $u, 'countries' => $this->countries()]);
    }

    public function update(Request $request): RedirectResponse
    {
        if (! $u = Accounts::customer()) {
            return redirect()->route('account.login');
        }
        $addr = fn ($p) => [
            "{$p}.name" => ['nullable', 'string', 'max:160'], "{$p}.company" => ['nullable', 'string', 'max:160'], "{$p}.street" => ['nullable', 'string', 'max:160'],
            "{$p}.zip" => ['nullable', 'string', 'max:12'], "{$p}.city" => ['nullable', 'string', 'max:120'], "{$p}.country" => ['nullable', Rule::in(array_keys(ShopSettings::COUNTRIES))],
        ];
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($u->id)],
            'phone' => ['nullable', 'string', 'max:60'],
            'company' => ['nullable', 'string', 'max:160'],
            ...$addr('billing'), ...$addr('shipping'),
        ], $this->messages() + ['email.unique' => 'Diese E-Mail-Adresse gehört schon zu einem anderen Konto.']);
        $same = $request->boolean('shipping_same');
        $u->update([
            'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null, 'company' => $data['company'] ?? null,
            'billing_address' => Accounts::clean($data['billing'] ?? []),
            'shipping_address' => $same ? null : Accounts::clean($data['shipping'] ?? []),
        ]);

        return redirect()->route('account.edit')->with('status', 'Ihre Daten sind gespeichert.');
    }

    public function password(Request $request): RedirectResponse
    {
        if (! $u = Accounts::customer()) {
            return redirect()->route('account.login');
        }
        $data = $request->validate(['current_password' => ['required', 'current_password'], 'password' => ['required', 'confirmed', PasswordRule::min(10)]],
            ['current_password.current_password' => 'Das bisherige Passwort stimmt nicht.'] + $this->messages());
        $u->update(['password' => $data['password']]);

        return redirect()->to(route('account.edit').'#passwort')->with('status', 'Ihr neues Passwort ist gespeichert.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        if (! $u = Accounts::customer()) {
            return redirect()->route('account.login');
        }
        $request->validate(['current_password' => ['required', 'current_password']], ['current_password.current_password' => 'Das Passwort stimmt nicht.']);
        Auth::logout();
        $u->delete(); // Bestellungen bleiben für die Buchhaltung erhalten (gesetzliche Aufbewahrung)
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Ihr Kundenkonto wurde gelöscht.');
    }

    private function countries(): array
    {
        return collect(ShopSettings::shippingCountries())->mapWithKeys(fn ($c) => [$c => ShopSettings::COUNTRIES[$c]['label']])->all();
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Bitte geben Sie Ihren Namen an.', 'email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse an.',
            'password.min' => 'Das Passwort braucht mindestens 10 Zeichen.', 'password.confirmed' => 'Die beiden Passwörter stimmen nicht überein.',
            'consent.accepted' => 'Bitte bestätigen Sie den Hinweis zum Datenschutz.',
        ];
    }
}
