<?php

use App\Models\Tenant;
use App\Models\TypeOfTenant;
use App\Models\User;
use App\Models\TenantSetting;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function setupTenantData(): array {
    // The SFC's update() assigns 'tourist' to the admin via
    // $admin->assignRole('tourist'). Spatie throws RoleDoesNotExist if
    // the role isn't seeded — and the SFC's outer try/catch silently
    // rolls back the transaction.
    Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'tourist',     'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    $type = TypeOfTenant::factory()->create(['type' => 'Resort']);

    $tenant = Tenant::factory()->create([
        'name' => 'Original Resort',
        'slug' => 'original-resort',
        'type_of_tenant_id' => $type->id,
        'email' => 'original@example.com',
        'contact_number' => '09171234567',
        'address' => 'Original Address',
        'coordinates' => [
            ['lat' => 10.9000, 'lng' => 123.0700, 'name' => 'Main', 'type' => 'parent'],
        ],
        'is_active' => true,
        'is_recommended' => false,
    ]);

    TenantSetting::create([
        'tenant_id' => $tenant->id,
        'key' => 'business_info',
        'value' => [
            'description' => 'Original description',
            'opening_hours' => ['opening' => '08:00', 'closing' => '17:00'],
            'barangay' => 'Barangay I',
            'city' => 'Victorias City',
            'province' => 'Negros Occidental',
        ],
    ]);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Old Admin',
        'email' => 'oldadmin@example.com',
        'password' => Hash::make('oldpassword'),
    ]);
    $admin->assignRole('admin');

    return ['tenant' => $tenant, 'admin' => $admin];
}

it('loads existing tenant data and updates business details and admin account', function () {
    ['tenant' => $tenant, 'admin' => $admin] = setupTenantData();

    $component = Livewire::test('superadmin::pages.tenant.edit-tenant', ['tenant' => $tenant]);

    $component->assertSet('name', 'Original Resort')
        ->assertSet('public_email', 'original@example.com')
        ->assertSet('admin_name', 'Old Admin')
        ->assertSet('admin_email', 'oldadmin@example.com')
        ->assertSet('latitude', 10.9000)
        ->assertSet('longitude', 123.0700)
        ->assertSet('description', 'Original description')
        ->assertSet('opening_time', '08:00')
        ->assertSet('closing_time', '17:00')
        ->assertSet('barangay', 'Barangay I')
        ->assertSet('city', 'Victorias City')
        ->assertSet('province', 'Negros Occidental');

    // Update core fields
    $component->set('name', 'Updated Resort');
    $component->set('public_email', 'updated@example.com');
    $component->set('contact_number', '09170000000');
    $component->set('address', 'Updated Address');
    $component->set('description', 'Updated description');
    $component->set('opening_time', '09:00');
    $component->set('closing_time', '18:00');
    $component->set('barangay', 'Barangay II');
    $component->set('city', 'Bacolod City');
    $component->set('province', 'Negros Occidental');
    $component->set('latitude', 10.9100);
    $component->set('longitude', 123.0800);
    $component->set('admin_name', 'New Admin');
    $component->set('admin_email', 'newadmin@example.com');
    $component->set('admin_password', 'newpassword123');
    $component->set('admin_password_confirmation', 'newpassword123');

    // Slug must be set explicitly on the edit page — the SFC only
    // auto-regenerates the slug when $slugEditable is false. On edit,
    // it defaults to true (safe: changing the name should NOT silently
    // break existing URLs).
    $component->set('slug', 'updated-resort');

    // KYB fields — required by the SFC's validation rules.
    $component->set('business_type', 'dti');
    $component->set('business_registration_number', 'DTI-2024-001234');
    $component->set('tin_number', '123-456-789-000');
    $component->set('owner_id_type', 'drivers_license');
    $component->set('owner_id_number', 'N01-23-456789');

    $component->call('update')->assertHasNoErrors();

    $this->assertDatabaseHas('tenants', [
        'id' => $tenant->id,
        'name' => 'Updated Resort',
        'slug' => 'updated-resort',
        'email' => 'updated@example.com',
        'contact_number' => '09170000000',
        'address' => 'Updated Address',
        'is_active' => true,
        'is_recommended' => false,
    ]);

    /** @var Tenant $updatedTenant */
    $updatedTenant = Tenant::query()->find($tenant->id);
    $this->assertNotNull($updatedTenant);

    $coords = $updatedTenant->coordinates;
    expect($coords[0]['lat'])->toBe(10.9100);
    expect($coords[0]['lng'])->toBe(123.0800);

    /** @var TenantSetting $setting */
    $setting = TenantSetting::query()
        ->where('tenant_id', $tenant->id)
        ->where('key', 'business_info')
        ->first();

    $this->assertNotNull($setting);

    $value = $setting->value;
    expect($value['description'])->toBe('Updated description');
    expect($value['opening_hours']['opening'])->toBe('09:00');
    expect($value['opening_hours']['closing'])->toBe('18:00');

    $this->assertDatabaseHas('users', [
        'id' => $admin->id,
        'name' => 'New Admin',
        'email' => 'newadmin@example.com',
        'tenant_id' => $tenant->id,
    ]);

    /** @var User $updatedAdmin */
    $updatedAdmin = User::query()->find($admin->id);
    $this->assertNotNull($updatedAdmin);

    expect(Hash::check('newpassword123', $updatedAdmin->password))->toBeTrue();
});