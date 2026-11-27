<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedIp;
use App\Models\SecurityEvent;
use App\Models\Setting;
use App\Support\Security\Scanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function index(): View
    {
        $since = now()->subDays(7);

        return view('admin.security.index', [
            'scan' => Scanner::last(),
            'firewall' => Setting::get('security_firewall') !== '0',
            'autoblock' => Setting::get('security_autoblock') !== '0',
            'stats' => [
                'attacks' => SecurityEvent::where('type', 'attack')->where('created_at', '>=', $since)->count(),
                'failed' => SecurityEvent::where('type', 'login_failed')->where('created_at', '>=', $since)->count(),
                'blocked' => BlockedIp::active()->count(),
            ],
            'events' => SecurityEvent::with('user')->latest('id')->limit(40)->get(),
            'blocked' => BlockedIp::active()->latest('id')->get(),
        ]);
    }

    public function scan(Scanner $scanner): RedirectResponse
    {
        $result = $scanner->run();

        return redirect()->route('admin.security')->with('status', "Sicherheits-Check abgeschlossen: {$result['score']} von 100 Punkten.");
    }

    public function fix(Request $request): RedirectResponse
    {
        match ($request->input('what')) {
            'uploads' => Scanner::protectUploads(),
            'integrity' => Scanner::storeHashes(),
            default => abort(422),
        };
        app(Scanner::class)->run();

        return redirect()->route('admin.security')->with('status', 'Erledigt. Der Check wurde neu ausgeführt.');
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate(['firewall' => ['required', 'boolean'], 'autoblock' => ['required', 'boolean']]);
        Setting::putMany([
            'security_firewall' => $data['firewall'] ? '1' : '0',
            'security_autoblock' => $data['autoblock'] ? '1' : '0',
        ]);

        return back()->with('status', 'Einstellungen gespeichert.');
    }

    public function block(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ip' => ['required', 'ip'],
            'reason' => ['nullable', 'string', 'max:200'],
        ], ['ip.ip' => 'Das ist keine gültige IP-Adresse.']);

        if ($data['ip'] === $request->ip()) {
            return back()->withErrors(['ip' => 'Das ist Ihre eigene Adresse. Sie würden sich selbst aussperren.']);
        }

        BlockedIp::block($data['ip'], $data['reason'] ?: 'Von Hand gesperrt');

        return back()->with('status', "Die Adresse {$data['ip']} ist gesperrt.");
    }

    public function unblock(BlockedIp $blockedIp): RedirectResponse
    {
        BlockedIp::unblock($blockedIp->ip);

        return back()->with('status', "Die Adresse {$blockedIp->ip} ist wieder freigegeben.");
    }
}
