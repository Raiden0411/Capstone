<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property string $type
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tenant> $tenants
 * @property-read int|null $tenants_count
 * @method static \Database\Factories\TypeOfTenantFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TypeOfTenant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TypeOfTenant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TypeOfTenant query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TypeOfTenant whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TypeOfTenant whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TypeOfTenant whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TypeOfTenant whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TypeOfTenant whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TypeOfTenant extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'description'];

    public function tenants() {
        return $this->hasMany(Tenant::class);
    }
}