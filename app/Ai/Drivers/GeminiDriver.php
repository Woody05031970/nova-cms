<?php

namespace App\Ai\Drivers;

use App\Ai\AiConfig;
use App\Ai\AiDriver;
use App\Ai\AiException;
use App\Ai\AiVision;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini über die Generative-Language-Schnittstelle.
 */
class GeminiDriver implements AiDriver, AiVision
{
    public function __construct(private readonly AiConfig $config) {}

    public function complete(string $system, string $prompt, ?array $schema = null, int $maxTokens = 4000): string
    {
        return $this->send($system, [['text' => $prompt]], $schema, $maxTokens);
    }

    public function describeImage(string $system, string $instruction, string $data, string $mime): string
    {
        return $this->send($system, [
            ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($data)]],
            ['text' => $instruction],
        ], null, 4000);
    }

    /** @param  list<array<string, mixed>>  $parts */
    private function send(string $system, array $parts, ?array $schema, int $maxTokens): string
    {
        $generation = ['maxOutputTokens' => $maxTokens];
        if ($schema) {
            $generation['responseMimeType'] = 'application/json';
            $system .= "\n\nAntworte ausschließlich mit JSON nach diesem Schema:\n".json_encode($schema, JSON_UNESCAPED_UNICODE);
        }

        $url = rtrim((string) $this->config->baseUrl, '/').'/models/'.rawurlencode($this->config->model).':generateContent';

        try {
            $response = Http::timeout(120)->acceptJson()
                ->withHeaders(['x-goog-api-key' => (string) $this->config->apiKey])
                ->post($url, [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => [['role' => 'user', 'parts' => $parts]],
                    'generationConfig' => $generation,
                ]);
        } catch (ConnectionException) {
            throw AiException::unreachable($this->config->label);
        }

        if ($response->failed()) {
            throw AiException::forStatus($response->status(), $this->config->label, $response->json('error.message'));
        }

        $candidate = $response->json('candidates.0');
        if (($candidate['finishReason'] ?? null) === 'MAX_TOKENS') {
            throw new AiException('Die Antwort war zu lang und wurde abgeschnitten. Bitte versuchen Sie es mit weniger Text oder weniger Seiten.');
        }
        if (in_array($candidate['finishReason'] ?? null, ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST'], true) || ! $candidate) {
            throw new AiException('Gemini hat diese Anfrage abgelehnt. Bitte formulieren Sie die Angaben anders.');
        }

        return collect($candidate['content']['parts'] ?? [])->pluck('text')->implode('');
    }
}
