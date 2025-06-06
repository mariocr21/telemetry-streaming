<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'client_id',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'role' => UserRole::class, // Cast a enum
        ];
    }

    /**
     * Get the client associated with the user.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Scope para filtrar por rol
     */
    public function scopeByRole($query, UserRole $role)
    {
        return $query->where('role', $role->value);
    }

    /**
     * Scope para usuarios activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Verificar si el usuario es Super Admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SUPER_ADMIN;
    }

    /**
     * Verificar si el usuario es Client Admin
     */
    public function isClientAdmin(): bool
    {
        return $this->role === UserRole::CLIENT_ADMIN;
    }

    /**
     * Verificar si el usuario es Client User
     */
    public function isClientUser(): bool
    {
        return $this->role === UserRole::CLIENT_USER;
    }

    /**
     * Obtener el label del rol
     */
    public function getRoleLabelAttribute(): string
    {
        return $this->role->label();
    }
}
