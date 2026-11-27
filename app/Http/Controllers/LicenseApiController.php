<?php

namespace App\Http\Controllers;

use App\Licensing\LicenseServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Öffentliche Schnittstelle des Lizenzservers für Nova-CMS-Installationen.
 */
class LicenseApiController extends Controller
{
    public function __construct(private readonly LicenseServer $server)
    {
        abort_unless(LicenseServer::enabled(), 404);
    }

    public function activate(Request $request): JsonResponse
    {
        $res = $this->server->activate($request->only('key', 'domain', 'install_id', 'version'));

        return response()->json($res, $res['ok'] ? 200 : 422);
    }

    public function release(Request $request): JsonResponse
    {
        return response()->json($this->server->release($request->only('key', 'install_id')));
    }

    public function catalog(): JsonResponse
    {
        return response()->json($this->server->catalog());
    }

    public function download(Request $request, string $slug): BinaryFileResponse|JsonResponse
    {
        $res = $this->server->download($slug, $request->only('key', 'install_id'));
        if (! $res['ok']) {
            return response()->json($res, 403);
        }

        return response()->download($res['path'], $slug.'.zip', ['Content-Type' => 'application/zip']);
    }
}
