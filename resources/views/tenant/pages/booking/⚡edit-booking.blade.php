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
            'discountAmount'  => ['nullable', 'numeric', 'min:0'],
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

        // Eager load relations to prevent N+1 queries
        $booking->load([
            'user',
            'items.property',
            'services.service',
        ]);

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

        $this->discountAmount = $booking->total_amount < $this->calculateBaseTotal()
            ? $this->calculateBaseTotal() - $booking->total_amount
            : 0;

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

    private function getDurationInDays(): int
    {
        if (!$this->check_in || !$this->check_out) return 1;
        $days = Carbon::parse($this->check_in)->diffInDays(Carbon::parse($this->check_out));
        return max(1, $days);
    }

    private function calculateBaseTotal(): float
    {
        $total = 0;
        $days = $this->getDurationInDays();

        foreach ($this->selectedProperties as $item) {
            $total += $item['price'] * $item['quantity'] * $days;
        }
        foreach ($this->selectedServices as $item) {
            $total += $item['price'] * $item['quantity'];
        }

        return $total;
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

    public function closeGuestDropdown()
    {
        $this->showGuestDropdown = false;
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
        $this->totalAmount = $this->calculateBaseTotal();
        $discount = is_numeric($this->discountAmount) ? (float) $this->discountAmount : 0;

        $this->finalTotal = max(0, $this->totalAmount - $discount);
        $this->reservationFee = round($this->finalTotal * 0.20, 2);
        $this->balanceOnArrival = round($this->finalTotal - $this->reservationFee, 2);
    }

    public function getAvailablePropertiesProperty()
    {
        if (!$this->check_in || !$this->check_out) {
            return collect();
        }

        $checkIn = Carbon::parse($this->check_in)->toDateString();
        $checkOut = Carbon::parse($this->check_out)->toDateString();
        $bookingId = $this->booking->id;
        $tenantId = Auth::user()->tenant_id;

        $conflictingPropertyIds = BookingItem::whereHas('booking', function ($query) use ($checkIn, $checkOut, $bookingId, $tenantId) {
            $query->withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
                ->where('id', '!=', $bookingId)
                ->where('check_in', '<', $checkOut)
                ->where('check_out', '>', $checkIn);
        })->pluck('property_id')->toArray();

        $properties = Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with('images')
            ->orderBy('name')
            ->get();

        $available = $properties->filter(function ($property) use ($conflictingPropertyIds) {
            if (isset($this->selectedProperties[$property->id])) return true;
            return !in_array($property->id, $conflictingPropertyIds);
        });

        return $available->values();
    }

    public function getAvailableServicesProperty()
    {
        if (!$this->check_in || !$this->check_out) {
            return collect();
        }

        $days = $this->getDurationInDays();
        $dates = [];
        for ($i = 0; $i < $days; $i++) {
            $dates[] = Carbon::parse($this->check_in)->addDays($i)->toDateString();
        }

        $unavailableServiceIds = ServiceAvailability::whereIn('date', $dates)
            ->where('is_available', false)
            ->pluck('service_id')
            ->unique()
            ->toArray();

        $services = Service::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $available = $services->filter(function ($service) use ($unavailableServiceIds) {
            return !in_array($service->id, $unavailableServiceIds);
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
            if ($this->user_id) {
                $user = User::where('tenant_id', Auth::user()->tenant_id)->find($this->user_id);
                if ($user) {
                    $user->update([
                        'name'  => $this->customerName,
                        'phone' => $this->customerPhone,
                        'email' => $this->customerEmail,
                    ]);
                }
            }

            $oldStatus = $this->booking->status;

            $this->booking->update([
                'user_id'      => $this->user_id ?: null,
                'check_in'     => $this->check_in,
                'check_out'    => $this->check_out,
                'status'       => $this->status,
                'booking_type' => $this->booking_type,
                'total_amount' => $this->finalTotal,
            ]);

            $days = $this->getDurationInDays();

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
            if (!empty($existingItemIds)) {
                BookingItem::whereIn('id', $existingItemIds)->delete();
            }

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
            if (!empty($existingServiceIds)) {
                BookingService::whereIn('id', $existingServiceIds)->delete();
            }

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
           class="btn-secondary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to View Booking
        </a>
    </div>

    @if (session()->has('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md text-sm text-red-700 dark:text-red-300 font-medium">
            {{ session('error') }}
        </div>
    @endif

    <form wire:submit="update" class="space-y-6">

        {{-- Guest Information --}}
        <div class="card p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Guest Information</h2>

            <div class="relative" wire:click.outside="closeGuestDropdown">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search existing guest</label>
                <input type="text" wire:model.live.debounce.300ms="guestSearch" placeholder="Type name, email or phone…"
                       class="input">

                @if($showGuestDropdown)
                    <div class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl max-h-48 overflow-y-auto">
                        @forelse($guestResults as $guest)
                            <button type="button" wire:click="selectGuest({{ $guest->id }})"
                                    class="w-full text-left px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 transition active:scale-[0.99]">
                                <p class="text-sm text-gray-900 dark:text-white">{{ $guest->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $guest->email ?? $guest->phone }}</p>
                            </button>
                        @empty
                            <div class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">No matching guests found.</div>
                        @endforelse
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name *</label>
                    <input type="text" wire:model="customerName" class="input">
                    @error('customerName') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone *</label>
                    <input type="text" wire:model="customerPhone" placeholder="09xxxxxxxxx" class="input">
                    @error('customerPhone') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                    <input type="email" wire:model="customerEmail" class="input">
                    @error('customerEmail') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- Booking Details --}}
        <div class="card p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Booking Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Check-in *</label>
                    <input type="date" wire:model.live="check_in" class="input">
                    @error('check_in') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Check-out *</label>
                    <input type="date" wire:model.live="check_out" class="input">
                    @error('check_out') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status *</label>
                    <select wire:model="status" class="select">
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
                    <select wire:model.live="booking_type" class="select">
                        <option value="full">Book Now (Full Payment)</option>
                        <option value="reservation">Reserve (20% Fee)</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Activity Selection --}}
        <div class="card p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Select Activities</h2>

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
                             wire:click="toggleProperty({{ $property->id }}, {{ $property->price }})">
                            <div class="aspect-[4/3] overflow-hidden rounded-t-xl">
                                <img class="w-full h-full object-cover group-hover:scale-105 transition duration-700"
                                     src="{{ $firstImg ? asset('storage/'. $firstImg->image_path) : asset('images/placeholder.jpg') }}"
                                     alt="{{ $property->name }}">
                            </div>
                            <div class="p-4 bg-white dark:bg-gray-800">
                                <h3 class="font-medium text-gray-900 dark:text-white truncate">{{ $property->name }}</h3>
                                <p class="mt-1 text-sm text-primary-600 dark:text-primary-400 font-semibold">
                                    ₱{{ number_format($property->price, 2) }} <span class="text-gray-500 font-normal">/ day</span>
                                </p>
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
                    <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No activities available</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try selecting different dates for the stay.</p>
                </div>
            @endif

            @if(count($selectedProperties) > 0)
                <div class="mt-8 space-y-3">
                    <h3 class="font-medium text-gray-900 dark:text-white mb-4 border-t border-gray-100 dark:border-gray-700 pt-6">Selected Items</h3>
                    @foreach($selectedProperties as $id => $item)
                        @php
                            $prop = $this->availableProperties->firstWhere('id', $id);
                            $days = $this->getDurationInDays();
                            $roomTotal = ($prop->price ?? 0) * $item['quantity'] * $days;
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
                            <button type="button" wire:click="toggleProperty({{ $id }}, {{ $item['price'] }})" 
                                    class="p-2 text-gray-400 dark:text-gray-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/20 dark:hover:text-red-400 rounded-full transition active:scale-95 focus-visible:ring-2 focus-visible:ring-red-500/50" title="Remove item">
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
            @if($this->availableServices->isNotEmpty())
                <div class="flex flex-wrap gap-2 mb-4">
                    @foreach($this->availableServices as $service)
                        @php $isServiceSelected = isset($selectedServices[$service->id]); @endphp
                        <button wire:key="svc-btn-{{ $service->id }}" type="button" wire:click="toggleService({{ $service->id }}, {{ $service->price }})"
                                class="border rounded-full px-4 py-2 text-sm font-medium transition-all duration-200 active:scale-95 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-primary-500
                                       {{ $isServiceSelected 
                                          ? 'bg-primary-50 dark:bg-primary-500/15 border-primary-200 dark:border-primary-500/30 text-primary-600 dark:text-primary-400 shadow-sm'
                                          : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
                            {{ $service->name }} (+₱{{ number_format($service->price, 2) }})
                        </button>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 dark:text-gray-400 text-sm">No services available for selected dates.</p>
            @endif

            @if(count($selectedServices) > 0)
                <div class="space-y-2 mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                    @foreach($selectedServices as $id => $item)
                        @php $service = $this->availableServices->firstWhere('id', $id); @endphp
                        @if($service)
                            <div wire:key="sel-svc-{{ $id }}" class="flex items-center justify-between gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $service->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Fixed rate per booking</p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">₱{{ number_format($service->price, 2) }}</p>
                                    <button type="button" wire:click="toggleService({{ $id }}, {{ $item['price'] }})" 
                                            class="p-2 text-gray-400 dark:text-gray-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/20 dark:hover:text-red-400 rounded-full transition active:scale-95 focus-visible:ring-2 focus-visible:ring-red-500/50" title="Remove service">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Payment Summary & Discount --}}
        <div class="card p-5 sm:p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Payment Summary</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Discount Amount (₱)</label>
                    <input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="discountAmount" class="input">
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Original Total</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">₱{{ number_format($totalAmount, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Final Total</p>
                    <p class="text-xl font-bold text-green-600 dark:text-green-400">₱{{ number_format($finalTotal, 2) }}</p>
                </div>
            </div>

            @if($booking_type === 'reservation')
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-primary-600 dark:text-primary-400 mb-1">Reservation Fee (20%)</p>
                        <p class="text-lg font-semibold text-primary-600 dark:text-primary-400">₱{{ number_format($reservationFee, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-amber-600 dark:text-amber-500 mb-1">Balance on Arrival</p>
                        <p class="text-lg font-semibold text-amber-600 dark:text-amber-500">₱{{ number_format($balanceOnArrival, 2) }}</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- Sticky Actions --}}
        <div class="card p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6 sticky bottom-4 z-20">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Final Total</p>
                <span class="text-3xl font-black text-primary-600 dark:text-primary-400">₱{{ number_format($finalTotal, 2) }}</span>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-3">
                <a href="{{ route('tenant.bookings.show', $booking->id) }}" wire:navigate class="btn-secondary text-center px-6 py-2.5 active:scale-95 transition-transform">
                    Cancel
                </a>
                <button type="submit" 
                        wire:loading.attr="disabled"
                        class="btn-primary px-8 py-2.5 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg shadow-primary-500/30 flex justify-center items-center active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    <span wire:loading.remove wire:target="update">Save Changes</span>
                    <span wire:loading wire:target="update" class="flex items-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Saving...
                    </span>
                </button>
            </div>
        </div>

    </form>
</div>