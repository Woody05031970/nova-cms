<?php

namespace App\Ai\Drivers;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Messages\JSONOutputFormat;
use Anthropic\Messages\OutputConfig;
use Anthropic\RequestOptions;
use App\Ai\AiConfig;
use App\Ai\AiDriver;
use App\Ai\AiException;
use Psr\Http\Client\ClientInterface;

/**
 * Claude über das offizielle Anthropic-PHP-SDK.
 */
class AnthropicDriver implements AiDriver
{
    /** Für Tests kann ein eigener HTTP-Client unter diesem Namen im Container hinterlegt werden. */
    public const TRANSPORT_BINDING = 'nova.ai.anthropic.transport';

    public function __construct(private readonly AiConfig $config) {}

    public function complete(string $system, string $prompt, ?array $schema = null, int $maxTokens = 4000): string
    {
        $transport = app()->bound(self::TRANSPORT_BINDING) ? app(self::TRANSPORT_BINDING) : null;

        $client = new Client(
            apiKey: (string) $this->config->apiKey,
            requestOptions: RequestOptions::with(
                timeout: 120,
                maxRetries: 2,
                transporter: $transport instanceof ClientInterface ? $transport : null,
            ),
        );

        try {
            $message = $client->messages->create(
                model: $this->config->model,
                maxTokens: $maxTokens,
                system: $system,
                messages: [['role' => 'user', 'content' => $prompt]],
                outputConfig: $schema ? OutputConfig::with(format: JSONOutputFormat::with(schema: $schema)) : null,
            );
        } catch (APIStatusException $e) {
            $body = is_array($e->body) ? ($e->body['error']['message'] ?? null) : null;
            throw AiException::forStatus((int) $e->status, $this->config->label, $body);
        } catch (APIConnectionException) {
            throw AiException::unreachable($this->config->label);
        }

        if ($message->stopReason === 'refusal') {
            throw new AiException('Claude hat diese Anfrage abgelehnt. Bitte formulieren Sie den Text oder die Angaben anders.');
        }
        if ($message->stopReason === 'max_tokens') {
            throw new AiException('Die Antwort war zu lang und wurde abgeschnitten. Bitte versuchen Sie es mit weniger Text oder weniger Seiten.');
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return $text;
    }
}
