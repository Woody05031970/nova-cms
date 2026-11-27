<?php

namespace App\Support\Social;

use App\Models\Setting;
use App\Shop\ShopSettings;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Verbindung zu Facebook und Instagram über die offizielle Graph-Schnittstelle von Meta.
 *
 * Voraussetzungen (erklärt im Backoffice):
 * - eine Facebook-Seite (keine Privatperson, das erlaubt Meta nicht)
 * - für Instagram ein Business- oder Creator-Konto, das mit der Seite verknüpft ist
 * - ein Seiten-Zugriffsschlüssel mit pages_manage_posts, pages_read_engagement,
 *   instagram_basic und instagram_content_publish
 */
class Meta
{
    public const API = 'https://graph.facebook.com/v21.0/';

    public static function pageId(): ?string
    {
        return Setting::get('social_fb_page_id') ?: null;
    }

    public static function token(): ?string
    {
        return ShopSettings::secret('social_fb_token');
    }

    public static function instagramId(): ?string
    {
        return Setting::get('social_ig_id') ?: null;
    }

    public static function connected(string $network): bool
    {
        return match ($network) {
            'facebook' => self::pageId() && self::token(),
            'instagram' => self::instagramId() && self::token(),
            default => false,
        };
    }

    /**
     * Prüft Seiten-ID und Schlüssel und liest den Namen der Seite und das verknüpfte Instagram-Konto.
     *
     * @return array{name: string, instagram_id: ?string, instagram_name: ?string}
     */
    public function verify(string $pageId, string $token): array
    {
        $data = $this->call('get', $pageId, ['fields' => 'name,instagram_business_account{id,username}', 'access_token' => $token]);

        return [
            'name' => (string) ($data['name'] ?? $pageId),
            'instagram_id' => $data['instagram_business_account']['id'] ?? null,
            'instagram_name' => $data['instagram_business_account']['username'] ?? null,
        ];
    }

    /** Beitrag auf der Facebook-Seite, mit Bild oder Link-Vorschau. Liefert die Beitrags-ID. */
    public function postFacebook(string $text, ?string $link, ?string $image): string
    {
        $page = self::pageId() ?? throw new RuntimeException('Facebook ist noch nicht verbunden.');
        $base = ['access_token' => self::token()];

        if ($image) {
            $data = $this->call('post', $page.'/photos', $base + ['url' => $image, 'caption' => trim($text."\n\n".($link ?? ''))]);

            return (string) ($data['post_id'] ?? $data['id'] ?? '');
        }

        $data = $this->call('post', $page.'/feed', $base + array_filter(['message' => $text, 'link' => $link]));

        return (string) ($data['id'] ?? '');
    }

    /** Beitrag bei Instagram: Bild hochladen lassen, dann veröffentlichen. Liefert die Medien-ID. */
    public function postInstagram(string $text, ?string $image): string
    {
        $ig = self::instagramId() ?? throw new RuntimeException('Instagram ist noch nicht verbunden.');
        if (! $image) {
            throw new RuntimeException('Instagram braucht ein Bild. Fügen Sie der Seite ein Titelbild oder Bild hinzu.');
        }
        $base = ['access_token' => self::token()];

        $container = $this->call('post', $ig.'/media', $base + ['image_url' => $image, 'caption' => mb_substr($text, 0, 2200)]);
        $data = $this->call('post', $ig.'/media_publish', $base + ['creation_id' => $container['id'] ?? '']);

        return (string) ($data['id'] ?? '');
    }

    /** @return array<string, mixed> */
    private function call(string $method, string $path, array $params): array
    {
        try {
            $response = Http::timeout(30)->asForm()->{$method}(self::API.ltrim($path, '/'), $params);
        } catch (Throwable) {
            throw new RuntimeException('Facebook ist gerade nicht erreichbar. Bitte versuchen Sie es später erneut.');
        }

        if ($response->failed()) {
            $error = $response->json('error') ?? [];
            throw new RuntimeException(self::explain((int) ($error['code'] ?? 0), (string) ($error['message'] ?? 'Unbekannter Fehler')));
        }

        return $response->json() ?? [];
    }

    /** Fehlermeldungen von Meta in verständliche Hinweise übersetzen */
    private static function explain(int $code, string $message): string
    {
        return match (true) {
            $code === 190 => 'Der Zugriffsschlüssel ist abgelaufen oder ungültig. Erzeugen Sie unter „Social Media“ einen neuen Schlüssel.',
            in_array($code, [10, 200, 299], true) => 'Dem Zugriffsschlüssel fehlt eine Berechtigung (z. B. pages_manage_posts oder instagram_content_publish).',
            $code === 100 && str_contains($message, 'image') => 'Instagram konnte das Bild nicht laden. Es muss öffentlich im Internet erreichbar und ein JPG sein.',
            $code === 100 => 'Facebook kennt diese Seiten-ID nicht, oder der Schlüssel gehört zu einer anderen Seite.',
            $code === 4, $code === 32, $code === 613 => 'Zu viele Beiträge in kurzer Zeit. Bitte warten Sie etwas.',
            default => 'Facebook meldet: '.mb_substr($message, 0, 200),
        };
    }
}
