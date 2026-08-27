{{-- resources/views/tenant/pages/dashboard/⚡dashboard-page.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Employee;
use App\Models\User;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new 
#[Layout('tenant.layouts.app')]
#[Title('Business Dashboard')]
class extends Component {

    public string $dateRange = 'this-month';
    public string $customStart = '';
    public string $customEnd = '';

    public function mount()
    {
        $this->customStart = now()->startOfMonth()->format('Y-m-d');
        $this->customEnd   = now()->endOfMonth()->format('Y-m-d');
    }

    protected function getDateRange(): array
    {
        $now = now();
        return match ($this->dateRange) {
            'today'     => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'last-7'    => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last-30'   => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'this-month'=> [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last-month'=> [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'custom'    => [
                Carbon::parse($this->customStart)->startOfDay(),
                Carbon::parse($this->customEnd)->endOfDay(),
            ],
            default     => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    #[Computed]
    public function stats()
    {
        [$start, $end] = $this->getDateRange();
        $tenantId = Auth::user()->tenant_id;

        $revenue = Payment::where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount');

        $totalBookings = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $totalGuests = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->distinct('user_id')
            ->count('user_id');

        $totalProperties = Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->count();

        $activeBookings = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->where('check_in', '<=', $end)
            ->where('check_out', '>', $start)
            ->count();

        $occupancy = $totalProperties > 0 ? round(($activeBookings / $totalProperties) * 100, 1) : 0;
        $avgBookingValue = $totalBookings > 0 ? round($revenue / $totalBookings, 2) : 0;

        $outstandingBalance = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->get()
            ->sum(function ($booking) {
                $paid = $booking->payments()->where('payment_status', 'paid')->sum('amount');
                return max(0, $booking->total_amount - $paid);
            });

        $repeatGuestRate = $this->getRepeatGuestRate($tenantId);

        return [
            'revenue'             => $revenue,
            'total_bookings'      => $totalBookings,
            'total_guests'        => $totalGuests,
            'occupancy_rate'      => $occupancy,
            'avg_booking_value'   => $avgBookingValue,
            'outstanding_balance' => $outstandingBalance,
            'repeat_guest_rate'   => $repeatGuestRate,
            'arrivals_today'      => Booking::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenantId)->whereDate('check_in', now())->count(),
            'departures_today'    => Booking::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenantId)->whereDate('check_out', now())->count(),
            'occupied_properties' => Property::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenantId)->where('status', 'occupied')->count(),
        ];
    }

    protected function getRepeatGuestRate(int $tenantId): float
    {
        $userCounts = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->select('user_id', DB::raw('COUNT(*) as bookings'))
            ->groupBy('user_id')
            ->pluck('bookings', 'user_id');

        $totalGuests = $userCounts->count();
        $repeatGuests = $userCounts->filter(fn($count) => $count > 1)->count();

        return $totalGuests > 0 ? round(($repeatGuests / $totalGuests) * 100, 1) : 0;
    }

    #[Computed]
    public function recentBookings()
    {
        return Booking::withoutGlobalScope(TenantScope::class)
            ->with('user')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
    }

    #[Computed]
    public function upcomingArrivals()
    {
        return Booking::withoutGlobalScope(TenantScope::class)
            ->with('user')
            ->where('tenant_id', Auth::user()->tenant_id)
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
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('payment_status', 'paid')
            ->whereNotNull('paid_at')
            ->orderBy('paid_at', 'desc')
            ->take(3)
            ->get();
    }

    public function updatedDateRange() { /* Livewire auto-refresh */ }
    public function updatedCustomStart() { }
    public function updatedCustomEnd() { }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-8"
     wire:poll.60s>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
                {{ Auth::user()->tenant->name ?? 'Dashboard' }}
            </h1>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('tenant.bookings.create') }}" wire:navigate
               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-lg shadow-primary-500/20 transition-all duration-200 hover:scale-105 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Booking
            </a>
            <a href="{{ route('tenant.analytics.index') }}" wire:navigate
               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm font-semibold transition-all duration-200 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                View Analytics
            </a>
        </div>
    </div>

    {{-- Date Range Selector --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
        <div class="flex flex-wrap items-center gap-2">
            @foreach([
                'today' => 'Today',
                'yesterday' => 'Yesterday',
                'last-7' => '7 Days',
                'last-30' => '30 Days',
                'this-month' => 'This Month',
                'last-month' => 'Last Month',
            ] as $val => $label)
                <button wire:click="$set('dateRange', '{{ $val }}')"
                        class="px-4 py-2 rounded-full text-xs font-semibold uppercase tracking-wider transition focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $dateRange === $val ? 'bg-primary-600 text-white shadow-md shadow-primary-600/20' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                    {{ $label }}
                </button>
            @endforeach
            <button wire:click="$set('dateRange', 'custom')"
                    class="px-4 py-2 rounded-full text-xs font-semibold uppercase tracking-wider transition focus-visible:ring-2 focus-visible:ring-primary-500/50
                           {{ $dateRange === 'custom' ? 'bg-primary-600 text-white shadow-md shadow-primary-600/20' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                Custom
            </button>
            @if($dateRange === 'custom')
                <div class="flex gap-2 items-center">
                    <input type="date" wire:model.live="customStart"
                           class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                    <span class="text-gray-500 dark:text-gray-400">to</span>
                    <input type="date" wire:model.live="customEnd"
                           class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50">
                </div>
            @endif
        </div>
    </div>

    {{-- Stats Cards --}}
    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Revenue</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">₱{{ number_format($s['revenue'], 2) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Bookings</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $s['total_bookings'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Occupancy</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $s['occupancy_rate'] }}%</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Avg Booking</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">₱{{ number_format($s['avg_booking_value'], 2) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-amber-600 dark:text-amber-400 uppercase tracking-wider">Outstanding</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-2">₱{{ number_format($s['outstanding_balance'], 2) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Repeat Guests</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $s['repeat_guest_rate'] }}%</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Arrivals</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $s['arrivals_today'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Departures</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $s['departures_today'] }}</p>
        </div>
    </div>

    {{-- Recent Bookings --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h2 class="font-bold text-gray-900 dark:text-white">Recent Bookings</h2>
            <a href="{{ route('tenant.bookings.index') }}" wire:navigate class="text-sm font-semibold text-primary-600 hover:underline focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">View all →</a>
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
                                    {{ $b->status === 'confirmed' ? 'bg-primary-100 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300 border border-primary-200 dark:border-primary-500/30' : '' }}
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

    {{-- Upcoming Arrivals & Recent Payments --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
            <h2 class="font-bold text-gray-900 dark:text-white mb-4">Upcoming Arrivals</h2>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($this->upcomingArrivals as $b)
                    <div class="py-3 flex justify-between items-center">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $b->user->name ?? 'Guest' }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $b->check_in->format('M d, Y') }} · {{ $b->booking_reference }}</p>
                        </div>
                        <span class="text-xs text-green-600 dark:text-green-400 font-medium">Confirmed</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400 py-3">No arrivals soon.</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
            <h2 class="font-bold text-gray-900 dark:text-white mb-4">Recent Payments</h2>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($this->recentPayments as $p)
                    <div class="py-3 flex justify-between items-center">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">₱{{ number_format($p->amount, 2) }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $p->paid_at?->format('M d') ?? '—' }} · {{ $p->reference_number ?? '—' }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400 py-3">No payments yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 shadow-sm">
        <h3 class="font-bold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <a href="{{ route('tenant.services.create') }}" wire:navigate
               class="flex items-center justify-center gap-2 p-3 rounded-xl bg-primary-50 dark:bg-primary-500/10 hover:bg-primary-100 dark:hover:bg-primary-500/20 text-primary-600 dark:text-primary-400 text-sm font-medium transition-colors border border-primary-200 dark:border-primary-500/20 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Add Service
            </a>
            <a href="{{ route('tenant.employees.create') }}" wire:navigate
               class="flex items-center justify-center gap-2 p-3 rounded-xl bg-primary-50 dark:bg-primary-500/10 hover:bg-primary-100 dark:hover:bg-primary-500/20 text-primary-600 dark:text-primary-400 text-sm font-medium transition-colors border border-primary-200 dark:border-primary-500/20 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                Add Employee
            </a>
            <a href="{{ route('tenant.payments.index') }}" wire:navigate
               class="flex items-center justify-center gap-2 p-3 rounded-xl bg-primary-50 dark:bg-primary-500/10 hover:bg-primary-100 dark:hover:bg-primary-500/20 text-primary-600 dark:text-primary-400 text-sm font-medium transition-colors border border-primary-200 dark:border-primary-500/20 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Payments
            </a>
            <a href="{{ route('tenant.settings.index') }}" wire:navigate
               class="flex items-center justify-center gap-2 p-3 rounded-xl bg-primary-50 dark:bg-primary-500/10 hover:bg-primary-100 dark:hover:bg-primary-500/20 text-primary-600 dark:text-primary-400 text-sm font-medium transition-colors border border-primary-200 dark:border-primary-500/20 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Settings
            </a>
        </div>
    </div>
</div>