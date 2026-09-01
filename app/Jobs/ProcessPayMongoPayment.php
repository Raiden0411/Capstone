<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Booking;
use App\Scopes\TenantScope;
use Luigel\Paymongo\Facades\Paymongo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPayMongoPayment implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $sessionId) {}

    public function handle(): bool
    {
        try {
            $checkout = Paymongo::checkout()->find($this->sessionId);
        } catch (\Exception $e) {
            Log::error('PayMongo session not found: ' . $e->getMessage());
            return false;
        }

        $checkoutData = $checkout->getData();
        $checkoutStatus = data_get($checkoutData, 'status');
        $checkoutId = data_get($checkoutData, 'id');

        if (!in_array($checkoutStatus, ['paid', 'succeeded'])) {
            Log::info('PayMongo session not paid yet', [
                'session_id' => $this->sessionId,
                'status'     => $checkoutStatus,
            ]);
            return false;
        }

        DB::transaction(function () use ($checkoutId) {
            $payment = Payment::withoutGlobalScope(TenantScope::class)
                ->where('paymongo_session_id', $this->sessionId)
                ->first();

            if (!$payment) {
                return;
            }

            // Update payment only if not already paid
            if ($payment->payment_status !== 'paid') {
                $payment->update([
                    'payment_status'   => 'paid',
                    'paid_at'          => now(),
                    'reference_number' => $checkoutId,
                ]);

                Transaction::create([
                    'tenant_id'   => $payment->tenant_id,
                    'booking_id'  => $payment->booking_id,
                    'type'        => 'income',
                    'amount'      => $payment->amount,
                    'description' => 'PayMongo payment: ' . $checkoutId,
                ]);
            }

            // Always update booking status based on total paid
            $booking = $payment->booking;
            $totalPaid = $booking->payments()
                ->withoutGlobalScope(TenantScope::class)
                ->where('payment_status', 'paid')
                ->sum('amount');

            if ($payment->payment_type === Payment::TYPE_RESERVATION) {
                $booking->update(['status' => Booking::STATUS_RESERVED]);
            } elseif ($totalPaid >= $booking->total_amount) {
                $booking->update(['status' => Booking::STATUS_CONFIRMED]);
            }

            Log::info('PayMongo payment processed', [
                'payment_id'    => $payment->id,
                'booking_id'    => $booking->id,
                'payment_type'  => $payment->payment_type,
                'total_paid'    => $totalPaid,
                'booking_status'=> $booking->fresh()->status,
            ]);
        });

        return true;
    }
}