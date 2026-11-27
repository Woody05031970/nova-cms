<?php

namespace App\Plugins;

use App\Licensing\License;
use App\Models\Plugin;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Throwable;
use ZipArchive;

/**
 * Installiert, aktiviert, deaktiviert und entfernt Plugins.
 *
 * Aufbau eines Plugins (Ordner plugins/<slug>/):
 *   plugin.json               Name, Version, Namensraum, Provider
 *   src/                      PHP-Klassen (PSR-4 unter dem Namensraum)
 *   resources/views/          Blade-Vorlagen (optional)
 *   database/migrations/      Datenbank-Änderungen (optional)
 */
class PluginManager
{
    private const MAX_ENTRIES = 2000;

    private const MAX_UNPACKED_BYTES = 50 * 1024 * 1024;

    /** @var array<string, ServiceProvider> */
    private array $booting = [];

    public function __construct(private Application $app) {}

    public function path(string $slug = ''): string
    {
        return rtrim(config('nova.plugins_path'), '/').($slug !== '' ? '/'.$slug : '');
    }

    /**
     * Findet alle Plugins im Plugin-Ordner.
     *
     * @return array{plugins: array<string, PluginManifest>, errors: array<string, string>}
     */
    public function discover(): array
    {
        $plugins = [];
        $errors = [];

        foreach (glob($this->path('*'), GLOB_ONLYDIR) ?: [] as $dir) {
            $folder = basename($dir);
            try {
                $manifest = PluginManifest::fromFile($dir.'/plugin.json');
                if ($manifest->slug !== $folder) {
                    throw new PluginException('Ordnername und Kurzname (slug) in plugin.json stimmen nicht überein.');
                }
                $plugins[$manifest->slug] = $manifest;
            } catch (PluginException $e) {
                $errors[$folder] = $e->getMessage();
            }
        }

        ksort($plugins);

        return ['plugins' => $plugins, 'errors' => $errors];
    }

    /**
     * Gleicht die Datenbank mit dem Plugin-Ordner ab (z. B. nach Upload per FTP).
     */
    public function sync(): void
    {
        $found = $this->discover()['plugins'];
        $state = $this->readState();

        foreach ($found as $slug => $m) {
            $plugin = Plugin::firstOrNew(['slug' => $slug]);
            $plugin->fill([
                'name' => $m->name, 'version' => $m->version,
                'description' => $m->description, 'author' => $m->author,
            ]);
            if (isset($state['errors'][$slug])) {
                $plugin->enabled = false;
                $plugin->last_error = $state['errors'][$slug];
            }
            $plugin->save();
        }

        Plugin::whereNotIn('slug', array_keys($found))->delete();

        $state['errors'] = [];
        $this->writeStateFromDatabase($state['errors']);
    }

