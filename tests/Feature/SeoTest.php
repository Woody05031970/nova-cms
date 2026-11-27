<?php

namespace Tests\Feature;

use App\Ai\AiDriver;
use App\Ai\AiManager;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\Seo\CompetitorAnalyzer;
use App\Support\Seo\PageAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        Setting::putMany(['site_name' => 'Sonnenkorn', 'company_name' => 'Sonnenkorn GmbH', 'street' => 'Lindenstr. 12', 'zip_city' => '50674 Köln', 'phone' => '0221 1', 'tagline' => 'Holzofenbrot aus Köln']);

        // Keine echten DNS-Abfragen im Test
        $this->app->bind(CompetitorAnalyzer::class, fn () => new class extends CompetitorAnalyzer
        {
            protected function resolve(string $host): array
            {
                return match ($host) {
                    'intern.example' => ['192.168.1.10'],
                    'nirgends.example' => [],
                    default => ['93.184.216.34'],
                };
            }
        });
    }

    private function goodPage(): array
    {
        $para = str_repeat('Unser Brot backen wir jeden Morgen frisch im Holzofen mit Sauerteig und viel Zeit. ', 25);

        return [
            'title' => 'Bäckerei in Köln', 'slug' => 'baeckerei-koeln', 'focus_keyword' => 'Bäckerei Köln',
            'meta_title' => 'Bäckerei Köln: Holzofenbrot von Sonnenkorn',
            'meta_description' => 'Ihre Bäckerei in Köln-Ehrenfeld: Holzofenbrot mit Sauerteig, Dinkelbrötchen und Kuchen. Jeden Morgen frisch ab 6 Uhr.',
            'blocks' => [
                ['type' => 'text', 'data' => ['html' => '<p>Willkommen in unserer Bäckerei in Köln. '.$para.'</p><h2>Ihre Bäckerei in Köln-Ehrenfeld</h2><p><a href="/kontakt">Kontakt</a></p>']],
                ['type' => 'image', 'data' => ['url' => '/uploads/a.jpg', 'alt' => 'Brot im Ofen']],
            ],
        ];
    }

    public function test_gute_seite_bekommt_hohe_punktzahl(): void
    {
        $r = (new PageAnalyzer)->analyze($this->goodPage());

        $this->assertSame(100, $r['score'], json_encode(collect($r['checks'])->where('status', '!=', 'good')->values(), JSON_UNESCAPED_UNICODE));
        $this->assertGreaterThan(300, $r['words']);
    }

    public function test_schwache_seite_bekommt_verstaendliche_tipps(): void
    {
        $r = (new PageAnalyzer)->analyze(['title' => 'Start', 'slug' => 'start', 'blocks' => [
            ['type' => 'text', 'data' => ['html' => '<p>Hallo.</p>']],
            ['type' => 'image', 'data' => ['url' => '/uploads/a.jpg', 'alt' => '']],
        ]]);

        $byKey = collect($r['checks'])->keyBy('key');
        $this->assertLessThan(50, $r['score']);
        $this->assertSame('bad', $byKey['description']['status']);
        $this->assertSame('warn', $byKey['keyword']['status']);
        $this->assertSame('bad', $byKey['length']['status']);
        $this->assertSame('1 von 1 Bildern ohne Beschreibung', $byKey['alt']['label']);
        $this->assertStringContainsString('Kontakt', $byKey['links']['tip']);
    }

    public function test_suchbegriff_pruefungen(): void
    {
        $page = $this->goodPage();
        $page['meta_title'] = 'Frisches Brot';
        $page['slug'] = 'brot';
        $r = collect((new PageAnalyzer)->analyze($page)['checks'])->keyBy('key');

        $this->assertSame('bad', $r['kw_title']['status']);
        $this->assertSame('warn', $r['kw_slug']['status']);
        $this->assertStringContainsString('/baeckerei-koeln', $r['kw_slug']['tip']);
        $this->assertSame('good', $r['kw_heading']['status']);
    }

    public function test_pflichtseiten_werden_nicht_bestraft(): void
    {
        $r = collect((new PageAnalyzer)->analyze(['title' => 'Impressum', 'purpose' => 'imprint', 'meta_description' => str_repeat('x', 100), 'blocks' => [['type' => 'text', 'data' => ['html' => '<p>Kurz</p>']]]])['checks'])->keyBy('key');
        $this->assertFalse($r->has('length'));
        $this->assertSame('good', $r['keyword']['status']);
    }

    public function test_sitemap_robots_und_sperre(): void
    {
        Page::create(['title' => 'Start', 'slug' => 'startseite', 'is_home' => true, 'status' => 'published', 'blocks' => []]);
        Page::create(['title' => 'Leistungen', 'slug' => 'leistungen', 'status' => 'published', 'blocks' => []]);
        Page::create(['title' => 'Geheim', 'slug' => 'geheim', 'status' => 'draft', 'blocks' => []]);

        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin')->assertSee('Sitemap: '.url('sitemap.xml'));
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>'.url('/').'</loc>', false)->assertSee(url('leistungen'))->assertDontSee('geheim');

        $this->actingAs($this->admin)->put(route('admin.seo.settings'), ['allow_indexing' => 0])->assertRedirect();
        $this->get('/robots.txt')->assertSee("Disallow: /\n", false);
        $this->get('/sitemap.xml')->assertNotFound();
        $this->get('/leistungen')->assertSee('<meta name="robots" content="noindex">', false);
    }

    public function test_startseite_hat_firmendaten_und_open_graph(): void
    {
        Page::create(['title' => 'Start', 'slug' => 'startseite', 'is_home' => true, 'status' => 'published', 'meta_description' => 'Brot aus Köln',
            'blocks' => [['id' => 'h1', 'type' => 'hero', 'data' => ['title' => 'Willkommen', 'image' => '/uploads/x.jpg']]]]);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('<meta property="og:image" content="'.url('/uploads/x.jpg').'">', $html);
        $this->assertStringContainsString('<meta property="og:description" content="Brot aus Köln">', $html);
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $ld = json_decode($m[1], true);
        $this->assertSame('LocalBusiness', $ld['@type']);
        $this->assertSame('50674', $ld['address']['postalCode']);
        $this->assertSame('Köln', $ld['address']['addressLocality']);
    }

    public function test_seo_uebersicht_und_live_pruefung(): void
    {
        Page::create(['title' => 'Start', 'slug' => 'startseite', 'is_home' => true, 'status' => 'published', 'blocks' => [], 'focus_keyword' => 'Bäckerei Köln']);

        $this->actingAs($this->admin)->get(route('admin.seo'))->assertOk()
            ->assertSee('Suchbegriff: Bäckerei Köln')->assertSee('Vergleich mit einem Wettbewerber')->assertSee(url('sitemap.xml'));

        $this->actingAs($this->admin)->postJson(route('admin.seo.analyse'), $this->goodPage())->assertOk()->assertJson(['score' => 100]);
    }

    public function test_fokus_suchbegriff_wird_gespeichert(): void
    {
        $page = Page::create(['title' => 'Start', 'slug' => 'start', 'blocks' => []]);
        $this->actingAs($this->admin)->put(route('admin.pages.update', $page), [
            'title' => 'Start', 'slug' => 'start', 'blocks' => '[]', 'action' => 'save', 'focus_keyword' => '  Bäckerei Köln ',
        ])->assertRedirect();
        $this->assertSame('Bäckerei Köln', $page->fresh()->focus_keyword);
    }

    public function test_wettbewerber_vergleich_mit_ki(): void
    {
        $page = Page::create(['title' => 'Start', 'slug' => 'startseite', 'is_home' => true, 'status' => 'published', 'focus_keyword' => 'Bäckerei Köln',
            'blocks' => [['id' => 'a1', 'type' => 'text', 'data' => ['html' => '<p>Kurzer Text.</p>']]]]);

        Http::fake(['www.mitbewerber.example*' => Http::response('<html><head><title>Bäckerei Köln – Meisterbäcker Schmitz seit 1900</title>'
            .'<meta name="description" content="Ihre Bäckerei in Köln mit Brot, Brötchen und Torten. Jetzt Filialen und Öffnungszeiten ansehen."><meta name="viewport" content="width=device-width">'
            .'<script type="application/ld+json">{"@type":"Bakery"}</script><script>var x = "kein Text";</script></head><body><h1>Bäckerei in Köln</h1><h2>Unser Brot</h2><h2>Filialen</h2>'
            .'<p>'.str_repeat('Brot Brötchen Kuchen ', 200).'</p><img src="a.jpg" alt="Brot"><img src="b.jpg"><a href="/filialen">Filialen</a></body></html>', 200, ['Content-Type' => 'text/html; charset=utf-8'])]);

        Setting::putMany(['ai_provider' => 'anthropic', 'ai_model' => 'claude-opus-5-5', 'ai_key' => AiManager::encryptKey('k')]);
        $driver = new class implements AiDriver
        {
            public string $prompt = '';

            public function complete(string $system, string $prompt, ?array $schema = null, int $maxTokens = 4000): string
            {
                $this->prompt = $prompt;

                return json_encode(['summary' => 'Der Wettbewerber hat viel mehr Text.', 'actions' => [['title' => 'Text ausbauen', 'detail' => 'Schreiben Sie mindestens 400 Wörter.']]]);
            }
        };
        app(AiManager::class)->fake($driver);

        $json = $this->actingAs($this->admin)->postJson(route('admin.seo.compare'), ['page_id' => $page->id, 'url' => 'www.mitbewerber.example'])
            ->assertOk()->json();

        $this->assertSame('https://www.mitbewerber.example', $json['competitor']['url']);
        $this->assertSame(600, $json['competitor']['words'] - $json['competitor']['words'] % 100);
        $this->assertTrue($json['competitor']['schema']);
        $this->assertSame(1, $json['competitor']['images_missing_alt']);
        $this->assertTrue($json['competitor']['keyword_hits']['title']);
        $this->assertStringNotContainsString('kein Text', json_encode($json['competitor']));
        $textRow = collect($json['rows'])->firstWhere('label', 'Textmenge');
        $this->assertSame('competitor', $textRow['winner']);
        $this->assertSame('Text ausbauen', $json['advice']['actions'][0]['title']);
        $this->assertStringContainsString('Meisterbäcker Schmitz', $driver->prompt);
    }

    public function test_vergleich_blockiert_interne_adressen(): void
    {
        $page = Page::create(['title' => 'Start', 'slug' => 'start', 'blocks' => []]);
        Http::fake(['www.weiter.example*' => Http::response('', 302, ['Location' => 'http://intern.example/geheim'])]);

        foreach (['http://intern.example', 'http://127.0.0.1/admin', 'http://10.0.0.1', 'ftp://example.com', 'https://example.com:8080'] as $url) {
            $this->actingAs($this->admin)->postJson(route('admin.seo.compare'), ['page_id' => $page->id, 'url' => $url])->assertStatus(422);
        }
        $this->actingAs($this->admin)->postJson(route('admin.seo.compare'), ['page_id' => $page->id, 'url' => 'https://nirgends.example'])
            ->assertStatus(422)->assertJson(['message' => 'Die Adresse nirgends.example wurde nicht gefunden.']);

        // Weiterleitung ins interne Netz wird ebenfalls gestoppt
        $this->actingAs($this->admin)->postJson(route('admin.seo.compare'), ['page_id' => $page->id, 'url' => 'https://www.weiter.example'])
            ->assertStatus(422)->assertJson(['message' => 'Diese Adresse kann nicht geprüft werden.']);

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'intern.example') || str_contains($r->url(), '127.0.0.1') || str_contains($r->url(), '10.0.0.1') || str_contains($r->url(), 'ftp'));
    }

    public function test_suchbegriffe_vorschlagen(): void
    {
        Setting::putMany(['ai_provider' => 'anthropic', 'ai_model' => 'claude-opus-5-5', 'ai_key' => AiManager::encryptKey('k')]);
        app(AiManager::class)->fake(new class implements AiDriver
        {
            public function complete(string $system, string $prompt, ?array $schema = null, int $maxTokens = 4000): string
            {
                return '{"keywords":["Bäckerei Köln","„Holzofenbrot Köln“","Bäckerei Köln","Sauerteigbrot kaufen"]}';
            }
        });

        $this->actingAs($this->admin)->postJson(route('admin.seo.keywords'), ['title' => 'Start', 'text' => str_repeat('Brot aus dem Holzofen. ', 5)])
            ->assertOk()->assertExactJson(['keywords' => ['Bäckerei Köln', 'Holzofenbrot Köln', 'Sauerteigbrot kaufen']]);
    }
}
