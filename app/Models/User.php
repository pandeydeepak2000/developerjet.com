<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_CUSTOMER = 'customer';

    public const ROLE_ADMIN = 'admin';

    /** `role` is intentionally NOT mass assignable. */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'password_change_required' => 'boolean',
            'is_active' => 'boolean',
            'suspended_at' => 'datetime',
            'last_staff_login_at' => 'datetime',
        ];
    }

    public const ROLE_STAFF = 'staff';

    /** Legacy owner flag (Phase 1). Treated as full access; new staff use roles. */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function staffRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function isStaff(): bool
    {
        return ($this->is_active ?? true)
            && ($this->isAdmin() || ($this->role === self::ROLE_STAFF && $this->staffRole !== null));
    }

    /** Any staff/owner account, active or not (used to keep staff out of customer-only flows). */
    public function isStaffAccount(): bool
    {
        return in_array($this->role, [self::ROLE_STAFF, self::ROLE_ADMIN], true);
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    public function isSuper(): bool
    {
        return ($this->is_active ?? true)
            && ($this->isAdmin() || ($this->role === self::ROLE_STAFF && (bool) $this->staffRole?->is_super));
    }

    /** Super Admin role (or legacy owner) regardless of active status. */
    public function isSuperAccount(): bool
    {
        return $this->isAdmin() || ($this->role === self::ROLE_STAFF && (bool) $this->staffRole?->is_super);
    }

    /** @var array<int, string>|null */
    private ?array $permissionCache = null;

    public function hasPermission(string $permission): bool
    {
        if (! $this->isStaff()) {
            return false;
        }

        if ($this->isSuper()) {
            return true;
        }

        $this->permissionCache ??= $this->staffRole->permissions()->pluck('permission')->all();

        return in_array($permission, $this->permissionCache, true);
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class);
    }

    /**
     * Verification is done with an emailed OTP (see OtpService), not Laravel's signed-link notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        // Intentionally empty.
    }

    public function otps(): HasMany
    {
        return $this->hasMany(EmailOtp::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }
}
