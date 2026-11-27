<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Speichert Bilder und Videos direkt unter public/uploads, damit es auf jedem
 * Webspace ohne Symlinks funktioniert.
 */
class UploadController extends Controller
{
    private const TYPES = [
        'image' => ['mimes' => 'jpg,jpeg,png,webp,gif', 'max' => 10240],
        'video' => ['mimes' => 'mp4,webm', 'max' => 102400],
    ];

    public function store(Request $request): JsonResponse
    {
        $kind = $request->input('kind') === 'video' ? 'video' : 'image';
        $rules = self::TYPES[$kind];

        $request->validate([
            'file' => ['required', 'file', 'mimes:'.$rules['mimes'], 'max:'.$rules['max']],
        ], [
            'file.mimes' => $kind === 'image' ? 'Bitte laden Sie ein Bild hoch (JPG, PNG, WebP oder GIF).' : 'Bitte laden Sie ein Video im Format MP4 oder WebM hoch.',
            'file.max' => 'Die Datei ist zu groß (höchstens '.($rules['max'] / 1024).' MB).',
        ]);

        $file = $request->file('file');
        $dir = 'uploads/'.now()->format('Y/m');
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: $kind;
        $name = Str::limit($base, 60, '').'-'.Str::lower(Str::random(6)).'.'.$file->guessExtension();

        $file->move(public_path($dir), $name);

        return response()->json(['url' => '/'.$dir.'/'.$name]);
    }
}
