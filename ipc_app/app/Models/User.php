<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    public const ROLE_STAFF = 'staff';

    public const ROLE_APPROVER = 'approver';

    public const ROLE_ADMIN = 'admin';

    public const ROLES = [self::ROLE_STAFF, self::ROLE_APPROVER, self::ROLE_ADMIN];

    public const ROLE_LABELS = [
        self::ROLE_STAFF => 'Staff',
        self::ROLE_APPROVER => 'Approver',
        self::ROLE_ADMIN => 'Admin',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
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
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /** Admin bypasses every workflow-role check, matching the sibling app's own convention. */
    public function isApprover(): bool
    {
        return $this->role === self::ROLE_APPROVER || $this->isAdmin();
    }
}
