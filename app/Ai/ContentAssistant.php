<?php

namespace App\Ai;

use App\Models\Setting;

/**
 * Die konkreten Schreibaufgaben der KI: Texte überarbeiten, Google-Beschreibungen, erste Seiten.
 */
class ContentAssistant
{
    /** Aktionen im Text-Editor: Schlüssel => [Beschriftung, Anweisung] */
    public const ACTIONS = [
        'verbessern' => ['Verbessern', 'Verbessere den Text: klarer, flüssiger und überzeugender. Inhalt und Aussage bleiben gleich.'],
        'kuerzer' => ['Kürzer', 'Kürze den Text auf etwa die Hälfte. Das Wichtigste bleibt erhalten.'],
        'laenger' => ['Ausführlicher', 'Schreibe den Text etwas ausführlicher, mit konkreten Details und Beispielen. Erfinde keine Fakten wie Zahlen, Preise, Namen oder Auszeichnungen.'],
        'freundlicher' => ['Freundlicher', 'Formuliere den Text freundlicher, wärmer und persönlicher.'],
        'professioneller' => ['Professioneller', 'Formuliere den Text sachlicher und professioneller.'],
        'rechtschreibung' => ['Rechtschreibung prüfen', 'Korrigiere nur Rechtschreibung, Grammatik und Zeichensetzung. Ändere sonst nichts.'],
        'englisch' => ['Ins Englische übersetzen', 'Übersetze den Text ins Englische.'],
    ];

    public function __construct(private readonly AiManager $ai) {}

    /** Überarbeitet HTML aus dem Text-Editor und liefert HTML zurück. */
    public function rewrite(string $action, string $html): string
    {
        $instruction = self::ACTIONS[$action][1] ?? self::ACTIONS['verbessern'][1];

        $system = $this->context()."\n\nDu überarbeitest einen Textabschnitt dieser Website. "
            .'Du erhältst HTML und antwortest ausschließlich mit dem überarbeiteten HTML, ohne Erklärung und ohne Code-Block. '
            .'Erlaubte Tags: p, h2, h3, h4, strong, em, u, ul, ol, li, blockquote, a, br. Links (a href) bleiben unverändert erhalten. '
            .'Wenn die Eingabe nur reiner Text ohne Tags ist, antworte ebenfalls mit reinem Text ohne Tags.';

        $out = $this->ai->text($system, $instruction."\n\n".$html, 8000);

        return preg_replace('/^```(?:html)?\s*|\s*```$/', '', trim($out));
    }

    /** Schlägt eine Beschreibung für Google vor (höchstens 160 Zeichen). */
    public function metaDescription(string $title, string $text): string
    {
        $system = $this->context()."\n\nDu schreibst Beschreibungen für Google-Suchergebnisse (Meta-Description). "
            .'Regeln: Deutsch, 120 bis 155 Zeichen, ein bis zwei Sätze, macht neugierig, enthält das wichtigste Suchwort, '
            .'keine Anführungszeichen, keine Emojis, keine erfundenen Fakten. Antworte nur mit der Beschreibung.';

        $out = $this->ai->text($system, "Seitentitel: {$title}\n\nSeitentext:\n".mb_substr($text, 0, 6000), 4000);
        $out = trim(preg_replace('/\s+/', ' ', $out), " \"'„“");

        return mb_strlen($out) > 160 ? rtrim(mb_substr($out, 0, 157)).'…' : $out;
    }

