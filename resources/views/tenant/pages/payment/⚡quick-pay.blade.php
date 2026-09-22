{{-- resources/views/tenant/pages/payment/⚡quick-pay.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Models\Payment;
use App\Scopes\TenantScope;
use App\Traits\ChecksTenantPermissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

new class extends Component {
    use ChecksTenantPermissions;

    /** Bound from parent. Auto-locked (Eloquent model). */
    public Booking $booking;

    public function mount($booking): void
    {
        if (! $booking instanceof Booking) {
            $booking = Booking::withoutGlobalScope(TenantScope::class)
                ->findOrFail((int) $booking);
        }

        abort_unless($booking->tenant_id === Auth::user()->tenant_id, 403);

        $this->booking = $booking;
    }

    /**
     * Guard every subsequent Livewire request. Re-checks tenant ownership
     * on each call — the component is embedded inside show-booking, so a
     * stale session or a tampered request must not be able to invoke
     * confirmAndPay() against another tenant's booking.
     *
     * NOTE: does NOT require `manage payments` here — otherwise the whole
     * show-booking page would fail to render for view-only employees.
     * The permission gate lives on the action itself.
     */
    public function hydrate(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->tenant_id, 403);
        abort_unless($this->booking->tenant_id === $user->tenant_id, 403);
    }

    /**
     * Live remaining balance — recomputed on every request, so a second
     * browser tab recording a payment here immediately hides the button
     * on the next render.
     */
    #[Computed]
    public function remainingBalance(): float
    {
        $paid = (float) $this->booking->payments()
            ->withoutGlobalScope(TenantScope::class)
            ->where('payment_status', 'paid')
            ->sum('amount');

        return max(0, (float) $this->booking->total_amount - $paid);
    }

    /**
     * Whether the Quick Pay button should render at all.
     *
     * Two gates:
     *   1. Payment is possible (balance > 0, status not cancelled/completed)
     *   2. Caller holds the `manage payments` permission (or is an admin)
     *
     * The permission gate keeps the UI honest — the same check inside
     * confirmAndPay() protects the server regardless.
     */
    #[Computed]
    public function canPay(): bool
    {
        if (! $this->tenantCan('manage payments')) {
            return false;
        }

        if ($this->remainingBalance <= 0) {
            return false;
        }

        return ! in_array($this->booking->status, [
            Booking::STATUS_CANCELLED,
            Booking::STATUS_COMPLETED,
        ], true);
    }

    public function confirmAndPay()
    {
        /*
         * Livewire actions bypass route middleware. The route
         * `/admin/bookings/{booking}` is gated by `permission:view bookings`,
         * but that only fires on the initial page load — not on subsequent
         * requests to the Livewire update endpoint. Enforce the payment
         * permission here explicitly.
         *
         * This is the same class of bug we fixed in view-booking's delete()
         * and cancelBooking(): the policy only checks tenant ownership, so
         * without this guard, an employee with only `view bookings` could
         * record a cash payment via a tampered request.
         */
        $this->requirePermission('manage payments');

        // Belt-and-suspenders — the policy still checks tenant ownership.
        $this->authorize('update', $this->booking);

        $flashMessage = null;

        try {
            DB::transaction(function () use (&$flashMessage): void {
                // Lock the booking row scoped to the caller's tenant.
                $booking = Booking::withoutGlobalScope(TenantScope::class)
                    ->where('tenant_id', Auth::user()->tenant_id)
                    ->whereKey($this->booking->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Server-side status guard — the client hides the button,
                // but a tampered request could still call this method.
                if (in_array($booking->status, [
                    Booking::STATUS_CANCELLED,
                    Booking::STATUS_COMPLETED,
                ], true)) {
                    throw new \DomainException('This booking can no longer accept payments.');
                }

                // Recompute balance inside the lock — a concurrent payment
                // in another tab or the webhook may have settled it already.
                $paid = (float) $booking->payments()
                    ->withoutGlobalScope(TenantScope::class)
                    ->where('payment_status', 'paid')
                    ->sum('amount');

                $balance = max(0, (float) $booking->total_amount - $paid);

                if ($balance <= 0) {
                    $flashMessage = 'Booking is already fully paid.';
                    return;
                }

                Payment::create([
                    'tenant_id'      => Auth::user()->tenant_id,
                    'booking_id'     => $booking->id,
                    'amount'         => $balance,
                    'payment_method' => 'cash',
                    'payment_status' => 'paid',
                    'payment_type'   => $booking->booking_type ?? Payment::TYPE_FULL,
                    'paid_at'        => now(),
                ]);

                if (in_array($booking->status, [
                    Booking::STATUS_PENDING,
                    Booking::STATUS_RESERVED,
                ], true)) {
                    $booking->update(['status' => Booking::STATUS_CONFIRMED]);
                }

                $flashMessage = 'Booking confirmed and payment recorded.';
            });
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
            return null;
        } catch (\Throwable $e) {
            Log::error('Quick pay failed', [
                'tenant_id'  => Auth::user()->tenant_id,
                'booking_id' => $this->booking->id,
                'error'      => $e->getMessage(),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
            ]);
            session()->flash('error', 'An error occurred while processing the payment. Please try again.');
            return null;
        }

        // Flash AFTER the transaction commits — session state isn't
        // rolled back with the DB, so setting it inside would leave a
        // misleading success message on a failed transaction.
        if ($flashMessage !== null) {
            session()->flash('message', $flashMessage);
        }

        return $this->redirectRoute(
            'tenant.bookings.show',
            ['booking' => $this->booking->id],
            navigate: true,
        );
    }
};
?>

<div>
    @if($this->canPay)
        {{-- Rule 19: Alpine confirm() instead of wire:confirm — reliable in
             v4 SFCs and during wire:navigate. Matches the delete/cancel
             pattern in show-booking. --}}
        <button type="button"
                x-on:click="if (confirm('Receive cash payment of ₱{{ number_format($this->remainingBalance, 2) }} and confirm booking?')) $wire.confirmAndPay()"
                wire:loading.attr="disabled"
                wire:target="confirmAndPay"
                class="w-full inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                       transition-all duration-200 active:scale-95
                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                       disabled:opacity-60 disabled:cursor-not-allowed">
            <span wire:loading.remove wire:target="confirmAndPay" class="inline-flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Confirm &amp; Pay (Cash)
            </span>
            <span wire:loading wire:target="confirmAndPay" class="inline-flex items-center gap-2">
                <svg class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Processing…
            </span>
        </button>
    @endif
</div>