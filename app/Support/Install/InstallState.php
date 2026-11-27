<?php

namespace App\Support\Install;

class InstallState
{
    public static function isInstalled(): bool
    {
        return file_exists(config('nova.installed_lock'));
    }

    public static function markInstalled(): void
    {
        $lock = config('nova.installed_lock');
        if (! is_dir(dirname($lock))) {
            mkdir(dirname($lock), 0755, true);
        }
        file_put_contents($lock, 'Installiert am '.now()->toDateTimeString().' mit Nova CMS '.config('nova.version'));
    }
}
