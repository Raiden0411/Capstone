<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class PropertyImage extends Model 
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'property_id', 'image_path'];
    
    public function property() { return $this->belongsTo(Property::class); }
}