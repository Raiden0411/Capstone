<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Booking;
use Spatie\Permission\Models\Role;

new 
#[Layout('superadmin.layouts.app')]
#[Title('Platform Dashboard')]
class extends Component {
    
    #[Computed]
    public function stats()
    {
        return [
            'total_tenants'     => Tenant::count(),
            'active_tenants'    => Tenant::where('is_active', true)->count(),
            'pending_tenants'   => Tenant::where('is_active', false)->count(),
            'total_users'       => User::count(),
            'total_roles'       => Role::where('name', '!=', 'super-admin')->count(),
            'new_this_week'     => Tenant::where('created_at', '>=', now()->subDays(7))->count(),
            'total_bookings'    => Booking::count(),
            'pending_bookings'  => Booking::where('status', 'pending')->count(),
        ];
    }

    #[Computed]
    public function recentTenants()
    {
        return Tenant::orderBy('created_at', 'desc')->take(6)->get();
    }
};
?>

<div class="p-6 space-y-8 text-gray-900 dark:text-white">

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Platform Health -->
        <div class="relative overflow-hidden rounded-2xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-6 shadow-sm dark:shadow-xl transition-colors">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">Platform Health</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400">Active</span>
            </div>
            <div class="text-4xl font-extrabold text-gray-900 dark:text-white">{{ $this->stats['active_tenants'] }}/{{ $this->stats['total_tenants'] }}</div>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">Active businesses</p>
            <div class="mt-4 h-1.5 bg-gray-200 dark:bg-slate-700/50 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-emerald-500 to-emerald-400 dark:from-emerald-400 dark:to-emerald-300 rounded-full transition-all duration-500" style="width: {{ $this->stats['total_tenants'] > 0 ? round(($this->stats['active_tenants'] / $this->stats['total_tenants']) * 100) : 0 }}%"></div>
            </div>
        </div>

        <!-- Pending Approval -->
        <div class="relative overflow-hidden rounded-2xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-6 shadow-sm dark:shadow-xl transition-colors">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">Pending Approval</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400">{{ $this->stats['pending_tenants'] }}</span>
            </div>
            <div class="text-4xl font-extrabold text-gray-900 dark:text-white">{{ $this->stats['pending_tenants'] }}</div>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">Awaiting activation</p>
            <div class="mt-4 h-1.5 bg-gray-200 dark:bg-slate-700/50 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-amber-500 to-amber-400 dark:from-amber-400 dark:to-amber-300 rounded-full transition-all duration-500" style="width: {{ $this->stats['total_tenants'] > 0 ? round(($this->stats['pending_tenants'] / $this->stats['total_tenants']) * 100) : 0 }}%"></div>
            </div>
        </div>

        <!-- Total Bookings (count only) -->
        <div class="relative overflow-hidden rounded-2xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-6 shadow-sm dark:shadow-xl transition-colors">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">Total Bookings</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400">{{ $this->stats['total_bookings'] }}</span>
            </div>
            <div class="text-4xl font-extrabold text-gray-900 dark:text-white">{{ $this->stats['total_bookings'] }}</div>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">{{ $this->stats['pending_bookings'] }} pending</p>
            <div class="mt-4 h-1.5 bg-gray-200 dark:bg-slate-700/50 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-blue-500 to-blue-400 dark:from-blue-400 dark:to-blue-300 rounded-full transition-all duration-500" style="width: {{ $this->stats['total_bookings'] > 0 ? round(($this->stats['pending_bookings'] / $this->stats['total_bookings']) * 100) : 0 }}%"></div>
            </div>
        </div>

        <!-- New This Week -->
        <div class="relative overflow-hidden rounded-2xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-6 shadow-sm dark:shadow-xl transition-colors">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-slate-400">New This Week</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-700 dark:bg-purple-500/20 dark:text-purple-400">{{ $this->stats['new_this_week'] }}</span>
            </div>
            <div class="text-4xl font-extrabold text-gray-900 dark:text-white">{{ $this->stats['new_this_week'] }}</div>
            <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">Businesses onboarded</p>
            <div class="mt-4 h-1.5 bg-gray-200 dark:bg-slate-700/50 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-purple-500 to-purple-400 dark:from-purple-400 dark:to-purple-300 rounded-full transition-all duration-500" style="width: {{ $this->stats['total_tenants'] > 0 ? round(($this->stats['new_this_week'] / $this->stats['total_tenants']) * 100) : 0 }}%"></div>
            </div>
        </div>
    </div>

    {{-- System Overview (full width) --}}
    <div class="rounded-2xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-6 shadow-sm dark:shadow-xl transition-colors">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">System Overview</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
            <div class="py-2 border-b border-gray-200 dark:border-slate-700/30">
                <span class="text-sm text-gray-500 dark:text-slate-400">Total Users</span>
                <p class="font-bold text-xl text-gray-900 dark:text-white">{{ $this->stats['total_users'] }}</p>
            </div>
            <div class="py-2 border-b border-gray-200 dark:border-slate-700/30">
                <span class="text-sm text-gray-500 dark:text-slate-400">System Roles</span>
                <p class="font-bold text-xl text-gray-900 dark:text-white">{{ $this->stats['total_roles'] }}</p>
            </div>
            <div class="py-2 border-b border-gray-200 dark:border-slate-700/30">
                <span class="text-sm text-gray-500 dark:text-slate-400">Total Businesses</span>
                <p class="font-bold text-xl text-gray-900 dark:text-white">{{ $this->stats['total_tenants'] }}</p>
            </div>
            <div class="py-2 border-b border-gray-200 dark:border-slate-700/30">
                <span class="text-sm text-gray-500 dark:text-slate-400">Bookings</span>
                <p class="font-bold text-xl text-gray-900 dark:text-white">{{ $this->stats['total_bookings'] }}</p>
            </div>
            <div class="py-2">
                <span class="text-sm text-gray-500 dark:text-slate-400">Pending Bookings</span>
                <p class="font-bold text-xl text-amber-600 dark:text-amber-400">{{ $this->stats['pending_bookings'] }}</p>
            </div>
        </div>
    </div>

    {{-- Recently Onboarded --}}
    <div class="rounded-2xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-6 shadow-sm dark:shadow-xl transition-colors">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recently Onboarded</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($this->recentTenants as $tenant)
                <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-slate-800/50 border border-gray-200 dark:border-slate-700/30 hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors">
                    <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-600/30 flex items-center justify-center text-blue-600 dark:text-blue-400 font-bold text-sm shrink-0">
                        {{ substr($tenant->name, 0, 1) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $tenant->name }}</p>
                        <p class="text-xs text-gray-400 dark:text-slate-500">{{ $tenant->created_at->diffForHumans() }}</p>
                    </div>
                    <a href="{{ route('superadmin.tenants.edit', $tenant->id) }}" wire:navigate class="text-xs text-blue-600 dark:text-blue-400 hover:underline">Manage</a>
                </div>
            @empty
                <p class="text-gray-400 dark:text-slate-500 text-sm col-span-full">No tenants onboarded yet.</p>
            @endforelse
        </div>
    </div>
</div>