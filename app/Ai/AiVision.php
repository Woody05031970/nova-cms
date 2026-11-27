<?php

namespace App\Ai;

/**
 * Anbieter, die Bilder verstehen (z. B. für Bildbeschreibungen).
 */
interface AiVision
{
    /** @throws AiException */
    public function describeImage(string $system, string $instruction, string $data, string $mime): string;
}
