{{-- resources/views/tenant/pages/payment/⚡create-payment.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PayMongoService;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;

new 
#[Layout('tenant.layouts.app')]
#[Title('Record Payment')]
class extends Component {
    
    public Booking $booking;
    
    #[Validate('required|numeric|min:0.01|max:999999.99')]
    public $amount = 0;
    
    #[Validate('required|in:cash,gcash,paymaya,card')]
    public $payment_method = 'cash';
    
    #[Validate('nullable|string|max:255')]
    public $reference_number = '';

    public ?string $payment_type = 'full';   // full | reservation

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

        $this->amount = max(0, $booking->total_amount - $paid);
        $this->payment_type = $booking->booking_type ?? 'full';

        if (in_array($booking->status, ['cancelled', 'completed'])) {
            session()->flash('error', 'Cannot record payment on a ' . $booking->status . ' booking.');
            $this->redirectRoute('tenant.bookings.show', $booking->id, navigate: true);
        }
    }

    public function updated($field)
    {
        if ($field === 'reference_number') {
            $this->reference_number = trim($this->reference_number);
        }
        if ($field === 'payment_type') {
            if ($this->payment_type === 'reservation') {
                $this->amount = round($this->booking->total_amount * 0.20, 2);
            } else {
                $paid = $this->booking->payments()
                    ->withoutGlobalScope(TenantScope::class)
                    ->where('payment_status', 'paid')
                    ->sum('amount');
                $this->amount = max(0, $this->booking->total_amount - $paid);
            }
        }
    }

    public function processCashPayment()
    {
        $this->validate();

        Payment::create([
            'tenant_id'        => Auth::user()->tenant_id,
            'booking_id'       => $this->booking->id,
            'amount'           => $this->amount,
            'payment_method'   => $this->payment_method,
            'payment_status'   => 'paid',
            'payment_type'     => $this->payment_type,
            'reference_number' => $this->reference_number,
            'paid_at'          => now(),
        ]);

        $this->maybeUpdateBookingStatus();

        session()->flash('message', 'Payment recorded successfully.');
        $this->dispatch('payment-recorded');
        return $this->redirectRoute('tenant.bookings.show', $this->booking->id, navigate: true);
    }

    public function processOnlinePayment(PayMongoService $payMongo)
    {
        $this->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:gcash,paymaya,card',
        ]);

        $user = $this->booking->user;

        $session = $payMongo->createCheckoutSession([
            'customer_name'   => $user->name,
            'customer_email'  => $user->email ?? 'guest@example.com',
            'customer_phone'  => $user->phone,
            'amount'          => $this->amount,
            'description'     => "Booking #{$this->booking->booking_reference}",
            'item_name'       => $this->payment_type === 'reservation' ? 'Reservation Fee' : 'Activity Payment',
            'success_url'     => route('tenant.payments.success', ['booking' => $this->booking->id]),
            'cancel_url'      => route('tenant.payments.cancel', ['booking' => $this->booking->id]),
            'metadata'        => [
                'booking_id' => $this->booking->id,
                'tenant_id'  => Auth::user()->tenant_id,
            ],
            'payment_method_types' => ['gcash', 'paymaya', 'card'],
        ]);

        if (!$session) {
            session()->flash('error', 'Unable to initiate payment. Please try again.');
            return;
        }

        Payment::create([
            'tenant_id'           => Auth::user()->tenant_id,
            'booking_id'          => $this->booking->id,
            'amount'              => $this->amount,
            'payment_method'      => $this->payment_method,
            'payment_status'      => 'unpaid',
            'payment_type'        => $this->payment_type,
            'paymongo_session_id' => $session['id'],
        ]);

        return redirect()->away($session['checkout_url']);
    }

    protected function maybeUpdateBookingStatus(): void
    {
        $totalPaid = $this->booking->payments()
            ->withoutGlobalScope(TenantScope::class)
            ->where('payment_status', 'paid')
            ->sum('amount');

        if ($this->booking->status === 'pending' && $totalPaid >= $this->booking->total_amount) {
            $this->booking->update(['status' => 'confirmed']);
        }

        if ($this->booking->status === 'reserved' && $totalPaid >= $this->booking->total_amount) {
            $this->booking->update(['status' => 'confirmed']);
        }
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-2xl mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Record Payment</h1>
        </div>
        <a href="{{ route('tenant.bookings.show', $booking->id) }}" wire:navigate
           class="btn-secondary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Booking
        </a>
    </div>

    @if (session()->has('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md text-sm text-red-700 dark:text-red-300 font-medium flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif

    <div class="card p-5 sm:p-6">

        {{-- Booking Summary --}}
        <div class="mb-5 p-4 rounded-xl bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700">
            <p class="text-sm text-gray-600 dark:text-gray-300">Guest: <span class="font-medium text-gray-900 dark:text-white">{{ $booking->user->name }}</span></p>
            <p class="text-sm text-gray-600 dark:text-gray-300">Total Amount: <span class="font-medium text-gray-900 dark:text-white">₱{{ number_format($booking->total_amount, 2) }}</span></p>
            <p class="text-sm text-gray-600 dark:text-gray-300">Remaining Balance: <span class="font-bold text-red-600 dark:text-red-400">₱{{ number_format($amount, 2) }}</span></p>
            @if($amount >= $booking->total_amount)
                <p class="mt-2 text-xs text-primary-600 dark:text-primary-400 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Full payment will confirm the booking automatically.
                </p>
            @endif
        </div>

        <form wire:submit="{{ in_array($payment_method, ['cash']) ? 'processCashPayment' : 'processOnlinePayment' }}" class="space-y-5"
              x-data="{ saved: false }" @payment-recorded.window="saved = true; setTimeout(() => saved = false, 2200)">

            {{-- Payment Type --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Type *</label>
                <select wire:model.live="payment_type" class="select">
                    <option value="full">Full Payment</option>
                    <option value="reservation">Reservation Fee (20%)</option>
                </select>
            </div>

            {{-- Amount --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount to Pay *</label>
                <input type="number" step="0.01" wire:model="amount" class="input">
                @error('amount') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Payment Method --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Payment Method *</label>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    @foreach([
                        ['cash', 'Cash', '<svg class="w-8 h-8 mx-auto text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'],
                        ['gcash', 'GCash', '<svg class="w-8 h-8 mx-auto text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>'],
                        ['paymaya', 'Maya', '<svg class="w-8 h-8 mx-auto text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>'],
                        ['card', 'Card', '<svg class="w-8 h-8 mx-auto text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>'],
                    ] as [$val, $label, $icon])
                        <label class="cursor-pointer">
                            <input type="radio" wire:model.live="payment_method" value="{{ $val }}" class="sr-only peer">
                            <div class="border-2 border-gray-200 dark:border-gray-700 rounded-xl p-3 text-center transition-all duration-200 peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-500/10 hover:border-gray-300 dark:hover:border-gray-600 active:scale-[0.98]">
                                {!! $icon !!}
                                <p class="text-gray-900 dark:text-white font-semibold text-xs mt-1">{{ $label }}</p>
                            </div>
                        </label>
                    @endforeach
                </div>
                @error('payment_method') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            @if($payment_method === 'cash')
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reference Number (Optional)</label>
                    <input type="text" wire:model="reference_number" class="input">
                </div>
            @endif

            {{-- Actions --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="btn-primary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    <span wire:loading.remove>
                        {{ $payment_method === 'cash' ? 'Record Payment' : 'Proceed to Pay' }}
                    </span>
                    <span wire:loading class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Processing…
                    </span>
                </button>
                <a href="{{ route('tenant.bookings.show', $booking->id) }}" wire:navigate
                   class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    Cancel
                </a>
                <span x-show="saved" x-transition class="sm:ml-3 inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-primary-50 dark:bg-primary-500/15 text-primary-600 dark:text-primary-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Done!
                </span>
            </div>
        </form>
    </div>
</div>