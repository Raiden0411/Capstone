<?php

namespace App\Observers;

use App\Mail\BookingCancelled;
use App\Mail\BookingConfirmed;
use App\Mail\BookingReceived;
use App\Mail\BookingRefundProcessed;
use App\Mail\BookingReserved;
use App\Mail\NewBookingAlert;
use App\Models\Booking;
use App\Models\UserNotification;
use App\Services\PublicNotificationService;
use App\Services\UserNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class BookingObserver
{
    public function __construct(
        protected PublicNotificationService $notifications,
        protected UserNotificationService $userNotifications,
    ) {}

    public function created(Booking $booking): void
    {
        $this->flushStakeholders($booking);
        $this->sendCreatedMail($booking);
        $this->createBookingCreatedNotifications($booking);
    }

    public function updated(Booking $booking): void
    {
        if ($booking->wasChanged('status')) {
            $this->flushStakeholders($booking);
            $this->sendStatusChangeMail($booking);
            $this->createBookingStatusChangeNotifications($booking);
        }

        if ($booking->wasChanged('refund_status')) {
            $this->sendRefundStatusMail($booking);
        }
    }

    public function deleted(Booking $booking): void
    {
        $this->flushStakeholders($booking);
    }

    // ─────────────────────────────────────────────────────
    //  Persistent notifications
    // ─────────────────────────────────────────────────────

    private function createBookingCreatedNotifications(Booking $booking): void
    {
        DB::afterCommit(function () use ($booking): void {
            $booking->loadMissing(['user', 'tenant', 'items.property']);

            $property = $booking->items->first()?->property;

            $place = $property->name
                ?? $booking->tenant->name
                ?? 'your booking';

            if ($booking->user) {
                $this->userNotifications->notify($booking->user, [
                    'scope'   => UserNotification::SCOPE_TOURIST,
                    'type'    => 'booking',
                    'title'   => 'Booking received',
                    'message' => "Your booking at {$place} has been received. Complete payment within 30 minutes.",
                    'url'     => route('booking.receipt', ['booking' => $booking->id]),
                    'icon'    => 'clock',
                    'color'   => 'amber',
                ]);
            }

            if (! $booking->tenant) {
                return;
            }

            $admins = $booking->tenant->users()
                ->select('id', 'tenant_id', 'name', 'email')
                ->get()
                ->filter(fn ($u) => $u->hasRole('admin'));

            if ($admins->isEmpty()) {
                return;
            }

            $guestName = $booking->user->name ?? 'A guest';
            $ref       = $booking->booking_reference;

            $this->userNotifications->notifyMany($admins, [
                'scope'   => UserNotification::SCOPE_BUSINESS,
                'type'    => 'booking',
                'title'   => 'New booking request',
                'message' => "{$guestName} placed a new booking ({$ref}).",
                'url'     => route('tenant.bookings.show', $booking->id),
                'icon'    => 'inbox',
                'color'   => 'blue',
            ]);
        });
    }

    private function createBookingStatusChangeNotifications(Booking $booking): void
    {
        DB::afterCommit(function () use ($booking): void {
            $booking->loadMissing(['user', 'items.property']);

            $user = $booking->user;
            if (! $user) {
                return;
            }

            $property = $booking->items->first()?->property;
            $place    = $property->name
                ?? $booking->tenant->name
                ?? 'your booking';

            $payload = match ($booking->status) {
                Booking::STATUS_CONFIRMED => [
                    'type'    => 'booking',
                    'title'   => 'Booking confirmed',
                    'message' => "Your booking at {$place} is confirmed.",
                    'icon'    => 'check-circle',
                    'color'   => 'emerald',
                ],
                Booking::STATUS_RESERVED => [
                    'type'    => 'booking',
                    'title'   => 'Reservation confirmed',
                    'message' => "Your reservation at {$place} is locked in. Pay the balance before check-in.",
                    'icon'    => 'check-circle',
                    'color'   => 'blue',
                ],
                Booking::STATUS_CANCELLED => [
                    'type'    => 'booking',
                    'title'   => 'Booking cancelled',
                    'message' => $booking->hasRefund()
                        ? "Your booking at {$place} was cancelled. A refund of ₱" . number_format((float) $booking->refund_amount, 2) . " is being processed."
                        : "Your booking at {$place} has been cancelled.",
                    'icon'    => 'alert',
                    'color'   => 'rose',
                ],
                default => null,
            };

            if ($payload === null) {
                return;
            }

            $this->userNotifications->notify($user, array_merge($payload, [
                'scope' => UserNotification::SCOPE_TOURIST,
                'url'   => route('booking.receipt', ['booking' => $booking->id]),
            ]));
        });
    }

    // ─────────────────────────────────────────────────────
    //  Cache flushes
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
    //  Mail
    // ─────────────────────────────────────────────────────

    private function sendCreatedMail(Booking $booking): void
    {
        DB::afterCommit(function () use ($booking): void {
            $booking->loadMissing(['user', 'tenant', 'items.property']);

            $touristEmail = $booking->user?->email;
            if ($touristEmail) {
                $this->safeMail(
                    fn () => Mail::to($touristEmail)->queue(new BookingReceived($booking)),
                    'booking-received',
                    $booking->id,
                );
            }

            $tenant = $booking->tenant;
            if (! $tenant) {
                return;
            }

            $admins = $tenant->users()
                ->select('id', 'tenant_id', 'name', 'email')
                ->get()
                ->filter(fn ($u) => $u->hasRole('admin'));

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

    private function sendRefundStatusMail(Booking $booking): void
    {
        DB::afterCommit(function () use ($booking): void {
            $to = $booking->user?->email;
            if (! $to) {
                return;
            }

            $mailable = match ($booking->refund_status) {
                Booking::REFUND_STATUS_PROCESSED => new BookingRefundProcessed($booking),
                default                          => null,
            };

            if (! $mailable) {
                return;
            }

            $this->safeMail(
                fn () => Mail::to($to)->queue($mailable),
                'refund-status-' . $booking->refund_status,
                $booking->id,
            );
        });
    }

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