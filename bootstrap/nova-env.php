<?php

/*
 * Beim allerersten Aufruf auf einem frischen Webspace gibt es noch keine .env-Datei.
 * Wir legen sie aus .env.example an und erzeugen einen geheimen Schlüssel,
 * damit der Installer im Browser starten kann.
 */
(function () {
    $base = dirname(__DIR__);
    $env = $base.'/.env';
    $example = $base.'/.env.example';

    if (file_exists($env) || ! file_exists($example)) {
        return;
    }

    $content = file_get_contents($example);
    $key = 'base64:'.base64_encode(random_bytes(32));
    $content = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$key, $content);

    if (@file_put_contents($env, $content) === false) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>Nova CMS kann nicht starten</h1>';
        echo '<p>Die Datei <code>.env</code> konnte nicht angelegt werden. ';
        echo 'Bitte geben Sie dem Nova-CMS-Ordner Schreibrechte (z.&nbsp;B. 755) und laden Sie die Seite neu.</p>';
        exit;
    }
})();
