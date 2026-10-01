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

it('counts inclusive calendar days for the booking duration', function () {
    // Pins the day-count semantics from the create-booking SFC:
    //   same day → 1 day, +1 day → 2 days, +2 days → 3 days.
    // Guards against a silent revert to hotel-night counting.
    $tenant = Tenant::factory()->create();
    $propertyType = PropertyType::factory()->create(['tenant_id' => null]);
    $property = Property::factory()->create([
        'tenant_id' => $tenant->id,
        'property_type_id' => $propertyType->id,
        'price' => 1000,
    ]);

    /** @var User $user */
    $user = User::factory()->create();
    $this->actingAs($user);

    $base = now()->addDays(5)->format('Y-m-d');

    // Same day → 1 inclusive day
    Livewire::test('public::pages.create-booking', ['publicproperty' => $property->id])
        ->call('setDates', $base, $base)
        ->assertSet('totalDays', 1)
        ->assertSet('totalAmount', 1000);

    // +1 day → 2 inclusive days
    Livewire::test('public::pages.create-booking', ['publicproperty' => $property->id])
        ->call('setDates', $base, now()->addDays(6)->format('Y-m-d'))
        ->assertSet('totalDays', 2)
        ->assertSet('totalAmount', 2000);

    // +2 days → 3 inclusive days
    Livewire::test('public::pages.create-booking', ['publicproperty' => $property->id])
        ->call('setDates', $base, now()->addDays(7)->format('Y-m-d'))
        ->assertSet('totalDays', 3)
        ->assertSet('totalAmount', 3000);
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

    // +5 → +7 = 3 inclusive days
    // Total: 1000 × 3 + 250 = 3250
    // Reservation fee (20%):   650
    // Balance on arrival:     2600
    Livewire::test('public::pages.create-booking', ['publicproperty' => $property->id])
        ->set('check_in', now()->addDays(5)->format('Y-m-d'))
        ->set('check_out', now()->addDays(7)->format('Y-m-d'))
        ->call('addService', $service->id)
        ->assertSet('totalDays', 3)
        ->assertSet('totalAmount', 3250)
        ->assertSet('reservationFee', 650)
        ->assertSet('balanceOnArrival', 2600);
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

    $this->mock(PayMongoService::class, function ($mock) {
        $mock->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn([
                'id' => 'sess_test123',
                'checkout_url' => 'https://checkout.paymongo.com/test',
                'status' => 'pending',
            ]);
    });

    // +10 → +12 = 3 inclusive days
    // Total: 1000 × 3 + 500 = 3500
    Livewire::test('public::pages.create-booking', ['publicproperty' => $property->id])
        ->set('check_in', now()->addDays(10)->format('Y-m-d'))
        ->set('check_out', now()->addDays(12)->format('Y-m-d'))
        ->call('addService', $service->id)
        ->call('submit')
        ->assertRedirect('https://checkout.paymongo.com/test');

    $this->assertDatabaseHas('bookings', [
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => 'pending',
        'booking_type' => 'full',
        'total_amount' => 3500,
    ]);

    /** @var Booking|null $booking */
    $booking = Booking::withoutGlobalScope(TenantScope::class)
        ->where('tenant_id', $tenant->id)
        ->first();

    $this->assertNotNull($booking);

    // payment_status is 'pending' — 'unpaid' is not a valid canonical
    // status (handoff §2.10).
    $this->assertDatabaseHas('payments', [
        'tenant_id' => $tenant->id,
        'booking_id' => $booking->id,
        'payment_status' => 'pending',
        'payment_type' => 'full',
        'paymongo_session_id' => 'sess_test123',
        'amount' => 3500,
    ]);
});