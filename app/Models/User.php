<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Security\Totp;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'phone', 'company', 'billing_address', 'shipping_address'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    /** Prüft einen Code aus der App oder einen Notfall-Code (der danach verbraucht ist). */
    public function verifyTwoFactor(string $code): bool
    {
        if (! $this->hasTwoFactor()) {
            return false;
        }
        if (Totp::verify($this->two_factor_secret, $code)) {
            return true;
        }

        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
        $codes = $this->two_factor_recovery_codes ?? [];
        foreach ($codes as $i => $hash) {
            if (password_verify($normalized, $hash)) {
                unset($codes[$i]);
                $this->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Mitglied (Modul Mitgliederbereich): member = freigeschaltet, member_pending = wartet auf Freigabe, member_blocked = gesperrt */
    public function isMember(): bool
    {
        return in_array($this->role, ['member', 'member_pending', 'member_blocked'], true);
    }

    /** Kundenkonto im Shop (Modul Kundenkonto) */
    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest('id');
    }

    public function communityProfile(): HasOne
    {
        return $this->hasOne(CommunityProfile::class);
    }

    /** Darf in der Community schreiben (freigeschaltetes Mitglied oder Administrator, nicht gesperrt) */
    public function canUseCommunity(): bool
    {
        return in_array($this->role, ['admin', 'member'], true) && ! $this->communityProfile?->isBanned();
    }

    public function canSeeMembersArea(): bool
    {
        return in_array($this->role, ['admin', 'member'], true);
    }
}
