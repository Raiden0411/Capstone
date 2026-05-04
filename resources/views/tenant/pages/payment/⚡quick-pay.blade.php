<?php

use Livewire\Component;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public Booking $booking;
    public $remainingBalance = 0;

    public function mount(Booking $booking)
    {
        $this->booking = $booking;
        $paid = $booking->payments()->where('payment_status', 'paid')->sum('amount');
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
            'paid_at'          => now(),
        ]);

        if ($this->booking->status === 'pending') {
            $this->booking->update(['status' => 'confirmed']);
        }

        session()->flash('message', 'Booking confirmed and payment recorded.');

        return redirect()->route('tenant.bookings.show', $this->booking->id);
    }
};
?>

{{-- Always wrapped in a single root element for Livewire --}}
<div>
    @if($remainingBalance > 0 && !in_array($booking->status, ['cancelled', 'completed']))
        <button wire:click="confirmAndPay"
                wire:confirm="Receive cash payment of ₱{{ number_format($remainingBalance, 2) }} and confirm booking?"
                class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 px-5 rounded-xl shadow-sm transition-colors flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Confirm & Pay (Cash)
        </button>
    @endif
</div>