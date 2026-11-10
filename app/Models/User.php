<?php

namespace App\Models;

use App\Notifications\ResetPasswordId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ADMIN_CK = 'admin_ck';
    public const ROLE_GUDANG = 'gudang';
    public const ROLE_OUTLET = 'outlet';

    /** Peran yang boleh dibuat lewat menu Pengguna. */
    public const ASSIGNABLE_ROLES = [
        self::ROLE_ADMIN_CK => 'Admin CK',
        self::ROLE_GUDANG => 'Staf Gudang',
        self::ROLE_OUTLET => 'Outlet',
    ];

    /** Peran yang bisa dipilih saat membuat akun baru (hanya Super Admin yang membuka menu ini). */
    public const CREATABLE_ROLES = [
        self::ROLE_SUPER_ADMIN => 'Super Admin',
    ] + self::ASSIGNABLE_ROLES;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'outlet_id', 'is_active', 'must_change_password', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /** Super Admin selalu lolos (akses penuh). */
    public function hasRole(string ...$roles): bool
    {
        return $this->isSuperAdmin() || in_array($this->role, $roles, true);
    }

    public function roleLabel(): string
    {
        return $this->isSuperAdmin() ? 'Super Admin' : (self::ASSIGNABLE_ROLES[$this->role] ?? $this->role);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $second = mb_substr($parts[1] ?? '', 0, 1);

        return mb_strtoupper($first.$second);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordId($token));
    }
}
