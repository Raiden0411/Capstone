<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyAvailability extends Model
{
    use BelongsToTenant;

    protected $table = 'property_availability';

    protected $fillable = ['tenant_id', 'property_id', 'date', 'is_available'];

    protected function casts(): array
    {
        return [
            'date'         => 'date',
            'is_available' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}