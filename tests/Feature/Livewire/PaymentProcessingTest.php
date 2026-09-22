<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Transaction;
use Livewire\Livewire;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('processes a successful PayMongo payment and confirms the booking', function () {
    // ── Arrange: PayMongo secret + REST mock ─────────────────────
    config()->set('paymongo.secret_key', 'sk_test_fake_for_testing');

    /*
     * The SFC's checkStatus() makes a direct HTTP call to
     * /v1/checkout_sessions/{id} to read the gateway state. Fake it to
     * return "paid" so finalizeCheckoutSession() runs and confirms the
     * booking. Without the fake, the real PayMongo API would 404 on the
     * test session ID and the SFC would return null.
     */
    Http::fake([
        'api.paymongo.com/v1/checkout_sessions/*' => Http::response([
            'data' => [
                'id' => 'sess_payment_test',
                'attributes' => [
                    'status'   => 'paid',
                    'payments' => [
                        [
                            'id'         => 'pay_test',
                            'attributes' => ['status' => 'paid'],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();

    /** @var User $user */
    $user = User::factory()->create(['tenant_id' => null]);

    /** @var Booking $booking */
    $booking = Booking::factory()->create([
        'tenant_id'    => $tenant->id,
        'user_id'      => $user->id,
        'status'       => Booking::STATUS_PENDING,
        'total_amount' => 500,
        'booking_type' => Booking::TYPE_FULL,
    ]);

    /** @var Payment $payment */
    $payment = Payment::factory()->create([
        'tenant_id'           => $tenant->id,
        'booking_id'          => $booking->id,
        'amount'              => 500,
        'payment_method'      => 'gcash',
        'payment_status'      => 'pending',
        'payment_type'        => Payment::TYPE_FULL,
        'paymongo_session_id' => 'sess_payment_test',
    ]);

    $this->actingAs($user);

    // ── Act ──────────────────────────────────────────────────────
    Livewire::test('public::pages.payment-processing', ['bookingId' => $booking->id])
        ->call('checkStatus')
        ->assertRedirect(route('my-bookings'));

    // ── Assert ───────────────────────────────────────────────────
    $this->assertDatabaseHas('payments', [
        'id'               => $payment->id,
        'payment_status'   => 'paid',
        'reference_number' => 'sess_payment_test',
    ]);

    $booking->refresh();
    $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->status);

    $this->assertDatabaseHas('transactions', [
        'tenant_id'  => $tenant->id,
        'booking_id' => $booking->id,
        'type'       => 'income',
        'amount'     => 500,
    ]);
});

it('redirects immediately if booking is already confirmed', function () {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();

    /** @var User $user */
    $user = User::factory()->create(['tenant_id' => null]);

    /** @var Booking $booking */
    $booking = Booking::factory()->create([
        'tenant_id'    => $tenant->id,
        'user_id'      => $user->id,
        'status'       => Booking::STATUS_CONFIRMED,
        'total_amount' => 500,
        'booking_type' => Booking::TYPE_FULL,
    ]);

    $this->actingAs($user);

    Livewire::test('public::pages.payment-processing', ['bookingId' => $booking->id])
        ->call('checkStatus')
        ->assertRedirect(route('my-bookings'));

    // No additional transactions should be created
    $this->assertCount(0, Transaction::all());
});