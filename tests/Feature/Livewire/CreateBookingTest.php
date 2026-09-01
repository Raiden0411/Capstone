<?php

use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Booking;
use App\Scopes\TenantScope;
use App\Services\PayMongoService;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('mounts booking component and initializes customer data', function () {
    $tenant = Tenant::factory()->create();
    $propertyType = PropertyType::factory()->create(['tenant_id' => null]);
    $property = Property::factory()->create([
        'tenant_id' => $tenant->id,
        'property_type_id' => $propertyType->id,
        'price' => 1000,
    ]);

    /** @var User $user */
    $user = User::factory()->create([
        'name' => 'Juan Dela Cruz',
        'email' => 'juan@example.com',
        'phone' => '09171234567',
    ]);

    $this->actingAs($user);

    Livewire::test('public::pages.create-booking', ['publicproperty' => $property->id])
        ->assertSet('customerName', 'Juan Dela Cruz')
        ->assertSet('customerEmail', 'juan@example.com')
        ->assertSet('customerPhone', '09171234567')
        ->assertSet('totalAmount', 1000)
        ->assertSet('totalDays', 1);
});

it('calculates total amount and service charges', function () {
    $tenant = Tenant::factory()->create();
    $propertyType = PropertyType::factory()->create(['tenant_id' => null]);
    $property = Property::factory()->create([
        'tenant_id' => $tenant->id,
        'property_type_id' => $propertyType->id,
        'price' => 1000,
    ]);

    $service = Service::create([
        'tenant_id' => $tenant->id,
        'name' => 'Breakfast',
        'price' => 250,
        'is_active' => true,
    ]);

    /** @var User $user */
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('public::pages.create-booking', ['publicproperty' => $property->id])
        ->set('check_in', now()->addDays(5)->format('Y-m-d'))
        ->set('check_out', now()->addDays(7)->format('Y-m-d'))
        ->call('addService', $service->id)
        ->assertSet('totalDays', 2)
        ->assertSet('totalAmount', 2250) // 2 days * 1000 + 250
        ->assertSet('reservationFee', 450) // 20% of 2250
        ->assertSet('balanceOnArrival', 1800);
});

it('submits booking and creates payment record', function () {
    $tenant = Tenant::factory()->create();
    $propertyType = PropertyType::factory()->create(['tenant_id' => null]);
    $property = Property::factory()->create([
        'tenant_id' => $tenant->id,
        'property_type_id' => $propertyType->id,
        'price' => 1000,
    ]);

    $service = Service::create([
        'tenant_id' => $tenant->id,
        'name' => 'Guide',
        'price' => 500,
        'is_active' => true,
    ]);

    /** @var User $user */
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '09170000000',
    ]);
    $this->actingAs($user);

    // Mock PayMongo service
    $this->mock(PayMongoService::class, function ($mock) {
        $mock->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn([
                'id' => 'sess_test123',
                'checkout_url' => 'https://checkout.paymongo.com/test',
                'status' => 'pending',
            ]);
    });

    Livewire::test('public::pages.create-booking', ['publicproperty' => $property->id])
        ->set('check_in', now()->addDays(10)->format('Y-m-d'))
        ->set('check_out', now()->addDays(12)->format('Y-m-d'))
        ->call('addService', $service->id)
        ->call('submit')
        ->assertRedirect('https://checkout.paymongo.com/test');

    // Verify booking and payment records exist
    $this->assertDatabaseHas('bookings', [
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => 'pending',
        'booking_type' => 'full',
        'total_amount' => 2500, // 2 days * 1000 + 500
    ]);

    /** @var Booking|null $booking */
    $booking = Booking::withoutGlobalScope(TenantScope::class)
        ->where('tenant_id', $tenant->id)
        ->first();

    $this->assertNotNull($booking);

    $this->assertDatabaseHas('payments', [
        'tenant_id' => $tenant->id,
        'booking_id' => $booking->id,
        'payment_status' => 'unpaid',
        'payment_type' => 'full',
        'paymongo_session_id' => 'sess_test123',
        'amount' => 2500,
    ]);
});