<?php

namespace App\Ai;

use App\Models\Page;
use App\Support\Pages\PageTemplates;
use Illuminate\Support\Str;

/**
 * Macht aus der KI-Antwort des Website-Assistenten echte Seitenentwürfe.
 */
class WebsiteWizard
{
    /** Seiten, die der Assistent schreiben kann: Schlüssel => [Titel, Inhalt] */
    public const PAGES = [
        'start' => ['Startseite', 'Begrüßung, was das Unternehmen anbietet, warum man hierher kommen sollte, Aufruf zur Kontaktaufnahme'],
        'leistungen' => ['Leistungen', 'die Angebote oder Produkte im Überblick, jeweils mit Nutzen für den Kunden'],
        'ueber' => ['Über uns', 'wer hinter dem Unternehmen steht, Werte und Arbeitsweise'],
        'faq' => ['Häufige Fragen', '4 bis 6 typische Kundenfragen als Überschrift mit kurzer Antwort darunter'],
        'kontakt' => ['Kontakt', 'kurze Einladung, sich zu melden, und wie man am besten Kontakt aufnimmt (die Adressdaten fügt das System selbst an)'],
    ];

    /**
     * @param  array{tagline?: string, pages?: list<array<string, mixed>>}  $draft
     * @param  list<string>  $wanted
     * @return list<Page>
     */
    public function createPages(array $draft, array $wanted, int $userId): array
    {
        $created = [];
        $order = (int) Page::max('menu_order');

        foreach ($draft['pages'] ?? [] as $p) {
            $key = $p['key'] ?? null;
            if (! in_array($key, $wanted, true) || isset($created[$key])) {
                continue;
            }

            $isHome = $key === 'start' && ! Page::where('is_home', true)->exists();
            $title = Str::limit(trim((string) ($p['title'] ?? '')) ?: self::PAGES[$key][0], 150, '');

            $created[$key] = Page::create([
                'title' => $title,
                'slug' => Page::uniqueSlug($isHome ? 'startseite' : $title),
                'blocks' => Page::cleanBlocks($this->blocks($key, $p)),
                'meta_description' => Str::limit(trim((string) ($p['meta_description'] ?? '')), 300, ''),
                'is_home' => $isHome,
                'show_in_menu' => true,
                'menu_order' => ++$order,
                'status' => 'draft',
                'updated_by' => $userId,
            ]);
        }

        return array_values($created);
    }

    /** @return list<array<string, mixed>> */
    private function blocks(string $key, array $p): array
    {
        $blocks = [];
        $id = fn () => Str::lower(Str::random(10));

        if (! empty($p['hero']) && is_array($p['hero'])) {
            $blocks[] = ['id' => $id(), 'type' => 'hero', 'data' => [
                'title' => (string) ($p['hero']['title'] ?? ''),
                'text' => (string) ($p['hero']['text'] ?? ''),
                'image' => null,
                'button_label' => (string) ($p['hero']['button_label'] ?? 'Kontakt aufnehmen'),
                'button_url' => '/kontakt',
            ]];
        }

        foreach ($p['sections'] ?? [] as $section) {
            $html = '';
            if (! empty($section['heading'])) {
                $html .= '<h2>'.e($section['heading']).'</h2>';
            }
            foreach ($section['paragraphs'] ?? [] as $para) {
                $html .= '<p>'.e($para).'</p>';
            }
            $bullets = array_filter($section['bullets'] ?? [], fn ($b) => trim((string) $b) !== '');
            if ($bullets) {
                $html .= '<ul>'.collect($bullets)->map(fn ($b) => '<li>'.e($b).'</li>')->implode('').'</ul>';
            }
            if ($html !== '') {
                $blocks[] = ['id' => $id(), 'type' => 'text', 'data' => ['html' => $html]];
            }
        }

        // Die Kontaktdaten kommen aus dem Firmenprofil, nicht von der KI.
        if ($key === 'kontakt') {
            $blocks = array_merge($blocks, PageTemplates::make('kontakt')['blocks']);
        }

        if ($key === 'start' || $key === 'leistungen') {
            $blocks[] = ['id' => $id(), 'type' => 'button', 'data' => ['label' => 'Jetzt anfragen', 'url' => '/kontakt', 'style' => 'primary', 'align' => 'center']];
        }

        return $blocks ?: [['id' => $id(), 'type' => 'text', 'data' => ['html' => '<p></p>']]];
    }
}
