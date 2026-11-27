<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    /** Nach so vielen Fehlversuchen wird die Anmeldung für LOCK_MINUTES gesperrt. */
    public const MAX_ATTEMPTS = 5;

    public const LOCK_MINUTES = 15;

    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Schutz gegen das Durchprobieren von Passwörtern: je Konto und IP
        $key = 'login:'.Str::lower($credentials['email']).'|'.$request->ip();
        if ($response = $this->lockedOut($key)) {
            return $response;
        }

        $user = User::where('email', $credentials['email'])->first();
        if (! $user || ! Auth::validate($credentials)) {
            RateLimiter::hit($key, self::LOCK_MINUTES * 60);
            SecurityEvent::record('login_failed', 'E-Mail: '.Str::limit($credentials['email'], 120), $user?->id);
            if (RateLimiter::attempts($key) >= self::MAX_ATTEMPTS) {
                SecurityEvent::record('lockout', 'Nach '.self::MAX_ATTEMPTS.' Fehlversuchen für '.self::LOCK_MINUTES.' Minuten gesperrt', $user?->id);
            }

            return back()->onlyInput('email')->withErrors(['email' => 'E-Mail-Adresse oder Passwort ist nicht korrekt.']);
        }

        RateLimiter::clear($key);

        if ($user->hasTwoFactor()) {
            $request->session()->put('nova.2fa', ['id' => $user->id, 'remember' => $request->boolean('remember'), 'at' => time()]);

            return redirect()->route('login.two-factor');
        }

        return $this->complete($request, $user, $request->boolean('remember'));
    }

    public function twoFactorForm(Request $request): View|RedirectResponse
    {
        return $this->pending($request) ? view('auth.two-factor') : redirect()->route('login');
    }

    public function twoFactor(Request $request): RedirectResponse
    {
        $pending = $this->pending($request);
        if (! $pending) {
            return redirect()->route('login')->withErrors(['email' => 'Bitte melden Sie sich erneut an.']);
        }

        $key = 'login-2fa:'.$pending['id'].'|'.$request->ip();
        if ($response = $this->lockedOut($key)) {
            return $response;
        }

        $user = User::find($pending['id']);
        $code = (string) $request->input('code');
        if (! $user || ! $user->verifyTwoFactor($code)) {
            RateLimiter::hit($key, self::LOCK_MINUTES * 60);
            SecurityEvent::record('two_factor_failed', null, $user?->id);

            return back()->withErrors(['code' => 'Der Code stimmt nicht. Bitte nehmen Sie den aktuellen Code aus Ihrer App.']);
        }

        RateLimiter::clear($key);
        $request->session()->forget('nova.2fa');

        return $this->complete($request, $user, $pending['remember']);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Sie wurden abgemeldet.');
    }

    private function complete(Request $request, User $user, bool $remember): RedirectResponse
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        SecurityEvent::record('login', null, $user->id);

        return redirect()->intended(route('admin.dashboard'));
    }

    /** Der Zwischenschritt gilt fünf Minuten. */
    private function pending(Request $request): ?array
    {
        $p = $request->session()->get('nova.2fa');

        return $p && time() - $p['at'] < 300 ? $p : null;
    }

    private function lockedOut(string $key): ?RedirectResponse
    {
        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return null;
        }
        $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

        return back()->onlyInput('email')->withErrors([
            'email' => "Zu viele Fehlversuche. Aus Sicherheitsgründen ist die Anmeldung für {$minutes} Minute(n) gesperrt.",
        ]);
    }
}
