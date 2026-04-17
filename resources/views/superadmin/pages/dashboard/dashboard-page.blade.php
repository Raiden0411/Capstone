<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Payment;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

new 
#[Layout('superadmin.layouts.app')]
#[Title('Platform Dashboard')]
class extends Component {
    
    #[Computed]
    public function stats()
    {
        $currentMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();
        
        return [
            'total_tenants' => Tenant::count(),
            'total_users' => User::count(),
            'total_roles' => Role::where('name', '!=', 'super-admin')->count(),
            'total_properties' => Property::count(),
            'total_bookings' => Booking::count(),
            'new_tenants_this_month' => Tenant::where('created_at', '>=', $currentMonth)->count(),
            'new_tenants_last_month' => Tenant::whereBetween('created_at', [$lastMonth, $currentMonth])->count(),
            'active_tenants' => Tenant::where('is_active', true)->count(),
            'pending_bookings' => Booking::where('status', 'pending')->count(),
            'confirmed_bookings' => Booking::where('status', 'confirmed')->count(),
            'completed_bookings' => Booking::where('status', 'completed')->count(),
            'revenue_this_month' => Payment::where('payment_status', 'paid')
                ->where('paid_at', '>=', $currentMonth)
                ->sum('amount'),
            'revenue_last_month' => Payment::where('payment_status', 'paid')
                ->whereBetween('paid_at', [$lastMonth, $currentMonth])
                ->sum('amount'),
        ];
    }

    #[Computed]
    public function recentTenants()
    {
        return Tenant::orderBy('created_at', 'desc')
            ->take(5)
            ->get();
    }

    #[Computed]
    public function recentBookings()
    {
        return Booking::with(['tenant', 'customer'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
    }

    #[Computed]
    public function topTenantsByBookings()
    {
        return Tenant::withCount('bookings')
            ->orderBy('bookings_count', 'desc')
            ->take(5)
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

    #[Computed]
    public function tenantGrowthTrend()
    {
        $current = $this->stats['new_tenants_this_month'];
        $last = $this->stats['new_tenants_last_month'];
        
        if ($last == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $last) / $last) * 100, 1);
    }
};
?>

<div class="p-6 sm:p-10 max-w-7xl mx-auto space-y-8">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Platform Overview</h1>
            <p class="text-slate-500">Welcome back. Here's what's happening across your multi‑tenant platform.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('superadmin.tenants.create') }}" wire:navigate class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-lg shadow-sm transition-all flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Onboard Business
            </a>
            <a href="{{ route('superadmin.map-markers.index') }}" wire:navigate class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-medium py-2.5 px-5 rounded-lg shadow-sm transition-all flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                Map Control
            </a>
        </div>
    </div>

    {{-- Key Metrics Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        {{-- Total Tenants --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-slate-500">Total Businesses</h3>
                <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m3-4h1m-1 4h1m-5 8h8"></path></svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-slate-800">{{ number_format($this->stats['total_tenants']) }}</p>
            <div class="flex items-center gap-2 mt-2">
                <span class="text-xs font-medium {{ $this->tenantGrowthTrend >= 0 ? 'text-green-600' : 'text-red-600' }} flex items-center gap-0.5">
                    @if($this->tenantGrowthTrend >= 0)
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                    @else
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6"></path></svg>
                    @endif
                    {{ abs($this->tenantGrowthTrend) }}%
                </span>
                <span class="text-xs text-slate-400">vs last month</span>
            </div>
            <p class="text-xs text-slate-500 mt-1">{{ $this->stats['active_tenants'] }} active businesses</p>
        </div>

        {{-- Total Users --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-slate-500">Total Users</h3>
                <div class="p-2 bg-indigo-50 rounded-lg text-indigo-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-slate-800">{{ number_format($this->stats['total_users']) }}</p>
            <p class="text-xs text-slate-400 mt-2">Across all tenants</p>
        </div>

        {{-- Total Bookings --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-slate-500">Total Bookings</h3>
                <div class="p-2 bg-amber-50 rounded-lg text-amber-600">
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

        {{-- Monthly Revenue --}}
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
    </div>

    {{-- Secondary Stats Row --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-purple-50 rounded-lg text-purple-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">System Roles</p>
                    <p class="text-xl font-bold text-slate-800">{{ number_format($this->stats['total_roles']) }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-rose-50 rounded-lg text-rose-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Total Properties</p>
                    <p class="text-xl font-bold text-slate-800">{{ number_format($this->stats['total_properties']) }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-cyan-50 rounded-lg text-cyan-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">System Status</p>
                    <p class="text-xl font-bold text-emerald-600">Healthy</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Activity & Top Tenants --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Recent Bookings --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-200 flex justify-between items-center bg-slate-50">
                <h3 class="text-lg font-semibold text-slate-800">Recent Bookings</h3>
                <a href="{{ route('superadmin.tenants.index') }}" wire:navigate class="text-sm text-blue-600 hover:text-blue-800 font-medium">View All Tenants &rarr;</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Booking Ref</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Tenant</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse($this->recentBookings as $booking)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-slate-600">
                                    {{ $booking->booking_reference }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                    {{ $booking->tenant->name ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                    {{ $booking->customer->name ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-800">
                                    ₱{{ number_format($booking->total_amount, 2) }}
                                </td>
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
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-slate-500">No bookings yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Top Tenants & Recently Onboarded --}}
        <div class="space-y-6">
            {{-- Top Tenants by Bookings --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                    <h3 class="text-md font-semibold text-slate-800">Top Performing Businesses</h3>
                </div>
                <div class="divide-y divide-slate-200">
                    @forelse($this->topTenantsByBookings as $tenant)
                        <div class="px-6 py-4 flex items-center justify-between hover:bg-slate-50">
                            <div class="flex items-center gap-3">
                                <div class="h-8 w-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-sm">
                                    {{ substr($tenant->name, 0, 1) }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-800">{{ $tenant->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $tenant->bookings_count }} bookings</p>
                                </div>
                            </div>
                            <a href="{{ route('superadmin.tenants.edit', $tenant->id) }}" wire:navigate class="text-xs text-blue-600 hover:text-blue-800">View</a>
                        </div>
                    @empty
                        <div class="px-6 py-4 text-center text-slate-500 text-sm">No bookings yet.</div>
                    @endforelse
                </div>
            </div>

            {{-- Recently Onboarded --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
                    <h3 class="text-md font-semibold text-slate-800">Recently Onboarded</h3>
                    <a href="{{ route('superadmin.tenants.index') }}" wire:navigate class="text-xs text-blue-600 hover:text-blue-800">All &rarr;</a>
                </div>
                <div class="divide-y divide-slate-200">
                    @forelse($this->recentTenants as $tenant)
                        <div class="px-6 py-4 flex items-center justify-between hover:bg-slate-50">
                            <div class="flex items-center gap-3">
                                <div class="h-8 w-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-sm">
                                    {{ substr($tenant->name, 0, 1) }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-800">{{ $tenant->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $tenant->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                            <a href="{{ route('superadmin.tenants.edit', $tenant->id) }}" wire:navigate class="text-xs text-blue-600 hover:text-blue-800">Manage</a>
                        </div>
                    @empty
                        <div class="px-6 py-4 text-center text-slate-500 text-sm">No tenants yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>