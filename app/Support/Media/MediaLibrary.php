<?php

namespace App\Support\Media;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Speichert Dateien unter public/uploads (ohne Symlinks, läuft auf jedem Webspace),
 * verkleinert große Fotos und erzeugt Vorschaubilder.
 */
class MediaLibrary
{
    /** Größere Fotos werden auf diese Kantenlänge verkleinert. */
    public const MAX_EDGE = 2400;

    public const THUMB_EDGE = 480;

    public const TYPES = [
        'image' => ['mimes' => 'jpg,jpeg,png,webp,gif', 'max' => 10240, 'ext' => ['jpg', 'jpeg', 'png', 'webp', 'gif']],
        'video' => ['mimes' => 'mp4,webm', 'max' => 102400, 'ext' => ['mp4', 'webm']],
    ];

    public function store(UploadedFile $file, string $kind, ?int $userId = null): Media
    {
        $dir = 'uploads/'.now()->format('Y/m');
        $base = Str::limit(Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: $kind, 60, '');
        $name = $base.'-'.Str::lower(Str::random(6)).'.'.$file->guessExtension();
        $mime = (string) $file->getMimeType();

        \App\Support\Security\Scanner::protectUploads();
        $file->move(public_path($dir), $name);

        return $this->register($dir.'/'.$name, $kind, $file->getClientOriginalName(), $mime, $userId);
    }

    /** Erfasst eine Datei, die schon unter public/ liegt. */
    public function register(string $path, string $kind, ?string $originalName = null, ?string $mime = null, ?int $userId = null): Media
    {
        $full = public_path($path);
        $width = $height = null;
        $thumb = null;

        if ($kind === 'image') {
            $this->shrink($full);
            [$width, $height] = @getimagesize($full) ?: [null, null];
            $thumb = $this->thumbnail($path);
        }

        clearstatcache(true, $full);

        return Media::create([
            'kind' => $kind,
            'path' => $path,
            'thumb' => $thumb,
            'original_name' => Str::limit($originalName ?? basename($path), 250, ''),
            'mime' => $mime ?: (File::mimeType($full) ?: 'application/octet-stream'),
            'size' => (int) @filesize($full),
            'width' => $width,
            'height' => $height,
            'user_id' => $userId,
        ]);
    }

    /**
     * Übernimmt Dateien aus public/uploads, die noch nicht in der Mediathek stehen
     * (z. B. aus älteren Versionen oder per FTP hochgeladen).
     */
    public function syncFolder(): int
    {
        $root = public_path('uploads');
        if (! is_dir($root)) {
            return 0;
        }

        $known = Media::pluck('path')->merge(Media::whereNotNull('thumb')->pluck('thumb'))->flip();
        $added = 0;

        foreach (File::allFiles($root) as $file) {
            $path = 'uploads/'.str_replace('\\', '/', $file->getRelativePathname());
            $ext = strtolower($file->getExtension());
            $kind = in_array($ext, self::TYPES['image']['ext'], true) ? 'image' : (in_array($ext, self::TYPES['video']['ext'], true) ? 'video' : null);

            if (! $kind || isset($known[$path]) || str_contains($path, '/thumbs/')) {
                continue;
            }

            $this->register($path, $kind);
            $added++;
        }

        return $added;
    }

    public function delete(Media $media): void
    {
        foreach (array_filter([$media->path, $media->thumb]) as $path) {
            $full = public_path($path);
            // Nur innerhalb von public/uploads löschen
            if (str_starts_with(realpath($full) ?: '', realpath(public_path('uploads')) ?: '//') && is_file($full)) {
                @unlink($full);
            }
        }
        $media->delete();
    }

    /** Verkleinert sehr große Fotos (Handy-Fotos haben oft 4000 Pixel und 8 MB). */
    private function shrink(string $full): void
    {
        $info = @getimagesize($full);
        if (! $info || max($info[0], $info[1]) <= self::MAX_EDGE || ! function_exists('imagecreatetruecolor')) {
            return;
        }
        // GIFs können animiert sein und bleiben unverändert.
        if ($info[2] === IMAGETYPE_GIF) {
            return;
        }

        $resized = $this->resized($full, $info, self::MAX_EDGE);
        if (! $resized) {
            return;
        }

        match ($info[2]) {
            IMAGETYPE_PNG => imagepng($resized, $full, 7),
            IMAGETYPE_WEBP => imagewebp($resized, $full, 85),
            default => imagejpeg($resized, $full, 85),
        };
        imagedestroy($resized);
    }

    /** Legt ein kleines WebP-Vorschaubild unter uploads/…/thumbs/ an. */
    private function thumbnail(string $path): ?string
    {
        $full = public_path($path);
        $info = @getimagesize($full);
        if (! $info || ! function_exists('imagewebp') || $info[2] === IMAGETYPE_GIF) {
            return null;
        }
        if (max($info[0], $info[1]) <= self::THUMB_EDGE) {
            return null; // Original ist klein genug
        }

        $img = $this->resized($full, $info, self::THUMB_EDGE);
        if (! $img) {
            return null;
        }

        $thumbPath = dirname($path).'/thumbs/'.pathinfo($path, PATHINFO_FILENAME).'.webp';
        File::ensureDirectoryExists(public_path(dirname($thumbPath)));
        imagewebp($img, public_path($thumbPath), 80);
        imagedestroy($img);

        return $thumbPath;
    }

    /** @param  array<int|string, mixed>  $info */
    private function resized(string $full, array $info, int $edge): ?\GdImage
    {
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($full),
            IMAGETYPE_PNG => @imagecreatefrompng($full),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($full) : false,
            default => false,
        };
        if (! $src) {
            return null;
        }

        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $src = $this->orient($src, @exif_read_data($full) ?: []);
        }

        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, $edge / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);

        return $dst;
    }

    /** Handy-Fotos richtig herum drehen. */
    private function orient(\GdImage $img, array $exif): \GdImage
    {
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180, 6 => -90, 8 => 90, default => 0,
        };

        return $angle ? (imagerotate($img, $angle, 0) ?: $img) : $img;
    }
}
