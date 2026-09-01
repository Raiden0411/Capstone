<?php

use App\Jobs\ProcessPayMongoPayment;
use App\Services\PayMongoService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('processes valid PayMongo webhook signature', function () {
    // Set a known webhook secret for testing
    config()->set('paymongo.webhook_secret', 'test_secret');

    // Prevent the queued job from actually executing
    Bus::fake();

    $sessionId = 'sess_123';

    // Payload as sent by PayMongo
    $payload = json_encode([
        'data' => [
            'attributes' => [
                'type' => 'checkout_session.payment.paid',
                'data' => ['id' => $sessionId],
            ],
        ],
    ]);

    // Generate valid signature
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'test_secret');
    $signatureHeader = "t={$timestamp},te={$signature}";

    // Mock the PayMongoService to avoid real API calls
    $this->mock(PayMongoService::class, function ($mock) use ($sessionId) {
        $mock->shouldReceive('handlePaymentPaid')
             ->once()
             ->with($sessionId);
    });

    $response = $this->postJson(
        route('paymongo.webhook'),
        json_decode($payload, true),
        ['Paymongo-Signature' => $signatureHeader]
    );

    $response->assertOk();

    // Verify the queued job was dispatched with the correct session ID
    Bus::assertDispatched(ProcessPayMongoPayment::class, function ($job) use ($sessionId) {
        return $job->sessionId === $sessionId;
    });
});

it('rejects invalid PayMongo webhook signature', function () {
    config()->set('paymongo.webhook_secret', 'test_secret');
    Bus::fake();

    $sessionId = 'sess_123';

    $payload = json_encode([
        'data' => [
            'attributes' => [
                'type' => 'checkout_session.payment.paid',
                'data' => ['id' => $sessionId],
            ],
        ],
    ]);

    // Invalid signature
    $timestamp = time();
    $signatureHeader = "t={$timestamp},te=invalid_signature";

    $response = $this->postJson(
        route('paymongo.webhook'),
        json_decode($payload, true),
        ['Paymongo-Signature' => $signatureHeader]
    );

    $response->assertStatus(401);
    Bus::assertNotDispatched(ProcessPayMongoPayment::class);
});