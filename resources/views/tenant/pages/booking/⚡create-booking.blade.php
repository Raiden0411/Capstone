<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Property;
use App\Models\Service;
use App\Models\BookingItem;
use App\Models\BookingService;
use App\Models\Payment;
use App\Services\PayMongoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

new 
#[Layout('tenant.layouts.app')]
#[Title('Walk‑In Checkout')]
class extends Component {
    #[Validate('required|string|max:255')]
    public $customerName = '';
    
    #[Validate('required|string|max:20')]
    public $customerPhone = '';
    
    public $customerEmail = '';
    public $customerAddress = '';

    #[Validate('required|date|after_or_equal:today')]
    public $check_in;
    
    #[Validate('required|date|after:check_in')]
    public $check_out;
    
    public $booking_reference;
    public $totalAmount = 0;
    public $selectedProperties = [];
    public $selectedServices = [];
    
    #[Validate('required|in:cash,card,gcash,paymaya')]
    public $payment_method = 'cash';
    
    public $createdBookingId = null;

    public function mount()
    {
        $this->check_in = now()->format('Y-m-d');
        $this->check_out = now()->addDay()->format('Y-m-d');
        $this->generateBookingReference();
    }

    public function updated($field)
    {
        $trimFields = ['customerName', 'customerPhone', 'customerEmail', 'customerAddress'];
        if (in_array($field, $trimFields)) {
            $this->$field = trim($this->$field);
        }
    }

    public function generateBookingReference()
    {
        $this->booking_reference = 'BK-' . strtoupper(Str::random(8));
    }

    public function updatedCheckIn()
    {
        if ($this->check_in && $this->check_out && Carbon::parse($this->check_in)->gte(Carbon::parse($this->check_out))) {
            $this->check_out = Carbon::parse($this->check_in)->addDay()->format('Y-m-d');
        }
        $this->calculateTotal();
    }

    public function updatedCheckOut()
    {
        $this->calculateTotal();
    }

    // Toggle a property on/off (no quantity – selected once)
    public function toggleProperty($propertyId, $price)
    {
        if (isset($this->selectedProperties[$propertyId])) {
            unset($this->selectedProperties[$propertyId]);
        } else {
            $this->selectedProperties[$propertyId] = ['quantity' => 1, 'price' => $price];
        }
        $this->calculateTotal();
    }

    // Toggle a service on/off (no quantity – selected once)
    public function toggleService($serviceId, $price)
    {
        if (isset($this->selectedServices[$serviceId])) {
            unset($this->selectedServices[$serviceId]);
        } else {
            $this->selectedServices[$serviceId] = ['quantity' => 1, 'price' => $price];
        }
        $this->calculateTotal();
    }

