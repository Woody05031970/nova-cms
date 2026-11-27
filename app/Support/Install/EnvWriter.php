<?php

namespace App\Support\Install;

use RuntimeException;

/**
 * Schreibt Werte sicher in die .env-Datei.
 */
class EnvWriter
{
    public function __construct(private ?string $path = null)
    {
        $this->path ??= config('nova.env_path');
    }

    /** @param array<string, string|bool|null> $values */
    public function set(array $values): void
    {
        $content = file_exists($this->path) ? file_get_contents($this->path) : '';

        foreach ($values as $key => $value) {
            if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                throw new RuntimeException("Ungültiger Schlüssel: {$key}");
            }

            $line = $key.'='.$this->format($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            $content = preg_match($pattern, $content)
                ? preg_replace_callback($pattern, fn () => $line, $content)
                : rtrim($content, "\n")."\n".$line."\n";
        }

        if (@file_put_contents($this->path, $content) === false) {
            throw new RuntimeException('Die Konfigurationsdatei (.env) ist nicht beschreibbar.');
        }
    }

    private function format(string|bool|null $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        // Zeilenumbrüche entfernen, damit niemand weitere Einträge einschleusen kann.
        $value = str_replace(["\r", "\n"], '', $value);

        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/\-@]+$/', $value)) {
            return $value;
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }
}
