<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Product;
use App\Shop\ShopSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Suche auf der Website: Seiten und Produkte, mit Textauszug rund um den Treffer. */
class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 100));
        $results = [];

        if (mb_strlen($q) >= 2) {
            $terms = array_values(array_filter(preg_split('/\s+/u', mb_strtolower($q)), fn ($t) => mb_strlen($t) >= 2));
            foreach (Page::published()->get() as $page) {
                $text = $page->plainText();
                $score = $this->score($page->title, $text.' '.$page->meta_description, $terms);
                if ($score > 0) {
                    $results[] = ['title' => $page->title, 'url' => $page->url(), 'type' => 'Seite', 'excerpt' => $this->excerpt($page->meta_description ?: $text, $terms), 'score' => $score];
                }
            }
            if (ShopSettings::enabled()) {
                foreach (Product::active()->get() as $p) {
                    $text = $p->short_description.' '.strip_tags((string) $p->description);
                    $score = $this->score($p->name, $text, $terms);
                    if ($score > 0) {
                        $results[] = ['title' => $p->name, 'url' => $p->url(), 'type' => 'Produkt', 'excerpt' => $this->excerpt($text, $terms), 'score' => $score + 1];
                    }
                }
            }
            usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);
        }

        return view('site.search', ['q' => $q, 'results' => array_slice($results, 0, 30)]);
    }

    /** Alle Suchwörter müssen vorkommen; Treffer im Titel zählen mehr. */
    private function score(string $title, string $text, array $terms): int
    {
        $title = mb_strtolower($title);
        $text = mb_strtolower($text);
        $score = 0;
        foreach ($terms as $t) {
            $inTitle = str_contains($title, $t);
            $inText = substr_count($text, $t);
            if (! $inTitle && ! $inText) {
                return 0;
            }
            $score += ($inTitle ? 10 : 0) + min(5, $inText);
        }

        return $score;
    }

    private function excerpt(string $text, array $terms): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
        $pos = null;
        foreach ($terms as $t) {
            $p = mb_stripos($text, $t);
            if ($p !== false) {
                $pos = $pos === null ? $p : min($pos, $p);
            }
        }
        $start = max(0, ($pos ?? 0) - 60);

        return ($start > 0 ? '… ' : '').Str::limit(mb_substr($text, $start), 200);
    }
}
