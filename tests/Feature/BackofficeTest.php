<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Blocks\BlockSanitizer;
use App\Support\Update\AutoUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BackofficeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function page(string $title, array $attrs = []): Page
    {
        return Page::create(['title' => $title, 'slug' => Page::uniqueSlug($title), 'status' => 'published', 'show_in_menu' => true,
            'blocks' => [['id' => 'a', 'type' => 'text', 'data' => ['html' => '<p>'.$title.'</p>']]], ...$attrs]);
    }

    public function test_seiten_mit_menue_haken_landen_automatisch_im_menue(): void
    {
        $a = $this->page('Leistungen');
        $b = $this->page('Versteckt', ['show_in_menu' => false]);

        $this->assertTrue(MenuItem::where('page_id', $a->id)->exists());
        $this->assertFalse(MenuItem::where('page_id', $b->id)->exists());

        $a->update(['show_in_menu' => false]);
        $this->assertFalse(MenuItem::where('page_id', $a->id)->exists());
    }

    public function test_menue_mit_unterpunkten_speichern_und_anzeigen(): void
    {
        $home = $this->page('Start', ['is_home' => true]);
        $brot = $this->page('Brot');
        $kuchen = $this->page('Kuchen');
        $entwurf = $this->page('Geheim', ['status' => 'draft']);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/menue')->assertOk()->assertSee('Menü speichern')->assertSee('Brot');

        $items = MenuItem::pluck('id', 'page_id');
        $tree = [
            ['id' => $items[$home->id], 'type' => 'page', 'page_id' => $home->id, 'label' => '', 'is_visible' => true, 'children' => []],
            ['id' => null, 'type' => 'link', 'label' => 'Sortiment', 'url' => '/brot', 'is_visible' => true, 'children' => [
                ['id' => $items[$brot->id], 'type' => 'page', 'page_id' => $brot->id, 'label' => 'Unser Brot', 'is_visible' => true],
                ['id' => $items[$kuchen->id], 'type' => 'page', 'page_id' => $kuchen->id, 'is_visible' => true],
                ['id' => $items[$entwurf->id], 'type' => 'page', 'page_id' => $entwurf->id, 'is_visible' => true],
            ]],
            ['id' => null, 'type' => 'link', 'label' => 'Instagram', 'url' => 'instagram.com/sonnenkorn', 'new_tab' => true, 'is_visible' => true, 'children' => []],
            ['id' => null, 'type' => 'link', 'label' => 'Versteckt', 'url' => '/x', 'is_visible' => false, 'children' => []],
        ];
        $this->actingAs($admin)->putJson('/admin/menue', ['tree' => json_encode($tree)])->assertOk()->assertJsonPath('ok', true);

        $sortiment = MenuItem::where('label', 'Sortiment')->sole();
        $this->assertSame([$brot->id, $kuchen->id, $entwurf->id], MenuItem::where('parent_id', $sortiment->id)->orderBy('position')->pluck('page_id')->all());
        $this->assertSame('https://instagram.com/sonnenkorn', MenuItem::where('label', 'Instagram')->value('url'));

        $html = $this->get('/kuchen')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Sortiment.*Unser Brot.*Kuchen.*Instagram/s', $html);
        $this->assertStringNotContainsString('Geheim', $html);
        $this->assertStringNotContainsString('Versteckt', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        // Aktiver Unterpunkt markiert auch den Hauptpunkt
        $this->assertMatchesRegularExpression('/data-has-submenu>\s*<a href="[^"]*\/brot"[^>]*aria-current="page"/', $html);

        // Seite aus dem Menü genommen: Haken an der Seite wird entfernt
        $tree[1]['children'] = array_slice($tree[1]['children'], 0, 1);
        $this->actingAs($admin)->putJson('/admin/menue', ['tree' => json_encode($tree)])->assertOk();
        $this->assertFalse($kuchen->fresh()->show_in_menu);
        $this->assertTrue($brot->fresh()->show_in_menu);
    }

    public function test_ungueltige_links_werden_abgelehnt(): void
    {
        $tree = [['type' => 'link', 'label' => 'Böse', 'url' => 'javascript:alert(1)', 'children' => []]];
        $this->actingAs($this->admin())->putJson('/admin/menue', ['tree' => json_encode($tree)])
            ->assertStatus(422)->assertJsonValidationErrors('tree');
        $this->assertSame(0, MenuItem::where('type', 'link')->count());
    }

    public function test_shop_erscheint_im_menue_wenn_eingeschaltet(): void
    {
        $this->page('Start', ['is_home' => true]);
        $this->get('/')->assertDontSee('>Shop<', false);

        Setting::put('shop_enabled', '1');
        $this->get('/')->assertSee('>Shop<', false);
    }

    public function test_texteditor_formate_bleiben_erhalten_skripte_nicht(): void
    {
        $clean = app(BlockSanitizer::class)->html(
            '<h2 style="text-align: center; color: red">Titel</h2><p>Mit <mark>Marker</mark></p>'
            .'<table><tbody><tr><th colspan="2"><p>Kopf</p></th></tr><tr><td><p>a</p></td><td><p>b</p></td></tr></tbody></table>'
            .'<img src="/uploads/2026/brot.jpg" alt="Brot"><img src="javascript:alert(1)"><script>alert(1)</script><p onclick="x()">Text</p>'
        );

        $this->assertStringContainsString('<h2 style="text-align:center;">Titel</h2>', $clean);
        $this->assertStringContainsString('<mark>Marker</mark>', $clean);
        $this->assertStringContainsString('<th colspan="2">', $clean);
        $this->assertStringContainsString('<img src="/uploads/2026/brot.jpg" alt="Brot"', $clean);
        $this->assertStringNotContainsString('javascript', $clean);
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('color', $clean);
    }

    public function test_live_vorschau_zeigt_ungespeicherten_stand(): void
    {
        $page = $this->page('Kontakt');
        $blocks = [['id' => 'x', 'type' => 'text', 'data' => ['html' => '<p>Noch nicht gespeichert</p><script>evil()</script>']]];

        $this->actingAs($this->admin())->post('/admin/seiten/live-vorschau', ['page' => $page->id, 'title' => 'Neuer Titel', 'blocks' => json_encode($blocks)])
            ->assertOk()->assertSee('Noch nicht gespeichert')->assertSee('Neuer Titel')->assertDontSee('evil()', false);

        $this->assertSame('Kontakt', $page->fresh()->title);
        $this->assertStringContainsString('Kontakt', $page->fresh()->blocks[0]['data']['html']);
    }

    public function test_schnellsuche(): void
    {
        $page = $this->page('Hochzeitstorten');
        Product::create(['name' => 'Torte Erdbeer', 'slug' => 'torte', 'sku' => 'T-1', 'price_cents' => 2500, 'tax_rate' => 7, 'is_active' => true]);
        $admin = $this->admin();

        $this->actingAs($admin)->getJson('/admin/suche?q=tort')->assertOk()
            ->assertJsonFragment(['label' => 'Hochzeitstorten', 'url' => route('admin.pages.edit', $page), 'group' => 'Seiten'])
            ->assertJsonFragment(['label' => 'Torte Erdbeer', 'group' => 'Produkte']);
        $this->actingAs($admin)->getJson('/admin/suche?q=t')->assertExactJson([]);
        Auth::logout();
        $this->getJson('/admin/suche?q=tort')->assertUnauthorized();
    }

    public function test_neues_layout_der_verwaltung(): void
    {
        $this->actingAs($this->admin())->get('/admin')->assertOk()
            ->assertSee('id="palette"', false)->assertSee('Strg K')->assertSee('Website')->assertSee('Menü anpassen');
    }

    public function test_update_spielt_datenbank_aenderungen_automatisch_ein(): void
    {
        @unlink(AutoUpdate::markerPath());
        $this->assertNull(AutoUpdate::installedVersion());

        $this->assertTrue(AutoUpdate::run());
        $this->assertSame(config('nova.version'), AutoUpdate::installedVersion());
        $this->assertTrue(Schema::hasTable('menu_items'));
        @unlink(AutoUpdate::markerPath());
    }

    public function test_update_leert_alte_zwischenspeicher(): void
    {
        $routes = app()->getCachedRoutesPath();
        $view = storage_path('framework/views/nova-alt-test.php');
        file_put_contents($routes, '<?php // alte Version');
        file_put_contents($view, 'alt');
        try {
            @unlink(AutoUpdate::markerPath());
            $this->assertTrue(AutoUpdate::run());
            $this->assertFileDoesNotExist($routes);
            $this->assertFileDoesNotExist($view);
        } finally {
            @unlink($routes);
            @unlink($view);
            @unlink(AutoUpdate::markerPath());
        }
    }

    public function test_update_laeuft_auch_mit_altem_einstellungs_zwischenspeicher(): void
    {
        // Ein alter config:cache meldet noch die alte Versionsnummer
        config(['nova.version' => '0.0.1']);
        file_put_contents(AutoUpdate::markerPath(), '0.0.1');
        try {
            $this->assertSame((require base_path('config/nova.php'))['version'], AutoUpdate::codeVersion());
            $this->assertTrue(AutoUpdate::run());
            $this->assertSame(AutoUpdate::codeVersion(), AutoUpdate::installedVersion());
            $this->assertNotSame('0.0.1', AutoUpdate::installedVersion());
        } finally {
            @unlink(AutoUpdate::markerPath());
        }
    }
}
