<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Employee extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'user_id', 'name', 'role', 'phone', 'is_active'];

    public function user() { return $this->belongsTo(User::class); }
}