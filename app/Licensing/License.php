<?php

namespace App\Licensing;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Lizenz dieser Installation: Status, Testzeitraum, freigeschaltete Module.
 * Die Lizenz wird beim Server aktiviert und danach regelmäßig (höchstens einmal täglich) bestätigt.
 */
class License
{
    public const STATUS_DEV = 'dev';            // kein öffentlicher Schlüssel konfiguriert: keine Prüfung

    public const STATUS_ACTIVE = 'active';

    public const STATUS_TRIAL = 'trial';

    public const STATUS_TRIAL_OVER = 'trial_over';

    public const STATUS_INVALID = 'invalid';    // gesperrt, abgelaufen oder manipuliert

    private ?array $payload = null;

    private bool $loaded = false;

    public function __construct(private readonly Transport $transport) {}

    public static function enforced(): bool
    {
        return (string) config('nova.license.public_key') !== '';
    }

    public function key(): ?string
    {
        return Setting::get('license_key');
    }

    /** Die zuletzt vom Server unterschriebenen Angaben, wenn Signatur und Installation passen */
    public function payload(): ?array
    {
        if (! $this->loaded) {
            $this->loaded = true;
            $data = Signer::open(['payload' => Setting::get('license_payload'), 'signature' => Setting::get('license_signature')]);
            $this->payload = $data && ($data['key'] ?? null) === $this->key()
                && (($data['status'] ?? '') === 'revoked' || ($data['install_id'] ?? null) === self::installId())
                ? $data : null;
        }

        return $this->payload;
    }

    public function status(): string
    {
        if (! self::enforced()) {
            return self::STATUS_DEV;
        }
        $p = $this->payload();
        if ($p) {
            if (($p['status'] ?? '') !== 'active') {
                return self::STATUS_INVALID;
            }
            $graceEnd = ((int) ($p['valid_until'] ?? 0)) + config('nova.license.offline_grace_days') * 86400;

            return now()->getTimestamp() <= $graceEnd ? self::STATUS_ACTIVE : self::STATUS_INVALID;
        }
        if ($this->key()) {
            return self::STATUS_INVALID;
        }

        return $this->trialDaysLeft() > 0 ? self::STATUS_TRIAL : self::STATUS_TRIAL_OVER;
    }

    public function isActive(): bool
    {
        return in_array($this->status(), [self::STATUS_ACTIVE, self::STATUS_DEV], true);
    }

    /** Darf die Verwaltung genutzt werden? */
    public function adminUsable(): bool
    {
        return ! config('nova.license.lock_after_trial')
            || in_array($this->status(), [self::STATUS_ACTIVE, self::STATUS_DEV, self::STATUS_TRIAL], true);
    }

    /** Ist ein kostenpflichtiges Modul freigeschaltet? */
    public function allows(string $module): bool
    {
        if ($this->status() === self::STATUS_DEV) {
            return true;
        }

        return $this->status() === self::STATUS_ACTIVE && in_array($module, $this->payload()['modules'] ?? [], true);
    }

    public function trialDaysLeft(): int
    {
        $end = self::installedAt()->addDays((int) config('nova.license.trial_days'));

        return max(0, (int) ceil(now()->diffInSeconds($end, false) / 86400));
    }

    public function updatesUntil(): ?CarbonImmutable
    {
        $d = $this->payload()['updates_until'] ?? null;

        return $d ? CarbonImmutable::parse($d) : null;
    }

    /** Lizenz eingeben und beim Server aktivieren */
    public function activate(string $key): void
    {
        $key = LicenseKey::normalize($key);
        if (! LicenseKey::isValid($key)) {
            throw new RuntimeException('Dieser Lizenzschlüssel ist ungültig. Er sieht so aus: NOVA-XXXX-XXXX-XXXX-XXXX.');
        }
        $res = $this->transport->post('aktivieren', $this->request($key));
        if (! ($res['ok'] ?? false)) {
            throw new RuntimeException((string) ($res['error'] ?? 'Die Lizenz konnte nicht aktiviert werden.'));
        }
        $this->store($key, $res['license'] ?? null);
    }

    /** Erneute Bestätigung beim Server. Ohne Verbindung bleibt die letzte gültige Bestätigung bestehen. */
    public function refresh(bool $force = false): void
    {
        $key = $this->key();
        if (! $key || ! self::enforced()) {
            return;
        }
        $last = (int) Setting::get('license_checked_at', '0');
        if (! $force && now()->getTimestamp() - $last < 86400) {
            return;
        }
        Setting::put('license_checked_at', (string) now()->getTimestamp());

        try {
            $res = $this->transport->post('aktivieren', $this->request($key));
        } catch (Throwable $e) {
            Setting::put('license_last_error', $e->getMessage());

            return;
        }
        if (($res['ok'] ?? false) || ($res['code'] ?? '') === 'revoked') {
            $this->store($key, $res['license'] ?? null, keepOnFailure: true);
        } else {
            Setting::put('license_last_error', (string) ($res['error'] ?? 'Unbekannter Fehler'));
        }
    }

    public function remove(): void
    {
        if ($key = $this->key()) {
            try {
                $this->transport->post('freigeben', ['key' => $key, 'install_id' => self::installId()]);
            } catch (Throwable) {
                // Freigabe ist ein Komfort; auch ohne Verbindung wird die Lizenz lokal entfernt
            }
        }
        Setting::putMany(['license_key' => null, 'license_payload' => null, 'license_signature' => null, 'license_last_error' => null]);
        $this->loaded = false;
    }

    /** @return array<string, mixed> */
    public function request(string $key): array
    {
        return [
            'key' => $key,
            'domain' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: request()->getHost(),
            'install_id' => self::installId(),
            'version' => config('nova.version'),
        ];
    }

    private function store(string $key, mixed $envelope, bool $keepOnFailure = false): void
    {
        $data = Signer::open($envelope);
        if (! $data || ($data['key'] ?? null) !== $key) {
            if ($keepOnFailure) {
                Setting::put('license_last_error', 'Die Antwort des Lizenzservers war nicht gültig unterschrieben.');

                return;
            }
            throw new RuntimeException('Die Antwort des Lizenzservers war nicht gültig unterschrieben. Bitte wenden Sie sich an den Support.');
        }
        Setting::putMany([
            'license_key' => $key,
            'license_payload' => $envelope['payload'],
            'license_signature' => $envelope['signature'],
            'license_last_error' => null,
        ]);
        $this->loaded = false;
    }

    public static function installId(): string
    {
        $id = Setting::get('install_id');
        if (! $id) {
            $id = bin2hex(random_bytes(16));
            Setting::put('install_id', $id);
        }

        return $id;
    }

    public static function installedAt(): CarbonImmutable
    {
        $at = Setting::get('installed_at');
        if ($at) {
            return CarbonImmutable::parse($at);
        }
        $lock = config('nova.installed_lock');
        $ts = is_file($lock) ? filemtime($lock) : time();
        Setting::put('installed_at', CarbonImmutable::createFromTimestamp($ts)->toIso8601String());

        return CarbonImmutable::createFromTimestamp($ts);
    }

    public static function label(string $status): string
    {
        return [
            self::STATUS_DEV => 'Entwicklerversion',
            self::STATUS_ACTIVE => 'Lizenziert',
            self::STATUS_TRIAL => 'Testversion',
            self::STATUS_TRIAL_OVER => 'Testzeitraum abgelaufen',
            self::STATUS_INVALID => 'Lizenz ungültig',
        ][$status] ?? $status;
    }

    public static function mask(string $key): string
    {
        return Str::substr($key, 0, 9).'••••-••••-'.Str::substr($key, -4);
    }
}
