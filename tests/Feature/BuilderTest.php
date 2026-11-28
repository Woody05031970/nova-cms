<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Support\Blocks\BlockSanitizer;
use App\Support\Blocks\Builder;
use App\Support\Icons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Visueller Editor: Bereiche mit Spalten und Elementen */
class BuilderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function section(string $layout, array $cols, array $style = []): array
    {
        return ['id' => 'sec1', 'type' => 'section', 'data' => ['layout' => $layout, 'style' => $style, 'cols' => $cols]];
    }

    public function test_bereich_wird_bereinigt_und_spalten_passen_zum_layout(): void
    {
        $blocks = app(BlockSanitizer::class)->clean([$this->section('1-1-1', [
            ['id' => 'c1', 'widgets' => [['id' => 'w1', 'type' => 'heading', 'data' => ['text' => 'Hallo <b>Welt</b>'], 'style' => ['tag' => 'h1', 'color' => 'javascript:alert(1)']]]],
            ['id' => 'c2', 'widgets' => [['id' => 'w2', 'type' => 'boese', 'data' => []]]],
        ], ['bg' => 'color', 'bg_color' => '#FF0000', 'pt' => 9999, 'anchor' => 'Kontakt!'])]);

        $s = $blocks[0]['data'];
        $this->assertSame('1-1-1', $s['layout']);
        $this->assertCount(3, $s['cols'], 'Fehlende Spalte wird ergänzt');
        $this->assertSame([], $s['cols'][1]['widgets'], 'Unbekannte Elemente fallen weg');
        $this->assertSame('#ff0000', $s['style']['bg_color']);
        $this->assertSame(240, $s['style']['pt']);
        $this->assertSame('', $s['style']['anchor']);
        $w = $s['cols'][0]['widgets'][0];
        $this->assertSame('h1', $w['style']['tag']);
        $this->assertNull($w['style']['color']);

        // Zu viele Spalten: Elemente landen in der letzten Spalte statt zu verschwinden
        $merged = app(BlockSanitizer::class)->clean([$this->section('1-1', [
            ['widgets' => [['type' => 'spacer']]], ['widgets' => [['type' => 'divider']]], ['widgets' => [['type' => 'heading', 'data' => ['text' => 'Rest']]]],
        ])])[0]['data'];
        $this->assertCount(2, $merged['cols']);
        $this->assertSame(['divider', 'heading'], array_column($merged['cols'][1]['widgets'], 'type'));
    }

    public function test_html_element_nur_wenn_erlaubt(): void
    {
        $block = $this->section('1', [['widgets' => [['type' => 'html', 'data' => ['code' => '<p>x</p>']], ['type' => 'text', 'data' => ['html' => '<p>ok</p><script>x</script>']]]]]);

        $without = app(BlockSanitizer::class)->clean([$block], false)[0]['data']['cols'][0]['widgets'];
        $this->assertSame(['text'], array_column($without, 'type'));
        $this->assertStringNotContainsString('<script', $without[0]['data']['html']);

        $with = app(BlockSanitizer::class)->clean([$block])[0]['data']['cols'][0]['widgets'];
        $this->assertSame(['html', 'text'], array_column($with, 'type'));
    }

    public function test_bereich_erscheint_auf_der_website_und_ersetzt_den_seitentitel(): void
    {
        $blocks = Page::cleanBlocks([$this->section('1-1', [
            ['widgets' => [
                ['type' => 'heading', 'data' => ['text' => 'Frisch gebacken'], 'style' => ['tag' => 'h1']],
                ['type' => 'list', 'data' => ['items' => ['Brot', 'Brötchen']]],
                ['type' => 'button', 'data' => ['label' => 'Anrufen', 'url' => 'tel:0221123']],
            ]],
            ['widgets' => [['type' => 'counter', 'data' => ['number' => '70', 'suffix' => '+', 'label' => 'Jahre']]]],
        ], ['bg' => 'color', 'bg_color' => 'brand'])]);
        Page::create(['title' => 'Start', 'slug' => 'start', 'blocks' => $blocks, 'status' => 'published', 'published_at' => now(), 'show_title' => true]);

        $html = $this->get('/start')->assertOk()->getContent();
        $this->assertStringContainsString('nb-section', $html);
        $this->assertStringContainsString('Frisch gebacken', $html);
        $this->assertStringContainsString('Brötchen', $html);
        $this->assertStringContainsString('tel:0221123', $html);
        $this->assertStringContainsString('70', $html);
        $this->assertSame(1, substr_count($html, '<h1'), 'Seitentitel entfällt, wenn der erste Bereich eine H1 hat');
        $this->assertTrue(Builder::startsWithH1($blocks));
    }

    public function test_seitentitel_laesst_sich_ausblenden(): void
    {
        $page = Page::create(['title' => 'Über uns', 'slug' => 'ueber-uns', 'blocks' => [], 'status' => 'draft']);
        $this->actingAs($this->admin)->put(route('admin.pages.update', $page), [
            'title' => 'Über uns', 'slug' => 'ueber-uns', 'show_in_menu' => 1, 'action' => 'publish', 'show_title' => 0,
            'blocks' => json_encode([$this->section('1', [['widgets' => [['type' => 'text', 'data' => ['html' => '<p>Wir backen seit 1952.</p>']]]]])]),
        ])->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertFalse($page->show_title);
        $this->assertSame('section', $page->blocks[0]['type']);
        $this->assertStringContainsString('seit 1952', $page->plainText());
        $this->get('/ueber-uns')->assertOk()->assertSee('seit 1952')->assertDontSee('<h1', false);
    }

    public function test_flatten_liefert_texte_fuer_seo(): void
    {
        $flat = Builder::flatten(Page::cleanBlocks([$this->section('1-1', [
            ['widgets' => [['type' => 'heading', 'data' => ['text' => 'Unser Team'], 'style' => ['tag' => 'h2']]]],
            ['widgets' => [['type' => 'image', 'data' => ['url' => '/uploads/a.jpg', 'alt' => 'Backstube']], ['type' => 'spacer']]],
        ])]));

        $this->assertSame(['text', 'image'], array_column($flat, 'type'));
        $this->assertSame('<h2>Unser Team</h2>', $flat[0]['data']['html']);
        $this->assertSame('Backstube', $flat[1]['data']['alt']);
    }

    public function test_leinwand_und_baustein_vorschau(): void
    {
        $page = Page::create(['title' => 'Test', 'slug' => 'test', 'blocks' => [], 'status' => 'draft']);
        $this->get(route('admin.pages.canvas', $page))->assertRedirect();
        $this->actingAs($this->admin)->get(route('admin.pages.canvas', $page))->assertOk()->assertSee('nbx-root', false);
        $this->actingAs($this->admin)->get(route('admin.pages.edit', $page))->assertOk()->assertSee(route('admin.pages.canvas', $page), false);

        $this->actingAs($this->admin)->post(route('admin.pages.block-preview'), [
            'page' => $page->id, 'block' => json_encode(['id' => 'b1', 'type' => 'faq', 'data' => ['title' => 'Häufige Fragen', 'items' => [['q' => 'Wann offen?', 'a' => 'Ab 6 Uhr']]]]),
        ])->assertOk()->assertSee('Wann offen?');

        // Bereiche zeichnet die Leinwand selbst
        $this->actingAs($this->admin)->post(route('admin.pages.block-preview'), ['page' => $page->id, 'block' => json_encode($this->section('1', []))])->assertStatus(422);
    }

    public function test_eigene_vorlagen_speichern_und_loeschen(): void
    {
        $res = $this->actingAs($this->admin)->postJson(route('admin.pages.templates'), [
            'name' => 'Mein <b>Kopf</b>', 'block' => json_encode($this->section('1-2', [['widgets' => [['type' => 'heading', 'data' => ['text' => 'Hallo']]]]])),
        ])->assertOk();
        $list = $res->json('templates');
        $this->assertCount(1, $list);
        $this->assertSame('Mein Kopf', $list[0]['name']);
        $this->assertSame('1-2', $list[0]['block']['data']['layout']);

        $this->actingAs($this->admin)->postJson(route('admin.pages.templates'), ['name' => 'x', 'block' => json_encode(['type' => 'text', 'data' => []])])->assertStatus(422);

        $this->actingAs($this->admin)->deleteJson(route('admin.pages.templates.destroy', $list[0]['id']))->assertOk()->assertJsonPath('templates', []);
    }

    public function test_alle_auswaehlbaren_symbole_existieren(): void
    {
        $this->assertSame([], array_values(array_diff(BlockSanitizer::ICONS, array_keys(Icons::PATHS))));
    }
}
