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
        $this->booking = $booking;
        
        // Set customer_id as string to match select option values
        $this->customer_id = $booking->customer_id ? (string) $booking->customer_id : '';
        
        // Safely handle dates - ensure they are Carbon instances before formatting
        $this->check_in = $booking->check_in 
            ? Carbon::parse($booking->check_in)->format('Y-m-d') 
            : now()->format('Y-m-d');
        $this->check_out = $booking->check_out 
            ? Carbon::parse($booking->check_out)->format('Y-m-d') 
            : now()->addDay()->format('Y-m-d');
            
        $this->booking_reference = $booking->booking_reference;
        $this->status = $booking->status;
        
        // Load existing property items with their details
        foreach ($booking->items as $item) {
            $this->selectedProperties[$item->property_id] = [
                'quantity' => $item->quantity,
                'price' => $item->price,
                'id' => $item->id, // Keep track of the existing item ID for updates
            ];
        }
        
        // Load existing service items with their details
        foreach ($booking->services as $service) {
            $this->selectedServices[$service->service_id] = [
                'quantity' => $service->quantity,
                'price' => $service->service->price ?? $service->price ?? 0,
                'id' => $service->id,
            ];
        }
        
        $this->calculateTotal();
    }

    public function updatedCheckIn() { $this->calculateTotal(); }
    public function updatedCheckOut() { $this->calculateTotal(); }

    public function addProperty($propertyId, $price)
    {
        if (!isset($this->selectedProperties[$propertyId])) {
            $this->selectedProperties[$propertyId] = ['quantity' => 1, 'price' => $price];
        }
        $this->calculateTotal();
    }

    public function removeProperty($propertyId)
    {
        // If this was an existing item, delete it from the database immediately
        if (isset($this->selectedProperties[$propertyId]['id'])) {
            BookingItem::find($this->selectedProperties[$propertyId]['id'])->delete();
        }
        unset($this->selectedProperties[$propertyId]);
        $this->calculateTotal();
    }

    public function updatePropertyQuantity($propertyId, $quantity)
    {
        if (isset($this->selectedProperties[$propertyId])) {
            $this->selectedProperties[$propertyId]['quantity'] = max(1, (int)$quantity);
        }
        $this->calculateTotal();
    }

    public function addService($serviceId, $price)
    {
        if (!isset($this->selectedServices[$serviceId])) {
            $this->selectedServices[$serviceId] = ['quantity' => 1, 'price' => $price];
        } else {
            $this->selectedServices[$serviceId]['quantity']++;
        }
        $this->calculateTotal();
    }

    public function removeService($serviceId)
    {
        if (isset($this->selectedServices[$serviceId]['id'])) {
            BookingService::find($this->selectedServices[$serviceId]['id'])->delete();
        }
        unset($this->selectedServices[$serviceId]);
        $this->calculateTotal();
    }

    public function updateServiceQuantity($serviceId, $quantity)
    {
        if (isset($this->selectedServices[$serviceId])) {
            $this->selectedServices[$serviceId]['quantity'] = max(1, (int)$quantity);
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
        $excludeIds = BookingItem::whereHas('booking', function ($q) {
            $q->where('status', '!=', 'cancelled')
              ->where('id', '!=', $this->booking->id)
              ->where(function ($q) {
                  $q->whereBetween('check_in', [$this->check_in, $this->check_out])
                    ->orWhereBetween('check_out', [$this->check_in, $this->check_out])
                    ->orWhere(function ($q) {
                        $q->where('check_in', '<=', $this->check_in)
                          ->where('check_out', '>=', $this->check_out);
                    });
              });
        })->pluck('property_id')->toArray();
        
        // Don't exclude properties that are already selected in this booking
        $excludeIds = array_diff($excludeIds, array_keys($this->selectedProperties));
        
        return Property::where('is_active', true)
            ->whereNotIn('id', $excludeIds)
            ->orderBy('name')
            ->get();
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

        $this->booking->update([
            'customer_id' => $this->customer_id,
            'check_in' => $this->check_in,
            'check_out' => $this->check_out,
            'status' => $this->status,
            'total_amount' => $this->totalAmount,
        ]);

        $nights = Carbon::parse($this->check_in)->diffInDays($this->check_out);
        $nights = max(1, $nights);

        // Update or create property items
        $existingItemIds = $this->booking->items->pluck('id')->toArray();
        foreach ($this->selectedProperties as $propertyId => $item) {
            $subtotal = $item['price'] * $item['quantity'] * $nights;
            
            if (isset($item['id'])) {
                // Update existing item
                BookingItem::where('id', $item['id'])->update([
                    'quantity' => $item['quantity'],
                    'subtotal' => $subtotal,
                ]);
                $existingItemIds = array_diff($existingItemIds, [$item['id']]);
            } else {
                // Create new item
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
        // Delete any items that were removed
        BookingItem::whereIn('id', $existingItemIds)->delete();

        // Update or create service items
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

        session()->flash('message', 'Booking updated successfully.');
        return $this->redirectRoute('tenant.bookings.index', navigate: true);
    }
};
?>

<div class="p-6 max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Edit Booking #{{ $booking->booking_reference }}</h1>

    @if (session()->has('error'))
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    <form wire:submit="update" class="space-y-6">
        {{-- Customer Selection --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Customer Information</h2>
            <select wire:model="customer_id" class="w-full rounded-lg border-slate-300">
                <option value="">-- Select Customer --</option>
                @foreach($this->customers as $customer)
                    <option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->phone }})</option>
                @endforeach
            </select>
            @error('customer_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        {{-- Dates & Status --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Check-in Date</label>
                    <input type="date" wire:model.live="check_in" class="w-full rounded-lg border-slate-300">
                    @error('check_in') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Check-out Date</label>
                    <input type="date" wire:model.live="check_out" class="w-full rounded-lg border-slate-300">
                    @error('check_out') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Status</label>
                    <select wire:model="status" class="w-full rounded-lg border-slate-300">
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="checked_in">Checked In</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    @error('status') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- Properties --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Properties</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                @forelse($this->availableProperties as $property)
                    <div class="border rounded-lg p-3 flex justify-between items-center">
                        <div>
                            <p class="font-medium">{{ $property->name }}</p>
                            <p class="text-sm text-slate-500">₱{{ number_format($property->price, 2) }} / night • Cap: {{ $property->capacity }}</p>
                        </div>
                        <button type="button" wire:click="addProperty({{ $property->id }}, {{ $property->price }})" 
                                class="text-blue-600 hover:bg-blue-50 px-3 py-1 rounded text-sm">
                            + Add
                        </button>
                    </div>
                @empty
                    <p class="text-slate-500 col-span-2">No available properties for selected dates.</p>
                @endforelse
            </div>

            @if(count($selectedProperties) > 0)
                <h3 class="font-medium mb-2">Selected Properties</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b">
                            <tr class="text-slate-500">
                                <th class="text-left py-2">Property</th>
                                <th class="text-center">Price/Night</th>
                                <th class="text-center">Qty</th>
                                <th class="text-right">Subtotal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($selectedProperties as $id => $item)
                                @php 
                                    $property = App\Models\Property::find($id);
                                    $nights = Carbon::parse($check_in)->diffInDays($check_out);
                                    $nights = max(1, $nights);
                                @endphp
                                <tr class="border-b">
                                    <td class="py-2">{{ $property->name ?? 'Unknown' }}</td>
                                    <td class="text-center">₱{{ number_format($item['price'], 2) }}</td>
                                    <td class="text-center">
                                        <input type="number" wire:model.live="selectedProperties.{{ $id }}.quantity" 
                                               min="1" class="w-16 border rounded text-center">
                                    </td>
                                    <td class="text-right">₱{{ number_format($item['price'] * $item['quantity'] * $nights, 2) }}</td>
                                    <td class="text-center">
                                        <button type="button" wire:click="removeProperty({{ $id }})" class="text-red-500">&times;</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Services --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Additional Services</h2>
            
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach($this->availableServices as $service)
                    <button type="button" wire:click="addService({{ $service->id }}, {{ $service->price }})" 
                            class="border rounded-full px-4 py-2 text-sm hover:bg-slate-50">
                        {{ $service->name }} (₱{{ number_format($service->price, 2) }})
                    </button>
                @endforeach
            </div>

            @if(count($selectedServices) > 0)
                <h3 class="font-medium mb-2">Selected Services</h3>
                <table class="w-full text-sm">
                    <thead class="border-b">
                        <tr class="text-slate-500">
                            <th class="text-left py-2">Service</th>
                            <th class="text-center">Price</th>
                            <th class="text-center">Qty</th>
                            <th class="text-right">Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($selectedServices as $id => $item)
                            @php $service = App\Models\Service::find($id); @endphp
                            <tr class="border-b">
                                <td class="py-2">{{ $service->name ?? 'Unknown' }}</td>
                                <td class="text-center">₱{{ number_format($item['price'], 2) }}</td>
                                <td class="text-center">
                                    <input type="number" wire:model.live="selectedServices.{{ $id }}.quantity" 
                                           min="1" class="w-16 border rounded text-center">
                                </td>
                                <td class="text-right">₱{{ number_format($item['price'] * $item['quantity'], 2) }}</td>
                                <td class="text-center">
                                    <button type="button" wire:click="removeService({{ $id }})" class="text-red-500">&times;</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Total & Submit --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 flex justify-between items-center">
            <span class="text-xl font-bold">Total: ₱{{ number_format($totalAmount, 2) }}</span>
            <div class="flex gap-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    Update Booking
                </button>
                <a href="{{ route('tenant.bookings.index') }}" wire:navigate class="border px-6 py-2 rounded-lg hover:bg-slate-50">
                    Cancel
                </a>
            </div>
        </div>
    </form>
</div>