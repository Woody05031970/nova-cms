<?php

namespace App\Http\Controllers\Admin;

use App\Ai\AiException;
use App\Ai\AiManager;
use App\Ai\ContentAssistant;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Support\Media\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function index(Request $request, MediaLibrary $library): View
    {
        $library->syncFolder();

        return view('admin.media.index', [
            'items' => $this->query($request)->get()->map->toPicker(),
            'missingAlt' => Media::where('kind', 'image')->where(fn ($q) => $q->whereNull('alt')->orWhere('alt', ''))->count(),
            'ai' => app(AiManager::class)->isConfigured(),
        ]);
    }

    /** Für die Auswahl im Editor. */
    public function list(Request $request): JsonResponse
    {
        return response()->json($this->query($request)->limit(300)->get()->map->toPicker());
    }

    public function show(Media $media): JsonResponse
    {
        return response()->json($media->toPicker() + [
            'title' => $media->title,
            'original_name' => $media->original_name,
            'created' => $media->created_at?->format('d.m.Y, H:i'),
            'user' => $media->user?->name,
            'usages' => $media->usages()->map(fn ($p) => [
                'title' => $p->title.($p->deleted_at ? ' (Papierkorb)' : ''),
                'url' => $p->deleted_at ? null : route('admin.pages.edit', $p->id),
            ]),
        ]);
    }

    public function update(Request $request, Media $media): JsonResponse
    {
        $data = $request->validate([
            'alt' => ['nullable', 'string', 'max:200'],
            'title' => ['nullable', 'string', 'max:160'],
        ]);
        $media->update($data);

        return response()->json($media->toPicker());
    }

    public function destroy(Request $request, Media $media, MediaLibrary $library): JsonResponse|RedirectResponse
    {
        $usages = $media->usages();
        if ($usages->isNotEmpty() && ! $request->boolean('trotzdem')) {
            return response()->json([
                'message' => 'Die Datei wird noch verwendet auf: '.$usages->pluck('title')->implode(', ').'. Dort würde sie nach dem Löschen fehlen.',
                'in_use' => true,
            ], 409);
        }

        $library->delete($media);

        return response()->json(['deleted' => true]);
    }

    public function describe(Media $media, ContentAssistant $assistant): JsonResponse
    {
        if (! $media->isImage()) {
            return response()->json(['message' => 'Nur Bilder können beschrieben werden.'], 422);
        }
        if (! app(AiManager::class)->isConfigured()) {
            return response()->json(['message' => 'Es ist noch kein KI-Anbieter eingerichtet.', 'setup' => route('admin.ai')], 422);
        }

        // Das kleine Vorschaubild reicht der KI und spart Kosten.
        $file = public_path($media->thumb ?: $media->path);
        $mime = $media->thumb ? 'image/webp' : $media->mime;
        if (! is_file($file)) {
            return response()->json(['message' => 'Die Bilddatei fehlt auf dem Server.'], 422);
        }

        try {
            $alt = $assistant->altText((string) file_get_contents($file), $mime);
        } catch (AiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $media->update(['alt' => $alt]);

        return response()->json(['alt' => $alt]);
    }

    private function query(Request $request)
    {
        $kind = $request->query('art');
        $search = trim((string) $request->query('suche'));

        return Media::query()
            ->when(in_array($kind, ['image', 'video'], true), fn ($q) => $q->where('kind', $kind))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('original_name', 'like', "%{$search}%")
                ->orWhere('alt', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")))
            ->latest('id');
    }
}
