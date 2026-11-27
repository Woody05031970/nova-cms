<?php

namespace Tests\Feature;

use App\Licensing\Features;
use App\Licensing\License;
use App\Licensing\Signer;
use App\Mail\NewsletterConfirm;
use App\Mail\NewsletterMail;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Blocks\BlockSanitizer;
use App\Support\Design\SiteDesign;
use App\Support\Pages\PageTemplates;
use App\Support\Starter\NovaSalesSite;
use App\Support\WhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DesignNewsletterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function reload(): void
    {
        $this->app->forgetScopedInstances();
        $this->app->forgetInstance(License::class);
        $this->app->forgetInstance(Features::class);
    }

    /** Kunden-Installation mit gekaufter Lizenz, die nur die angegebenen Module enthält */
    private function customerLicense(array $modules): void
    {
        $pair = Signer::generateKeyPair();
        config(['nova.license.public_key' => $pair['public'], 'nova.license.private_key' => '']);
        Setting::put('license_key', 'NOVA-TEST-KEY');
        $json = json_encode(['key' => 'NOVA-TEST-KEY', 'status' => 'active', 'install_id' => License::installId(),
            'valid_until' => now()->addYear()->getTimestamp(), 'core' => true, 'modules' => $modules]);
        Setting::put('license_payload', $json);
        Setting::put('license_signature', Signer::sign($json, $pair['secret']));
        $this->reload();
    }

    private function trialOver(): void
    {
        $pair = Signer::generateKeyPair();
        config(['nova.license.public_key' => $pair['public'], 'nova.license.private_key' => '', 'nova.license.lock_after_trial' => false]);
        Setting::put('installed_at', now()->subDays(90)->toIso8601String());
        $this->reload();
    }

    public function test_freischaltung_richtet_sich_nach_der_lizenz(): void
    {
        // Entwicklerversion: alles
        $this->assertTrue(Features::on('newsletter'));

        // Hersteller-Installation (Lizenzserver): alles
        $pair = Signer::generateKeyPair();
        config(['nova.license.public_key' => $pair['public'], 'nova.license.private_key' => $pair['secret']]);
        $this->reload();
        $this->assertTrue(Features::on('design-pro') && Features::on('newsletter') && Features::on('whatsapp'));

        // Testphase beim Kunden: alles zum Ausprobieren
        config(['nova.license.private_key' => '']);
        $this->reload();
        $this->assertSame(License::STATUS_TRIAL, app(License::class)->status());
        $this->assertTrue(Features::on('newsletter'));

        // Gekauftes Paket Business: Design Pro und WhatsApp, aber kein Newsletter
        $this->customerLicense(['design-pro', 'whatsapp']);
        $this->assertSame(License::STATUS_ACTIVE, app(License::class)->status());
        $this->assertTrue(Features::on('design-pro'));
        $this->assertTrue(Features::on('whatsapp'));
        $this->assertFalse(Features::on('newsletter'));

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/newsletter')->assertStatus(403)->assertSee('Newsletter');
        $this->post('/newsletter/anmelden', ['email' => 'a@example.org'])->assertNotFound();
        $this->actingAs($admin)->get('/admin/whatsapp')->assertOk();
    }

    public function test_design_wird_als_css_ausgegeben(): void
    {
        Setting::putMany(['design_theme' => 'studio', 'design_brand' => '#0F766E', 'design_accent' => '#f59e0b', 'design_font' => 'serif', 'design_radius' => 'sharp', 'design_header' => 'dark']);
        $v = SiteDesign::values();
        $this->assertSame('studio', $v['theme']);
        $this->assertSame('#0f766e', $v['brand']);
        $css = SiteDesign::css($v);
        $this->assertStringContainsString('--color-nova-600:#0f766e', $css);
        $this->assertStringContainsString('--color-fuchsia-600:#f59e0b', $css);
        $this->assertStringContainsString('--radius-2xl:0.375rem', $css);
        $this->assertStringContainsString('Georgia', $css);

        Page::create(['title' => 'Start', 'slug' => 'start', 'status' => 'published', 'is_home' => true, 'blocks' => []]);
        $this->get('/')->assertOk()->assertSee('--color-nova-600:#0f766e', false)->assertSee('data-theme="studio"', false);

        // Ungültige Werte fallen auf die Vorlage zurück
        Setting::putMany(['design_brand' => 'red;}body{display:none', 'design_font' => 'comic']);
        $this->assertSame('#111827', SiteDesign::values()['brand']);
        $this->assertSame('serif', SiteDesign::values()['font']);
        $this->assertSame(10, count(SiteDesign::palette('#5a37f5')));
    }

    public function test_ohne_design_pro_nur_freie_vorlage(): void
    {
        Setting::putMany(['design_theme' => 'aurora', 'design_accent' => '#000000', 'design_animation' => 'zoom']);
        $this->customerLicense([]);
        $v = SiteDesign::values();
        $this->assertSame('klar', $v['theme']);
        $this->assertSame(SiteDesign::THEMES['klar']['accent'], $v['accent']);
        $this->assertSame('none', $v['animation']);

        $this->actingAs($this->admin())->put('/admin/design', ['theme' => 'aurora', 'brand' => '#123456', 'radius' => 'soft', 'header' => 'light', 'footer' => 'light'])
            ->assertSessionHasErrors('theme');
        $this->put('/admin/design', ['theme' => 'klar', 'brand' => '#123456', 'radius' => 'round', 'header' => 'glass', 'footer' => 'dark', 'show_name' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('#123456', Setting::get('design_brand'));
        $this->assertSame('glass', SiteDesign::values()['header']);
    }

    public function test_design_bereich_und_vorschau(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/design')->assertOk()->assertSee('Aurora')->assertSee('Nacht');
        Page::create(['title' => 'Start', 'slug' => 'start', 'status' => 'published', 'is_home' => true, 'blocks' => []]);
        $preview = base64_encode(json_encode(['theme' => 'nacht', 'brand' => '#ff0000']));
        $this->get('/?design_vorschau='.$preview)->assertOk()->assertSee('--color-nova-600:#ff0000', false);
        $this->assertNotSame('#ff0000', Setting::get('design_brand'));

        // Gäste können die Vorschau nicht nutzen
        auth()->logout();
        $this->get('/?design_vorschau='.$preview)->assertOk()->assertDontSee('--color-nova-600:#ff0000', false);
    }

    public function test_titelbild_mit_hintergrundvideo(): void
    {
        $blocks = [['id' => 'h1', 'type' => 'hero', 'data' => ['variant' => 'video', 'title' => 'Sommeraktion', 'text' => '20 % auf alles', 'video' => '/uploads/2026/12/clip.mp4', 'overlay' => 'brand', 'opacity' => 40, 'height' => 'full', 'button_label' => 'Jetzt sichern', 'button_url' => '/shop']]];
        Page::create(['title' => 'Start', 'slug' => 'start', 'status' => 'published', 'is_home' => true, 'blocks' => app(BlockSanitizer::class)->clean($blocks)]);

        $this->get('/')->assertOk()->assertSee('data-video-hero', false)->assertSee('/uploads/2026/12/clip.mp4', false)
            ->assertSee('Sommeraktion')->assertSee('Jetzt sichern')->assertSee('opacity: 0.4', false);

        // Ohne Design Pro wird stattdessen das technische Titelbild gezeigt
        $this->customerLicense([]);
        $this->get('/')->assertOk()->assertDontSee('data-video-hero', false)->assertSee('Sommeraktion');

        // Nur eigene Uploads oder https-Adressen werden übernommen
        $clean = app(BlockSanitizer::class)->clean([['type' => 'hero', 'data' => ['variant' => 'video', 'video' => 'javascript:alert(1)']]]);
        $this->assertNull($clean[0]['data']['video']);
    }

    public function test_newsletter_anmeldung_bestaetigung_und_abmeldung(): void
    {
        Mail::fake();
        Page::create(['title' => 'Start', 'slug' => 'start', 'status' => 'published', 'is_home' => true,
            'blocks' => [['id' => 'n1', 'type' => 'newsletter', 'data' => ['title' => 'Bleiben Sie informiert', 'button_label' => 'Abonnieren']]]]);
        $this->get('/')->assertOk()->assertSee('Bleiben Sie informiert')->assertSee(route('newsletter.subscribe'), false);

        $t = encrypt(now()->subSeconds(5)->getTimestamp());
        // Zu schnell oder mit ausgefülltem Lockfeld: still verworfen
        $this->post('/newsletter/anmelden', ['email' => 'bot@example.org', 'consent' => '1', '_t' => encrypt(now()->getTimestamp())]);
        $this->post('/newsletter/anmelden', ['email' => 'bot2@example.org', 'consent' => '1', '_t' => $t, 'website' => 'x']);
        $this->assertSame(0, NewsletterSubscriber::count());

        $this->post('/newsletter/anmelden', ['email' => 'Anna@Example.org', 'name' => 'Anna', 'consent' => '1', '_t' => $t])->assertSessionHas('newsletter_sent');
        $sub = NewsletterSubscriber::sole();
        $this->assertSame('anna@example.org', $sub->email);
        $this->assertSame('pending', $sub->status);
        Mail::assertSent(NewsletterConfirm::class, fn ($m) => $m->hasTo('anna@example.org'));

        $this->get(route('newsletter.confirm', $sub->token))->assertOk()->assertSee('Danke für Ihre Anmeldung');
        $this->assertSame('confirmed', $sub->fresh()->status);

        $this->get($sub->unsubscribeUrl())->assertOk()->assertSee('wirklich abbestellen');
        $this->post(route('newsletter.unsubscribe.do', $sub->token))->assertOk()->assertSee('Sie sind abgemeldet');
        $this->assertSame('unsubscribed', $sub->fresh()->status);

        // Ohne Einwilligung keine Anmeldung
        $this->post('/newsletter/anmelden', ['email' => 'b@example.org', '_t' => $t])->assertSessionHasErrors('consent');
    }

    public function test_newsletter_versand_in_schueben(): void
    {
        Mail::fake();
        foreach (range(1, 25) as $i) {
            NewsletterSubscriber::create(['email' => "kunde{$i}@example.org", 'status' => 'confirmed', 'confirmed_at' => now()]);
        }
        NewsletterSubscriber::create(['email' => 'offen@example.org', 'status' => 'pending']);
        NewsletterSubscriber::create(['email' => 'weg@example.org', 'status' => 'unsubscribed']);

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/newsletter')->assertOk()->assertSee('25');
        $this->post('/admin/newsletter', ['subject' => 'Neu im Shop', 'heading' => 'Frische Ware', 'body' => "Hallo!\n\nWir haben Neues.", 'button_label' => 'Zum Shop', 'button_url' => '/shop'])
            ->assertRedirect();
        $campaign = NewsletterCampaign::sole();
        $this->assertSame(url('/shop'), $campaign->button_url);

        $this->post(route('admin.newsletter.test', $campaign))->assertSessionHas('status');
        Mail::assertSent(NewsletterMail::class, fn ($m) => $m->hasTo($admin->email));

        $this->postJson(route('admin.newsletter.send', $campaign))->assertOk()->assertJson(['done' => false, 'sent' => 20, 'total' => 25]);
        $this->postJson(route('admin.newsletter.send', $campaign))->assertOk()->assertJson(['done' => true, 'sent' => 25]);
        $this->assertSame('sent', $campaign->fresh()->status);
        Mail::assertSent(NewsletterMail::class, 26);
        Mail::assertNotSent(NewsletterMail::class, fn ($m) => $m->hasTo('offen@example.org') || $m->hasTo('weg@example.org'));

        $this->get(route('admin.newsletter.export'))->assertOk();
    }

    public function test_whatsapp_knopf_und_produktanfrage(): void
    {
        $this->assertSame('4917612345678', WhatsApp::number('0176 1234 5678'));
        $this->assertSame('4317612345', WhatsApp::number('0043 176 12345'));
        $this->assertSame('4917612345678', WhatsApp::number('+49 176 12345678'));
        $this->assertNull(WhatsApp::number('123'));

        Page::create(['title' => 'Start', 'slug' => 'start', 'status' => 'published', 'is_home' => true, 'blocks' => []]);
        $this->get('/')->assertDontSee('data-whatsapp-button', false);

        $this->actingAs($this->admin())->put('/admin/whatsapp', ['whatsapp_enabled' => '1', 'whatsapp_number' => '0176 1234 5678', 'whatsapp_message' => 'Hallo!', 'whatsapp_label' => 'Chat starten', 'whatsapp_position' => 'left', 'whatsapp_floating' => '1', 'whatsapp_products' => '1'])
            ->assertSessionHasNoErrors();
        $this->get('/')->assertSee('data-whatsapp-button', false)->assertSee('https://wa.me/4917612345678?text=Hallo%21', false)->assertSee('Chat starten');

        Setting::putMany(['shop_enabled' => '1']);
        $p = Product::create(['name' => 'Honig', 'slug' => 'honig', 'price_cents' => 690, 'tax_rate' => 7, 'is_active' => true, 'images' => []]);
        $this->get($p->url())->assertOk()->assertSee('data-whatsapp-product', false)->assertSee('Honig');

        // Ohne WhatsApp-Modul verschwindet der Knopf
        $this->customerLicense(['design-pro']);
        $this->get('/')->assertDontSee('data-whatsapp-button', false);
    }

    public function test_neue_seitenvorlagen(): void
    {
        foreach (['landing', 'aktion', 'leistungen', 'newsletter'] as $key) {
            $this->assertArrayHasKey($key, PageTemplates::all());
            $page = PageTemplates::make($key);
            $this->assertNotEmpty($page['blocks'] ?? $page, $key);
        }
        $types = array_column(PageTemplates::make('landing')['blocks'], 'type');
        $this->assertContains('hero', $types);
        $this->assertSame('video', PageTemplates::make('landing')['blocks'][0]['data']['variant']);
        $this->assertContains('newsletter', array_column(PageTemplates::make('newsletter')['blocks'], 'type'));
    }

    public function test_startpaket_aktualisieren(): void
    {
        app(NovaSalesSite::class)->install();
        Product::where('slug', 'modul-whatsapp')->delete();
        Product::where('slug', 'modul-newsletter')->update(['is_active' => false, 'price_cents' => 5000]);

        $this->actingAs($this->admin())->get('/admin/einstellungen')->assertSee('data-starter-refresh', false);
        $this->post('/admin/startinhalt/aktualisieren')->assertSessionHas('status');

        $this->assertTrue(Product::where('slug', 'modul-whatsapp')->first()->is_active);
        $newsletter = Product::where('slug', 'modul-newsletter')->first();
        $this->assertTrue($newsletter->is_active);
        $this->assertSame(5000, $newsletter->price_cents); // eigener Preis bleibt
        $this->assertSame(['design-pro', 'whatsapp', 'newsletter'], Product::where('slug', 'nova-cms-agentur')->first()->license_spec['modules']);
    }
}
