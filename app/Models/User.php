<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'unit_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function permissionNames(): array
    {
        if ($this->permissionCache === null) {
            $this->loadMissing('roles.permissions');
            $this->permissionCache = $this->roles
                ->flatMap->permissions
                ->pluck('name')
                ->unique()
                ->values()
                ->all();
        }

        return $this->permissionCache;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissionNames(), true);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function forgetPermissionCache(): void
    {
        $this->permissionCache = null;
        $this->unsetRelation('roles');
    }

    public function canAccessUnit(int $unitId): bool
    {
        return $this->unit_id === null || (int) $this->unit_id === $unitId;
    }

    public function canSeeEventUnit(?int $unitId): bool
    {
        if ($this->unit_id === null) {
            return true;
        }

        return $unitId !== null && (int) $this->unit_id === $unitId;
    }

    public function primaryRoleLabel(): string
    {
        $this->loadMissing('roles');

        return $this->roles->pluck('label')->join(', ') ?: 'Pengguna';
    }
}
