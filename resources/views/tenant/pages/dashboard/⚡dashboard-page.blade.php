<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Employee;
use Illuminate\Support\Facades\Auth;

new 
#[Layout('tenant.layouts.app')]
#[Title('Business Dashboard')]
class extends Component {
    
    #[Computed]
    public function stats()
    {
        $currentMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();
        $today = now()->toDateString();

        return [
            'total_bookings' => Booking::count(),
            'total_properties' => Property::count(),
            'total_customers' => Customer::count(),
            'total_services' => Service::count(),
            'total_employees' => Employee::count(),
            
            'revenue_this_month' => Payment::where('payment_status', 'paid')
                ->where('paid_at', '>=', $currentMonth)
                ->sum('amount'),
            'revenue_last_month' => Payment::where('payment_status', 'paid')
                ->whereBetween('paid_at', [$lastMonth, $currentMonth])
                ->sum('amount'),
                
            'pending_bookings' => Booking::where('status', 'pending')->count(),
            'confirmed_bookings' => Booking::where('status', 'confirmed')->count(),
            'completed_bookings' => Booking::where('status', 'completed')->count(),
            
            'arrivals_today' => Booking::whereDate('check_in', $today)->count(),
            'departures_today' => Booking::whereDate('check_out', $today)->count(),
            'occupied_properties' => Property::where('status', 'occupied')->count(),
        ];
    }

    #[Computed]
    public function recentBookings()
    {
        // Booking does not have a direct propertyType relationship; remove it.
        return Booking::with('customer')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
    }

    #[Computed]
    public function upcomingArrivals()
    {
        return Booking::with('customer')
            ->where('status', 'confirmed')
            ->whereDate('check_in', '>=', now())
            ->orderBy('check_in')
            ->take(3)
            ->get();
    }

    #[Computed]
    public function recentPayments()
    {
        return Payment::with('booking')
            ->where('payment_status', 'paid')
            ->whereNotNull('paid_at')
            ->orderBy('paid_at', 'desc')
            ->take(3)
            ->get();
    }

    #[Computed]
    public function revenueTrend()
    {
        $current = $this->stats['revenue_this_month'];
        $last = $this->stats['revenue_last_month'];
        if ($last == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $last) / $last) * 100, 1);
    }
};
?>

