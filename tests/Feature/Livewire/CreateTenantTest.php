<?php

use App\Models\Tenant;
use App\Models\TypeOfTenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('creates a tenant with admin account and default data', function () {
    // The SFC stores uploaded KYB documents via Storage::disk('public').
    // Fake it so the test writes to the testing disk, not real storage.
    Storage::fake('public');

    // The SFC assigns BOTH 'tourist' and 'admin' to the newly-created
    // admin user via syncRoles(['tourist', 'admin']). Both roles must
    // exist or Spatie throws RoleDoesNotExist.
    Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'tourist',     'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    // The SFC calls Auth::id() in save() to record reviewed_by.
    /** @var User $superAdmin */
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');
    $this->actingAs($superAdmin);

    // Create a business type
    $type = TypeOfTenant::factory()->create(['type' => 'Resort']);

    // Start the component
    $component = Livewire::test('superadmin::pages.tenant.create-tenant');

    /*
     * Step 1 bundles Business Details + Legal & Identity + Required
     * Documents. The SFC's stepFields(1) validates ALL of the KYB
     * fields and all 4 document uploads before allowing nextStep() to
     * advance. Missing any of these silently halts at step 1.
     */
    $component->set('name', 'Test Resort')
        ->set('slug', 'test-resort')
        ->set('type_of_tenant_id', $type->id)
        ->set('public_email', 'testresort@example.com')
        ->set('contact_number', '09171234567')
        // KYB fields — required on step 1
        ->set('business_type', 'dti')
        ->set('business_registration_number', 'DTI-2024-001234')
        ->set('tin_number', '123-456-789-000')
        ->set('owner_id_type', 'drivers_license')
        ->set('owner_id_number', 'N01-23-456789')
        // Required documents — 4 fakes, ~100KB each as PDFs
        ->set('document_uploads.dti_sec_cda',   UploadedFile::fake()->create('dti.pdf',    100, 'application/pdf'))
        ->set('document_uploads.bir_2303',      UploadedFile::fake()->create('bir.pdf',    100, 'application/pdf'))
        ->set('document_uploads.mayors_permit', UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'))
        ->set('document_uploads.owner_id',      UploadedFile::fake()->create('id.pdf',     100, 'application/pdf'))
        ->call('nextStep')
        ->assertSet('step', 2);

    // Step 2: Location — confirmLocation() flips the lock, then nextStep advances
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
        'name'              => 'Test Resort',
        'slug'              => 'test-resort',
        'type_of_tenant_id' => $type->id,
        'email'             => 'testresort@example.com',
        'contact_number'    => '09171234567',
    ]);

    /** @var Tenant|null $tenant */
    $tenant = Tenant::query()->firstWhere('slug', 'test-resort');
    $this->assertNotNull($tenant);

    // Verify admin user
    $this->assertDatabaseHas('users', [
        'email'     => 'admin@testresort.com',
        'tenant_id' => $tenant->id,
    ]);

    // Verify tenant settings (business_info)
    $this->assertDatabaseHas('tenant_settings', [
        'tenant_id' => $tenant->id,
        'key'       => 'business_info',
    ]);

    // Verify default property types created (based on 'Resort')
    $this->assertDatabaseHas('property_types', [
        'tenant_id' => $tenant->id,
        'name'      => 'Standard Room',
    ]);

    // Verify default services created
    $this->assertDatabaseHas('services', [
        'tenant_id' => $tenant->id,
        'name'      => 'Entrance Fee',
    ]);
});