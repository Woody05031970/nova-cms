<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEvent extends Model
{
    public const UPDATED_AT = null;

    /** Wie lange Ereignisse (mit IP-Adressen) aufbewahrt werden. Datensparsam nach DSGVO. */
    public const KEEP_DAYS = 30;

    public const LABELS = [
        'login_failed' => 'Anmeldung fehlgeschlagen',
        'login' => 'Anmeldung',
        'lockout' => 'Anmeldung gesperrt',
        'attack' => 'Angriffsversuch abgewehrt',
        'blocked' => 'IP-Adresse gesperrt',
        'two_factor_enabled' => 'Zwei-Faktor-Anmeldung eingeschaltet',
        'two_factor_disabled' => 'Zwei-Faktor-Anmeldung ausgeschaltet',
        'two_factor_failed' => 'Falscher Bestätigungscode',
        'password_changed' => 'Passwort geändert',
        'scan' => 'Sicherheits-Check',
    ];

    protected $fillable = ['type', 'ip', 'user_id', 'detail'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? $this->type;
    }

    public function isWarning(): bool
    {
        return in_array($this->type, ['login_failed', 'lockout', 'attack', 'blocked', 'two_factor_failed', 'two_factor_disabled'], true);
    }

    public static function record(string $type, ?string $detail = null, ?int $userId = null, ?string $ip = null): self
    {
        // Gelegentlich alte Einträge löschen
        if (random_int(1, 50) === 1) {
            static::where('created_at', '<', now()->subDays(self::KEEP_DAYS))->delete();
        }

        return static::create([
            'type' => $type,
            'ip' => $ip ?? request()?->ip(),
            'user_id' => $userId,
            'detail' => $detail ? mb_substr($detail, 0, 500) : null,
        ]);
    }
}
