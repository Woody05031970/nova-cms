<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberFile;
use App\Models\Setting;
use App\Models\User;
use App\Support\Members\Members;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Modul „Mitgliederbereich“ in der Verwaltung */
class MembersAdminController extends Controller
{
    public function __construct(private readonly Members $members) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        return view('admin.members.index', [
            'users' => User::whereIn('role', array_keys(Members::STATUS))
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
                ->orderByRaw("CASE role WHEN 'member_pending' THEN 0 WHEN 'member' THEN 1 ELSE 2 END")->latest()->limit(500)->get(),
            'q' => $q,
            'pending' => User::where('role', 'member_pending')->count(),
            'files' => MemberFile::latest()->get(),
            's' => fn ($k, $d = null) => Setting::get($k, $d),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ], ['email.unique' => 'Mit dieser E-Mail-Adresse gibt es schon ein Konto.', 'name.required' => 'Bitte geben Sie einen Namen an.']);
        $user = User::create($data + ['password' => Str::password(32), 'role' => 'member']);
        $this->members->sendResetLink($user, 'invite');

        return back()->with('status', $user->name.' ist eingeladen und bekommt eine E-Mail zum Festlegen des Passworts.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isMember(), 404);
        $action = $request->validate(['action' => ['required', Rule::in(['approve', 'block', 'unblock', 'reset'])]])['action'];
        match ($action) {
            'approve' => $this->members->approve($user),
            'block' => $user->forceFill(['role' => 'member_blocked', 'remember_token' => Str::random(60)])->save(),
            'unblock' => $user->forceFill(['role' => 'member'])->save(),
            'reset' => $this->members->sendResetLink($user),
        };
        // Gesperrte Mitglieder fliegen aus laufenden Sitzungen
        if ($action === 'block') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return back()->with('status', match ($action) {
            'approve' => $user->name.' ist freigeschaltet und wurde per E-Mail informiert.',
            'block' => $user->name.' ist gesperrt.',
            'unblock' => $user->name.' ist wieder freigeschaltet.',
            'reset' => $user->name.' bekommt einen Link für ein neues Passwort.',
        });
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_unless($user->isMember(), 404);
        $user->delete();

        return back()->with('status', 'Mitglied gelöscht.');
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'members_registration' => ['required', Rule::in(['off', 'approval', 'open'])],
            'members_title' => ['nullable', 'string', 'max:80'],
            'members_intro' => ['nullable', 'string', 'max:2000'],
        ]);
        Setting::putMany($data + ['members_footer_link' => $request->boolean('members_footer_link') ? '1' : '0']);

        return redirect()->to(route('admin.members').'#einstellungen')->with('status', 'Einstellungen gespeichert.');
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'file' => ['required', 'file', 'max:51200', 'mimes:pdf,zip,doc,docx,xls,xlsx,ppt,pptx,odt,ods,txt,csv,jpg,jpeg,png,webp,mp3,mp4'],
        ], ['file.required' => 'Bitte wählen Sie eine Datei.', 'file.max' => 'Die Datei darf höchstens 50 MB groß sein.', 'file.mimes' => 'Dieser Dateityp ist nicht erlaubt.']);
        $file = $request->file('file');
        $path = $file->storeAs('members', Str::random(32).'.'.strtolower($file->getClientOriginalExtension()), 'local');
        MemberFile::create([
            'title' => $request->input('title') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'path' => $path, 'original_name' => Str::limit($file->getClientOriginalName(), 200, ''), 'size' => $file->getSize(),
        ]);

        return redirect()->to(route('admin.members').'#downloads')->with('status', 'Datei hochgeladen. Mitglieder finden sie nach der Anmeldung.');
    }

    public function deleteFile(MemberFile $file): RedirectResponse
    {
        $file->delete();

        return redirect()->to(route('admin.members').'#downloads')->with('status', 'Datei gelöscht.');
    }
}
