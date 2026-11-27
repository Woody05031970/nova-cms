<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Weiterleitung einer alten Adresse auf eine neue (z. B. nach Umbenennen einer Seite). */
class Redirect extends Model
{
    protected $fillable = ['from_path', 'to_url', 'status', 'automatic', 'hits', 'last_hit_at'];

    protected function casts(): array
    {
        return ['automatic' => 'boolean', 'last_hit_at' => 'datetime'];
    }

    /** Einheitliche Form: führender Schrägstrich, ohne Schrägstrich am Ende, ohne Domain */
    public static function normalize(string $path): string
    {
        $path = trim($path);
        if (preg_match('#^https?://#i', $path)) {
            $path = (string) parse_url($path, PHP_URL_PATH);
        }
        $path = '/'.trim(rawurldecode(strtok($path, '?#') ?: ''), '/');

        return mb_strtolower($path);
    }

    /** Legt eine automatische Weiterleitung an und vermeidet Ketten (A → B → C wird A → C). */
    public static function remember(string $from, string $to): void
    {
        $from = self::normalize($from);
        $toPath = self::normalize($to);
        if ($from === $toPath || $from === '/') {
            return;
        }
        static::where('to_url', $from)->update(['to_url' => $toPath]);
        static::where('from_path', $toPath)->delete(); // Die neue Adresse existiert jetzt wirklich
        static::updateOrCreate(['from_path' => $from], ['to_url' => $toPath, 'status' => 301, 'automatic' => true]);
        NotFoundLog::where('path', $from)->delete();
    }
}
