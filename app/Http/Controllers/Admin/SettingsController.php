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

        return view('admin.settings', ['values' => $values]);
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
}
