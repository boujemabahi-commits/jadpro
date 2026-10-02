<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Permissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasFactory, HasRoles, Notifiable;

    public const STATUSES = ['نشط', 'متوقف'];

    protected $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'status',
        'locale',
        'is_platform_admin',
        'email_verified_at',
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
            'is_platform_admin' => 'boolean',
        ];
    }

    /**
     * Emails are stored lowercase and without surrounding spaces, so "Sara@X.com " typed on a
     * phone (auto-capitalised first letter, trailing space after autocomplete) is the same
     * account as "sara@x.com" — whatever the database collation.
     */
    protected function email(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            set: fn ($value) => $value === null ? null : mb_strtolower(trim((string) $value)),
        );
    }

    /** The SaaS operator's own staff: no tenant, no roles, only the /admin area. */
    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    public function isActive(): bool
    {
        return $this->status !== 'متوقف';
    }

    public function isOwner(): bool
    {
        return $this->hasRole(Permissions::OWNER_ROLE);
    }

    /** The user's role as the UI shows it (custom roles show their plain name). */
    public function roleLabel(): string
    {
        $role = $this->roles->first();

        return $role ? __($role->display_name ?: $role->name) : '—';
    }
}
