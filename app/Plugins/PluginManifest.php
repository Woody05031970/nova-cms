<?php

namespace App\Plugins;

/**
 * Inhalt der Datei plugin.json eines Plugins.
 */
final class PluginManifest
{
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $version,
        public readonly string $namespace,
        public readonly string $provider,
        public readonly ?string $description = null,
        public readonly ?string $author = null,
        public readonly ?string $requires = null,
    ) {}

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new PluginException('Die Datei plugin.json fehlt.');
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            throw new PluginException('Die Datei plugin.json ist kein gültiges JSON.');
        }

        return self::fromArray($data);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        foreach (['slug', 'name', 'version', 'namespace', 'provider'] as $field) {
            if (! isset($data[$field]) || ! is_string($data[$field]) || trim($data[$field]) === '') {
                throw new PluginException("In plugin.json fehlt die Angabe \"{$field}\".");
            }
        }

        if (! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $data['slug']) || strlen($data['slug']) > 64) {
            throw new PluginException('Der Plugin-Kurzname (slug) darf nur Kleinbuchstaben, Ziffern und Bindestriche enthalten.');
        }

        $namespace = trim($data['namespace'], '\\');
        if (! preg_match('/^[A-Z][A-Za-z0-9]*(\\\\[A-Z][A-Za-z0-9]*)*$/', $namespace) || str_starts_with($namespace, 'App\\') || $namespace === 'App' || str_starts_with($namespace, 'Illuminate')) {
            throw new PluginException('Der Namensraum (namespace) des Plugins ist ungültig.');
        }

        $provider = trim($data['provider'], '\\');
        if (! str_starts_with($provider, $namespace.'\\')) {
            throw new PluginException('Der Provider muss im Namensraum des Plugins liegen.');
        }

        return new self(
            slug: $data['slug'],
            name: mb_substr($data['name'], 0, 120),
            version: mb_substr($data['version'], 0, 32),
            namespace: $namespace,
            provider: $provider,
            description: isset($data['description']) ? mb_substr((string) $data['description'], 0, 500) : null,
            author: isset($data['author']) ? mb_substr((string) $data['author'], 0, 120) : null,
            requires: isset($data['requires']) ? (string) $data['requires'] : null,
        );
    }

    public function isCompatible(string $coreVersion): bool
    {
        return $this->requires === null || version_compare($coreVersion, $this->requires, '>=');
    }
}
