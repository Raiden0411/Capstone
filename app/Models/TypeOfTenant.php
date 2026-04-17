<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TypeOfTenant extends Model
{
    protected $fillable = ['type', 'description'];

    public function tenants() {
        return $this->hasMany(Tenant::class);
    }
}