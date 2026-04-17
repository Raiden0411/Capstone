<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create the Roles
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $tenantAdminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']); // Your normal tenant admin
        $editorRole = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);

        // 2. Give the Tenant Admin their specific permissions
        $tenantAdminRole->givePermissionTo(Permission::all()); 
        
        // Note: We don't need to assign permissions to the super-admin because of the Gate::before bypass we just added!
    }
}