<?php

namespace App\Ai;

interface AiDriver
{
    /**
     * Schickt eine Anfrage an den Anbieter und liefert den Antworttext.
     *
     * Mit $schema (JSON-Schema) antwortet das Modell mit JSON, das diesem Schema folgt.
     *
     * @param  array<string, mixed>|null  $schema
     *
     * @throws AiException
     */
    public function complete(string $system, string $prompt, ?array $schema = null, int $maxTokens = 4000): string;
}
