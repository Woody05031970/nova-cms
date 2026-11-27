<?php

namespace App\Http\Controllers\Admin\Licensing;

use App\Http\Controllers\Controller;
use App\Licensing\License;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class LicenseController extends Controller
{
    public function __construct(private readonly License $license) {}

    public function index(): View
    {
        return view('admin.license.index', [
            'license' => $this->license,
            'status' => $this->license->status(),
            'payload' => $this->license->payload(),
            'lastError' => Setting::get('license_last_error'),
            'checkedAt' => Setting::get('license_checked_at'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['key' => ['required', 'string', 'max:40']], ['key.required' => 'Bitte geben Sie Ihren Lizenzschlüssel ein.']);
        try {
            $this->license->activate($data['key']);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['key' => $e->getMessage()]);
        }

        return redirect()->route('admin.license')->with('status', 'Vielen Dank! Ihre Lizenz ist aktiv.');
    }

    public function refresh(): RedirectResponse
    {
        $this->license->refresh(force: true);
        $error = Setting::get('license_last_error');

        return $error
            ? back()->withErrors(['key' => $error])
            : back()->with('status', 'Die Lizenz wurde beim Lizenzserver bestätigt.');
    }

    public function destroy(): RedirectResponse
    {
        $this->license->remove();

        return redirect()->route('admin.license')->with('status', 'Die Lizenz wurde von dieser Website entfernt und ist für eine andere Website frei.');
    }
}
