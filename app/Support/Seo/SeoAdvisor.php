<?php

namespace App\Support\Seo;

use App\Models\Competitor;
use App\Models\NotFoundLog;
use App\Models\Page;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\Setting;
use App\Shop\ShopSettings;
use App\Support\Blocks\Builder;
use App\Support\Menu\SiteMenu;
use App\Support\WhatsApp;
use Illuminate\Support\Collection;

/**
 * SEO-Helfer: vergleicht die eigene Website mit den erfassten Mitbewerbern und
 * leitet daraus konkrete Maßnahmen ab, sortiert nach Wirkung.
 */
class SeoAdvisor
{
    public const PRIORITY = [1 => 'Hoch', 2 => 'Mittel', 3 => 'Niedrig'];

    /** Funktionen, die sich für Google und Besucher lohnen: Schlüssel => [Maßnahme, Begründung, So geht's] */
    private const FEATURE_ADVICE = [
        'faq' => ['Häufige Fragen ergänzen', 'Fragen und Antworten beantworten genau das, was Menschen bei Google eintippen. Nova CMS gibt sie zusätzlich als FAQ-Daten für Google aus.', 'Fügen Sie auf einer passenden Seite den Baustein „Häufige Fragen“ mit 5 bis 8 echten Kundenfragen hinzu.'],
        'blog' => ['Ratgeber-Seiten aufbauen', 'Ratgeber-Texte bringen Besucher über viele zusätzliche Suchbegriffe auf Ihre Website, nicht nur über Ihren Firmennamen.', 'Legen Sie unter „Ratgeber“ einzelne Seiten zu typischen Kundenfragen an, z. B. „Woran erkenne ich …?“, je mindestens 400 Wörter.'],
        'reviews' => ['Kundenstimmen zeigen', 'Bewertungen schaffen Vertrauen und erhöhen die Klickrate. Mitbewerber nutzen sie bereits.', 'Bitten Sie zufriedene Kunden um eine Google-Bewertung und zeigen Sie zwei bis drei Zitate mit dem Baustein „Vorteile“ (Stil Kennzahlen oder Kacheln).'],
        'contact_form' => ['Kontaktformular anbieten', 'Viele Besucher schreiben lieber, als anzurufen. Ohne Formular gehen Anfragen verloren.', 'Fügen Sie auf Ihrer Kontaktseite den Baustein „Kontaktformular“ hinzu.'],
        'newsletter' => ['Newsletter-Anmeldung einbauen', 'Wer sich anmeldet, kommt wieder. Wiederkehrende Besucher sind ein gutes Signal.', 'Fügen Sie den Baustein „Newsletter“ auf der Startseite oder einer eigenen Seite hinzu.'],
        'whatsapp' => ['WhatsApp-Kontakt anbieten', 'Mitbewerber sind per WhatsApp erreichbar. Gerade auf dem Handy ist das die einfachste Kontaktaufnahme.', 'Schalten Sie unter Wachstum › WhatsApp den Chat-Knopf ein.'],
        'video' => ['Ein kurzes Video zeigen', 'Videos halten Besucher länger auf der Seite und erklären mehr als viele Worte.', 'Nutzen Sie im Titelbild die Variante „Video“ oder den Baustein „Video“.'],
        'prices' => ['Preise oder Preisrahmen nennen', 'Mitbewerber zeigen Preise. Besucher, die keine Preise finden, springen häufig ab.', 'Nutzen Sie den Baustein „Preise“ oder nennen Sie „ab …“-Preise im Text.'],
        'phone' => ['Telefonnummer sichtbar machen', 'Eine anklickbare Telefonnummer ist für Handy-Besucher und für die Umgebungssuche wichtig.', 'Tragen Sie die Telefonnummer unter Einstellungen › Firmenprofil ein. Sie erscheint dann in der Fußzeile.'],
        'local' => ['Firmendaten für Google vervollständigen', 'Mit Name, Adresse und Telefon gibt Nova CMS strukturierte Firmendaten aus. So erscheinen Sie in der Umgebungssuche und bei Google Maps.', 'Füllen Sie unter Einstellungen den Firmennamen, die Adresse und den Ort aus.'],
        'accessibility' => ['Erklärung zur Barrierefreiheit veröffentlichen', 'Sie ist für Online-Shops Pflicht und zeigt Sorgfalt.', 'Unter System › Barrierefreiheit legen Sie sie mit einem Klick an.'],
    ];

