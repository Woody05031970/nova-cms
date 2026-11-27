<?php

namespace App\Ai\Drivers;

use App\Ai\AiConfig;
use App\Ai\AiDriver;
use App\Ai\AiException;
use App\Ai\AiVision;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI und alle Dienste mit derselben Schnittstelle (Mistral, Ollama, OpenRouter, IONOS AI Model Hub …).
 */
class OpenAiCompatibleDriver implements AiDriver, AiVision
{
    public function __construct(private readonly AiConfig $config) {}

    public function complete(string $system, string $prompt, ?array $schema = null, int $maxTokens = 4000): string
    {
        return $this->send($system, $prompt, $schema, $maxTokens);
    }

    public function describeImage(string $system, string $instruction, string $data, string $mime): string
    {
        return $this->send($system, [
            ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$mime.';base64,'.base64_encode($data)]],
            ['type' => 'text', 'text' => $instruction],
        ], null, 4000);
    }

    /** @param  string|list<array<string, mixed>>  $content */
    private function send(string $system, string|array $content, ?array $schema, int $maxTokens): string
    {
        $body = [
            'model' => $this->config->model,
            'messages' => [
                ['role' => 'system', 'content' => $system.($schema ? "\n\nAntworte ausschließlich mit JSON nach diesem Schema:\n".json_encode($schema, JSON_UNESCAPED_UNICODE) : '')],
                ['role' => 'user', 'content' => $content],
            ],
        ];

        if ($schema) {
            $body['response_format'] = $this->config->jsonMode === 'schema'
                ? ['type' => 'json_schema', 'json_schema' => ['name' => 'antwort', 'strict' => true, 'schema' => $schema]]
                : ['type' => 'json_object'];
        }

        // OpenAI erwartet bei neueren Modellen max_completion_tokens, andere Dienste max_tokens.
        $body[$this->config->provider === 'openai' ? 'max_completion_tokens' : 'max_tokens'] = $maxTokens;

        try {
            $request = Http::timeout(120)->acceptJson();
            if ($this->config->apiKey) {
                $request = $request->withToken($this->config->apiKey);
            }
            $response = $request->post(rtrim((string) $this->config->baseUrl, '/').'/chat/completions', $body);
        } catch (ConnectionException) {
            throw AiException::unreachable($this->config->label);
        }

        if ($response->failed()) {
            throw AiException::forStatus($response->status(), $this->config->label, $response->json('error.message'));
        }

        $choice = $response->json('choices.0');
        if (($choice['finish_reason'] ?? null) === 'length') {
            throw new AiException('Die Antwort war zu lang und wurde abgeschnitten. Bitte versuchen Sie es mit weniger Text oder weniger Seiten.');
        }
        if (! empty($choice['message']['refusal'])) {
            throw new AiException($this->config->label.' hat diese Anfrage abgelehnt. Bitte formulieren Sie die Angaben anders.');
        }

        return (string) ($choice['message']['content'] ?? '');
    }
}
