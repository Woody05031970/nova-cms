<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\AdminNav;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** „Backoffice-Menü anpassen“: Kategorien und Punkte der linken Seitenleiste ordnen, umbenennen, ausblenden */
class AdminNavController extends Controller
{
    public function index(): View
    {
        $tree = AdminNav::tree(true);
        $strip = fn (array $i) => ['key' => $i['key'], 'label' => $i['label'], 'icon' => $i['icon'] ?? 'puzzle', 'hidden' => $i['hidden'], 'locked' => ! empty($i['locked'])];

        return view('admin.nav', [
            'groups' => collect($tree['groups'])->map(fn ($g) => [
                'key' => $g['key'], 'label' => $g['label'], 'icon' => $g['icon'], 'default' => AdminNav::GROUPS[$g['key']][0],
                'items' => array_map($strip, $g['items']),
            ])->values()->all(),
            'single' => $tree['single'],
            'customized' => AdminNav::custom() !== [],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['layout' => ['required', 'json', 'max:50000']]);
        AdminNav::save((array) json_decode($data['layout'], true), $request->boolean('single'));

        return redirect()->route('admin.nav')->with('status', 'Das Menü ist gespeichert.');
    }

    public function reset(): RedirectResponse
    {
        AdminNav::reset();

        return redirect()->route('admin.nav')->with('status', 'Das Menü steht wieder in der ursprünglichen Reihenfolge.');
    }
}
