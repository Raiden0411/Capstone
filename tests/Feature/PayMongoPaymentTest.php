<?php

use App\Jobs\ProcessPayMongoPayment;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Luigel\Paymongo\Facades\Paymongo;
use Luigel\Paymongo\Models\Checkout;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('updates payment and booking status when PayMongo session is paid', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $booking = Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => Booking::STATUS_PENDING,
        'total_amount' => 500,
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'booking_id' => $booking->id,
        'paymongo_session_id' => 'sess_123',
        'payment_status' => 'unpaid',
        'amount' => 500,
    ]);

    // Mock the Checkout model to return the required data via getData()
    $checkout = \Mockery::mock(Checkout::class);
    $checkout->shouldReceive('getData')
        ->once()
        ->andReturn([
            'id' => 'sess_123',
            'status' => 'paid',
        ]);

    Paymongo::shouldReceive('checkout->find')
        ->once()
        ->with('sess_123')
        ->andReturn($checkout);

    $job = new ProcessPayMongoPayment('sess_123');
    $result = $job->handle();

    expect($result)->toBeTrue();
    expect($payment->fresh()->payment_status)->toBe('paid');
    expect($booking->fresh()->status)->toBe(Booking::STATUS_CONFIRMED);
});