<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Scopes\TenantScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cancel pending bookings whose payment window has closed.
 *
 * ── WHY THIS EXISTS ─────────────────────────────────────────────
 *
 * Before this command, the auto-cancel was driven entirely from a
 * client-side Alpine timer in `⚡my-bookings.blade.php`. That has
 * three failure modes:
 *
 *   1. The user closes the tab → no cancel fires at all.
 *   2. The user's device clock is wrong → the timer fires at the
 *      wrong time (or immediately).
 *   3. The user has multiple tabs open → multiple `cancelOverdue`
 *      POSTs race for the same booking.
 *
 * This command is the single source of truth. It runs every minute
 * via the scheduler. The client timer is now display-only.
 *
 * ── WHAT IT DOES ────────────────────────────────────────────────
 *
 * Finds every `pending` booking whose `created_at` is more than
 * PAYMENT_DEADLINE_MINUTES ago and flips it to `cancelled` inside a
 * row-locking transaction. Skips any booking that received a paid
 * payment in the interim (belt-and-braces against a race with the
 * PayMongo webhook). Property release is handled automatically by
 * Booking's `booted()` hook when the status changes.
 */
class CancelOverdueBookings extends Command
{
    protected $signature = 'bookings:cancel-overdue
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Cancel pending bookings whose payment window has closed';

    public function handle(): int
    {
        $deadlineMinutes = Booking::PAYMENT_DEADLINE_MINUTES;
        $cutoff          = now()->subMinutes($deadlineMinutes);

        $query = Booking::withoutGlobalScope(TenantScope::class)
            ->where('status', Booking::STATUS_PENDING)
            ->where('created_at', '<', $cutoff);

        if ($this->option('dry-run')) {
            $bookings = $query->orderBy('id')->get(['id', 'booking_reference', 'created_at']);

            if ($bookings->isEmpty()) {
                $this->info("No overdue bookings (> {$deadlineMinutes} min).");
                return self::SUCCESS;
            }

            $this->table(
                ['ID', 'Reference', 'Created'],
                $bookings->map(fn ($b) => [
                    $b->id,
                    $b->booking_reference,
                    $b->created_at?->toDateTimeString(),
                ])
            );

            $this->info("Would cancel {$bookings->count()} booking(s). [dry-run]");
            return self::SUCCESS;
        }

        $cancelled = 0;
        $skipped   = 0;

        // chunkById streams the IDs; each booking gets its own
        // transaction so the row lock is per-booking, not page-wide.
        $query->orderBy('id')->chunkById(100, function ($bookings) use (&$cancelled, &$skipped): void {
            foreach ($bookings as $booking) {
                $didCancel = DB::transaction(function () use ($booking): bool {
                    $locked = Booking::withoutGlobalScope(TenantScope::class)
                        ->whereKey($booking->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $locked || $locked->status !== Booking::STATUS_PENDING) {
                        return false;
                    }

                    // Race guard — the PayMongo webhook may have
                    // finalized a payment between the outer query
                    // and this lock. Never cancel a paid booking.
                    if ($locked->isFullyPaid()) {
                        return false;
                    }

                    $locked->update(['status' => Booking::STATUS_CANCELLED]);

                    return true;
                });

                if ($didCancel) {
                    $cancelled++;
                } else {
                    $skipped++;
                }
            }
        });

        $this->info(sprintf(
            'Cancelled %d overdue booking(s).%s',
            $cancelled,
            $skipped > 0 ? " Skipped {$skipped} (already resolved)." : ''
        ));

        if ($cancelled > 0) {
            Log::info('Auto-cancelled overdue bookings', [
                'count'          => $cancelled,
                'deadline_min'   => $deadlineMinutes,
            ]);
        }

        return self::SUCCESS;
    }
}