    /**
     * Erzeugt die ersten Seiten einer Website.
     *
     * @param  array{offer: string, audience: ?string, tone: string, highlights: ?string, pages: list<string>}  $answers
     * @return array{tagline: string, pages: list<array<string, mixed>>}
     */
    public function siteDraft(array $answers): array
    {
        $tones = [
            'persoenlich' => 'freundlich und persönlich, Sie-Form',
            'professionell' => 'sachlich und professionell, Sie-Form',
            'locker' => 'locker und modern, Du-Form',
        ];
        $pageNames = WebsiteWizard::PAGES;
        $wanted = collect($answers['pages'])->map(fn ($p) => "- {$p}: ".$pageNames[$p][1])->implode("\n");

        $system = $this->context()."\n\nDu bist ein erfahrener Texter für Websites kleiner Unternehmen im deutschsprachigen Raum. "
            .'Du schreibst die ersten Seiten einer neuen Website. Die Texte sollen konkret, gut lesbar und für Google geeignet sein. '
            .'Erfinde keine überprüfbaren Fakten wie Preise, Jahreszahlen, Auszeichnungen, Kundenzahlen oder Namen von Personen. '
            .'Wo eine konkrete Angabe fehlt, setze einen kurzen Platzhalter in eckigen Klammern, z. B. [Öffnungszeiten ergänzen]. '
            .'Impressum und Datenschutzerklärung schreibst du nicht.';

        $prompt = "Angebot: {$answers['offer']}\n"
            .'Zielgruppe: '.(($answers['audience'] ?? null) ?: 'nicht angegeben')."\n"
            .'Besonderheiten: '.(($answers['highlights'] ?? null) ?: 'nicht angegeben')."\n"
            .'Tonfall: '.($tones[$answers['tone']] ?? $tones['persoenlich'])."\n\n"
            ."Schreibe diese Seiten (das Feld key übernimmst du genau so):\n{$wanted}\n\n"
            .'Jede Seite hat 2 bis 4 Abschnitte. Jeder Abschnitt hat eine Überschrift, 1 bis 3 Absätze und optional eine kurze Aufzählung. '
            .'Die Startseite bekommt zusätzlich ein Titelbild (hero) mit kurzer Überschrift, einem Satz darunter und einem Knopf-Text. '
            .'Bei anderen Seiten ist hero null. Dazu je Seite eine Beschreibung für Google (120 bis 155 Zeichen) '
            .'und für die ganze Website eine Kurzbeschreibung in einem Satz (tagline).';

        return $this->ai->json($system, $prompt, self::siteSchema(array_values($answers['pages'])), 16000);
    }

    /** @param  list<string>  $keys */
    public static function siteSchema(array $keys): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['tagline', 'pages'],
            'properties' => [
                'tagline' => $string,
                'pages' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['key', 'title', 'meta_description', 'hero', 'sections'],
                        'properties' => [
                            'key' => ['type' => 'string', 'enum' => $keys],
                            'title' => $string,
                            'meta_description' => $string,
                            'hero' => [
                                'anyOf' => [
                                    ['type' => 'null'],
                                    [
                                        'type' => 'object',
                                        'additionalProperties' => false,
                                        'required' => ['title', 'text', 'button_label'],
                                        'properties' => ['title' => $string, 'text' => $string, 'button_label' => $string],
                                    ],
                                ],
                            ],
                            'sections' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'required' => ['heading', 'paragraphs', 'bullets'],
                                    'properties' => [
                                        'heading' => $string,
                                        'paragraphs' => ['type' => 'array', 'items' => $string],
                                        'bullets' => ['type' => 'array', 'items' => $string],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** Was die KI über das Unternehmen wissen sollte. */
    private function context(): string
    {
        $facts = collect([
            'Website' => Setting::get('site_name'),
            'Unternehmen' => Setting::get('company_name'),
            'Branche' => Setting::get('industry'),
            'Kurzbeschreibung' => Setting::get('tagline'),
            'Ort' => Setting::get('zip_city'),
        ])->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");

        return 'Du arbeitest im Content-Management-System Nova CMS für dieses Unternehmen:'."\n"
            .($facts ?: 'Noch keine Angaben.')."\n"
            .'Schreibe in natürlichem, fehlerfreiem Deutsch (Deutschland), sofern nicht anders verlangt.';
    }
}
