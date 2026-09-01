{{-- resources/views/tenant/pages/booking/⚡create-booking.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\Booking;
use App\Models\User;
use App\Models\Property;
use App\Models\Service;
use App\Models\BookingItem;
use App\Models\BookingService;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

new 
#[Layout('tenant.layouts.app')]
#[Title('Walk-In Booking')]
class extends Component {
    #[Validate('required|string|max:255')]
    public $customerName = '';
    
    #[Validate(['required', 'string', 'max:20', 'regex:/^(09|\+639)\d{9}$/'])]
    public $customerPhone = '';
    
    #[Validate('nullable|email|max:255')]
    public $customerEmail = '';
    
    #[Validate('nullable|string|max:255')]
    public $customerAddress = '';

    #[Validate('required|date|after_or_equal:today')]
    public $check_in;
    
    #[Validate('required|date|after:check_in')]
    public $check_out;
    
    public $booking_reference;
    public $totalAmount = 0;
    
    // Store as [id => quantity] to prevent frontend price manipulation
    public $selectedProperties = [];
    public $selectedServices = [];
    
    #[Validate('required|in:cash,qr')]
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
            if ($field === 'customerPhone') {
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
        $maxDate = now()->addDays(30)->format('Y-m-d');
        if ($this->check_in > $maxDate) {
            $this->check_in = $maxDate;
        }
        if ($this->check_out && Carbon::parse($this->check_in)->gte(Carbon::parse($this->check_out))) {
            $this->check_out = Carbon::parse($this->check_in)->addDay()->format('Y-m-d');
        }
        $this->calculateTotal();
    }

    public function updatedCheckOut()
    {
        $maxDate = now()->addDays(30)->format('Y-m-d');
        if ($this->check_out > $maxDate) {
            $this->check_out = $maxDate;
        }
        if (Carbon::parse($this->check_out)->lte(Carbon::parse($this->check_in))) {
            $this->check_out = Carbon::parse($this->check_in)->addDay()->format('Y-m-d');
        }
        $this->calculateTotal();
    }

    public function toggleProperty($propertyId)
    {
        if (isset($this->selectedProperties[$propertyId])) {
            unset($this->selectedProperties[$propertyId]);
        } else {
            $this->selectedProperties[$propertyId] = 1; // quantity
        }
        $this->calculateTotal();
    }

    public function toggleService($serviceId)
    {
        if (isset($this->selectedServices[$serviceId])) {
            unset($this->selectedServices[$serviceId]);
        } else {
            $this->selectedServices[$serviceId] = 1; // quantity
        }
        $this->calculateTotal();
    }

    public function calculateTotal()
    {
        $total = 0;
        $days = $this->getNumberOfDays();

        if (!empty($this->selectedProperties)) {
            $properties = Property::whereIn('id', array_keys($this->selectedProperties))->get();
            foreach ($properties as $property) {
                $quantity = $this->selectedProperties[$property->id];
                $total += $property->price * $quantity * $days;
            }
        }

        if (!empty($this->selectedServices)) {
            $services = Service::whereIn('id', array_keys($this->selectedServices))->get();
            foreach ($services as $service) {
                $quantity = $this->selectedServices[$service->id];
                $total += $service->price * $quantity;
            }
        }

        $this->totalAmount = $total;
    }

    protected function getNumberOfDays(): int
    {
        if ($this->check_in && $this->check_out) {
            $days = Carbon::parse($this->check_in)->diffInDays(Carbon::parse($this->check_out));
            return max(1, $days);
        }
        return 1;
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

        return $properties->filter(function ($property) use ($checkIn, $checkOut) {
            return !BookingItem::where('property_id', $property->id)
                ->whereHas('booking', function ($query) use ($checkIn, $checkOut) {
                    $query->whereNotIn('status', ['cancelled', 'completed'])
                        ->where('check_in', '<', $checkOut)
                        ->where('check_out', '>', $checkIn);
                })->exists();
        })->values();
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

        $checkIn = $this->check_in;
        $checkOut = $this->check_out;

        foreach (array_keys($this->selectedProperties) as $propertyId) {
            $conflict = BookingItem::where('property_id', $propertyId)
                ->whereHas('booking', function ($query) use ($checkIn, $checkOut) {
                    $query->whereNotIn('status', ['cancelled', 'completed'])
                          ->where('check_in', '<', $checkOut)
                          ->where('check_out', '>', $checkIn);
                })->exists();

            if ($conflict) {
                $property = Property::find($propertyId);
                $this->addError('check_in', "The property '{$property->name}' is no longer available for the selected dates.");
                return;
            }
        }

        if (!$this->booking_reference) {
            $this->generateBookingReference();
        }

        DB::transaction(function () {
            $tenantId = Auth::user()->tenant_id;
            
            $user = User::firstOrCreate(
                ['email' => $this->customerEmail ?: ('guest_' . Str::random(8) . '@walkin.local')],
                [
                    'tenant_id' => $tenantId,
                    'name'      => $this->customerName,
                    'password'  => bcrypt(Str::random(16)),
                    'is_active' => true,
                ]
            );

            $this->calculateTotal();

            $booking = Booking::create([
                'tenant_id'         => $tenantId,
                'user_id'           => $user->id,
                'booking_reference' => $this->booking_reference,
                'check_in'          => $this->check_in,
                'check_out'         => $this->check_out,
                'total_amount'      => $this->totalAmount,
                'status'            => 'pending',
                'booking_type'      => 'full',
            ]);

            $days = $this->getNumberOfDays();

            $properties = Property::whereIn('id', array_keys($this->selectedProperties))->get();
            foreach ($properties as $property) {
                $quantity = $this->selectedProperties[$property->id];
                BookingItem::create([
                    'tenant_id'   => $tenantId,
                    'booking_id'  => $booking->id,
                    'property_id' => $property->id,
                    'price'       => $property->price,
                    'quantity'    => $quantity,
                    'subtotal'    => $property->price * $quantity * $days,
                ]);
            }

            if (!empty($this->selectedServices)) {
                $services = Service::whereIn('id', array_keys($this->selectedServices))->get();
                foreach ($services as $service) {
                    $quantity = $this->selectedServices[$service->id];
                    BookingService::create([
                        'tenant_id'  => $tenantId,
                        'booking_id' => $booking->id,
                        'service_id' => $service->id,
                        'quantity'   => $quantity,
                        'subtotal'   => $service->price * $quantity,
                    ]);
                }
            }

            Payment::create([
                'tenant_id'       => $tenantId,
                'booking_id'      => $booking->id,
                'amount'          => $this->totalAmount,
                'payment_method'  => $this->payment_method,
                'payment_status'  => 'paid',
                'paid_at'         => now(),
                'payment_type'    => 'full',
            ]);

            $booking->update(['status' => 'confirmed']);
            $this->createdBookingId = $booking->id;
        });

        session()->flash('message', 'Booking created and confirmed successfully.');
        return $this->redirectRoute('tenant.bookings.show', ['booking' => $this->createdBookingId], navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Walk-In Booking</h1>
        </div>
        <a href="#"
           @click.prevent="window.history.back()"
           class="btn-secondary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Bookings
        </a>
    </div>

    {{-- Flash Error --}}
    @if (session()->has('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md text-sm text-red-700 dark:text-red-300 font-medium transition-all duration-300">
            {{ session('error') }}
        </div>
    @endif

    <form wire:submit="submit" class="space-y-6 relative">
        {{-- Global Loading Overlay --}}
        <div wire:loading.delay.longer wire:target="submit"
             class="absolute inset-0 z-10 flex items-center justify-center rounded-2xl bg-white/60 dark:bg-gray-900/60 backdrop-blur-sm">
            <div class="animate-spin h-12 w-12 rounded-full border-4 border-primary-600 border-t-transparent"></div>
        </div>

        {{-- Customer Information --}}
        <div class="card p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Guest Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="lg:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name *</label>
                    <input type="text" wire:model="customerName" class="input w-full" placeholder="Guest name">
                    @error('customerName') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="lg:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone * <span class="text-gray-400 font-normal">(e.g. 09123456789)</span></label>
                    <input type="text" wire:model.live.debounce.300ms="customerPhone" class="input w-full" placeholder="09123456789">
                    @error('customerPhone') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="lg:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email (Optional)</label>
                    <input type="email" wire:model="customerEmail" class="input w-full" placeholder="guest@example.com">
                    @error('customerEmail') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="lg:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address (Optional)</label>
                    <input type="text" wire:model="customerAddress" class="input w-full" placeholder="Complete address">
                    @error('customerAddress') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- Dates & Reference --}}
        <div class="card p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Stay Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Check-in *</label>
                    <input type="date" wire:model.live="check_in"
                           min="{{ now()->format('Y-m-d') }}"
                           max="{{ now()->addDays(30)->format('Y-m-d') }}"
                           class="input w-full">
                    @error('check_in') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Check-out *</label>
                    <input type="date" wire:model.live="check_out"
                           min="{{ now()->addDay()->format('Y-m-d') }}"
                           max="{{ now()->addDays(30)->format('Y-m-d') }}"
                           class="input w-full">
                    @error('check_out') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Booking Ref</label>
                    <input type="text" wire:model="booking_reference"
                           class="input w-full bg-gray-100 dark:bg-gray-900 cursor-not-allowed"
                           readonly>
                    <button type="button" wire:click="generateBookingReference"
                            class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-primary-600 hover:text-primary-700 active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Generate New
                    </button>
                </div>
            </div>
        </div>

        {{-- Property Selection --}}
        <div class="card p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Select Property</h2>

            @if(count($this->availableProperties) > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($this->availableProperties as $property)
                        @php
                            $isSelected = isset($selectedProperties[$property->id]);
                            $firstImg = $property->images->first();
                        @endphp
                        <div wire:key="prop-{{ $property->id }}" 
                             class="relative group cursor-pointer rounded-2xl border-2 transition-all duration-200 overflow-hidden active:scale-[0.98]
                                    {{ $isSelected ? 'border-primary-600 ring-2 ring-primary-500/30' : 'border-gray-200 dark:border-gray-700 hover:border-primary-400/50' }}"
                             wire:click="toggleProperty({{ $property->id }})">
                            <div class="aspect-[4/3] overflow-hidden rounded-t-xl">
                                <img class="w-full h-full object-cover group-hover:scale-105 transition duration-700"
                                     src="{{ $firstImg ? asset('storage/'. $firstImg->image_path) : asset('images/placeholder-room.jpg') }}"
                                     alt="{{ $property->name }}">
                            </div>
                            <div class="p-4 bg-white dark:bg-gray-800">
                                <h3 class="font-medium text-gray-900 dark:text-white truncate">{{ $property->name }}</h3>
                                <p class="mt-1 text-sm text-primary-600 dark:text-primary-400 font-semibold">₱{{ number_format($property->price, 2) }} <span class="text-gray-500 font-normal">/ day</span></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Capacity: {{ $property->capacity }} persons</p>
                            </div>
                            @if($isSelected)
                                <div class="absolute top-3 right-3 bg-primary-600 text-white rounded-full p-1 shadow-md">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-10 bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No properties available</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try selecting different dates for your stay.</p>
                </div>
            @endif

            @if(count($selectedProperties) > 0)
                <div class="mt-8 space-y-3">
                    <h3 class="font-medium text-gray-900 dark:text-white mb-4 border-t border-gray-100 dark:border-gray-700 pt-6">Selected Items</h3>
                    @foreach($selectedProperties as $id => $quantity)
                        @php
                            $prop = $this->availableProperties->firstWhere('id', $id);
                            $days = $this->getNumberOfDays();
                            $roomTotal = ($prop->price ?? 0) * $quantity * $days;
                        @endphp
                        <div wire:key="sel-prop-{{ $id }}" class="flex items-center gap-4 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700 transition-all hover:bg-gray-100 dark:hover:bg-gray-700">
                            <div class="w-14 h-14 rounded-lg overflow-hidden bg-gray-200 dark:bg-gray-600 shrink-0 shadow-sm">
                                @if($prop && $prop->images->isNotEmpty())
                                    <img src="{{ asset('storage/'. $prop->images->first()->image_path) }}" class="w-full h-full object-cover" alt="{{ $prop->name }}">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-gray-400">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    </div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 dark:text-white truncate">{{ $prop->name ?? 'Unknown Property' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    ₱{{ number_format($prop->price ?? 0, 2) }} / day &middot; {{ $days }} day{{ $days > 1 ? 's' : '' }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-gray-900 dark:text-white">₱{{ number_format($roomTotal, 2) }}</p>
                            </div>
                            <button type="button" wire:click="toggleProperty({{ $id }})" 
                                    class="p-2 text-gray-400 dark:text-gray-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/20 dark:hover:text-red-400 rounded-full transition active:scale-95" title="Remove item">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Add-On Services --}}
        <div class="card p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Add-On Services (Optional)</h2>
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach($this->availableServices as $service)
                    @php $isServiceSelected = isset($selectedServices[$service->id]); @endphp
                    <button wire:key="svc-btn-{{ $service->id }}" type="button" wire:click="toggleService({{ $service->id }})"
                            class="border rounded-full px-4 py-2 text-sm font-medium transition-all duration-200 active:scale-95 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-primary-500
                                   {{ $isServiceSelected 
                                      ? 'bg-primary-50 dark:bg-primary-500/15 border-primary-200 dark:border-primary-500/30 text-primary-600 dark:text-primary-400 shadow-sm'
                                      : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
                        {{ $service->name }} (+₱{{ number_format($service->price, 2) }})
                    </button>
                @endforeach
            </div>

            @if(count($selectedServices) > 0)
                <div class="space-y-2 mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                    @foreach($selectedServices as $id => $quantity)
                        @php $service = $this->availableServices->firstWhere('id', $id); @endphp
                        @if($service)
                            <div wire:key="sel-svc-{{ $id }}" class="flex items-center justify-between gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $service->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Fixed rate per booking</p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">₱{{ number_format($service->price, 2) }}</p>
                                    <button type="button" wire:click="toggleService({{ $id }})" class="p-2 text-gray-400 dark:text-gray-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/20 dark:hover:text-red-400 rounded-full transition active:scale-95" title="Remove service">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Payment Method --}}
        <div class="card p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Payment Method</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select Method *</label>
                    <select wire:model.live="payment_method" class="select w-full">
                        <option value="cash">Cash (On Hand)</option>
                        <option value="qr">QR Code / Digital</option>
                    </select>
                    @error('payment_method') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-3 bg-primary-50 dark:bg-primary-900/20 p-3 rounded-lg border border-primary-100 dark:border-primary-800/30">
                <span class="font-medium text-primary-800 dark:text-primary-300">Note:</span> 
                @if($payment_method === 'cash')
                    Payment will be recorded as cash and this walk-in booking will be confirmed immediately.
                @else
                    Ensure the customer has successfully transferred the funds via QR before completing this checkout.
                @endif
            </p>
        </div>

        {{-- Total & Actions --}}
        <div class="card p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6 sticky bottom-4 z-20">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Grand Total</p>
                <span class="text-3xl font-black text-primary-600 dark:text-primary-400">₱{{ number_format($totalAmount, 2) }}</span>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-3">
                <a href="{{ route('tenant.bookings.index') }}" wire:navigate class="btn-secondary text-center px-6 py-2.5 active:scale-95 transition-transform">
                    Cancel
                </a>
                <button type="submit" 
                        wire:loading.attr="disabled"
                        class="btn-primary px-8 py-2.5 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg shadow-primary-500/30 flex justify-center items-center active:scale-95 transition-transform">
                    <span wire:loading.remove wire:target="submit">Complete Checkout</span>
                    <span wire:loading wire:target="submit" class="flex items-center gap-2">
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