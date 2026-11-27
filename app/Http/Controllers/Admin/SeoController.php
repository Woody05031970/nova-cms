<?php

namespace App\Http\Controllers\Admin;

use App\Ai\AiException;
use App\Ai\AiManager;
use App\Ai\ContentAssistant;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Setting;
use App\Support\Seo\CompetitorAnalyzer;
use App\Support\Seo\PageAnalyzer;
use App\Support\Seo\SiteSeo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class SeoController extends Controller
{
    public function index(PageAnalyzer $analyzer): View
    {
        $pages = Page::orderByDesc('is_home')->orderBy('menu_order')->orderBy('title')->get()
            ->map(fn (Page $p) => ['page' => $p, 'result' => $analyzer->analyzePage($p)]);

        $published = $pages->filter(fn ($r) => $r['page']->isPublished());
        $site = [
            ['ok' => SiteSeo::indexingAllowed(), 'label' => 'Google darf die Website finden', 'tip' => 'Die Website ist für Suchmaschinen gesperrt. Schalten Sie das unten frei, sobald sie fertig ist.'],
            ['ok' => $published->contains(fn ($r) => $r['page']->is_home), 'label' => 'Startseite ist veröffentlicht', 'tip' => 'Ohne Startseite hat Google nichts, womit es anfangen kann.'],
            ['ok' => (bool) Setting::get('company_name') && (bool) Setting::get('zip_city'), 'label' => 'Firmendaten für Google (Name und Ort)', 'tip' => 'Mit Name und Ort im Firmenprofil kann Google Sie in der Umgebungssuche zeigen.'],
            ['ok' => (bool) Setting::get('tagline'), 'label' => 'Kurzbeschreibung der Website', 'tip' => 'Ergänzen Sie im Firmenprofil einen Satz, was Sie anbieten.'],
            ['ok' => request()->isSecure() || str_starts_with((string) config('app.url'), 'https://'), 'label' => 'Verschlüsselte Verbindung (https)', 'tip' => 'Google bevorzugt Seiten mit https. Bei IONOS und Strato schalten Sie das kostenlose SSL-Zertifikat im Kundenkonto ein.'],
        ];

        return view('admin.seo.index', [
            'pages' => $pages,
            'site' => $site,
            'average' => $published->isEmpty() ? null : (int) round($published->avg(fn ($r) => $r['result']['score'])),
            'indexing' => SiteSeo::indexingAllowed(),
            'ai' => app(AiManager::class)->isConfigured(),
        ]);
    }

    public function settings(Request $request): RedirectResponse
    {
        Setting::put('seo_noindex', $request->boolean('allow_indexing') ? '0' : '1');

        return back()->with('status', $request->boolean('allow_indexing')
            ? 'Google und andere Suchmaschinen dürfen Ihre Website jetzt aufnehmen.'
            : 'Ihre Website ist jetzt für Suchmaschinen gesperrt.');
    }

    /** Live-Prüfung im Editor mit dem aktuellen, noch nicht gespeicherten Stand. */
    public function analyse(Request $request, PageAnalyzer $analyzer): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160'],
            'meta_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'focus_keyword' => ['nullable', 'string', 'max:100'],
            'blocks' => ['nullable', 'array'],
            'is_home' => ['boolean'],
            'purpose' => ['nullable', 'string', 'max:20'],
        ]);

        return response()->json($analyzer->analyze($data));
    }

    public function keywords(Request $request, ContentAssistant $assistant): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'text' => ['nullable', 'string', 'max:20000']]);

        if (! app(AiManager::class)->isConfigured()) {
            return response()->json(['message' => 'Es ist noch kein KI-Anbieter eingerichtet.', 'setup' => route('admin.ai')], 422);
        }
        if (mb_strlen(trim((string) ($data['text'] ?? ''))) < 40) {
            return response()->json(['message' => 'Die Seite hat noch zu wenig Text für Vorschläge.'], 422);
        }

        try {
            return response()->json(['keywords' => $assistant->keywordIdeas($data['title'], $data['text'])]);
        } catch (AiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function compare(Request $request, PageAnalyzer $analyzer, CompetitorAnalyzer $competitors, ContentAssistant $assistant): JsonResponse
    {
        $data = $request->validate([
            'page_id' => ['required', 'integer', 'exists:pages,id'],
            'url' => ['required', 'string', 'max:500'],
            'keyword' => ['nullable', 'string', 'max:100'],
        ], ['url.required' => 'Bitte geben Sie die Adresse des Wettbewerbers ein.']);

        $page = Page::findOrFail($data['page_id']);
        $keyword = trim((string) ($data['keyword'] ?? '')) ?: $page->focus_keyword;

        try {
            $them = $competitors->analyze($data['url'], $keyword);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $own = $this->ownMetrics($page, $analyzer, $keyword);
        $rows = $this->rows($own, $them, (bool) $keyword);

        $advice = null;
        $adviceError = null;
        if (app(AiManager::class)->isConfigured()) {
            try {
                $advice = $assistant->competitorAdvice(
                    collect($own)->except('keyword_hits')->all() + ['keyword_hits' => $own['keyword_hits']],
                    collect($them)->except(['headings'])->all() + ['headings' => array_slice($them['headings'], 0, 12)],
                    $keyword,
                );
            } catch (AiException $e) {
                $adviceError = $e->getMessage();
            }
        }

        return response()->json([
            'own' => $own, 'competitor' => $them, 'rows' => $rows, 'keyword' => $keyword,
            'advice' => $advice, 'advice_error' => $adviceError,
            'wins' => collect($rows)->where('winner', 'own')->count(),
            'losses' => collect($rows)->where('winner', 'competitor')->count(),
        ]);
    }

    private function ownMetrics(Page $page, PageAnalyzer $analyzer, ?string $keyword): array
    {
        $blocks = $page->blocks ?? [];
        $text = $analyzer->text($blocks);
        $title = ($page->meta_title ?: $page->title).($page->is_home && ! $page->meta_title ? '' : ' – '.Setting::get('site_name', ''));
        $headings = [];
        $images = $missing = $links = 0;
        foreach ($blocks as $b) {
            $d = $b['data'] ?? [];
            if (($b['type'] ?? '') === 'text') {
                preg_match_all('/<h[2-4][^>]*>(.*?)<\/h[2-4]>/is', (string) ($d['html'] ?? ''), $m);
                array_push($headings, ...array_map('strip_tags', $m[1]));
                $links += preg_match_all('/href="\/(?!\/)/i', (string) ($d['html'] ?? ''));
            }
            if (($b['type'] ?? '') === 'image' && ! empty($d['url'])) {
                $images++;
                $missing += empty($d['alt']) ? 1 : 0;
            }
            if (($b['type'] ?? '') === 'slider') {
                foreach ($d['slides'] ?? [] as $s) {
                    $images++;
                    $missing += empty($s['alt']) ? 1 : 0;
                }
            }
            if (($b['type'] ?? '') === 'hero' && ! empty($d['image'])) {
                $images++; // Hintergrundbild, braucht keine Beschreibung
            }
            if (in_array($b['type'] ?? '', ['button', 'hero'], true) && str_starts_with((string) ($d['url'] ?? $d['button_url'] ?? ''), '/')) {
                $links++;
            }
        }
        // Menü und Fußzeile verlinken zusätzlich auf jede veröffentlichte Seite
        $links += Page::published()->where('show_in_menu', true)->count() + Page::published()->whereNotNull('purpose')->count();

        $h1 = (($blocks[0]['type'] ?? null) === 'hero') ? ($blocks[0]['data']['title'] ?? $page->title) : $page->title;

        return [
            'url' => $page->url(),
            'title' => $title,
            'description' => (string) $page->meta_description,
            'h1' => [$h1],
            'headings' => $headings,
            'words' => $analyzer->countWords($text),
            'images' => $images,
            'images_missing_alt' => $missing,
            'internal_links' => $links,
            'schema' => (bool) $page->is_home,
            'viewport' => true,
            'https' => str_starts_with($page->url(), 'https://'),
            'keyword_hits' => $keyword ? [
                'title' => $analyzer->contains($title, $keyword),
                'description' => $analyzer->contains((string) $page->meta_description, $keyword),
                'h1' => $analyzer->contains((string) $h1, $keyword),
                'headings' => collect($headings)->contains(fn ($h) => $analyzer->contains($h, $keyword)),
                'text' => $analyzer->contains($text, $keyword),
            ] : [],
        ];
    }

    /** Gegenüberstellung in Laien-Sprache: wer liegt bei welchem Punkt vorn? */
    private function rows(array $own, array $them, bool $withKeyword): array
    {
        $len = fn ($s, $min, $max) => ($l = mb_strlen((string) $s)) >= $min && $l <= $max;
        $cmp = fn ($a, $b) => $a === $b ? 'tie' : ($a ? 'own' : 'competitor');
        $more = fn ($a, $b, $tolerance = 0.15) => abs($a - $b) <= max($a, $b) * $tolerance ? 'tie' : ($a > $b ? 'own' : 'competitor');

        $rows = [
            ['label' => 'Textmenge', 'own' => $own['words'].' Wörter', 'competitor' => $them['words'].' Wörter', 'winner' => $more($own['words'], $them['words'])],
            ['label' => 'Titel für Google', 'own' => mb_strlen($own['title']).' Zeichen', 'competitor' => mb_strlen($them['title']).' Zeichen', 'winner' => $cmp($len($own['title'], 30, 60), $len($them['title'], 30, 60))],
            ['label' => 'Beschreibung für Google', 'own' => $own['description'] ? mb_strlen($own['description']).' Zeichen' : 'fehlt', 'competitor' => $them['description'] ? mb_strlen($them['description']).' Zeichen' : 'fehlt', 'winner' => $cmp($len($own['description'], 70, 160), $len($them['description'], 70, 160))],
            ['label' => 'Zwischenüberschriften', 'own' => (string) count($own['headings']), 'competitor' => (string) count($them['headings']), 'winner' => $more(count($own['headings']), count($them['headings']), 0.25)],
            ['label' => 'Bilder mit Beschreibung', 'own' => ($own['images'] - $own['images_missing_alt']).' von '.$own['images'], 'competitor' => ($them['images'] - $them['images_missing_alt']).' von '.$them['images'],
                'winner' => $cmp($own['images'] > 0 && $own['images_missing_alt'] === 0, $them['images'] > 0 && $them['images_missing_alt'] === 0)],
            ['label' => 'Firmendaten für Google (schema.org)', 'own' => $own['schema'] ? 'ja' : 'nein', 'competitor' => $them['schema'] ? 'ja' : 'nein', 'winner' => $cmp($own['schema'], $them['schema'])],
            ['label' => 'Verschlüsselt (https)', 'own' => $own['https'] ? 'ja' : 'nein', 'competitor' => $them['https'] ? 'ja' : 'nein', 'winner' => $cmp($own['https'], $them['https'])],
        ];

        if ($withKeyword) {
            $names = ['title' => 'im Titel', 'description' => 'in der Beschreibung', 'h1' => 'in der Hauptüberschrift', 'headings' => 'in Zwischenüberschriften', 'text' => 'im Text'];
            foreach ($names as $k => $name) {
                $a = $own['keyword_hits'][$k] ?? false;
                $b = $them['keyword_hits'][$k] ?? false;
                $rows[] = ['label' => 'Suchbegriff '.$name, 'own' => $a ? 'ja' : 'nein', 'competitor' => $b ? 'ja' : 'nein', 'winner' => $cmp($a, $b)];
            }
        }

        return $rows;
    }
}
