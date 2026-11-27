<?php

namespace App\Licensing;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpTransport implements Transport
{
    private function url(string $path): string
    {
        $base = rtrim((string) config('nova.license.server'), '/');
        if (! str_starts_with($base, 'https://') && ! app()->environment('local', 'testing')) {
            throw new RuntimeException('Der Lizenzserver muss über https erreichbar sein.');
        }

        return $base.'/api/nova/'.ltrim($path, '/');
    }

    public function post(string $path, array $data): array
    {
        try {
            $res = Http::acceptJson()->timeout(15)->post($this->url($path), $data);
        } catch (\Throwable) {
            throw new RuntimeException('Der Lizenzserver ist gerade nicht erreichbar. Bitte versuchen Sie es später erneut.');
        }
        $json = $res->json();
        if (! is_array($json)) {
            throw new RuntimeException('Der Lizenzserver hat unerwartet geantwortet (HTTP '.$res->status().').');
        }

        return $json;
    }

    public function download(string $path, array $data, string $target): void
    {
        try {
            $res = Http::timeout(60)->sink($target)->post($this->url($path), $data);
        } catch (\Throwable) {
            throw new RuntimeException('Der Download ist fehlgeschlagen. Bitte versuchen Sie es später erneut.');
        }
        if (! $res->successful()) {
            @unlink($target);
            $msg = json_decode((string) $res->body(), true)['error'] ?? null;
            throw new RuntimeException($msg ?: 'Der Download wurde abgelehnt (HTTP '.$res->status().').');
        }
    }
}
