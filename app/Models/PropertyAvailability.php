<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class PropertyAvailability extends Model 
{
    use BelongsToTenant;

    protected $table = 'property_availability'; 
    protected $fillable = ['tenant_id', 'property_id', 'date', 'is_available'];
    
    public function property() { return $this->belongsTo(Property::class); }
}