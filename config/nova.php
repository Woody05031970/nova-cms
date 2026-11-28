<?php

return [
    // Version des Nova-CMS-Kerns. Plugins können eine Mindestversion verlangen.
    'version' => '0.9.1',

    // Wo die Konfigurationsdatei liegt, die der Installer schreibt.
    'env_path' => env('NOVA_ENV_PATH', base_path('.env')),

    // Existiert diese Datei, gilt Nova CMS als installiert.
    'installed_lock' => env('NOVA_INSTALLED_LOCK', storage_path('app/installed.lock')),

    // Speicherort der SQLite-Datenbank, wenn im Installer "Einfach" gewählt wird.
    'sqlite_path' => env('NOVA_SQLITE_PATH', database_path('nova.sqlite')),

    // Ordner, in dem installierte Plugins liegen.
    'plugins_path' => env('NOVA_PLUGINS_PATH', base_path('plugins')),

    // Merkliste der aktiven Plugins, damit beim Seitenaufruf keine Datenbankabfrage nötig ist.
    'plugins_state' => env('NOVA_PLUGINS_STATE', storage_path('app/nova-plugins.json')),

    // Fingerabdruck der Programmdateien für den Sicherheits-Check.
    'core_hashes' => env('NOVA_CORE_HASHES', storage_path('app/nova-core-hashes.json')),

    // Maximale Größe eines Plugin-Pakets in Kilobyte.
    'plugin_max_kb' => 20480,

    // Lizenzen und Modul-Marktplatz
    'license' => [
        // Adresse des Lizenzservers (Ihre Verkaufs-Website mit eingeschaltetem Lizenzserver)
        'server' => env('NOVA_LICENSE_SERVER', 'https://lizenz.nova-cms.de'),
        // Öffentlicher Schlüssel des Lizenzservers (php artisan nova:license-keys). Steht in der Datei nova-license.pub
        // der Kaufversion. Fehlt er, läuft Nova CMS als Entwicklerversion ohne Lizenzprüfung.
        'public_key' => env('NOVA_LICENSE_PUBLIC_KEY', is_file(base_path('nova-license.pub')) ? trim((string) file_get_contents(base_path('nova-license.pub'))) : ''),
        // Nur auf dem Lizenzserver: geheimer Schlüssel. Ist er gesetzt, arbeitet diese Installation als Lizenzserver.
        'private_key' => env('NOVA_LICENSE_PRIVATE_KEY', ''),
        // Testzeitraum ohne Lizenz
        'trial_days' => (int) env('NOVA_TRIAL_DAYS', 30),
        // Nach dem Testzeitraum ist die Verwaltung bis zur Eingabe einer Lizenz gesperrt (die Website bleibt online).
        'lock_after_trial' => (bool) env('NOVA_LOCK_AFTER_TRIAL', true),
        // So lange gilt eine Lizenz ohne Verbindung zum Server weiter (Tage)
        'offline_grace_days' => 14,
    ],

    // Mindestanforderungen, die der Installer prüft.
    'requirements' => [
        'php' => '8.3.0',
        'extensions' => ['pdo', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo', 'zip', 'sodium'],
        'writable' => ['storage', 'bootstrap/cache', 'database', 'plugins'],
    ],
];
