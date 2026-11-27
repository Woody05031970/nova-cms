<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\WhatsApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppController extends Controller
{
    public function index(): View
    {
        $keys = ['whatsapp_enabled', 'whatsapp_number', 'whatsapp_message', 'whatsapp_label', 'whatsapp_position', 'whatsapp_floating', 'whatsapp_products'];

        return view('admin.whatsapp', [
            'v' => collect($keys)->mapWithKeys(fn ($k) => [$k => Setting::get($k)])->all(),
            'link' => WhatsApp::link(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
            'whatsapp_message' => ['nullable', 'string', 'max:300'],
            'whatsapp_label' => ['nullable', 'string', 'max:40'],
            'whatsapp_position' => ['nullable', 'in:left,right'],
        ]);
        if ($request->boolean('whatsapp_enabled') && ! WhatsApp::number($data['whatsapp_number'] ?? '')) {
            return back()->withInput()->withErrors(['whatsapp_number' => 'Bitte geben Sie eine Handynummer an, z. B. 0171 1234567 oder +49 171 1234567.']);
        }
        Setting::putMany([
            'whatsapp_enabled' => $request->boolean('whatsapp_enabled') ? '1' : '0',
            'whatsapp_floating' => $request->boolean('whatsapp_floating') ? '1' : '0',
            'whatsapp_products' => $request->boolean('whatsapp_products') ? '1' : '0',
            'whatsapp_number' => $data['whatsapp_number'] ?? null,
            'whatsapp_message' => $data['whatsapp_message'] ?? null,
            'whatsapp_label' => $data['whatsapp_label'] ?? '',
            'whatsapp_position' => $data['whatsapp_position'] ?? 'right',
        ]);

        return back()->with('status', 'WhatsApp-Einstellungen gespeichert.');
    }
}
