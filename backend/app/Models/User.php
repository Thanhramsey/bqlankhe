<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'username', 'name', 'date_of_birth', 'gender', 'email', 'phone', 'identity_number',
    'address', 'avatar', 'collection_route_id', 'password', 'is_active',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function collectionRoute()
    {
        return $this->belongsTo(CollectionRoute::class);
    }

    public function collectionRoutes()
    {
        return $this->belongsToMany(CollectionRoute::class)->withTimestamps();
    }

    public function permissions(): array
    {
        return $this->roles()->with('permissions')->get()->flatMap->permissions->pluck('code')->unique()->values()->all();
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

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
            'date_of_birth' => 'date:Y-m-d',
        ];
    }
}
