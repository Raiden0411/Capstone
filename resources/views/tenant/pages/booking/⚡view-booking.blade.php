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
        
        $oldStatus = $booking->status;
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

    #[Computed]
    public function customers()
    {
        return Customer::orderBy('name')->get();
    }

    #[Computed]
    public function bookings()
    {
        return Booking::with(['customer', 'items.property', 'services.service', 'payments'])
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
            'total' => Booking::count(),
            'pending' => Booking::where('status', 'pending')->count(),
            'confirmed' => Booking::where('status', 'confirmed')->count(),
            'checked_in' => Booking::where('status', 'checked_in')->count(),
            'completed' => Booking::where('status', 'completed')->count(),
            'cancelled' => Booking::where('status', 'cancelled')->count(),
            'revenue' => Booking::whereIn('status', ['confirmed', 'checked_in', 'completed'])->sum('total_amount'),
            'today_arrivals' => Booking::whereDate('check_in', today())->where('status', '!=', 'cancelled')->count(),
            'today_departures' => Booking::whereDate('check_out', today())->where('status', '!=', 'cancelled')->count(),
            'available_rooms' => Property::where('is_active', true)->where('status', 'available')->count(),
        ];
    }
};
?>
<div class="p-6 sm:p-10 max-w-7xl mx-auto">
    {{-- Header with actions --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Bookings</h1>
            <p class="text-slate-500">Walk‑ins, reservations, and today's activity.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('tenant.bookings.create') }}" wire:navigate class="bg-green-600 hover:bg-green-700 text-white font-medium py-2.5 px-5 rounded-lg shadow-sm transition flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                New Walk‑In
            </a>
            <a href="{{ route('tenant.customers.create') }}" wire:navigate class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-lg shadow-sm transition flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                New Reservation
            </a>
        </div>
    </div>

    {{-- Flash Message --}}
    @if (session()->has('message'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center gap-3 shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            {{ session('message') }}
        </div>
    @endif

    {{-- Today's Activity Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <p class="text-sm text-slate-500">Today's Arrivals</p>
            <p class="text-2xl font-bold text-slate-800">{{ $this->stats['today_arrivals'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <p class="text-sm text-slate-500">Today's Departures</p>
            <p class="text-2xl font-bold text-slate-800">{{ $this->stats['today_departures'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <p class="text-sm text-slate-500">Available Rooms</p>
            <p class="text-2xl font-bold text-slate-800">{{ $this->stats['available_rooms'] }}</p>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <p class="text-sm text-slate-500">Total Bookings</p>
            <p class="text-2xl font-bold text-slate-800">{{ $this->stats['total'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <p class="text-sm text-amber-600">Pending</p>
            <p class="text-2xl font-bold text-amber-600">{{ $this->stats['pending'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <p class="text-sm text-blue-600">Confirmed</p>
            <p class="text-2xl font-bold text-blue-600">{{ $this->stats['confirmed'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <p class="text-sm text-emerald-600">Revenue</p>
            <p class="text-2xl font-bold text-emerald-600">₱{{ number_format($this->stats['revenue'], 2) }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-6">
        <div class="flex flex-col md:flex-row gap-3">
            <div class="relative flex-1">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by ref or customer..." 
                       class="w-full pl-10 pr-4 py-2 border border-slate-300 rounded-lg focus:ring-blue-500">
                <svg class="absolute left-3 top-2.5 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="statusFilter" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="checked_in">Checked In</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <select wire:model.live="customerFilter" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="">All Customers</option>
                    @foreach($this->customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                    @endforeach
                </select>
                <input type="text" wire:model.live="dateRange" placeholder="Check-in date range" class="px-3 py-2 border border-slate-300 rounded-lg text-sm w-56" />
                @if($search || $statusFilter || $dateRange || $customerFilter)
                    <button wire:click="clearFilters" class="px-3 py-2 text-sm text-slate-600 hover:text-slate-800 border border-slate-300 rounded-lg hover:bg-slate-50">Clear</button>
                @endif
            </div>
        </div>
    </div>

    {{-- Bookings Table --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Ref</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Customer</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Check In/Out</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Total</th>
                        <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold text-slate-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($this->bookings as $booking)
                        <tr class="hover:bg-slate-50 transition-colors cursor-pointer"
                            wire:click="toggleExpand({{ $booking->id }})">
                            <td class="px-6 py-4 font-mono text-sm">{{ $booking->booking_reference }}</td>
                            <td class="px-6 py-4 text-sm">{{ $booking->customer->name ?? 'N/A' }}</td>
                            <td class="px-6 py-4 text-sm">
                                {{ $booking->check_in?->format('M d, Y') ?? '—' }} → {{ $booking->check_out?->format('M d, Y') ?? '—' }}
                                @if($booking->check_in && $booking->check_out)
                                    <div class="text-xs text-slate-500">{{ $booking->check_in->diffInDays($booking->check_out) }} nights</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm font-medium">₱{{ number_format($booking->total_amount, 2) }}</td>
                            <td class="px-6 py-4" wire:click.stop>
                                <select wire:change="updateStatus({{ $booking->id }}, $event.target.value)" class="text-xs rounded-full px-2 py-1 border-0 font-medium
                                    {{ $booking->status === 'pending' ? 'bg-amber-100 text-amber-800' : '' }}
                                    {{ $booking->status === 'confirmed' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $booking->status === 'checked_in' ? 'bg-purple-100 text-purple-800' : '' }}
                                    {{ $booking->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $booking->status === 'cancelled' ? 'bg-red-100 text-red-800' : '' }}">
                                    <option value="pending" {{ $booking->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="confirmed" {{ $booking->status === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                                    <option value="checked_in" {{ $booking->status === 'checked_in' ? 'selected' : '' }}>Checked In</option>
                                    <option value="completed" {{ $booking->status === 'completed' ? 'selected' : '' }}>Completed</option>
                                    <option value="cancelled" {{ $booking->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                </select>
                            </td>
                            <td class="px-6 py-4 text-right" wire:click.stop>
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('tenant.bookings.show', $booking->id) }}" wire:navigate class="p-1.5 text-slate-600 hover:bg-slate-100 rounded-lg" title="View Details">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </a>
                                    <a href="{{ route('tenant.bookings.edit', $booking->id) }}" wire:navigate class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <button wire:click="delete({{ $booking->id }})" wire:confirm="Delete this booking?" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @if($expandedId === $booking->id)
                        <tr>
                            <td colspan="6" class="px-6 py-4 bg-slate-50 border-t border-slate-200">
                                {{-- Expanded details --}}
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    {{-- Customer Details --}}
                                    <div>
                                        <h4 class="font-semibold text-slate-700 mb-2 flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                            Customer Details
                                        </h4>
                                        @php $customer = $booking->customer; @endphp
                                        @if($customer)
                                            <div class="space-y-1 text-sm">
                                                <p><span class="text-slate-500">Name:</span> {{ $customer->name }}</p>
                                                <p><span class="text-slate-500">Phone:</span> {{ $customer->phone ?? '—' }}</p>
                                                <p><span class="text-slate-500">Email:</span> {{ $customer->email ?? '—' }}</p>
                                                <p><span class="text-slate-500">Address:</span> {{ $customer->address ?? '—' }}</p>
                                                <p><span class="text-slate-500">Notes:</span> {{ $customer->notes ?? '—' }}</p>
                                            </div>
                                        @else
                                            <p class="text-sm text-slate-500">No customer information available.</p>
                                        @endif
                                    </div>

                                    {{-- Property Items --}}
                                    <div>
                                        <h4 class="font-semibold text-slate-700 mb-2 flex items-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                            Properties
                                        </h4>
                                        @if($booking->items->isNotEmpty())
                                            <ul class="space-y-2">
                                                @foreach($booking->items as $item)
                                                    <li class="text-sm flex justify-between border-b border-slate-200 pb-1">
                                                        <span>{{ $item->property->name ?? 'Unknown Property' }} (x{{ $item->quantity }})</span>
                                                        <span class="font-medium">₱{{ number_format($item->subtotal, 2) }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <p class="text-sm text-slate-500">No properties in this booking.</p>
                                        @endif
                                    </div>

                                    {{-- Services & Payments --}}
                                    <div class="space-y-4">
                                        <div>
                                            <h4 class="font-semibold text-slate-700 mb-2 flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                                Services
                                            </h4>
                                            @if($booking->services->isNotEmpty())
                                                <ul class="space-y-2">
                                                    @foreach($booking->services as $serviceItem)
                                                        <li class="text-sm flex justify-between border-b border-slate-200 pb-1">
                                                            <span>{{ $serviceItem->service->name ?? 'Unknown Service' }} (x{{ $serviceItem->quantity }})</span>
                                                            <span class="font-medium">₱{{ number_format($serviceItem->subtotal, 2) }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <p class="text-sm text-slate-500">No additional services.</p>
                                            @endif
                                        </div>

                                        <div>
                                            <h4 class="font-semibold text-slate-700 mb-2 flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                Payments
                                            </h4>
                                            @if($booking->payments->isNotEmpty())
                                                <ul class="space-y-2">
                                                    @foreach($booking->payments as $payment)
                                                        <li class="text-sm flex justify-between border-b border-slate-200 pb-1">
                                                            <span>
                                                                {{ ucfirst($payment->payment_method) }}
                                                                @if($payment->reference_number)
                                                                    <span class="text-xs text-slate-400 block">{{ $payment->reference_number }}</span>
                                                                @endif
                                                            </span>
                                                            <span class="font-medium">₱{{ number_format($payment->amount, 2) }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                                @php
                                                    $paidAmount = $booking->payments->where('payment_status', 'paid')->sum('amount');
                                                    $balance = $booking->total_amount - $paidAmount;
                                                @endphp
                                                <div class="mt-2 text-sm">
                                                    <p><span class="text-slate-500">Total Paid:</span> ₱{{ number_format($paidAmount, 2) }}</p>
                                                    <p><span class="text-slate-500">Balance:</span> <span class="{{ $balance > 0 ? 'text-red-600' : 'text-green-600' }}">₱{{ number_format($balance, 2) }}</span></p>
                                                    @if($balance > 0 && !in_array($booking->status, ['cancelled', 'completed']))
                                                        <a href="{{ route('tenant.payments.create', ['booking' => $booking->id]) }}" wire:navigate class="mt-3 inline-flex items-center gap-1 text-sm bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg transition">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                                            Record Payment
                                                        </a>
                                                    @endif
                                                </div>
                                            @else
                                                <p class="text-sm text-slate-500">No payments recorded.</p>
                                                @if($booking->total_amount > 0 && !in_array($booking->status, ['cancelled', 'completed']))
                                                    <a href="{{ route('tenant.payments.create', ['booking' => $booking->id]) }}" wire:navigate class="mt-3 inline-flex items-center gap-1 text-sm bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg transition">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                                        Record Payment
                                                    </a>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endif
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-slate-500">No bookings found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->bookings->hasPages())
            <div class="px-6 py-4 border-t bg-slate-50">{{ $this->bookings->links() }}</div>
        @endif
    </div>
</div>