<?php

namespace App\Licensing;

use RuntimeException;

/**
 * Spricht direkt mit dem LicenseServer im selben Programm, ohne HTTP.
 * Für Tests und für den Lizenzserver selbst (er prüft seine eigene Lizenz lokal).
 */
class LocalTransport implements Transport
{
    public function __construct(private readonly LicenseServer $server) {}

    public function post(string $path, array $data): array
    {
        return match ($path) {
            'aktivieren' => $this->server->activate($data),
            'freigeben' => $this->server->release($data),
            'marktplatz' => $this->server->catalog(),
            default => ['ok' => false, 'error' => 'Unbekannt'],
        };
    }

    public function download(string $path, array $data, string $target): void
    {
        $slug = str_replace('marktplatz/', '', $path);
        $res = $this->server->download($slug, $data);
        if (! $res['ok']) {
            throw new RuntimeException($res['error']);
        }
        copy($res['path'], $target);
    }
}
