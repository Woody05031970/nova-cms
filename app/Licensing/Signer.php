<?php

namespace App\Licensing;

use RuntimeException;

/**
 * Ed25519-Signaturen: Der Lizenzserver unterschreibt mit seinem geheimen Schlüssel,
 * jede Installation prüft mit dem öffentlichen Schlüssel. Gefälschte Antworten fallen so auf.
 */
class Signer
{
    /** @return array{public: string, secret: string} Base64 */
    public static function generateKeyPair(): array
    {
        $pair = sodium_crypto_sign_keypair();

        return [
            'public' => base64_encode(sodium_crypto_sign_publickey($pair)),
            'secret' => base64_encode(sodium_crypto_sign_secretkey($pair)),
        ];
    }

    public static function sign(string $message, ?string $secret = null): string
    {
        $secret ??= (string) config('nova.license.private_key');
        $sk = base64_decode($secret, true);
        if ($sk === false || strlen($sk) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new RuntimeException('Der geheime Schlüssel des Lizenzservers fehlt oder ist ungültig.');
        }

        return base64_encode(sodium_crypto_sign_detached($message, $sk));
    }

    public static function verify(string $message, string $signature, ?string $public = null): bool
    {
        $public ??= (string) config('nova.license.public_key');
        $pk = base64_decode($public, true);
        $sig = base64_decode($signature, true);
        if ($pk === false || strlen($pk) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || $sig === false || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($sig, $message, $pk);
    }

    /** Unterschriebene Antwort: Nutzdaten als JSON-Text plus Signatur */
    public static function envelope(array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return ['payload' => $json, 'signature' => self::sign($json)];
    }

    /** @return array<string, mixed>|null Nutzdaten, wenn die Signatur stimmt */
    public static function open(mixed $envelope): ?array
    {
        if (! is_array($envelope) || ! is_string($envelope['payload'] ?? null) || ! is_string($envelope['signature'] ?? null)) {
            return null;
        }
        if (! self::verify($envelope['payload'], $envelope['signature'])) {
            return null;
        }
        $data = json_decode($envelope['payload'], true);

        return is_array($data) ? $data : null;
    }
}
