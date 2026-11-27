<?php

namespace App\Licensing;

/** Verbindung zum Lizenzserver (austauschbar für Tests) */
interface Transport
{
    /** @return array<string, mixed> JSON-Antwort des Servers */
    public function post(string $path, array $data): array;

    /** Lädt eine Datei herunter und liefert den lokalen Pfad */
    public function download(string $path, array $data, string $target): void;
}
