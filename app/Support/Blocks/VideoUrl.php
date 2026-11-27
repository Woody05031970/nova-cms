<?php

namespace App\Support\Blocks;

use App\Models\Media;
use App\Support\Media\MediaLibrary;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class VideoUrl
{
    /** @return array{provider: string, id: string}|null */
    public static function parse(string $url): ?array
    {
        if (preg_match('#^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $url, $m)) {
            return ['provider' => 'youtube', 'id' => $m[1]];
        }
        if (preg_match('#^https?://(?:www\.|player\.)?vimeo\.com/(?:video/)?(\d{6,12})#', $url, $m)) {
            return ['provider' => 'vimeo', 'id' => $m[1]];
        }

        return null;
    }

    /**
     * Vorschaubild von YouTube oder Vimeo herunterladen und in der Mediathek speichern,
     * damit Besucher vor dem Abspielen keine Verbindung zu Google oder Vimeo aufbauen.
     */
    public static function fetchThumbnail(string $url, ?int $userId = null): ?Media
    {
        $parsed = self::parse($url);
        if (! $parsed) {
            return null;
        }
        $candidates = $parsed['provider'] === 'youtube'
            ? ['https://i.ytimg.com/vi/'.$parsed['id'].'/maxresdefault.jpg', 'https://i.ytimg.com/vi/'.$parsed['id'].'/hqdefault.jpg']
            : array_filter([Http::timeout(8)->get('https://vimeo.com/api/oembed.json', ['url' => 'https://vimeo.com/'.$parsed['id'], 'width' => 1280])->json('thumbnail_url')]);
        foreach ($candidates as $src) {
            $res = Http::timeout(10)->get($src);
            $body = $res->body();
            // YouTube liefert für fehlende Größen ein graues 120-px-Bild
            if (! $res->successful() || strlen($body) < 2000 || ! str_starts_with((string) $res->header('Content-Type'), 'image/')) {
                continue;
            }
            $dir = 'uploads/'.now()->format('Y/m');
            $name = 'video-'.$parsed['provider'].'-'.$parsed['id'].'-'.Str::lower(Str::random(4)).'.jpg';
            File::ensureDirectoryExists(public_path($dir));
            file_put_contents(public_path($dir.'/'.$name), $body);

            return app(MediaLibrary::class)->register($dir.'/'.$name, 'image', 'Vorschaubild '.$parsed['provider'].' '.$parsed['id'].'.jpg', 'image/jpeg', $userId);
        }

        return null;
    }

    public static function embed(string $provider, string $id): string
    {
        return $provider === 'youtube'
            ? 'https://www.youtube-nocookie.com/embed/'.$id.'?autoplay=1&rel=0'
            : 'https://player.vimeo.com/video/'.$id.'?autoplay=1&dnt=1';
    }
}
