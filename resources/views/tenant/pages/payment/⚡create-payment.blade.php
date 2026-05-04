<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PayMongoService;
use Illuminate\Support\Facades\Auth;

new 
#[Layout('tenant.layouts.app')]
#[Title('Record Payment')]
class extends Component {
    
    public Booking $booking;
    
    #[Validate('required|numeric|min:0.01|max:999999.99')]
    public $amount = 0;
    
    #[Validate('required|in:cash,card,gcash,paymaya,bank_transfer')]
    public $payment_method = 'cash';
    
    #[Validate('nullable|string|max:255')]
    public $reference_number = '';

    public function mount(Booking $booking)
    {
        if ($booking->tenant_id !== Auth::user()->tenant_id) {
            abort(403);
        }
        
        $this->booking = $booking;
        $paid = $booking->payments()->where('payment_status', 'paid')->sum('amount');
        $this->amount = max(0, $booking->total_amount - $paid);

        // Prevent recording payment on cancelled or completed bookings
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
            'reference_number' => $this->reference_number,
            'paid_at'          => now(),
        ]);

        $this->maybeConfirmBooking();

        session()->flash('message', 'Payment recorded successfully.');
        $this->dispatch('payment-recorded');
        return $this->redirectRoute('tenant.bookings.show', $this->booking->id, navigate: true);
    }

    public function processOnlinePayment(PayMongoService $payMongo)
    {
        $this->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:card,gcash,paymaya',
        ]);

        $customer = $this->booking->customer;
        
        $session = $payMongo->createCheckoutSession([
            'customer_name'   => $customer->name,
            'customer_email'  => $customer->email ?? 'guest@example.com',
            'customer_phone'  => $customer->phone,
            'amount'          => $this->amount,
            'description'     => "Booking #{$this->booking->booking_reference}",
            'item_name'       => 'Accommodation Payment',
            'success_url'     => route('tenant.payments.success', ['booking' => $this->booking->id]),
            'cancel_url'      => route('tenant.payments.cancel', ['booking' => $this->booking->id]),
            'metadata'        => [
                'booking_id' => $this->booking->id,
                'tenant_id'  => Auth::user()->tenant_id,
            ],
            'payment_method_types' => [$this->payment_method],
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
            'paymongo_session_id' => $session['data']['id'],
        ]);

        return redirect()->away($session['data']['attributes']['checkout_url']);
    }

    /** Auto‑confirm the booking when fully paid. */
    protected function maybeConfirmBooking(): void
    {
        $totalPaid = $this->booking->payments()->where('payment_status', 'paid')->sum('amount');
        if ($totalPaid >= $this->booking->total_amount && $this->booking->status === 'pending') {
            $this->booking->update(['status' => 'confirmed']);
        }
    }
};
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-2xl mx-auto text-gray-900 dark:text-white space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold">Record Payment</h1>
            <p class="text-gray-500 dark:text-slate-400 mt-1">Booking #{{ $booking->booking_reference }}</p>
        </div>
        <a href="{{ route('tenant.bookings.show', $booking->id) }}" wire:navigate class="text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-white font-medium transition-colors">
            &larr; Back to Booking
        </a>
    </div>

    @if (session()->has('error'))
        <div class="p-4 bg-red-50 dark:bg-red-500/10 border-l-4 border-red-500 rounded-md shadow-sm">
            <p class="text-sm text-red-700 dark:text-red-400 font-medium">{{ session('error') }}</p>
        </div>
    @endif

    <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
        {{-- Booking Summary --}}
        <div class="mb-5 p-4 rounded-lg bg-gray-50 dark:bg-slate-800/50 border border-gray-200 dark:border-slate-700/50">
            <p class="text-sm">Customer: <span class="font-medium">{{ $booking->customer->name }}</span></p>
            <p class="text-sm">Total Amount: <span class="font-medium">₱{{ number_format($booking->total_amount, 2) }}</span></p>
            <p class="text-sm">Remaining Balance: <span class="font-bold text-red-600 dark:text-red-400">₱{{ number_format($amount, 2) }}</span></p>
            @if($amount >= $booking->total_amount)
                <p class="mt-2 text-xs text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Full payment will confirm the booking automatically.
                </p>
            @endif
        </div>

        <form wire:submit="{{ in_array($payment_method, ['cash', 'bank_transfer']) ? 'processCashPayment' : 'processOnlinePayment' }}" class="space-y-5"
              x-data="{ saved: false }" @payment-recorded.window="saved = true; setTimeout(() => saved = false, 2200)">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Amount to Pay *</label>
                <input type="number" step="0.01" wire:model="amount" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500">
                @error('amount') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Payment Method *</label>
                <select wire:model.live="payment_method" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                    <option value="cash">Cash</option>
                    <option value="card">Credit/Debit Card (PayMongo)</option>
                    <option value="gcash">GCash (PayMongo)</option>
                    <option value="paymaya">PayMaya (PayMongo)</option>
                    <option value="bank_transfer">Bank Transfer</option>
                </select>
                @error('payment_method') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            @if(in_array($payment_method, ['cash', 'bank_transfer']))
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Reference Number (Optional)</label>
                <input type="text" wire:model="reference_number" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500">
            </div>
            @endif

            <div class="flex items-center gap-3 pt-4 border-t border-gray-200 dark:border-slate-700/50">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-xl shadow-sm transition-colors flex items-center gap-2 data-loading:opacity-75 data-loading:cursor-not-allowed">
                    <span class="in-data-loading:hidden">
                        {{ in_array($payment_method, ['cash', 'bank_transfer']) ? 'Record Payment' : 'Proceed to Pay' }}
                    </span>
                    <span class="not-in-data-loading:hidden flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Processing…
                    </span>
                </button>
                <a href="{{ route('tenant.bookings.show', $booking->id) }}" wire:navigate class="border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 font-medium py-2.5 px-6 rounded-xl transition-colors">
                    Cancel
                </a>
                {{-- Saved badge --}}
                <span x-show="saved" x-transition class="ml-3 inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 dark:bg-green-500/20 text-green-700 dark:text-green-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Done!
                </span>
            </div>
        </form>
    </div>
</div>