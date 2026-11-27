<?php

namespace App\Support\Members;

use App\Licensing\Features;
use App\Mail\MemberMail;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Throwable;

/** Modul „Mitgliederbereich“: Mitglieder sind Benutzer mit der Rolle member (member_pending, member_blocked) */
class Members
{
    public const STATUS = ['member' => 'Freigeschaltet', 'member_pending' => 'Wartet auf Freigabe', 'member_blocked' => 'Gesperrt'];

    public static function on(): bool
    {
        return Features::on('mitgliederbereich');
    }

    /** off = nur Einladung, approval = Registrierung mit Freigabe, open = sofort freigeschaltet */
    public static function registration(): string
    {
        $r = Setting::get('members_registration', 'approval');

        return in_array($r, ['off', 'approval', 'open'], true) ? $r : 'approval';
    }

    public static function title(): string
    {
        return Setting::get('members_title') ?: 'Mitgliederbereich';
    }

    public function sendResetLink(User $user, string $kind = 'reset'): void
    {
        $token = Password::broker()->createToken($user);
        $this->mail($user->email, new MemberMail($user, $kind, route('password.reset', ['token' => $token, 'email' => $user->email])));
    }

    public function approve(User $user): void
    {
        $user->forceFill(['role' => 'member'])->save();
        $this->mail($user->email, new MemberMail($user, 'approved', route('members.login')));
    }

    public function notifyOwner(User $user): void
    {
        if ($to = Setting::get('contact_email')) {
            $this->mail($to, new MemberMail($user, 'owner', route('admin.members')));
        }
    }

    private function mail(string $to, MemberMail $mail): void
    {
        try {
            Mail::to($to)->send($mail);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
