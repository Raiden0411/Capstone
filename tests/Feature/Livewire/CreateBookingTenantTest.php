<?php

use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Booking;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

uses(RefreshDatabase::class);

it('creates a walk-in booking with property and service, then redirects', function () {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();

    /** @var User $adminUser */
    $adminUser = User::factory()->create(['tenant_id' => $tenant->id]);

    /** @var PropertyType $propertyType */
    $propertyType = PropertyType::factory()->create([
        'tenant_id' => null,
        'name' => 'Standard Room',
    ]);

    /** @var Property $property */
    $property = Property::factory()->create([
        'tenant_id'        => $tenant->id,
        'property_type_id' => $propertyType->id,
        'name'             => 'Test Room',
        'price'            => 1000,
        'capacity'         => 2,
        'quantity'         => 1,
        'status'           => 'available',
        'is_active'        => true,
    ]);

    /** @var Service $service */
    $service = Service::create([
        'tenant_id' => $tenant->id,
        'name'      => 'Breakfast',
        'price'     => 250,
        'is_active' => true,
    ]);

    $this->actingAs($adminUser);

    $component = Livewire::test('tenant::pages.booking.create-booking');

    $checkIn  = Carbon::today()->addDays(5)->toDateString();
    $checkOut = Carbon::today()->addDays(7)->toDateString();

    $component->set('customerName', 'Juan Dela Cruz');
    $component->set('customerPhone', '09171234567');
    $component->set('customerEmail', 'juan@example.com');
    $component->set('check_in', $checkIn);
    $component->set('check_out', $checkOut);
    $component->set('payment_method', 'cash');

    $component->call('toggleProperty', $property->id);
    $component->call('toggleService', $service->id);

    // Verify total: (1000 * 2 days) + 250 = 2250
    $component->assertSet('totalAmount', 2250);

    // Submit
    $component->call('submit')->assertHasNoErrors();

    // Fetch the guest user created by the component
    /** @var User $guestUser */
    $guestUser = User::query()->firstWhere('email', 'juan@example.com');
    $this->assertNotNull($guestUser);

    // Verify booking created (asserting reference and totals only)
    $this->assertDatabaseHas('bookings', [
        'tenant_id'         => $tenant->id,
        'booking_reference' => $component->get('booking_reference'),
        'total_amount'      => 2250,
        'status'            => 'confirmed',
        'booking_type'      => 'full',
    ]);

    /** @var Booking $booking */
    $booking = Booking::query()->firstWhere('booking_reference', $component->get('booking_reference'));
    $this->assertNotNull($booking);

    // Check dates separately
    $this->assertEquals($checkIn, $booking->check_in->format('Y-m-d'));
    $this->assertEquals($checkOut, $booking->check_out->format('Y-m-d'));

    // Verify the booking user is the guest user, not the admin
    $this->assertEquals($guestUser->id, $booking->user_id);

    // Verify booking item
    $this->assertDatabaseHas('booking_items', [
        'booking_id'  => $booking->id,
        'property_id' => $property->id,
        'quantity'    => 1,
        'subtotal'    => 2000, // 1000 * 2 days
    ]);

    // Verify booking service
    $this->assertDatabaseHas('booking_services', [
        'booking_id' => $booking->id,
        'service_id' => $service->id,
        'quantity'   => 1,
        'subtotal'   => 250,
    ]);

    // Verify payment
    $this->assertDatabaseHas('payments', [
        'booking_id'     => $booking->id,
        'amount'         => 2250,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'payment_type'   => 'full',
    ]);

    // Verify redirect to booking show
    $component->assertRedirect(route('tenant.bookings.show', ['booking' => $booking->id]));
});