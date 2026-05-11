<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // ── Permissions ───────────────────────────────
        $permissions = [
            // Bookings
            'view bookings',
            'create bookings',
            'edit bookings',
            // Customers
            'view customers',
            'create customers',
            // Properties
            'view properties',
            'edit properties',
            // Payments
            'view payments',
            'create payments',
            'edit payments',
            // Services
            'view services',
            'create services',
            'edit services',
            // Employees
            'view employees',
            'create employees',
            'edit employees',
            // Analytics
            'view analytics',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // ── Roles ─────────────────────────────────────
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $admin      = Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);
        $tourist    = Role::firstOrCreate(['name' => 'tourist',     'guard_name' => 'web']);

        // Admin gets everything (except delete / system permissions)
        $admin->syncPermissions(Permission::all());
    }
}