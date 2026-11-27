<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Einfache Schlüssel-Wert-Einstellungen (Firmenprofil, Website-Name usw.).
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /** @var array<string, string|null>|null */
    protected static ?array $loaded = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        static::$loaded ??= static::query()->pluck('value', 'key')->all();

        $value = static::$loaded[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        static::$loaded = null;
    }

    /** @param array<string, string|null> $values */
    public static function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::put($key, $value);
        }
    }

    public static function flushCache(): void
    {
        static::$loaded = null;
    }
}
