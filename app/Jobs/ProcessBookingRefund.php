<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\Payment;
use App\Scopes\TenantScope;
use App\Services\PayMongoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProcessBookingRefund implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries    = 5;
    public int $timeout  = 30;
    public array $backoff = [10, 30, 60, 120];

    public function __construct(public int $bookingId) {}

    public function handle(PayMongoService $payMongo): void
    {
        $booking = Booking::withoutGlobalScope(TenantScope::class)->find($this->bookingId);

        if (! $booking) {
            return;
        }

        if ($booking->refund_status !== Booking::REFUND_STATUS_PENDING) {
            return;
        }

        if ((float) $booking->refund_amount <= 0) {
            $booking->update([
                'refund_status'       => Booking::REFUND_STATUS_NONE,
                'refund_processed_at' => now(),
            ]);
            return;
        }

        // Prefer the payment the refund is being issued against.
        $payment = Payment::withoutGlobalScope(TenantScope::class)
            ->where('booking_id', $booking->id)
            ->where('payment_status', 'paid')
            ->whereNotNull('paymongo_payment_id')
            ->latest('id')
            ->first();

        // If no payment with a resolved ID exists, check whether any
        // paid payment has a PayMongo session. If so, resolve the
        // payment ID retroactively — this handles the case where the
        // webhook fired before the payments array was populated.
        if (! $payment) {
            $paymentWithSession = Payment::withoutGlobalScope(TenantScope::class)
                ->where('booking_id', $booking->id)
                ->where('payment_status', 'paid')
                ->whereNotNull('paymongo_session_id')
                ->latest('id')
                ->first();

            if ($paymentWithSession) {
                $resolvedId = $payMongo->resolvePaymentIdFromSession(
                    (string) $paymentWithSession->paymongo_session_id
                );

                if ($resolvedId !== null) {
                    $paymentWithSession->update(['paymongo_payment_id' => $resolvedId]);
                    $payment = $paymentWithSession;

                    Log::info('Resolved missing PayMongo payment ID from session', [
                        'booking_id'        => $booking->id,
                        'session_id'        => $paymentWithSession->paymongo_session_id,
                        'paymongo_payment_id' => $resolvedId,
                    ]);
                }
            }
        }

        // Only fall through to the manual-refund branch when the
        // payment has NO PayMongo session at all — i.e. cash or
        // truly manual. A payment with a session but no resolvable
        // ID is a bug, not a manual refund; throw so the queue
        // retries and ops investigates.
        if (! $payment) {
            $hasAnyGatewayTrace = Payment::withoutGlobalScope(TenantScope::class)
                ->where('booking_id', $booking->id)
                ->where('payment_status', 'paid')
                ->where(function ($q) {
                    $q->whereNotNull('paymongo_session_id')
                      ->orWhereNotNull('paymongo_payment_id');
                })
                ->exists();

            if ($hasAnyGatewayTrace) {
                throw new RuntimeException(
                    'Paid payment has a PayMongo trace but no resolvable payment ID. '
                    . 'Manual review required — do not mark refund as processed.'
                );
            }

            // Genuine manual/cash payment. Handover handled offline.
            $booking->update([
                'refund_status'       => Booking::REFUND_STATUS_PROCESSED,
                'refund_processed_at' => now(),
            ]);

            Log::info('Booking refund marked processed (no gateway payment)', [
                'booking_id' => $booking->id,
                'amount'     => $booking->refund_amount,
                'method'     => 'manual',
            ]);

            return;
        }

        $refund = $payMongo->refundPayment(
            paymentId: $payment->paymongo_payment_id,
            amount:    (float) $booking->refund_amount,
            notes:     "Cancellation refund for booking {$booking->booking_reference}",
            bookingId: (string) $booking->id,
        );

        if (! $refund || empty($refund['id'])) {
            throw new RuntimeException('PayMongo refund API returned no refund object.');
        }

        $isProcessed = ($refund['status'] ?? null) === 'succeeded';

        $booking->update([
            'paymongo_refund_id'  => $refund['id'],
            'refund_status'       => $isProcessed
                ? Booking::REFUND_STATUS_PROCESSED
                : Booking::REFUND_STATUS_PENDING,
            'refund_processed_at' => $isProcessed ? now() : null,
        ]);

        Log::info('PayMongo refund dispatched', [
            'booking_id' => $booking->id,
            'refund_id'  => $refund['id'],
            'status'     => $refund['status'],
            'amount'     => $booking->refund_amount,
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::critical('Booking refund job permanently failed', [
            'booking_id' => $this->bookingId,
            'error'      => $e->getMessage(),
        ]);

        $booking = Booking::withoutGlobalScope(TenantScope::class)->find($this->bookingId);

        if ($booking && $booking->refund_status === Booking::REFUND_STATUS_PENDING) {
            $booking->update([
                'refund_status'       => Booking::REFUND_STATUS_REJECTED,
                'refund_processed_at' => now(),
            ]);
        }
    }
}