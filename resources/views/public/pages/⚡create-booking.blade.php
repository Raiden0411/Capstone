<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\Property;
use App\Models\Service;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\BookingItem;
use App\Models\BookingService;
use App\Models\Payment;
use App\Services\PayMongoService;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

new 
#[Layout('layouts.app')]
#[Title('Complete Your Booking')]
class extends Component {
    public Property $property;

    // Guest info
    #[Validate('required|string|max:255')]
    public $customerName = '';
    #[Validate('nullable|string|max:20')]
    public $customerPhone = '';
    #[Validate('nullable|email|max:255')]
    public $customerEmail = '';
    #[Validate('nullable|string')]
    public $customerAddress = '';

    // Dates
    #[Validate('required|date|after_or_equal:today')]
    public $check_in;
    #[Validate('required|date|after:check_in')]
    public $check_out;

    // Selected extra services
    public $selectedServices = [];
    public $totalAmount = 0;
    public $totalNights = 1;

    // Payment
    #[Validate('required|in:cash,card,gcash,paymaya')]
    public $payment_method = 'cash';
    #[Validate('nullable|string|max:255')]
    public $reference_number = '';

    public function mount($publicproperty)
    {
        $this->property = Property::with('tenant')
            ->withoutGlobalScope(TenantScope::class)
            ->findOrFail($publicproperty);

        if (!$this->property->tenant || !$this->property->tenant_id) {
            abort(404, 'This property is not associated with any business.');
        }

        if (Auth::check()) {
            $this->customerName  = Auth::user()->name;
            $this->customerEmail = Auth::user()->email;
        }

        $this->check_in = now()->format('Y-m-d');
        $this->check_out = now()->addDay()->format('Y-m-d');
        $this->calculateTotal();
    }

    public function updatedCheckIn() { $this->calculateTotal(); }
    public function updatedCheckOut() { $this->calculateTotal(); }

    public function addService($serviceId)
    {
        if (!isset($this->selectedServices[$serviceId])) {
            $this->selectedServices[$serviceId] = 1;
        } else {
            $this->selectedServices[$serviceId]++;
        }
        $this->calculateTotal();
    }

    public function removeService($serviceId)
    {
        unset($this->selectedServices[$serviceId]);
        $this->calculateTotal();
    }

    public function calculateTotal()
    {
        $checkIn = Carbon::parse($this->check_in);
        $checkOut = Carbon::parse($this->check_out);
        $this->totalNights = max(1, $checkIn->diffInDays($checkOut));
        $this->totalAmount = $this->property->price * $this->totalNights;

        foreach ($this->selectedServices as $serviceId => $qty) {
            $service = Service::find($serviceId);
            if ($service) {
                $this->totalAmount += $service->price * $qty;
            }
        }
    }

