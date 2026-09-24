<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Role codes, kept as named constants for readability at call sites (isAdmin(), factory
     * states, tests, ...). The actual allowed-values list and labels live in the `roles` table
     * (see the Role model) — these are just convenient string literals, not a second source of
     * truth for validation.
     */
    public const ROLE_STAFF = 'staff';

    public const ROLE_APPROVER = 'approver';

    public const ROLE_ADMIN = 'admin';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'role_id',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'roleRecord',
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

    public function roleRecord(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Exposes `role` as the role's code string (e.g. 'admin'), backed by the `role_id` FK to the
     * `roles` table instead of a hand-typed varchar — reading/writing `$user->role` still works
     * exactly like before, but the value is now guaranteed to correspond to a real Role row.
     */
    protected function role(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->roleRecord?->code,
            set: fn (string $value) => ['role_id' => Role::where('code', $value)->value('id')],
        );
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->role_id)) {
                $user->role_id = Role::where('code', self::ROLE_STAFF)->value('id');
            }
        });
    }
}
