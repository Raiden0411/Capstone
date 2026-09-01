<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\PayMongoService;

class PayMongoWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $payload = $request->getContent();
        $signatureHeader = $request->header('Paymongo-Signature');

        if (!$this->verifySignature($payload, $signatureHeader)) {
            Log::warning('PayMongo webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $data = $request->json()->all();
        $eventType = $data['data']['attributes']['type'] ?? null;
        $sessionId = $data['data']['attributes']['data']['id'] ?? null;

        if ($eventType === 'checkout_session.payment.paid' && $sessionId) {
            // Dispatch queued job (async fallback)
            \App\Jobs\ProcessPayMongoPayment::dispatch($sessionId);

            // Also process synchronously to update immediately
            app(PayMongoService::class)->handlePaymentPaid($sessionId);

            Log::info('PayMongo webhook processed synchronously', ['session_id' => $sessionId]);
        }

        return response()->json(['status' => 'ok']);
    }

    protected function verifySignature(string $payload, ?string $signatureHeader): bool
    {
        if (!$signatureHeader) {
            return false;
        }

        $secret = config('paymongo.webhook_secret') ?: env('PAYMONGO_WEBHOOK_SECRET');
        if (!$secret) {
            Log::error('PayMongo webhook secret is not set.');
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $kv = explode('=', $part, 2);
            if (count($kv) === 2) {
                $parts[trim($kv[0])] = trim($kv[1]);
            }
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['te'] ?? $parts['li'] ?? '';

        if (!$timestamp || !$signature) {
            return false;
        }

        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}