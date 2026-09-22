<?php

use App\Models\Booking;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\BookingItem;
use App\Models\BookingService;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

uses(RefreshDatabase::class);

it('loads existing booking data and updates it', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();

    /** @var User $adminUser */
    $adminUser = User::factory()->create(['tenant_id' => $tenant->id]);
    $adminUser->assignRole('admin');

    /** @var User $guestUser */
    $guestUser = User::factory()->create([
        'tenant_id' => $tenant->id,
        'name'      => 'Original Guest',
        'phone'     => '09171234567',
        'email'     => 'guest@example.com',
    ]);

    /** @var PropertyType $propertyType */
    $propertyType = PropertyType::factory()->create([
        'tenant_id' => null,
        'name'      => 'Standard Room',
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

    $checkIn  = Carbon::today()->addDays(5)->toDateString();
    $checkOut = Carbon::today()->addDays(7)->toDateString();

    /*
     * total_amount must equal the sum of items + services. The SFC's
     * mount() computes a discount as (sum) − total_amount, so a mismatch
     * makes mount() think a discount was applied, which it then re-saves
     * on update. Items = 1000 × 1 × 2 days = 2000. Services = 250.
     * Sum = 2250.
     */
    /** @var Booking $booking */
    $booking = Booking::factory()->create([
        'tenant_id'         => $tenant->id,
        'user_id'           => $guestUser->id,
        'booking_reference' => 'BK-EDITEST',
        'check_in'          => $checkIn,
        'check_out'         => $checkOut,
        'total_amount'      => 2250,
        'status'            => 'pending',
        'booking_type'      => 'full',
    ]);

    BookingItem::create([
        'tenant_id'   => $tenant->id,
        'booking_id'  => $booking->id,
        'property_id' => $property->id,
        'price'       => $property->price,
        'quantity'    => 1,
        'subtotal'    => 2000,
    ]);

    BookingService::create([
        'tenant_id'  => $tenant->id,
        'booking_id' => $booking->id,
        'service_id' => $service->id,
        'quantity'   => 1,
        'subtotal'   => 250,
    ]);

    // Test component loads existing data
    $component = Livewire::test('tenant::pages.booking.edit-booking', ['booking' => $booking]);

    $component->assertSet('customerName', 'Original Guest')
        ->assertSet('customerPhone', '09171234567')
        ->assertSet('customerEmail', 'guest@example.com')
        ->assertSet('check_in', $checkIn)
        ->assertSet('check_out', $checkOut)
        ->assertSet('status', 'pending')
        ->assertSet('booking_type', 'full');

    // Update guest info and dates
    $newCheckIn  = Carbon::today()->addDays(10)->toDateString();
    $newCheckOut = Carbon::today()->addDays(12)->toDateString();

    $component->set('customerName', 'Updated Guest');
    $component->set('customerPhone', '09170000000');
    $component->set('customerEmail', 'updated@example.com');
    $component->set('check_in', $newCheckIn);
    $component->set('check_out', $newCheckOut);
    $component->set('status', 'confirmed');

    // Submit update
    $component->call('update')
        ->assertHasNoErrors()
        ->assertRedirect(route('tenant.bookings.show', ['booking' => $booking->id]));

    // Verify guest user updated
    $this->assertDatabaseHas('users', [
        'id'    => $guestUser->id,
        'name'  => 'Updated Guest',
        'phone' => '09170000000',
        'email' => 'updated@example.com',
    ]);

    // Verify booking updated
    $booking->refresh();
    $this->assertEquals('Updated Guest', $booking->user->name);
    $this->assertEquals($newCheckIn, $booking->check_in->format('Y-m-d'));
    $this->assertEquals($newCheckOut, $booking->check_out->format('Y-m-d'));
    $this->assertEquals('confirmed', $booking->status);
    $this->assertEquals('full', $booking->booking_type);

    // Total stays 2250 — same 2 nights, same property, same service.
    $this->assertEquals(2250, (float) $booking->total_amount);
});