<?php

namespace App\Support\Install;

/**
 * Prüft, ob der Webspace alles mitbringt. Jede Meldung ist so formuliert,
 * dass ein Laie weiß, was zu tun ist.
 */
class Requirements
{
    /** @return list<array{label: string, ok: bool, help: string}> */
    public function check(): array
    {
        $req = config('nova.requirements');
        $checks = [];

        $checks[] = [
            'label' => 'PHP-Version '.PHP_VERSION.' (benötigt: '.$req['php'].' oder neuer)',
            'ok' => version_compare(PHP_VERSION, $req['php'], '>='),
            'help' => 'Stellen Sie im Kundenbereich Ihres Hosters (z. B. IONOS oder Strato unter "PHP-Einstellungen") die neueste PHP-Version ein.',
        ];

        foreach ($req['extensions'] as $ext) {
            $checks[] = [
                'label' => 'PHP-Erweiterung "'.$ext.'"',
                'ok' => extension_loaded($ext),
                'help' => 'Diese Erweiterung fehlt. Sie lässt sich meist in den PHP-Einstellungen Ihres Hosters aktivieren, sonst hilft der Support des Hosters.',
            ];
        }

        $checks[] = [
            'label' => 'Datenbank-Treiber (SQLite oder MySQL)',
            'ok' => extension_loaded('pdo_sqlite') || extension_loaded('pdo_mysql'),
            'help' => 'Es wird mindestens einer der Treiber pdo_sqlite oder pdo_mysql benötigt.',
        ];

        foreach ($req['writable'] as $dir) {
            $path = base_path($dir);
            if (! is_dir($path)) {
                @mkdir($path, 0755, true);
            }
            $checks[] = [
                'label' => 'Ordner "'.$dir.'" ist beschreibbar',
                'ok' => is_dir($path) && is_writable($path),
                'help' => 'Setzen Sie per FTP (z. B. mit FileZilla: Rechtsklick, "Dateiberechtigungen") die Rechte dieses Ordners auf 755.',
            ];
        }

        $env = config('nova.env_path');
        $checks[] = [
            'label' => 'Konfigurationsdatei (.env) ist beschreibbar',
            'ok' => is_writable($env) || (! file_exists($env) && is_writable(dirname($env))),
            'help' => 'Setzen Sie die Rechte der Datei .env im Hauptordner auf 644.',
        ];

        return $checks;
    }

    public function passes(): bool
    {
        return collect($this->check())->every(fn ($c) => $c['ok']);
    }
}
