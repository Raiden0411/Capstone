<?php

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createTenantAndUser(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    return [$tenant, $user];
}

it('marks a pending booking as overdue if past deadline and not fully paid', function () {
    [$tenant, $user] = createTenantAndUser();

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => Booking::STATUS_PENDING,
        'total_amount' => 1000,
        'created_at' => now()->subMinutes(31),
    ]);

    expect($booking->isOverdue())->toBeTrue();
});

it('does not mark a booking as overdue if within deadline', function () {
    [$tenant, $user] = createTenantAndUser();

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => Booking::STATUS_PENDING,
        'total_amount' => 1000,
        'created_at' => now()->subMinutes(20),
    ]);

    expect($booking->isOverdue())->toBeFalse();
});

it('does not mark a booking as overdue if fully paid', function () {
    [$tenant, $user] = createTenantAndUser();

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => Booking::STATUS_PENDING,
        'total_amount' => 1000,
        'created_at' => now()->subMinutes(31),
    ]);

    Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'booking_id' => $booking->id,
        'amount' => 1000,
        'payment_status' => 'paid',
    ]);

    expect($booking->isOverdue())->toBeFalse();
});

it('cancels a booking if it is overdue', function () {
    [$tenant, $user] = createTenantAndUser();

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => Booking::STATUS_PENDING,
        'total_amount' => 1000,
        'created_at' => now()->subMinutes(40),
    ]);

    $booking->cancelIfOverdue();

    expect($booking->fresh()->status)->toBe(Booking::STATUS_CANCELLED);
});

it('updates property status to available when booking is cancelled', function () {
    [$tenant, $user] = createTenantAndUser();

    $property = Property::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'occupied',
    ]);

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => Booking::STATUS_PENDING,
    ]);

    BookingItem::factory()->create([
        'tenant_id' => $tenant->id,
        'booking_id' => $booking->id,
        'property_id' => $property->id,
    ]);

    $booking->update(['status' => Booking::STATUS_CANCELLED]);

    expect($property->fresh()->status)->toBe('available');
});