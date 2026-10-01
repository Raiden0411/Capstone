<?php

namespace App\Services;

use App\Jobs\ProcessBookingRefund;
use App\Models\Booking;
use App\Models\Payment;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Single source of truth for booking cancellations and refunds.
 *
 * Tier logic (tourist-initiated):
 *   • 7 or more days before check_in  → 100% refund of paid amount
 *   • 3 to 6 days before check_in      →  50% refund of paid amount
 *   • Fewer than 3 days before check_in →   0% refund; booking still cancelled
 *
 * Admin-initiated cancellations always refund 100% of the paid amount,
 * regardless of timing.
 *
 * The refund AMOUNT is always a percentage of the PAID amount, never
 * of the total — a reservation that only paid the 20% fee must not
 * refund the full total it never collected.
 */
class BookingRefundService
{
    /**
     * Preview what a cancellation would yield without writing anything.
     * Used by the confirmation modals to show the tier before the user
     * commits.
     *
     * @return array{percentage: int, refund_amount: float, paid_amount: float, days_until: ?int, label: string}
     */
    public function previewRefund(Booking $booking, string $cancelledBy): array
    {
        $tier = $this->resolveTier($booking, $cancelledBy);

        $paidAmount = (float) Payment::withoutGlobalScope(TenantScope::class)
            ->where('booking_id', $booking->id)
            ->where('payment_status', 'paid')
            ->sum('amount');

        $refundAmount = round($paidAmount * ($tier['percentage'] / 100), 2);

        return [
            'percentage'    => $tier['percentage'],
            'refund_amount' => $refundAmount,
            'paid_amount'   => $paidAmount,
            'days_until'    => $tier['days_until'],
            'label'         => $this->tierLabel($tier['percentage'], $cancelledBy),
        ];
    }

    /**
     * Determine which tier applies. Pure calculation — no DB writes.
     *
     * @return array{percentage: int, days_until: ?int}
     */
    public function resolveTier(Booking $booking, string $cancelledBy): array
    {
        if ($cancelledBy === Booking::CANCELLED_BY_ADMIN) {
            return ['percentage' => Booking::REFUND_TIER_FULL, 'days_until' => null];
        }

        if (! $booking->check_in) {
            return ['percentage' => Booking::REFUND_TIER_NONE, 'days_until' => null];
        }

        $today   = now()->startOfDay();
        $checkIn = $booking->check_in->copy()->startOfDay();

        // false → preserve sign. Positive when check_in is in the future.
        $daysUntil = (int) $today->diffInDays($checkIn, false);

        if ($daysUntil >= Booking::FULL_REFUND_THRESHOLD_DAYS) {
            return ['percentage' => Booking::REFUND_TIER_FULL, 'days_until' => $daysUntil];
        }

        if ($daysUntil >= Booking::PARTIAL_REFUND_THRESHOLD_DAYS) {
            return ['percentage' => Booking::REFUND_TIER_PARTIAL, 'days_until' => $daysUntil];
        }

        return ['percentage' => Booking::REFUND_TIER_NONE, 'days_until' => $daysUntil];
    }

    public function tierLabel(int $percentage, string $cancelledBy): string
    {
        if ($cancelledBy === Booking::CANCELLED_BY_ADMIN) {
            return 'Full refund — cancelled by the business';
        }

        return match ($percentage) {
            Booking::REFUND_TIER_FULL    => 'Full refund — cancelled 7 or more days in advance',
            Booking::REFUND_TIER_PARTIAL => 'Partial refund — cancelled 3 to 6 days in advance',
            Booking::REFUND_TIER_NONE    => 'No refund — cancelled less than 3 days in advance',
            default                      => 'Refund',
        };
    }

    /**
     * Cancel the booking and record the refund state.
     *
     * The whole operation runs in a row-locking transaction so two
     * concurrent cancel requests (tourist + admin, or double-tap)
     * cannot both pass the canBeCancelled() guard.
     *
     * If a refund is owed, an async job is dispatched after commit.
     *
     * @throws RuntimeException when the booking cannot be cancelled.
     */
    public function cancel(
        Booking $booking,
        string $cancelledBy,
        ?string $reason = null,
    ): Booking {
        return DB::transaction(function () use ($booking, $cancelledBy, $reason): Booking {
            $locked = Booking::withoutGlobalScope(TenantScope::class)
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                throw new RuntimeException('Booking not found.');
            }

            if (! $locked->canBeCancelled()) {
                throw new RuntimeException('This booking cannot be cancelled.');
            }

            $preview      = $this->previewRefund($locked, $cancelledBy);
            $refundAmount = $preview['refund_amount'];
            $willRefund   = $refundAmount > 0;

            $locked->update([
                'status'              => Booking::STATUS_CANCELLED,
                'cancelled_by'        => $cancelledBy,
                'cancellation_reason' => $reason,
                'cancelled_at'        => now(),
                'refund_amount'       => $refundAmount,
                'refund_percentage'   => $preview['percentage'],
                'refund_status'       => $willRefund
                    ? Booking::REFUND_STATUS_PENDING
                    : Booking::REFUND_STATUS_NONE,
            ]);

            // Dispatch the async refund job only for bookings that
            // actually owe money. Unpaid or 0%-tier cancellations stay
            // in the 'none' state and never enter the pipeline.
            if ($willRefund) {
                ProcessBookingRefund::dispatch($locked->id);
            }

            return $locked->fresh();
        });
    }
}