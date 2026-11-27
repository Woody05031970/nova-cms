<?php

namespace App\Licensing;

use App\Models\Plugin;
use App\Plugins\PluginException;
use App\Plugins\PluginManager;
use App\Plugins\PluginManifest;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Modul-Marktplatz aus Sicht einer Installation: Katalog laden, Module installieren und aktualisieren.
 * Jede Datei wird gegen die vom Server unterschriebene Prüfsumme geprüft.
 */
class Marketplace
{
    public function __construct(
        private readonly Transport $transport,
        private readonly License $license,
        private readonly PluginManager $plugins,
    ) {}

    /** @return list<array<string, mixed>> */
    public function modules(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget('nova.marketplace');
        }

        return Cache::remember('nova.marketplace', 3600, function () {
            $res = $this->transport->post('marktplatz', ['version' => config('nova.version')]);
            $data = Signer::open($res['catalog'] ?? null);
            if (! $data && License::enforced()) {
                throw new RuntimeException('Der Katalog des Marktplatzes ist nicht gültig unterschrieben.');
            }
            // Im Entwicklermodus (ohne Schlüssel) wird der Katalog ungeprüft angezeigt
            $data ??= json_decode((string) ($res['catalog']['payload'] ?? '[]'), true) ?: [];

            return array_values(array_filter($data['modules'] ?? [], 'is_array'));
        });
    }

    /** Katalog mit Installationsstand dieser Website */
    public function overview(): array
    {
        $installed = Plugin::all()->keyBy('slug');

        return array_map(function (array $m) use ($installed) {
            $local = $installed->get($m['slug']);
            $m['installed'] = $local !== null;
            $m['installed_version'] = $local?->version;
            $m['enabled'] = (bool) $local?->enabled;
            $m['update'] = $local && version_compare((string) $m['version'], (string) $local->version, '>');
            $m['licensed'] = ! ($m['paid'] ?? false) || $this->license->allows($m['slug']);
            $m['compatible'] = empty($m['requires']) || version_compare(config('nova.version'), (string) $m['requires'], '>=');

            return $m;
        }, $this->modules());
    }

    public function install(string $slug): PluginManifest
    {
        $module = collect($this->modules(true))->firstWhere('slug', $slug);
        if (! $module) {
            throw new RuntimeException('Dieses Modul gibt es im Marktplatz nicht.');
        }
        if (($module['paid'] ?? false) && ! $this->license->allows($slug)) {
            throw new RuntimeException("„{$module['name']}“ ist kostenpflichtig. Nach dem Kauf wird es mit Ihrer Lizenz freigeschaltet.");
        }

        $tmp = storage_path('app/market-'.bin2hex(random_bytes(6)).'.zip');
        try {
            $this->transport->download('marktplatz/'.$slug, $this->license->key()
                ? ['key' => $this->license->key(), 'install_id' => License::installId()]
                : ['install_id' => License::installId()], $tmp);

            if (! is_file($tmp) || ! hash_equals((string) $module['sha256'], hash_file('sha256', $tmp))) {
                throw new RuntimeException('Die heruntergeladene Datei ist beschädigt oder wurde verändert. Sie wurde nicht installiert.');
            }

            try {
                return $this->plugins->installFromZip($tmp, replace: true, expectedSlug: $slug);
            } catch (PluginException $e) {
                throw new RuntimeException($e->getMessage(), 0, $e);
            }
        } finally {
            @unlink($tmp);
        }
    }
}
