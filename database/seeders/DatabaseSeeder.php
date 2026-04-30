<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Tenant;
use App\Models\TypeOfTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Call your setup seeders
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,  
        ]);

        // 2. Create a default Type of Tenant
        $tenantType = TypeOfTenant::firstOrCreate(
            ['id' => 1],
            ['type' => 'System Platform'] 
        );

        // 3. Create the System Default Tenant
        $tenant = Tenant::firstOrCreate(
            ['id' => 1], 
            [
                'name' => 'System Administration',
                'slug' => 'system-administration',
                'type_of_tenant_id' => $tenantType->id,
                'address' => 'System HQ',
                'contact_number' => '00000000000',
                'email' => 'system@admin.com',
            ]
        );

        // 4. Create the Super Admin User
        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'name' => 'System Super Admin',
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id, 
                'is_active' => 1,
            ]
        );
        $superAdmin->assignRole('super-admin');

        // 5. Create a Sample Normal Tenant Admin User
        $tenantAdmin = User::firstOrCreate(
            ['email' => 'tenantadmin@gmail.com'],
            [
                'name' => 'Sample Tenant Admin',
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id, 
                'is_active' => 1,
            ]
        );
        $tenantAdmin->assignRole('admin');
    }
}