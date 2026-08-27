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
        // Ensure booking instance and tenant scope
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

        // Default payment type from booking type
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
            // Automatically adjust amount if switching to reservation fee?
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

        // If reservation and balance now fully paid, optionally confirm
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
           class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-[#376df1] dark:hover:text-blue-400 transition-colors">
            &larr; Back to Booking
        </a>
    </div>

    {{-- Error --}}
    @if (session()->has('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md text-sm text-red-700 dark:text-red-300 font-medium">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm">

        {{-- Booking Summary --}}
        <div class="mb-5 p-4 rounded-xl bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700">
            <p class="text-sm text-gray-600 dark:text-gray-300">Guest: <span class="font-medium text-gray-900 dark:text-white">{{ $booking->user->name }}</span></p>
            <p class="text-sm text-gray-600 dark:text-gray-300">Total Amount: <span class="font-medium text-gray-900 dark:text-white">₱{{ number_format($booking->total_amount, 2) }}</span></p>
            <p class="text-sm text-gray-600 dark:text-gray-300">Remaining Balance: <span class="font-bold text-red-600 dark:text-red-400">₱{{ number_format($amount, 2) }}</span></p>
            @if($amount >= $booking->total_amount)
                <p class="mt-2 text-xs text-[#376df1] dark:text-blue-400 flex items-center gap-1">
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
                <select wire:model.live="payment_type"
                        class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition">
                    <option value="full">Full Payment</option>
                    <option value="reservation">Reservation Fee (20%)</option>
                </select>
            </div>

            {{-- Amount --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Amount to Pay *</label>
                <input type="number" step="0.01" wire:model="amount"
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition">
                @error('amount') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Payment Method --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Payment Method *</label>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    @foreach([
                        ['cash', 'Cash', '💵'],
                        ['gcash', 'GCash', '📱'],
                        ['paymaya', 'Maya', '💳'],
                        ['card', 'Card', '🏦'],
                    ] as [$val, $label, $icon])
                        <label class="cursor-pointer">
                            <input type="radio" wire:model.live="payment_method" value="{{ $val }}" class="sr-only peer">
                            <div class="border-2 border-gray-200 dark:border-gray-700 rounded-xl p-3 text-center transition peer-checked:border-[#376df1] peer-checked:bg-blue-50 dark:peer-checked:bg-blue-500/10 hover:border-gray-300 dark:hover:border-gray-600">
                                <span class="text-xl">{{ $icon }}</span>
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
                    <input type="text" wire:model="reference_number"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition">
                </div>
            @endif

            {{-- Actions --}}
            <div class="flex items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="bg-[#376df1] hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-full shadow-lg shadow-blue-500/20 transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove>
                        {{ $payment_method === 'cash' ? 'Record Payment' : 'Proceed to Pay' }}
                    </span>
                    <span wire:loading class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Processing…
                    </span>
                </button>
                <a href="{{ route('tenant.bookings.show', $booking->id) }}" wire:navigate
                   class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 px-6 py-3 rounded-full font-medium transition">
                    Cancel
                </a>
                <span x-show="saved" x-transition class="ml-3 inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 dark:bg-blue-500/15 text-[#376df1] dark:text-blue-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Done!
                </span>
            </div>
        </form>
    </div>
</div>