<?php

namespace App\Ai;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Zentrale Stelle für alle KI-Funktionen: liest die Einstellungen und spricht den gewählten Anbieter an.
 */
class AiManager
{
    private ?AiDriver $fake = null;

    public function isConfigured(): bool
    {
        $provider = ProviderCatalog::get(Setting::get('ai_provider'));
        if (! $provider || ! Setting::get('ai_model')) {
            return false;
        }

        return ! $provider['needs_key'] || $this->apiKey() !== null;
    }

    public function config(): AiConfig
    {
        $key = Setting::get('ai_provider');
        $provider = ProviderCatalog::get($key);
        if (! $provider || ! $this->isConfigured()) {
            throw new AiException('Es ist noch kein KI-Anbieter eingerichtet. Sie können das unter „KI-Assistent“ in wenigen Minuten nachholen.');
        }

        return new AiConfig(
            provider: $key,
            label: $provider['label'],
            model: (string) Setting::get('ai_model'),
            apiKey: $this->apiKey(),
            baseUrl: $provider['editable_url'] ? Setting::get('ai_base_url', $provider['base_url']) : $provider['base_url'],
            jsonMode: $provider['json_mode'],
        );
    }

    public function driver(?AiConfig $config = null): AiDriver
    {
        if ($this->fake) {
            return $this->fake;
        }

        $config ??= $this->config();
        $class = ProviderCatalog::get($config->provider)['driver'];

        return new $class($config);
    }

    /** Fließtext erzeugen. */
    public function text(string $system, string $prompt, int $maxTokens = 8000): string
    {
        $this->allowLongRequest();

        return trim($this->driver()->complete($system, $prompt, null, $maxTokens));
    }

    /**
     * Strukturierte Antwort erzeugen.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function json(string $system, string $prompt, array $schema, int $maxTokens = 16000): array
    {
        $this->allowLongRequest();
        $raw = trim($this->driver()->complete($system, $prompt, $schema, $maxTokens));

        // Manche Anbieter setzen JSON trotz Anweisung in einen Code-Block.
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $raw);
        $data = json_decode($raw, true);

        if (! is_array($data)) {
            throw new AiException('Die KI hat keine verwertbare Antwort geliefert. Bitte versuchen Sie es noch einmal.');
        }

        return $data;
    }

    public function apiKey(): ?string
    {
        $stored = Setting::get('ai_key');
        if (! $stored) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return null; // z. B. nach einem Umzug mit neuem APP_KEY
        }
    }

    public static function encryptKey(string $key): string
    {
        return Crypt::encryptString($key);
    }

    /** Ersetzt den Anbieter, z. B. in Tests oder durch ein Plugin. */
    public function fake(AiDriver $driver): void
    {
        $this->fake = $driver;
    }

    private function allowLongRequest(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }
    }
}