    public function __construct(private readonly PageAnalyzer $analyzer) {}

    /** Kennzahlen der eigenen Website im gleichen Format wie bei den Mitbewerbern */
    public function own(): array
    {
        $pages = Page::published()->get();
        $types = $pages->flatMap(fn (Page $p) => collect($p->blocks ?? [])->pluck('type')->merge(collect(Builder::flatten($p->blocks ?? []))->pluck('type')))->unique();
        $heroVideo = $pages->contains(fn (Page $p) => collect($p->blocks ?? [])->contains(fn ($b) => ($b['type'] ?? '') === 'hero' && ($b['data']['variant'] ?? '') === 'video'));
        $shop = ShopSettings::enabled() && Product::active()->exists();

        $features = [
            'shop' => $shop,
            'faq' => $types->contains('faq'),
            'blog' => $pages->count() >= 12,
            'contact_form' => $types->contains('contact'),
            'newsletter' => $types->contains('newsletter'),
            'whatsapp' => WhatsApp::enabled(),
            'booking' => false,
            'reviews' => false,
            'video' => $types->contains('video') || $heroVideo,
            'prices' => $types->contains('pricing') || $shop,
            'phone' => (bool) Setting::get('phone'),
            'local' => (bool) Setting::get('company_name') && (bool) Setting::get('zip_city'),
            'accessibility' => $pages->contains('purpose', 'accessibility'),
            'social' => false,
        ];

        $content = $pages->reject(fn (Page $p) => $p->purpose);
        $text = mb_strtolower($pages->map(fn (Page $p) => $p->title.' '.$p->meta_title.' '.$p->meta_description.' '.$p->plainText())->implode(' '));

        return [
            'name' => Setting::get('site_name', 'Ihre Website'),
            'pages' => $pages,
            'content_pages' => $content,
            'menu' => collect(SiteMenu::tree('/'))->flatMap(fn ($i) => array_merge([$i['label']], array_column($i['children'], 'label')))->all(),
            'features' => $features,
            'avg_words' => (int) round($content->avg(fn (Page $p) => $this->analyzer->countWords($this->analyzer->text($p->blocks ?? []))) ?? 0),
            'text' => $text,
        ];
    }

