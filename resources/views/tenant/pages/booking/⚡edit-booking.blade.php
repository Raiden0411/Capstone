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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new 
#[Layout('tenant.layouts.app')]
#[Title('Edit Booking')]
class extends Component {
    public Booking $booking;
    
    #[Validate('required|exists:customers,id')]
    public $customer_id = '';
    
    #[Validate('required|date')]
    public $check_in;
    
    #[Validate('required|date|after:check_in')]
    public $check_out;
    
    public $booking_reference;
    
    #[Validate('required|in:pending,confirmed,checked_in,completed,cancelled')]
    public $status;
    
    public $selectedProperties = [];
    public $selectedServices = [];
    public $totalAmount = 0;

    public function mount(Booking $booking)
    {
        // Ensure the booking belongs to the current tenant
        if ($booking->tenant_id !== Auth::user()->tenant_id) {
            abort(403, 'Unauthorized.');
        }

        $this->booking = $booking;
        $this->customer_id = (string) $booking->customer_id;
        $this->check_in = $booking->check_in ? Carbon::parse($booking->check_in)->format('Y-m-d') : now()->format('Y-m-d');
        $this->check_out = $booking->check_out ? Carbon::parse($booking->check_out)->format('Y-m-d') : now()->addDay()->format('Y-m-d');
        $this->booking_reference = $booking->booking_reference;
        $this->status = $booking->status;
        
        foreach ($booking->items as $item) {
            $this->selectedProperties[$item->property_id] = [
                'quantity' => $item->quantity,
                'price' => $item->price,
                'id' => $item->id,
            ];
        }
        
        foreach ($booking->services as $service) {
            $this->selectedServices[$service->service_id] = [
                'quantity' => $service->quantity,
                'price' => $service->service->price ?? 0,
                'id' => $service->id,
            ];
        }
        
        $this->calculateTotal();
    }

    public function updatedCheckIn() { $this->calculateTotal(); }
    public function updatedCheckOut() { $this->calculateTotal(); }

    // Toggle a property on/off (no quantity)
    public function toggleProperty($propertyId, $price)
    {
        if (isset($this->selectedProperties[$propertyId])) {
            // Remove existing BookingItem if it had an ID
            if (isset($this->selectedProperties[$propertyId]['id'])) {
                BookingItem::find($this->selectedProperties[$propertyId]['id'])?->delete();
            }
            unset($this->selectedProperties[$propertyId]);
        } else {
            $this->selectedProperties[$propertyId] = ['quantity' => 1, 'price' => $price];
        }
        $this->calculateTotal();
    }

    // Toggle a service on/off (no quantity)
    public function toggleService($serviceId, $price)
    {
        if (isset($this->selectedServices[$serviceId])) {
            if (isset($this->selectedServices[$serviceId]['id'])) {
                BookingService::find($this->selectedServices[$serviceId]['id'])?->delete();
            }
            unset($this->selectedServices[$serviceId]);
        } else {
            $this->selectedServices[$serviceId] = ['quantity' => 1, 'price' => $price];
        }
        $this->calculateTotal();
    }

