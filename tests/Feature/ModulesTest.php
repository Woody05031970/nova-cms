<?php

namespace Tests\Feature;

use App\Licensing\Features;
use App\Licensing\License;
use App\Licensing\Signer;
use App\Models\EditorialItem;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Models\SocialPost;
use App\Models\User;
use App\Shop\ShopSettings;
use App\Support\Geo\Geo;
use App\Support\Planner\Tick;
use App\Support\Social\SocialPublisher;
use App\Support\Starter\NovaSalesSite;
use App\Support\Turbo\PageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Version 0.6: KI-Sichtbarkeit, Social Media, Redaktionsplan, Statistik, Turbo und SEO Pro als Module */
class ModulesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function page(array $attrs = []): Page
    {
        return Page::create($attrs + ['title' => 'Leistungen', 'slug' => 'leistungen', 'status' => 'published', 'published_at' => now(), 'show_in_menu' => true,
            'meta_description' => 'Wir reparieren Fahrräder aller Marken in Erfurt, schnell und zum fairen Festpreis.',
            'blocks' => [['id' => 'a', 'type' => 'hero', 'data' => ['title' => 'Fahrradwerkstatt', 'text' => 'Wir reparieren Ihr Fahrrad in 24 Stunden, für alle Marken, mit Abholung in ganz Erfurt.', 'image' => '/uploads/rad.jpg']]]]);
    }

    private function customerLicense(array $modules): void
    {
        $pair = Signer::generateKeyPair();
        config(['nova.license.public_key' => $pair['public'], 'nova.license.private_key' => '']);
        Setting::put('license_key', 'NOVA-TEST-KEY');
        $json = json_encode(['key' => 'NOVA-TEST-KEY', 'status' => 'active', 'install_id' => License::installId(),
            'valid_until' => now()->addYear()->getTimestamp(), 'core' => true, 'modules' => $modules]);
        Setting::put('license_payload', $json);
        Setting::put('license_signature', Signer::sign($json, $pair['secret']));
        $this->app->forgetScopedInstances();
        $this->app->forgetInstance(License::class);
        $this->app->forgetInstance(Features::class);
    }

    public function test_neue_module_sind_nur_mit_lizenz_erreichbar(): void
    {
        $this->customerLicense(['design-pro']);
        $admin = $this->admin();
        foreach (['/admin/seo/mitbewerber', '/admin/seo/ki-sichtbarkeit', '/admin/social-media', '/admin/redaktionsplan', '/admin/statistik', '/admin/turbo'] as $url) {
            $this->actingAs($admin)->get($url)->assertForbidden()->assertSee('ist in Ihrer Lizenz nicht enthalten');
        }
        $this->get('/llms.txt')->assertNotFound();
        // Kernfunktionen bleiben
        $this->get('/admin/seo/weiterleitungen')->assertOk();
        $this->get('/admin/seo')->assertOk()->assertSee('Mitbewerber und Maßnahmen');

        $this->customerLicense(['seo-pro', 'geo']);
        $this->get('/admin/seo/mitbewerber')->assertOk();
        $this->get('/admin/seo/ki-sichtbarkeit')->assertOk();
    }

    public function test_ki_sichtbarkeit_strukturierte_daten_llms_und_crawler(): void
    {
        Setting::putMany(['site_name' => 'Radwerk', 'company_name' => 'Radwerk GmbH', 'zip_city' => '99084 Erfurt', 'phone' => '0361 123456',
            'geo_summary' => 'Radwerk repariert seit 2009 Fahrräder und E-Bikes in Erfurt.', 'geo_profile_instagram' => 'https://instagram.com/radwerk']);
        $page = $this->page();

        $html = $this->get('/leistungen')->assertOk()->getContent();
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringContainsString('"sameAs":["https://instagram.com/radwerk"]', $html);
        $this->assertStringContainsString('rel="alternate" type="text/markdown"', $html);

        $llms = $this->get('/llms.txt')->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')->getContent();
        $this->assertStringStartsWith("# Radwerk\n\n> Radwerk repariert seit 2009", $llms);
        $this->assertStringContainsString('- [Leistungen]('.$page->url().'): Wir reparieren Fahrräder', $llms);

        $this->assertStringNotContainsString('GPTBot', $this->get('/robots.txt')->getContent());
        $this->actingAs($this->admin())->put('/admin/seo/ki-sichtbarkeit', ['geo_ai_bots' => 'block', 'geo_llms' => '0', 'geo_summary' => 'Kurz'])->assertRedirect();
        auth()->logout();
        $robots = $this->get('/robots.txt')->getContent();
        $this->assertStringContainsString("User-agent: GPTBot\nDisallow: /", $robots);
        $this->assertStringContainsString("User-agent: *\nDisallow: /admin", $robots);
        $this->get('/llms.txt')->assertNotFound();

        // Prüfung je Seite
        $check = app(Geo::class)->check($page);
        $byKey = collect($check['checks'])->keyBy('key');
        $this->assertTrue($byKey['summary']['ok']);
        $this->assertTrue($byKey['answer-first']['ok']);
        $this->assertFalse($byKey['questions']['ok']);
        $this->actingAs($this->admin())->get('/admin/seo/ki-sichtbarkeit')->assertOk()->assertSee('data-geo-page="leistungen"', false);
    }

    public function test_seite_mit_facebook_und_instagram_veroeffentlichen(): void
    {
        Http::fake([
            'graph.facebook.com/v21.0/123456789?*' => Http::response(['name' => 'Radwerk', 'instagram_business_account' => ['id' => '1784', 'username' => 'radwerk']]),
            'graph.facebook.com/v21.0/123456789/photos' => Http::response(['id' => '9', 'post_id' => '123456789_9']),
            'graph.facebook.com/v21.0/1784/media' => Http::response(['id' => 'c1']),
            'graph.facebook.com/v21.0/1784/media_publish' => Http::response(['id' => 'ig9']),
        ]);
        $admin = $this->admin();
        $this->actingAs($admin)->put('/admin/social-media/verbindung', ['page_id' => '123456789', 'token' => 'EAAB-geheim'])
            ->assertRedirect(route('admin.social'))->assertSessionHas('status', fn ($s) => str_contains($s, 'Instagram @radwerk'));
        $this->assertNotSame('EAAB-geheim', Setting::get('social_fb_token')); // verschlüsselt
        $this->assertSame('EAAB-geheim', ShopSettings::secret('social_fb_token'));

        $page = $this->page(['status' => 'draft', 'published_at' => null]);
        $edit = $this->get('/admin/seiten/'.$page->id)->assertOk();
        $edit->assertSee('Bei Facebook posten')->assertSee('Bei Instagram posten');

        $this->put('/admin/seiten/'.$page->id, [
            'title' => 'Leistungen', 'slug' => 'leistungen', 'blocks' => json_encode($page->blocks), 'action' => 'publish',
            'social_networks' => ['facebook', 'instagram'], 'social_text' => 'Neu: unsere Werkstatt! Mehr unter '.$page->url(),
        ])->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, 'Facebook: Beitrag veröffentlicht.') && str_contains($s, 'Instagram: Beitrag veröffentlicht.'));

        $this->assertSame(2, SocialPost::where('status', 'sent')->count());
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/1784/media') && $r['image_url'] === url('/uploads/rad.jpg') && str_contains($r['caption'], 'Link in der Bio'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/photos') && $r['access_token'] === 'EAAB-geheim');
    }

    public function test_abgelaufener_schluessel_wird_verstaendlich_gemeldet(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 190, 'message' => 'Error validating access token']], 400)]);
        $this->actingAs($this->admin())->put('/admin/social-media/verbindung', ['page_id' => '123456789', 'token' => 'alt'])
            ->assertSessionHasErrors(['token' => 'Der Zugriffsschlüssel ist abgelaufen oder ungültig. Erzeugen Sie unter „Social Media“ einen neuen Schlüssel.']);
        $this->assertNull(Setting::get('social_fb_page_id'));
    }

    public function test_textvorschlag_ohne_ki_aus_vorlage(): void
    {
        $page = $this->page(['focus_keyword' => 'Fahrrad Reparatur Erfurt']);
        $this->actingAs($this->admin())->postJson('/admin/social-media/vorschlag', ['source' => 'page:'.$page->id])
            ->assertOk()->assertJson(['ai' => false, 'image' => url('/uploads/rad.jpg')])
            ->assertJsonPath('text', fn ($t) => str_starts_with($t, "Leistungen\n\n") && str_contains($t, '👉 Mehr dazu: '.$page->url()) && str_contains($t, '#Fahrrad'));
    }

    public function test_zeitgesteuert_veroeffentlichen_mit_beitrag(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => '1_2'])]);
        ShopSettings::putSecret('social_fb_token', 'tok');
        Setting::put('social_fb_page_id', '123456789');
        $page = $this->page(['status' => 'draft', 'published_at' => null, 'slug' => 'sommeraktion', 'title' => 'Sommeraktion']);

        $this->actingAs($this->admin())->put('/admin/seiten/'.$page->id, [
            'title' => 'Sommeraktion', 'slug' => 'sommeraktion', 'blocks' => json_encode($page->blocks), 'action' => 'schedule',
            'publish_at' => now()->addDay()->format('Y-m-d\TH:i'), 'social_networks' => ['facebook'], 'social_text' => 'Ab morgen: Sommeraktion!',
        ])->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, 'automatisch online') && str_contains($s, 'Beitrag folgt mit der Seite'));

        $page->refresh();
        $this->assertTrue($page->isScheduled());
        $this->assertSame('pending', SocialPost::first()->status);
        auth()->logout();
        $this->get('/sommeraktion')->assertNotFound();
        $this->actingAs($this->admin())->get('/admin/redaktionsplan?monat='.now()->addDay()->format('Y-m'))->assertOk()->assertSee('Sommeraktion')->assertSee('Geplante Seiten');

        $this->travel(25)->hours();
        $result = Tick::run();
        $this->assertSame(['pages' => 1, 'posts' => 1], $result);
        $this->assertTrue($page->fresh()->isPublished());
        $this->assertSame('sent', SocialPost::first()->status);
        auth()->logout();
        $this->get('/sommeraktion')->assertOk();

        // Termin in der Vergangenheit wird abgelehnt
        $other = $this->page(['status' => 'draft', 'slug' => 'b', 'title' => 'B']);
        $this->actingAs($this->admin())->put('/admin/seiten/'.$other->id, ['title' => 'B', 'blocks' => '[]', 'action' => 'schedule', 'publish_at' => now()->subHour()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors(['publish_at' => 'Der Termin muss in der Zukunft liegen.']);
    }

    public function test_redaktionsplan_eintraege(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/redaktionsplan', ['title' => 'Herbstnewsletter', 'date' => '2026-10-02', 'channel' => 'newsletter', 'notes' => 'Neue Kollektion'])
            ->assertRedirect(route('admin.plan', ['monat' => '2026-10']));
        $item = EditorialItem::first();
        $this->get('/admin/redaktionsplan?monat=2026-10')->assertOk()->assertSee('Herbstnewsletter')->assertSee('data-plan-day="2026-10-02"', false);
        $this->put('/admin/redaktionsplan/'.$item->id, ['toggle' => 1]);
        $this->assertTrue($item->fresh()->done);
        $this->delete('/admin/redaktionsplan/'.$item->id);
        $this->assertSame(0, EditorialItem::count());
    }

    public function test_besucherstatistik_ohne_personenbezug(): void
    {
        $this->page();
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone)', 'Referer' => 'https://www.google.de/search'])->get('/leistungen')->assertOk();
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows)'])->get('/leistungen');
        $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])->get('/leistungen');
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0'])->get('/suche?q=Reparatur');
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0'])->get('/gibt-es-nicht');

        $row = DB::table('stat_views')->where('path', '/leistungen')->first();
        $this->assertSame(2, (int) $row->views);
        $this->assertSame(1, (int) $row->mobile);
        $this->assertSame('google.de', DB::table('stat_sources')->where('kind', 'referrer')->value('value'));
        $this->assertSame('reparatur', DB::table('stat_sources')->where('kind', 'search')->value('value'));
        $this->assertFalse(DB::table('stat_views')->where('path', '/gibt-es-nicht')->exists());
        // Keine IP-Adressen oder Kennungen in den Tabellen
        $this->assertSame(['id', 'day', 'path', 'views', 'mobile'], array_keys((array) $row));

        $admin = $this->admin();
        $this->actingAs($admin)->get('/leistungen');
        $this->assertSame(2, (int) DB::table('stat_views')->where('path', '/leistungen')->value('views'));
        $this->get('/admin/statistik')->assertOk()->assertSee('data-stats-total', false)->assertSee('Leistungen')->assertSee('google.de');
    }

    public function test_turbo_speichert_seiten_fuer_besucher(): void
    {
        $page = $this->page();
        $this->get('/leistungen')->assertHeader('X-Nova-Cache', 'MISS');
        $hit = $this->get('/leistungen')->assertHeader('X-Nova-Cache', 'HIT');
        $this->assertStringContainsString('Fahrradwerkstatt', $hit->getContent());
        $this->assertStringNotContainsString('%%NOVA_CSRF%%', $hit->getContent());

        // Änderung leert den Zwischenspeicher sofort
        $page->update(['title' => 'Werkstatt']);
        $this->get('/leistungen')->assertHeader('X-Nova-Cache', 'MISS');
        // Parameter, Suche und angemeldete Benutzer bekommen nie den Zwischenspeicher
        $this->get('/leistungen?x=1')->assertHeaderMissing('X-Nova-Cache');
        $this->actingAs($this->admin())->get('/leistungen')->assertHeaderMissing('X-Nova-Cache');
        $this->get('/admin/turbo')->assertOk()->assertSee('Turbo einschalten');
        $this->put('/admin/turbo', ['turbo_minutes' => 60])->assertRedirect();
        auth()->logout();
        $this->get('/leistungen')->assertHeaderMissing('X-Nova-Cache');
        $this->assertFalse(PageCache::enabled());
    }

    public function test_verkaufswebsite_bietet_die_neuen_module_an(): void
    {
        app(NovaSalesSite::class)->install();
        foreach (['modul-seo-pro', 'modul-geo', 'modul-social', 'modul-redaktionsplan', 'modul-statistik', 'modul-turbo'] as $slug) {
            $p = Product::where('slug', $slug)->first();
            $this->assertTrue($p->is_active, $slug);
            $this->assertSame([str_replace('modul-', '', $slug)], $p->license_spec['modules']);
        }
        $this->get('/module')->assertOk()->assertSee('KI-Sichtbarkeit')->assertSee('Besucherstatistik');
        $this->get('/preise')->assertSee('WhatsApp, SEO Pro und Besucherstatistik')->assertSee('Alle Module inklusive');
        $this->assertStringContainsString('Inklusive Module: Design Pro, WhatsApp, SEO Pro, Besucherstatistik', Product::where('slug', 'nova-cms-business')->value('description'));
        $this->assertTrue(SocialPublisher::on());
    }
}
