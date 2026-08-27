{{-- resources/views/tenant/pages/booking/⚡edit-booking.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Booking;
use App\Models\User;
use App\Models\Property;
use App\Models\Service;
use App\Models\BookingItem;
use App\Models\BookingService;
use App\Models\Payment;
use App\Models\ServiceAvailability;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new
#[Layout('tenant.layouts.app')]
#[Title('Edit Booking')]
class extends Component
{
    public Booking $booking;

    public $user_id = '';
    public $customerName = '';
    public $customerPhone = '';
    public $customerEmail = '';
    public $check_in;
    public $check_out;
    public $booking_reference;
    public $status;
    public $booking_type = 'full';
    public $selectedProperties = [];
    public $selectedServices = [];
    public $totalAmount = 0;
    public $discountAmount = 0;
    public $finalTotal = 0;
    public $reservationFee = 0;
    public $balanceOnArrival = 0;
    public $guestSearch = '';
    public $guestResults = [];
    public $showGuestDropdown = false;
    public $originalTotalAmount = 0;
    public $originalPaidAmount = 0;

    protected function rules()
    {
        return [
            'customerName'    => ['required', 'string', 'max:255'],
            'customerPhone'   => ['required', 'string', 'max:20', 'regex:/^(09|\+639)\d{9}$/'],
            'customerEmail'   => ['nullable', 'email', 'max:255'],
            'check_in'        => ['required', 'date'],
            'check_out'       => ['required', 'date', 'after:check_in'],
            'status'          => ['required', 'in:pending,reserved,confirmed,checked_in,completed,cancelled'],
            'booking_type'    => ['required', 'in:full,reservation'],
        ];
    }

    public function mount($booking)
    {
        if (!$booking instanceof Booking) {
            $booking = Booking::withoutGlobalScope(TenantScope::class)->findOrFail($booking);
        }

        if ($booking->tenant_id !== Auth::user()->tenant_id) {
            abort(403, 'Unauthorized.');
        }

        $this->booking = $booking;
        $this->user_id = (string) $booking->user_id;
        $this->customerName = $booking->user->name ?? '';
        $this->customerPhone = $booking->user->phone ?? '';
        $this->customerEmail = $booking->user->email ?? '';

        $this->check_in = $booking->check_in ? Carbon::parse($booking->check_in)->toDateString() : now()->toDateString();
        $this->check_out = $booking->check_out ? Carbon::parse($booking->check_out)->toDateString() : now()->addDay()->toDateString();

        $this->booking_reference = $booking->booking_reference;
        $this->status = $booking->status;
        $this->booking_type = $booking->booking_type ?? Booking::TYPE_FULL;
        $this->discountAmount = 0;

        foreach ($booking->items as $item) {
            $this->selectedProperties[$item->property_id] = [
                'quantity' => $item->quantity,
                'price'    => $item->price,
                'id'       => $item->id,
            ];
        }

        foreach ($booking->services as $service) {
            $this->selectedServices[$service->service_id] = [
                'quantity' => $service->quantity,
                'price'    => $service->service->price ?? 0,
                'id'       => $service->id,
            ];
        }

        $this->calculateTotal();

        $this->originalTotalAmount = $booking->total_amount;
        $this->originalPaidAmount = $booking->payments()->where('payment_status', 'paid')->sum('amount');
    }

    public function updatedUser($value)
    {
        if ($value) {
            $user = User::where('tenant_id', Auth::user()->tenant_id)->find($value);
            if ($user) {
                $this->customerName = $user->name;
                $this->customerPhone = $user->phone ?? '';
                $this->customerEmail = $user->email ?? '';
                $this->guestSearch = $user->name;
                $this->showGuestDropdown = false;
                $this->guestResults = [];
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

        $this->user_id = (string) $user->id;
        $this->customerName = $user->name;
        $this->customerPhone = $user->phone ?? '';
        $this->customerEmail = $user->email ?? '';
        $this->guestSearch = $user->name;
        $this->showGuestDropdown = false;
        $this->guestResults = [];
    }

    public function updatedCheckIn() { $this->recalculateAndWarn(); }
    public function updatedCheckOut() { $this->recalculateAndWarn(); }
    public function updatedBookingType() { $this->calculateTotal(); }
    public function updatedDiscountAmount() { $this->calculateTotal(); }

    public function recalculateAndWarn()
    {
        $available = $this->getAvailablePropertiesProperty();
        foreach ($this->selectedProperties as $id => $item) {
            if (!$available->contains('id', $id)) {
                unset($this->selectedProperties[$id]);
                session()->flash('error', "Selected activity was removed because it is not available for the new dates.");
            }
        }
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
        $days = Carbon::parse($this->check_in)->diffInDays(Carbon::parse($this->check_out));
        $days = max(1, $days);

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

    public function getUsersProperty()
    {
        return User::where('tenant_id', Auth::user()->tenant_id)
            ->orderBy('name')
            ->get();
    }

    public function getAvailablePropertiesProperty()
    {
        if (!$this->check_in || !$this->check_out) {
            return collect();
        }

        $checkIn = Carbon::parse($this->check_in)->toDateString();
        $checkOut = Carbon::parse($this->check_out)->toDateString();
        $bookingId = $this->booking->id;

        $properties = Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('is_active', true)
            ->with('images')
            ->orderBy('name')
            ->get();

        $available = $properties->filter(function ($property) use ($checkIn, $checkOut, $bookingId) {
            if (isset($this->selectedProperties[$property->id])) {
                return true;
            }

            $hasConflict = BookingItem::where('property_id', $property->id)
                ->whereHas('booking', function ($query) use ($checkIn, $checkOut, $bookingId) {
                    $query->withoutGlobalScope(TenantScope::class)
                        ->where('tenant_id', Auth::user()->tenant_id)
                        ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
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
        if (!$this->check_in || !$this->check_out) {
            return collect();
        }

        $services = Service::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $available = $services->filter(function ($service) {
            $days = Carbon::parse($this->check_in)->diffInDays(Carbon::parse($this->check_out));
            $days = max(1, $days);
            $dates = [];
            for ($i = 0; $i < $days; $i++) {
                $dates[] = Carbon::parse($this->check_in)->addDays($i)->toDateString();
            }

            $availabilities = ServiceAvailability::where('service_id', $service->id)
                ->whereIn('date', $dates)
                ->get();

            return !$availabilities->contains('is_available', false);
        });

        return $available->values();
    }

    public function getAllowedStatuses(): array
    {
        $current = $this->booking->status;
        if (in_array($current, [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED])) {
            return [];
        }

        return match ($current) {
            Booking::STATUS_PENDING    => [Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED],
            Booking::STATUS_RESERVED   => [Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED],
            Booking::STATUS_CONFIRMED  => [Booking::STATUS_CHECKED_IN, Booking::STATUS_CANCELLED],
            Booking::STATUS_CHECKED_IN => [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED],
            default                    => [],
        };
    }

    public function update()
    {
        if (empty($this->selectedProperties)) {
            session()->flash('error', 'Please select at least one activity.');
            return;
        }

        $this->validate();

        $allowed = $this->getAllowedStatuses();
        if (!in_array($this->status, $allowed) && $this->status !== $this->booking->status) {
            session()->flash('error', "Cannot change status from '{$this->booking->status}' to '{$this->status}'.");
            return;
        }

        DB::transaction(function () {
            $user = User::where('tenant_id', Auth::user()->tenant_id)->find($this->user_id);
            if ($user) {
                $user->update([
                    'name'  => $this->customerName,
                    'phone' => $this->customerPhone,
                    'email' => $this->customerEmail,
                ]);
            }

            $oldStatus = $this->booking->status;

            $this->booking->update([
                'user_id'      => $this->user_id,
                'check_in'     => $this->check_in,
                'check_out'    => $this->check_out,
                'status'       => $this->status,
                'booking_type' => $this->booking_type,
                'total_amount' => $this->finalTotal,
            ]);

            $days = Carbon::parse($this->check_in)->diffInDays(Carbon::parse($this->check_out));
            $days = max(1, $days);

            // Sync booking items
            $existingItemIds = $this->booking->items->pluck('id')->toArray();
            foreach ($this->selectedProperties as $propertyId => $item) {
                $subtotal = $item['price'] * $item['quantity'] * $days;
                if (isset($item['id'])) {
                    BookingItem::where('id', $item['id'])->update([
                        'quantity' => $item['quantity'],
                        'subtotal' => $subtotal,
                    ]);
                    $existingItemIds = array_diff($existingItemIds, [$item['id']]);
                } else {
                    BookingItem::create([
                        'tenant_id'   => Auth::user()->tenant_id,
                        'booking_id'  => $this->booking->id,
                        'property_id' => $propertyId,
                        'price'       => $item['price'],
                        'quantity'    => $item['quantity'],
                        'subtotal'    => $subtotal,
                    ]);
                }
            }
            BookingItem::whereIn('id', $existingItemIds)->delete();

            // Sync services
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
                        'tenant_id'  => Auth::user()->tenant_id,
                        'booking_id' => $this->booking->id,
                        'service_id' => $serviceId,
                        'quantity'   => $item['quantity'],
                        'subtotal'   => $subtotal,
                    ]);
                }
            }
            BookingService::whereIn('id', $existingServiceIds)->delete();

            if (in_array($this->status, [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED])
                && !in_array($oldStatus, [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED])) {
                $propertyIds = $this->booking->items()->pluck('property_id')->unique()->toArray();
                if ($propertyIds) {
                    Property::whereIn('id', $propertyIds)->update(['status' => 'available']);
                }
            }
        });

        session()->flash('message', 'Booking updated successfully.');
        return $this->redirectRoute('tenant.bookings.show', ['booking' => $this->booking->id], navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Edit Booking</h1>
        </div>
        <a href="{{ route('tenant.bookings.show', $booking->id) }}" wire:navigate
           class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-[#376df1] dark:hover:text-blue-400 transition-colors">
            &larr; Back to Booking
        </a>
    </div>

    @if (session()->has('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md text-sm text-red-700 dark:text-red-300 font-medium">
            {{ session('error') }}
        </div>
    @endif

    <form wire:submit="update" class="space-y-6">

        {{-- Guest Information --}}
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
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name *</label>
                    <input type="text" wire:model="customerName"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition">
                    @error('customerName') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone *</label>
                    <input type="text" wire:model="customerPhone"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition">
                    @error('customerPhone') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                    <input type="email" wire:model="customerEmail"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition">
                    @error('customerEmail') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- Booking Details --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Booking Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Check-in *</label>
                    <input type="date" wire:model.live="check_in"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition">
                    @error('check_in') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Check-out *</label>
                    <input type="date" wire:model.live="check_out"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition">
                    @error('check_out') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status *</label>
                    <select wire:model="status"
                            class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition appearance-none">
                        <option value="pending">Pending</option>
                        <option value="reserved">Reserved</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="checked_in">Checked In</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    @error('status') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Booking Type</label>
                    <select wire:model="booking_type"
                            class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition appearance-none">
                        <option value="full">Book Now (Full Payment)</option>
                        <option value="reservation">Reserve (20% Fee)</option>
                    </select>
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
                            $days = Carbon::parse($check_in)->diffInDays(Carbon::parse($check_out));
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

        {{-- Add-On Services --}}
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

        {{-- Payment Summary & Discount --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Payment Summary</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Discount / Override Amount (₱)</label>
                    <input type="number" min="0" step="0.01" wire:model.live="discountAmount"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition">
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Original Total</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">₱{{ number_format($totalAmount, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Final Total</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">₱{{ number_format($finalTotal, 2) }}</p>
                </div>
            </div>

            @if($booking_type === 'reservation')
                <div class="mt-3 grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-[#376df1] dark:text-blue-400">Reservation Fee (20%)</p>
                        <p class="text-lg font-semibold text-[#376df1] dark:text-blue-400">₱{{ number_format($reservationFee, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-amber-600 dark:text-amber-400">Balance on Arrival</p>
                        <p class="text-lg font-semibold text-amber-600 dark:text-amber-400">₱{{ number_format($balanceOnArrival, 2) }}</p>
                    </div>
                </div>
            @endif

            @if($originalPaidAmount > $finalTotal)
                <div class="mt-4 p-4 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-300 text-sm">
                    ⚠ Existing paid amount (₱{{ number_format($originalPaidAmount, 2) }}) exceeds the new total. Adjust payments manually.
                </div>
            @endif

            @php
                $paid = $booking->payments()->where('payment_status','paid')->sum('amount');
                $balance = $finalTotal - $paid;
            @endphp
            <div class="mt-4 flex justify-between text-sm">
                <span class="text-gray-500 dark:text-gray-400">Paid</span>
                <span class="text-gray-900 dark:text-white">₱{{ number_format($paid, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-500 dark:text-gray-400">Balance</span>
                <span class="{{ $balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">{{ $balance > 0 ? '₱'.number_format($balance,2) : 'Settled ✓' }}</span>
            </div>
            @if($balance > 0)
                <a href="{{ route('tenant.payments.create', ['booking' => $booking->id]) }}" wire:navigate
                   class="mt-3 inline-flex items-center gap-2 px-4 py-2 rounded-full bg-[#376df1] hover:bg-blue-700 text-white text-sm font-semibold transition">
                    Record Payment
                </a>
            @endif
        </div>

        {{-- Actions --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <button type="submit" wire:loading.attr="disabled"
                    class="bg-[#376df1] hover:bg-blue-700 text-white font-semibold py-3 px-8 rounded-full shadow-lg shadow-blue-500/20 transition hover:scale-105 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove>Update Booking</span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Saving...
                </span>
            </button>
            <a href="{{ route('tenant.bookings.show', $booking->id) }}" wire:navigate
               class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 px-6 py-3 rounded-full font-medium transition text-center">
                Cancel
            </a>
        </div>
    </form>
</div>