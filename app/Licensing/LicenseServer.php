<?php

namespace App\Licensing;

use App\Models\IssuedLicense;
use App\Models\MarketModule;
use Illuminate\Support\Str;

/**
 * Die Logik des Lizenzservers. Antworten sind unterschrieben (Signer::envelope),
 * damit Installationen sie nicht fälschen lassen können.
 */
class LicenseServer
{
    /** Wie lange eine Bestätigung ohne neue Rückfrage gilt */
    public const VALID_DAYS = 7;

    public static function enabled(): bool
    {
        return (string) config('nova.license.private_key') !== '';
    }

    /**
     * Aktiviert eine Lizenz für eine Installation oder bestätigt sie erneut.
     *
     * @param  array{key?: string, domain?: string, install_id?: string, version?: string}  $req
     * @return array{ok: bool, error?: string, license?: array}
     */
    public function activate(array $req): array
    {
        $key = LicenseKey::normalize((string) ($req['key'] ?? ''));
        $domain = self::domain((string) ($req['domain'] ?? ''));
        $install = (string) ($req['install_id'] ?? '');

        if (! LicenseKey::isValid($key)) {
            return $this->error('Dieser Lizenzschlüssel ist ungültig. Bitte prüfen Sie die Schreibweise.');
        }
        if ($domain === '' || ! preg_match('/^[a-f0-9]{32,64}$/', $install)) {
            return $this->error('Die Anfrage ist unvollständig.');
        }

        $license = IssuedLicense::where('key', $key)->first();
        if (! $license) {
            return $this->error('Diesen Lizenzschlüssel gibt es nicht.');
        }
        if (! $license->isActive()) {
            return $this->error('Diese Lizenz wurde gesperrt. Bitte wenden Sie sich an den Support.', 'revoked', $license);
        }

        $activation = $license->activations()->where('install_id', $install)->first();
        if (! $activation) {
            $isLocal = self::isLocal($domain);
            $used = $license->activations()->get()->reject(fn ($a) => self::isLocal($a->domain))->count();
            if (! $isLocal && $used >= $license->max_domains) {
                return $this->error("Diese Lizenz ist bereits auf {$used} Website(s) aktiv. Geben Sie sie dort frei oder erweitern Sie Ihre Lizenz.");
            }
            $activation = $license->activations()->create(['domain' => $domain, 'install_id' => $install]);
        }
        $activation->update(['domain' => $domain, 'version' => Str::limit((string) ($req['version'] ?? ''), 20, ''), 'last_seen_at' => now()]);

        return ['ok' => true, 'license' => Signer::envelope($this->payload($license, $domain, $install))];
    }

    /** Gibt den Platz einer Installation frei (z. B. beim Umzug auf eine neue Domain) */
    public function release(array $req): array
    {
        $license = IssuedLicense::where('key', LicenseKey::normalize((string) ($req['key'] ?? '')))->first();
        $license?->activations()->where('install_id', (string) ($req['install_id'] ?? ''))->delete();

        return ['ok' => true];
    }

    /** Katalog aller veröffentlichten Module, unterschrieben */
    public function catalog(): array
    {
        $modules = MarketModule::where('is_published', true)->orderBy('name')->get()->map(fn (MarketModule $m) => [
            'slug' => $m->slug, 'name' => $m->name, 'description' => $m->description, 'category' => $m->category,
            'version' => $m->version, 'requires' => $m->requires, 'paid' => $m->is_paid, 'price_cents' => $m->price_cents,
            'buy_url' => $m->buy_url, 'sha256' => $m->sha256,
        ])->values()->all();

        return ['ok' => true, 'catalog' => Signer::envelope(['modules' => $modules, 'issued_at' => now()->getTimestamp()])];
    }

    /**
     * Pfad zur ZIP-Datei eines Moduls, wenn der Abruf erlaubt ist.
     *
     * @return array{ok: bool, error?: string, path?: string, module?: MarketModule}
     */
    public function download(string $slug, array $req): array
    {
        $module = MarketModule::where('slug', $slug)->where('is_published', true)->first();
        if (! $module || ! is_file($module->path())) {
            return $this->error('Dieses Modul gibt es nicht.');
        }

        if ($module->is_paid) {
            $license = IssuedLicense::where('key', LicenseKey::normalize((string) ($req['key'] ?? '')))->first();
            $activated = $license?->activations()->where('install_id', (string) ($req['install_id'] ?? ''))->exists();
            if (! $license || ! $license->isActive() || ! $activated || ! in_array($slug, $license->modules ?? [], true)) {
                return $this->error('Für dieses Modul ist keine Lizenz vorhanden.');
            }
        }
        $module->increment('downloads');

        return ['ok' => true, 'path' => $module->path(), 'module' => $module];
    }

    private function payload(IssuedLicense $license, string $domain, string $install): array
    {
        return [
            'key' => $license->key,
            'status' => $license->status,
            'customer' => $license->customer_name,
            'core' => $license->core,
            'modules' => array_values($license->modules ?? []),
            'domain' => $domain,
            'install_id' => $install,
            'updates_until' => $license->updates_until?->toDateString(),
            'issued_at' => now()->getTimestamp(),
            'valid_until' => now()->addDays(self::VALID_DAYS)->getTimestamp(),
        ];
    }

    private function error(string $message, string $code = 'error', ?IssuedLicense $license = null): array
    {
        $out = ['ok' => false, 'error' => $message, 'code' => $code];
        if ($code === 'revoked' && $license) {
            // Auch Sperren werden unterschrieben, damit Installationen sie sicher erkennen
            $out['license'] = Signer::envelope(['key' => $license->key, 'status' => 'revoked', 'issued_at' => now()->getTimestamp()]);
        }

        return $out;
    }

    public static function domain(string $value): string
    {
        $host = parse_url(str_contains($value, '://') ? $value : 'http://'.$value, PHP_URL_HOST) ?: '';
        $host = strtolower(preg_replace('/^www\./i', '', $host));

        return preg_match('/^[a-z0-9.\-]{1,190}$/', $host) ? $host : '';
    }

    /** Test- und Entwicklungsumgebungen zählen nicht gegen die Anzahl der Websites */
    public static function isLocal(string $domain): bool
    {
        return in_array($domain, ['localhost', '127.0.0.1'], true)
            || preg_match('/\.(local|test|localhost|invalid|example)$/', $domain) === 1
            || str_starts_with($domain, 'staging.') || str_starts_with($domain, 'test.');
    }
}
