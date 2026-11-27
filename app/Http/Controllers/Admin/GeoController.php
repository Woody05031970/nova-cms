<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Setting;
use App\Support\Geo\Geo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Modul „KI-Sichtbarkeit“: Prüfung und Einstellungen */
class GeoController extends Controller
{
    public function index(Geo $geo): View
    {
        // Rechtstexte (Impressum, Datenschutz …) brauchen keine KI-Optimierung
        $pages = Page::published()->whereNull('purpose')->orderByDesc('is_home')->orderBy('menu_order')->get()
            ->map(fn (Page $p) => ['page' => $p] + $geo->check($p));
        $site = $geo->siteChecks();
        $contentPages = $pages;
        $pageScore = $contentPages->isEmpty() ? 0 : (int) round($contentPages->avg('score'));
        $siteScore = (int) round(collect($site)->where('ok', true)->count() / count($site) * 100);

        return view('admin.geo.index', [
            'pages' => $pages->sortBy('score')->values(),
            'site' => $site,
            'score' => (int) round($pageScore * 0.6 + $siteScore * 0.4),
            'v' => [
                'geo_llms' => Setting::get('geo_llms', '1'),
                'geo_ai_bots' => Setting::get('geo_ai_bots', 'allow'),
                'geo_summary' => Setting::get('geo_summary'),
            ] + collect(Geo::PROFILES)->mapWithKeys(fn ($n, $k) => [$k => Setting::get($k)])->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'geo_summary' => ['nullable', 'string', 'max:600'],
            'geo_ai_bots' => ['required', 'in:allow,block'],
        ];
        $messages = [];
        foreach (Geo::PROFILES as $key => $name) {
            $rules[$key] = ['nullable', 'url:https', 'max:300'];
            $messages[$key.'.url'] = "Bitte geben Sie die vollständige Adresse Ihres {$name}-Profils an, beginnend mit https://";
        }
        $data = $request->validate($rules, $messages);

        Setting::putMany([
            'geo_llms' => $request->boolean('geo_llms') ? '1' : '0',
        ] + array_map(fn ($v) => $v === null ? null : trim($v), $data));

        return redirect()->to(route('admin.geo').'#einstellungen')->with('status', 'Einstellungen zur KI-Sichtbarkeit gespeichert.');
    }
}
