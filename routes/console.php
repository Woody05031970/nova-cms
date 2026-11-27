<?php

use App\Licensing\Signer;
use App\Support\Planner\Tick;
use App\Support\Security\Scanner;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sicherheits-Check, z. B. als Cronjob beim Hoster: php artisan nova:security-scan
Artisan::command('nova:security-scan {--trust : Aktuellen Stand der Programmdateien als sicher speichern}', function () {
    if ($this->option('trust')) {
        $this->info(Scanner::storeHashes().' Dateien als sicher gespeichert.');
    }
    $result = app(Scanner::class)->run();
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

// Schlüsselpaar für den Lizenzserver erzeugen (einmalig, vor dem ersten Verkauf)
Artisan::command('nova:license-keys', function () {
    $pair = Signer::generateKeyPair();
    $this->info('Neues Schlüsselpaar für den Lizenzserver:');
    $this->newLine();
    $this->line('1) In die .env Ihres Lizenzservers (geheim halten, nie weitergeben):');
    $this->line('   NOVA_LICENSE_PRIVATE_KEY='.$pair['secret']);
    $this->newLine();
    $this->line('   NOVA_LICENSE_PUBLIC_KEY='.$pair['public']);
    $this->newLine();
    $this->line('2) In die Kaufversion, die Sie an Kunden ausliefern: Datei nova-license.pub im Hauptordner mit diesem Inhalt:');
    $this->line('   '.$pair['public']);
    $this->line('   und in config/nova.php bei license.server die Adresse Ihres Lizenzservers (https://…).');
    $this->newLine();
    $this->warn('Achtung: Ein neues Schlüsselpaar macht alle bisher ausgegebenen Bestätigungen ungültig.');
})->purpose('Erzeugt das Schlüsselpaar für den Nova-Lizenzserver');

// Geplante Seiten und Social-Media-Beiträge (läuft auch ohne Cronjob beim nächsten Seitenaufruf)
Artisan::command('nova:tick', function () {
    $r = Tick::run();
    $this->info("{$r['pages']} Seiten veröffentlicht, {$r['posts']} Beiträge gesendet, {$r['reminders']} Terminerinnerungen".($r['backup'] ? ', Sicherung erstellt.' : '.'));
})->purpose('Veröffentlicht fällige Seiten und Beiträge');
Schedule::command('nova:tick')->everyMinute();
