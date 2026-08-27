{{-- resources/views/tenant/pages/employee/⚡dashboard.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Service;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;

new
#[Layout('tenant.layouts.app')]
#[Title('Dashboard')]
class extends Component
{
    #[Computed]
    public function stats()
    {
        $tid = Auth::user()->tenant_id;

        return [
            'pending_bookings'   => Booking::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tid)->where('status', 'pending')->count(),
            'today_arrivals'     => Booking::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tid)->whereDate('check_in', now())->where('status', '!=', 'cancelled')->count(),
            'available_properties' => Property::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tid)->where('is_active', true)->where('status', 'available')->count(),
            'revenue_this_month' => Payment::where('tenant_id', $tid)
                ->where('payment_status', 'paid')
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
        ];
    }

    #[Computed]
    public function recentBookings()
    {
        return Booking::withoutGlobalScope(TenantScope::class)
            ->with('user')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->latest()
            ->take(5)
            ->get();
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="pb-6 border-b border-gray-200 dark:border-gray-700">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
            Welcome, {{ auth()->user()->name }}
        </h1>
    </div>

    {{-- Quick Stats (permission‑aware) --}}
    @php $s = $this->stats; @endphp
    @if(auth()->user()->hasAnyPermission(['view bookings','view payments','view properties']))
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @if(auth()->user()->can('view bookings'))
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
                    <p class="text-xs text-amber-600 dark:text-amber-400 uppercase tracking-wider">Pending</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $s['pending_bookings'] }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
                    <p class="text-xs text-[#376df1] dark:text-blue-400 uppercase tracking-wider">Arrivals Today</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $s['today_arrivals'] }}</p>
                </div>
            @endif
            @if(auth()->user()->can('view properties'))
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
                    <p class="text-xs text-green-600 dark:text-green-400 uppercase tracking-wider">Available</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $s['available_properties'] }}</p>
                </div>
            @endif
            @if(auth()->user()->can('view payments'))
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
                    <p class="text-xs text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Revenue (Month)</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">₱{{ number_format($s['revenue_this_month'], 2) }}</p>
                </div>
            @endif
        </div>
    @endif

    {{-- Permission‑aware cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">

        {{-- Bookings --}}
        @can('view bookings')
        <a href="{{ route('tenant.bookings.index') }}" wire:navigate
           class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm hover:shadow-md transition flex flex-col items-center text-center gap-2 group">
            <div class="w-11 h-11 rounded-xl bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-5 h-5 text-[#376df1] dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <h2 class="text-base font-bold text-gray-900 dark:text-white">Bookings</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">View & manage reservations</p>
        </a>
        @endcan

        {{-- New Reservation --}}
        @can('create bookings')
        <a href="{{ route('tenant.bookings.create') }}" wire:navigate
           class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm hover:shadow-md transition flex flex-col items-center text-center gap-2 group">
            <div class="w-11 h-11 rounded-xl bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-5 h-5 text-[#376df1] dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </div>
            <h2 class="text-base font-bold text-gray-900 dark:text-white">New Reservation</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">Create a new booking</p>
        </a>
        @endcan

        {{-- Properties --}}
        @can('view properties')
        <a href="{{ route('tenant.properties.index') }}" wire:navigate
           class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm hover:shadow-md transition flex flex-col items-center text-center gap-2 group">
            <div class="w-11 h-11 rounded-xl bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-5 h-5 text-[#376df1] dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <h2 class="text-base font-bold text-gray-900 dark:text-white">Activities</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">Browse bookable activities</p>
        </a>
        @endcan

        {{-- Payments --}}
        @can('view payments')
        <a href="{{ route('tenant.payments.index') }}" wire:navigate
           class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm hover:shadow-md transition flex flex-col items-center text-center gap-2 group">
            <div class="w-11 h-11 rounded-xl bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-5 h-5 text-[#376df1] dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h2 class="text-base font-bold text-gray-900 dark:text-white">Payments</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">View payment history</p>
        </a>
        @endcan

        {{-- Services --}}
        @can('view services')
        <a href="{{ route('tenant.services.index') }}" wire:navigate
           class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm hover:shadow-md transition flex flex-col items-center text-center gap-2 group">
            <div class="w-11 h-11 rounded-xl bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-5 h-5 text-[#376df1] dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                </svg>
            </div>
            <h2 class="text-base font-bold text-gray-900 dark:text-white">Services</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">Manage add‑on services</p>
        </a>
        @endcan

        {{-- Employees --}}
        @can('view employees')
        <a href="{{ route('tenant.employees.index') }}" wire:navigate
           class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm hover:shadow-md transition flex flex-col items-center text-center gap-2 group">
            <div class="w-11 h-11 rounded-xl bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-5 h-5 text-[#376df1] dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </div>
            <h2 class="text-base font-bold text-gray-900 dark:text-white">Employees</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">Manage team access</p>
        </a>
        @endcan

        {{-- Analytics --}}
        @can('view analytics')
        <a href="{{ route('tenant.analytics.index') }}" wire:navigate
           class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm hover:shadow-md transition flex flex-col items-center text-center gap-2 group">
            <div class="w-11 h-11 rounded-xl bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-5 h-5 text-[#376df1] dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3v18h18"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l4-4 4 4 5-5"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h1l4 4 4-4h1"/>
                </svg>
            </div>
            <h2 class="text-base font-bold text-gray-900 dark:text-white">Analytics</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">Revenue & occupancy stats</p>
        </a>
        @endcan

        {{-- Tourist Spot Profile --}}
        <a href="{{ route('tenant.settings.overview') }}" wire:navigate
           class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm hover:shadow-md transition flex flex-col items-center text-center gap-2 group">
            <div class="w-11 h-11 rounded-xl bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-5 h-5 text-[#376df1] dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                </svg>
            </div>
            <h2 class="text-base font-bold text-gray-900 dark:text-white">Spot Profile</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">Edit public business page</p>
        </a>

    </div>

    {{-- Recent Bookings (only if user can view bookings) --}}
    @can('view bookings')
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <h3 class="font-bold text-gray-900 dark:text-white">Recent Bookings</h3>
                <a href="{{ route('tenant.bookings.index') }}" wire:navigate class="text-sm font-semibold text-[#376df1] dark:text-blue-400 hover:underline">View all →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <th class="px-6 py-4 text-left">Ref</th>
                            <th class="px-6 py-4 text-left">Guest</th>
                            <th class="px-6 py-4 text-left">Check‑in</th>
                            <th class="px-6 py-4 text-left">Amount</th>
                            <th class="px-6 py-4 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-200">
                        @forelse($this->recentBookings as $b)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-6 py-4 font-mono text-xs">{{ $b->booking_reference }}</td>
                                <td class="px-6 py-4">{{ $b->user->name ?? 'N/A' }}</td>
                                <td class="px-6 py-4">{{ $b->check_in->format('M d, Y') }}</td>
                                <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">₱{{ number_format($b->total_amount, 2) }}</td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $b->status === 'pending' ? 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30' : '' }}
                                        {{ $b->status === 'confirmed' ? 'bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-500/30' : '' }}
                                        {{ $b->status === 'completed' ? 'bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-500/30' : '' }}">
                                        {{ ucfirst($b->status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">No bookings yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endcan
</div>