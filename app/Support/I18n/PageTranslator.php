<?php

namespace App\Support\I18n;

use App\Ai\AiManager;
use App\Models\Page;

/** Legt die Übersetzung einer Seite an, auf Wunsch mit KI übersetzt */
class PageTranslator
{
    /** Diese Felder enthalten keine Texte zum Übersetzen */
    private const SKIP_KEYS = ['id', 'type', 'image', 'images', 'src', 'url', 'href', 'link', 'video', 'poster', 'icon', 'color', 'style', 'layout',
        'variant', 'align', 'service', 'product', 'products', 'email', 'phone', 'target', 'size', 'columns', 'anim', 'animation', 'background', 'width'];

    public function __construct(private readonly AiManager $ai) {}

    public function create(Page $original, string $locale, bool $useAi): Page
    {
        $data = [
            'title' => $original->title, 'meta_title' => $original->meta_title, 'meta_description' => $original->meta_description,
            'blocks' => $original->blocks ?? [],
        ];
        $translated = false;
        if ($useAi && $this->ai->isConfigured()) {
            $data = $this->translate($data, $locale);
            $translated = true;
        }

        $page = Page::create([
            'title' => $data['title'], 'meta_title' => $data['meta_title'], 'meta_description' => $data['meta_description'],
            'blocks' => Page::cleanBlocks($data['blocks']),
            'slug' => Page::uniqueSlug($translated ? $data['title'] : $original->slug.'-'.$locale),
            'status' => 'draft', 'locale' => $locale, 'translation_of' => $original->id, 'show_in_menu' => false,
            'visibility' => $original->visibility ?? 'public',
        ]);

        return $page;
    }

    /** Übersetzt Titel, Beschreibung und alle Texte der Bausteine in einem Durchgang */
    public function translate(array $data, string $locale): array
    {
        $strings = [];
        $collect = function (&$node) use (&$collect, &$strings) {
            foreach ($node as $key => &$value) {
                if (is_array($value)) {
                    if (! in_array($key, self::SKIP_KEYS, true) || is_int($key)) {
                        $collect($value);
                    }
                } elseif (is_string($value) && ! in_array($key, self::SKIP_KEYS, true) && self::isText($value)) {
                    $strings[] = &$value;
                }
            }
        };
        $fields = ['title' => $data['title'], 'meta_title' => $data['meta_title'] ?? '', 'meta_description' => $data['meta_description'] ?? '', 'blocks' => $data['blocks']];
        $collect($fields);
        if ($strings === []) {
            return $data;
        }

        $language = Languages::name($locale, false);
        $result = $this->ai->json(
            'Du bist ein professioneller Übersetzer für Websites. Übersetze aus dem Deutschen ins '.$language.'. '
            .'Behalte HTML-Tags, Platzhalter, Zahlen, Preise, Markennamen und Zeilenumbrüche genau bei. Übersetze natürlich und für Suchmaschinen geeignet, nicht wörtlich. '
            .'Gib genau so viele Einträge in derselben Reihenfolge zurück.',
            json_encode(['texte' => array_map(fn ($s) => $s, $strings)], JSON_UNESCAPED_UNICODE),
            ['type' => 'object', 'additionalProperties' => false, 'required' => ['texte'], 'properties' => ['texte' => ['type' => 'array', 'items' => ['type' => 'string']]]],
            16000,
        );
        $out = $result['texte'] ?? [];
        if (count($out) === count($strings)) {
            foreach ($strings as $i => &$ref) {
                $ref = (string) $out[$i];
            }
            unset($ref);
        }

        return ['title' => $fields['title'], 'meta_title' => $fields['meta_title'] ?: null, 'meta_description' => $fields['meta_description'] ?: null, 'blocks' => $fields['blocks']];
    }

    private static function isText(string $v): bool
    {
        $v = trim($v);

        // Einzelne kleingeschriebene Wörter sind meist Einstellungen wie „dark“ oder „left“
        return $v !== '' && preg_match('/\p{L}{2}/u', $v) === 1
            && preg_match('#^(https?:|/|\#|mailto:|tel:)#i', $v) !== 1
            && preg_match('/^[a-z0-9_-]+$/', $v) !== 1;
    }
}
