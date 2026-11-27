<?php

namespace Tests\Feature;

use App\Models\Plugin;
use App\Models\User;
use App\Plugins\Hooks;
use App\Plugins\PluginException;
use App\Plugins\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ZipArchive;

class PluginTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function exampleZip(): UploadedFile
    {
        $zip = $this->sandbox.'/oeffnungszeiten.zip';
        $this->zipDirectory(base_path('plugin-examples/oeffnungszeiten'), $zip, 'oeffnungszeiten/');

        return new UploadedFile($zip, 'oeffnungszeiten.zip', 'application/zip', null, true);
    }

    private function zipDirectory(string $dir, string $target, string $prefix): void
    {
        $zip = new ZipArchive;
        $zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $zip->addFile($file->getPathname(), $prefix.substr($file->getPathname(), strlen($dir) + 1));
        }
        $zip->close();
    }

    /** @param array<string, string> $files */
    private function makeZip(array $files): UploadedFile
    {
        $path = $this->sandbox.'/test-'.bin2hex(random_bytes(4)).'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return new UploadedFile($path, 'plugin.zip', 'application/zip', null, true);
    }

    public function test_kompletter_lebenszyklus_eines_plugins(): void
    {
        $admin = $this->admin();

        // Hochladen
        $this->actingAs($admin)->post('/admin/plugins', ['plugin' => $this->exampleZip()])
            ->assertSessionHasNoErrors();
        $this->assertDirectoryExists(config('nova.plugins_path').'/oeffnungszeiten');
        $this->assertFalse(Plugin::where('slug', 'oeffnungszeiten')->first()->enabled);

        $this->actingAs($admin)->get('/admin/plugins')->assertSee('Öffnungszeiten')->assertSee('Aktivieren');

        // Aktivieren: Migration läuft, Status wird gemerkt
        $this->actingAs($admin)->post('/admin/plugins/oeffnungszeiten/aktivieren')->assertSessionHasNoErrors();
        $this->assertTrue(Plugin::where('slug', 'oeffnungszeiten')->first()->enabled);
        $this->assertTrue(Schema::hasTable('oeffnungszeiten'));
        $state = json_decode(file_get_contents(config('nova.plugins_state')), true);
        $this->assertArrayHasKey('oeffnungszeiten', $state['enabled']);

        // Beim nächsten Start wird das Plugin geladen (hier simuliert)
        $manager = new PluginManager($this->app);
        $manager->registerEnabled();
        $manager->bootEnabled();
        $this->app['router']->getRoutes()->refreshNameLookups();

        $this->actingAs($admin)->get('/admin')->assertSee('Öffnungszeiten eintragen');
        $this->actingAs($admin)->get('/admin/oeffnungszeiten')->assertOk()->assertSee('Montag');
        $this->actingAs($admin)->put('/admin/oeffnungszeiten', ['tage' => [0 => ['offen' => 1, 'von' => '08:00', 'bis' => '18:00']]])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->get('/admin')->assertDontSee('Öffnungszeiten eintragen')->assertSee('08:00 bis 18:00 Uhr');

        // Ein aktives Plugin kann nicht gelöscht werden
        $this->actingAs($admin)->delete('/admin/plugins/oeffnungszeiten')->assertSessionHasErrors('plugin');

        // Deaktivieren und entfernen
        $this->actingAs($admin)->post('/admin/plugins/oeffnungszeiten/deaktivieren');
        $this->assertFalse(Plugin::where('slug', 'oeffnungszeiten')->first()->enabled);
        $this->actingAs($admin)->delete('/admin/plugins/oeffnungszeiten')->assertSessionHasNoErrors();
        $this->assertDirectoryDoesNotExist(config('nova.plugins_path').'/oeffnungszeiten');
        $this->assertNull(Plugin::where('slug', 'oeffnungszeiten')->first());
    }

    public function test_doppelte_installation_wird_abgelehnt(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/plugins', ['plugin' => $this->exampleZip()]);
        $this->actingAs($admin)->post('/admin/plugins', ['plugin' => $this->exampleZip()])
            ->assertSessionHasErrors(['plugin' => 'Das Plugin "Öffnungszeiten" ist bereits installiert. Bitte entfernen Sie die alte Version zuerst.']);
    }

    public function test_pfad_tricks_im_archiv_werden_blockiert(): void
    {
        $manifest = json_encode(['slug' => 'boese', 'name' => 'Böse', 'version' => '1', 'namespace' => 'Boese', 'provider' => 'Boese\\P']);

        $this->actingAs($this->admin())->post('/admin/plugins', ['plugin' => $this->makeZip([
            'plugin.json' => $manifest,
            '../../public/hack.php' => '<?php echo 1;',
        ])])->assertSessionHasErrors(['plugin' => 'Das Archiv enthält unzulässige Pfade.']);

        $this->assertFileDoesNotExist(base_path('public/hack.php'));
        $this->assertDirectoryDoesNotExist(config('nova.plugins_path').'/boese');
    }

    public function test_fehlende_oder_ungueltige_plugin_json(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/plugins', ['plugin' => $this->makeZip(['readme.txt' => 'x'])])
            ->assertSessionHasErrors(['plugin' => 'Im Archiv wurde keine plugin.json gefunden.']);

        $this->actingAs($admin)->post('/admin/plugins', ['plugin' => $this->makeZip([
            'plugin.json' => json_encode(['slug' => '../x', 'name' => 'X', 'version' => '1', 'namespace' => 'X', 'provider' => 'X\\P']),
        ])])->assertSessionHasErrors('plugin');

        $this->actingAs($admin)->post('/admin/plugins', ['plugin' => $this->makeZip([
            'plugin.json' => json_encode(['slug' => 'kern', 'name' => 'X', 'version' => '1', 'namespace' => 'App\\Evil', 'provider' => 'App\\Evil\\P']),
        ])])->assertSessionHasErrors(['plugin' => 'Der Namensraum (namespace) des Plugins ist ungültig.']);
    }

    public function test_zu_neue_core_version_wird_abgelehnt(): void
    {
        $this->actingAs($this->admin())->post('/admin/plugins', ['plugin' => $this->makeZip([
            'plugin.json' => json_encode(['slug' => 'zukunft', 'name' => 'Zukunft', 'version' => '1', 'requires' => '99.0.0', 'namespace' => 'Zukunft', 'provider' => 'Zukunft\\P']),
        ])])->assertSessionHasErrors(['plugin' => 'Dieses Plugin benötigt Nova CMS 99.0.0 oder neuer.']);
    }

    public function test_fehlerhaftes_plugin_legt_die_website_nicht_lahm(): void
    {
        $dir = config('nova.plugins_path').'/kaputt';
        mkdir($dir.'/src', 0755, true);
        file_put_contents($dir.'/plugin.json', json_encode(['slug' => 'kaputt', 'name' => 'Kaputt', 'version' => '1', 'namespace' => 'Kaputt', 'provider' => 'Kaputt\\Provider']));
        file_put_contents($dir.'/src/Provider.php', '<?php namespace Kaputt; class Provider extends \Illuminate\Support\ServiceProvider { public function boot(): void { throw new \RuntimeException("Absturz"); } }');

        $manager = app(PluginManager::class);
        $manager->sync();
        $manager->activate('kaputt');

        $fresh = new PluginManager($this->app);
        $fresh->registerEnabled();
        $fresh->bootEnabled(); // darf keine Ausnahme werfen

        $state = json_decode(file_get_contents(config('nova.plugins_state')), true);
        $this->assertArrayNotHasKey('kaputt', $state['enabled']);
        $this->assertStringContainsString('Absturz', $state['errors']['kaputt']);

        $this->actingAs($this->admin())->get('/admin/plugins')->assertSee('Automatisch deaktiviert: Absturz');
        $this->assertFalse(Plugin::where('slug', 'kaputt')->first()->enabled);
    }

    public function test_hooks_reihenfolge_nach_prioritaet(): void
    {
        $hooks = new Hooks;
        $hooks->addFilter('x', fn ($v) => $v.'b', 20);
        $hooks->addFilter('x', fn ($v) => $v.'a', 10);
        $this->assertSame('_ab', $hooks->applyFilters('x', '_'));
        $this->assertSame('_', $hooks->applyFilters('unbekannt', '_'));
    }

    public function test_aktivieren_mit_fehlender_klasse_meldet_fehler(): void
    {
        $dir = config('nova.plugins_path').'/leer';
        mkdir($dir, 0755, true);
        file_put_contents($dir.'/plugin.json', json_encode(['slug' => 'leer', 'name' => 'Leer', 'version' => '1', 'namespace' => 'Leer', 'provider' => 'Leer\\Fehlt']));
        app(PluginManager::class)->sync();

        $this->expectException(PluginException::class);
        app(PluginManager::class)->activate('leer');
    }
}
