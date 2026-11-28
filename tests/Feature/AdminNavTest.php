<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Plugins\Hooks;
use App\Support\Admin\AdminNav;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Linke Seitenleiste der Verwaltung: aufklappbare Kategorien und eigene Reihenfolge */
class AdminNavTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_seitenleiste_zeigt_kategorien_und_oeffnet_die_aktuelle(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.seo'))->assertOk()->getContent();

        foreach (['Website', 'Kommunikation', 'Verkaufen', 'Sichtbarkeit &amp; KI', 'System'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        // Nur die Kategorie mit der aktuellen Seite ist offen
        $this->assertMatchesRegularExpression('/data-nav-group="sichtbarkeit"\s+data-nav-current/', $html);
        $this->assertSame(1, substr_count($html, 'aria-expanded="true" aria-controls="nav-'));
        $this->assertStringContainsString(route('admin.nav'), $html);
    }

    public function test_reihenfolge_kategorie_name_und_ausblenden_speichern(): void
    {
        $tree = AdminNav::tree(true);
        $groups = collect($tree['groups'])->map(fn ($g) => ['key' => $g['key'], 'label' => $g['label'], 'items' => array_map(fn ($i) => ['key' => $i['key'], 'hidden' => false], $g['items'])])->all();
        // System nach oben, umbenennen, „Shop“ in die Kategorie Website ganz nach vorn, „Turbo“ ausblenden
        $system = collect($groups)->firstWhere('key', 'system');
        $system['label'] = 'Technik';
        $system['items'] = array_map(fn ($i) => $i['key'] === 'turbo' ? ['key' => 'turbo', 'hidden' => true] : $i, $system['items']);
        $groups = array_values(array_filter($groups, fn ($g) => $g['key'] !== 'system'));
        array_unshift($groups, $system);
        foreach ($groups as &$g) {
            $g['items'] = array_values(array_filter($g['items'], fn ($i) => $i['key'] !== 'shop'));
            if ($g['key'] === 'website') {
                array_unshift($g['items'], ['key' => 'shop', 'hidden' => false]);
            }
        }
        unset($g);

        $this->actingAs($this->admin)->put(route('admin.nav.update'), ['layout' => json_encode($groups), 'single' => 0])
            ->assertRedirect(route('admin.nav'))->assertSessionHas('status');

        $tree = AdminNav::tree();
        $this->assertSame('system', $tree['groups'][0]['key']);
        $this->assertSame('Technik', $tree['groups'][0]['label']);
        $this->assertNotContains('turbo', array_column($tree['groups'][0]['items'], 'key'));
        $website = collect($tree['groups'])->firstWhere('key', 'website');
        $this->assertSame('shop', $website['items'][0]['key']);
        $this->assertFalse($tree['single']);

        $html = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('Technik', $html);
        $this->assertStringContainsString('data-nav-single="0"', $html);
        // Ausgeblendet heißt: nicht in der Leiste, aber in der Schnellsuche
        $this->assertStringNotContainsString('href="'.route('admin.turbo').'"', $html);
        $this->assertStringContainsString(json_encode(route('admin.turbo')), $html);

        $this->actingAs($this->admin)->get(route('admin.nav'))->assertOk()->assertSee('Backoffice-Menü anpassen')->assertSee('Ursprüngliche Reihenfolge');

        $this->actingAs($this->admin)->delete(route('admin.nav.reset'))->assertRedirect();
        $this->assertSame('website', AdminNav::tree()['groups'][0]['key']);
        $this->assertNull(Setting::get(AdminNav::SETTING));
    }

    public function test_unbekannte_eintraege_werden_ignoriert(): void
    {
        AdminNav::save([
            ['key' => 'gibtsnicht', 'items' => [['key' => 'pages']]],
            ['key' => 'website', 'label' => '<b>Meine Seite</b>', 'items' => [['key' => 'boese'], ['key' => 'dashboard'], ['key' => 'media']]],
        ], true);

        $custom = AdminNav::custom();
        $this->assertSame(['website'], $custom['groups']);
        $this->assertSame('Meine Seite', $custom['labels']['website']);
        $this->assertSame(['media'], array_keys($custom['items']));
        // Nicht erwähnte Punkte bleiben in ihrer Kategorie, das Dashboard steht immer oben
        $tree = AdminNav::tree();
        $this->assertSame('dashboard', $tree['top'][0]['key']);
        $this->assertSame('media', collect($tree['groups'])->firstWhere('key', 'website')['items'][0]['key']);
        $this->assertNotNull(collect($tree['groups'])->firstWhere('key', 'system'));
    }

    public function test_plugin_punkte_landen_unter_erweiterungen_und_sind_sortierbar(): void
    {
        app(Hooks::class)->addMenuItem('Mein Plugin', '/admin/mein-plugin', 'puzzle');
        $tree = AdminNav::tree();
        $ext = collect($tree['groups'])->firstWhere('key', 'erweiterungen');
        $this->assertSame('Mein Plugin', $ext['items'][0]['label']);
        $key = $ext['items'][0]['key'];

        AdminNav::save([['key' => 'website', 'items' => [['key' => $key]]]], true);
        $website = collect(AdminNav::tree()['groups'])->firstWhere('key', 'website');
        $this->assertSame('Mein Plugin', $website['items'][0]['label']);
        $this->assertNull(collect(AdminNav::tree()['groups'])->firstWhere('key', 'erweiterungen'), 'Leere Kategorien erscheinen nicht');
    }
}
