<?php

use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

uses(RefreshDatabase::class);

it('loads existing property data and updates it', function () {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();

    /** @var User $user */
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    /** @var PropertyType $propertyType */
    $propertyType = PropertyType::factory()->create([
        'tenant_id' => null,
        'name' => 'Standard Room',
    ]);

    /** @var Property $property */
    $property = Property::factory()->create([
        'tenant_id'        => $tenant->id,
        'property_type_id' => $propertyType->id,
        'name'             => 'Original Activity',
        'description'      => 'Original description',
        'capacity'         => 2,
        'quantity'         => 1,
        'price'            => 1000,
        'status'           => 'available',
        'is_active'        => true,
    ]);

    $this->actingAs($user);

    // Pass the model instance, not the ID
    $component = Livewire::test('tenant::pages.property.edit-property', ['property' => $property]);

    // Assert initial data loaded
    $component->assertSet('name', 'Original Activity')
        ->assertSet('description', 'Original description')
        ->assertSet('property_type_id', (string) $propertyType->id)
        ->assertSet('capacity', 2)
        ->assertSet('quantity', 1)
        ->assertSet('price', 1000)
        ->assertSet('status', 'available')
        ->assertSet('is_active', true);

    // Define blackout dates (2 days)
    $from = Carbon::today()->toDateString();
    $to = Carbon::today()->addDays(1)->toDateString();

    // Update fields
    $component->set('name', 'Updated Activity');
    $component->set('description', 'Updated description');
    $component->set('capacity', 4);
    $component->set('quantity', 3);
    $component->set('price', 1500);
    $component->set('status', 'reserved');
    $component->set('is_active', false);
    $component->set('unavailableFrom', $from);
    $component->set('unavailableTo', $to);

    // Submit update
    $component->call('update')
        ->assertHasNoErrors()
        ->assertRedirect(route('tenant.properties.index'));

    // Verify property updated
    $this->assertDatabaseHas('properties', [
        'id'               => $property->id,
        'name'             => 'Updated Activity',
        'description'      => 'Updated description',
        'capacity'         => 4,
        'quantity'         => 3,
        'price'            => 1500,
        'status'           => 'reserved',
        'is_active'        => false,
    ]);

    // Verify availability records created (2 days blackout)
    $property->refresh();
    $this->assertEquals(2, $property->availabilities()->where('is_available', false)->count());
});