    /**
     * @param  Collection<int, Competitor>  $competitors
     * @return array{measures: list<array<string, mixed>>, strengths: list<string>, own: array<string, mixed>}
     */
    public function advise(Collection $competitors): array
    {
        $own = $this->own();
        $done = json_decode((string) Setting::get('seo_done', '[]'), true) ?: [];
        $m = [];
        $add = function (string $id, int $prio, string $area, string $title, string $why, string $how, ?array $link = null) use (&$m, $done) {
            $m[] = compact('id', 'prio', 'area', 'title', 'why', 'how', 'link') + ['done' => in_array($id, $done, true)];
        };

        // 1. Grundlagen der eigenen Website
        if (! SiteSeo::indexingAllowed()) {
            $add('indexing', 1, 'Technik', 'Website für Google freischalten', 'Ihre Website ist für Suchmaschinen gesperrt. Solange das so ist, erscheint sie bei Google gar nicht.', 'Schalten Sie im SEO-Bereich „Google darf die Website finden“ ein.', [route('admin.seo'), 'Zum SEO-Bereich']);
        }
        $results = $own['content_pages']->map(fn (Page $p) => ['page' => $p, 'r' => $this->analyzer->analyzePage($p)]);
        foreach ($results->filter(fn ($x) => $x['r']['score'] < 60)->sortBy(fn ($x) => $x['r']['score'])->take(4) as $x) {
            $tips = collect($x['r']['checks'])->where('status', '!=', 'good')->take(3)->pluck('label')->implode(', ');
            $add('page-'.$x['page']->id, $x['page']->is_home ? 1 : 2, 'Inhalt', 'Seite „'.$x['page']->title.'“ verbessern ('.$x['r']['score'].' von 100)', 'Offene Punkte: '.$tips.'.', 'Öffnen Sie die Seite im Editor. Rechts zeigt der SEO-Check jeden Punkt mit Erklärung.', [route('admin.pages.edit', $x['page']), 'Seite bearbeiten']);
        }
        $noKeyword = $own['content_pages']->filter(fn (Page $p) => ! $p->focus_keyword)->count();
        if ($noKeyword) {
            $add('keywords', 2, 'Inhalt', $noKeyword.' '.($noKeyword === 1 ? 'Seite hat' : 'Seiten haben').' keinen Fokus-Suchbegriff', 'Ohne festen Suchbegriff weiß weder Google noch Nova CMS, wofür eine Seite gefunden werden soll.', 'Legen Sie je Seite einen Suchbegriff fest, z. B. „Fahrradreparatur Leipzig“. Der KI-Assistent macht auf Wunsch Vorschläge.', [route('admin.pages.index'), 'Zu den Seiten']);
        }
        $broken = $this->brokenLinks($own['pages']);
        if ($broken) {
            $add('broken-links', 1, 'Technik', count($broken).' defekte interne '.(count($broken) === 1 ? 'Link' : 'Links'), 'Links auf Seiten, die es nicht gibt, ärgern Besucher und kosten Vertrauen bei Google: '.implode(', ', array_slice($broken, 0, 4)).'.', 'Korrigieren Sie den Link im Text oder legen Sie unter SEO › Weiterleitungen eine Weiterleitung an.', [route('admin.seo.redirects'), 'Zu den Weiterleitungen']);
        }
        $notFound = NotFoundLog::where('hits', '>=', 3)->count();
        if ($notFound) {
            $add('not-found', 2, 'Technik', $notFound.' oft aufgerufene Adressen führen ins Leere', 'Besucher oder Google rufen Adressen auf, die es nicht mehr gibt, z. B. von einer früheren Website.', 'Leiten Sie diese Adressen mit einem Klick auf passende Seiten weiter.', [route('admin.seo.redirects'), 'Zu den Weiterleitungen']);
        }

        $analyzed = $competitors->filter(fn (Competitor $c) => is_array($c->data));
        $strengths = [];

        if ($analyzed->isNotEmpty()) {
            $n = $analyzed->count();
            $names = fn (Collection $cs) => $cs->map(fn (Competitor $c) => $c->label())->take(3)->implode(', ');

            // 2. Funktionen, die Mitbewerber haben und Sie nicht
            foreach (self::FEATURE_ADVICE as $key => [$title, $why, $how]) {
                $with = $analyzed->filter(fn (Competitor $c) => $c->data['features'][$key] ?? false);
                if ($own['features'][$key]) {
                    if ($with->count() < $n) {
                        $strengths[] = CompetitorCrawler::FEATURES[$key].': Sie bieten das, '.($n - $with->count()).' von '.$n.' Mitbewerbern nicht.';
                    }

                    continue;
                }
                if ($with->isEmpty() && ! in_array($key, ['local', 'accessibility', 'phone'], true)) {
                    continue;
                }
                $prio = in_array($key, ['local', 'accessibility', 'phone'], true) || $with->count() > $n / 2 ? 1 : 2;
                $whyFull = $with->isEmpty() ? $why : $why.' Vorhanden bei: '.$names($with).'.';
                $add('feature-'.$key, $prio, $key === 'local' ? 'Lokal' : 'Funktionen', $title, $whyFull, $how, $this->featureLink($key));
            }

            // 3. Themen aus den Menüs der Mitbewerber, die bei Ihnen fehlen
            $ownTopics = mb_strtolower(implode(' ', $own['menu']).' '.$own['pages']->pluck('title')->implode(' '));
            $topics = [];
            foreach ($analyzed as $c) {
                foreach ($c->data['menu'] ?? [] as $item) {
                    foreach (array_merge([$item['label']], $item['children'] ?? []) as $label) {
                        $key = mb_strtolower(trim($label));
                        if (mb_strlen($key) < 4 || preg_match('/^(home|start|startseite|kontakt|impressum|datenschutz|agb|login|anmelden|warenkorb|suche|menü|menu|mehr)$/u', $key)) {
                            continue;
                        }
                        $words = array_filter(preg_split('/[^\p{L}]+/u', $key), fn ($w) => mb_strlen($w) >= 4);
                        if ($words && ! collect($words)->contains(fn ($w) => str_contains($ownTopics, mb_substr($w, 0, max(4, mb_strlen($w) - 2))))) {
                            $topics[$label] = ($topics[$label] ?? 0) + 1;
                        }
                    }
                }
            }
            arsort($topics);
            if ($topics) {
                $list = array_slice(array_keys($topics), 0, 6);
                $add('topics', 2, 'Inhalt', 'Themen ergänzen, die Mitbewerber abdecken', 'In den Menüs Ihrer Mitbewerber stehen Themen, zu denen Sie keine Seite haben: „'.implode('“, „', $list).'“. Jede eigene Seite ist eine weitere Chance, bei Google gefunden zu werden.', 'Prüfen Sie, welche dieser Themen zu Ihrem Angebot passen, und legen Sie dafür je eine eigene Seite mit mindestens 300 Wörtern an.', [route('admin.pages.index'), 'Neue Seite anlegen']);
            }

            // 4. Suchbegriffe, auf die Mitbewerber optimieren
            $terms = [];
            foreach ($analyzed as $c) {
                foreach ($c->data['terms'] ?? [] as $term => $count) {
                    $terms[$term] = ($terms[$term] ?? 0) + $count;
                }
            }
            arsort($terms);
            $missing = array_slice(array_keys(array_filter($terms, fn ($cnt, $t) => ! str_contains($own['text'], mb_substr($t, 0, max(4, mb_strlen($t) - 1))), ARRAY_FILTER_USE_BOTH)), 0, 8);
            if ($missing) {
                $add('terms', 2, 'Inhalt', 'Wichtige Begriffe aufgreifen', 'Diese Begriffe stehen bei Mitbewerbern in Titeln und Überschriften, kommen bei Ihnen aber nicht vor: '.implode(', ', $missing).'. Google ordnet Seiten über solche Begriffe den Suchanfragen zu.', 'Verwenden Sie die passenden Begriffe in Überschriften und Texten, ohne sie zu wiederholen. Passt ein Begriff zu Ihrem Angebot, ist er ein guter Fokus-Suchbegriff.');
            }

            // 5. Textmenge
            $theirWords = (int) round($analyzed->avg(fn (Competitor $c) => $c->data['avg_words'] ?? 0));
            if ($theirWords > 0 && $own['avg_words'] < $theirWords * 0.7) {
                $add('words', 1, 'Inhalt', 'Mehr aussagekräftigen Text schreiben', 'Ihre Seiten haben im Schnitt '.$own['avg_words'].' Wörter, die Ihrer Mitbewerber '.$theirWords.'. Google versteht Seiten mit mehr Inhalt besser und zeigt sie eher an.', 'Ergänzen Sie auf Ihren wichtigsten Seiten Details: Ablauf, Vorteile, Einsatzgebiet, häufige Fragen. Der KI-Assistent hilft mit „Ausführlicher“.');
            } elseif ($theirWords > 0 && $own['avg_words'] >= $theirWords) {
                $strengths[] = 'Textmenge: Ihre Seiten sind mit durchschnittlich '.$own['avg_words'].' Wörtern ausführlicher als die Ihrer Mitbewerber ('.$theirWords.').';
            }

            // 6. Umfang der Website
            $theirPages = (int) round($analyzed->avg(fn (Competitor $c) => $c->data['sitemap_pages'] ?? count($c->data['pages'] ?? [])));
            if ($theirPages > 0 && $own['content_pages']->count() < $theirPages * 0.5) {
                $add('size', 3, 'Inhalt', 'Website schrittweise ausbauen', 'Ihre Mitbewerber haben im Schnitt etwa '.$theirPages.' Seiten, Sie '.$own['content_pages']->count().'. Mehr gute Seiten bedeuten mehr Einstiege über Google.', 'Planen Sie eine neue Seite pro Monat, z. B. je Leistung, Produktgruppe oder Einsatzort.');
            }

            // 7. Technik: Mitbewerber ohne https, ohne Beschreibung oder mit Bildern ohne Beschreibung
            $noDesc = $analyzed->filter(fn (Competitor $c) => collect($c->data['pages'] ?? [])->contains(fn ($p) => ($p['description'] ?? '') === ''));
            if ($noDesc->isNotEmpty() && $own['content_pages']->every(fn (Page $p) => $p->meta_description)) {
                $strengths[] = 'Beschreibungen für Google: Alle Ihre Seiten haben eine, bei '.$noDesc->count().' Mitbewerber(n) fehlen sie teilweise.';
            }
            if ($analyzed->contains(fn (Competitor $c) => ! ($c->data['https'] ?? true))) {
                $strengths[] = 'Mindestens ein Mitbewerber nutzt keine verschlüsselte Verbindung (https).';
            }
        }

        usort($m, fn ($a, $b) => [$a['done'], $a['prio']] <=> [$b['done'], $b['prio']]);

        return ['measures' => $m, 'strengths' => $strengths, 'own' => $own];
    }

