<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Tenant permissions. These names MUST match the `permission:`
     * middleware strings in routes/web.php exactly (case-sensitive).
     *
     * @var array<int, string>
     */
    protected array $tenantPermissions = [
        // Bookings
        'view bookings',
        'create bookings',
        'edit bookings',
        'delete bookings',

        // Payments
        'view payments',
        'manage payments',

        // Properties & Property Types
        'view properties',
        'manage properties',

        // Services
        'view services',
        'manage services',

        // Employees
        'view employees',
        'manage employees',

        // Events
        'view events',
        'manage events',

        // Analytics
        'view analytics',
    ];

    public function run(): void
    {
        // Clear Spatie cache before mutating.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ── 1. Create all permissions ─────────────────────────
        foreach ($this->tenantPermissions as $name) {
            Permission::firstOrCreate([
                'name'       => $name,
                'guard_name' => 'web',
            ]);
        }

        // ── 2. Create system roles ────────────────────────────
        // super-admin carries NO explicit permissions — it bypasses all
        // authorization via the Gate::before() callback registered in
        // AppServiceProvider. The tourist role is a marker role only.
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'tourist',     'guard_name' => 'web']);

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        // ── 3. Grant tenant permissions to admin ──────────────
        $admin->syncPermissions($this->tenantPermissions);

        // ── 4. Seed starter custom roles ──────────────────────
        $this->seedStarterRoles();

        // Clear cache again so new permissions/roles are picked up
        // by any code that runs after the seeder (e.g. DatabaseSeeder's
        // subsequent tenant + user creation, which assigns roles).
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Seed ready-to-use roles so tenants can assign them without
     * having to create them from scratch.
     */
    protected function seedStarterRoles(): void
    {
        // Front Desk — can handle bookings + payments.
        $frontDesk = Role::firstOrCreate(['name' => 'front desk', 'guard_name' => 'web']);
        $frontDesk->syncPermissions([
            'view bookings',
            'create bookings',
            'edit bookings',
            'view payments',
            'manage payments',
        ]);

        // Guide — read-only bookings.
        $guide = Role::firstOrCreate(['name' => 'guide', 'guard_name' => 'web']);
        $guide->syncPermissions([
            'view bookings',
        ]);

        // Property Manager — properties + services + analytics.
        $propManager = Role::firstOrCreate(['name' => 'property manager', 'guard_name' => 'web']);
        $propManager->syncPermissions([
            'view properties',
            'manage properties',
            'view services',
            'manage services',
            'view analytics',
        ]);

        // Analyst — read-only across the board + analytics.
        $analyst = Role::firstOrCreate(['name' => 'analyst', 'guard_name' => 'web']);
        $analyst->syncPermissions([
            'view bookings',
            'view payments',
            'view properties',
            'view services',
            'view employees',
            'view analytics',
        ]);
    }
}