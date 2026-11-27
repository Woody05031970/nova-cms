<?php

namespace App\Http\Controllers\Admin;

use App\Ai\AiConfig;
use App\Ai\AiException;
use App\Ai\AiManager;
use App\Ai\ContentAssistant;
use App\Ai\ProviderCatalog;
use App\Ai\WebsiteWizard;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiController extends Controller
{
    public function __construct(private readonly AiManager $ai) {}

    public function index(): View
    {
        $key = $this->ai->apiKey();

        return view('admin.ai.index', [
            'providers' => ProviderCatalog::all(),
            'configured' => $this->ai->isConfigured(),
            'current' => [
                'provider' => Setting::get('ai_provider', 'anthropic'),
                'model' => Setting::get('ai_model'),
                'base_url' => Setting::get('ai_base_url'),
                'key_hint' => $key ? '••••'.Str::substr($key, -4) : null,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Setting::putMany([
            'ai_provider' => $data['provider'],
            'ai_model' => $data['model'],
            'ai_base_url' => $data['base_url'] ?? null,
        ]);
        if (! empty($data['api_key'])) {
            Setting::put('ai_key', AiManager::encryptKey($data['api_key']));
        } elseif ($request->boolean('forget_key')) {
            Setting::put('ai_key', null);
        }

        return back()->with('status', 'KI-Einstellungen gespeichert.');
    }

    /** Verbindungstest mit den Werten aus dem Formular, auch bevor sie gespeichert sind. */
    public function test(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $provider = ProviderCatalog::get($data['provider']);

        $config = new AiConfig(
            provider: $data['provider'],
            label: $provider['label'],
            model: $data['model'],
            apiKey: ($data['api_key'] ?? null) ?: ($this->sameEndpoint($data, $provider) ? $this->ai->apiKey() : null),
            baseUrl: $provider['editable_url'] ? ($data['base_url'] ?? $provider['base_url']) : $provider['base_url'],
            jsonMode: $provider['json_mode'],
        );

        if ($provider['needs_key'] && ! $config->apiKey) {
            return response()->json(['ok' => false, 'message' => 'Bitte tragen Sie zuerst den API-Schlüssel ein.'], 422);
        }

        try {
            $answer = $this->ai->driver($config)->complete(
                'Du bist ein Verbindungstest. Antworte mit genau einem kurzen deutschen Satz.',
                'Sag Hallo und nenne, welches Modell du bist.',
                null,
                2000,
            );
        } catch (AiException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Verbindung klappt. Antwort: '.Str::limit(trim($answer), 200)]);
    }

    public function wizard(): View|RedirectResponse
    {
        if (! $this->ai->isConfigured()) {
            return redirect()->route('admin.ai')->with('status', 'Richten Sie zuerst einen KI-Anbieter ein. Danach schreibt der Assistent Ihre ersten Seiten.');
        }

        return view('admin.ai.wizard', [
            'pages' => WebsiteWizard::PAGES,
            'existing' => Page::pluck('title')->map(fn ($t) => Str::lower($t))->all(),
            'industry' => Setting::get('industry'),
            'tagline' => Setting::get('tagline'),
        ]);
    }

    public function runWizard(Request $request, ContentAssistant $assistant, WebsiteWizard $wizard): RedirectResponse
    {
        $data = $request->validate([
            'industry' => ['nullable', 'string', 'max:120'],
            'offer' => ['required', 'string', 'min:10', 'max:2000'],
            'audience' => ['nullable', 'string', 'max:500'],
            'highlights' => ['nullable', 'string', 'max:1000'],
            'tone' => ['required', Rule::in(['persoenlich', 'professionell', 'locker'])],
            'pages' => ['required', 'array', 'min:1'],
            'pages.*' => [Rule::in(array_keys(WebsiteWizard::PAGES))],
        ], [
            'offer.required' => 'Beschreiben Sie bitte kurz, was Sie anbieten.',
            'offer.min' => 'Bitte etwas ausführlicher: ein, zwei Sätze zu Ihrem Angebot.',
            'pages.required' => 'Wählen Sie mindestens eine Seite aus.',
        ]);

        if (! empty($data['industry'])) {
            Setting::put('industry', $data['industry']);
        }

        try {
            $draft = $assistant->siteDraft($data);
        } catch (AiException $e) {
            return back()->withInput()->withErrors(['ai' => $e->getMessage()]);
        }

        $pages = $wizard->createPages($draft, $data['pages'], $request->user()->id);
        if (! $pages) {
            return back()->withInput()->withErrors(['ai' => 'Die KI hat keine Seiten geliefert. Bitte versuchen Sie es noch einmal.']);
        }

        if (! Setting::get('tagline') && ! empty($draft['tagline'])) {
            Setting::put('tagline', Str::limit(trim($draft['tagline']), 200, ''));
        }

        return redirect()->route('admin.pages.index')->with('status',
            count($pages).' Seiten wurden als Entwurf angelegt: '.collect($pages)->pluck('title')->implode(', ')
            .'. Lesen Sie sie in Ruhe durch, ergänzen Sie Platzhalter in [eckigen Klammern] und veröffentlichen Sie sie dann.');
    }

    public function rewrite(Request $request, ContentAssistant $assistant): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(array_keys(ContentAssistant::ACTIONS))],
            'html' => ['required', 'string', 'max:20000'],
        ]);

        $plain = $data['html'] === strip_tags($data['html']);

        return $this->respond(function () use ($assistant, $data, $plain) {
            $out = $assistant->rewrite($data['action'], $data['html']);

            // Was die KI liefert, geht durch denselben Filter wie jede Eingabe im Editor.
            return ['html' => $plain
                ? trim(strip_tags($out))
                : Page::cleanBlocks([['type' => 'text', 'data' => ['html' => $out]]])[0]['data']['html']];
        });
    }

    public function description(Request $request, ContentAssistant $assistant): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'text' => ['nullable', 'string', 'max:20000'],
        ]);

        if (mb_strlen(trim((string) ($data['text'] ?? ''))) < 40) {
            return response()->json(['message' => 'Die Seite hat noch zu wenig Text. Schreiben Sie zuerst ein paar Sätze, dann kann die KI eine Beschreibung vorschlagen.'], 422);
        }

        return $this->respond(fn () => ['description' => $assistant->metaDescription($data['title'], $data['text'])]);
    }

    public function productText(Request $request, ContentAssistant $assistant, \App\Support\Blocks\BlockSanitizer $sanitizer): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ], ['name.required' => 'Geben Sie zuerst einen Produktnamen ein.']);

        return $this->respond(function () use ($assistant, $data, $sanitizer) {
            $out = $assistant->productText($data['name'], (string) ($data['notes'] ?? ''));

            return ['short' => $out['short'], 'html' => $sanitizer->html($out['html'])];
        });
    }

    private function respond(\Closure $work): JsonResponse
    {
        if (! $this->ai->isConfigured()) {
            return response()->json(['message' => 'Es ist noch kein KI-Anbieter eingerichtet.', 'setup' => route('admin.ai')], 422);
        }

        try {
            return response()->json($work());
        } catch (AiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** Den gespeicherten Schlüssel nur an dieselbe Adresse schicken, an die er gehört. */
    private function sameEndpoint(array $data, array $provider): bool
    {
        return $data['provider'] === Setting::get('ai_provider')
            && (! $provider['editable_url'] || rtrim((string) ($data['base_url'] ?? ''), '/') === rtrim((string) Setting::get('ai_base_url'), '/'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $provider = ProviderCatalog::get($request->input('provider'));

        return $request->validate([
            'provider' => ['required', Rule::in(array_keys(ProviderCatalog::all()))],
            'model' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._:\/@-]+$/'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'base_url' => [($provider['editable_url'] ?? false) ? 'required' : 'nullable', 'url:http,https', 'max:255'],
        ], [
            'model.required' => 'Bitte wählen Sie ein Modell.',
            'model.regex' => 'Der Modellname enthält ungültige Zeichen.',
            'base_url.required' => 'Bitte geben Sie die Adresse der Schnittstelle an.',
            'base_url.url' => 'Die Adresse muss mit http:// oder https:// beginnen.',
        ]);
    }
}
