<?php

namespace App\Ai;

/**
 * Die gewählte Verbindung zu einem KI-Anbieter.
 */
final class AiConfig
{
    public function __construct(
        public readonly string $provider,
        public readonly string $label,
        public readonly string $model,
        public readonly ?string $apiKey,
        public readonly ?string $baseUrl,
        public readonly string $jsonMode = 'object',
    ) {}
}
