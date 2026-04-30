<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Create Roles
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $tenantAdminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $touristRole = Role::firstOrCreate(['name' => 'tourist', 'guard_name' => 'web']); 

        // Assign permissions to Tenant Admin
        $tenantAdminRole->givePermissionTo(Permission::all());
    }
}