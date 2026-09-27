<?php

/**
 * CSAV ISO 25010 — Pass 2, Check 4 (Authorization Tamper)
 *
 * Verifies two security boundaries that the rubric scores:
 *   - Integrity / Authenticity — cross-tenant isolation
 *   - Confidentiality / Accountability — superadmin-only boundary
 *
 * Note on cross-tenant behavior:
 *   The Property model applies a TenantScope global scope. When a
 *   tenant admin requests a resource owned by another tenant, the
 *   scope filters the query and Laravel's implicit route model
 *   binding returns 404 — not 403. A 404 is the stronger outcome:
 *   it does not leak the existence of the resource. The test
 *   asserts 404 deliberately.
 */

use App\Models\Property;
use App\Models\Tenant;
use App\Models\TypeOfTenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'tourist', 'super-admin'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
});

/**
 * Build a minimally-valid tenant row.
 *
 * The TypeOfTenant is created per-test — do NOT cache statically,
 * because RefreshDatabase resets the database between tests and a
 * cached ID will fail the FK constraint on the second test.
 */
function tamperMakeTenant(string $suffix): Tenant
{
    $type = TypeOfTenant::factory()->create(['type' => "Type {$suffix}"]);

    return Tenant::factory()->create([
        'name'              => "Tenant {$suffix}",
        'slug'              => "tenant-{$suffix}",
        'type_of_tenant_id' => $type->id,
        'email'             => "tenant-{$suffix}@example.com",
        'contact_number'    => '09171234567',
        'address'           => "Address {$suffix}",
        'coordinates'       => [
            ['lat' => 10.9000, 'lng' => 123.0700, 'name' => 'Main', 'type' => 'parent'],
        ],
    ]);
}

function tamperMakeTenantAdmin(Tenant $tenant, string $suffix): User
{
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'name'      => "Admin {$suffix}",
        'email'     => "admin-{$suffix}@example.com",
        'password'  => Hash::make('password'),
    ]);
    $admin->assignRole('admin');

    return $admin;
}

it('denies a tenant admin from opening another tenant\'s property edit page', function () {
    $tenantA = tamperMakeTenant('A');
    $tenantB = tamperMakeTenant('B');

    $adminA = tamperMakeTenantAdmin($tenantA, 'A');

    // Property belongs to Tenant B. Admin A is authenticated as Tenant A.
    // The TenantScope global scope filters the lookup → 404, which is
    // stronger than 403 because it does not disclose the resource exists.
    $propertyB = Property::factory()->create([
        'tenant_id' => $tenantB->id,
        'name'      => 'Tenant B Secret Villa',
    ]);

    $this->actingAs($adminA)
        ->get(route('tenant.properties.edit', ['property' => $propertyB->id]))
        ->assertNotFound();
});

it('denies a tourist from opening a superadmin page', function () {
    $tourist = User::factory()->create([
        'tenant_id' => null,
        'name'      => 'Tourist',
        'email'     => 'tourist@example.com',
        'password'  => Hash::make('password'),
    ]);
    $tourist->assignRole('tourist');

    $this->actingAs($tourist)
        ->get(route('superadmin.tenants.index'))
        ->assertForbidden();
});

it('denies a tenant admin from opening a superadmin page', function () {
    $tenant = tamperMakeTenant('C');
    $admin  = tamperMakeTenantAdmin($tenant, 'C');

    $this->actingAs($admin)
        ->get(route('superadmin.tenants.index'))
        ->assertForbidden();
});