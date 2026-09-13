<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyImage extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'property_id', 'image_path'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}