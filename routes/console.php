<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sicherheits-Check, z. B. als Cronjob beim Hoster: php artisan nova:security-scan
Artisan::command('nova:security-scan {--trust : Aktuellen Stand der Programmdateien als sicher speichern}', function () {
    if ($this->option('trust')) {
        $this->info(\App\Support\Security\Scanner::storeHashes().' Dateien als sicher gespeichert.');
    }
    $result = app(\App\Support\Security\Scanner::class)->run();
    foreach ($result['checks'] as $c) {
        $this->line(['good' => '<info>OK</info>     ', 'warn' => '<comment>Hinweis</comment>', 'bad' => '<error>Problem</error>'][$c['status']].'  '.$c['label']);
        foreach ($c['status'] === 'good' ? [] : $c['details'] as $d) {
            $this->line('           '.$d);
        }
    }
    $this->newLine();
    $this->info("Ergebnis: {$result['score']} von 100 Punkten");

    return collect($result['checks'])->contains('status', 'bad') ? 1 : 0;
})->purpose('Prüft Nova CMS auf Sicherheitsprobleme');
