<?php

namespace App\Http\Controllers;

use App\Models\MemberFile;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\Members\Members;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Modul „Mitgliederbereich“: Anmeldung, Registrierung, Passwort und geschützte Inhalte */
class MembersController extends Controller
{
    public function __construct(private readonly Members $members) {}

    public function home(Request $request): View|RedirectResponse
    {
        if (! $request->user()?->canSeeMembersArea()) {
            return redirect()->route('members.login');
        }

        return view('site.members.home', [
            'pages' => Page::published()->where('visibility', 'members')->orderBy('menu_order')->orderBy('title')->get(),
            'files' => MemberFile::latest()->get(),
            'intro' => Setting::get('members_intro'),
        ]);
    }

    public function loginForm(Request $request): View|RedirectResponse
    {
        return $request->user()?->canSeeMembersArea() ? redirect()->route('members.home') : view('site.members.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']],
            ['email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.', 'password.required' => 'Bitte geben Sie Ihr Passwort ein.']);
        $key = 'member-login:'.Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->onlyInput('email')->withErrors(['email' => 'Zu viele Versuche. Bitte warten Sie '.ceil(RateLimiter::availableIn($key) / 60).' Minuten.']);
        }
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! $user->isMember() || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 900);

            return back()->onlyInput('email')->withErrors(['email' => 'E-Mail-Adresse oder Passwort ist nicht korrekt.']);
        }
        RateLimiter::clear($key);
        if ($user->role !== 'member') {
            return back()->onlyInput('email')->withErrors(['email' => $user->role === 'member_pending'
                ? 'Ihr Zugang wartet noch auf die Freigabe. Sie bekommen eine E-Mail, sobald es losgehen kann.'
                : 'Ihr Zugang ist gesperrt. Bitte wenden Sie sich an uns.']);
        }
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->to($request->session()->pull('members.intended', route('members.home')));
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($request->user()?->isMember()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('home')->with('status', 'Sie sind abgemeldet.');
    }

    public function registerForm(): View
    {
        abort_if(Members::registration() === 'off', 404);

        return view('site.members.register', ['privacy' => Page::published()->where('purpose', 'privacy')->first()]);
    }

    public function register(Request $request): RedirectResponse
    {
        abort_if(Members::registration() === 'off', 404);
        if ($request->filled('website')) {
            abort(422);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)],
            'consent' => ['accepted'],
        ], [
            'name.required' => 'Bitte geben Sie Ihren Namen an.',
            'password.min' => 'Das Passwort braucht mindestens 10 Zeichen.',
            'password.confirmed' => 'Die beiden Passwörter stimmen nicht überein.',
            'consent.accepted' => 'Bitte bestätigen Sie den Hinweis zum Datenschutz.',
        ]);
        // Kein Hinweis, ob die Adresse schon registriert ist
        if (User::where('email', $data['email'])->exists()) {
            return redirect()->route('members.login')->with('status', 'Vielen Dank. Wenn alles passt, können Sie sich jetzt anmelden oder bekommen eine E-Mail von uns.');
        }
        $open = Members::registration() === 'open';
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'role' => $open ? 'member' : 'member_pending']);
        $this->members->notifyOwner($user);
        if ($open) {
            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->route('members.home')->with('status', 'Willkommen! Ihr Zugang ist eingerichtet.');
        }

        return redirect()->route('members.login')->with('status', 'Vielen Dank. Wir prüfen Ihre Registrierung und schicken Ihnen eine E-Mail, sobald Ihr Zugang freigeschaltet ist.');
    }

    public function forgotForm(): View
    {
        return view('site.members.forgot');
    }

    public function forgot(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']], ['email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.']);
        $user = User::where('email', $data['email'])->first();
        if ($user && $user->isMember() && $user->role !== 'member_blocked') {
            $this->members->sendResetLink($user);
        }

        return back()->with('status', 'Wenn es ein Konto mit dieser Adresse gibt, ist eine E-Mail mit einem Link unterwegs.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('site.members.reset', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', PasswordRule::min(10)],
        ], ['password.min' => 'Das Passwort braucht mindestens 10 Zeichen.', 'password.confirmed' => 'Die beiden Passwörter stimmen nicht überein.']);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! ($user->isMember() || $user->isCustomer())) {
            return back()->withErrors(['email' => 'Der Link ist ungültig oder abgelaufen. Bitte fordern Sie einen neuen an.']);
        }
        $status = Password::broker()->reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'Der Link ist ungültig oder abgelaufen. Bitte fordern Sie einen neuen an.']);
        }

        return redirect()->route($user->isCustomer() ? 'account.login' : 'members.login')->with('status', 'Ihr neues Passwort ist gespeichert. Bitte melden Sie sich an.');
    }

    public function download(Request $request, MemberFile $file): StreamedResponse|RedirectResponse
    {
        if (! $request->user()?->canSeeMembersArea()) {
            return redirect()->route('members.login');
        }
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }
}
