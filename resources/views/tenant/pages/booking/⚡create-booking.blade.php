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
    
    // Phone validation is now defined in rules() – no attribute here
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

    protected function rules()
    {
        return [
            'customerPhone' => [
                'required',
                'string',
                'max:20',
                'regex:/^(09|\+639)\d{9}$/',
            ],
        ];
    }

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
            if ($field === 'customerPhone') {
                // Remove any spaces, dashes, or invalid characters
                $this->customerPhone = preg_replace('/[^0-9+]/', '', $this->customerPhone);
            }
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

    public function toggleProperty($propertyId, $price)
    {
        if (isset($this->selectedProperties[$propertyId])) {
            unset($this->selectedProperties[$propertyId]);
        } else {
            $this->selectedProperties[$propertyId] = ['quantity' => 1, 'price' => $price];
        }
        $this->calculateTotal();
    }

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

        $checkIn = $this->check_in;
        $checkOut = $this->check_out;

        foreach ($this->selectedProperties as $propertyId => $item) {
            $conflict = BookingItem::where('property_id', $propertyId)
                ->whereHas('booking', function ($query) use ($checkIn, $checkOut) {
                    $query->whereNotIn('status', ['cancelled', 'completed'])
                          ->where('check_in', '<', $checkOut)
                          ->where('check_out', '>', $checkIn);
                })
                ->exists();

            if ($conflict) {
                $property = Property::find($propertyId);
                $this->addError(
                    'check_in',
                    "The property '{$property->name}' is not available for the selected dates. Please remove it or change the dates."
                );
                return;
            }
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

@push('styles')
<style>
    /* Fix invisible options in glass-style selects */
    select option {
        background: #1e293b;
        color: #e2e8f0;
    }
</style>
@endpush

<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-brand-400 flex items-center gap-2 mb-2">
                <span class="w-4 h-px bg-brand-400"></span>Quick Reservation
            </p>
            <h1 class="font-display text-3xl md:text-4xl font-bold text-white">
                Walk‑In <em class="italic text-brand-400">Checkout</em>
            </h1>
            <p class="text-sm text-white/50 mt-1">Quickly register a guest and complete their stay.</p>
        </div>
    </div>

    @if (session()->has('error'))
        <div class="glass-card border-l-4 border-l-red-400 p-4 text-sm text-white/80 flex items-center gap-3">
            <svg class="w-4 h-4 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.293 10.293a1 1 0 011.414 0L12 10.586l.293-.293a1 1 0 111.414 1.414L13.414 12l.293.293a1 1 0 01-1.414 1.414L12 13.414l-.293.293a1 1 0 01-1.414-1.414L10.586 12l-.293-.293a1 1 0 010-1.414z"/></svg>
            {{ session('error') }}
        </div>
    @endif

    <form wire:submit="submit" class="space-y-6">
        {{-- Customer Information --}}
        <div class="glass-card !rounded-xl p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-white mb-4">Guest Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/70 mb-1">Full Name *</label>
                    <input type="text" wire:model="customerName"
                           class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"
                           placeholder="Guest name">
                    @error('customerName') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/70 mb-1">Phone * <span class="text-white/40 font-normal">(e.g. 09123456789)</span></label>
                    <input type="text" wire:model="customerPhone"
                           class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"
                           placeholder="09123456789">
                    @error('customerPhone') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
            <p class="text-xs text-white/40 mt-2">* Required for walk‑in. Phone must be 11 digits (09XXXXXXXXX or +639XXXXXXXXX).</p>
        </div>

        {{-- Dates & Reference --}}
        <div class="glass-card !rounded-xl p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-white mb-4">Stay Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/70 mb-1">Check‑in *</label>
                    <input type="date" wire:model.live="check_in"
                           class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                    @error('check_in') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/70 mb-1">Check‑out *</label>
                    <input type="date" wire:model.live="check_out"
                           class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                    @error('check_out') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/70 mb-1">Booking Ref</label>
                    <input type="text" wire:model="booking_reference"
                           class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white/60 cursor-not-allowed"
                           readonly>
                    <button type="button" wire:click="generateBookingReference" class="text-xs text-brand-400 hover:underline mt-1">Generate New</button>
                </div>
            </div>
        </div>

        {{-- Property Selection --}}
        <div class="glass-card !rounded-xl p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-white mb-6">Select Room(s) — Tap to add</h2>

            @if(count($this->availableProperties) > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($this->availableProperties as $property)
                        @php
                            $isSelected = isset($selectedProperties[$property->id]);
                            $firstImg = $property->images->first();
                        @endphp
                        <div class="relative group cursor-pointer rounded-2xl border-2 transition-all duration-200 overflow-hidden
                                    {{ $isSelected ? 'border-brand-400 ring-2 ring-brand-500/30' : 'border-white/10 hover:border-brand-400/50' }}"
                             wire:click="toggleProperty({{ $property->id }}, {{ $property->price }})">
                            <div class="aspect-4/4 overflow-hidden rounded-t-2xl">
                                <img class="size-full object-cover group-hover:scale-105 transition duration-700"
                                     src="{{ $firstImg ? Storage::url($firstImg->image_path) : asset('images/placeholder-room.jpg') }}"
                                     alt="{{ $property->name }}">
                            </div>
                            <div class="p-4">
                                <h3 class="font-medium text-white">{{ $property->name }}</h3>
                                <p class="mt-1 text-sm text-white/60">₱{{ number_format($property->price, 2) }} / night</p>
                                <p class="text-xs text-white/40 mt-1">Capacity: {{ $property->capacity }} persons</p>
                            </div>
                            @if($isSelected)
                                <div class="absolute top-3 right-3 bg-brand-600 text-white rounded-full p-1 shadow">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-white/50 text-center py-8">No available rooms for selected dates.</p>
            @endif

            @if(count($selectedProperties) > 0)
                <div class="mt-8 space-y-3">
                    <h3 class="font-medium text-white mb-4">Selected Rooms</h3>
                    @foreach($selectedProperties as $id => $item)
                        @php
                            $prop = $this->availableProperties->firstWhere('id', $id) ?? App\Models\Property::find($id);
                            $nights = Carbon::parse($check_in)->diffInDays($check_out);
                            $nights = max(1, $nights);
                            $roomTotal = $item['price'] * $item['quantity'] * $nights;
                        @endphp
                        <div class="flex items-center gap-4 p-3 bg-white/5 rounded-xl border border-white/10">
                            <div class="w-12 h-12 rounded-lg overflow-hidden bg-white/10 shrink-0">
                                @if($prop && $prop->images->isNotEmpty())
                                    <img src="{{ Storage::url($prop->images->first()->image_path) }}" class="w-full h-full object-cover" alt="{{ $prop->name }}">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-white/30">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    </div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-white truncate">{{ $prop->name ?? 'Room' }}</p>
                                <p class="text-xs text-white/50 mt-0.5">
                                    ₱{{ number_format($item['price'], 2) }} / night · {{ $nights }} nights
                                </p>
                                <p class="text-sm font-semibold text-white mt-1">₱{{ number_format($roomTotal, 2) }}</p>
                            </div>
                            <button type="button" wire:click="toggleProperty({{ $id }}, {{ $item['price'] }})" 
                                    class="p-1 text-white/40 hover:text-red-400 transition" title="Remove room">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Add‑On Services --}}
        <div class="glass-card !rounded-xl p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-white mb-4">Add‑On Services</h2>
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach($this->availableServices as $service)
                    @php $isServiceSelected = isset($selectedServices[$service->id]); @endphp
                    <button type="button" wire:click="toggleService({{ $service->id }}, {{ $service->price }})"
                            class="border rounded-full px-4 py-2 text-sm transition-colors
                                   {{ $isServiceSelected 
                                      ? 'bg-brand-500/20 border-brand-400/50 text-brand-300'
                                      : 'border-white/10 hover:bg-white/5 text-white/70' }}">
                        {{ $service->name }} (₱{{ number_format($service->price, 2) }})
                    </button>
                @endforeach
            </div>

            @if(count($selectedServices) > 0)
                <div class="space-y-2">
                    @foreach($selectedServices as $id => $item)
                        @php $service = App\Models\Service::find($id); @endphp
                        <div class="flex items-center gap-3 p-3 bg-white/5 rounded-xl border border-white/10">
                            <div class="flex-1">
                                <p class="font-medium text-white">{{ $service->name ?? 'Service' }}</p>
                                <p class="text-xs text-white/50">₱{{ number_format($item['price'], 2) }}</p>
                            </div>
                            <p class="text-sm font-semibold text-white w-20 text-right">₱{{ number_format($item['price'], 2) }}</p>
                            <button type="button" wire:click="toggleService({{ $id }}, {{ $item['price'] }})" class="p-1 text-white/40 hover:text-red-400 transition" title="Remove service">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Payment Method --}}
        <div class="glass-card !rounded-xl p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-white mb-4">Payment</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/70 mb-1">Payment Method *</label>
                    <select wire:model.live="payment_method"
                            class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition appearance-none">
                        <option value="cash">Cash</option>
                        <option value="card">Credit/Debit Card</option>
                        <option value="gcash">GCash</option>
                        <option value="paymaya">PayMaya</option>
                    </select>
                    @error('payment_method') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- Total & Actions --}}
        <div class="glass-card !rounded-xl p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <span class="text-xl font-bold text-white">Total: ₱{{ number_format($totalAmount, 2) }}</span>
            <div class="flex gap-3">
                <button type="submit" 
                        wire:loading.attr="disabled"
                        class="bg-brand-600 hover:bg-brand-500 text-white font-semibold py-3 px-8 rounded-xl shadow-lg shadow-brand-500/20 transition hover:scale-105 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove>
                        {{ $payment_method === 'cash' ? 'Complete Checkout' : 'Proceed to Pay' }}
                    </span>
                    <span wire:loading class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Processing...
                    </span>
                </button>
                <a href="{{ route('tenant.bookings.index') }}" wire:navigate 
                   class="glass px-6 py-3 rounded-xl text-white/80 hover:bg-white/10 font-medium transition">
                    Cancel
                </a>
            </div>
        </div>
    </form>
</div>