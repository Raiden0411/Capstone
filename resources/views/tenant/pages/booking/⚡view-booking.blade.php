<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

new 
#[Layout('tenant.layouts.app')]
#[Title('Bookings')]
class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $dateRange = '';
    public ?int $customerFilter = null;
    public ?int $expandedId = null;

    public int $paymentDeadlineHours = 3;

    public function booted()
    {
        $this->cancelOverdueBookings();
    }

    protected function cancelOverdueBookings(): void
    {
        $overdue = Booking::where('status', 'pending')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('created_at', '<=', now()->subHours($this->paymentDeadlineHours))
            ->get();

        foreach ($overdue as $booking) {
            $paid = $booking->payments()->where('payment_status', 'paid')->sum('amount');
            if ($paid >= $booking->total_amount) {
                continue;
            }

            $propertyIds = $booking->items()->pluck('property_id')->unique();
            Property::whereIn('id', $propertyIds)->update(['status' => 'available']);

            $booking->update(['status' => 'cancelled']);
        }
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingStatusFilter() { $this->resetPage(); }
    public function updatingDateRange() { $this->resetPage(); }
    public function updatingCustomerFilter() { $this->resetPage(); }

    public function toggleExpand(int $id)
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function delete(int $id)
    {
        $booking = Booking::where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->firstOrFail();
        $bookingRef = $booking->booking_reference;
        $booking->delete();
        session()->flash('message', "Booking #{$bookingRef} deleted.");
    }

    public function updateStatus(int $id, string $status)
    {
        $booking = Booking::where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->firstOrFail();

        $validTransitions = [
            'pending'   => ['confirmed', 'cancelled'],
            'confirmed' => ['checked_in', 'cancelled'],
            'checked_in'=> ['completed', 'cancelled'],
        ];

        $current = $booking->status;

        if (isset($validTransitions[$current]) && !in_array($status, $validTransitions[$current])) {
            session()->flash('error', "Cannot change status from '{$current}' to '{$status}'.");
            return;
        }

        if ($current === 'pending' && $status === 'confirmed') {
            $totalPaid = $booking->payments()->where('payment_status', 'paid')->sum('amount');
            if ($totalPaid < $booking->total_amount) {
                session()->flash('error', 'Payment must be completed before confirming the booking.');
                return;
            }
        }

        $oldStatus = $current;
        $booking->update(['status' => $status]);

        if (in_array($status, ['completed', 'cancelled']) && !in_array($oldStatus, ['completed', 'cancelled'])) {
            $propertyIds = $booking->items()->pluck('property_id')->unique();
            Property::whereIn('id', $propertyIds)->update(['status' => 'available']);
        }

        session()->flash('message', "Booking #{$booking->booking_reference} marked as {$status}.");
    }

    public function clearFilters()
    {
        $this->reset(['search', 'statusFilter', 'dateRange', 'customerFilter']);
        $this->resetPage();
    }

    public function isOverdue(Booking $booking): bool
    {
        if ($booking->status !== 'pending') {
            return false;
        }
        $paid = $booking->payments()->where('payment_status', 'paid')->sum('amount');
        if ($paid >= $booking->total_amount) {
            return false;
        }
        return $booking->created_at->diffInHours(now()) >= $this->paymentDeadlineHours;
    }

    #[Computed]
    public function customers()
    {
        return Customer::orderBy('name')->get();
    }

    #[Computed]
    public function bookings()
    {
        return Booking::with(['customer', 'items.property', 'services.service', 'payments'])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('booking_reference', 'like', '%' . $this->search . '%')
                      ->orWhereHas('customer', fn($c) => $c->where('name', 'like', '%' . $this->search . '%'));
                });
            })
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->customerFilter, fn($q) => $q->where('customer_id', $this->customerFilter))
            ->when($this->dateRange, function ($query) {
                $dates = explode(' to ', $this->dateRange);
                if (count($dates) === 2) {
                    $query->whereBetween('check_in', [Carbon::parse($dates[0]), Carbon::parse($dates[1])]);
                }
            })
            ->orderBy('check_in', 'desc')
            ->paginate(10);
    }

    #[Computed]
    public function stats()
    {
        return [
            'total' => Booking::whereNotIn('status', ['completed', 'cancelled'])->count(),
            'pending' => Booking::where('status', 'pending')->count(),
            'confirmed' => Booking::where('status', 'confirmed')->count(),
            'checked_in' => Booking::where('status', 'checked_in')->count(),
            'overdue' => Booking::where('status', 'pending')
                ->where('created_at', '<=', now()->subHours($this->paymentDeadlineHours))
                ->count(),
            'revenue' => Booking::whereIn('status', ['confirmed', 'checked_in'])->sum('total_amount'),
            'today_arrivals' => Booking::whereDate('check_in', today())->where('status', '!=', 'cancelled')->count(),
            'today_departures' => Booking::whereDate('check_out', today())->where('status', '!=', 'cancelled')->count(),
            'available' => Property::where('is_active', true)->where('status', 'available')->count(),
        ];
    }
};
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto text-gray-900 dark:text-white space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Active Bookings</h1>
            <p class="text-gray-500 dark:text-slate-400 mt-1">All upcoming stays. Unpaid bookings are auto‑cancelled after {{ $paymentDeadlineHours }} hours.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('tenant.bookings.create') }}" wire:navigate class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 px-5 rounded-xl shadow-sm transition-colors flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Walk‑In
            </a>
            <a href="{{ route('tenant.customers.create') }}" wire:navigate class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-xl shadow-sm transition-colors flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Reservation
            </a>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="p-4 bg-green-50 dark:bg-green-500/10 border-l-4 border-green-500 rounded-md shadow-sm">
            <p class="text-sm text-green-700 dark:text-green-400 font-medium">{{ session('message') }}</p>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="p-4 bg-red-50 dark:bg-red-500/10 border-l-4 border-red-500 rounded-md shadow-sm">
            <p class="text-sm text-red-700 dark:text-red-400 font-medium">{{ session('error') }}</p>
        </div>
    @endif

    {{-- Today's Activity Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <p class="text-sm text-gray-500 dark:text-slate-400">Today's Arrivals</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->stats['today_arrivals'] }}</p>
        </div>
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <p class="text-sm text-gray-500 dark:text-slate-400">Today's Departures</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->stats['today_departures'] }}</p>
        </div>
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <p class="text-sm text-gray-500 dark:text-slate-400">Available Properties</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->stats['available'] }}</p>
        </div>
    </div>

    {{-- Status Summary --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="rounded-lg bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-3 text-center">
            <p class="text-xs text-gray-500 dark:text-slate-400">All Active</p>
            <p class="text-xl font-bold">{{ $this->stats['total'] }}</p>
        </div>
        <div class="rounded-lg bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 p-3 text-center">
            <p class="text-xs text-amber-700 dark:text-amber-400">Pending</p>
            <p class="text-xl font-bold text-amber-700 dark:text-amber-400">{{ $this->stats['pending'] }}</p>
        </div>
        <div class="rounded-lg bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 p-3 text-center">
            <p class="text-xs text-red-700 dark:text-red-400">Overdue</p>
            <p class="text-xl font-bold text-red-700 dark:text-red-400">{{ $this->stats['overdue'] }}</p>
        </div>
        <div class="rounded-lg bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/30 p-3 text-center">
            <p class="text-xs text-blue-700 dark:text-blue-400">Confirmed</p>
            <p class="text-xl font-bold text-blue-700 dark:text-blue-400">{{ $this->stats['confirmed'] }}</p>
        </div>
        <div class="rounded-lg bg-purple-50 dark:bg-purple-500/10 border border-purple-200 dark:border-purple-500/30 p-3 text-center">
            <p class="text-xs text-purple-700 dark:text-purple-400">Checked In</p>
            <p class="text-xl font-bold text-purple-700 dark:text-purple-400">{{ $this->stats['checked_in'] }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 p-4">
        <div class="flex flex-col md:flex-row gap-3">
            <div class="relative flex-1">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by ref or customer..." 
                       class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 rounded-lg focus:ring-blue-500">
                <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="statusFilter" class="px-3 py-2 border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 rounded-lg text-sm focus:ring-blue-500">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="checked_in">Checked In</option>
                </select>
                <select wire:model.live="customerFilter" class="px-3 py-2 border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 rounded-lg text-sm focus:ring-blue-500">
                    <option value="">All Customers</option>
                    @foreach($this->customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                    @endforeach
                </select>
                <input type="text" wire:model.live="dateRange" placeholder="Check-in date range" class="px-3 py-2 border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 rounded-lg text-sm w-56 focus:ring-blue-500" />
                @if($search || $statusFilter || $dateRange || $customerFilter)
                    <button wire:click="clearFilters" class="px-3 py-2 text-sm text-gray-600 dark:text-slate-400 hover:text-gray-800 dark:hover:text-white border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors">Clear</button>
                @endif
            </div>
        </div>
    </div>

    {{-- Bookings Table --}}
    <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-gray-50 dark:bg-slate-800/50 border-b border-gray-200 dark:border-slate-700/50">
                    <tr>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Ref</th>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Customer</th>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Check In/Out</th>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Total</th>
                        <th class="px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-slate-700/30 text-gray-700 dark:text-slate-300">
                    @forelse($this->bookings as $booking)
                        @php
                            $isOverdue = $this->isOverdue($booking);
                            $isToday = $booking->check_in && $booking->check_in->isToday();
                            $isReservation = $booking->customer && ($booking->customer->email || $booking->customer->address);
                            $stayDuration = $booking->check_in && $booking->check_out
                                ? max(1, $booking->check_in->diffInDays($booking->check_out))
                                : 0;
                            $minutesRemaining = max(0, ($this->paymentDeadlineHours * 60) - $booking->created_at->diffInMinutes(now()));
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors cursor-pointer {{ $isOverdue ? 'bg-red-50 dark:bg-red-500/5' : '' }}"
                            wire:click="toggleExpand({{ $booking->id }})">
                            <td class="px-6 py-4 font-mono text-sm">
                                {{ $booking->booking_reference }}
                                @if($isToday)
                                    <span class="ml-1.5 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-500/20 text-green-800 dark:text-green-400">Today</span>
                                @endif
                                @if($isReservation)
                                    <span class="ml-1.5 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 dark:bg-indigo-500/20 text-indigo-800 dark:text-indigo-400">Reservation</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">{{ $booking->customer->name ?? 'N/A' }}</td>
                            <td class="px-6 py-4 text-sm">
                                {{ $booking->check_in?->format('M d, Y') ?? '—' }}
                                @if($booking->check_out && $booking->check_out->greaterThan($booking->check_in))
                                    → {{ $booking->check_out->format('M d, Y') }}
                                @endif
                                @if($stayDuration > 0)
                                    <div class="text-xs text-gray-500 dark:text-slate-400">{{ $stayDuration }} {{ $stayDuration === 1 ? 'day' : 'days' }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm font-medium">₱{{ number_format($booking->total_amount, 2) }}</td>
                            <td class="px-6 py-4" wire:click.stop>
                                <div class="flex items-center gap-2">
                                    <select wire:change="updateStatus({{ $booking->id }}, $event.target.value)" class="text-xs rounded-full px-2 py-1 border-0 font-medium
                                        {{ $booking->status === 'pending' ? 'bg-amber-100 dark:bg-amber-500/20 text-amber-800 dark:text-amber-400' : '' }}
                                        {{ $booking->status === 'confirmed' ? 'bg-blue-100 dark:bg-blue-500/20 text-blue-800 dark:text-blue-400' : '' }}
                                        {{ $booking->status === 'checked_in' ? 'bg-purple-100 dark:bg-purple-500/20 text-purple-800 dark:text-purple-400' : '' }}">
                                        <option value="pending" {{ $booking->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="confirmed" {{ $booking->status === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                                        <option value="checked_in" {{ $booking->status === 'checked_in' ? 'selected' : '' }}>Checked In</option>
                                        <option value="completed" {{ $booking->status === 'completed' ? 'selected' : '' }}>Completed</option>
                                        <option value="cancelled" {{ $booking->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                    </select>
                                    {{-- Payment timer / overdue badge --}}
                                    @if($booking->status === 'pending')
                                        @php $paid = $booking->payments->where('payment_status', 'paid')->sum('amount'); @endphp
                                        @if($paid < $booking->total_amount)
                                            @if($isOverdue)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-500/20 text-red-800 dark:text-red-400">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    Overdue
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-500/10 text-amber-800 dark:text-amber-400"
                                                      title="{{ floor($minutesRemaining / 60) }}h {{ $minutesRemaining % 60 }}m remaining">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    {{ floor($minutesRemaining / 60) }}h {{ $minutesRemaining % 60 }}m
                                                </span>
                                            @endif
                                        @endif
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right" wire:click.stop>
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('tenant.bookings.show', $booking->id) }}" wire:navigate class="p-1.5 text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 rounded-lg" title="View Details">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </a>
                                    <a href="{{ route('tenant.bookings.edit', $booking->id) }}" wire:navigate class="p-1.5 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-500/10 rounded-lg" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <button wire:click="delete({{ $booking->id }})" wire:confirm="Delete this booking?" class="p-1.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @if($expandedId === $booking->id)
                        <tr>
                            <td colspan="6" class="px-6 py-4 bg-gray-50 dark:bg-slate-800/50 border-t border-gray-200 dark:border-slate-700/30">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <h4 class="font-semibold mb-2">Customer</h4>
                                        @php $customer = $booking->customer; @endphp
                                        @if($customer)
                                            <div class="space-y-1 text-sm">
                                                <p>{{ $customer->name }}</p>
                                                <p>{{ $customer->phone ?? '—' }}</p>
                                                <p>{{ $customer->email ?? '—' }}</p>
                                                <p>{{ $customer->address ?? '—' }}</p>
                                            </div>
                                        @else
                                            <p class="text-sm text-gray-500 dark:text-slate-400">No customer data.</p>
                                        @endif
                                    </div>
                                    <div>
                                        <h4 class="font-semibold mb-2">Properties</h4>
                                        @foreach($booking->items as $item)
                                            <div class="flex justify-between text-sm">
                                                <span>{{ $item->property->name ?? 'Unknown' }} (x{{ $item->quantity }})</span>
                                                <span>₱{{ number_format($item->subtotal, 2) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div>
                                        <h4 class="font-semibold mb-2">Payments</h4>
                                        @php
                                            $paid = $booking->payments->where('payment_status', 'paid')->sum('amount');
                                            $balance = $booking->total_amount - $paid;
                                        @endphp
                                        <p class="text-sm">Paid: ₱{{ number_format($paid, 2) }}</p>
                                        <p class="text-sm">Balance: <span class="{{ $balance > 0 ? 'text-red-600' : 'text-green-600' }}">₱{{ number_format($balance, 2) }}</span></p>
                                        @if($balance > 0)
                                            <a href="{{ route('tenant.payments.create', ['booking' => $booking->id]) }}" wire:navigate class="mt-2 inline-flex items-center gap-1 text-sm bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg">Record Payment</a>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endif
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-slate-400">No active bookings.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->bookings->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-slate-700/50 bg-gray-50 dark:bg-slate-800/50">{{ $this->bookings->links() }}</div>
        @endif
    </div>
</div>