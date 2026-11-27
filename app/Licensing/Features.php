<?php

namespace App\Licensing;

/**
 * Welche Funktionen diese Installation nutzen darf.
 *
 * - Hersteller-Installation (Lizenzserver) und Entwicklerversion: alles.
 * - Testphase: alles, damit Interessenten jede Funktion ausprobieren können.
 * - Gekaufte Lizenz: Kernfunktionen plus die Module aus der Lizenz (z. B. „newsletter“).
 */
class Features
{
    /** Schlüssel => [Name, Beschreibung] */
    public const ALL = [
        'design-pro' => ['Design Pro', 'Alle Design-Vorlagen, eigene Akzentfarbe und Schrift, Einblend-Effekte und Hintergrundvideos.'],
        'newsletter' => ['Newsletter', 'Anmeldung mit Bestätigung per E-Mail, Abonnentenverwaltung und Versand an alle Abonnenten.'],
        'whatsapp' => ['WhatsApp', 'Chat-Knopf auf jeder Seite und „Per WhatsApp fragen“ bei Produkten.'],
        'seo-pro' => ['SEO Pro', 'Mitbewerber-Analyse mit Menü, Themen und Funktionen sowie eine Maßnahmenliste für besseres Ranking.'],
        'geo' => ['KI-Sichtbarkeit', 'Strukturierte Daten je Seite, llms.txt und Prüfung, ob ChatGPT, Gemini und Co. Ihre Inhalte gut zitieren können.'],
        'social' => ['Social-Media-Beiträge', 'Beiträge für Instagram, Facebook und LinkedIn aus einer Seite oder einem Produkt, per KI oder Vorlage.'],
        'redaktionsplan' => ['Redaktionsplan', 'Seiten zeitgesteuert veröffentlichen und Themen, Aktionen und Beiträge im Kalender planen.'],
        'statistik' => ['Besucherstatistik', 'Aufrufe, beliebte Seiten, Herkunft und Suchbegriffe, ohne Cookies und ohne fremde Dienste.'],
        'turbo' => ['Turbo', 'Zwischenspeicher für Besucher: Seiten laden deutlich schneller, gut für Google (Core Web Vitals).'],
    ];

    public function __construct(private readonly License $license) {}

    public function enabled(string $feature): bool
    {
        if (LicenseServer::enabled()) {
            return true; // Hersteller-Installation
        }

        return match ($this->license->status()) {
            License::STATUS_DEV, License::STATUS_TRIAL => true,
            License::STATUS_ACTIVE => $this->license->allows($feature),
            default => false,
        };
    }

    /** @return array<string, array{name: string, description: string, enabled: bool}> */
    public function overview(): array
    {
        $out = [];
        foreach (self::ALL as $key => [$name, $description]) {
            $out[$key] = ['name' => $name, 'description' => $description, 'enabled' => $this->enabled($key)];
        }

        return $out;
    }

    public static function on(string $feature): bool
    {
        return app(self::class)->enabled($feature);
    }
}
