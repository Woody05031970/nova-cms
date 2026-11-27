<?php

/*
 * Startprüfung von Nova CMS.
 *
 * Läuft vor Laravel und zeigt bei typischen Problemen auf einem frischen Webspace
 * eine verständliche Seite statt „HTTP Error 500“. Bewusst in einfachem PHP geschrieben,
 * damit sie auch auf alten PHP-Versionen noch funktioniert und dort den Grund nennen kann.
 */

function nova_preflight_page($title, $items, $hint)
{
    if (! headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        header('Retry-After: 300');
    }
    $list = '';
    foreach ($items as $item) {
        $list .= '<li>'.$item.'</li>';
    }
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        .'<meta name="robots" content="noindex"><title>Nova CMS: Einrichtung</title><style>'
        .'body{margin:0;background:#f8fafc;color:#0f172a;font:16px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif}'
        .'main{max-width:42rem;margin:3rem auto;padding:0 1rem}'
        .'.card{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;padding:1.5rem 1.75rem;box-shadow:0 1px 2px rgba(0,0,0,.04)}'
        .'h1{font-size:1.4rem;margin:0 0 .5rem}li{margin:.6rem 0}code{background:#f1f5f9;padding:.1rem .35rem;border-radius:.3rem;font-size:.9em;word-break:break-all}'
        .'.hint{margin-top:1.25rem;color:#475569;font-size:.95rem}.logo{display:inline-block;width:2rem;height:2rem;border-radius:.5rem;background:#6d28d9;vertical-align:middle;margin-right:.5rem}'
        .'</style></head><body><main><p><span class="logo"></span><strong>Nova CMS</strong></p><div class="card">'
        .'<h1>'.$title.'</h1><ul>'.$list.'</ul><p class="hint">'.$hint.'</p></div></main></body></html>';
    exit;
}