    /** Interne Links in Texten, deren Ziel es nicht gibt */
    public function brokenLinks(Collection $pages): array
    {
        $slugs = Page::published()->pluck('slug')->all();
        $redirects = Redirect::pluck('from_path')->all();
        $known = ['shop', 'warenkorb', 'kasse', 'suche', 'bestellung', 'sitemap.xml', 'robots.txt', 'admin', 'login'];
        $broken = [];
        foreach ($pages as $p) {
            foreach (Builder::flatten($p->blocks ?? []) as $b) {
                $html = (string) ($b['data']['html'] ?? '');
                preg_match_all('/href="\/([a-z0-9\-\/]*)(?:[?#][^"]*)?"/i', $html, $mm);
                $urls = array_merge($mm[1], array_filter([$b['data']['button_url'] ?? null, $b['data']['button2_url'] ?? null, $b['data']['url2'] ?? null, $b['data']['url'] ?? null], fn ($u) => is_string($u) && str_starts_with($u, '/') && ! str_starts_with($u, '//') && ! str_starts_with($u, '/uploads/')));
                foreach ($urls as $u) {
                    $path = trim(strtok(ltrim($u, '/'), '?#') ?: '', '/');
                    $first = explode('/', $path)[0];
                    if ($path === '' || in_array($first, $known, true) || in_array($path, $slugs, true) || in_array('/'.$path, $redirects, true)) {
                        continue;
                    }
                    $broken['/'.$path] = true;
                }
            }
        }

        return array_keys($broken);
    }

    private function featureLink(string $key): ?array
    {
        return match ($key) {
            'whatsapp' => [route('admin.whatsapp'), 'WhatsApp einrichten'],
            'phone', 'local' => [route('admin.settings'), 'Zum Firmenprofil'],
            'accessibility' => [route('admin.accessibility'), 'Zur Barrierefreiheit'],
            'newsletter' => [route('admin.newsletter.index'), 'Zum Newsletter'],
            default => [route('admin.pages.index'), 'Zu den Seiten'],
        };
    }
}
