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
use App\Models\ServiceAvailability;
use App\Services\PayMongoService;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

new 
#[Layout('tenant.layouts.app')]
#[Title('New Booking')]
class extends Component {
    #[Validate('required|string|max:255')]
    public $customerName = '';
    
    public $customerPhone = '';
    public $customerEmail = '';

    #[Validate('required|date|after_or_equal:today')]
    public $check_in;
    #[Validate('required|date|after:check_in')]
    public $check_out;

    public $booking_reference;
    public $totalAmount = 0;
    public $discountAmount = 0;
    public $finalTotal = 0;
    public $reservationFee = 0;
    public $balanceOnArrival = 0;
    public $selectedProperties = [];
    public $selectedServices = [];
    public $bookingType = 'full';
    #[Validate('required|in:cash,gcash,paymaya,card')]
    public $payment_method = 'cash';
    public $cashAmountReceived = 0;
    public $createdBookingId = null;
    public $guestSearch = '';
    public $selectedGuestId = null;
    public $guestResults = [];
    public $showGuestDropdown = false;
    public $sendEmailConfirmation = true;

    protected function rules()
    {
        return [
            'customerPhone' => [
                'required',
                'string',
                'max:20',
                'regex:/^(09|\+639)\d{9}$/',
            ],
            'discountAmount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    public function mount()
    {
        $this->check_in = now()->format('Y-m-d');
        $this->check_out = now()->addDay()->format('Y-m-d');
        $this->generateBookingReference();
        $this->calculateTotal();
        $this->updateCashAmount();
    }

    public function updated($field)
    {
        $trimFields = ['customerName', 'customerPhone', 'customerEmail', 'guestSearch'];
        if (in_array($field, $trimFields)) {
            $this->$field = trim($this->$field);
            if ($field === 'customerPhone') {
                $this->customerPhone = preg_replace('/[^0-9+]/', '', $this->customerPhone);
            }
        }
        if ($field === 'guestSearch') {
            $this->searchGuests();
        }
        if (in_array($field, ['check_in', 'check_out'])) {
            $this->warnDateConflicts();
        }
        if (in_array($field, ['bookingType', 'discountAmount', 'payment_method'])) {
            $this->calculateTotal();
            if ($field === 'payment_method' && $this->payment_method === 'cash') {
                $this->updateCashAmount();
            }
        }
    }

    public function searchGuests()
    {
        if (strlen($this->guestSearch) < 2) {
            $this->guestResults = [];
            $this->showGuestDropdown = false;
            return;
        }
        $this->guestResults = User::where('tenant_id', Auth::user()->tenant_id)
            ->where(function ($q) {
                $q->where('name', 'like', '%'.$this->guestSearch.'%')
                  ->orWhere('email', 'like', '%'.$this->guestSearch.'%')
                  ->orWhere('phone', 'like', '%'.$this->guestSearch.'%');
            })
            ->limit(6)
            ->get();
        $this->showGuestDropdown = $this->guestResults->isNotEmpty();
    }

    public function selectGuest($userId)
    {
        $user = User::where('tenant_id', Auth::user()->tenant_id)->find($userId);
        if (!$user) return;
        $this->selectedGuestId = $user->id;
        $this->customerName = $user->name;
        $this->customerPhone = $user->phone ?? '';
        $this->customerEmail = $user->email ?? '';
        $this->guestSearch = $user->name;
        $this->showGuestDropdown = false;
        $this->guestResults = [];
    }

    public function clearSelectedGuest()
    {
        $this->selectedGuestId = null;
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerEmail = '';
        $this->guestSearch = '';
        $this->showGuestDropdown = false;
        $this->guestResults = [];
    }

    public function generateBookingReference()
    {
        $this->booking_reference = 'BK-' . strtoupper(Str::random(8));
    }

    public function updatedCheckIn() { /* handled in updated() */ }
    public function updatedCheckOut() { /* handled in updated() */ }

    public function warnDateConflicts()
    {
        $available = $this->getAvailablePropertiesProperty();
        foreach ($this->selectedProperties as $id => $item) {
            if (!$available->contains('id', $id)) {
                unset($this->selectedProperties[$id]);
                session()->flash('error', "Selected activity was removed because it is not available for the new dates.");
                $this->calculateTotal();
                $this->updateCashAmount();
            }
        }
    }

    public function toggleProperty($propertyId, $price)
    {
        if (isset($this->selectedProperties[$propertyId])) {
            unset($this->selectedProperties[$propertyId]);
        } else {
            $this->selectedProperties[$propertyId] = ['quantity' => 1, 'price' => $price];
        }
        $this->calculateTotal();
        $this->updateCashAmount();
    }

    public function toggleService($serviceId, $price)
    {
        if (isset($this->selectedServices[$serviceId])) {
            unset($this->selectedServices[$serviceId]);
        } else {
            $this->selectedServices[$serviceId] = ['quantity' => 1, 'price' => $price];
        }
        $this->calculateTotal();
        $this->updateCashAmount();
    }

    public function calculateTotal()
    {
        $total = 0;
        $days = 1;
        if ($this->check_in && $this->check_out) {
            $days = Carbon::parse($this->check_in)->diffInDays(Carbon::parse($this->check_out));
            $days = max(1, $days);
        }
        foreach ($this->selectedProperties as $item) {
            $total += $item['price'] * $item['quantity'] * $days;
        }
        foreach ($this->selectedServices as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        $this->totalAmount = $total;
        $this->finalTotal = max(0, $total - (float) $this->discountAmount);
        $this->reservationFee = round($this->finalTotal * 0.20, 2);
        $this->balanceOnArrival = round($this->finalTotal - $this->reservationFee, 2);
    }

    public function updateCashAmount()
    {
        if ($this->payment_method === 'cash') {
            $this->cashAmountReceived = $this->bookingType === 'reservation'
                ? $this->reservationFee
                : $this->finalTotal;
        } else {
            $this->cashAmountReceived = 0;
        }
    }

    public function getAvailablePropertiesProperty()
    {
        if (!$this->check_in || !$this->check_out) return collect();

        $checkIn = $this->check_in;
        $checkOut = $this->check_out;

        $properties = Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('is_active', true)
            ->where('status', 'available')
            ->with('images')
            ->orderBy('name')
            ->get();

        $available = $properties->filter(function ($property) use ($checkIn, $checkOut) {
            $hasConflict = BookingItem::where('property_id', $property->id)
                ->whereHas('booking', function ($query) use ($checkIn, $checkOut) {
                    $query->withoutGlobalScope(TenantScope::class)
                        ->where('tenant_id', Auth::user()->tenant_id)
                        ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
                        ->where('check_in', '<', $checkOut)
                        ->where('check_out', '>', $checkIn);
                })->exists();
            return !$hasConflict;
        });

        return $available->values();
    }

    public function getAvailableServicesProperty()
    {
        if (!$this->check_in || !$this->check_out) return collect();

        $services = Service::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $available = $services->filter(function ($service) {
            $days = Carbon::parse($this->check_in)->diffInDays($this->check_out);
            $days = max(1, $days);
            $dates = collect();
            for ($i = 0; $i < $days; $i++) {
                $dates->push(Carbon::parse($this->check_in)->addDays($i)->toDateString());
            }
            $availabilities = ServiceAvailability::where('service_id', $service->id)
                ->whereIn('date', $dates->toArray())
                ->get();
            return !$availabilities->contains('is_available', false);
        });

        return $available->values();
    }

    public function submit()
    {
        if (empty($this->selectedProperties)) {
            session()->flash('error', 'Please select at least one activity.');
            return;
        }

        $this->validate();

        if ($this->booking_reference && Booking::where('booking_reference', $this->booking_reference)->exists()) {
            $this->generateBookingReference();
        }

        $this->createdBookingId = DB::transaction(function () {
            $tenantId = Auth::user()->tenant_id;

            if ($this->selectedGuestId) {
                $user = User::where('tenant_id', $tenantId)->find($this->selectedGuestId);
            } else {
                $user = User::firstOrCreate(
                    ['email' => $this->customerEmail ?: ('guest_' . Str::random(8) . '@reservation.local')],
                    [
                        'tenant_id' => $tenantId,
                        'name'      => $this->customerName,
                        'phone'     => $this->customerPhone,
                        'password'  => bcrypt(Str::random(16)),
                        'is_active' => true,
                    ]
                );

                if (!$user->wasRecentlyCreated) {
                    $user->update([
                        'name'  => $this->customerName,
                        'phone' => $this->customerPhone,
                    ]);
                }
            }

            $days = Carbon::parse($this->check_in)->diffInDays($this->check_out);
            $days = max(1, $days);

            $booking = Booking::create([
                'tenant_id'         => $tenantId,
                'user_id'           => $user->id,
                'booking_reference' => $this->booking_reference,
                'check_in'          => $this->check_in,
                'check_out'         => $this->check_out,
                'total_amount'      => $this->finalTotal,
                'status'            => Booking::STATUS_PENDING,
                'booking_type'      => $this->bookingType,
            ]);

            foreach ($this->selectedProperties as $propertyId => $item) {
                BookingItem::create([
                    'tenant_id'   => $tenantId,
                    'booking_id'  => $booking->id,
                    'property_id' => $propertyId,
                    'price'       => $item['price'],
                    'quantity'    => $item['quantity'],
                    'subtotal'    => $item['price'] * $item['quantity'] * $days,
                ]);
            }

            foreach ($this->selectedServices as $serviceId => $item) {
                BookingService::create([
                    'tenant_id'  => $tenantId,
                    'booking_id' => $booking->id,
                    'service_id' => $serviceId,
                    'quantity'   => $item['quantity'],
                    'subtotal'   => $item['price'] * $item['quantity'],
                ]);
            }

            if ($this->payment_method === 'cash') {
                $amount = (float) $this->cashAmountReceived;
                if ($amount <= 0) {
                    $amount = $this->bookingType === 'reservation' ? $this->reservationFee : $this->finalTotal;
                }
                Payment::create([
                    'tenant_id'      => $tenantId,
                    'booking_id'     => $booking->id,
                    'amount'         => $amount,
                    'payment_method' => 'cash',
                    'payment_status' => 'paid',
                    'payment_type'   => $this->bookingType,
                    'paid_at'        => now(),
                ]);
                $booking->update(['status' => Booking::STATUS_CONFIRMED]);
            }

            return $booking->id;
        });

        if ($this->payment_method === 'cash') {
            session()->flash('message', 'Reservation created successfully. ' 
                . ($this->bookingType === 'reservation' ? 'Balance due on arrival.' : 'Payment recorded.'));
            return $this->redirectRoute('tenant.bookings.show', ['booking' => $this->createdBookingId], navigate: true);
        } else {
            return $this->initiateOnlinePayment();
        }
    }

    protected function initiateOnlinePayment()
    {
        $booking = Booking::withoutGlobalScope(TenantScope::class)
            ->where('id', $this->createdBookingId)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->firstOrFail();

        $user = $booking->user;
        $chargeAmount = $this->bookingType === 'reservation' ? $this->reservationFee : $this->finalTotal;

        $payMongo = app(PayMongoService::class);
        $session = $payMongo->createCheckoutSession([
            'customer_name'   => $user->name,
            'customer_email'  => $user->email ?? 'guest@example.com',
            'customer_phone'  => $user->phone,
            'amount'          => $chargeAmount,
            'description'     => "Booking #{$booking->booking_reference}",
            'item_name'       => $this->bookingType === 'reservation' ? 'Reservation Fee' : 'Activity Booking',
            'success_url'     => route('tenant.payments.success', ['booking' => $booking->id]),
            'cancel_url'      => route('tenant.payments.cancel', ['booking' => $booking->id]),
            'metadata'        => ['booking_id' => $booking->id, 'tenant_id' => Auth::user()->tenant_id],
            'payment_method_types' => ['gcash', 'paymaya', 'card'],
        ]);

        if (!$session) {
            session()->flash('error', 'Unable to initiate payment. Please try again.');
            return $this->redirectRoute('tenant.bookings.show', ['booking' => $booking->id], navigate: true);
        }

        Payment::create([
            'tenant_id'           => Auth::user()->tenant_id,
            'booking_id'          => $booking->id,
            'amount'              => $chargeAmount,
            'payment_method'      => $this->payment_method,
            'payment_status'      => 'unpaid',
            'payment_type'        => $this->bookingType,
            'paymongo_session_id' => $session['id'],
        ]);

        return redirect()->away($session['checkout_url']);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">New Booking</h1>
        </div>
        <a href="{{ route('tenant.bookings.index') }}" wire:navigate
           class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-[#376df1] dark:hover:text-blue-400 transition-colors">
            &larr; Back to Bookings
        </a>
    </div>

    {{-- Flash Error --}}
    @if (session()->has('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md text-sm text-red-700 dark:text-red-300 font-medium">
            {{ session('error') }}
        </div>
    @endif

    <form wire:submit="submit" class="space-y-6">

        {{-- Guest Info --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Guest Information</h2>
            <div class="relative">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search existing guest</label>
                <input type="text" wire:model.live="guestSearch" placeholder="Type name, email or phone…"
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition">
                @if($showGuestDropdown)
                    <div class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl max-h-48 overflow-y-auto">
                        @foreach($guestResults as $guest)
                            <button type="button" wire:click="selectGuest({{ $guest->id }})"
                                    class="w-full text-left px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                <p class="text-sm text-gray-900 dark:text-white">{{ $guest->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $guest->email ?? $guest->phone }}</p>
                            </button>
                        @endforeach
                    </div>
                @endif
                @if($selectedGuestId)
                    <button type="button" wire:click="clearSelectedGuest" class="mt-2 text-xs text-[#376df1] hover:text-blue-700">Clear selected guest</button>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name *</label>
                    <input type="text" wire:model="customerName"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition"
                           placeholder="Guest name">
                    @error('customerName') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone *</label>
                    <input type="text" wire:model="customerPhone"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition"
                           placeholder="09123456789">
                    @error('customerPhone') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                    <input type="email" wire:model="customerEmail"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition"
                           placeholder="Email address">
                </div>
            </div>
        </div>

        {{-- Dates & Reference --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Visit Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Check-in *</label>
                    <input type="date" wire:model.live="check_in"
                           min="{{ now()->format('Y-m-d') }}"
                           max="{{ now()->addDays(30)->format('Y-m-d') }}"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition">
                    @error('check_in') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Check-out *</label>
                    <input type="date" wire:model.live="check_out"
                           min="{{ now()->addDay()->format('Y-m-d') }}"
                           max="{{ now()->addDays(30)->format('Y-m-d') }}"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition">
                    @error('check_out') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Booking Ref</label>
                    <input type="text" wire:model="booking_reference"
                           class="w-full bg-gray-100 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-500 dark:text-gray-400 cursor-not-allowed"
                           readonly>
                    <button type="button" wire:click="generateBookingReference" class="text-xs text-[#376df1] hover:text-blue-700 mt-1">Generate New</button>
                </div>
            </div>
        </div>

        {{-- Activity Selection --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Select Activities</h2>

            @if(count($this->availableProperties) > 0)
                <div class="flex gap-4 overflow-x-auto pb-4 lg:grid lg:grid-cols-3 lg:gap-6" style="scrollbar-width: thin;">
                    @foreach($this->availableProperties as $property)
                        @php
                            $isSelected = isset($selectedProperties[$property->id]);
                            $firstImg = $property->images->first();
                        @endphp
                        <div class="relative group cursor-pointer rounded-2xl border-2 transition-all duration-200 overflow-hidden min-w-[220px] lg:min-w-0
                                    {{ $isSelected ? 'border-[#376df1] ring-2 ring-blue-500/30' : 'border-gray-200 dark:border-gray-700 hover:border-blue-400/50' }}"
                             wire:click="toggleProperty({{ $property->id }}, {{ $property->price }})">
                            <div class="aspect-[4/3] overflow-hidden rounded-t-2xl">
                                <img class="w-full h-full object-cover group-hover:scale-105 transition duration-700"
                                     src="{{ $firstImg ? asset('storage/'. $firstImg->image_path) : asset('images/placeholder.jpg') }}"
                                     alt="{{ $property->name }}">
                            </div>
                            <div class="p-4 bg-white dark:bg-gray-800">
                                <h3 class="font-medium text-gray-900 dark:text-white">{{ $property->name }}</h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">₱{{ number_format($property->price, 2) }} / day</p>
                            </div>
                            @if($isSelected)
                                <div class="absolute top-3 right-3 bg-[#376df1] text-white rounded-full p-1 shadow">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 dark:text-gray-400 text-center py-8">No available activities for selected dates.</p>
            @endif

            @if(count($selectedProperties) > 0)
                <div class="mt-8 space-y-3">
                    <h3 class="font-medium text-gray-900 dark:text-white mb-4">Selected Activities</h3>
                    @foreach($selectedProperties as $id => $item)
                        @php
                            $prop = $this->availableProperties->firstWhere('id', $id) ?? App\Models\Property::withoutGlobalScope(TenantScope::class)->find($id);
                            $days = Carbon::parse($check_in)->diffInDays($check_out);
                            $days = max(1, $days);
                            $subtotal = $item['price'] * $item['quantity'] * $days;
                        @endphp
                        <div class="flex items-center gap-4 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700">
                            <div class="flex-1">
                                <p class="font-medium text-gray-900 dark:text-white truncate">{{ $prop->name ?? 'Activity' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">₱{{ number_format($item['price'], 2) }} / day · {{ $days }} day(s)</p>
                            </div>
                            <p class="font-semibold text-gray-900 dark:text-white">₱{{ number_format($subtotal, 2) }}</p>
                            <button type="button" wire:click="toggleProperty({{ $id }}, {{ $item['price'] }})" class="p-1 text-gray-400 dark:text-gray-500 hover:text-red-600 dark:hover:text-red-400 transition">✕</button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Services --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Add-On Services</h2>
            @if($this->availableServices->isNotEmpty())
                <div class="flex gap-2 flex-wrap mb-4">
                    @foreach($this->availableServices as $service)
                        @php $isServiceSelected = isset($selectedServices[$service->id]); @endphp
                        <button type="button" wire:click="toggleService({{ $service->id }}, {{ $service->price }})"
                                class="border rounded-full px-4 py-2 text-sm transition-colors
                                       {{ $isServiceSelected ? 'bg-blue-50 dark:bg-blue-500/15 border-blue-200 dark:border-blue-500/30 text-[#376df1] dark:text-blue-400' : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
                            {{ $service->name }} (₱{{ number_format($service->price, 2) }})
                        </button>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 dark:text-gray-400">No services available for selected dates.</p>
            @endif

            @if(count($selectedServices) > 0)
                <div class="space-y-2">
                    @foreach($selectedServices as $id => $item)
                        @php $service = App\Models\Service::withoutGlobalScope(TenantScope::class)->find($id); @endphp
                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700">
                            <div class="flex-1">
                                <p class="font-medium text-gray-900 dark:text-white">{{ $service->name ?? 'Service' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">₱{{ number_format($item['price'], 2) }}</p>
                            </div>
                            <p class="font-semibold text-gray-900 dark:text-white">₱{{ number_format($item['price'], 2) }}</p>
                            <button type="button" wire:click="toggleService({{ $id }}, {{ $item['price'] }})" class="p-1 text-gray-400 dark:text-gray-500 hover:text-red-600 dark:hover:text-red-400">✕</button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Booking Type & Payment --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Booking Type & Payment</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Booking Type</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" wire:model.live="bookingType" value="full" class="sr-only peer">
                            <div class="border-2 border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center transition peer-checked:border-[#376df1] peer-checked:bg-blue-50 dark:peer-checked:bg-blue-500/10 hover:border-gray-300 dark:hover:border-gray-600">
                                <span class="text-2xl">📅</span>
                                <p class="text-gray-900 dark:text-white font-semibold text-sm mt-1">Book Now</p>
                                <p class="text-gray-500 dark:text-gray-400 text-xs">Pay 100%</p>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" wire:model.live="bookingType" value="reservation" class="sr-only peer">
                            <div class="border-2 border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center transition peer-checked:border-[#376df1] peer-checked:bg-blue-50 dark:peer-checked:bg-blue-500/10 hover:border-gray-300 dark:hover:border-gray-600">
                                <span class="text-2xl">🪙</span>
                                <p class="text-gray-900 dark:text-white font-semibold text-sm mt-1">Reserve</p>
                                <p class="text-gray-500 dark:text-gray-400 text-xs">Pay 20% fee</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Payment Method</label>
                    <div class="grid grid-cols-4 gap-2">
                        @foreach([
                            ['cash', 'Cash', '💵'],
                            ['gcash', 'GCash', '📱'],
                            ['paymaya', 'Maya', '💳'],
                            ['card', 'Card', '🏦'],
                        ] as [$val, $label, $icon])
                            <label class="cursor-pointer">
                                <input type="radio" wire:model.live="payment_method" value="{{ $val }}" class="sr-only peer">
                                <div class="border-2 border-gray-200 dark:border-gray-700 rounded-xl p-3 text-center transition peer-checked:border-[#376df1] peer-checked:bg-blue-50 dark:peer-checked:bg-blue-500/10 hover:border-gray-300 dark:hover:border-gray-600">
                                    <span class="text-xl">{{ $icon }}</span>
                                    <p class="text-gray-900 dark:text-white font-semibold text-xs mt-1">{{ $label }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Discount / Override Amount (₱)</label>
                    <input type="number" min="0" step="0.01" wire:model.live="discountAmount"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cash Amount Received (if cash)</label>
                    <input type="number" min="0" step="0.01" wire:model="cashAmountReceived"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notifications</label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model="sendEmailConfirmation" class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-[#376df1] focus:ring-[#376df1]">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Send email confirmation</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Summary & Actions --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Summary</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-gray-500 dark:text-gray-400">Total Amount</span><span class="text-gray-900 dark:text-white">₱{{ number_format($totalAmount, 2) }}</span></div>
                @if($discountAmount > 0)
                    <div class="flex justify-between text-red-600 dark:text-red-400"><span>Discount</span><span>-₱{{ number_format($discountAmount, 2) }}</span></div>
                @endif
                <div class="flex justify-between font-semibold"><span class="text-gray-700 dark:text-gray-300">Final Total</span><span class="text-gray-900 dark:text-white">₱{{ number_format($finalTotal, 2) }}</span></div>
                @if($bookingType === 'reservation')
                    <div class="flex justify-between text-[#376df1] dark:text-blue-400"><span>Reservation Fee (20%)</span><span>₱{{ number_format($reservationFee, 2) }}</span></div>
                    <div class="flex justify-between text-amber-600 dark:text-amber-400"><span>Balance on Arrival</span><span>₱{{ number_format($balanceOnArrival, 2) }}</span></div>
                @endif
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mt-6">
                <button type="submit" wire:loading.attr="disabled"
                        class="bg-[#376df1] hover:bg-blue-700 text-white font-semibold py-3 px-8 rounded-full shadow-lg shadow-blue-500/20 transition hover:scale-105 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove>
                        {{ $payment_method === 'cash' ? 'Complete Reservation' : 'Proceed to Pay' }}
                    </span>
                    <span wire:loading class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Processing...
                    </span>
                </button>
                <a href="{{ route('tenant.bookings.index') }}" wire:navigate
                   class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 px-6 py-3 rounded-full font-medium transition text-center">
                    Cancel
                </a>
            </div>
        </div>
    </form>
</div>