    public function getAvailableServicesProperty()
    {
        return Service::where('tenant_id', $this->property->tenant_id)
            ->withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function submit()
    {
        $this->validate();

        // Use the tenant_id from the already loaded property (no global scope interference)
        $tenantId = $this->property->tenant_id;

        if (!$tenantId) {
            session()->flash('error', 'This property is not linked to a valid business. Booking cannot be completed.');
            return;
        }

        $booking = DB::transaction(function () use ($tenantId) {
            $customer = Customer::firstOrCreate(
                ['email' => $this->customerEmail, 'tenant_id' => $tenantId],
                [
                    'name'    => $this->customerName,
                    'phone'   => $this->customerPhone,
                    'address' => $this->customerAddress,
                ]
            );

            $booking = Booking::create([
                'tenant_id'          => $tenantId,
                'customer_id'        => $customer->id,
                'booking_reference'  => 'BK-' . strtoupper(Str::random(8)),
                'check_in'           => $this->check_in,
                'check_out'          => $this->check_out,
                'total_amount'       => $this->totalAmount,
                'status'             => 'pending',
            ]);

            BookingItem::create([
                'tenant_id'   => $tenantId,
                'booking_id'  => $booking->id,
                'property_id' => $this->property->id,
                'price'       => $this->property->price,
                'quantity'    => 1,
                'subtotal'    => $this->property->price * $this->totalNights,
            ]);

            foreach ($this->selectedServices as $serviceId => $qty) {
                $service = Service::find($serviceId);
                if ($service) {
                    BookingService::create([
                        'tenant_id'  => $tenantId,
                        'booking_id' => $booking->id,
                        'service_id' => $serviceId,
                        'quantity'   => $qty,
                        'subtotal'   => $service->price * $qty,
                    ]);
                }
            }

            if ($this->payment_method === 'cash') {
                Payment::create([
                    'tenant_id'        => $tenantId,
                    'booking_id'       => $booking->id,
                    'amount'           => $this->totalAmount,
                    'payment_method'   => 'cash',
                    'payment_status'   => 'paid',
                    'reference_number' => $this->reference_number,
                    'paid_at'          => now(),
                ]);
            } else {
                $payMongo = app(PayMongoService::class);
                $session = $payMongo->createCheckoutSession([
                    'customer_name'  => $customer->name,
                    'customer_email' => $customer->email ?? 'guest@example.com',
                    'customer_phone' => $customer->phone,
                    'amount'         => $this->totalAmount,
                    'description'    => "Booking #{$booking->booking_reference}",
                    'item_name'      => 'Accommodation + services',
                    'success_url'    => route('tenant.payments.success', ['booking' => $booking->id]),
                    'cancel_url'     => route('tenant.payments.cancel', ['booking' => $booking->id]),
                    'metadata'       => [
                        'booking_id' => $booking->id,
                        'tenant_id'  => $tenantId,
                    ],
                    'payment_method_types' => [$this->payment_method],
                ]);

                if ($session) {
                    Payment::create([
                        'tenant_id'           => $tenantId,
                        'booking_id'          => $booking->id,
                        'amount'              => $this->totalAmount,
                        'payment_method'      => $this->payment_method,
                        'payment_status'      => 'unpaid',
                        'paymongo_session_id' => $session['data']['id'],
                    ]);
                    return redirect()->away($session['data']['attributes']['checkout_url']);
                }

                Payment::create([
                    'tenant_id'       => $tenantId,
                    'booking_id'      => $booking->id,
                    'amount'          => $this->totalAmount,
                    'payment_method'  => $this->payment_method,
                    'payment_status'  => 'unpaid',
                ]);
            }

            return $booking;
        });

        session()->flash('message', 'Booking confirmed! Thank you for your reservation.');
        return redirect()->route('home');
    }
};
?>

<div class="min-h-screen bg-white dark:bg-black py-12 px-4 transition-colors duration-300">
    <div class="max-w-3xl mx-auto">
        {{-- Page header with back link --}}
        <div class="mb-8">
            <a href="{{ route('tenant.show', $property->tenant->slug) }}" 
               wire:navigate 
               class="inline-flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-red-500 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to {{ $property->tenant->name }}
            </a>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white mt-2">Complete Your Booking</h1>
        </div>

        {{-- Display general errors --}}
        @if(session()->has('error'))
            <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-red-700 dark:text-red-400 text-sm">
                {{ session('error') }}
            </div>
        @endif

