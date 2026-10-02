<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * App-level notification feed (custom table, not Laravel's Notifiable system).
 * user_id NULL = tenant-wide (everyone in the tenant sees it); set = that user only.
 * permission set = only staff holding that permission see it (money amounts, etc.).
 * Read state is per user (notification_reads); the old shared `read` column is unused.
 */
class Notification extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'title',
        'body',
        'icon',
        'tone',
        'category',
        'permission',
        'read',
    ];

    protected function casts(): array
    {
        return [
            'read' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Create a notification for the current tenant (tenant-wide unless user_id is given). */
    public static function notify(array $attrs): self
    {
        return static::create($attrs + [
            'tenant_id' => auth()->user()?->tenant_id,
            'tone' => 'brand',
            'read' => false,
        ]);
    }

    /**
     * Tenant-wide notifications plus the ones addressed to this user, limited to those whose
     * permission the user holds. Each row also gets `read` = read by THIS user.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        $permissions = $user ? $user->getAllPermissions()->pluck('name')->all() : [];

        $query->where(function (Builder $q) use ($user) {
            $q->whereNull('user_id');
            if ($user) {
                $q->orWhere('user_id', $user->id);
            }
        })->where(function (Builder $q) use ($permissions) {
            $q->whereNull('permission');
            if ($permissions) {
                $q->orWhereIn('permission', $permissions);
            }
        });

        if ($user) {
            $query->select('notifications.*')->withExists(['reads as read' => fn ($q) => $q->where('user_id', $user->id)]);
        }

        return $query;
    }

    /** Not yet read by this user. */
    public function scopeUnreadBy(Builder $query, User $user): Builder
    {
        return $query->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id));
    }

    public function reads()
    {
        return $this->hasMany(NotificationRead::class);
    }

    /** Mark these notification ids as read by the user (ignores ones already read). */
    public static function markReadFor(User $user, array $ids): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (! $ids) {
            return 0;
        }

        return \Illuminate\Support\Facades\DB::table('notification_reads')->insertOrIgnore(
            array_map(fn ($id) => ['notification_id' => $id, 'user_id' => $user->id, 'read_at' => now()], $ids)
        );
    }
}
