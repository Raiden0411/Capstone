{{-- resources/views/public/pages/⚡payment-processing.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Transaction;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;

new
#[Layout('layouts.app')]
#[Title('Processing Payment')]
class extends Component
{
    public $bookingId;
    public Booking $booking;

    public function mount($bookingId)
    {
        $this->bookingId = $bookingId;
        $this->booking = Booking::withoutGlobalScope(TenantScope::class)->findOrFail($bookingId);
        if (Auth::id() !== $this->booking->user_id) {
            abort(403);
        }
    }

    public function checkStatus()
    {
        $this->booking->refresh();

        // If booking is already confirmed/reserved, redirect immediately
        if (in_array($this->booking->status, [Booking::STATUS_CONFIRMED, Booking::STATUS_RESERVED])) {
            session()->flash('message', 'Payment successful! Your booking is updated.');
            return $this->redirectRoute('my-bookings');
        }

        // Find the latest unpaid payment
        $payment = $this->booking->payments()
            ->withoutGlobalScope(TenantScope::class)
            ->where('payment_status', 'unpaid')
            ->latest()
            ->first();

        if (!$payment || !$payment->paymongo_session_id) {
            session()->flash('error', 'Payment session not found.');
            return $this->redirectRoute('my-bookings');
        }

        // Mark payment as paid (success_url means payment succeeded)
        if ($payment->payment_status !== 'paid') {
            $payment->update([
                'payment_status'   => 'paid',
                'paid_at'          => now(),
                'reference_number' => $payment->paymongo_session_id,
            ]);

            Transaction::create([
                'tenant_id'   => $payment->tenant_id,
                'booking_id'  => $payment->booking_id,
                'type'        => 'income',
                'amount'      => $payment->amount,
                'description' => 'PayMongo payment: ' . $payment->paymongo_session_id,
            ]);
        }

        // Update booking status based on payment type and total paid
        $totalPaid = $this->booking->payments()
            ->withoutGlobalScope(TenantScope::class)
            ->where('payment_status', 'paid')
            ->sum('amount');

        if ($payment->payment_type === Payment::TYPE_RESERVATION) {
            $this->booking->update(['status' => Booking::STATUS_RESERVED]);
        } elseif ($totalPaid >= $this->booking->total_amount) {
            $this->booking->update(['status' => Booking::STATUS_CONFIRMED]);
        }

        session()->flash('message', 'Payment successful! Your booking is updated.');
        return $this->redirectRoute('my-bookings');
    }
};
?>

<div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-950 py-12 px-4 sm:px-6 lg:px-8"
     wire:poll.3000ms="checkStatus">

    <div class="w-full max-w-md">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-xl overflow-hidden">
            <div class="p-6 sm:p-8 text-center">
                {{-- Spinner Icon --}}
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-primary-50 dark:bg-primary-500/10 border border-primary-200 dark:border-primary-500/20">
                    <svg class="h-8 w-8 text-primary-600 dark:text-primary-400 animate-spin motion-reduce:animate-none" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>

                <h2 class="mt-6 text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">
                    Processing your payment…
                </h2>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                    Please wait while we confirm your payment with PayMongo. This usually takes a few seconds.
                </p>

                {{-- Booking Reference --}}
                <div class="mt-5 inline-flex items-center gap-2 px-4 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-full">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ref</span>
                    <span class="text-sm font-mono font-bold text-gray-900 dark:text-white">{{ $booking->booking_reference }}</span>
                </div>

                {{-- Check Status Button --}}
                <div class="mt-8">
                    <button type="button" wire:click="checkStatus"
                            wire:loading.attr="disabled"
                            class="btn-primary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <span wire:loading.remove>Check Status Now</span>
                        <span wire:loading class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Checking…
                        </span>
                    </button>
                </div>

                {{-- Skip link --}}
                <div class="mt-4">
                    <a href="{{ route('my-bookings') }}" wire:navigate
                       class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                        Skip and go to My Bookings
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>