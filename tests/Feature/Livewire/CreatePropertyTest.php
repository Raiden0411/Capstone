<?php

use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

uses(RefreshDatabase::class);

it('creates a property with availability blackout dates', function () {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();

    /** @var User $user */
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    /** @var PropertyType $propertyType */
    $propertyType = PropertyType::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Standard Room',
    ]);

    $this->actingAs($user);

    $from = Carbon::today()->toDateString();
    $to = Carbon::today()->addDays(2)->toDateString();

    Livewire::test('tenant::pages.property.create-property')
        ->set('name', 'Test Activity')
        ->set('description', 'A test activity description')
        ->set('property_type_id', $propertyType->id)
        ->set('capacity', 4)
        ->set('quantity', 2)
        ->set('price', 1500)
        ->set('status', 'available')
        ->set('is_active', true)
        ->set('unavailableFrom', $from)
        ->set('unavailableTo', $to)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('tenant.properties.index'));

    $this->assertDatabaseHas('properties', [
        'tenant_id'        => $tenant->id,
        'property_type_id' => $propertyType->id,
        'name'             => 'Test Activity',
        'capacity'         => 4,
        'quantity'         => 2,
        'price'            => 1500,
        'status'           => 'available',
        'is_active'        => true,
    ]);

    /** @var Property $property */
    $property = Property::query()->firstWhere('tenant_id', $tenant->id);
    $this->assertNotNull($property);

    $this->assertEquals(3, $property->availabilities()->where('is_available', false)->count());
});