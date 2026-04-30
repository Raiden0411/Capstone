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
#[Title('Book Now')]
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

    // Selected extra services (id → quantity)
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

        if (!$this->property->tenant) {
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

        // 🔒 Fetch the tenant ID directly from the database – foolproof
        $tenantId = Property::where('id', $this->property->id)->value('tenant_id');

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

<div class="max-w-3xl mx-auto py-8 px-4">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Complete Your Booking</h1>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-6">
        <h2 class="font-semibold text-lg mb-3">{{ $property->name }}</h2>
        <p class="text-slate-600">{{ $property->propertyType->name ?? 'Property' }} · {{ $property->tenant->name }}</p>
        <p class="text-blue-600 font-bold mt-2">₱{{ number_format($property->price, 2) }} / night</p>
    </div>

    <form wire:submit="submit" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-6">
        {{-- Booking identity (pre‑filled from account) --}}
        <div class="flex items-center justify-between text-sm text-slate-600 bg-slate-50 rounded-lg px-4 py-3 mb-2">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span>Booking as <strong>{{ Auth::user()->name }}</strong> ({{ Auth::user()->email }})</span>
            </div>
            <button type="button" class="text-blue-600 hover:underline text-xs" onclick="document.getElementById('guest-info-fields').classList.toggle('hidden')">Change</button>
        </div>

        {{-- Hidden fields (still stored so validation passes) --}}
        <div id="guest-info-fields" class="hidden space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Full Name</label>
                    <input type="text" wire:model="customerName" class="w-full rounded-lg border-slate-300">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input type="email" wire:model="customerEmail" class="w-full rounded-lg border-slate-300">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Phone</label>
                    <input type="text" wire:model="customerPhone" class="w-full rounded-lg border-slate-300">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Address</label>
                    <input type="text" wire:model="customerAddress" class="w-full rounded-lg border-slate-300">
                </div>
            </div>
        </div>

        {{-- Dates --}}
        <div>
            <h3 class="font-medium text-slate-800 mb-3">Stay Dates</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Check-in *</label>
                    <input type="date" wire:model.live="check_in" class="w-full rounded-lg border-slate-300">
                    @error('check_in') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Check-out *</label>
                    <input type="date" wire:model.live="check_out" class="w-full rounded-lg border-slate-300">
                    @error('check_out') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- Extra Services --}}
        @if($this->availableServices->isNotEmpty())
        <div>
            <h3 class="font-medium text-slate-800 mb-3">Add Extra Services</h3>
            <div class="flex flex-wrap gap-2 mb-3">
                @foreach($this->availableServices as $service)
                    <button type="button" wire:click="addService({{ $service->id }})"
                            class="border rounded-full px-4 py-2 text-sm hover:bg-slate-50 transition">
                        {{ $service->name }} (₱{{ number_format($service->price, 2) }})
                    </button>
                @endforeach
            </div>

            @if(count($selectedServices))
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-slate-500 border-b">
                            <th class="text-left py-2">Service</th>
                            <th class="text-center">Qty</th>
                            <th class="text-right">Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($selectedServices as $serviceId => $qty)
                            @php $svc = App\Models\Service::find($serviceId); @endphp
                            <tr class="border-b">
                                <td class="py-2">{{ $svc->name }}</td>
                                <td class="text-center">{{ $qty }}</td>
                                <td class="text-right">₱{{ number_format($svc->price * $qty, 2) }}</td>
                                <td class="text-center">
                                    <button type="button" wire:click="removeService({{ $serviceId }})" class="text-red-500">&times;</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
        @endif

        {{-- Payment Method --}}
        <div>
            <h3 class="font-medium text-slate-800 mb-3">Payment Method</h3>
            <select wire:model.live="payment_method" class="w-full md:w-1/2 rounded-lg border-slate-300">
                <option value="cash">Cash (on arrival)</option>
                <option value="gcash">GCash</option>
                <option value="paymaya">PayMaya</option>
                <option value="card">Credit/Debit Card</option>
            </select>
            @if($payment_method === 'cash')
                <div class="mt-3">
                    <label class="block text-sm font-medium mb-1">Reference Number (Optional)</label>
                    <input type="text" wire:model="reference_number" class="w-full md:w-1/2 rounded-lg border-slate-300" placeholder="e.g. Receipt #">
                </div>
            @endif
        </div>

        {{-- Total & Submit --}}
        <div class="border-t pt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div>
                <span class="text-xs text-slate-500">{{ $totalNights }} night{{ $totalNights > 1 ? 's' : '' }}</span>
                <div class="text-2xl font-bold text-slate-900">₱{{ number_format($totalAmount, 2) }}</div>
            </div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg shadow-sm transition w-full sm:w-auto">
                {{ $payment_method === 'cash' ? 'Confirm Booking' : 'Proceed to Pay' }}
            </button>
        </div>
    </form>
</div>