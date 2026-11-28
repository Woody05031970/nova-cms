<?php

namespace App\Support\Design;

use App\Models\Setting;
use App\Support\Security\Scanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Das kleine Bild im Browser-Tab, in Lesezeichen und auf dem Handy-Startbildschirm.
 *
 * Aus einem hochgeladenen Bild entstehen alle nötigen Größen als PNG (quadratisch, das Motiv wird
 * eingepasst, nichts abgeschnitten) und eine favicon.ico. Alles liegt unter /uploads/favicon/<id>/.
 */
class Favicon
{
    /** Größe => Hintergrund (null = durchsichtig). Apple zeigt Transparenz schwarz, darum weiß. */
    public const SIZES = [32 => null, 180 => '#ffffff', 192 => null, 512 => null];

    private const PATTERN = '#^/uploads/favicon/[a-z0-9]{12}$#';

    /** Ordner des aktuellen Favicons oder null */
    public static function current(): ?string
    {
        $v = Setting::get('design_favicon');

        return is_string($v) && preg_match(self::PATTERN, $v) && is_file(public_path(ltrim($v, '/').'/32.png')) ? $v : null;
    }

    /** Erzeugt alle Größen und gibt den Ordner zurück, z. B. /uploads/favicon/ab12cd34ef56 */
    public static function make(UploadedFile $file): string
    {
        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $src) {
            throw new RuntimeException('Das Bild konnte nicht gelesen werden.');
        }
        $w = imagesx($src);
        $h = imagesy($src);

        Scanner::protectUploads();
        $rel = '/uploads/favicon/'.Str::lower(Str::random(12));
        $dir = public_path(ltrim($rel, '/'));
        File::ensureDirectoryExists($dir);

        foreach (self::SIZES as $size => $bg) {
            $dst = imagecreatetruecolor($size, $size);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $fill = $bg ? imagecolorallocate($dst, ...sscanf($bg, '#%02x%02x%02x')) : imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $size, $size, $fill);
            imagealphablending($dst, true);
            // Etwas Rand bei weißem Hintergrund, sonst randlos einpassen
            $box = $bg ? (int) round($size * 0.84) : $size;
            $scale = min($box / $w, $box / $h);
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            imagecopyresampled($dst, $src, intdiv($size - $nw, 2), intdiv($size - $nh, 2), 0, 0, $nw, $nh, $w, $h);
            imagealphablending($dst, false);
            imagepng($dst, $dir.'/'.$size.'.png', 9);
            imagedestroy($dst);
        }
        imagedestroy($src);

        // favicon.ico mit eingebettetem PNG (versteht jeder heutige Browser)
        $png = (string) file_get_contents($dir.'/32.png');
        file_put_contents($dir.'/favicon.ico', pack('vvv', 0, 1, 1).pack('CCCCvvVV', 32, 32, 0, 0, 1, 32, strlen($png), 22).$png);

        return $rel;
    }

    /** Löscht einen Favicon-Ordner (nur echte Favicon-Ordner) */
    public static function remove(?string $rel): void
    {
        if (is_string($rel) && preg_match(self::PATTERN, $rel)) {
            File::deleteDirectory(public_path(ltrim($rel, '/')));
        }
    }

    /** Die Zeilen für den Kopf jeder Website-Seite */
    public static function tags(): string
    {
        $base = self::current();
        if (! $base) {
            return '';
        }
        $v = substr((string) filemtime(public_path(ltrim($base, '/').'/32.png')), -6);

        return '<link rel="icon" href="'.$base.'/favicon.ico?v='.$v.'" sizes="32x32">'
            .'<link rel="icon" type="image/png" sizes="192x192" href="'.$base.'/192.png?v='.$v.'">'
            .'<link rel="apple-touch-icon" href="'.$base.'/180.png?v='.$v.'">';
    }
}
