<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Tenant;
use App\Models\TypeOfTenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Multi-tenant row isolation.
 *
 * Every model using the BelongsToTenant trait is filtered by
 * TenantScope: a global Eloquent scope that adds `WHERE tenant_id = ?`
 * to every query. Super-admins bypass the scope; unauthenticated
 * requests skip it; users without a tenant see nothing.
 */
class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('super-admin', 'web');
        Role::findOrCreate('admin', 'web');
    }

    public function test_user_only_sees_their_own_tenants_properties(): void
    {
        $type = TypeOfTenant::create(['type' => 'Resort']);

        $tenantA = Tenant::create([
            'name'              => 'Tenant A',
            'slug'              => 'tenant-a',
            'type_of_tenant_id' => $type->id,
            'address'           => 'A',
            'contact_number'    => '09000000000',
            'email'             => 'a@example.com',
        ]);

        $tenantB = Tenant::create([
            'name'              => 'Tenant B',
            'slug'              => 'tenant-b',
            'type_of_tenant_id' => $type->id,
            'address'           => 'B',
            'contact_number'    => '09000000001',
            'email'             => 'b@example.com',
        ]);

        $propType = PropertyType::create([
            'tenant_id' => $tenantA->id,
            'name'      => 'Villa',
        ]);

        Property::create([
            'tenant_id'        => $tenantA->id,
            'property_type_id' => $propType->id,
            'name'             => 'Villa A1',
            'price'            => 1000,
        ]);

        Property::create([
            'tenant_id'        => $tenantB->id,
            'property_type_id' => $propType->id,
            'name'             => 'Villa B1',
            'price'            => 2000,
        ]);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userA->assignRole('admin');

        Auth::login($userA);

        $visible = Property::query()->pluck('name')->all();

        $this->assertSame(['Villa A1'], $visible);
    }

    public function test_super_admin_bypasses_the_tenant_scope(): void
    {
        $type = TypeOfTenant::create(['type' => 'Resort']);

        $tenant = Tenant::create([
            'name'              => 'Tenant A',
            'slug'              => 'tenant-a',
            'type_of_tenant_id' => $type->id,
            'address'           => 'A',
            'contact_number'    => '09000000000',
            'email'             => 'a@example.com',
        ]);

        $propType = PropertyType::create([
            'tenant_id' => $tenant->id,
            'name'      => 'Villa',
        ]);

        Property::create([
            'tenant_id'        => $tenant->id,
            'property_type_id' => $propType->id,
            'name'             => 'Villa A1',
            'price'            => 1000,
        ]);

        $super = User::factory()->create(['tenant_id' => null]);
        $super->assignRole('super-admin');

        Auth::login($super);

        $this->assertSame(1, Property::query()->count('*'));
    }
}