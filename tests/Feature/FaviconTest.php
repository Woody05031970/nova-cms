<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Design\Favicon;
use App\Support\Design\SiteDesign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FaviconTest extends TestCase
{
    use RefreshDatabase;

    private array $base = ['theme' => 'klar', 'brand' => '#123456', 'radius' => 'soft', 'header' => 'light', 'footer' => 'light'];

    protected function tearDown(): void
    {
        Favicon::remove(Favicon::current());
        parent::tearDown();
    }

    public function test_favicon_hochladen_einbinden_und_entfernen(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->get('/')->assertDontSee('apple-touch-icon', false);

        $this->actingAs($admin)->put('/admin/design', $this->base + ['favicon_file' => UploadedFile::fake()->image('zeichen.png', 600, 300)])
            ->assertSessionHasNoErrors()->assertRedirect();

        $base = Favicon::current();
        $this->assertMatchesRegularExpression('#^/uploads/favicon/[a-z0-9]{12}$#', $base);
        foreach ([32, 180, 192, 512] as $size) {
            [$w, $h] = getimagesize(public_path(ltrim($base, '/')."/$size.png"));
            $this->assertSame([$size, $size], [$w, $h]);
        }
        $ico = file_get_contents(public_path(ltrim($base, '/').'/favicon.ico'));
        $this->assertSame("\0\0\1\0\1\0", substr($ico, 0, 6));

        auth()->logout();
        $this->get('/')->assertOk()->assertSee($base.'/favicon.ico', false)->assertSee('apple-touch-icon', false);
        $this->actingAs($admin)->get('/admin/design')->assertOk()->assertSee('Favicon hochladen');

        // Neues Favicon ersetzt das alte, der alte Ordner verschwindet
        $this->put('/admin/design', $this->base + ['favicon_file' => UploadedFile::fake()->image('neu.jpg', 512, 512)])->assertSessionHasNoErrors();
        $this->assertNotSame($base, Favicon::current());
        $this->assertDirectoryDoesNotExist(public_path(ltrim($base, '/')));

        $this->put('/admin/design', $this->base + ['remove_favicon' => '1'])->assertSessionHasNoErrors();
        $this->assertNull(Favicon::current());
        $this->assertNull(Setting::get('design_favicon'));
    }

    public function test_ungueltige_favicons_werden_abgelehnt(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put('/admin/design', $this->base + ['favicon_file' => UploadedFile::fake()->image('klein.png', 16, 16)])->assertSessionHasErrors('favicon_file');
        $this->put('/admin/design', $this->base + ['favicon_file' => UploadedFile::fake()->create('x.svg', 2, 'image/svg+xml')])->assertSessionHasErrors('favicon_file');

        // Manipulierte Einstellung wird nicht ausgegeben
        Setting::put('design_favicon', '/uploads/../../.env');
        $this->assertNull(Favicon::current());
        $this->assertSame('', Favicon::tags());
    }

    public function test_vorlage_nova_in_logo_farben(): void
    {
        Setting::putMany(['design_theme' => 'nova', 'design_header' => 'dark']);
        $v = SiteDesign::values();
        $this->assertSame('nova', $v['theme']);
        $css = SiteDesign::css($v);
        $this->assertStringContainsString('--color-nova-600:#0a4b5a', $css);
        $this->assertStringContainsString('--color-fuchsia-600:#f97a12', $css);
        $this->assertGreaterThanOrEqual(4.5, SiteDesign::contrast('#0a4b5a', '#ffffff'));
    }
}
