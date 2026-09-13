<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'barangay',
        'description',
        'type',
        'start_date',
        'end_date',
        'coordinates',
        'image_path',
        'is_active',
        'featured',
    ];

    protected function casts(): array
    {
        return [
            'start_date'  => 'datetime',
            'end_date'    => 'datetime',
            'coordinates' => 'array',
            'is_active'   => 'boolean',
            'featured'    => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q
                ->whereNull('end_date')
                ->orWhere('end_date', '>=', now()))
            ->where('start_date', '>=', now()->subDay());
    }
}