    public function calculateTotal()
    {
        $total = 0;
        $nights = 1;
        if ($this->check_in && $this->check_out) {
            $nights = Carbon::parse($this->check_in)->diffInDays(Carbon::parse($this->check_out));
            $nights = max(1, $nights);
        }
        foreach ($this->selectedProperties as $item) {
            $total += $item['price'] * $item['quantity'] * $nights;
        }
        foreach ($this->selectedServices as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        $this->totalAmount = $total;
    }

    public function getAvailablePropertiesProperty()
    {
        if (!$this->check_in || !$this->check_out) {
            return collect();
        }

        $checkIn = $this->check_in;
        $checkOut = $this->check_out;

        $properties = Property::with('images')
            ->where('is_active', true)
            ->where('status', 'available')
            ->orderBy('name')
            ->get();

        $available = $properties->filter(function ($property) use ($checkIn, $checkOut) {
            $hasConflict = BookingItem::where('property_id', $property->id)
                ->whereHas('booking', function ($query) use ($checkIn, $checkOut) {
                    $query->whereNotIn('status', ['cancelled', 'completed'])
                        ->where('check_in', '<', $checkOut)
                        ->where('check_out', '>', $checkIn);
                })
                ->exists();
            return !$hasConflict;
        });

        return $available->values();
    }

    public function getAvailableServicesProperty()
    {
        return Service::where('is_active', true)->orderBy('name')->get();
    }

    public function submit()
    {
        if (empty($this->selectedProperties)) {
            session()->flash('error', 'Please select at least one property.');
            return;
        }

        $this->validate();

        if (!$this->booking_reference) {
            $this->generateBookingReference();
        }

        DB::transaction(function () {
            $customer = Customer::create([
                'tenant_id' => Auth::user()->tenant_id,
                'name' => $this->customerName,
                'phone' => $this->customerPhone,
                'email' => $this->customerEmail ?: null,
                'address' => $this->customerAddress ?: null,
            ]);

            $booking = Booking::create([
                'tenant_id' => Auth::user()->tenant_id,
                'customer_id' => $customer->id,
                'booking_reference' => $this->booking_reference,
                'check_in' => $this->check_in,
                'check_out' => $this->check_out,
                'total_amount' => $this->totalAmount,
                'status' => 'pending',
            ]);

            $nights = Carbon::parse($this->check_in)->diffInDays($this->check_out);
            $nights = max(1, $nights);

            foreach ($this->selectedProperties as $propertyId => $item) {
                BookingItem::create([
                    'tenant_id' => Auth::user()->tenant_id,
                    'booking_id' => $booking->id,
                    'property_id' => $propertyId,
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['price'] * $item['quantity'] * $nights,
                ]);
            }

            foreach ($this->selectedServices as $serviceId => $item) {
                BookingService::create([
                    'tenant_id' => Auth::user()->tenant_id,
                    'booking_id' => $booking->id,
                    'service_id' => $serviceId,
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['price'] * $item['quantity'],
                ]);
            }

            $this->createdBookingId = $booking->id;

            if ($this->payment_method === 'cash') {
                Payment::create([
                    'tenant_id' => Auth::user()->tenant_id,
                    'booking_id' => $booking->id,
                    'amount' => $this->totalAmount,
                    'payment_method' => 'cash',
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ]);

                $totalPaid = $booking->payments()->where('payment_status', 'paid')->sum('amount');
                if ($totalPaid >= $booking->total_amount && $booking->status === 'pending') {
                    $booking->update(['status' => 'confirmed']);
                }
            }
        });

        if ($this->payment_method === 'cash') {
            session()->flash('message', 'Booking created and confirmed with cash payment.');
            return $this->redirectRoute('tenant.bookings.show', ['booking' => $this->createdBookingId], navigate: true);
        } else {
            return $this->initiateOnlinePayment();
        }
    }

    protected function initiateOnlinePayment()
    {
        $booking = Booking::find($this->createdBookingId);
        $customer = $booking->customer;

        $payMongo = app(PayMongoService::class);
        $session = $payMongo->createCheckoutSession([
            'customer_name' => $customer->name,
            'customer_email' => $customer->email ?? 'guest@example.com',
            'customer_phone' => $customer->phone,
            'amount' => $this->totalAmount,
            'description' => "Booking #{$booking->booking_reference}",
            'item_name' => 'Accommodation Payment',
            'success_url' => route('tenant.payments.success', ['booking' => $booking->id]),
            'cancel_url' => route('tenant.payments.cancel', ['booking' => $booking->id]),
            'metadata' => [
                'booking_id' => $booking->id,
                'tenant_id' => Auth::user()->tenant_id,
            ],
            'payment_method_types' => [$this->payment_method],
        ]);

        if (!$session) {
            session()->flash('error', 'Unable to initiate payment. Please try again.');
            return $this->redirectRoute('tenant.bookings.show', ['booking' => $booking->id], navigate: true);
        }

        Payment::create([
            'tenant_id' => Auth::user()->tenant_id,
            'booking_id' => $booking->id,
            'amount' => $this->totalAmount,
            'payment_method' => $this->payment_method,
            'payment_status' => 'unpaid',
            'paymongo_session_id' => $session['data']['id'],
        ]);

        return redirect()->away($session['data']['attributes']['checkout_url']);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto text-gray-900 dark:text-white">
    <div class="mb-6">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Walk‑In Checkout</h1>
        <p class="text-gray-500 dark:text-slate-400 mt-1">Quickly register a guest and complete their stay.</p>
    </div>

    @if (session()->has('error'))
        <div class="mb-4 bg-red-50 dark:bg-red-500/10 border-l-4 border-red-500 p-4 rounded-md shadow-sm">
            <p class="text-sm text-red-700 dark:text-red-400 font-medium">{{ session('error') }}</p>
        </div>
    @endif

    <form wire:submit="submit" class="space-y-6">
        {{-- Customer Information --}}
        <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Guest Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Full Name *</label>
                    <input type="text" wire:model="customerName" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500" placeholder="Guest name">
                    @error('customerName') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Phone *</label>
                    <input type="text" wire:model="customerPhone" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500" placeholder="Contact number">
                    @error('customerPhone') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
            <p class="text-xs text-gray-400 dark:text-slate-500 mt-2">* Required for walk‑in.</p>
        </div>

        {{-- Dates & Reference --}}
        <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Stay Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Check‑in *</label>
                    <input type="date" wire:model.live="check_in" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                    @error('check_in') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Check‑out *</label>
                    <input type="date" wire:model.live="check_out" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                    @error('check_out') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Booking Ref</label>
                    <input type="text" wire:model="booking_reference" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300 cursor-not-allowed" readonly>
                    <button type="button" wire:click="generateBookingReference" class="text-xs text-blue-600 dark:text-blue-400 hover:underline mt-1">Generate New</button>
                </div>
            </div>
        </div>

        {{-- Property Card Grid --}}
        <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Select Room(s) — Tap to add</h2>

            @if(count($this->availableProperties) > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($this->availableProperties as $property)
                        @php
                            $isSelected = isset($selectedProperties[$property->id]);
                            $firstImg = $property->images->first();
                        @endphp
                        <div class="relative group cursor-pointer rounded-2xl border-2 transition-all duration-200
                                    {{ $isSelected ? 'border-emerald-500 dark:border-emerald-400 ring-2 ring-emerald-200 dark:ring-emerald-500/30' : 'border-gray-200 dark:border-slate-700/50 hover:border-blue-400 dark:hover:border-blue-400' }}"
                             wire:click="toggleProperty({{ $property->id }}, {{ $property->price }})">
                            <div class="aspect-4/4 overflow-hidden rounded-t-2xl">
                                <img class="size-full object-cover"
                                     src="{{ $firstImg ? Storage::url($firstImg->image_path) : asset('images/placeholder-room.jpg') }}"
                                     alt="{{ $property->name }}">
                            </div>
                            <div class="p-4">
                                <h3 class="font-medium text-gray-900 dark:text-white">{{ $property->name }}</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">
                                    ₱{{ number_format($property->price, 2) }} / night
                                </p>
                                <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">Capacity: {{ $property->capacity }} persons</p>
                            </div>
                            @if($isSelected)
                                <div class="absolute top-3 right-3 bg-emerald-600 text-white rounded-full p-1 shadow">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 dark:text-slate-400 text-center py-8">No available rooms for selected dates.</p>
            @endif

            {{-- Selected Rooms --}}
            @if(count($selectedProperties) > 0)
                <div class="mt-8 space-y-3">
                    <h3 class="font-medium text-gray-900 dark:text-white mb-4">Selected Rooms</h3>
                    @foreach($selectedProperties as $id => $item)
                        @php
                            $prop = $this->availableProperties->firstWhere('id', $id) ?? App\Models\Property::find($id);
                            $nights = Carbon::parse($check_in)->diffInDays($check_out);
                            $nights = max(1, $nights);
                            $roomTotal = $item['price'] * $item['quantity'] * $nights;
                        @endphp
                        <div class="flex items-center gap-4 p-3 bg-gray-50 dark:bg-slate-800/50 rounded-xl border border-gray-200 dark:border-slate-700/50">
                            <div class="w-12 h-12 rounded-lg overflow-hidden bg-gray-100 dark:bg-slate-700 shrink-0">
                                @if($prop && $prop->images->isNotEmpty())
                                    <img src="{{ Storage::url($prop->images->first()->image_path) }}" class="w-full h-full object-cover" alt="{{ $prop->name }}">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-gray-400 dark:text-slate-500">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    </div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 dark:text-white truncate">{{ $prop->name ?? 'Room' }}</p>
                                <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">
                                    ₱{{ number_format($item['price'], 2) }} / night · {{ $nights }} nights
                                </p>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white mt-1">₱{{ number_format($roomTotal, 2) }}</p>
                            </div>
                            <button type="button" wire:click="toggleProperty({{ $id }}, {{ $item['price'] }})" 
                                    class="p-1 text-gray-400 dark:text-slate-500 hover:text-red-500" title="Remove room">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Add‑On Services (toggle, no quantity) --}}
        <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Add‑On Services</h2>
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach($this->availableServices as $service)
                    @php $isServiceSelected = isset($selectedServices[$service->id]); @endphp
                    <button type="button" wire:click="toggleService({{ $service->id }}, {{ $service->price }})"
                            class="border rounded-full px-4 py-2 text-sm transition-colors
                                   {{ $isServiceSelected 
                                      ? 'bg-blue-100 dark:bg-blue-500/20 border-blue-300 dark:border-blue-500/50 text-blue-800 dark:text-blue-400'
                                      : 'border-gray-300 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-800 text-gray-700 dark:text-slate-300' }}">
                        {{ $service->name }} (₱{{ number_format($service->price, 2) }})
                    </button>
                @endforeach
            </div>

            @if(count($selectedServices) > 0)
                <div class="space-y-2">
                    @foreach($selectedServices as $id => $item)
                        @php $service = App\Models\Service::find($id); @endphp
                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-800/50 rounded-xl border border-gray-200 dark:border-slate-700/50">
                            <div class="flex-1">
                                <p class="font-medium text-gray-900 dark:text-white">{{ $service->name ?? 'Service' }}</p>
                                <p class="text-xs text-gray-500 dark:text-slate-400">₱{{ number_format($item['price'], 2) }}</p>
                            </div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white w-20 text-right">₱{{ number_format($item['price'], 2) }}</p>
                            <button type="button" wire:click="toggleService({{ $id }}, {{ $item['price'] }})" class="p-1 text-gray-400 dark:text-slate-500 hover:text-red-500" title="Remove service">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Payment Method --}}
        <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Payment</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Payment Method *</label>
                    <select wire:model.live="payment_method" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                        <option value="cash">Cash</option>
                        <option value="card">Credit/Debit Card</option>
                        <option value="gcash">GCash</option>
                        <option value="paymaya">PayMaya</option>
                    </select>
                    @error('payment_method') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- Total & Actions --}}
        <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <span class="text-xl font-bold text-gray-900 dark:text-white">Total: ₱{{ number_format($totalAmount, 2) }}</span>
            <div class="flex gap-3">
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 px-6 rounded-xl shadow-sm transition-colors flex items-center gap-2 data-loading:opacity-75 data-loading:cursor-not-allowed">
                    <span class="in-data-loading:hidden">
                        {{ $payment_method === 'cash' ? 'Complete Checkout' : 'Proceed to Pay' }}
                    </span>
                    <span class="not-in-data-loading:hidden">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Processing...
                    </span>
                </button>
                <a href="{{ route('tenant.bookings.index') }}" wire:navigate class="border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 font-medium py-2.5 px-6 rounded-xl transition-colors">
                    Cancel
                </a>
            </div>
        </div>
    </form>
</div>