(function () {
    $base = dirname(__DIR__);
    $problems = array();

    // 1. PHP-Version
    if (version_compare(PHP_VERSION, '8.3.0', '<')) {
        $problems[] = 'Auf Ihrem Webspace läuft <strong>PHP '.htmlspecialchars(PHP_VERSION).'</strong>. Nova CMS braucht <strong>PHP 8.3 oder neuer</strong>. '
            .'Die Version stellen Sie im Kundenbereich Ihres Hosters um, zum Beispiel bei IONOS unter <em>Hosting › PHP-Version</em> '
            .'oder bei Strato unter <em>Einstellungen › PHP-Version</em>. Danach diese Seite neu laden.';
    }

    // 2. Programmbibliotheken (Ordner vendor)
    if (! is_file($base.'/vendor/autoload.php')) {
        $problems[] = 'Der Ordner <code>vendor</code> fehlt oder ist unvollständig. Er enthält die Programmbibliotheken von Nova CMS. '
            .'Bitte laden Sie das <strong>Installationspaket</strong> (ZIP mit dem Ordner <code>vendor</code>) hoch, nicht den Quellcode von GitHub. '
            .'Prüfen Sie nach dem Hochladen, ob die Datei <code>vendor/autoload.php</code> auf dem Server liegt.';
    }

    // 3. Ordner, in die Nova CMS schreiben muss. Fehlende Unterordner werden angelegt
    //    (manche FTP-Programme übertragen leere Ordner nicht).
    $dirs = array(
        'storage', 'storage/app', 'storage/app/public', 'storage/framework', 'storage/framework/cache',
        'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs',
        'bootstrap/cache',
    );
    $notWritable = array();
    foreach ($dirs as $dir) {
        $path = $base.'/'.$dir;
        if (! is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        if (! is_dir($path) || ! is_writable($path)) {
            $notWritable[] = '<code>'.$dir.'</code>';
        }
    }
    if ($notWritable) {
        $problems[] = 'Nova CMS kann in diese Ordner nicht schreiben: '.implode(', ', $notWritable).'. '
            .'Bitte setzen Sie in Ihrem FTP-Programm die Rechte dieser Ordner auf <strong>755</strong> '
            .'(bei manchen Hostern <strong>775</strong>), jeweils mit allen Unterordnern.';
    }

    // 4. Grundeinstellungen: .env oder die Vorlage .env.example
    if (! is_file($base.'/.env') && ! is_file($base.'/.env.example') && ! is_writable($base)) {
        $problems[] = 'Die Datei <code>.env.example</code> fehlt, und Nova CMS darf im Hauptordner keine Dateien anlegen. '
            .'Manche FTP-Programme übertragen Dateien nicht, die mit einem Punkt beginnen. Schalten Sie im FTP-Programm '
            .'„versteckte Dateien anzeigen“ ein und laden Sie sie erneut hoch, oder geben Sie dem Hauptordner die Rechte 755.';
    }

    // 5. Benötigte PHP-Erweiterungen (nur wenn die Version passt, sonst ist die Liste irreführend)
    if (! $problems) {
        $missing = array();
        foreach (array('pdo', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo') as $ext) {
            if (! extension_loaded($ext)) {
                $missing[] = '<code>'.$ext.'</code>';
            }
        }
        if (! extension_loaded('pdo_sqlite') && ! extension_loaded('pdo_mysql')) {
            $missing[] = '<code>pdo_sqlite</code> oder <code>pdo_mysql</code>';
        }
        if ($missing) {
            $problems[] = 'Diese PHP-Erweiterungen fehlen: '.implode(', ', $missing).'. '
                .'Sie lassen sich meist im Kundenbereich des Hosters einschalten; sonst hilft der Support des Hosters.';
        }
    }

    // 6. Die .htaccess im Ordner public sorgt dafür, dass Unterseiten wie /admin funktionieren
    $htaccess = __DIR__.'/.htaccess';
    if (! is_file($htaccess) && is_writable(__DIR__)) {
        @file_put_contents($htaccess, "<IfModule mod_rewrite.c>\n    <IfModule mod_negotiation.c>\n        Options -MultiViews -Indexes\n    </IfModule>\n\n"
            ."    RewriteEngine On\n\n    RewriteCond %{HTTP:Authorization} .\n    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\n\n"
            ."    RewriteCond %{REQUEST_FILENAME} !-d\n    RewriteCond %{REQUEST_URI} (.+)/$\n    RewriteRule ^ %1 [L,R=301]\n\n"
            ."    RewriteCond %{REQUEST_FILENAME} !-d\n    RewriteCond %{REQUEST_FILENAME} !-f\n    RewriteRule ^ index.php [L]\n</IfModule>\n");
    }

    if ($problems) {
        nova_preflight_page(
            count($problems) === 1 ? 'Noch ein Schritt bis zur Installation' : 'Noch '.count($problems).' Schritte bis zur Installation',
            $problems,
            'Ihre Website ist noch nicht eingerichtet. Sobald alles erledigt ist, laden Sie diese Seite neu; dann startet der Installations-Assistent.'
        );
    }

    // Während der Einrichtung: Statt einer leeren Fehlerseite den Grund zeigen.
    // Nach der Installation bleiben Fehlerdetails verborgen (sie stehen dann in storage/logs).
    $installed = is_file($base.'/storage/app/installed.lock') || getenv('NOVA_INSTALLED_LOCK');
    if (! $installed) {
        register_shutdown_function(function () {
            $e = error_get_last();
            if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
                nova_preflight_page('Nova CMS konnte nicht starten', array(
                    'Fehlermeldung: <code>'.htmlspecialchars($e['message']).'</code>',
                    'Datei: <code>'.htmlspecialchars($e['file']).':'.(int) $e['line'].'</code>',
                ), 'Bitte schicken Sie diese Meldung an den Support. Häufige Ursache: Dateien wurden beim Hochladen nicht vollständig übertragen.');
            }
        });
    }
})();
