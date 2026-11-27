<?php

return [
    // Version des Nova-CMS-Kerns. Plugins können eine Mindestversion verlangen.
    'version' => '0.1.0',

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

    // Mindestanforderungen, die der Installer prüft.
    'requirements' => [
        'php' => '8.3.0',
        'extensions' => ['pdo', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo', 'zip'],
        'writable' => ['storage', 'bootstrap/cache', 'database', 'plugins'],
    ],
];
