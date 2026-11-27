<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Media\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Hochladen aus dem Editor und der Mediathek. Jede Datei landet in der Mediathek.
 */
class UploadController extends Controller
{
    public function store(Request $request, MediaLibrary $library): JsonResponse
    {
        $kind = $request->input('kind') === 'video' ? 'video' : 'image';
        $rules = MediaLibrary::TYPES[$kind];

        $request->validate([
            'file' => ['required', 'file', 'mimes:'.$rules['mimes'], 'max:'.$rules['max']],
        ], [
            'file.mimes' => $kind === 'image' ? 'Bitte laden Sie ein Bild hoch (JPG, PNG, WebP oder GIF).' : 'Bitte laden Sie ein Video im Format MP4 oder WebM hoch.',
            'file.max' => 'Die Datei ist zu groß (höchstens '.($rules['max'] / 1024).' MB).',
            'file.uploaded' => 'Die Datei konnte nicht hochgeladen werden. Vermutlich ist sie größer, als Ihr Webspace erlaubt.',
        ]);

        $media = $library->store($request->file('file'), $kind, $request->user()->id);

        return response()->json($media->toPicker());
    }
}
