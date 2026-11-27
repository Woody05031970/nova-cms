<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use App\Models\Setting;
use App\Support\Backup\Backups;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Modul „Datensicherung“: Sicherungen anlegen, herunterladen, zurückspielen und automatisch erstellen */
class BackupController extends Controller
{
    public function index(): View
    {
        $free = @disk_free_space(storage_path('app'));

        return view('admin.backups', [
            'backups' => Backups::list(),
            'auto' => Setting::get('backup_auto', 'off'),
            'keep' => (int) Setting::get('backup_keep', '7'),
            'last' => Setting::get('backup_last_at'),
            'free' => $free === false ? null : (int) $free,
        ]);
    }

    public function store(Backups $backups): RedirectResponse
    {
        try {
            $name = $backups->create();
        } catch (RuntimeException $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }
        SecurityEvent::record('backup_created', $name, auth()->id());

        return redirect()->route('admin.backups')->with('status', 'Sicherung erstellt. Laden Sie sie am besten auch auf Ihren Computer herunter.');
    }

    public function download(string $name): BinaryFileResponse
    {
        $path = Backups::path($name);
        abort_unless($path, 404);

        return response()->download($path, $name, ['Content-Type' => 'application/zip']);
    }

    public function destroy(string $name): RedirectResponse
    {
        $path = Backups::path($name);
        abort_unless($path, 404);
        @unlink($path);

        return redirect()->route('admin.backups')->with('status', 'Sicherung gelöscht.');
    }

    public function restore(Request $request, Backups $backups): RedirectResponse
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'file' => ['nullable', 'file', 'mimes:zip'],
            'confirm' => ['accepted'],
        ], ['confirm.accepted' => 'Bitte bestätigen Sie, dass der aktuelle Stand ersetzt werden soll.', 'file.mimes' => 'Bitte eine ZIP-Datei von Nova CMS hochladen.']);

        if ($request->hasFile('file')) {
            $path = $request->file('file')->getRealPath();
        } else {
            $path = Backups::path((string) $request->input('name'));
            if (! $path) {
                return back()->withErrors(['backup' => 'Bitte wählen Sie eine Sicherung aus oder laden Sie eine hoch.']);
            }
        }
        try {
            $result = $backups->restore($path);
        } catch (RuntimeException $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }
        SecurityEvent::record('backup_restored', 'Stand vom '.($result['from'] ?? '?'), auth()->id());

        return redirect()->route('admin.backups')->with('status', 'Sicherung zurückgespielt: '.number_format($result['rows'], 0, ',', '.').' Datensätze und '
            .$result['files'].' Dateien. Der vorherige Stand liegt als Sicherung „Vor Wiederherstellung“ bereit.');
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate(['backup_auto' => ['required', Rule::in(array_keys(Backups::INTERVALS))], 'backup_keep' => ['required', 'integer', 'min:1', 'max:60']]);
        Setting::putMany(['backup_auto' => $data['backup_auto'], 'backup_keep' => (string) $data['backup_keep']]);

        return redirect()->route('admin.backups')->with('status', $data['backup_auto'] === 'off' ? 'Automatische Sicherung ist aus.' : 'Automatische Sicherung eingerichtet.');
    }
}
