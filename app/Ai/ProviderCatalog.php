<?php

namespace App\Ai;

use App\Ai\Drivers\AnthropicDriver;
use App\Ai\Drivers\GeminiDriver;
use App\Ai\Drivers\OpenAiCompatibleDriver;
use App\Plugins\Hooks;

/**
 * Alle KI-Anbieter, die Nova CMS von Haus aus anbinden kann.
 *
 * Plugins können über den Filter "ai.providers" weitere Anbieter ergänzen.
 */
class ProviderCatalog
{
    /**
     * @return array<string, array{
     *     label: string, description: string, driver: class-string<AiDriver>, model: string,
     *     models: array<string, string>, needs_key: bool, base_url: ?string, editable_url: bool,
     *     key_url: ?string, json_mode: string, region: string
     * }>
     */
    public static function all(): array
    {
        $providers = [
            'anthropic' => [
                'label' => 'Claude (Anthropic)',
                'description' => 'Schreibt sehr natürliches Deutsch und folgt Anweisungen genau.',
                'driver' => AnthropicDriver::class,
                'model' => 'claude-opus-5-5',
                'models' => [
                    'claude-opus-5-5' => 'Claude Opus 5.5: beste Qualität',
                    'claude-sonnet-5-5' => 'Claude Sonnet 5.5: schnell und gut',
                    'claude-haiku-5-5' => 'Claude Haiku 5.5: am günstigsten',
                ],
                'needs_key' => true,
                'base_url' => null,
                'editable_url' => false,
                'key_url' => 'https://console.anthropic.com/settings/keys',
                'json_mode' => 'schema',
                'region' => 'USA',
            ],
            'openai' => [
                'label' => 'ChatGPT (OpenAI)',
                'description' => 'Der bekannteste Anbieter mit vielen Modellen.',
                'driver' => OpenAiCompatibleDriver::class,
                'model' => 'gpt-5-mini',
                'models' => ['gpt-5' => 'GPT-5', 'gpt-5-mini' => 'GPT-5 mini: günstiger'],
                'needs_key' => true,
                'base_url' => 'https://api.openai.com/v1',
                'editable_url' => false,
                'key_url' => 'https://platform.openai.com/api-keys',
                'json_mode' => 'schema',
                'region' => 'USA',
            ],
            'gemini' => [
                'label' => 'Gemini (Google)',
                'description' => 'Die KI von Google.',
                'driver' => GeminiDriver::class,
                'model' => 'gemini-2.5-flash',
                'models' => ['gemini-2.5-pro' => 'Gemini 2.5 Pro', 'gemini-2.5-flash' => 'Gemini 2.5 Flash: günstiger'],
                'needs_key' => true,
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'editable_url' => false,
                'key_url' => 'https://aistudio.google.com/app/apikey',
                'json_mode' => 'object',
                'region' => 'USA',
            ],
            'mistral' => [
                'label' => 'Mistral AI',
                'description' => 'Europäischer Anbieter mit Sitz in Frankreich.',
                'driver' => OpenAiCompatibleDriver::class,
                'model' => 'mistral-large-latest',
                'models' => ['mistral-large-latest' => 'Mistral Large', 'mistral-small-latest' => 'Mistral Small: günstiger'],
                'needs_key' => true,
                'base_url' => 'https://api.mistral.ai/v1',
                'editable_url' => false,
                'key_url' => 'https://console.mistral.ai/api-keys',
                'json_mode' => 'object',
                'region' => 'EU',
            ],
            'ollama' => [
                'label' => 'Eigener KI-Server (Ollama)',
                'description' => 'Für Profis: ein eigenes Modell auf Ihrem Server. Keine Daten verlassen Ihr Netz.',
                'driver' => OpenAiCompatibleDriver::class,
                'model' => 'llama3.1',
                'models' => [],
                'needs_key' => false,
                'base_url' => 'http://localhost:11434/v1',
                'editable_url' => true,
                'key_url' => null,
                'json_mode' => 'object',
                'region' => 'Eigener Server',
            ],
            'custom' => [
                'label' => 'Anderer Anbieter',
                'description' => 'Jeder Dienst mit OpenAI-kompatibler Schnittstelle, z. B. OpenRouter, IONOS AI Model Hub oder Azure.',
                'driver' => OpenAiCompatibleDriver::class,
                'model' => '',
                'models' => [],
                'needs_key' => true,
                'base_url' => '',
                'editable_url' => true,
                'key_url' => null,
                'json_mode' => 'object',
                'region' => 'je nach Anbieter',
            ],
        ];

        return app(Hooks::class)->applyFilters('ai.providers', $providers);
    }

    /** @return array<string, mixed>|null */
    public static function get(?string $key): ?array
    {
        return $key ? (static::all()[$key] ?? null) : null;
    }
}
