<?php

use App\Models\Tenant;
use App\Models\TypeOfTenant;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('creates a tenant with admin account and default data', function () {
    // Ensure the admin role exists for assignment
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    // Create a business type
    $type = TypeOfTenant::factory()->create(['type' => 'Resort']);

    // Start the component
    $component = Livewire::test('superadmin::pages.tenant.create-tenant');

    // Step 1: Business Details
    $component->set('name', 'Test Resort')
              ->set('slug', 'test-resort')
              ->set('type_of_tenant_id', $type->id)
              ->set('public_email', 'testresort@example.com')
              ->set('contact_number', '09171234567')
              ->call('nextStep')
              ->assertSet('step', 2);

    // Step 2: Location
    $component->set('latitude', 10.9000)
              ->set('longitude', 123.0700)
              ->call('confirmLocation')
              ->call('nextStep')
              ->assertSet('step', 3);

    // Step 3: Sub-branches (skip)
    $component->set('hasSubBranches', false)
              ->call('nextStep')
              ->assertSet('step', 4);

    // Step 4: Admin account and save
    $component->set('admin_name', 'Admin User')
              ->set('admin_email', 'admin@testresort.com')
              ->set('password', 'password123')
              ->set('password_confirmation', 'password123')
              ->call('save')
              ->assertSet('showSuccessModal', true);

    // Verify tenant was created
    $this->assertDatabaseHas('tenants', [
        'name' => 'Test Resort',
        'slug' => 'test-resort',
        'type_of_tenant_id' => $type->id,
        'email' => 'testresort@example.com',
        'contact_number' => '09171234567',
    ]);

    /** @var Tenant|null $tenant */
    $tenant = Tenant::query()->firstWhere('slug', 'test-resort');
    $this->assertNotNull($tenant);

    // Verify admin user
    $this->assertDatabaseHas('users', [
        'email' => 'admin@testresort.com',
        'tenant_id' => $tenant->id,
    ]);

    // Verify tenant settings (business_info)
    $this->assertDatabaseHas('tenant_settings', [
        'tenant_id' => $tenant->id,
        'key' => 'business_info',
    ]);

    // Verify default property types created (based on 'Resort')
    $this->assertDatabaseHas('property_types', [
        'tenant_id' => $tenant->id,
        'name' => 'Standard Room',
    ]);

    // Verify default services created
    $this->assertDatabaseHas('services', [
        'tenant_id' => $tenant->id,
        'name' => 'Entrance Fee',
    ]);
});