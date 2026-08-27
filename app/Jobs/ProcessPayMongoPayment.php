<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Booking;
use Luigel\Paymongo\Facades\Paymongo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPayMongoPayment implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $sessionId) {}

    public function handle(): void
    {
        try {
            $checkout = Paymongo::checkout()->find($this->sessionId);
        } catch (\Exception $e) {
            Log::error('PayMongo session not found: ' . $e->getMessage());
            return;
        }

        if ($checkout->status !== 'paid') {
            return;
        }

        DB::transaction(function () use ($checkout) {
            $payment = Payment::query()
                ->where('paymongo_session_id', $this->sessionId)
                ->first();

            if (!$payment || $payment->payment_status === 'paid') {
                return;
            }

            $payment->update([
                'payment_status'   => 'paid',
                'paid_at'          => now(),
                'reference_number' => $checkout->id,
            ]);

            Transaction::create([
                'tenant_id'   => $payment->tenant_id,
                'booking_id'  => $payment->booking_id,
                'type'        => 'income',
                'amount'      => $payment->amount,
                'description' => 'PayMongo payment: ' . $checkout->id,
            ]);

            $booking = $payment->booking;
            $totalPaid = $booking->payments()->where('payment_status', 'paid')->sum('amount');

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
    }
}