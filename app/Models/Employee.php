<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $user_id
 * @property string|null $code
 * @property string $name
 * @property string|null $role
 * @property string|null $phone
 * @property string|null $avatar
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Tenant $tenant
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Employee whereUserId($value)
 * @mixin \Eloquent
 */
class Employee extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'code',
        'name',
        'role',
        'phone',
        'avatar',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Resolve role names for a batch of employees.
     *
     * Bypasses Spatie's roles() relation entirely. That relation pins the
     * ambient team at BUILD time — and Eloquent's eager loader builds it
     * on a fresh User instance (no attributes, tenant_id = null), so the
     * pinned team is 0, not the employee's tenant. The resulting pivot
     * query matches zero rows and every employee reads as "no roles".
     *
     * This method reads the pivot tables directly, joining users on
     * (id = model_id AND tenant_id = team_id), so only each user's own
     * tenant's pivots come back. One query for the entire batch.
     *
     * @param  iterable<self>  $employees
     * @return array<int, array<int, string>>   keyed by employee.user_id
     */
    public static function roleNamesBatch(iterable $employees): array
    {
        $userIds = collect($employees)
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($userIds)) {
            return [];
        }

        $rows = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->join('users', function ($join): void {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                     ->on('users.tenant_id', '=', 'model_has_roles.team_id');
            })
            ->where('model_has_roles.model_type', User::class)
            ->whereIn('model_has_roles.model_id', $userIds)
            ->where('roles.guard_name', 'web')
            ->orderBy('roles.name')
            ->get(['model_has_roles.model_id as user_id', 'roles.name']);

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row->user_id][] = (string) $row->name;
        }

        return $grouped;
    }

    /**
     * Convenience accessor for a single employee.
     *
     * @return array<int, string>
     */
    public function roleNames(): array
    {
        if (! $this->user_id) {
            return [];
        }

        return self::roleNamesBatch([$this])[(int) $this->user_id] ?? [];
    }
}