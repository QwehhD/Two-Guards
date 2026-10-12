<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Exceptions\LastAdminException;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserRole $role
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
        ];
    }

    /**
     * The app must always keep at least one admin: with self-registration
     * and public password reset closed, only an admin can create accounts or
     * reset passwords. Enforced here, underneath the API's own checks, so
     * the console and tinker can't break it either.
     */
    protected static function booted(): void
    {
        static::deleting(function (User $user): void {
            if ($user->isLastAdmin()) {
                throw new LastAdminException("[{$user->email}] is the last admin and cannot be deleted.");
            }
        });

        static::updating(function (User $user): void {
            if ($user->isDirty('role') && $user->role !== UserRole::Admin && $user->isLastAdmin()) {
                throw new LastAdminException("[{$user->email}] is the last admin and cannot lose the admin role.");
            }
        });
    }

    /**
     * Whether this account, as currently stored, is the only admin left.
     */
    public function isLastAdmin(): bool
    {
        return $this->getOriginal('role') === UserRole::Admin
            && static::query()->where('role', UserRole::Admin)->count() === 1;
    }

    public function rfidCards(): HasMany
    {
        return $this->hasMany(RfidCard::class, 'created_by');
    }

    public function processedAccessLogs(): HasMany
    {
        return $this->hasMany(AccessLog::class, 'processed_by');
    }
}
