<?php

namespace App\Support\Security;

use App\Models\SecurityEvent;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/**
 * Sicherheits-Check: prüft Einstellungen, Dateien und Konten und erklärt jedes Ergebnis
 * in einfachen Worten. Ergebnis: ['score' => 0..100, 'checks' => [...], 'at' => ISO-Zeit].
 */
class Scanner
{
    /** Muster, die in Schadcode häufig vorkommen (in Plugins und Uploads). */
    public const MALWARE_PATTERNS = [
        'eval(base64_decode' => '#eval\s*\(\s*(base64_decode|gzinflate|gzuncompress|str_rot13)\s*\(#i',
        'Befehl aus Anfrage' => '#(system|exec|shell_exec|passthru|popen|proc_open)\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)#i',
        'assert mit Anfrage' => '#assert\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)#i',
        'preg_replace /e' => '#preg_replace\s*\(\s*[\'"](.).*\1[a-z]*e[a-z]*[\'"]#i',
        'Web-Shell' => '#(c99shell|r57shell|wso\s*shell|FilesMan|b374k)#i',
    ];

    /** Ordner und Dateien des Kerns, deren Inhalt sich nur bei Updates ändern darf. */
    public const CORE_PATHS = ['app', 'bootstrap/app.php', 'config', 'routes', 'public/index.php', 'public/.htaccess'];

    public const MAX_SCAN_BYTES = 2_000_000;

    /** @return array{score: int, checks: list<array<string, mixed>>, at: string} */
    public function run(): array
    {
        $checks = [
            $this->debugMode(),
            $this->environment(),
            $this->https(),
            $this->phpVersion(),
            $this->envFile(),
            $this->twoFactor(),
            $this->firewall(),
            $this->uploadsProtected(),
            $this->scriptsInUploads(),
            $this->malware(),
            $this->integrity(),
        ];

        $weights = ['good' => 1, 'warn' => 0.5, 'bad' => 0];
        $score = (int) round(collect($checks)->avg(fn ($c) => $weights[$c['status']]) * 100);

        $result = ['score' => $score, 'checks' => $checks, 'at' => now()->toIso8601String()];
        Setting::put('security_last_scan', json_encode($result));

        $problems = collect($checks)->where('status', 'bad')->count();
        SecurityEvent::record('scan', "Sicherheits-Check: {$score} von 100".($problems ? ", {$problems} Problem(e)" : ''));

        return $result;
    }

    /** Letztes gespeichertes Ergebnis, ohne neu zu prüfen. */
    public static function last(): ?array
    {
        $raw = Setting::get('security_last_scan');

        return $raw ? json_decode($raw, true) : null;
    }

    private function check(string $key, string $label, string $status, string $tip = '', array $details = []): array
    {
        return compact('key', 'label', 'status', 'tip', 'details');
    }

    private function debugMode(): array
    {
        return config('app.debug')
            ? $this->check('debug', 'Fehlerdetails sind öffentlich sichtbar', 'bad',
                'Setzen Sie in der Datei .env den Wert APP_DEBUG=false. Sonst sehen Angreifer bei Fehlern Passwörter und Pfade.')
            : $this->check('debug', 'Fehlerdetails sind für Besucher verborgen', 'good');
    }

    private function environment(): array
    {
        return app()->environment('production')
            ? $this->check('env', 'Website läuft im Live-Betrieb', 'good')
            : $this->check('env', 'Website läuft im Test-Modus ('.app()->environment().')', 'warn',
                'Für die echte Website in der Datei .env APP_ENV=production eintragen.');
    }

    private function https(): array
    {
        $url = (string) config('app.url');

        return str_starts_with($url, 'https://') || request()->isSecure()
            ? $this->check('https', 'Verschlüsselte Verbindung (HTTPS) aktiv', 'good')
            : $this->check('https', 'Keine verschlüsselte Verbindung (HTTPS)', 'bad',
                'Aktivieren Sie im Kundenbereich Ihres Hosters das kostenlose SSL-Zertifikat. Danach APP_URL in der .env auf https:// ändern.');
    }

    private function phpVersion(): array
    {
        return version_compare(PHP_VERSION, '8.3.0', '>=')
            ? $this->check('php', 'PHP-Version '.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.' erhält Sicherheits-Updates', 'good')
            : $this->check('php', 'Veraltete PHP-Version '.PHP_VERSION, 'bad', 'Stellen Sie im Kundenbereich Ihres Hosters PHP 8.3 oder neuer ein.');
    }

