<?php

namespace App\Observers;

use App\Mail\BookingCancelled;
use App\Mail\BookingConfirmed;
use App\Mail\BookingReceived;
use App\Mail\BookingReserved;
use App\Mail\NewBookingAlert;
use App\Models\Booking;
use App\Services\PublicNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Invalidates public-notification caches AND dispatches booking
 * lifecycle emails on every booking state change that matters.
 *
 * Cache flushes and mail dispatches are deferred to after-commit:
 * if the surrounding transaction rolls back, neither fires. Mail
 * failures are swallowed (logged only) — a broken SMTP server must
 * never roll back a committed booking.
 *
 * All mail is queued (`->queue()`) so the creating request never
 * blocks on SMTP. `php artisan queue:work` must be running for
 * emails to actually leave the queue.
 */
class BookingObserver
{
    public function __construct(
        protected PublicNotificationService $notifications,
    ) {}

    public function created(Booking $booking): void
    {
        $this->flushStakeholders($booking);
        $this->sendCreatedMail($booking);
    }

    public function updated(Booking $booking): void
    {
        if (! $booking->wasChanged('status')) {
            return;
        }

        $this->flushStakeholders($booking);
        $this->sendStatusChangeMail($booking);
    }

    public function deleted(Booking $booking): void
    {
        $this->flushStakeholders($booking);
    }

    // ─────────────────────────────────────────────────────
    //  Cache flushes (unchanged)
    // ─────────────────────────────────────────────────────

    private function flushStakeholders(Booking $booking): void
    {
        $bookingUserId = (int) $booking->user_id;
        $tenantId      = (int) ($booking->tenant_id ?? 0);

        DB::afterCommit(function () use ($bookingUserId, $tenantId): void {
            if ($bookingUserId > 0) {
                $this->notifications->flushForUserId($bookingUserId);
            }

            if ($tenantId > 0) {
                $this->notifications->flushTenantAdmins($tenantId);
            }
        });
    }

    // ─────────────────────────────────────────────────────
    //  Mail dispatches
    // ─────────────────────────────────────────────────────

    private function sendCreatedMail(Booking $booking): void
    {
        DB::afterCommit(function () use ($booking): void {
            // Hydrate relations the mailables depend on. Eloquent
            // caches these; the queued jobs serialise the model with
            // relations intact.
            $booking->loadMissing(['user', 'tenant', 'items.property']);

            // Tourist receipt.
            $touristEmail = $booking->user?->email;
            if ($touristEmail) {
                $this->safeMail(
                    fn () => Mail::to($touristEmail)->queue(new BookingReceived($booking)),
                    'booking-received',
                    $booking->id,
                );
            }

            // Tenant admins — new-booking alert.
            $tenant = $booking->tenant;
            if (! $tenant) {
                return;
            }

            $admins = $tenant->users()
                ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
                ->get(['id', 'name', 'email']);

            foreach ($admins as $admin) {
                if (! $admin->email) {
                    continue;
                }

                $this->safeMail(
                    fn () => Mail::to($admin->email)->queue(new NewBookingAlert($booking)),
                    'new-booking-alert',
                    $booking->id,
                );
            }
        });
    }

    private function sendStatusChangeMail(Booking $booking): void
    {
        DB::afterCommit(function () use ($booking): void {
            $booking->loadMissing(['user', 'tenant', 'items.property', 'payments']);

            $to = $booking->user?->email;
            if (! $to) {
                return;
            }

            $mailable = match ($booking->status) {
                Booking::STATUS_CONFIRMED => new BookingConfirmed($booking),
                Booking::STATUS_RESERVED  => new BookingReserved($booking),
                Booking::STATUS_CANCELLED => new BookingCancelled($booking),
                default                   => null,
            };

            if (! $mailable) {
                return;
            }

            $this->safeMail(
                fn () => Mail::to($to)->queue($mailable),
                'booking-status-' . $booking->status,
                $booking->id,
            );
        });
    }

    // ─────────────────────────────────────────────────────
    //  Safe wrapper
    // ─────────────────────────────────────────────────────

    private function safeMail(callable $callback, string $context, int $bookingId): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            Log::warning('Booking mail dispatch failed', [
                'context'    => $context,
                'booking_id' => $bookingId,
                'error'      => $e->getMessage(),
                'type'       => get_class($e),
            ]);
        }
    }
}