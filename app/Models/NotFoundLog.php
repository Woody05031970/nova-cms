<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Aufrufe von Adressen, die es nicht gibt (z. B. alte Links von Google oder anderen Websites). */
class NotFoundLog extends Model
{
    protected $fillable = ['path', 'referer', 'hits', 'last_seen_at'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public static function record(string $path, ?string $referer): void
    {
        if (static::count() >= 2000 && ! static::where('path', $path)->exists()) {
            return; // Schutz gegen Fluten mit Zufallsadressen
        }
        $log = static::firstOrNew(['path' => mb_substr($path, 0, 500)]);
        $log->hits++;
        $log->last_seen_at = now();
        if ($referer) {
            $log->referer = mb_substr($referer, 0, 500);
        }
        $log->save();
    }
}