    private function envFile(): array
    {
        $env = config('nova.env_path');
        if (! is_file($env)) {
            return $this->check('envfile', 'Konfigurationsdatei .env nicht gefunden', 'warn', 'Normalerweise legt der Installer sie an.');
        }

        $problems = [];
        if (DIRECTORY_SEPARATOR === '/' && (fileperms($env) & 0o004)) {
            $problems[] = 'Die Datei ist für alle Benutzer des Servers lesbar (Rechte '.substr(sprintf('%o', fileperms($env)), -3).').';
        }

        // Liegt die .env im öffentlichen Ordner, muss die .htaccess im Hauptordner sie schützen.
        $publicRoot = realpath(public_path()) ?: '';
        if (str_starts_with(realpath($env) ?: '', $publicRoot.DIRECTORY_SEPARATOR)) {
            $problems[] = 'Die Datei liegt im öffentlichen Ordner.';
        }
        $rootHtaccess = base_path('.htaccess');
        if (is_file($rootHtaccess) && ! str_contains((string) file_get_contents($rootHtaccess), '.env')) {
            $problems[] = 'Die .htaccess im Hauptordner schützt die .env nicht mehr.';
        }

        return $problems
            ? $this->check('envfile', 'Zugangsdaten-Datei (.env) nicht ausreichend geschützt', 'warn',
                'Setzen Sie die Dateirechte der .env auf 640 oder 600 (im FTP-Programm: Rechtsklick, Dateiberechtigungen).', $problems)
            : $this->check('envfile', 'Zugangsdaten-Datei (.env) ist geschützt', 'good');
    }

    private function twoFactor(): array
    {
        $without = User::where('role', 'admin')->whereNull('two_factor_confirmed_at')->pluck('email')->all();

        return $without
            ? $this->check('2fa', count($without) === 1 ? '1 Administrator ohne Zwei-Faktor-Anmeldung' : count($without).' Administratoren ohne Zwei-Faktor-Anmeldung', 'bad',
                'Mit Zwei-Faktor-Anmeldung reicht ein gestohlenes Passwort nicht mehr. Einrichten unter "Mein Konto".', $without)
            : $this->check('2fa', 'Alle Administratoren nutzen die Zwei-Faktor-Anmeldung', 'good');
    }

    private function firewall(): array
    {
        return Setting::get('security_firewall') === '0'
            ? $this->check('firewall', 'Angriffsschutz ist ausgeschaltet', 'bad', 'Schalten Sie den Angriffsschutz unten wieder ein.')
            : $this->check('firewall', 'Angriffsschutz ist aktiv', 'good');
    }

    private function uploadsProtected(): array
    {
        $file = public_path('uploads/.htaccess');

        return is_file($file) && str_contains((string) file_get_contents($file), 'php')
            ? $this->check('uploads_htaccess', 'Im Upload-Ordner können keine Programme ausgeführt werden', 'good')
            : $this->check('uploads_htaccess', 'Upload-Ordner ist nicht gegen Programme geschützt', 'warn',
                'Klicken Sie auf "Jetzt beheben". Nova CMS legt dann eine Schutzdatei an.');
    }

    private function scriptsInUploads(): array
    {
        $root = public_path('uploads');
        if (! is_dir($root)) {
            return $this->check('uploads_scripts', 'Keine Programmdateien im Upload-Ordner', 'good');
        }

        $found = [];
        foreach (Finder::create()->files()->in($root)->ignoreDotFiles(false)->name('/\.(php\d?|phtml|phar|pht|cgi|pl|py|sh|asp|aspx|jsp)$/i') as $file) {
            $found[] = 'uploads/'.str_replace('\\', '/', $file->getRelativePathname());
        }

        return $found
            ? $this->check('uploads_scripts', count($found).' verdächtige Programmdatei(en) im Upload-Ordner', 'bad',
                'Dort gehören nur Bilder und Dokumente hin. Löschen Sie diese Dateien per FTP, wenn Sie sie nicht selbst hochgeladen haben.', array_slice($found, 0, 20))
            : $this->check('uploads_scripts', 'Keine Programmdateien im Upload-Ordner', 'good');
    }

