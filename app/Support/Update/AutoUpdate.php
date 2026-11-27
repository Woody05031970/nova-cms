<?php

namespace App\Support\Update;

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

    public static function remember(): void
    {
        @file_put_contents(self::markerPath(), (string) config('nova.version'));
    }

    /** true, wenn ein Update eingespielt wurde (oder nichts zu tun war) */
    public static function run(): bool
    {
        if (self::installedVersion() === (string) config('nova.version')) {
            return true;
        }

        $lock = @fopen(storage_path('app/nova-update.lock'), 'c');
        if ($lock && ! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            return false; // ein anderer Aufruf spielt das Update gerade ein
        }

        try {
            if (self::installedVersion() !== (string) config('nova.version')) {
                $from = self::installedVersion() ?? 'unbekannt';
                Artisan::call('migrate', ['--force' => true]);
                self::remember();
                Log::info("Nova CMS von Version {$from} auf ".config('nova.version').' aktualisiert.');
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
