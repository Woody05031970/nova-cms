<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Competitor;
use App\Models\NotFoundLog;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Setting;
use App\Support\Seo\CompetitorCrawler;
use App\Support\Seo\SeoAdvisor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Mitbewerber-Analyse, SEO-Helfer mit Maßnahmenliste und Weiterleitungen */
class SeoToolsController extends Controller
{
    public const MAX_COMPETITORS = 5;

    public function competitors(SeoAdvisor $advisor): View
    {
        $competitors = Competitor::orderBy('created_at')->get();

        return view('admin.seo.competitors', $advisor->advise($competitors) + [
            'competitors' => $competitors,
            'features' => CompetitorCrawler::FEATURES,
            'priorities' => SeoAdvisor::PRIORITY,
        ]);
    }

    public function addCompetitor(Request $request, CompetitorCrawler $crawler): RedirectResponse
    {
        $data = $request->validate(['url' => ['required', 'string', 'max:500']], ['url.required' => 'Bitte geben Sie die Adresse eines Mitbewerbers ein.']);
        if (Competitor::count() >= self::MAX_COMPETITORS) {
            return back()->withErrors(['url' => 'Sie können bis zu '.self::MAX_COMPETITORS.' Mitbewerber vergleichen. Entfernen Sie zuerst einen.']);
        }

        try {
            $result = $crawler->crawl($data['url']);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['url' => $e->getMessage()]);
        }
        $existing = Competitor::get()->first(fn (Competitor $c) => $c->host() === $result['host']);
        if ($existing) {
            $existing->update(['data' => $result, 'name' => $result['name'], 'url' => $result['url'], 'error' => null, 'analyzed_at' => now()]);
        } else {
            Competitor::create(['url' => $result['url'], 'name' => $result['name'], 'data' => $result, 'analyzed_at' => now()]);
        }

        return redirect()->route('admin.seo.competitors')->with('status', '„'.$result['name'].'“ wurde analysiert: '.count($result['pages']).' Seiten gelesen.');
    }

    public function refreshCompetitor(Competitor $competitor, CompetitorCrawler $crawler): RedirectResponse
    {
        try {
            $result = $crawler->crawl($competitor->url);
            $competitor->update(['data' => $result, 'name' => $result['name'], 'error' => null, 'analyzed_at' => now()]);
        } catch (RuntimeException $e) {
            $competitor->update(['error' => $e->getMessage()]);

            return back()->withErrors(['url' => $e->getMessage()]);
        }

        return back()->with('status', '„'.$competitor->label().'“ wurde neu analysiert.');
    }

    public function removeCompetitor(Competitor $competitor): RedirectResponse
    {
        $competitor->delete();

        return back()->with('status', 'Mitbewerber entfernt.');
    }

    /** Maßnahme als erledigt markieren (oder wieder öffnen) */
    public function toggleMeasure(Request $request): RedirectResponse
    {
        $id = (string) $request->validate(['id' => ['required', 'string', 'max:80']])['id'];
        $done = json_decode((string) Setting::get('seo_done', '[]'), true) ?: [];
        $done = in_array($id, $done, true) ? array_values(array_diff($done, [$id])) : [...$done, $id];
        Setting::put('seo_done', json_encode(array_slice($done, -200)));

        return redirect()->to(route('admin.seo.competitors').'#massnahmen');
    }

    public function redirects(): View
    {
        return view('admin.seo.redirects', [
            'redirects' => Redirect::orderByDesc('updated_at')->get(),
            'notFound' => NotFoundLog::orderByDesc('hits')->limit(100)->get(),
            'pages' => Page::published()->orderBy('title')->get(['id', 'title', 'slug', 'is_home']),
        ]);
    }

    public function storeRedirect(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_path' => ['required', 'string', 'max:500'],
            'to_url' => ['required', 'string', 'max:500'],
            'status' => ['nullable', Rule::in([301, 302])],
        ], ['from_path.required' => 'Bitte geben Sie die alte Adresse ein.', 'to_url.required' => 'Bitte wählen Sie das Ziel.']);

        $from = Redirect::normalize($data['from_path']);
        $to = trim($data['to_url']);
        if (! preg_match('#^(https?://|/)#i', $to)) {
            $to = '/'.ltrim($to, '/');
        }
        if ($from === '/' || str_starts_with($from, '/admin') || $from === Redirect::normalize($to)) {
            return back()->withInput()->withErrors(['from_path' => 'Diese Adresse kann nicht weitergeleitet werden.']);
        }
        Redirect::updateOrCreate(['from_path' => $from], ['to_url' => $to, 'status' => (int) ($data['status'] ?? 301), 'automatic' => false]);
        NotFoundLog::where('path', $from)->delete();

        return back()->with('status', 'Weiterleitung gespeichert: '.$from.' → '.$to);
    }

    public function destroyRedirect(Redirect $redirect): RedirectResponse
    {
        $redirect->delete();

        return back()->with('status', 'Weiterleitung gelöscht.');
    }

    public function clearNotFound(Request $request): RedirectResponse
    {
        $request->filled('id') ? NotFoundLog::whereKey($request->input('id'))->delete() : NotFoundLog::query()->delete();

        return back()->with('status', 'Einträge entfernt.');
    }
}
