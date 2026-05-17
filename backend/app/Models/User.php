<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'owner_id',
        'station_id',
        'role',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function ownedAccount(): HasOne
    {
        return $this->hasOne(Owner::class);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function ownsStation(int $stationId): bool
    {
        return $this->ownedAccount?->stations()->where('id', $stationId)->exists() ?? false;
    }

    /**
     * Returns the station ID to use for tenant scoping.
     * Non-owners: their assigned station_id.
     * Owners: the station resolved by ResolveStation middleware (via setRelation).
     */
    public function effectiveStationId(): ?int
    {
        return $this->station_id ?? ($this->isOwner() ? $this->station?->id : null);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['owner', 'manager']);
    }

    public function isOperator(): bool
    {
        return $this->role === 'operator';
    }
}
