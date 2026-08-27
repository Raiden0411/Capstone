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
                class="inline-flex items-center gap-2 bg-[#376df1] hover:bg-blue-700 text-white font-semibold py-2.5 px-5 rounded-full shadow-lg shadow-blue-500/20 transition hover:scale-105">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Confirm & Pay (Cash)
        </button>
    @endif
</div>