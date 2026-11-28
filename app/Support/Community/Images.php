<?php

namespace App\Support\Community;

use App\Support\Security\Scanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bilder von Mitgliedern: werden immer neu gezeichnet (entfernt versteckte Inhalte und Standortdaten),
 * verkleinert und als JPEG oder PNG unter /uploads/community/ gespeichert.
 */
class Images
{
    private const PATTERN = '#^/uploads/community/\d{4}/\d{2}/[a-z0-9]{20}\.(jpg|png)$#';

    public static function store(UploadedFile $file, string $field, int $max = 1600, bool $square = false): string
    {
        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $src) {
            throw ValidationException::withMessages([$field => 'Das Bild konnte nicht gelesen werden. Bitte nehmen Sie ein JPG oder PNG.']);
        }
        $w = imagesx($src);
        $h = imagesy($src);
        [$sx, $sy, $sw, $sh] = [0, 0, $w, $h];
        if ($square) {
            $side = min($w, $h);
            [$sx, $sy, $sw, $sh] = [intdiv($w - $side, 2), intdiv($h - $side, 2), $side, $side];
        }
        $scale = min(1, $max / max($sw, $sh));
        $nw = max(1, (int) round($sw * $scale));
        $nh = max(1, (int) round($sh * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        $alpha = in_array($file->getMimeType(), ['image/png', 'image/webp', 'image/gif'], true);
        if ($alpha) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefilledrectangle($dst, 0, 0, $nw, $nh, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        }
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $nw, $nh, $sw, $sh);

        Scanner::protectUploads();
        $rel = '/uploads/community/'.now()->format('Y/m').'/'.Str::lower(Str::random(20)).($alpha ? '.png' : '.jpg');
        File::ensureDirectoryExists(dirname(public_path(ltrim($rel, '/'))));
        $alpha ? imagepng($dst, public_path(ltrim($rel, '/')), 8) : imagejpeg($dst, public_path(ltrim($rel, '/')), 84);
        imagedestroy($src);
        imagedestroy($dst);

        return $rel;
    }

    public static function delete(?string $rel): void
    {
        if (is_string($rel) && preg_match(self::PATTERN, $rel)) {
            File::delete(public_path(ltrim($rel, '/')));
        }
    }

    public static function valid(?string $rel): bool
    {
        return is_string($rel) && preg_match(self::PATTERN, $rel) === 1;
    }

    /** Validierungsregel für Uploads */
    public static function rule(): array
    {
        return ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'];
    }
}
