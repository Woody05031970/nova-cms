<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        Setting::putMany(['site_name' => 'Bäckerei Müller', 'company_name' => 'Müller GmbH', 'street' => 'Hauptstr. 1', 'zip_city' => '50667 Köln']);
    }

    /** @param list<array<string, mixed>> $blocks */
    private function save(Page $page, array $blocks, string $action = 'save', array $extra = [])
    {
        return $this->actingAs($this->admin)->put(route('admin.pages.update', $page), array_merge([
            'title' => $page->title,
            'slug' => $page->slug,
            'blocks' => json_encode($blocks),
            'show_in_menu' => 1,
            'is_home' => $page->is_home ? 1 : 0,
            'action' => $action,
        ], $extra));
    }

    public function test_seite_aus_vorlage_anlegen_bearbeiten_und_veroeffentlichen(): void
    {
        $this->actingAs($this->admin)->post(route('admin.pages.store'), ['template' => 'ueber'])->assertRedirect();
        $page = Page::firstWhere('slug', 'ueber-uns');
        $this->assertNotNull($page);
        $this->assertSame('draft', $page->status);

        $this->actingAs($this->admin)->get(route('admin.pages.edit', $page))->assertOk()->assertSee('Bei Google');

        // Entwurf ist öffentlich nicht sichtbar
        $this->get('/ueber-uns')->assertNotFound();

        $this->save($page, [
            ['id' => 'a1', 'type' => 'text', 'data' => ['html' => '<h2>Hallo</h2><p>Wir backen <strong>seit 1952</strong>.</p>']],
            ['id' => 'a2', 'type' => 'button', 'data' => ['label' => 'Anrufen', 'url' => 'tel:0221123', 'style' => 'primary', 'align' => 'center']],
        ], 'publish', ['meta_description' => 'Familienbäckerei in Köln.'])->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertTrue($page->isPublished());
        $this->assertCount(1, $page->revisions);

        $this->get('/ueber-uns')->assertOk()
            ->assertSee('<strong>seit 1952</strong>', false)
            ->assertSee('Familienbäckerei in Köln.')
            ->assertSee('href="tel:0221123"', false)
            ->assertSee('Müller GmbH'); // Footer
    }

    public function test_gefaehrliche_inhalte_werden_entfernt(): void
    {
        $page = Page::create(['title' => 'Test', 'slug' => 'test', 'status' => 'published']);

        $this->save($page, [
            ['id' => 'x1', 'type' => 'text', 'data' => ['html' => '<p onclick="alert(1)">Hallo<script>alert(2)</script><a href="javascript:alert(3)">Link</a></p>']],
            ['id' => 'x2', 'type' => 'image', 'data' => ['url' => 'javascript:alert(4)', 'alt' => '<b>x</b>']],
            ['id' => 'x3', 'type' => 'button', 'data' => ['label' => 'Klick', 'url' => 'javascript:alert(5)']],
            ['id' => 'x4', 'type' => 'unbekannt', 'data' => []],
            ['id' => 'x5', 'type' => 'image', 'data' => ['url' => '/uploads/../../.env']],
        ])->assertSessionHasNoErrors();

        $blocks = $page->fresh()->blocks;
        $this->assertCount(4, $blocks);
        $this->assertStringNotContainsString('script', $blocks[0]['data']['html']);
        $this->assertStringNotContainsString('onclick', $blocks[0]['data']['html']);
        $this->assertStringNotContainsString('javascript', $blocks[0]['data']['html']);
        $this->assertNull($blocks[1]['data']['url']);
        $this->assertSame('x', $blocks[1]['data']['alt']);
        $this->assertNull($blocks[2]['data']['url']);
        $this->assertNull($blocks[3]['data']['url']);
    }

    public function test_startseite_und_menue(): void
    {
        $this->actingAs($this->admin)->post(route('admin.pages.store'), ['template' => 'start']);
        $home = Page::firstWhere('is_home', true);
        $this->save($home, $home->blocks, 'publish');

        $kontakt = Page::create(['title' => 'Kontakt', 'slug' => 'kontakt', 'status' => 'published', 'show_in_menu' => true]);
        Page::create(['title' => 'Geheim', 'slug' => 'geheim', 'status' => 'draft', 'show_in_menu' => true]);

        $this->get('/')->assertOk()->assertSee('Kontakt aufnehmen')->assertSee('href="'.route('page.show', 'kontakt').'"', false)->assertDontSee('Geheim');
        $this->get('/startseite')->assertRedirect('/');

        // Neue Startseite setzen
        $this->save($kontakt, [], 'save', ['is_home' => 1]);
        $this->assertTrue($kontakt->fresh()->is_home);
        $this->assertFalse($home->fresh()->is_home);
    }

    public function test_video_wird_erst_nach_klick_geladen(): void
    {
        $page = Page::create(['title' => 'Video', 'slug' => 'video', 'status' => 'published']);
        $this->save($page, [['id' => 'v1', 'type' => 'video', 'data' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']]]);

        $this->assertSame('youtube', $page->fresh()->blocks[0]['data']['provider']);
        $this->get('/video')->assertOk()
            ->assertSee('data-src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertDontSee('<iframe', false);
    }

    public function test_versionen_wiederherstellen(): void
    {
        $page = Page::create(['title' => 'Version 1', 'slug' => 'v', 'blocks' => []]);
        $this->save($page, [], 'save', ['title' => 'Version 2']);
        $this->save($page->fresh(), [], 'save', ['title' => 'Version 3']);

        $rev = $page->revisions()->where('title', 'Version 1')->first();
        $this->actingAs($this->admin)->post(route('admin.pages.revisions.restore', [$page, $rev]))->assertRedirect();
        $this->assertSame('Version 1', $page->fresh()->title);
    }

    public function test_papierkorb(): void
    {
        $page = Page::create(['title' => 'Alt', 'slug' => 'alt', 'status' => 'published']);

        $this->actingAs($this->admin)->delete(route('admin.pages.destroy', $page));
        $this->get('/alt')->assertNotFound();
        $this->actingAs($this->admin)->get(route('admin.pages.index', ['papierkorb' => 1]))->assertSee('Alt');

        $this->actingAs($this->admin)->post(route('admin.pages.restore', $page->id));
        $this->get('/alt')->assertOk();

        $page->delete();
        $this->actingAs($this->admin)->delete(route('admin.pages.force-delete', $page->id));
        $this->assertNull(Page::withTrashed()->find($page->id));
    }

    public function test_adressen_sind_eindeutig_und_schuetzen_systempfade(): void
    {
        Page::create(['title' => 'Kontakt', 'slug' => 'kontakt']);
        $this->assertSame('kontakt-2', Page::uniqueSlug('Kontakt'));
        $this->assertSame('admin-seite', Page::uniqueSlug('Admin'));
        $this->assertSame('ueber-uns', Page::uniqueSlug('Über uns'));
    }

    public function test_impressum_vorlage_nutzt_firmenprofil(): void
    {
        $this->actingAs($this->admin)->post(route('admin.pages.store'), ['template' => 'impressum']);
        $page = Page::firstWhere('purpose', 'imprint');
        $this->assertStringContainsString('Müller GmbH', $page->blocks[0]['data']['html']);
        $this->assertStringContainsString('50667 Köln', $page->blocks[0]['data']['html']);
        $this->assertFalse($page->show_in_menu);

        $this->actingAs($this->admin)->get('/admin')->assertSee('Impressum veröffentlichen');
        $this->save($page, $page->blocks, 'publish');
        $this->actingAs($this->admin)->get('/admin')->assertDontSee('Impressum veröffentlichen');
    }

    public function test_bild_hochladen(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.uploads.store'), [
            'file' => UploadedFile::fake()->image('Unser Laden.jpg', 800, 600),
            'kind' => 'image',
        ], ['Accept' => 'application/json'])->assertOk();

        $url = $response->json('url');
        $this->assertMatchesRegularExpression('#^/uploads/\d{4}/\d{2}/unser-laden-[a-z0-9]{6}\.jpg$#', $url);
        $this->assertFileExists(public_path($url));
        File::delete(public_path($url));
    }

    public function test_php_dateien_koennen_nicht_hochgeladen_werden(): void
    {
        $this->actingAs($this->admin)->post(route('admin.uploads.store'), [
            'file' => UploadedFile::fake()->createWithContent('bild.php', '<?php echo 1;'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_vorschau_eines_entwurfs_nur_fuer_angemeldete(): void
    {
        $page = Page::create(['title' => 'Entwurf', 'slug' => 'entwurf', 'status' => 'draft', 'blocks' => []]);
        $this->get(route('admin.pages.preview', $page))->assertRedirect(route('login'));
        $this->actingAs($this->admin)->get(route('admin.pages.preview', $page))->assertOk()->assertSee('noch ein Entwurf');
    }
}
