<?php

namespace App\Support\Update;

use App\Support\Turbo\PageCache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Spielt nach dem Hochladen einer neuen Version die Datenbank-Änderungen
 * automatisch ein. Der Kunde muss dafür nichts tun: Beim ersten Aufruf nach
 * dem Update wird "migrate" einmal ausgeführt und die Version gemerkt.
 */
class AutoUpdate
{
    public static function markerPath(): string
    {
        return storage_path('app/nova-version');
    }

    public static function installedVersion(): ?string
    {
        $path = self::markerPath();

        return is_file($path) ? trim((string) file_get_contents($path)) : null;
    }

    /**
     * Version der hochgeladenen Dateien. Direkt aus config/nova.php gelesen, weil ein alter
     * Einstellungs-Zwischenspeicher (config:cache) sonst noch die alte Nummer meldet und das Update nie läuft.
     */
    public static function codeVersion(): string
    {
        $file = base_path('config/nova.php');
        $cfg = is_file($file) ? @include $file : null;

        return (string) (is_array($cfg) && isset($cfg['version']) ? $cfg['version'] : config('nova.version'));
    }

    public static function remember(): void
    {
        @file_put_contents(self::markerPath(), self::codeVersion());
    }

    /**
     * Nach dem Hochladen einer neuen Version dürfen keine alten Zwischenstände übrig bleiben:
     * zwischengespeicherte Adressen (Routen), Einstellungen, übersetzte Vorlagen und der
     * PHP-Zwischenspeicher (OPcache) mancher Hoster würden sonst noch die alte Version zeigen.
     */
    public static function clearCaches(): void
    {
        foreach (['route:clear', 'config:clear', 'view:clear', 'event:clear'] as $command) {
            try {
                Artisan::call($command);
            } catch (Throwable $e) {
                report($e);
            }
        }
        try {
            // Zwischengespeicherte Website-Seiten verweisen sonst noch auf alte Skripte und Stile
            PageCache::flush();
        } catch (Throwable $e) {
            report($e);
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    /** true, wenn ein Update eingespielt wurde (oder nichts zu tun war) */
    public static function run(): bool
    {
        if (self::installedVersion() === self::codeVersion()) {
            return true;
        }

        $lock = @fopen(storage_path('app/nova-update.lock'), 'c');
        if ($lock && ! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            return false; // ein anderer Aufruf spielt das Update gerade ein
        }

        try {
            if (self::installedVersion() !== self::codeVersion()) {
                $from = self::installedVersion() ?? 'unbekannt';
                Artisan::call('migrate', ['--force' => true]);
                self::clearCaches();
                self::remember();
                Log::info("Nova CMS von Version {$from} auf ".self::codeVersion().' aktualisiert.');
            }

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        } finally {
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }
}
