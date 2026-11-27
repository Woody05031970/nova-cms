<?php

namespace Tests\Feature;

use App\Ai\AiDriver;
use App\Ai\AiManager;
use App\Ai\Drivers\AnthropicDriver;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        Setting::putMany(['site_name' => 'Bäckerei Müller', 'industry' => 'Bäckerei', 'zip_city' => '50667 Köln']);
    }

    private function configure(string $provider = 'anthropic', string $model = 'claude-opus-5-5', ?string $key = 'sk-test-1234'): void
    {
        Setting::putMany(['ai_provider' => $provider, 'ai_model' => $model, 'ai_key' => $key ? AiManager::encryptKey($key) : null]);
    }

    /** Ersetzt den Anbieter durch eine feste Antwort und merkt sich die Anfragen. */
    private function fakeAi(string $answer): object
    {
        $driver = new class($answer) implements AiDriver
        {
            public array $calls = [];

            public function __construct(private string $answer) {}

            public function complete(string $system, string $prompt, ?array $schema = null, int $maxTokens = 4000): string
            {
                $this->calls[] = compact('system', 'prompt', 'schema', 'maxTokens');

                return $this->answer;
            }
        };
        app(AiManager::class)->fake($driver);

        return $driver;
    }

    public function test_einstellungen_speichern_den_schluessel_verschluesselt(): void
    {
        $this->actingAs($this->admin)->get(route('admin.ai'))->assertOk()->assertSee('Claude (Anthropic)')->assertSee('Mistral AI');

        $this->assertFalse(app(AiManager::class)->isConfigured());

        $this->actingAs($this->admin)->put(route('admin.ai.update'), [
            'provider' => 'anthropic', 'model' => 'claude-sonnet-5-5', 'api_key' => 'sk-ant-geheim-9876',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $stored = Setting::query()->find('ai_key')->value;
        $this->assertStringNotContainsString('geheim', $stored);
        $this->assertSame('sk-ant-geheim-9876', app(AiManager::class)->apiKey());
        $this->assertTrue(app(AiManager::class)->isConfigured());

        // Ohne neuen Schlüssel bleibt der alte erhalten, angezeigt wird nur das Ende.
        $this->actingAs($this->admin)->put(route('admin.ai.update'), ['provider' => 'anthropic', 'model' => 'claude-haiku-5-5', 'api_key' => '']);
        $this->assertSame('sk-ant-geheim-9876', app(AiManager::class)->apiKey());
        $this->actingAs($this->admin)->get(route('admin.ai'))->assertSee('••••9876')->assertDontSee('sk-ant-geheim');

        // Hinweis im Dashboard ist erledigt
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertDontSee('KI-Anbieter einrichten');
    }

    public function test_eigener_server_braucht_eine_adresse(): void
    {
        $this->actingAs($this->admin)->put(route('admin.ai.update'), ['provider' => 'ollama', 'model' => 'llama3.1', 'base_url' => ''])
            ->assertSessionHasErrors('base_url');

        $this->actingAs($this->admin)->put(route('admin.ai.update'), ['provider' => 'ollama', 'model' => 'llama3.1', 'base_url' => 'http://10.0.0.5:11434/v1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(app(AiManager::class)->isConfigured(), 'Ollama braucht keinen Schlüssel');
    }

    public function test_verbindungstest_mit_openai(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'Hallo, ich bin GPT.'], 'finish_reason' => 'stop']]]),
        ]);

        $this->actingAs($this->admin)->postJson(route('admin.ai.test'), ['provider' => 'openai', 'model' => 'gpt-5-mini', 'api_key' => 'sk-openai'])
            ->assertOk()->assertJson(['ok' => true])->assertJsonFragment(['message' => 'Verbindung klappt. Antwort: Hallo, ich bin GPT.']);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.openai.com/v1/chat/completions'
            && $r->hasHeader('Authorization', 'Bearer sk-openai')
            && $r['model'] === 'gpt-5-mini'
            && isset($r['max_completion_tokens']));
    }

    public function test_abgelehnter_schluessel_wird_verstaendlich_gemeldet(): void
    {
        Http::fake(['api.mistral.ai/*' => Http::response(['message' => 'Unauthorized'], 401)]);

        $this->actingAs($this->admin)->postJson(route('admin.ai.test'), ['provider' => 'mistral', 'model' => 'mistral-small-latest', 'api_key' => 'falsch'])
            ->assertStatus(422)->assertJson(['ok' => false])
            ->assertJsonFragment(['message' => 'Mistral AI hat den API-Schlüssel abgelehnt. Bitte prüfen Sie den Schlüssel in den KI-Einstellungen.']);
    }

    public function test_gespeicherter_schluessel_geht_nicht_an_fremde_adressen(): void
    {
        Setting::putMany(['ai_provider' => 'custom', 'ai_model' => 'm', 'ai_base_url' => 'https://ki.example.de/v1', 'ai_key' => AiManager::encryptKey('geheim')]);
        Http::fake();

        $this->actingAs($this->admin)->postJson(route('admin.ai.test'), ['provider' => 'custom', 'model' => 'm', 'base_url' => 'https://angreifer.example/v1'])
            ->assertStatus(422)->assertJsonFragment(['message' => 'Bitte tragen Sie zuerst den API-Schlüssel ein.']);
        Http::assertNothingSent();
    }

    public function test_claude_ueber_das_offizielle_sdk(): void
    {
        $this->configure();
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
                'content' => [['type' => 'text', 'text' => '{"tagline":"Brot mit Liebe","pages":[]}']],
                'stop_reason' => 'end_turn', 'stop_sequence' => null,
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ])),
        ]));
        $stack->push(Middleware::history($history));
        app()->instance(AnthropicDriver::TRANSPORT_BINDING, new Guzzle(['handler' => $stack]));

        $data = app(AiManager::class)->json('System', 'Frage', ['type' => 'object', 'properties' => ['tagline' => ['type' => 'string']], 'required' => ['tagline'], 'additionalProperties' => false]);

        $this->assertSame('Brot mit Liebe', $data['tagline']);
        $request = $history[0]['request'];
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('https://api.anthropic.com/v1/messages', (string) $request->getUri());
        $this->assertSame('Bearer sk-test-1234', $request->getHeaderLine('Authorization'));
        $this->assertSame('claude-opus-5-5', $body['model']);
        $this->assertSame('json_schema', $body['output_config']['format']['type']);
        $this->assertSame('System', $body['system']);
    }

    public function test_claude_ablehnung_wird_gemeldet(): void
    {
        $this->configure();
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 'msg_2', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
                'content' => [], 'stop_reason' => 'refusal', 'stop_sequence' => null,
                'usage' => ['input_tokens' => 10, 'output_tokens' => 0],
            ])),
        ]));
        app()->instance(AnthropicDriver::TRANSPORT_BINDING, new Guzzle(['handler' => $stack]));
        $this->actingAs($this->admin)->postJson(route('admin.ai.rewrite'), ['action' => 'verbessern', 'html' => '<p>Hallo</p>'])
            ->assertStatus(422)->assertJsonFragment(['message' => 'Claude hat diese Anfrage abgelehnt. Bitte formulieren Sie den Text oder die Angaben anders.']);
    }

    public function test_gemini(): void
    {
        $this->configure('gemini', 'gemini-2.5-flash', 'g-key');
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Frisches Brot aus Köln.']]], 'finishReason' => 'STOP']],
        ])]);

        $this->assertSame('Frisches Brot aus Köln.', app(AiManager::class)->text('s', 'p'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/models/gemini-2.5-flash:generateContent') && $r->hasHeader('x-goog-api-key', 'g-key'));
    }

    public function test_website_assistent_legt_entwuerfe_an(): void
    {
        $this->configure();
        $this->actingAs($this->admin)->get(route('admin.ai.wizard'))->assertOk()->assertSee('Was bieten Sie an?');

        $fake = $this->fakeAi(json_encode([
            'tagline' => 'Sauerteigbrot aus Köln-Ehrenfeld',
            'pages' => [
                ['key' => 'start', 'title' => 'Willkommen', 'meta_description' => 'Ihre Bäckerei in Ehrenfeld.',
                    'hero' => ['title' => 'Brot wie früher', 'text' => 'Jeden Morgen frisch.', 'button_label' => 'Besuchen Sie uns'],
                    'sections' => [['heading' => 'Unser Brot', 'paragraphs' => ['Mit <script>alert(1)</script> Zeit gebacken.'], 'bullets' => ['Sauerteig', 'Dinkel']]]],
                ['key' => 'kontakt', 'title' => 'Kontakt', 'meta_description' => 'So erreichen Sie uns.', 'hero' => null,
                    'sections' => [['heading' => 'Schreiben Sie uns', 'paragraphs' => ['Wir freuen uns.'], 'bullets' => []]]],
                ['key' => 'boese', 'title' => 'Nicht gewünscht', 'meta_description' => '', 'hero' => null, 'sections' => []],
            ],
        ]));

        $this->actingAs($this->admin)->post(route('admin.ai.wizard.run'), [
            'offer' => 'Familienbäckerei mit Sauerteigbrot und Café', 'tone' => 'persoenlich', 'pages' => ['start', 'kontakt'],
        ])->assertRedirect(route('admin.pages.index'))->assertSessionHas('status');

        $this->assertCount(2, Page::all());
        $home = Page::where('is_home', true)->first();
        $this->assertSame('Willkommen', $home->title);
        $this->assertSame('draft', $home->status);
        $this->assertSame('hero', $home->blocks[0]['type']);
        $this->assertSame('Brot wie früher', $home->blocks[0]['data']['title']);
        $this->assertStringContainsString('<li>Sauerteig</li>', $home->blocks[1]['data']['html']);
        $this->assertStringNotContainsString('<script', $home->blocks[1]['data']['html']);
        $this->assertSame('button', collect($home->blocks)->last()['type']);

        $contact = Page::where('slug', 'kontakt')->first();
        $this->assertStringContainsString('50667 Köln', json_encode($contact->blocks, JSON_UNESCAPED_UNICODE), 'Adresse kommt aus dem Firmenprofil');

        $this->assertSame('Sauerteigbrot aus Köln-Ehrenfeld', Setting::get('tagline'));
        $this->assertNotNull($fake->calls[0]['schema']);
        $this->assertStringContainsString('Bäckerei Müller', $fake->calls[0]['system']);
        $this->assertStringContainsString('Familienbäckerei', $fake->calls[0]['prompt']);
    }

    public function test_assistent_ohne_anbieter_fuehrt_zur_einrichtung(): void
    {
        $this->actingAs($this->admin)->get(route('admin.ai.wizard'))->assertRedirect(route('admin.ai'));
        $this->actingAs($this->admin)->postJson(route('admin.ai.rewrite'), ['action' => 'kuerzer', 'html' => '<p>Text</p>'])
            ->assertStatus(422)->assertJsonPath('setup', route('admin.ai'));
    }

    public function test_text_verbessern_filtert_die_antwort(): void
    {
        $this->configure();
        $fake = $this->fakeAi("```html\n<h2>Besser</h2><p onclick=\"x()\">Neu <strong>formuliert</strong></p><script>alert(1)</script>\n```");

        $html = $this->actingAs($this->admin)->postJson(route('admin.ai.rewrite'), ['action' => 'verbessern', 'html' => '<p>Alt</p>'])
            ->assertOk()->json('html');

        $this->assertSame('<h2>Besser</h2><p>Neu <strong>formuliert</strong></p>', $html);
        $this->assertStringContainsString('klarer', $fake->calls[0]['prompt']);

        // Markierter Text ohne Tags kommt als reiner Text zurück
        $this->fakeAi('<b>Kurz</b> und gut');
        $this->actingAs($this->admin)->postJson(route('admin.ai.rewrite'), ['action' => 'kuerzer', 'html' => 'Ein sehr langer Satz'])
            ->assertOk()->assertJson(['html' => 'Kurz und gut']);

        $this->actingAs($this->admin)->postJson(route('admin.ai.rewrite'), ['action' => 'hacken', 'html' => 'x'])->assertStatus(422);
    }

    public function test_google_beschreibung_vorschlagen(): void
    {
        $this->configure();
        $this->fakeAi('„Frisches Sauerteigbrot, Brötchen und Kuchen aus Köln-Ehrenfeld. Jeden Morgen ab 6 Uhr für Sie gebacken."');

        $this->actingAs($this->admin)->postJson(route('admin.ai.description'), ['title' => 'Start', 'text' => 'kurz'])
            ->assertStatus(422);

        $this->actingAs($this->admin)->postJson(route('admin.ai.description'), ['title' => 'Start', 'text' => str_repeat('Wir backen Brot mit Sauerteig. ', 5)])
            ->assertOk()->assertJson(['description' => 'Frisches Sauerteigbrot, Brötchen und Kuchen aus Köln-Ehrenfeld. Jeden Morgen ab 6 Uhr für Sie gebacken.']);
    }

    public function test_editor_zeigt_ki_knopf_nur_mit_anbieter(): void
    {
        $page = Page::create(['title' => 'Start', 'slug' => 'start', 'blocks' => []]);
        $this->actingAs($this->admin)->get(route('admin.pages.edit', $page))->assertOk()->assertSee('\u0022enabled\u0022:false', false);

        $this->configure();
        $this->actingAs($this->admin)->get(route('admin.pages.edit', $page))->assertOk()->assertSee('\u0022enabled\u0022:true', false)->assertSee('Rechtschreibung prüfen');
    }
}