        {{-- Property summary card --}}
        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $property->name }}</h2>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-sm text-gray-500 dark:text-gray-400">
                <span>{{ $property->propertyType->name ?? 'Property' }}</span>
                <span class="w-1 h-1 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                <span>{{ $property->tenant->name }}</span>
            </div>
            <div class="mt-3 inline-flex items-baseline gap-1">
                <span class="text-2xl font-extrabold text-gray-900 dark:text-white">₱{{ number_format($property->price, 2) }}</span>
                <span class="text-sm text-gray-500 dark:text-gray-400">/ night</span>
            </div>
        </div>

        {{-- Booking form --}}
        <form wire:submit.prevent="submit" class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
            {{-- Guest info --}}
            <div class="border-b border-gray-100 dark:border-gray-800 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-600 dark:text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Your Details
                    </h3>
                    @auth
                        <button type="button" 
                                onclick="document.getElementById('guest-fields').classList.toggle('hidden')"
                                class="text-xs text-blue-600 dark:text-red-500 hover:underline">
                            Change
                        </button>
                    @endauth
                </div>

                @auth
                    <div class="text-sm text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
                        Booking as <strong>{{ Auth::user()->name }}</strong> ({{ Auth::user()->email }})
                    </div>
                    <div id="guest-fields" class="hidden mt-4 space-y-4">
                @else
                    <div id="guest-fields" class="space-y-4">
                @endauth
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name *</label>
                            <input type="text" wire:model="customerName" 
                                   class="w-full rounded-xl border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 dark:focus:ring-red-500">
                            @error('customerName') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                            <input type="email" wire:model="customerEmail" 
                                   class="w-full rounded-xl border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 dark:focus:ring-red-500">
                            @error('customerEmail') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                            <input type="text" wire:model="customerPhone" 
                                   class="w-full rounded-xl border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 dark:focus:ring-red-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
                            <input type="text" wire:model="customerAddress" 
                                   class="w-full rounded-xl border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 dark:focus:ring-red-500">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Dates selection --}}
            <div class="border-b border-gray-100 dark:border-gray-800 p-6">
                <h3 class="font-semibold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600 dark:text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Stay Dates
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Check-in *</label>
                        <input type="date" wire:model.live="check_in" 
                               class="w-full rounded-xl border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 dark:focus:ring-red-500">
                        @error('check_in') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Check-out *</label>
                        <input type="date" wire:model.live="check_out" 
                               class="w-full rounded-xl border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 dark:focus:ring-red-500">
                        @error('check_out') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Extra services --}}
            @if($this->availableServices->isNotEmpty())
                <div class="border-b border-gray-100 dark:border-gray-800 p-6">
                    <h3 class="font-semibold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-600 dark:text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        Add Extra Services
                    </h3>
                    <div class="flex flex-wrap gap-2 mb-5">
                        @foreach($this->availableServices as $service)
                            <button type="button" wire:click="addService({{ $service->id }})"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-full text-sm text-gray-800 dark:text-gray-200 transition-colors">
                                {{ $service->name }}
                                <span class="text-xs font-semibold text-blue-600 dark:text-red-500">+₱{{ number_format($service->price) }}</span>
                            </button>
                        @endforeach
                    </div>

                    @if(count($selectedServices))
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-4">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                        <th class="text-left py-2">Service</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-right">Subtotal</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($selectedServices as $serviceId => $qty)
                                        @php $svc = App\Models\Service::find($serviceId); @endphp
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <td class="py-2 text-gray-800 dark:text-white">{{ $svc->name }}</td>
                                            <td class="text-center text-gray-700 dark:text-gray-300">{{ $qty }}</td>
                                            <td class="text-right text-gray-800 dark:text-white">₱{{ number_format($svc->price * $qty, 2) }}</td>
                                            <td class="text-center">
                                                <button type="button" wire:click="removeService({{ $serviceId }})" class="text-red-500 hover:text-red-700 text-xl leading-4">&times;</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Payment method --}}
            <div class="border-b border-gray-100 dark:border-gray-800 p-6">
                <h3 class="font-semibold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600 dark:text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    Payment Method
                </h3>
                <select wire:model.live="payment_method" 
                        class="w-full md:w-2/3 rounded-xl border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 dark:focus:ring-red-500 py-2.5 px-4">
                    <option value="cash">Cash (on arrival)</option>
                    <option value="gcash">GCash</option>
                    <option value="paymaya">PayMaya</option>
                    <option value="card">Credit/Debit Card</option>
                </select>

                @if($payment_method === 'cash')
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reference Number (Optional)</label>
                        <input type="text" wire:model="reference_number" 
                               class="w-full md:w-2/3 rounded-xl border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 dark:focus:ring-red-500"
                               placeholder="e.g., Receipt #">
                    </div>
                @endif
            </div>

            {{-- Total and submit button --}}
            <div class="p-6 bg-gray-50 dark:bg-gray-800/30">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $totalNights }} night{{ $totalNights > 1 ? 's' : '' }}</span>
                        <div class="text-3xl font-extrabold text-gray-900 dark:text-white">₱{{ number_format($totalAmount, 2) }}</div>
                    </div>
                    <button type="submit" 
                            wire:loading.attr="disabled"
                            class="w-full sm:w-auto bg-blue-600 dark:bg-red-600 hover:bg-blue-700 dark:hover:bg-red-700 text-white font-semibold py-3 px-8 rounded-full shadow-md transition-colors duration-200 flex items-center justify-center gap-2 disabled:opacity-50">
                        <span wire:loading.remove>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            {{ $payment_method === 'cash' ? 'Confirm Booking' : 'Proceed to Pay' }}
                        </span>
                        <span wire:loading>
                            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processing...
                        </span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>