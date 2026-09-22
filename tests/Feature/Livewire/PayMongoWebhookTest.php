<?php

use App\Jobs\ProcessPayMongoPayment;
use Illuminate\Support\Facades\Bus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('processes valid PayMongo webhook signature', function () {
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

    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'test_secret');
    $signatureHeader = "t={$timestamp},te={$signature}";

    $response = $this->postJson(
        route('paymongo.webhook'),
        json_decode($payload, true),
        ['Paymongo-Signature' => $signatureHeader]
    );

    $response->assertOk();

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