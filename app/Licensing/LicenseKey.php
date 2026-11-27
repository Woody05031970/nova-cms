<?php

namespace App\Licensing;

/**
 * Lizenzschlüssel im Format NOVA-XXXX-XXXX-XXXX-XXXX. Die letzte Gruppe enthält eine Prüfsumme,
 * damit Tippfehler sofort auffallen, ohne den Server zu fragen.
 */
class LicenseKey
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function generate(): string
    {
        $body = '';
        for ($i = 0; $i < 12; $i++) {
            $body .= self::ALPHABET[random_int(0, 31)];
        }

        return self::format($body.self::checksum($body));
    }

    public static function normalize(string $key): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $key));
        if (str_starts_with($clean, 'NOVA')) {
            $clean = substr($clean, 4);
        }

        return strlen($clean) === 16 ? self::format($clean) : strtoupper(trim($key));
    }

    public static function isValid(string $key): bool
    {
        $clean = substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $key)), 4);
        if (strlen($clean) !== 16 || strspn($clean, self::ALPHABET) !== 16) {
            return false;
        }

        return substr($clean, 12) === self::checksum(substr($clean, 0, 12));
    }

    private static function checksum(string $body): string
    {
        $hash = hash('sha256', 'nova:'.$body, true);
        $out = '';
        for ($i = 0; $i < 4; $i++) {
            $out .= self::ALPHABET[ord($hash[$i]) % 32];
        }

        return $out;
    }

    private static function format(string $clean): string
    {
        return 'NOVA-'.implode('-', str_split($clean, 4));
    }
}
