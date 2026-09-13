<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPayMongoPayment;
use App\Services\PayMongoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayMongoWebhookController extends Controller
{
    public function __invoke(Request $request, PayMongoService $payMongoService)
    {
        $payload         = $request->getContent();
        $signatureHeader = $request->header('Paymongo-Signature');

        if (!$this->verifySignature($payload, $signatureHeader)) {
            Log::warning('PayMongo webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Decode once — avoid re-reading the body.
        $data      = json_decode($payload, true) ?: [];
        $eventType = $data['data']['attributes']['type'] ?? null;
        $sessionId = $data['data']['attributes']['data']['id'] ?? null;

        if ($eventType !== 'checkout_session.payment.paid' || !$sessionId) {
            Log::info('PayMongo webhook received unsupported event', [
                'type'       => $eventType,
                'session_id' => $sessionId,
            ]);

            return response()->json(['status' => 'ignored']);
        }

        // Process off-request. The job is idempotent (lockForUpdate + status check).
        ProcessPayMongoPayment::dispatch($sessionId);

        return response()->json(['status' => 'ok']);
    }

    protected function verifySignature(string $payload, ?string $signatureHeader): bool
    {
        if (!$signatureHeader) {
            return false;
        }

        $secret = config('paymongo.webhook_secret');
        if (!$secret) {
            Log::error('PayMongo webhook secret is not set.');
            return false;
        }

        // Header format: "t=...,te=..." or "t=...,li=...".
        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $kv = explode('=', $part, 2);
            if (count($kv) === 2) {
                $parts[trim($kv[0])] = trim($kv[1]);
            }
        }

        $timestamp = $parts['t']  ?? '';
        $signature = $parts['te'] ?? $parts['li'] ?? '';

        if (!$timestamp || !$signature) {
            return false;
        }

        // Replay protection: 5-minute tolerance.
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $signedPayload     = "{$timestamp}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}