<div class="p-6 sm:p-10 max-w-7xl mx-auto space-y-8">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Business Overview</h1>
            <p class="text-slate-500">Welcome back, {{ Auth::user()->tenant->name ?? 'Admin' }}.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('tenant.bookings.create') }}" wire:navigate class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-lg shadow-sm transition flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                New Booking
            </a>
            <a href="{{ route('tenant.properties.index') }}" wire:navigate class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-medium py-2.5 px-5 rounded-lg shadow-sm transition flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                Properties
            </a>
        </div>
    </div>

    {{-- Key Metrics Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        {{-- Total Bookings --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-slate-500">Total Bookings</h3>
                <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-slate-800">{{ number_format($this->stats['total_bookings']) }}</p>
            <div class="flex gap-3 mt-2">
                <span class="text-xs text-amber-600">{{ $this->stats['pending_bookings'] }} pending</span>
                <span class="text-xs text-green-600">{{ $this->stats['confirmed_bookings'] }} confirmed</span>
                <span class="text-xs text-blue-600">{{ $this->stats['completed_bookings'] }} completed</span>
            </div>
        </div>

        {{-- Revenue This Month --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-slate-500">Revenue (This Month)</h3>
                <div class="p-2 bg-emerald-50 rounded-lg text-emerald-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-slate-800">₱{{ number_format($this->stats['revenue_this_month'], 2) }}</p>
            <div class="flex items-center gap-2 mt-2">
                <span class="text-xs font-medium {{ $this->revenueTrend >= 0 ? 'text-green-600' : 'text-red-600' }} flex items-center gap-0.5">
                    @if($this->revenueTrend >= 0)
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                    @else
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6"></path></svg>
                    @endif
                    {{ abs($this->revenueTrend) }}%
                </span>
                <span class="text-xs text-slate-400">vs last month</span>
            </div>
        </div>

        {{-- Occupancy / Today's Activity --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-slate-500">Today's Activity</h3>
                <div class="p-2 bg-amber-50 rounded-lg text-amber-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-slate-800">{{ $this->stats['arrivals_today'] }} / {{ $this->stats['departures_today'] }}</p>
            <p class="text-xs text-slate-500 mt-2">Arrivals / Departures</p>
            <p class="text-sm text-slate-600 mt-1">{{ $this->stats['occupied_properties'] }} properties occupied</p>
        </div>

        {{-- Customers --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-slate-500">Customers</h3>
                <div class="p-2 bg-purple-50 rounded-lg text-purple-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-slate-800">{{ number_format($this->stats['total_customers']) }}</p>
            <p class="text-xs text-slate-400 mt-2">Total registered guests</p>
        </div>
    </div>

    {{-- Secondary Stats Row --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 flex items-center gap-3">
            <div class="p-2 bg-indigo-50 rounded-lg text-indigo-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
            <div>
                <p class="text-xs text-slate-500">Properties</p>
                <p class="text-xl font-bold text-slate-800">{{ $this->stats['total_properties'] }}</p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 flex items-center gap-3">
            <div class="p-2 bg-rose-50 rounded-lg text-rose-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
            </div>
            <div>
                <p class="text-xs text-slate-500">Services</p>
                <p class="text-xl font-bold text-slate-800">{{ $this->stats['total_services'] }}</p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 flex items-center gap-3">
            <div class="p-2 bg-cyan-50 rounded-lg text-cyan-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
            <div>
                <p class="text-xs text-slate-500">Employees</p>
                <p class="text-xl font-bold text-slate-800">{{ $this->stats['total_employees'] }}</p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 flex items-center gap-3">
            <div class="p-2 bg-lime-50 rounded-lg text-lime-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
            </div>
            <div>
                <p class="text-xs text-slate-500">Pending Payments</p>
                <p class="text-xl font-bold text-slate-800">{{ Payment::where('payment_status', '!=', 'paid')->count() }}</p>
            </div>
        </div>
    </div>

    {{-- Recent Activity & Quick Actions --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Recent Bookings --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-slate-800">Recent Bookings</h3>
                <a href="{{ route('tenant.bookings.index') }}" wire:navigate class="text-sm text-blue-600 hover:text-blue-800 font-medium">View All &rarr;</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Ref</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Check In</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse($this->recentBookings as $booking)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-slate-600">{{ $booking->booking_reference }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">{{ $booking->customer->name ?? 'N/A' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">{{ $booking->check_in->format('M d, Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-800">₱{{ number_format($booking->total_amount, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($booking->status === 'pending')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Pending</span>
                                    @elseif($booking->status === 'confirmed')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Confirmed</span>
                                    @elseif($booking->status === 'completed')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Completed</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">{{ ucfirst($booking->status) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">No bookings yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Right Column: Upcoming & Quick Actions --}}
        <div class="space-y-6">
            {{-- Upcoming Arrivals --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                    <h3 class="text-md font-semibold text-slate-800">Upcoming Arrivals</h3>
                </div>
                <div class="divide-y divide-slate-200">
                    @forelse($this->upcomingArrivals as $booking)
                        <div class="px-6 py-4 hover:bg-slate-50">
                            <p class="text-sm font-medium text-slate-800">{{ $booking->customer->name ?? 'Guest' }}</p>
                            <p class="text-xs text-slate-500">{{ $booking->check_in->format('M d, Y') }} · {{ $booking->booking_reference }}</p>
                        </div>
                    @empty
                        <div class="px-6 py-4 text-center text-slate-500 text-sm">No upcoming arrivals.</div>
                    @endforelse
                </div>
            </div>

            {{-- Recent Payments --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                    <h3 class="text-md font-semibold text-slate-800">Recent Payments</h3>
                </div>
                <div class="divide-y divide-slate-200">
                    @forelse($this->recentPayments as $payment)
                        <div class="px-6 py-4 hover:bg-slate-50">
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-slate-800">₱{{ number_format($payment->amount, 2) }}</span>
                                <span class="text-xs text-slate-500">{{ $payment->paid_at?->format('M d') ?? '—' }}</span>
                            </div>
                            <p class="text-xs text-slate-500">Ref: {{ $payment->reference_number ?? '—' }}</p>
                        </div>
                    @empty
                        <div class="px-6 py-4 text-center text-slate-500 text-sm">No payments yet.</div>
                    @endforelse
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <h3 class="text-md font-semibold text-slate-800 mb-3">Quick Actions</h3>
                <div class="grid grid-cols-2 gap-2">
                    <a href="{{ route('tenant.customers.create') }}" wire:navigate class="flex items-center gap-2 p-2 rounded-lg hover:bg-slate-50 text-sm text-slate-700">
                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                        Add Customer
                    </a>
                    <a href="{{ route('tenant.services.create') }}" wire:navigate class="flex items-center gap-2 p-2 rounded-lg hover:bg-slate-50 text-sm text-slate-700">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        Add Service
                    </a>
                    <a href="{{ route('tenant.employees.create') }}" wire:navigate class="flex items-center gap-2 p-2 rounded-lg hover:bg-slate-50 text-sm text-slate-700">
                        <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        Add Employee
                    </a>
                    <a href="{{ route('tenant.settings.index') }}" wire:navigate class="flex items-center gap-2 p-2 rounded-lg hover:bg-slate-50 text-sm text-slate-700">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>