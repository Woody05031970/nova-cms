<?php

namespace Tests\Feature;

use App\Licensing\License;
use App\Licensing\LicenseKey;
use App\Licensing\Marketplace;
use App\Licensing\Signer;
use App\Licensing\Transport;
use App\Mail\LicenseIssued;
use App\Models\IssuedLicense;
use App\Models\MarketModule;
use App\Models\Order;
use App\Models\Plugin;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class LicenseTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $marketFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->marketFiles as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** Diese Installation ist zugleich Lizenzserver (LocalTransport) und prüft Lizenzen. */
    private function withServer(): void
    {
        $pair = Signer::generateKeyPair();
        config(['nova.license.public_key' => $pair['public'], 'nova.license.private_key' => $pair['secret']]);
        $this->fresh();
    }

    private function fresh(): License
    {
        $this->app->forgetScopedInstances();
        $this->app->forgetInstance(License::class);

        return $this->app->make(License::class);
    }

    private function issue(array $attrs = []): IssuedLicense
    {
        return IssuedLicense::issue(['customer_name' => 'Bäckerei Müller', 'core' => true, 'modules' => [], 'max_domains' => 1, ...$attrs]);
    }

    /** @param array<string, string> $files */
    private function zip(array $files): string
    {
        $path = $this->sandbox.'/z-'.bin2hex(random_bytes(4)).'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $path;
    }

    private function exampleZip(): string
    {
        $dir = base_path('plugin-examples/oeffnungszeiten');
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
            $files['oeffnungszeiten/'.substr($f->getPathname(), strlen($dir) + 1)] = file_get_contents($f->getPathname());
        }

        return $this->zip($files);
    }

    private function premiumZip(string $version = '1.0.0'): string
    {
        return $this->zip([
            'plugin.json' => json_encode([
                'slug' => 'premium-galerie', 'name' => 'Premium-Galerie', 'version' => $version, 'requires' => '0.1.0',
                'namespace' => 'NovaPlugins\\PremiumGalerie', 'provider' => 'NovaPlugins\\PremiumGalerie\\GalerieServiceProvider',
                'license' => 'paid',
            ]),
            'src/GalerieServiceProvider.php' => "<?php\nnamespace NovaPlugins\\PremiumGalerie;\nclass GalerieServiceProvider extends \\Illuminate\\Support\\ServiceProvider {}\n",
        ]);
    }

    private function publish(string $slug, string $zip, bool $paid = false, string $version = '1.0.0'): MarketModule
    {
        File::ensureDirectoryExists(storage_path('app/marketplace'));
        $file = 'test-'.$slug.'-'.bin2hex(random_bytes(4)).'.zip';
        copy($zip, storage_path('app/marketplace/'.$file));
        $this->marketFiles[] = storage_path('app/marketplace/'.$file);

        return MarketModule::updateOrCreate(['slug' => $slug], [
            'name' => ucfirst($slug), 'version' => $version, 'is_paid' => $paid, 'is_published' => true,
            'file' => $file, 'sha256' => hash_file('sha256', $zip),
        ]);
    }

    public function test_lizenzschluessel_haben_eine_pruefsumme(): void
    {
        $key = LicenseKey::generate();
        $this->assertMatchesRegularExpression('/^NOVA(-[A-Z2-9]{4}){4}$/', $key);
        $this->assertTrue(LicenseKey::isValid($key));
        $this->assertSame($key, LicenseKey::normalize(strtolower(str_replace('-', ' ', $key))));

        $typo = substr($key, 0, 5).($key[5] === 'A' ? 'B' : 'A').substr($key, 6);
        $this->assertFalse(LicenseKey::isValid($typo));
        $this->assertFalse(LicenseKey::isValid('NOVA-1234'));
    }

    public function test_signaturen_erkennen_veraenderungen(): void
    {
        $pair = Signer::generateKeyPair();
        config(['nova.license.public_key' => $pair['public'], 'nova.license.private_key' => $pair['secret']]);

        $env = Signer::envelope(['key' => 'X', 'modules' => ['shop']]);
        $this->assertSame(['key' => 'X', 'modules' => ['shop']], Signer::open($env));

        $env['payload'] = str_replace('shop', 'alles', $env['payload']);
        $this->assertNull(Signer::open($env));

        // Fremder Schlüssel
        $other = Signer::generateKeyPair();
        $forged = ['payload' => '{"key":"X"}', 'signature' => Signer::sign('{"key":"X"}', $other['secret'])];
        $this->assertNull(Signer::open($forged));
        $this->assertNull(Signer::open('kaputt'));
    }

    public function test_entwicklerversion_ohne_schluessel_prueft_nichts(): void
    {
        $license = $this->fresh();
        $this->assertSame(License::STATUS_DEV, $license->status());
        $this->assertTrue($license->allows('irgendwas'));
        $this->actingAs($this->admin())->get('/admin')->assertOk()->assertDontSee('data-license-banner', false);
    }

    public function test_testzeitraum_und_sperre_der_verwaltung(): void
    {
        $this->withServer();
        Setting::put('installed_at', now()->subDays(20)->toIso8601String());
        $admin = $this->admin();

        $this->assertSame(License::STATUS_TRIAL, $this->fresh()->status());
        $this->assertSame(10, $this->fresh()->trialDaysLeft());
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('data-license-banner', false)->assertSee('10 Tage');

        Setting::put('installed_at', now()->subDays(31)->toIso8601String());
        $this->assertSame(License::STATUS_TRIAL_OVER, $this->fresh()->status());
        $this->actingAs($admin)->get('/admin')->assertRedirect(route('admin.license'));
        $this->actingAs($admin)->get('/admin/seiten')->assertRedirect(route('admin.license'));
        $this->actingAs($admin)->get('/admin/lizenz')->assertOk()->assertSee('Testzeitraum abgelaufen');
        $this->actingAs($admin)->get('/admin/konto')->assertOk();

        // Die Website selbst bleibt immer online
        $this->get('/')->assertOk();
    }

    public function test_lizenz_aktivieren_schaltet_module_frei(): void
    {
        $this->withServer();
        Setting::put('installed_at', now()->subDays(40)->toIso8601String());
        $issued = $this->issue(['modules' => ['premium-galerie'], 'updates_until' => now()->addYear()->toDateString()]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/lizenz', ['key' => 'NOVA-AAAA-AAAA-AAAA-AAAA'])->assertSessionHasErrors('key');
        $this->actingAs($admin)->post('/admin/lizenz', ['key' => strtolower($issued->key)])->assertSessionHasNoErrors();

        $license = $this->fresh();
        $this->assertSame(License::STATUS_ACTIVE, $license->status());
        $this->assertTrue($license->allows('premium-galerie'));
        $this->assertFalse($license->allows('anderes-modul'));
        $this->assertSame($issued->updates_until->toDateString(), $license->updatesUntil()->toDateString());
        $this->assertSame(1, $issued->activations()->count());

        $this->actingAs($admin)->get('/admin')->assertOk()->assertDontSee('data-license-banner', false);
        $this->actingAs($admin)->get('/admin/lizenz')->assertOk()->assertSee('Lizenziert')->assertSee('Bäckerei Müller')
            ->assertDontSee($issued->key);
    }

    public function test_anzahl_der_websites_ist_begrenzt(): void
    {
        $this->withServer();
        $issued = $this->issue(['max_domains' => 1]);
        config(['app.url' => 'https://kunde.de']);
        $this->fresh()->activate($issued->key);

        // Zweite Installation auf einer anderen Domain
        Setting::put('install_id', str_repeat('b', 32));
        config(['app.url' => 'https://www.zweite-seite.de']);
        try {
            $this->fresh()->activate($issued->key);
            $this->fail('Zweite Website hätte abgelehnt werden müssen.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('bereits auf 1 Website', $e->getMessage());
        }

        // Testumgebungen zählen nicht
        config(['app.url' => 'http://localhost']);
        $this->fresh()->activate($issued->key);
        Setting::put('install_id', str_repeat('c', 32));
        config(['app.url' => 'https://staging.kunde.de']);
        $this->fresh()->activate($issued->key);
        $this->assertSame(3, $issued->activations()->count());
    }

    public function test_gesperrte_lizenz_wird_ungueltig(): void
    {
        $this->withServer();
        $issued = $this->issue();
        $this->fresh()->activate($issued->key);

        $issued->update(['status' => 'revoked']);
        $this->fresh()->refresh(force: true);

        $license = $this->fresh();
        $this->assertSame(License::STATUS_INVALID, $license->status());
        $this->actingAs($this->admin())->get('/admin')->assertRedirect(route('admin.license'));
        $this->get('/')->assertOk();
    }

    public function test_manipulierte_lizenz_wird_erkannt(): void
    {
        $this->withServer();
        $this->fresh()->activate($this->issue()->key);

        $payload = json_decode(Setting::get('license_payload'), true);
        $payload['modules'] = ['alles'];
        Setting::put('license_payload', json_encode($payload));

        $this->assertSame(License::STATUS_INVALID, $this->fresh()->status());
        $this->assertFalse($this->fresh()->allows('alles'));

        // Kopierte Lizenzdaten auf einer anderen Installation helfen nicht
        $this->fresh()->activate($this->issue()->key);
        Setting::put('install_id', str_repeat('d', 32));
        $this->assertSame(License::STATUS_INVALID, $this->fresh()->status());
    }

    public function test_ohne_verbindung_gilt_die_lizenz_noch_eine_weile(): void
    {
        $this->withServer();
        $this->fresh()->activate($this->issue()->key);

        // Lizenzserver nicht erreichbar
        $this->app->bind(Transport::class, fn () => new class implements Transport
        {
            public function post(string $path, array $data): array
            {
                throw new RuntimeException('Keine Verbindung');
            }

            public function download(string $path, array $data, string $target): void
            {
                throw new RuntimeException('Keine Verbindung');
            }
        });

        $this->travel(10)->days();
        $this->fresh()->refresh();
        $this->assertSame('Keine Verbindung', Setting::get('license_last_error'));
        $this->assertSame(License::STATUS_ACTIVE, $this->fresh()->status());

        // 7 Tage Gültigkeit + 14 Tage Kulanz
        $this->travel(12)->days();
        $this->assertSame(License::STATUS_INVALID, $this->fresh()->status());
    }

    public function test_lizenz_entfernen_gibt_den_platz_frei(): void
    {
        $this->withServer();
        $issued = $this->issue();
        $this->fresh()->activate($issued->key);
        $this->assertSame(1, $issued->activations()->count());

        $this->actingAs($this->admin())->delete('/admin/lizenz')->assertRedirect(route('admin.license'));
        $this->assertSame(0, $issued->activations()->count());
        $this->assertNull(Setting::get('license_key'));
    }

    public function test_marktplatz_installiert_kostenlose_module(): void
    {
        $this->withServer();
        $this->publish('oeffnungszeiten', $this->exampleZip());
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/plugins/marktplatz')->assertOk()->assertSee('Oeffnungszeiten')->assertSee('kostenlos');
        $this->actingAs($admin)->post('/admin/plugins/marktplatz/oeffnungszeiten')->assertRedirect(route('admin.plugins'))->assertSessionHasNoErrors();
        $this->assertDirectoryExists(config('nova.plugins_path').'/oeffnungszeiten');
        $this->assertSame('1.0.0', Plugin::where('slug', 'oeffnungszeiten')->value('version'));

        // Aktualisierung über den Marktplatz ersetzt die alte Version
        $zip = $this->exampleZip();
        $z = new ZipArchive;
        $z->open($zip);
        $json = json_decode($z->getFromName('oeffnungszeiten/plugin.json'), true);
        $json['version'] = '1.1.0';
        $z->addFromString('oeffnungszeiten/plugin.json', json_encode($json));
        $z->close();
        $this->publish('oeffnungszeiten', $zip, version: '1.1.0');

        $this->actingAs($admin)->get('/admin/plugins/marktplatz?neu=1')->assertOk()->assertSee('Aktualisieren');
        $this->actingAs($admin)->post('/admin/plugins/marktplatz/oeffnungszeiten')->assertSessionHasNoErrors();
        $this->assertSame('1.1.0', Plugin::where('slug', 'oeffnungszeiten')->value('version'));
    }

    public function test_veraenderte_dateien_werden_nicht_installiert(): void
    {
        $this->withServer();
        $module = $this->publish('oeffnungszeiten', $this->exampleZip());
        $module->update(['sha256' => str_repeat('0', 64)]);

        $this->expectExceptionMessage('beschädigt oder wurde verändert');
        $this->app->make(Marketplace::class)->install('oeffnungszeiten');
    }

    public function test_kostenpflichtige_module_brauchen_eine_lizenz(): void
    {
        $this->withServer();
        $this->publish('premium-galerie', $this->premiumZip(), paid: true);
        $admin = $this->admin();
        $this->fresh()->activate($this->issue(['modules' => []])->key);

        $this->actingAs($admin)->get('/admin/plugins/marktplatz')->assertOk()->assertSee('Premium-galerie');
        $this->actingAs($admin)->post('/admin/plugins/marktplatz/premium-galerie')->assertSessionHasErrors('market');
        $this->assertDirectoryDoesNotExist(config('nova.plugins_path').'/premium-galerie');

        // Auch von Hand hochgeladen lässt es sich nicht aktivieren
        $this->actingAs($admin)->post('/admin/plugins', ['plugin' => new UploadedFile($this->premiumZip(), 'p.zip', 'application/zip', null, true)])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/plugins/premium-galerie/aktivieren')->assertSessionHasErrors();
        $this->assertFalse((bool) Plugin::where('slug', 'premium-galerie')->value('enabled'));

        // Mit passender Lizenz geht beides
        $this->fresh()->remove();
        $this->fresh()->activate($this->issue(['modules' => ['premium-galerie']])->key);
        $this->actingAs($admin)->post('/admin/plugins/marktplatz/premium-galerie')->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/admin/plugins/premium-galerie/aktivieren')->assertSessionHasNoErrors();
        $this->assertTrue((bool) Plugin::where('slug', 'premium-galerie')->value('enabled'));
    }

    public function test_kauf_im_shop_erzeugt_eine_lizenz(): void
    {
        $this->withServer();
        Mail::fake();
        Setting::putMany(['site_name' => 'Nova CMS', 'contact_email' => 'verkauf@example.com', 'shop_enabled' => '1', 'shop_iban' => 'DE89 3704 0044 0532 0130 00']);
        $product = Product::create([
            'name' => 'Nova CMS Business', 'slug' => 'business', 'price_cents' => 19900, 'tax_rate' => 19, 'is_active' => true,
            'is_digital' => true,
            'license_spec' => ['core' => true, 'modules' => ['premium-galerie'], 'max_domains' => 3, 'update_months' => 12],
        ]);

        $this->post('/warenkorb', ['product' => $product->id, 'quantity' => 1]);
        $this->post('/kasse', [
            'email' => 'kunde@example.com', 'name' => 'Erika Mustermann', 'street' => 'Hauptstraße 1', 'zip' => '50667',
            'city' => 'Köln', 'country' => 'DE', 'payment_method' => 'prepayment', 'accept_terms' => '1',
        ])->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame(0, IssuedLicense::count());

        $order->markPaid();
        $order->markPaid(); // doppelt ausgelöst: trotzdem nur eine Lizenz

        $issued = IssuedLicense::sole();
        $this->assertTrue($issued->core);
        $this->assertSame(['premium-galerie'], $issued->modules);
        $this->assertSame(3, $issued->max_domains);
        $this->assertSame($order->id, $issued->order_id);
        $this->assertSame(now()->addMonths(12)->toDateString(), $issued->updates_until->toDateString());

        Mail::assertSent(LicenseIssued::class, fn ($m) => $m->hasTo('kunde@example.com') && str_contains($m->render(), $issued->key));
    }

    public function test_schnittstelle_des_lizenzservers(): void
    {
        $this->postJson('/api/nova/aktivieren', [])->assertNotFound();

        $this->withServer();
        $issued = $this->issue();
        $res = $this->postJson('/api/nova/aktivieren', ['key' => $issued->key, 'domain' => 'kunde.de', 'install_id' => str_repeat('a', 32), 'version' => '0.1.0'])
            ->assertOk()->assertJsonPath('ok', true);
        $data = Signer::open($res->json('license'));
        $this->assertSame('kunde.de', $data['domain']);
        $this->assertSame('active', $data['status']);

        $this->postJson('/api/nova/aktivieren', ['key' => $issued->key, 'domain' => 'andere.de', 'install_id' => str_repeat('b', 32)])
            ->assertStatus(422)->assertJsonPath('ok', false);

        $this->postJson('/api/nova/freigeben', ['key' => $issued->key, 'install_id' => str_repeat('a', 32)])->assertOk();
        $this->assertSame(0, $issued->activations()->count());

        $this->publish('premium-galerie', $this->premiumZip(), paid: true);
        $catalog = Signer::open($this->postJson('/api/nova/marktplatz')->assertOk()->json('catalog'));
        $this->assertSame('premium-galerie', $catalog['modules'][0]['slug']);
        $this->postJson('/api/nova/marktplatz/premium-galerie', ['install_id' => str_repeat('a', 32)])->assertForbidden();
    }

    public function test_verwaltung_des_lizenzservers(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/lizenzserver')->assertNotFound();

        $this->withServer();
        $this->actingAs($admin)->get('/admin/lizenzserver')->assertOk()->assertSee('Lizenz erstellen');
        $this->actingAs($admin)->post('/admin/lizenzserver/lizenzen', [
            'customer_name' => 'Kanzlei Beispiel', 'customer_email' => 'info@kanzlei.example', 'core' => '1', 'max_domains' => 2,
        ])->assertSessionHasNoErrors();
        $issued = IssuedLicense::sole();
        $this->assertTrue(LicenseKey::isValid($issued->key));
        $this->actingAs($admin)->get(route('admin.license-server.show', $issued))->assertOk()->assertSee($issued->key)->assertSee('Kanzlei Beispiel');

        $this->actingAs($admin)->put(route('admin.license-server.update', $issued), ['customer_name' => 'Kanzlei Beispiel', 'max_domains' => 5, 'status' => 'revoked'])
            ->assertSessionHasNoErrors();
        $this->assertSame('revoked', $issued->fresh()->status);
        $this->assertSame(5, $issued->fresh()->max_domains);

        // Modul hochladen: Angaben kommen aus plugin.json
        $this->actingAs($admin)->post('/admin/lizenzserver/module', ['module' => new UploadedFile($this->premiumZip('2.0.0'), 'premium.zip', 'application/zip', null, true)])
            ->assertSessionHasNoErrors();
        $module = MarketModule::sole();
        $this->marketFiles[] = $module->path();
        $this->assertTrue($module->is_paid);
        $this->assertFalse($module->is_published);
        $this->assertSame('2.0.0', $module->version);
        $this->assertSame(hash_file('sha256', $module->path()), $module->sha256);

        $this->actingAs($admin)->put(route('admin.license-server.modules.update', $module), [
            'description' => 'Schöne Galerien', 'category' => 'inhalt', 'price' => '49,00', 'is_paid' => '1', 'is_published' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(4900, $module->fresh()->price_cents);
        $this->assertTrue($module->fresh()->is_published);

        $this->actingAs($admin)->post('/admin/lizenzserver/module', ['module' => UploadedFile::fake()->create('x.zip', 1, 'application/zip')])
            ->assertSessionHasErrors('module');
    }
}
