<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    protected string $sandbox;

    /** Ob Nova CMS in diesem Test als installiert gilt. */
    protected bool $installed = true;

    protected function setUp(): void
    {
        $this->sandbox = sys_get_temp_dir().'/nova-test-'.bin2hex(random_bytes(6));
        mkdir($this->sandbox.'/plugins', 0755, true);

        $lock = $this->sandbox.'/installed.lock';
        if ($this->installed) {
            touch($lock);
        }

        // Die Pfade müssen gesetzt sein, bevor die Anwendung startet.
        foreach ([
            'NOVA_INSTALLED_LOCK' => $lock,
            'NOVA_PLUGINS_PATH' => $this->sandbox.'/plugins',
            'NOVA_PLUGINS_STATE' => $this->sandbox.'/plugins.json',
            'NOVA_ENV_PATH' => $this->sandbox.'/.env',
            'NOVA_SQLITE_PATH' => $this->sandbox.'/nova.sqlite',
        ] as $key => $value) {
            putenv("$key=$value");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        parent::setUp();

        // Hochgeladene Dateien landen im Sandbox-Ordner, nicht im echten public/.
        $public = $this->sandbox.'/public';
        mkdir($public);
        symlink(base_path('public/build'), $public.'/build');
        $this->app->usePublicPath($public);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        File::deleteDirectory($this->sandbox);
    }
}
