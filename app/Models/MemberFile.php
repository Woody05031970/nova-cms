<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Download im Mitgliederbereich, gespeichert außerhalb von public unter storage/app/members */
class MemberFile extends Model
{
    protected $fillable = ['title', 'path', 'original_name', 'size'];

    public function sizeLabel(): string
    {
        $kb = $this->size / 1024;

        return $kb >= 1024 ? number_format($kb / 1024, 1, ',', '.').' MB' : number_format(max(1, $kb), 0, ',', '.').' KB';
    }

    public function extension(): string
    {
        return strtoupper(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    protected static function booted(): void
    {
        static::deleted(fn (MemberFile $f) => Storage::disk('local')->delete($f->path));
    }
}
