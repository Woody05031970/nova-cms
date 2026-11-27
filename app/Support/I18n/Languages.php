<?php

namespace App\Support\I18n;

use App\Licensing\Features;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Modul „Mehrsprachigkeit“: Seiten in weiteren Sprachen unter /en/…, /fr/… usw.
 * Hauptsprache ist Deutsch (ohne Kürzel in der Adresse). Eine Übersetzung ist eine eigene Seite
 * mit locale und translation_of (Verweis auf die deutsche Seite).
 */
class Languages
{
    public const MAIN = 'de';

    /** Kürzel => [Name in der Sprache selbst, deutscher Name] */
    public const AVAILABLE = [
        'en' => ['English', 'Englisch'], 'fr' => ['Français', 'Französisch'], 'it' => ['Italiano', 'Italienisch'],
        'es' => ['Español', 'Spanisch'], 'nl' => ['Nederlands', 'Niederländisch'], 'pl' => ['Polski', 'Polnisch'],
        'cs' => ['Čeština', 'Tschechisch'], 'da' => ['Dansk', 'Dänisch'], 'pt' => ['Português', 'Portugiesisch'], 'tr' => ['Türkçe', 'Türkisch'],
    ];

    /** Feste Texte der Website-Vorlage */
    private const DICT = [
        'Zum Inhalt springen' => ['en' => 'Skip to content', 'fr' => 'Aller au contenu', 'it' => 'Vai al contenuto', 'es' => 'Ir al contenido', 'nl' => 'Naar de inhoud', 'pl' => 'Przejdź do treści', 'cs' => 'Přejít na obsah', 'da' => 'Gå til indhold', 'pt' => 'Ir para o conteúdo', 'tr' => 'İçeriğe geç'],
        'Hauptmenü' => ['en' => 'Main menu', 'fr' => 'Menu principal', 'it' => 'Menu principale', 'es' => 'Menú principal', 'nl' => 'Hoofdmenu', 'pl' => 'Menu główne', 'cs' => 'Hlavní menu', 'da' => 'Hovedmenu', 'pt' => 'Menu principal', 'tr' => 'Ana menü'],
        'Menü öffnen' => ['en' => 'Open menu', 'fr' => 'Ouvrir le menu', 'it' => 'Apri menu', 'es' => 'Abrir menú', 'nl' => 'Menu openen', 'pl' => 'Otwórz menu', 'cs' => 'Otevřít menu', 'da' => 'Åbn menu', 'pt' => 'Abrir menu', 'tr' => 'Menüyü aç'],
        'Menü schließen' => ['en' => 'Close menu', 'fr' => 'Fermer le menu', 'it' => 'Chiudi menu', 'es' => 'Cerrar menú', 'nl' => 'Menu sluiten', 'pl' => 'Zamknij menu', 'cs' => 'Zavřít menu', 'da' => 'Luk menu', 'pt' => 'Fechar menu', 'tr' => 'Menüyü kapat'],
        'Website durchsuchen' => ['en' => 'Search website', 'fr' => 'Rechercher', 'it' => 'Cerca nel sito', 'es' => 'Buscar', 'nl' => 'Zoeken', 'pl' => 'Szukaj', 'cs' => 'Hledat', 'da' => 'Søg', 'pt' => 'Pesquisar', 'tr' => 'Ara'],
        'Sprache' => ['en' => 'Language', 'fr' => 'Langue', 'it' => 'Lingua', 'es' => 'Idioma', 'nl' => 'Taal', 'pl' => 'Język', 'cs' => 'Jazyk', 'da' => 'Sprog', 'pt' => 'Idioma', 'tr' => 'Dil'],
        'Rechtliches' => ['en' => 'Legal', 'fr' => 'Mentions légales', 'it' => 'Note legali', 'es' => 'Aviso legal', 'nl' => 'Juridisch', 'pl' => 'Informacje prawne', 'cs' => 'Právní informace', 'da' => 'Juridisk', 'pt' => 'Informação legal', 'tr' => 'Yasal'],
        'zur Startseite' => ['en' => 'to the home page', 'fr' => 'vers l’accueil', 'it' => 'alla home page', 'es' => 'a la página de inicio', 'nl' => 'naar de startpagina', 'pl' => 'do strony głównej', 'cs' => 'na úvodní stránku', 'da' => 'til forsiden', 'pt' => 'para a página inicial', 'tr' => 'ana sayfaya'],
    ];

    private static string $current = self::MAIN;

    public static function on(): bool
    {
        return Features::on('mehrsprachigkeit') && self::active() !== [];
    }

    /** @return list<string> weitere Sprachen, die eingeschaltet sind */
    public static function active(): array
    {
        $list = json_decode((string) Setting::get('languages'), true);

        return is_array($list) ? array_values(array_intersect(array_keys(self::AVAILABLE), $list)) : [];
    }

    /** @return list<string> Hauptsprache und weitere Sprachen */
    public static function all(): array
    {
        return [self::MAIN, ...self::active()];
    }

    public static function isActive(?string $locale): bool
    {
        return $locale !== null && in_array($locale, self::active(), true) && Features::on('mehrsprachigkeit');
    }

    public static function name(string $locale, bool $native = true): string
    {
        return $locale === self::MAIN ? 'Deutsch' : (self::AVAILABLE[$locale][$native ? 0 : 1] ?? strtoupper($locale));
    }

    public static function current(): string
    {
        return self::$current;
    }

    public static function setCurrent(?string $locale): void
    {
        self::$current = $locale && isset(self::AVAILABLE[$locale]) ? $locale : self::MAIN;
    }

    public static function t(string $german): string
    {
        return self::DICT[$german][self::$current] ?? $german;
    }

    public static function homeUrl(?string $locale = null): string
    {
        $locale ??= self::$current;

        return $locale === self::MAIN || ! self::isActive($locale) ? route('home') : route('home.locale', $locale);
    }

    /** Deutsche Ausgangsseite einer Seite */
    public static function original(Page $page): Page
    {
        return $page->translation_of ? ($page->source ?? $page) : $page;
    }

    /**
     * Veröffentlichte Fassungen einer Seite in allen Sprachen
     *
     * @return Collection<string, Page>
     */
    public static function variants(Page $page): Collection
    {
        if (! self::on()) {
            return collect();
        }
        $original = self::original($page);
        $out = collect();
        if ($original->isPublished() && $original->locale === null) {
            $out[self::MAIN] = $original;
        }
        foreach (Page::published()->where('translation_of', $original->id)->whereIn('locale', self::active())->get() as $t) {
            $out[$t->locale] = $t;
        }

        return $out->count() > 1 ? $out : collect();
    }

    /**
     * Links für die Sprachauswahl: Übersetzung der aktuellen Seite oder die Startseite der Sprache
     *
     * @return list<array{locale: string, name: string, url: string, current: bool}>
     */
    public static function switcher(?Page $page = null): array
    {
        if (! self::on()) {
            return [];
        }
        $variants = $page ? self::variants($page) : collect();

        return array_map(fn ($l) => [
            'locale' => $l, 'name' => self::name($l), 'current' => $l === self::$current,
            'url' => isset($variants[$l]) ? $variants[$l]->url() : self::homeUrl($l),
        ], self::all());
    }
}