    private function malware(): array
    {
        $found = [];
        foreach ([config('nova.plugins_path') => 'plugins', public_path('uploads') => 'uploads'] as $dir => $label) {
            if (! is_dir($dir)) {
                continue;
            }
            $files = Finder::create()->files()->in($dir)->ignoreDotFiles(false)
                ->name('/\.(php\d?|phtml|phar|inc|js|svg|html?)$/i')->size('< '.self::MAX_SCAN_BYTES);
            foreach ($files as $file) {
                $content = (string) @file_get_contents($file->getPathname());
                foreach (self::MALWARE_PATTERNS as $name => $regex) {
                    if (preg_match($regex, $content)) {
                        $found[] = $label.'/'.str_replace('\\', '/', $file->getRelativePathname()).' ('.$name.')';
                        break;
                    }
                }
            }
        }

        return $found
            ? $this->check('malware', 'Verdacht auf Schadcode in '.count($found).' Datei(en)', 'bad',
                'Deaktivieren und löschen Sie das betroffene Plugin bzw. die Datei. Im Zweifel fragen Sie Ihren Hoster.', array_slice($found, 0, 20))
            : $this->check('malware', 'Kein Schadcode in Plugins und Uploads gefunden', 'good');
    }

    private function integrity(): array
    {
        $stored = self::storedHashes();
        if ($stored === null) {
            return $this->check('integrity', 'Fingerabdruck der Programmdateien fehlt', 'warn',
                'Klicken Sie auf "Aktuellen Stand als sicher speichern". Danach erkennt Nova CMS jede Veränderung.');
        }

        $current = self::currentHashes();
        $changed = array_keys(array_diff_assoc(array_intersect_key($current, $stored), $stored));
        $added = array_keys(array_diff_key($current, $stored));
        $removed = array_keys(array_diff_key($stored, $current));

        $details = [
            ...array_map(fn ($f) => 'verändert: '.$f, $changed),
            ...array_map(fn ($f) => 'neu: '.$f, $added),
            ...array_map(fn ($f) => 'fehlt: '.$f, $removed),
        ];

        return $details
            ? $this->check('integrity', count($details).' Programmdatei(en) wurden verändert', 'bad',
                'Haben Sie gerade ein Update eingespielt? Dann speichern Sie den Stand als sicher. Sonst wurde die Website vielleicht manipuliert.',
                array_slice($details, 0, 30))
            : $this->check('integrity', 'Programmdateien sind unverändert', 'good');
    }

    public static function hashesPath(): string
    {
        return config('nova.core_hashes', storage_path('app/nova-core-hashes.json'));
    }

    /** @return array<string, string>|null */
    public static function storedHashes(): ?array
    {
        $path = self::hashesPath();

        return is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: null) : null;
    }

    /** @return array<string, string> relativer Pfad => sha256 */
    public static function currentHashes(): array
    {
        $hashes = [];
        foreach (self::CORE_PATHS as $rel) {
            $full = base_path($rel);
            if (is_file($full)) {
                $hashes[$rel] = hash_file('sha256', $full);
            } elseif (is_dir($full)) {
                foreach (Finder::create()->files()->in($full)->ignoreDotFiles(false)->sortByName() as $file) {
                    $hashes[$rel.'/'.str_replace('\\', '/', $file->getRelativePathname())] = hash_file('sha256', $file->getPathname());
                }
            }
        }
        ksort($hashes);

        return $hashes;
    }

    public static function storeHashes(): int
    {
        $hashes = self::currentHashes();
        File::ensureDirectoryExists(dirname(self::hashesPath()));
        file_put_contents(self::hashesPath(), json_encode($hashes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return count($hashes);
    }

    /** Legt im Upload-Ordner eine .htaccess an, die das Ausführen von Programmen verbietet. */
    public static function protectUploads(): void
    {
        $dir = public_path('uploads');
        File::ensureDirectoryExists($dir);
        $file = $dir.'/.htaccess';
        if (is_file($file) && str_contains((string) file_get_contents($file), 'php')) {
            return;
        }

        file_put_contents($file, <<<'HTACCESS'
# Von Nova CMS angelegt: In diesem Ordner dürfen keine Programme laufen.
<FilesMatch "\.(php\d?|phtml|phar|pht|cgi|pl|py|sh)$">
    Require all denied
</FilesMatch>
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
HTACCESS);
    }
}