    /**
     * Installiert ein Plugin aus einer ZIP-Datei. Das Plugin ist danach noch nicht aktiv.
     */
    public function installFromZip(string $zipPath, bool $replace = false, ?string $expectedSlug = null): PluginManifest
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new PluginException('Die Datei ist kein gültiges ZIP-Archiv.');
        }

        $tmp = storage_path('app/plugin-upload-'.bin2hex(random_bytes(8)));

        try {
            $prefix = $this->inspectZip($zip);
            File::ensureDirectoryExists($tmp);

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (! str_starts_with($name, $prefix)) {
                    continue;
                }
                $relative = substr($name, strlen($prefix));
                if ($relative === '' || str_ends_with($name, '/')) {
                    if ($relative !== '') {
                        File::ensureDirectoryExists($tmp.'/'.$relative);
                    }

                    continue;
                }
                File::ensureDirectoryExists(dirname($tmp.'/'.$relative));
                $stream = $zip->getStream($name);
                if ($stream === false) {
                    throw new PluginException('Eine Datei im Archiv konnte nicht gelesen werden.');
                }
                file_put_contents($tmp.'/'.$relative, $stream);
                fclose($stream);
            }
            $zip->close();

            $manifest = PluginManifest::fromFile($tmp.'/plugin.json');

            if (! $manifest->isCompatible(config('nova.version'))) {
                throw new PluginException("Dieses Plugin benötigt Nova CMS {$manifest->requires} oder neuer.");
            }

            if ($expectedSlug !== null && $manifest->slug !== $expectedSlug) {
                throw new PluginException('Das heruntergeladene Paket passt nicht zum gewählten Modul.');
            }

            $target = $this->path($manifest->slug);
            $backup = null;
            if (file_exists($target)) {
                if (! $replace) {
                    throw new PluginException("Das Plugin \"{$manifest->name}\" ist bereits installiert. Bitte entfernen Sie die alte Version zuerst.");
                }
                // Aktualisierung: alte Version erst beiseitelegen, damit bei Fehlern nichts verloren geht
                $backup = $target.'.alt-'.bin2hex(random_bytes(4));
                File::moveDirectory($target, $backup);
            }

            File::ensureDirectoryExists($this->path());
            if (! File::moveDirectory($tmp, $target)) {
                if ($backup) {
                    File::moveDirectory($backup, $target);
                }
                throw new PluginException('Das Plugin konnte nicht in den Plugin-Ordner verschoben werden.');
            }
            if ($backup) {
                File::deleteDirectory($backup);
            }

            $this->sync();
            if ($replace && Plugin::where('slug', $manifest->slug)->value('enabled')) {
                $this->runMigrations($manifest->slug);
            }

            return $manifest;
        } finally {
            if (is_dir($tmp)) {
                File::deleteDirectory($tmp);
            }
        }
    }

    public function activate(string $slug): void
    {
        $manifest = $this->manifest($slug);
        $plugin = Plugin::where('slug', $slug)->firstOrFail();

        if (! $manifest->isCompatible(config('nova.version'))) {
            throw new PluginException("Dieses Plugin benötigt Nova CMS {$manifest->requires} oder neuer.");
        }
        if ($manifest->paid && ! $this->app->make(License::class)->allows($slug)) {
            throw new PluginException("„{$manifest->name}“ ist ein kostenpflichtiges Modul. Für die Aktivierung brauchen Sie eine Lizenz, die dieses Modul enthält.");
        }

        try {
            $this->registerAutoloader($slug, $manifest->namespace);

            if (! class_exists($manifest->provider)) {
                throw new PluginException("Die Klasse {$manifest->provider} wurde nicht gefunden.");
            }
            if (! is_subclass_of($manifest->provider, ServiceProvider::class)) {
                throw new PluginException('Der Provider muss von Illuminate\Support\ServiceProvider erben.');
            }

            $this->runMigrations($slug);
        } catch (Throwable $e) {
            $plugin->update(['enabled' => false, 'last_error' => $e->getMessage()]);
            throw $e instanceof PluginException ? $e : new PluginException('Aktivierung fehlgeschlagen: '.$e->getMessage(), 0, $e);
        }

        $plugin->update(['enabled' => true, 'last_error' => null]);
        $this->writeStateFromDatabase();
    }

    public function deactivate(string $slug): void
    {
        Plugin::where('slug', $slug)->update(['enabled' => false]);
        $this->writeStateFromDatabase();
    }

    public function delete(string $slug): void
    {
        $plugin = Plugin::where('slug', $slug)->firstOrFail();
        if ($plugin->enabled) {
            throw new PluginException('Bitte deaktivieren Sie das Plugin, bevor Sie es entfernen.');
        }

        $dir = $this->path($slug);
        if (is_dir($dir) && ! File::deleteDirectory($dir)) {
            throw new PluginException('Der Plugin-Ordner konnte nicht gelöscht werden.');
        }

        $plugin->delete();
        $this->writeStateFromDatabase();
    }

    /**
     * Lädt aktive Plugins beim Start. Ein fehlerhaftes Plugin wird automatisch
     * abgeschaltet, damit die Website weiterläuft.
     */
    public function registerEnabled(): void
    {
        $state = $this->readState();

        foreach ($state['enabled'] as $slug => $info) {
            try {
                $this->registerAutoloader($slug, $info['namespace']);
                $provider = new $info['provider']($this->app);
                $provider->register();
                $this->booting[$slug] = $provider;
            } catch (Throwable $e) {
                $this->markBroken($slug, $e);
            }
        }
    }

    public function bootEnabled(): void
    {
        foreach ($this->booting as $slug => $provider) {
            try {
                if (method_exists($provider, 'boot')) {
                    $this->app->call([$provider, 'boot']);
                }
            } catch (Throwable $e) {
                $this->markBroken($slug, $e);
            }
        }
        $this->booting = [];
    }

    public function manifest(string $slug): PluginManifest
    {
        if (! preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new PluginException('Ungültiger Plugin-Name.');
        }

        return PluginManifest::fromFile($this->path($slug).'/plugin.json');
    }

    private function inspectZip(ZipArchive $zip): string
    {
        if ($zip->numFiles === 0 || $zip->numFiles > self::MAX_ENTRIES) {
            throw new PluginException('Das Archiv ist leer oder enthält zu viele Dateien.');
        }

        $total = 0;
        $tops = [];
        $hasRootManifest = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'];

            if ($name === '' || str_contains($name, '\\') || str_starts_with($name, '/') || str_contains($name, ':') || str_contains($name, "\0")) {
                throw new PluginException('Das Archiv enthält ungültige Dateinamen.');
            }
            if (in_array('..', explode('/', $name), true)) {
                throw new PluginException('Das Archiv enthält unzulässige Pfade.');
            }
            if ($zip->getExternalAttributesIndex($i, $opsys, $attr) && $opsys === ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) === 0120000) {
                throw new PluginException('Das Archiv enthält Verknüpfungen (Symlinks), die nicht erlaubt sind.');
            }

            $total += $stat['size'];
            if ($total > self::MAX_UNPACKED_BYTES) {
                throw new PluginException('Das entpackte Plugin wäre zu groß.');
            }

            if (str_starts_with($name, '__MACOSX/')) {
                continue;
            }
            $tops[explode('/', $name)[0]] = true;
            if ($name === 'plugin.json') {
                $hasRootManifest = true;
            }
        }

        if ($hasRootManifest) {
            return '';
        }

        unset($tops['__MACOSX']);
        if (count($tops) === 1) {
            $top = array_key_first($tops);
            if ($zip->locateName($top.'/plugin.json') !== false) {
                return $top.'/';
            }
        }

        throw new PluginException('Im Archiv wurde keine plugin.json gefunden.');
    }

    private function registerAutoloader(string $slug, string $namespace): void
    {
        $prefix = trim($namespace, '\\').'\\';
        $base = $this->path($slug).'/src/';

        spl_autoload_register(function (string $class) use ($prefix, $base) {
            if (! str_starts_with($class, $prefix)) {
                return;
            }
            $file = $base.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            if (is_file($file)) {
                require_once $file;
            }
        });
    }

    private function runMigrations(string $slug): void
    {
        $dir = $this->path($slug).'/database/migrations';
        if (is_dir($dir)) {
            Artisan::call('migrate', ['--path' => realpath($dir), '--realpath' => true, '--force' => true]);
        }
    }

    private function markBroken(string $slug, Throwable $e): void
    {
        report($e);
        $state = $this->readState();
        unset($state['enabled'][$slug]);
        $state['errors'][$slug] = 'Automatisch deaktiviert: '.$e->getMessage();
        $this->writeState($state);
        unset($this->booting[$slug]);
    }

    /** @return array{enabled: array<string, array{namespace: string, provider: string}>, errors: array<string, string>} */
    private function readState(): array
    {
        $file = config('nova.plugins_state');
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return [
            'enabled' => is_array($data['enabled'] ?? null) ? $data['enabled'] : [],
            'errors' => is_array($data['errors'] ?? null) ? $data['errors'] : [],
        ];
    }

    /** @param array<string, string> $errors */
    private function writeStateFromDatabase(array $errors = []): void
    {
        $enabled = [];
        foreach (Plugin::where('enabled', true)->orderBy('slug')->get() as $plugin) {
            try {
                $m = $this->manifest($plugin->slug);
                $enabled[$plugin->slug] = ['namespace' => $m->namespace, 'provider' => $m->provider];
            } catch (PluginException) {
                $plugin->update(['enabled' => false, 'last_error' => 'plugin.json fehlt oder ist fehlerhaft.']);
            }
        }

        $this->writeState(['enabled' => $enabled, 'errors' => $errors]);
    }

    /** @param array{enabled: array<string, mixed>, errors: array<string, string>} $state */
    private function writeState(array $state): void
    {
        $file = config('nova.plugins_state');
        File::ensureDirectoryExists(dirname($file));
        file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
