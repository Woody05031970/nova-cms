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
