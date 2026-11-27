<?php

namespace App\Ai;

use RuntimeException;

/**
 * Fehler bei einer KI-Anfrage. Die Nachricht ist für Laien formuliert und darf angezeigt werden.
 */
class AiException extends RuntimeException
{
    public static function forStatus(int $status, string $provider, ?string $detail = null): self
    {
        $message = match (true) {
            $status === 401 || $status === 403 => "{$provider} hat den API-Schlüssel abgelehnt. Bitte prüfen Sie den Schlüssel in den KI-Einstellungen.",
            $status === 404 => "{$provider} kennt das gewählte Modell nicht. Bitte wählen Sie in den KI-Einstellungen ein anderes Modell.",
            $status === 402 || $status === 429 => "{$provider} meldet zu viele Anfragen oder ein aufgebrauchtes Guthaben. Bitte warten Sie kurz oder prüfen Sie Ihr Konto beim Anbieter.",
            $status >= 500 => "{$provider} ist gerade überlastet oder nicht erreichbar. Bitte versuchen Sie es in ein paar Minuten noch einmal.",
            default => "{$provider} hat die Anfrage nicht angenommen (Fehler {$status}).",
        };

        if ($detail && $status === 400) {
            $message .= ' Meldung: '.mb_strimwidth($detail, 0, 200, '…');
        }

        return new self($message, $status);
    }

    public static function unreachable(string $provider): self
    {
        return new self("{$provider} ist nicht erreichbar. Bitte prüfen Sie die Internetverbindung des Servers oder die Adresse in den KI-Einstellungen.");
    }
}
