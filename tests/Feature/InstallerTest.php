<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    protected bool $installed = false;

    public function test_vor_der_installation_fuehrt_jede_seite_zum_installer(): void
    {
        $this->get('/')->assertRedirect(route('install.welcome'));
        $this->get('/admin')->assertRedirect(route('install.welcome'));
    }

    public function test_systempruefung_wird_angezeigt(): void
    {
        $this->get('/install')->assertOk()->assertSee('Willkommen bei Nova CMS')->assertSee('PHP-Version');
    }

    public function test_kompletter_installationsablauf_mit_sqlite(): void
    {
        $this->post('/install/datenbank', ['driver' => 'sqlite'])->assertRedirect(route('install.site'));

        $this->post('/install/website', [
            'site_name' => 'Bäckerei Müller',
            'name' => 'Erika Müller',
            'email' => 'erika@example.com',
            'password' => 'geheim12345',
            'password_confirmation' => 'geheim12345',
        ])->assertSessionHasNoErrors()->assertRedirect(route('install.done'));

        $this->assertFileExists(config('nova.installed_lock'));
        $this->assertFileExists(config('nova.sqlite_path'));
        $this->assertTrue(User::where('email', 'erika@example.com')->first()->isAdmin());
        $this->assertSame('Bäckerei Müller', Setting::get('site_name'));

        $env = file_get_contents(config('nova.env_path'));
        $this->assertStringContainsString('APP_NAME="Bäckerei Müller"', $env);
        $this->assertStringContainsString('DB_CONNECTION=sqlite', $env);
        $this->assertStringContainsString('APP_DEBUG=false', $env);

        // Danach ist der Installer gesperrt
        $this->get('/install')->assertNotFound();
    }

    public function test_schwaches_passwort_wird_abgelehnt(): void
    {
        $this->post('/install/datenbank', ['driver' => 'sqlite']);
        $this->post('/install/website', [
            'site_name' => 'Test', 'name' => 'A', 'email' => 'a@example.com',
            'password' => 'kurz', 'password_confirmation' => 'kurz',
        ])->assertSessionHasErrors('password');

        $this->assertFileDoesNotExist(config('nova.installed_lock'));
    }

    public function test_falsche_mysql_daten_ergeben_verstaendliche_meldung(): void
    {
        $this->post('/install/datenbank', [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'y', 'password' => 'z',
        ])->assertSessionHasErrors(['database']);
    }
}
