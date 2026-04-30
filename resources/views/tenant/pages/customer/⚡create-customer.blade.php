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
#[Title('New Reservation')]
class extends Component {
    // Customer fields
    #[Validate('required|string|max:255')]
    public $customerName = '';
    
    #[Validate('required|string|max:20')]
    public $customerPhone = '';
    
    public $customerEmail = '';
    public $customerAddress = '';
    public $customerNotes = '';

    // Booking dates
    #[Validate('required|date|after_or_equal:today')]
    public $check_in;
    
    #[Validate('required|date|after:check_in')]
    public $check_out;
    
    public $booking_reference;
    public $totalAmount = 0;
    public $selectedProperties = [];
    public $selectedServices = [];
    
    // Payment
    #[Validate('required|in:cash,card,gcash,paymaya')]
    public $payment_method = 'cash';
    
    public $reference_number = '';
    public $createdBookingId = null;

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

    public function getAvailablePropertiesProperty()
    {
        if (!$this->check_in || !$this->check_out) return collect();

        $checkIn = $this->check_in;
        $checkOut = $this->check_out;
        return Property::where('is_active', true)
            ->where('status', 'available')
            ->orderBy('name')
            ->get()
            ->filter(function ($property) use ($checkIn, $checkOut) {
                $hasConflict = BookingItem::where('property_id', $property->id)
                    ->whereHas('booking', function ($q) use ($checkIn, $checkOut) {
                        $q->whereNotIn('status', ['cancelled', 'completed'])
                            ->where('check_in', '<', $checkOut)
                            ->where('check_out', '>', $checkIn);
                    })->exists();
                return !$hasConflict;
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

        $this->validate([
            'customerName' => 'required|string|max:255',
            'customerPhone' => 'required|string|max:20',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'payment_method' => 'required|in:cash,card,gcash,paymaya',
        ]);

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
                'notes' => $this->customerNotes ?: null,
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
                    'reference_number' => $this->reference_number,
                    'paid_at' => now(),
                ]);
            }
        });

        if ($this->payment_method === 'cash') {
            session()->flash('message', 'Reservation created and payment recorded.');
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
            'metadata' => ['booking_id' => $booking->id, 'tenant_id' => Auth::user()->tenant_id],
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
<div class="p-6 max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">New Reservation</h1>

    @if (session()->has('error'))
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">{{ session('error') }}</div>
    @endif

    <form wire:submit="submit" class="space-y-6">
        {{-- Guest Information --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Guest Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Full Name *</label>
                    <input type="text" wire:model="customerName" class="w-full rounded-lg border-slate-300" placeholder="Guest name">
                    @error('customerName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Phone *</label>
                    <input type="text" wire:model="customerPhone" class="w-full rounded-lg border-slate-300" placeholder="Contact number">
                    @error('customerPhone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input type="email" wire:model="customerEmail" class="w-full rounded-lg border-slate-300" placeholder="Email address">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Address</label>
                    <input type="text" wire:model="customerAddress" class="w-full rounded-lg border-slate-300" placeholder="Residential address">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">Notes</label>
                    <textarea wire:model="customerNotes" rows="2" class="w-full rounded-lg border-slate-300" placeholder="Special requests..."></textarea>
                </div>
            </div>
        </div>

        {{-- Dates & Reference --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Stay Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Check‑in *</label>
                    <input type="date" wire:model.live="check_in" class="w-full rounded-lg border-slate-300">
                    @error('check_in') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Check‑out *</label>
                    <input type="date" wire:model.live="check_out" class="w-full rounded-lg border-slate-300">
                    @error('check_out') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Booking Ref</label>
                    <input type="text" wire:model="booking_reference" class="w-full rounded-lg border-slate-300 bg-slate-50" readonly>
                    <button type="button" wire:click="generateBookingReference" class="text-xs text-blue-600 mt-1">Generate New</button>
                </div>
            </div>
        </div>

        {{-- Property & Service Selection --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Select Room(s)</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                @forelse($this->availableProperties as $property)
                    <div class="border rounded-lg p-3 flex justify-between items-center">
                        <div>
                            <p class="font-medium">{{ $property->name }}</p>
                            <p class="text-sm text-slate-500">₱{{ number_format($property->price, 2) }} / night • Cap: {{ $property->capacity }}</p>
                        </div>
                        <button type="button" wire:click="addProperty({{ $property->id }}, {{ $property->price }})" class="text-blue-600 hover:bg-blue-50 px-3 py-1 rounded">Add</button>
                    </div>
                @empty
                    <p class="text-slate-500 col-span-2">No available rooms for selected dates.</p>
                @endforelse
            </div>
            @if(count($selectedProperties))
                <h3 class="font-medium mb-2">Selected Rooms</h3>
                <table class="w-full text-sm">
                    <thead><tr class="text-slate-500"><th class="text-left">Room</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr></thead>
                    <tbody>
                        @foreach($selectedProperties as $id => $item)
                            @php 
                                $property = $this->availableProperties->firstWhere('id', $id) ?? App\Models\Property::find($id);
                                $nights = Carbon::parse($check_in)->diffInDays($check_out);
                                $nights = max(1, $nights);
                            @endphp
                            <tr>
                                <td>{{ $property->name ?? 'Room' }}</td>
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

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Add‑On Services</h2>
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

        {{-- Payment --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-lg font-semibold mb-4">Payment</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Payment Method *</label>
                    <select wire:model.live="payment_method" class="w-full rounded-lg border-slate-300">
                        <option value="cash">Cash</option>
                        <option value="card">Credit/Debit Card</option>
                        <option value="gcash">GCash</option>
                        <option value="paymaya">PayMaya</option>
                    </select>
                    @error('payment_method') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                @if($payment_method === 'cash')
                <div>
                    <label class="block text-sm font-medium mb-1">Reference (Optional)</label>
                    <input type="text" wire:model="reference_number" class="w-full rounded-lg border-slate-300" placeholder="e.g. Receipt #">
                </div>
                @endif
            </div>
        </div>

        {{-- Total & Actions --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 flex justify-between items-center">
            <span class="text-xl font-bold">Total: ₱{{ number_format($totalAmount, 2) }}</span>
            <div class="flex gap-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    {{ $payment_method === 'cash' ? 'Complete Reservation' : 'Proceed to Pay' }}
                </button>
                <a href="{{ route('tenant.bookings.index') }}" wire:navigate class="border px-6 py-2 rounded-lg hover:bg-slate-50">Cancel</a>
            </div>
        </div>
    </form>
</div>