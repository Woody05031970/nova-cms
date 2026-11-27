<?php

namespace App\Support\Blocks;

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

    public static function embed(string $provider, string $id): string
    {
        return $provider === 'youtube'
            ? 'https://www.youtube-nocookie.com/embed/'.$id.'?autoplay=1&rel=0'
            : 'https://player.vimeo.com/video/'.$id.'?autoplay=1&dnt=1';
    }
}