    public function calculateTotal()
    {
        $total = 0;
        $nights = Carbon::parse($this->check_in)->diffInDays(Carbon::parse($this->check_out));
        $nights = max(1, $nights);
        
        foreach ($this->selectedProperties as $item) {
            $total += $item['price'] * $item['quantity'] * $nights;
        }
        foreach ($this->selectedServices as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        $this->totalAmount = $total;
    }

    public function getCustomersProperty()
    {
        return Customer::orderBy('name')->get();
    }
    
    public function getAvailablePropertiesProperty()
    {
        if (!$this->check_in || !$this->check_out) {
            return collect();
        }

        $checkIn = $this->check_in;
        $checkOut = $this->check_out;
        $bookingId = $this->booking->id;

        $properties = Property::with('images')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $available = $properties->filter(function ($property) use ($checkIn, $checkOut, $bookingId) {
            if (isset($this->selectedProperties[$property->id])) {
                return true;
            }

            $hasConflict = BookingItem::where('property_id', $property->id)
                ->whereHas('booking', function ($query) use ($checkIn, $checkOut, $bookingId) {
                    $query->whereNotIn('status', ['cancelled', 'completed'])
                        ->where('id', '!=', $bookingId)
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

    public function update()
    {
        if (empty($this->selectedProperties)) {
            session()->flash('error', 'Please select at least one property.');
            return;
        }
        
        $this->validate();

        DB::transaction(function () {
            $this->booking->update([
                'customer_id' => $this->customer_id,
                'check_in' => $this->check_in,
                'check_out' => $this->check_out,
                'status' => $this->status,
                'total_amount' => $this->totalAmount,
            ]);

            $nights = Carbon::parse($this->check_in)->diffInDays($this->check_out);
            $nights = max(1, $nights);

            // Sync property items
            $existingItemIds = $this->booking->items->pluck('id')->toArray();
            foreach ($this->selectedProperties as $propertyId => $item) {
                $subtotal = $item['price'] * $item['quantity'] * $nights;
                if (isset($item['id'])) {
                    BookingItem::where('id', $item['id'])->update([
                        'quantity' => $item['quantity'],
                        'subtotal' => $subtotal,
                    ]);
                    $existingItemIds = array_diff($existingItemIds, [$item['id']]);
                } else {
                    BookingItem::create([
                        'tenant_id' => Auth::user()->tenant_id,
                        'booking_id' => $this->booking->id,
                        'property_id' => $propertyId,
                        'price' => $item['price'],
                        'quantity' => $item['quantity'],
                        'subtotal' => $subtotal,
                    ]);
                }
            }
            BookingItem::whereIn('id', $existingItemIds)->delete();

            // Sync service items
            $existingServiceIds = $this->booking->services->pluck('id')->toArray();
            foreach ($this->selectedServices as $serviceId => $item) {
                $subtotal = $item['price'] * $item['quantity'];
                if (isset($item['id'])) {
                    BookingService::where('id', $item['id'])->update([
                        'quantity' => $item['quantity'],
                        'subtotal' => $subtotal,
                    ]);
                    $existingServiceIds = array_diff($existingServiceIds, [$item['id']]);
                } else {
                    BookingService::create([
                        'tenant_id' => Auth::user()->tenant_id,
                        'booking_id' => $this->booking->id,
                        'service_id' => $serviceId,
                        'quantity' => $item['quantity'],
                        'subtotal' => $subtotal,
                    ]);
                }
            }
            BookingService::whereIn('id', $existingServiceIds)->delete();
        });

        session()->flash('message', 'Booking updated successfully.');
        return $this->redirectRoute('tenant.bookings.show', ['booking' => $this->booking->id], navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto text-gray-900 dark:text-white space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold">Edit Booking #{{ $booking->booking_reference }}</h1>
            <p class="text-gray-500 dark:text-slate-400 mt-1">Modify customer, dates, properties, and services.</p>
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

    <form wire:submit="update" class="space-y-6">
        {{-- Customer Selection --}}
        <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
            <h2 class="text-lg font-semibold mb-4">Customer Information</h2>
            <select wire:model="customer_id" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                <option value="">-- Select Customer --</option>
                @foreach($this->customers as $customer)
                    <option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->phone }})</option>
                @endforeach
            </select>
            @error('customer_id') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        {{-- Dates & Status --}}
        <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Check-in Date</label>
                    <input type="date" wire:model.live="check_in" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                    @error('check_in') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Check-out Date</label>
                    <input type="date" wire:model.live="check_out" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                    @error('check_out') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Status</label>
                    <select wire:model="status" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="checked_in">Checked In</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    @error('status') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- Property Card Grid --}}
        <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
            <h2 class="text-lg font-semibold mb-6">Select Room(s) — Tap to add</h2>

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
            <h2 class="text-lg font-semibold mb-4">Add‑On Services</h2>
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

        {{-- Total & Submit --}}
        <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <span class="text-xl font-bold">Total: ₱{{ number_format($totalAmount, 2) }}</span>
            <div class="flex gap-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-xl shadow-sm transition-colors flex items-center gap-2 data-loading:opacity-75 data-loading:cursor-not-allowed">
                    <span class="in-data-loading:hidden">Update Booking</span>
                    <span class="not-in-data-loading:hidden flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Saving...
                    </span>
                </button>
                <a href="{{ route('tenant.bookings.show', $booking->id) }}" wire:navigate class="border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 font-medium py-2.5 px-6 rounded-xl transition-colors">
                    Cancel
                </a>
            </div>
        </div>
    </form>
</div>