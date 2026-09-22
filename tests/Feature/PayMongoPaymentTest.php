<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Transaction;
use App\Services\PayMongoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('updates payment and booking status when PayMongo session is paid', function () {
    $tenant = Tenant::factory()->create();
    $user   = User::factory()->create(['tenant_id' => $tenant->id]);

    $booking = Booking::factory()->create([
        'tenant_id'    => $tenant->id,
        'user_id'      => $user->id,
        'status'       => Booking::STATUS_PENDING,
        'total_amount' => 500,
    ]);

    $payment = Payment::factory()->create([
        'tenant_id'           => $tenant->id,
        'booking_id'          => $booking->id,
        'paymongo_session_id' => 'sess_123',
        'payment_status'      => 'pending',
        'payment_type'        => 'full',
        'amount'              => 500,
    ]);

    /*
     * Why call finalizeCheckoutSession() directly instead of running the
     * ProcessPayMongoPayment job?
     *
     * The job delegates to PayMongoService::processPayment(), which
     * first asks the Paymongo HTTP client (Luigel\Paymongo\Paymongo)
     * for the session's paid/pending state. That client is registered
     * as a container singleton by the package, and Mockery binding on
     * the class name doesn't reliably override it in Pest. When the
     * mock leaks, the service sees a real (or fake-empty) session,
     * never reaches the paid branch, and the test fails with the
     * payment still marked "pending".
     *
     * finalizeCheckoutSession() is the SAME code path processPayment()
     * runs AFTER it detects a paid session — it looks up the payment
     * row by session ID and runs the finalizePayment() transaction
     * (payment -> paid, Transaction created, booking -> confirmed).
     * It does NOT need the Paymongo client, so it exercises the exact
     * business logic we care about, without the package-binding noise.
     *
     * The "session is paid?" detection itself is verified by:
     *   • PayMongoWebhookTest    — signature + job dispatch
     *   • PaymentProcessingTest  — SFC reads a paid snapshot via
     *                              Http::fake() and finalizes
     */
    $service = app(PayMongoService::class);
    $result  = $service->finalizeCheckoutSession('sess_123');

    expect($result)->toBeTrue();

    // Payment marked paid
    $freshPayment = $payment->fresh();
    expect($freshPayment->payment_status)->toBe('paid');
    expect($freshPayment->paid_at)->not->toBeNull();

    // Booking confirmed
    expect($booking->fresh()->status)->toBe(Booking::STATUS_CONFIRMED);

    // Income transaction recorded
    expect(Transaction::query()
        ->where('booking_id', $booking->id)
        ->where('type', 'income')
        ->where('amount', 500)
        ->exists()
    )->toBeTrue();
});