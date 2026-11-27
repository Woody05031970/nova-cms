<?php

namespace Tests\Feature;

use App\Models\Competitor;
use App\Models\MenuItem;
use App\Models\NotFoundLog;
use App\Models\Page;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\Setting;
use App\Models\User;
use App\Support\Accessibility\AccessibilityChecker;
use App\Support\Design\SiteDesign;
use App\Support\Seo\CompetitorAnalyzer;
use App\Support\Seo\SeoAdvisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccessibilitySeoToolsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function page(array $attrs = []): Page
    {
        return Page::create($attrs + ['title' => 'Leistungen', 'slug' => 'leistungen', 'status' => 'published', 'published_at' => now(), 'blocks' => [], 'show_in_menu' => true]);
    }

    public function test_website_hat_sprunglink_landmarken_und_handy_menue(): void
    {
        Page::create(['title' => 'Start', 'slug' => 'start', 'status' => 'published', 'is_home' => true, 'blocks' => []]);
        $parent = $this->page(['title' => 'Leistungen', 'slug' => 'leistungen']);
        $child = $this->page(['title' => 'Reparatur', 'slug' => 'reparatur']);
        MenuItem::where('page_id', $child->id)->update(['parent_id' => MenuItem::where('page_id', $parent->id)->value('id')]);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('href="#inhalt"', $html);
        $this->assertStringContainsString('<main id="inhalt"', $html);
        $this->assertStringContainsString('aria-controls="mobile-menu"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('data-submenu-toggle', $html);
        $this->assertStringContainsString('Unterpunkte von Leistungen', $html);
        $this->assertStringContainsString('role="search"', $html);
        $this->assertStringContainsString('<html lang="de">', $html);
    }

    public function test_kontrast_nach_wcag(): void
    {
        $this->assertSame(21.0, SiteDesign::contrast('#000000', '#ffffff'));
        $this->assertGreaterThanOrEqual(4.5, SiteDesign::contrast('#5a37f5', '#ffffff'));
        $this->assertLessThan(4.5, SiteDesign::contrast('#facc15', '#ffffff'));
    }

    public function test_barrierefreiheits_check_findet_barrieren(): void
    {
        $issues = app(AccessibilityChecker::class)->pageIssues([
            ['type' => 'image', 'data' => ['url' => '/uploads/a.jpg', 'alt' => '']],
            ['type' => 'text', 'data' => ['html' => '<h2>Titel</h2><h4>Zu tief</h4><p>Infos <a href="/preise">hier</a> und <a href="/x"></a></p><table><tr><td>1</td></tr></table><a href="/a.pdf">Preisliste</a>']],
            ['type' => 'button', 'data' => ['label' => 'Mehr', 'url' => '/x']],
            ['type' => 'video', 'data' => ['file' => '/uploads/v.mp4']],
        ]);
        $labels = implode(' | ', array_column($issues, 'label'));
        $this->assertStringContainsString('Ein Bild ohne Beschreibung', $labels);
        $this->assertStringContainsString('H2 → H4', $labels);
        $this->assertStringContainsString('„hier“', $labels);
        $this->assertStringContainsString('Link ohne Text', $labels);
        $this->assertStringContainsString('Tabelle ohne Kopfzeile', $labels);
        $this->assertStringContainsString('Video ohne Untertitel', $labels);
        $this->assertStringContainsString('Verlinktes PDF', $labels);

        $this->assertSame([], app(AccessibilityChecker::class)->pageIssues([
            ['type' => 'image', 'data' => ['url' => '/uploads/a.jpg', 'alt' => 'Werkstatt']],
            ['type' => 'text', 'data' => ['html' => '<h2>A</h2><h3>B</h3><p><a href="/preise">Preisliste ansehen</a></p><table><tr><th>Kopf</th></tr></table>']],
        ]));
    }

    public function test_erklaerung_zur_barrierefreiheit_und_kontrastwarnung(): void
    {
        Setting::putMany(['company_name' => 'Radhaus GmbH', 'contact_email' => 'info@radhaus.example', 'design_brand' => '#facc15']);
        $this->page(['blocks' => [['type' => 'image', 'data' => ['url' => '/uploads/a.jpg', 'alt' => '']]]]);
        $admin = $this->admin();

        $res = $this->actingAs($admin)->get('/admin/barrierefreiheit')->assertOk();
        $res->assertSee('data-a11y-check="statement" data-ok="0"', false)->assertSee('data-a11y-check="button-contrast" data-ok="0"', false)
            ->assertSee('data-a11y-page="leistungen"', false);

        $this->post('/admin/barrierefreiheit/erklaerung')->assertRedirect();
        $statement = Page::where('purpose', 'accessibility')->sole();
        $this->assertTrue($statement->isPublished());
        $this->assertStringContainsString('Marktüberwachungsstelle', $statement->plainText());
        $this->assertStringContainsString('Radhaus GmbH', $statement->plainText());

        $this->get('/admin/barrierefreiheit')->assertSee('data-a11y-check="statement" data-ok="1"', false);
        $this->get('/leistungen')->assertSee('Erklärung zur Barrierefreiheit');

        // Ein zweiter Klick legt keine zweite Seite an
        $this->post('/admin/barrierefreiheit/erklaerung');
        $this->assertSame(1, Page::where('purpose', 'accessibility')->count());
    }

    public function test_umbenannte_seite_leitet_automatisch_weiter(): void
    {
        $page = $this->page(['slug' => 'angebot']);
        $page->update(['slug' => 'leistungen']);
        $this->get('/angebot')->assertRedirect(url('/leistungen'))->assertStatus(301);
        $this->assertSame(1, Redirect::sole()->hits);

        // Kette wird verkürzt: /angebot → /leistungen → /service wird /angebot → /service
        $page->update(['slug' => 'service']);
        $this->assertSame('/service', Redirect::where('from_path', '/angebot')->value('to_url'));
        $this->get('/leistungen')->assertRedirect(url('/service'));

        // Entwürfe erzeugen keine Weiterleitung
        $draft = Page::create(['title' => 'Neu', 'slug' => 'neu', 'status' => 'draft', 'blocks' => []]);
        $draft->update(['slug' => 'neu-2']);
        $this->assertNull(Redirect::where('from_path', '/neu')->first());
    }

    public function test_nicht_gefundene_seite_wird_protokolliert_und_weitergeleitet(): void
    {
        Page::create(['title' => 'Start', 'slug' => 'start', 'status' => 'published', 'is_home' => true, 'blocks' => []]);
        $this->page();
        $this->get('/alte-preisliste', ['referer' => 'https://google.de/'])->assertNotFound()->assertSee('Diese Seite gibt es leider nicht')->assertSee('role="search"', false);
        $this->get('/alte-preisliste');
        $this->get('/wp-login.php');
        $log = NotFoundLog::sole();
        $this->assertSame('/alte-preisliste', $log->path);
        $this->assertSame(2, $log->hits);

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/seo/weiterleitungen')->assertOk()->assertSee('/alte-preisliste');
        $this->post('/admin/seo/weiterleitungen', ['from_path' => 'https://meine-alte-seite.de/Alte-Preisliste?x=1', 'to_url' => '/leistungen', 'status' => 301])->assertSessionHasNoErrors();
        $this->assertSame(0, NotFoundLog::count());
        $this->get('/alte-preisliste')->assertRedirect(url('/leistungen'));

        $this->post('/admin/seo/weiterleitungen', ['from_path' => '/admin/x', 'to_url' => '/'])->assertSessionHasErrors('from_path');
        $this->get('/admin/gibt-es-nicht')->assertNotFound()->assertDontSee('Diese Seite gibt es leider nicht');
    }

    public function test_wartungsmodus(): void
    {
        Page::create(['title' => 'Start', 'slug' => 'start', 'status' => 'published', 'is_home' => true, 'blocks' => []]);
        $admin = $this->admin();
        $this->actingAs($admin)->put('/admin/einstellungen/website', ['maintenance_mode' => '1', 'maintenance_message' => 'Neue Website ab Montag!', 'site_search' => '1'])->assertSessionHas('status');
        $this->get('/')->assertOk(); // Administratoren sehen die Website

        auth()->logout();
        $this->get('/')->assertStatus(503)->assertSee('Neue Website ab Montag!')->assertHeader('Retry-After');
        $this->get('/admin/login')->assertOk();

        Setting::put('maintenance_mode', '0');
        $this->get('/')->assertOk();
    }

    public function test_suche_findet_seiten_und_produkte(): void
    {
        $this->page(['title' => 'Fahrradreparatur', 'slug' => 'reparatur', 'blocks' => [['type' => 'text', 'data' => ['html' => '<p>Wir reparieren E-Bikes und Lastenräder in Leipzig.</p>']]]]);
        $this->page(['title' => 'Team', 'slug' => 'team']);
        Setting::put('shop_enabled', '1');
        Product::create(['name' => 'Lastenrad-Inspektion', 'slug' => 'inspektion', 'price_cents' => 8900, 'tax_rate' => 19, 'is_active' => true, 'images' => []]);

        $this->get('/suche?q=lastenr')->assertOk()->assertSee('Fahrradreparatur')->assertSee('Lastenrad-Inspektion')->assertDontSee('/team" class="block rounded-2xl', false);
        $this->get('/suche?q=xyz')->assertOk()->assertSee('Keine Treffer');
        $this->get('/suche?q=e')->assertOk()->assertSee('mindestens zwei Buchstaben');

        Setting::put('site_search', '0');
        $this->get('/team')->assertDontSee('data-search-link', false);
    }

    private function fakeCompetitor(): void
    {
        $this->app->bind(CompetitorAnalyzer::class, fn () => new class extends CompetitorAnalyzer
        {
            protected function resolve(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
        $text = str_repeat('Wir reparieren Fahrräder, E-Bikes und Lastenräder schnell und günstig in Leipzig. ', 20);
        $nav = '<header><nav><ul><li><a href="/">Start</a></li><li><a href="/werkstatt">Werkstatt</a><ul><li><a href="/werkstatt/e-bike">E-Bike-Service</a></li></ul></li><li><a href="/ratgeber">Ratgeber</a></li><li><a href="/kontakt">Kontakt</a></li></ul></nav></header>';
        $page = fn ($title, $h1, $extra = '') => '<html><head><title>'.$title.' | Radwerk Leipzig</title><meta name="description" content="Fahrradwerkstatt Leipzig"><meta property="og:site_name" content="Radwerk"></head><body>'.$nav.'<h1>'.$h1.'</h1><h2>Fahrradwerkstatt Leipzig</h2><h2>Lastenrad Service</h2><p>'.$text.'</p>'.$extra.'</body></html>';
        Http::fake([
            'https://radwerk.example/sitemap.xml' => Http::response('<urlset><url><loc>a</loc></url><url><loc>b</loc></url></urlset>', 200, ['Content-Type' => 'application/xml']),
            'https://radwerk.example/werkstatt' => Http::response($page('Werkstatt', 'Fahrradwerkstatt Leipzig', '<a href="https://wa.me/49171">WhatsApp</a>'), 200, ['Content-Type' => 'text/html']),
            'https://radwerk.example/ratgeber' => Http::response($page('Ratgeber', 'Ratgeber Lastenrad'), 200, ['Content-Type' => 'text/html']),
            'https://radwerk.example/kontakt' => Http::response($page('Kontakt', 'Kontakt', '<form><input type="email" name="email"><textarea></textarea></form>'), 200, ['Content-Type' => 'text/html']),
            'https://radwerk.example*' => Http::response($page('Fahrradwerkstatt Leipzig', 'Ihre Fahrradwerkstatt', '<script type="application/ld+json">{"@type":"FAQPage"}</script><script type="application/ld+json">{"@type":"LocalBusiness"}</script><a href="tel:0341">0341</a><p>Inspektion ab 49,00 €</p><p>Kundenstimmen</p>'), 200, ['Content-Type' => 'text/html']),
        ]);
    }

    public function test_mitbewerber_analyse_und_massnahmen(): void
    {
        $this->fakeCompetitor();
        Page::create(['title' => 'Start', 'slug' => 'start', 'status' => 'published', 'is_home' => true, 'blocks' => [['type' => 'text', 'data' => ['html' => '<p>Willkommen. <a href="/gibts-nicht">Preise</a></p>']]]]);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/seo/mitbewerber')->assertOk()->assertSee('Mitbewerber analysieren')->assertSee('defekte interne Link');

        $this->post('/admin/seo/mitbewerber', ['url' => 'radwerk.example'])->assertRedirect(route('admin.seo.competitors'))->assertSessionHas('status');
        $c = Competitor::sole();
        $this->assertSame('Radwerk', $c->name);
        $d = $c->data;
        $this->assertSame(['Start', 'Werkstatt', 'Ratgeber', 'Kontakt'], array_column($d['menu'], 'label'));
        $this->assertSame(['E-Bike-Service'], $d['menu'][1]['children']);
        $this->assertCount(4, $d['pages']);
        $this->assertTrue($d['features']['faq']);
        $this->assertTrue($d['features']['blog']);
        $this->assertTrue($d['features']['whatsapp']);
        $this->assertTrue($d['features']['contact_form']);
        $this->assertTrue($d['features']['local']);
        $this->assertTrue($d['features']['prices']);
        $this->assertTrue($d['features']['reviews']);
        $this->assertFalse($d['features']['shop']);
        $this->assertSame(2, $d['sitemap_pages']);
        $this->assertArrayHasKey('lastenrad', $d['terms']);

        $res = $this->get('/admin/seo/mitbewerber')->assertOk();
        $res->assertSee('Häufige Fragen ergänzen')->assertSee('Firmendaten für Google vervollständigen')->assertSee('Wichtige Begriffe aufgreifen')
            ->assertSee('Themen ergänzen, die Mitbewerber abdecken')->assertSee('Mehr aussagekräftigen Text schreiben')->assertSee('data-feature-matrix', false);

        // Maßnahme abhaken
        $this->post('/admin/seo/massnahmen', ['id' => 'feature-faq']);
        $measure = collect(app(SeoAdvisor::class)->advise(Competitor::all())['measures'])->firstWhere('id', 'feature-faq');
        $this->assertTrue($measure['done']);

        // Gleiche Domain wird aktualisiert statt doppelt angelegt
        $this->post('/admin/seo/mitbewerber', ['url' => 'https://radwerk.example/']);
        $this->assertSame(1, Competitor::count());

        // Interne Adressen sind gesperrt
        $this->app->bind(CompetitorAnalyzer::class, fn () => new class extends CompetitorAnalyzer
        {
            protected function resolve(string $host): array
            {
                return ['10.0.0.5'];
            }
        });
        $this->post('/admin/seo/mitbewerber', ['url' => 'intranet.example'])->assertSessionHasErrors('url');

        $this->delete(route('admin.seo.competitors.destroy', $c))->assertSessionHas('status');
        $this->assertSame(0, Competitor::count());
    }
}
