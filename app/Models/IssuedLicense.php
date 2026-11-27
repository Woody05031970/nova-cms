<?php

namespace App\Models;

use App\Licensing\LicenseKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Eine vom Lizenzserver ausgegebene Lizenz */
class IssuedLicense extends Model
{
    protected $fillable = ['key', 'customer_name', 'customer_email', 'core', 'modules', 'max_domains', 'updates_until', 'status', 'order_id', 'notes'];

    protected function casts(): array
    {
        return ['core' => 'boolean', 'modules' => 'array', 'updates_until' => 'date', 'max_domains' => 'integer'];
    }

    public function activations(): HasMany
    {
        return $this->hasMany(LicenseActivation::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public static function issue(array $attrs): self
    {
        do {
            $key = LicenseKey::generate();
        } while (static::where('key', $key)->exists());

        return static::create(['key' => $key, 'status' => 'active', ...$attrs]);
    }
}
