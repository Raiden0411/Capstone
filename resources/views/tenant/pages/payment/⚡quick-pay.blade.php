{{-- resources/views/tenant/pages/payment/⚡quick-pay.blade.php --}}
<?php

use Livewire\Component;
use App\Models\Booking;
use App\Models\Payment;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public Booking $booking;
    public $remainingBalance = 0;

    public function mount($booking)
    {
        if (!$booking instanceof Booking) {
            $booking = Booking::withoutGlobalScope(TenantScope::class)->findOrFail($booking);
        }

        if ($booking->tenant_id !== Auth::user()->tenant_id) {
            abort(403);
        }

        $this->booking = $booking;

        $paid = $booking->payments()
            ->withoutGlobalScope(TenantScope::class)
            ->where('payment_status', 'paid')
            ->sum('amount');

        $this->remainingBalance = max(0, $booking->total_amount - $paid);
    }

    public function confirmAndPay()
    {
        if ($this->remainingBalance <= 0) {
            session()->flash('message', 'No balance due.');
            return;
        }

        Payment::create([
            'tenant_id'        => Auth::user()->tenant_id,
            'booking_id'       => $this->booking->id,
            'amount'           => $this->remainingBalance,
            'payment_method'   => 'cash',
            'payment_status'   => 'paid',
            'payment_type'     => $this->booking->booking_type ?? 'full',
            'paid_at'          => now(),
        ]);

        if ($this->booking->status === 'pending') {
            $this->booking->update(['status' => 'confirmed']);
        } elseif ($this->booking->status === 'reserved') {
            $this->booking->update(['status' => 'confirmed']);
        }

        session()->flash('message', 'Booking confirmed and payment recorded.');

        return redirect()->route('tenant.bookings.show', $this->booking->id);
    }
};
?>

<div>
    @if($remainingBalance > 0 && !in_array($booking->status, ['cancelled', 'completed']))
        <button wire:click="confirmAndPay"
                wire:confirm="Receive cash payment of ₱{{ number_format($remainingBalance, 2) }} and confirm booking?"
                wire:loading.attr="disabled"
                class="btn-primary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
            <span wire:loading.remove>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Confirm & Pay (Cash)
            </span>
            <span wire:loading class="inline-flex items-center gap-2">
                <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Processing…
            </span>
        </button>
    @endif
</div>