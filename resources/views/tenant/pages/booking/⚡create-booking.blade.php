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
use Illuminate\Support\Str;
use Carbon\Carbon;

new 
#[Layout('tenant.layouts.app')]
#[Title('Create Booking')]
class extends Component {
    #[Validate('required|string|max:255')]
    public $customerName = '';
    
    #[Validate('nullable|string|max:20')]
    public $customerPhone = '';
    
    #[Validate('nullable|email|max:255')]
    public $customerEmail = '';
    
    #[Validate('nullable|string')]
    public $customerAddress = '';

    #[Validate('nullable|exists:customers,id')]
    public $existingCustomerId = '';
    
    public $customerSearch = '';
    public $showExistingCustomerDropdown = false;

    #[Validate('required|date|after_or_equal:today')]
    public $check_in;
    #[Validate('required|date|after:check_in')]
    public $check_out;
    public $booking_reference;
    public $notes;

    public $selectedProperties = [];
    public $selectedServices = [];
    public $totalAmount = 0;

    public function mount()
    {
        $this->check_in = now()->format('Y-m-d');
        $this->check_out = now()->addDay()->format('Y-m-d');
        $this->generateBookingReference();
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

    public function updatedExistingCustomerId($value)
    {
        if ($value) {
            $customer = Customer::find($value);
            if ($customer) {
                $this->customerName = $customer->name;
                $this->customerPhone = $customer->phone;
                $this->customerEmail = $customer->email;
                $this->customerAddress = $customer->address;
            }
        } else {
            $this->reset(['customerName', 'customerPhone', 'customerEmail', 'customerAddress']);
        }
    }

    public function selectExistingCustomer($id)
    {
        $this->existingCustomerId = $id;
        $customer = Customer::find($id);
        if ($customer) {
            $this->customerName = $customer->name;
            $this->customerPhone = $customer->phone;
            $this->customerEmail = $customer->email;
            $this->customerAddress = $customer->address;
        }
        $this->showExistingCustomerDropdown = false;
        $this->customerSearch = '';
    }

    public function clearExistingCustomer()
    {
        $this->existingCustomerId = '';
        $this->reset(['customerName', 'customerPhone', 'customerEmail', 'customerAddress']);
    }

    public function addProperty($propertyId, $price)
    {
        if (!isset($this->selectedProperties[$propertyId])) {
            $this->selectedProperties[$propertyId] = ['quantity' => 1, 'price' => $price];
        }
        $this->calculateTotal();
    }

    public function removeProperty($propertyId)
    {
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

    public function getFilteredCustomersProperty()
    {
        if (empty($this->customerSearch)) {
            return collect();
        }
        return Customer::where('name', 'like', '%' . $this->customerSearch . '%')
            ->orWhere('phone', 'like', '%' . $this->customerSearch . '%')
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    public function getAvailablePropertiesProperty()
    {
        if (!$this->check_in || !$this->check_out) {
            return collect();
        }

        $checkIn = $this->check_in;
        $checkOut = $this->check_out;

        // Get all active and available properties
        $properties = Property::where('is_active', true)
            ->where('status', 'available')
            ->orderBy('name')
            ->get();

        // Filter out properties that have conflicting bookings
        $available = $properties->filter(function ($property) use ($checkIn, $checkOut) {
            $hasConflict = BookingItem::where('property_id', $property->id)
                ->whereHas('booking', function ($query) use ($checkIn, $checkOut) {
                    $query->where('status', '!=', 'cancelled')
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

    public function save()
    {
        if (empty($this->selectedProperties)) {
            session()->flash('error', 'Please select at least one property.');
            return;
        }

        if (!$this->existingCustomerId) {
            $this->validate([
                'customerName' => 'required|string|max:255',
                'customerPhone' => 'nullable|string|max:20',
                'customerEmail' => 'nullable|email|max:255',
                'customerAddress' => 'nullable|string',
            ]);
        } else {
            $this->validate(['existingCustomerId' => 'required|exists:customers,id']);
        }

        $this->validate([
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
        ]);

        if (!$this->booking_reference) {
            $this->generateBookingReference();
        }

        $customerId = $this->existingCustomerId;
        if (!$customerId) {
            $customer = Customer::create([
                'tenant_id' => Auth::user()->tenant_id,
                'name' => $this->customerName,
                'phone' => $this->customerPhone,
                'email' => $this->customerEmail,
                'address' => $this->customerAddress,
            ]);
            $customerId = $customer->id;
        }

        $booking = Booking::create([
            'tenant_id' => Auth::user()->tenant_id,
            'customer_id' => $customerId,
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

        session()->flash('message', 'Booking created successfully.');
        return $this->redirectRoute('tenant.bookings.index', navigate: true);
    }
};
?>

<div class="p-6 max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">New Booking</h1>

    @if (session()->has('error'))
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        {{-- Customer Information --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Customer Information</h2>

            {{-- Search existing customer (optional) --}}
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Find existing customer (optional)</label>
                <div class="relative">
                    <input type="text" wire:model.live="customerSearch" 
                           @focus="$wire.showExistingCustomerDropdown = true"
                           @blur="setTimeout(() => $wire.showExistingCustomerDropdown = false, 200)"
                           placeholder="Search by name or phone..." 
                           class="w-full rounded-lg border-slate-300 pr-10">
                    @if($existingCustomerId)
                        <button type="button" wire:click="clearExistingCustomer" class="absolute right-2 top-2 text-slate-400 hover:text-red-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    @endif
                </div>
                @if($showExistingCustomerDropdown && count($this->filteredCustomers) > 0)
                    <div class="absolute z-10 mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-lg max-h-60 overflow-auto">
                        @foreach($this->filteredCustomers as $customer)
                            <div wire:click="selectExistingCustomer({{ $customer->id }})" class="px-4 py-2 hover:bg-slate-50 cursor-pointer">
                                <span class="font-medium">{{ $customer->name }}</span>
                                <span class="text-sm text-slate-500 ml-2">{{ $customer->phone }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if($existingCustomerId)
                    <p class="text-xs text-green-600 mt-1">✓ Using existing customer</p>
                @endif
            </div>

            {{-- New Customer Form (always visible) --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Full Name *</label>
                    <input type="text" wire:model="customerName" class="w-full rounded-lg border-slate-300" placeholder="Enter guest name">
                    @error('customerName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Phone</label>
                    <input type="text" wire:model="customerPhone" class="w-full rounded-lg border-slate-300" placeholder="Contact number">
                    @error('customerPhone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input type="email" wire:model="customerEmail" class="w-full rounded-lg border-slate-300" placeholder="Email address">
                    @error('customerEmail') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Address</label>
                    <input type="text" wire:model="customerAddress" class="w-full rounded-lg border-slate-300" placeholder="Residential address">
                    @error('customerAddress') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-2">* Required fields. Fill in new customer details or search above to reuse an existing one.</p>
        </div>

        {{-- Dates & Reference --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Booking Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Check-in Date *</label>
                    <input type="date" wire:model.live="check_in" class="w-full rounded-lg border-slate-300">
                    @error('check_in') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Check-out Date *</label>
                    <input type="date" wire:model.live="check_out" class="w-full rounded-lg border-slate-300">
                    @error('check_out') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Booking Reference</label>
                    <input type="text" wire:model="booking_reference" class="w-full rounded-lg border-slate-300 bg-slate-50" readonly>
                    <button type="button" wire:click="generateBookingReference" class="text-xs text-blue-600 mt-1">Generate New</button>
                </div>
            </div>
        </div>

        {{-- Property Selection --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Select Properties</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                @forelse($this->availableProperties as $property)
                    <div class="border rounded-lg p-3 flex justify-between items-center">
                        <div>
                            <p class="font-medium">{{ $property->name }}</p>
                            <p class="text-sm text-slate-500">₱{{ number_format($property->price, 2) }} / night • Capacity: {{ $property->capacity }}</p>
                        </div>
                        <button type="button" wire:click="addProperty({{ $property->id }}, {{ $property->price }})" class="text-blue-600 hover:bg-blue-50 px-3 py-1 rounded">Add</button>
                    </div>
                @empty
                    <p class="text-slate-500 col-span-2">No available properties for selected dates.</p>
                @endforelse
            </div>
            @if(count($selectedProperties))
                <h3 class="font-medium mb-2">Selected Properties</h3>
                <table class="w-full text-sm">
                    <thead><tr class="text-slate-500"><th class="text-left">Property</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr></thead>
                    <tbody>
                        @foreach($selectedProperties as $id => $item)
                            @php 
                                $property = $this->availableProperties->firstWhere('id', $id) ?? App\Models\Property::find($id);
                                $nights = Carbon::parse($check_in)->diffInDays($check_out);
                                $nights = max(1, $nights);
                            @endphp
                            <tr>
                                <td>{{ $property->name ?? 'Property' }}</td>
                                <td>₱{{ number_format($item['price'], 2) }}</td>
                                <td><input type="number" wire:model.live="selectedProperties.{{ $id }}.quantity" min="1" class="w-16 border rounded text-center"></td>
                                <td>₱{{ number_format($item['price'] * $item['quantity'] * $nights, 2) }}</td>
                                <td><button type="button" wire:click="removeProperty({{ $id }})" class="text-red-500">&times;</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Service Selection --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Add Services (Optional)</h2>
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach($this->availableServices as $service)
                    <button type="button" wire:click="addService({{ $service->id }}, {{ $service->price }})" class="border rounded-full px-4 py-2 text-sm hover:bg-slate-50">
                        {{ $service->name }} (₱{{ number_format($service->price, 2) }})
                    </button>
                @endforeach
            </div>
            @if(count($selectedServices))
                <table class="w-full text-sm">
                    <thead><tr class="text-slate-500"><th>Service</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr></thead>
                    <tbody>
                        @foreach($selectedServices as $id => $item)
                            @php $service = App\Models\Service::find($id); @endphp
                            <tr>
                                <td>{{ $service->name }}</td>
                                <td>₱{{ number_format($item['price'], 2) }}</td>
                                <td><input type="number" wire:model.live="selectedServices.{{ $id }}.quantity" min="1" class="w-16 border rounded text-center"></td>
                                <td>₱{{ number_format($item['price'] * $item['quantity'], 2) }}</td>
                                <td><button type="button" wire:click="removeService({{ $id }})" class="text-red-500">&times;</button></td>
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
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">Create Booking</button>
                <a href="{{ route('tenant.bookings.index') }}" wire:navigate class="border px-6 py-2 rounded-lg hover:bg-slate-50">Cancel</a>
            </div>
        </div>
    </form>
</div>