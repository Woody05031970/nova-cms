<?php

namespace Tests\Feature;

use App\Ai\AiDriver;
use App\Ai\AiManager;
use App\Ai\AiVision;
use App\Ai\Drivers\AnthropicDriver;
use App\Models\Media;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        Setting::put('site_name', 'Bäckerei Müller');
    }

    private function upload(UploadedFile $file, string $kind = 'image')
    {
        return $this->actingAs($this->admin)->post(route('admin.uploads.store'), ['file' => $file, 'kind' => $kind]);
    }

    public function test_upload_landet_in_der_mediathek_mit_vorschaubild(): void
    {
        $json = $this->upload(UploadedFile::fake()->image('Unser Laden.jpg', 1200, 800))->assertOk()->json();

        $media = Media::sole();
        $this->assertSame($media->url(), $json['url']);
        $this->assertSame([1200, 800], [$media->width, $media->height]);
        $this->assertSame($this->admin->id, $media->user_id);
        $this->assertSame('Unser Laden.jpg', $media->original_name);
        $this->assertFileExists(public_path($media->path));

        // Vorschaubild als WebP, höchstens 480 Pixel
        $this->assertStringEndsWith('.webp', $media->thumb);
        [$w, $h] = getimagesize(public_path($media->thumb));
        $this->assertSame([480, 320], [$w, $h]);
        $this->assertSame('/'.$media->thumb, $json['thumb']);
    }

    public function test_grosse_fotos_werden_verkleinert(): void
    {
        $this->upload(UploadedFile::fake()->image('handy.jpg', 4000, 3000))->assertOk();

        $media = Media::sole();
        $this->assertSame([2400, 1800], [$media->width, $media->height]);
        $this->assertSame([2400, 1800], array_slice(getimagesize(public_path($media->path)), 0, 2));
    }

    public function test_kleine_bilder_brauchen_kein_vorschaubild(): void
    {
        $this->upload(UploadedFile::fake()->image('logo.png', 300, 100))->assertOk();
        $this->assertNull(Media::sole()->thumb);
        $this->assertSame('/'.Media::sole()->path, Media::sole()->thumbUrl());
    }

    public function test_videos_und_falsche_dateien(): void
    {
        $this->upload(UploadedFile::fake()->create('film.mp4', 500, 'video/mp4'), 'video')->assertOk();
        $this->assertSame('video', Media::sole()->kind);

        $this->upload(UploadedFile::fake()->createWithContent('bild.php', '<?php echo 1;'))->assertSessionHasErrors('file');
        $this->assertSame(1, Media::count());
    }

    public function test_vorhandene_dateien_werden_uebernommen(): void
    {
        File::ensureDirectoryExists(public_path('uploads/2026/10'));
        imagejpeg(imagecreatetruecolor(800, 600), public_path('uploads/2026/10/alt.jpg'));
        file_put_contents(public_path('uploads/2026/10/notiz.txt'), 'x');

        $this->actingAs($this->admin)->get(route('admin.media.index'))->assertOk()->assertSee('alt.jpg');

        $this->assertSame(['uploads/2026/10/alt.jpg'], Media::pluck('path')->all());
        $this->assertNotNull(Media::sole()->thumb);

        // Zweiter Aufruf legt nichts doppelt an (auch nicht das Vorschaubild)
        $this->actingAs($this->admin)->get(route('admin.media.index'))->assertOk();
        $this->assertSame(1, Media::count());
    }

    public function test_beschreibung_speichern_und_verwendung_anzeigen(): void
    {
        $url = $this->upload(UploadedFile::fake()->image('brot.jpg', 800, 600))->json('url');
        $media = Media::sole();

        $this->actingAs($this->admin)->patchJson(route('admin.media.update', $media), ['alt' => 'Frisches Brot', 'title' => 'Brot'])
            ->assertOk()->assertJson(['alt' => 'Frisches Brot', 'name' => 'Brot']);

        Page::create(['title' => 'Startseite', 'slug' => 'start', 'blocks' => [['id' => 'a1', 'type' => 'image', 'data' => ['url' => $url, 'alt' => '']]]]);
        Page::create(['title' => 'Andere', 'slug' => 'andere', 'blocks' => []]);

        $this->actingAs($this->admin)->getJson(route('admin.media.show', $media))
            ->assertOk()->assertJsonCount(1, 'usages')->assertJsonPath('usages.0.title', 'Startseite');

        $this->actingAs($this->admin)->getJson(route('admin.media.list', ['art' => 'image']))->assertOk()->assertJsonPath('0.alt', 'Frisches Brot');
        $this->actingAs($this->admin)->getJson(route('admin.media.list', ['art' => 'video']))->assertOk()->assertJsonCount(0);
    }

    public function test_loeschen_warnt_bei_verwendung(): void
    {
        $url = $this->upload(UploadedFile::fake()->image('brot.jpg', 800, 600))->json('url');
        $media = Media::sole();
        $thumb = public_path($media->thumb);
        Page::create(['title' => 'Startseite', 'slug' => 'start', 'blocks' => [['id' => 'a1', 'type' => 'hero', 'data' => ['image' => $url]]]]);

        $this->actingAs($this->admin)->deleteJson(route('admin.media.destroy', $media))
            ->assertStatus(409)->assertJsonFragment(['in_use' => true]);
        $this->assertFileExists(public_path($media->path));

        $this->actingAs($this->admin)->deleteJson(route('admin.media.destroy', $media).'?trotzdem=1')->assertOk();
        $this->assertFileDoesNotExist(public_path($media->path));
        $this->assertFileDoesNotExist($thumb);
        $this->assertSame(0, Media::count());
    }

    public function test_bildbeschreibung_per_ki(): void
    {
        Setting::putMany(['ai_provider' => 'anthropic', 'ai_model' => 'claude-opus-5-5', 'ai_key' => AiManager::encryptKey('k')]);
        $driver = new class implements AiDriver, AiVision
        {
            public array $seen = [];

            public function complete(string $system, string $prompt, ?array $schema = null, int $maxTokens = 4000): string
            {
                return '';
            }

            public function describeImage(string $system, string $instruction, string $data, string $mime): string
            {
                $this->seen = compact('system', 'mime') + ['bytes' => strlen($data)];

                return '„Frisch gebackene Brote auf einem Holzregal."';
            }
        };
        app(AiManager::class)->fake($driver);

        $this->upload(UploadedFile::fake()->image('brot.jpg', 1200, 800));
        $media = Media::sole();

        $this->actingAs($this->admin)->postJson(route('admin.media.describe', $media))
            ->assertOk()->assertJson(['alt' => 'Frisch gebackene Brote auf einem Holzregal.']);

        $this->assertSame('Frisch gebackene Brote auf einem Holzregal.', $media->fresh()->alt);
        $this->assertSame('image/webp', $driver->seen['mime'], 'Das Vorschaubild genügt');
        $this->assertStringContainsString('Alternativtext', $driver->seen['system']);
    }

    public function test_bildbeschreibung_ueber_openai_schickt_das_bild_mit(): void
    {
        Setting::putMany(['ai_provider' => 'openai', 'ai_model' => 'gpt-5-mini', 'ai_key' => AiManager::encryptKey('sk')]);
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'Ein Laden von außen.'], 'finish_reason' => 'stop']]])]);

        $this->upload(UploadedFile::fake()->image('laden.png', 300, 200));

        $this->actingAs($this->admin)->postJson(route('admin.media.describe', Media::sole()))->assertOk()->assertJson(['alt' => 'Ein Laden von außen.']);
        Http::assertSent(fn ($r) => str_starts_with($r['messages'][1]['content'][0]['image_url']['url'] ?? '', 'data:image/png;base64,'));
    }

    public function test_bildbeschreibung_ueber_claude_sdk(): void
    {
        Setting::putMany(['ai_provider' => 'anthropic', 'ai_model' => 'claude-opus-5-5', 'ai_key' => AiManager::encryptKey('k')]);
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
                'content' => [['type' => 'text', 'text' => 'Brote im Holzofen.']],
                'stop_reason' => 'end_turn', 'stop_sequence' => null, 'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ])),
        ]));
        $stack->push(Middleware::history($history));
        app()->instance(AnthropicDriver::TRANSPORT_BINDING, new Client(['handler' => $stack]));

        $this->upload(UploadedFile::fake()->image('brot.jpg', 1200, 800));
        $this->actingAs($this->admin)->postJson(route('admin.media.describe', Media::sole()))->assertOk()->assertJson(['alt' => 'Brote im Holzofen.']);

        $content = json_decode((string) $history[0]['request']->getBody(), true)['messages'][0]['content'];
        $this->assertSame('image', $content[0]['type']);
        $this->assertSame(['type' => 'base64', 'media_type' => 'image/webp'], array_diff_key($content[0]['source'], ['data' => 1]));
        $this->assertSame('text', $content[1]['type']);
    }

    public function test_mediathek_und_hinweis(): void
    {
        $this->actingAs($this->admin)->get(route('admin.media.index'))->assertOk()->assertSee('Noch keine Bilder oder Videos');
        $this->actingAs($this->admin)->get('/admin')->assertDontSee('Bildbeschreibungen ergänzen');

        $this->upload(UploadedFile::fake()->image('brot.jpg', 800, 600));
        $this->actingAs($this->admin)->get('/admin')->assertSee('Bildbeschreibungen ergänzen');
        $this->actingAs($this->admin)->get(route('admin.media.index'))->assertSee('KI einrichten, um das zu automatisieren');
    }
}
