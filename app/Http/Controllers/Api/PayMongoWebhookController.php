<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPayMongoPayment;
use App\Models\Booking;
use App\Scopes\TenantScope;
use App\Services\PayMongoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayMongoWebhookController extends Controller
{
    public function __invoke(Request $request, PayMongoService $payMongoService)
    {
        $payload         = $request->getContent();
        $signatureHeader = $request->header('Paymongo-Signature');

        if (! $this->verifySignature($payload, $signatureHeader)) {
            Log::warning('PayMongo webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $data      = json_decode($payload, true) ?: [];
        $eventType = $data['data']['attributes']['type'] ?? null;

        if ($eventType === 'checkout_session.payment.paid') {
            $sessionId = $data['data']['attributes']['data']['id'] ?? null;

            if ($sessionId) {
                ProcessPayMongoPayment::dispatch($sessionId);
                return response()->json(['status' => 'ok']);
            }

            Log::info('PayMongo webhook: paid event missing session ID', ['event' => $eventType]);
            return response()->json(['status' => 'ignored']);
        }

        // PayMongo emits BOTH `payment.refunded` (creation) and
        // `payment.refund.updated` (status change). The older
        // `refund.updated` is retained defensively — different API
        // versions have historically used different names.
        if (in_array($eventType, [
            'payment.refunded',
            'payment.refund.updated',
            'refund.updated',
        ], true)) {
            $this->handleRefundEvent($data);
            return response()->json(['status' => 'ok']);
        }

        Log::info('PayMongo webhook received unsupported event', ['type' => $eventType]);
        return response()->json(['status' => 'ignored']);
    }

    protected function handleRefundEvent(array $data): void
    {
        $payload = $data['data']['attributes']['data'] ?? null;

        if (! is_array($payload)) {
            return;
        }

        $refunds = [];

        if (($payload['type'] ?? null) === 'refund') {
            $refunds = [$payload];
        } elseif (($payload['type'] ?? null) === 'payment') {
            $refunds = $payload['attributes']['refunds'] ?? [];
        }

        foreach ($refunds as $refund) {
            if (! is_array($refund)) {
                continue;
            }

            $refundId = $refund['id'] ?? null;
            $status   = $refund['attributes']['status'] ?? null;

            if (! is_string($refundId) || $refundId === '' || ! is_string($status)) {
                continue;
            }

            $booking = Booking::withoutGlobalScope(TenantScope::class)
                ->where('paymongo_refund_id', $refundId)
                ->first();

            if (! $booking) {
                continue;
            }

            $this->applyRefundStatus($booking, $status);
        }
    }

    protected function applyRefundStatus(Booking $booking, string $status): void
    {
        if ($booking->refund_status === Booking::REFUND_STATUS_PROCESSED) {
            return;
        }

        $mapped = match ($status) {
            'succeeded' => Booking::REFUND_STATUS_PROCESSED,
            'failed'    => Booking::REFUND_STATUS_REJECTED,
            default     => Booking::REFUND_STATUS_PENDING,
        };

        $booking->update([
            'refund_status'       => $mapped,
            'refund_processed_at' => $mapped === Booking::REFUND_STATUS_PROCESSED ? now() : null,
        ]);

        Log::info('Refund status updated via webhook', [
            'booking_id' => $booking->id,
            'refund_id'  => $booking->paymongo_refund_id,
            'status'     => $mapped,
        ]);
    }

    protected function verifySignature(string $payload, ?string $signatureHeader): bool
    {
        if (! $signatureHeader) {
            return false;
        }

        $secret = config('paymongo.webhook_secret');
        if (! $secret) {
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

        $timestamp = $parts['t']  ?? '';
        $signature = $parts['te'] ?? $parts['li'] ?? '';

        if (! $timestamp || ! $signature) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $signedPayload     = "{$timestamp}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}