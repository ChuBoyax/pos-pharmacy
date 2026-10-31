<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'role', 'is_active', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_SUPERADMIN = Role::SUPERADMIN;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_PHARMACIST = 'pharmacist';

    public const ROLE_CASHIER = 'cashier';

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
            'is_active' => 'boolean',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function assignedRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role', 'name');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    /**
     * The super admin implicitly holds every permission, including the reserved ones.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return $this->isSuperAdmin() ? Role::allPermissions() : ($this->assignedRole?->permissions ?? []);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /**
     * Sidebar menus this user's role may see; the super admin always sees them all.
     *
     * @return array<int, string>
     */
    public function menus(): array
    {
        $all = array_keys(Role::MENUS);

        return $this->isSuperAdmin() ? $all : ($this->assignedRole?->menus ?? $all);
    }

    /**
     * What the frontend needs to know about the signed-in user.
     *
     * @return array<string, mixed>
     */
    public function authPayload(): array
    {
        return [
            ...$this->only(['id', 'name', 'email', 'role']),
            'role_label' => $this->assignedRole?->label ?? $this->role,
            'permissions' => $this->permissions(),
            'menus' => $this->menus(),
        ];
    }
}
