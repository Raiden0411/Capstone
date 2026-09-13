{{-- resources/views/superadmin/pages/dashboard/⚡dashboard-page.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Event;
use Spatie\Permission\Models\Role;

new
#[Layout('superadmin.layouts.app')]
#[Title('Platform Dashboard')]
class extends Component
{
    public function mount(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');
    }

    // ─────────────────────────────────────────────────────────
    //  Stats — consolidated aggregates
    // ─────────────────────────────────────────────────────────

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        $now          = now();
        $weekAgo      = $now->copy()->subDays(7);
        $startOfMonth = $now->copy()->startOfMonth();

        // Tenants — 1 aggregate query
        $tenantStats = Tenant::query()
            ->selectRaw('
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) as active,
                COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END), 0) as pending,
                COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as new_this_week,
                COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as new_this_month
            ', [$weekAgo, $startOfMonth])
            ->first();

        // Events — 1 aggregate query
        $eventStats = Event::query()
            ->selectRaw('
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN start_date >= ? AND is_active = 1 THEN 1 ELSE 0 END), 0) as upcoming,
                COALESCE(SUM(CASE WHEN featured = 1 AND is_active = 1 THEN 1 ELSE 0 END), 0) as featured
            ', [$now])
            ->first();

        return [
            'total_tenants'   => (int) ($tenantStats?->total ?? 0),
            'active_tenants'  => (int) ($tenantStats?->active ?? 0),
            'pending_tenants' => (int) ($tenantStats?->pending ?? 0),
            'new_this_week'   => (int) ($tenantStats?->new_this_week ?? 0),
            'new_this_month'  => (int) ($tenantStats?->new_this_month ?? 0),
            'total_users'     => User::query()->count(),
            'total_roles'     => Role::query()->where('name', '!=', 'super-admin')->count(),
            'total_events'    => (int) ($eventStats?->total ?? 0),
            'upcoming_events' => (int) ($eventStats?->upcoming ?? 0),
            'featured_events' => (int) ($eventStats?->featured ?? 0),
        ];
    }

    #[Computed]
    public function recentTenants()
    {
        return Tenant::query()
            ->with('typeOfTenant:id,type')
            ->select('id', 'name', 'slug', 'type_of_tenant_id', 'is_active', 'created_at')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();
    }

    #[Computed]
    public function recentUsers()
    {
        return User::query()
            ->select('id', 'name', 'email', 'created_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
    }

    /**
     * The next 3 upcoming active events (public visibility).
     */
    #[Computed]
    public function upcomingEvents()
    {
        return Event::query()
            ->select('id', 'name', 'start_date', 'barangay', 'type', 'image_path')
            ->where('is_active', true)
            ->where('start_date', '>=', now())
            ->orderBy('start_date')
            ->limit(3)
            ->get();
    }

    /**
     * Tenant registration counts for the last 6 months.
     *
     * ONE grouped query instead of six per-month counts.
     *
     * @return array<int, array{label: string, value: int}>
     */
    #[Computed]
    public function tenantSparkline(): array
    {
        $startMonth = now()->startOfMonth()->subMonths(5);

        $counts = Tenant::query()
            ->where('created_at', '>=', $startMonth)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $date   = now()->startOfMonth()->subMonths($i);
            $key    = $date->format('Y-m');
            $data[] = [
                'label' => $date->format('M'),
                'value' => (int) $counts->get($key, 0),
            ];
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function systemInfo(): array
    {
        return [
            'php'         => PHP_VERSION,
            'laravel'     => app()->version(),
            'environment' => (string) app()->environment(),
            'debug'       => config('app.debug') ? 'On' : 'Off',
            'cache'       => (string) config('cache.default'),
            'queue'       => (string) config('queue.default'),
        ];
    }

    #[Computed]
    public function serverTime(): \Carbon\Carbon
    {
        return now();
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1600px] mx-auto space-y-8" wire:poll.60s>

    {{-- ═══════════════ HEADER ═══════════════ --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-2 border-b border-gray-200/80 dark:border-gray-800">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white">
                    Platform Dashboard
                </h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                             bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-400
                             border border-emerald-200 dark:border-emerald-800">
                    <span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-emerald-500 animate-pulse motion-reduce:animate-none"></span>
                    Live · 60s
                </span>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Super Admin Overview · {{ $this->serverTime->format('F j, Y') }}
            </p>
        </div>

        <div class="text-left md:text-right">
            <div class="text-xs text-gray-500 dark:text-gray-400">
                System time
                <span class="text-gray-700 dark:text-gray-200 font-medium tabular-nums">
                    {{ $this->serverTime->format('D, d M Y · H:i') }}
                </span>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Environment
                <span class="text-gray-700 dark:text-gray-200 font-medium">{{ app()->environment() }}</span>
            </div>
        </div>
    </div>

    {{-- ═══════════════ QUICK ACTIONS ═══════════════ --}}
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('superadmin.tenants.create') }}" wire:navigate
           class="btn-primary text-sm active:scale-95 transition-transform
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                  inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Tenant
        </a>
        <a href="{{ route('superadmin.users.index') }}" wire:navigate
           class="btn-secondary text-sm active:scale-95 transition-transform
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                  inline-flex items-center gap-2">
            Manage Users
        </a>
        <a href="{{ route('superadmin.analytics') }}" wire:navigate
           class="btn-secondary text-sm active:scale-95 transition-transform
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                  inline-flex items-center gap-2">
            View Reports
        </a>
        <a href="{{ route('superadmin.homepage.editor') }}" wire:navigate
           class="btn-secondary text-sm active:scale-95 transition-transform
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                  inline-flex items-center gap-2">
            Edit Site Settings
        </a>
        <a href="{{ route('superadmin.events.index') }}" wire:navigate
           class="btn-secondary text-sm active:scale-95 transition-transform
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                  inline-flex items-center gap-2">
            Manage Events
        </a>
    </div>

    {{-- ═══════════════ KPI CARDS ═══════════════ --}}
    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Tenants --}}
        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm
                    hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Tenants</p>
                <div class="p-2 bg-primary-50 dark:bg-primary-950/50 rounded-xl text-primary-600 dark:text-primary-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2 tabular-nums">{{ number_format($s['total_tenants']) }}</p>
            <p class="text-xs font-medium text-emerald-600 dark:text-emerald-400 mt-2">{{ $s['active_tenants'] }} active</p>
        </div>

        {{-- Active Tenants --}}
        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm
                    hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Active</p>
                <div class="p-2 bg-emerald-50 dark:bg-emerald-950/50 rounded-xl text-emerald-600 dark:text-emerald-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2 tabular-nums">{{ number_format($s['active_tenants']) }}</p>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-2">Operational</p>
        </div>

        {{-- Pending --}}
        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm
                    hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pending</p>
                <div class="p-2 bg-amber-50 dark:bg-amber-950/50 rounded-xl text-amber-600 dark:text-amber-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-2 tabular-nums">{{ number_format($s['pending_tenants']) }}</p>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-2">Awaiting action</p>
        </div>

        {{-- New This Week --}}
        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm
                    hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">New (Week)</p>
                <div class="p-2 bg-purple-50 dark:bg-purple-950/50 rounded-xl text-purple-600 dark:text-purple-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2 tabular-nums">{{ number_format($s['new_this_week']) }}</p>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-2">Onboarded</p>
        </div>
    </div>

    {{-- ═══════════════ EVENTS OVERVIEW ═══════════════ --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-gray-800/90 p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Events</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2 tabular-nums">{{ number_format($s['total_events']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800/90 p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Upcoming</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2 tabular-nums">{{ number_format($s['upcoming_events']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800/90 p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Featured</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2 tabular-nums">{{ number_format($s['featured_events']) }}</p>
        </div>
    </div>

    {{-- ═══════════════ PENDING APPROVAL CALLOUT ═══════════════ --}}
    @if($s['pending_tenants'] > 0)
        <div class="bg-amber-50 dark:bg-amber-900/20 border-l-4 border-amber-500 rounded-2xl p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="font-semibold text-amber-700 dark:text-amber-400">Action Required: Pending Approvals</p>
                    <p class="text-sm text-amber-600/80 dark:text-amber-300 mt-1">
                        {{ $s['pending_tenants'] }} {{ \Illuminate\Support\Str::plural('business', $s['pending_tenants']) }} waiting for activation.
                    </p>
                </div>
                <a href="{{ route('superadmin.tenants.index') }}" wire:navigate
                   class="inline-flex items-center gap-1 px-4 py-2 rounded-full
                          bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300
                          text-sm font-semibold border border-amber-200 dark:border-amber-500/30
                          hover:bg-amber-200 dark:hover:bg-amber-500/30 transition active:scale-95
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50">
                    Review Tenants
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        </div>
    @endif

    {{-- ═══════════════ TENANT GROWTH SPARKLINE ═══════════════ --}}
    <div class="bg-white dark:bg-gray-800/90 p-6 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Tenant Growth</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">New tenant registrations over the last 6 months.</p>
            </div>
            <div class="hidden sm:flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                <span class="w-3 h-3 rounded-full bg-cyan-500 inline-block"></span>
                New Tenants
            </div>
        </div>
        <div id="chart-container"
             data-sparkline="{{ json_encode($this->tenantSparkline, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
             class="w-full h-40 relative"
             wire:ignore>
            <canvas id="sparklineChart"></canvas>
        </div>
    </div>

    {{-- ═══════════════ RECENT ACTIVITY ═══════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Recent Tenants --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm overflow-hidden flex flex-col">
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <h2 class="font-bold text-gray-900 dark:text-white">Recently Onboarded</h2>
                <a href="{{ route('superadmin.tenants.index') }}" wire:navigate
                   class="inline-flex items-center gap-1 text-sm font-semibold text-primary-600 dark:text-primary-400
                          hover:text-primary-700 dark:hover:text-primary-300
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded
                          active:scale-95 transition">
                    View all
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
            <div class="p-6 space-y-3 flex-1">
                @forelse($this->recentTenants as $tenant)
                    <div wire:key="tenant-{{ $tenant->id }}"
                         class="flex items-center gap-3 p-3 rounded-xl
                                bg-gray-50 dark:bg-gray-800/50 border border-gray-200/60 dark:border-gray-700
                                hover:bg-gray-100 dark:hover:bg-gray-700/60 transition-colors">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-500/10
                                    border border-blue-200 dark:border-blue-500/20
                                    flex items-center justify-center font-bold text-sm
                                    text-blue-700 dark:text-blue-400 shrink-0">
                            {{ strtoupper(substr($tenant->name, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $tenant->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $tenant->created_at->diffForHumans() }}</p>
                        </div>
                        <a href="{{ route('superadmin.tenants.edit', $tenant->id) }}" wire:navigate
                           class="text-xs font-semibold text-primary-600 dark:text-primary-400
                                  hover:text-primary-700 dark:hover:text-primary-300 shrink-0 p-1 rounded
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 transition">
                            Manage
                        </a>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-8 text-center text-gray-500 dark:text-gray-400">
                        <svg class="w-10 h-10 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <p class="text-sm">No tenants onboarded yet.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Recent Users --}}
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm overflow-hidden flex flex-col">
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <h2 class="font-bold text-gray-900 dark:text-white">Recent User Registrations</h2>
                <a href="{{ route('superadmin.users.index') }}" wire:navigate
                   class="inline-flex items-center gap-1 text-sm font-semibold text-primary-600 dark:text-primary-400
                          hover:text-primary-700 dark:hover:text-primary-300
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded
                          active:scale-95 transition">
                    View all
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
            <div class="p-6 space-y-3 flex-1">
                @forelse($this->recentUsers as $user)
                    <div wire:key="user-{{ $user->id }}"
                         class="flex items-center gap-3 p-3 rounded-xl
                                bg-gray-50 dark:bg-gray-800/50 border border-gray-200/60 dark:border-gray-700
                                hover:bg-gray-100 dark:hover:bg-gray-700/60 transition-colors">
                        <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-500/10
                                    border border-purple-200 dark:border-purple-500/20
                                    flex items-center justify-center font-bold text-sm
                                    text-purple-700 dark:text-purple-400 shrink-0">
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $user->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $user->email }}</p>
                        </div>
                        <span class="text-[10px] text-gray-500 dark:text-gray-400 shrink-0
                                     bg-white dark:bg-gray-900 px-2 py-1 rounded-md
                                     border border-gray-200 dark:border-gray-700">
                            {{ $user->created_at->diffForHumans() }}
                        </span>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-8 text-center text-gray-500 dark:text-gray-400">
                        <svg class="w-10 h-10 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <p class="text-sm">No users registered yet.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ═══════════════ UPCOMING EVENTS ═══════════════ --}}
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <div>
                <h2 class="font-bold text-gray-900 dark:text-white">Upcoming Events</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Next active events across the platform.</p>
            </div>
            <a href="{{ route('superadmin.events.index') }}" wire:navigate
               class="inline-flex items-center gap-1 text-sm font-semibold text-primary-600 dark:text-primary-400
                      hover:text-primary-700 dark:hover:text-primary-300
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded
                      active:scale-95 transition">
                View all
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

        @if($this->upcomingEvents->isNotEmpty())
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($this->upcomingEvents as $event)
                    <a href="{{ route('superadmin.events.edit', $event) }}" wire:navigate
                       wire:key="upcoming-{{ $event->id }}"
                       class="group flex items-start gap-3 p-3 rounded-xl
                              bg-gray-50 dark:bg-gray-800/50 border border-gray-200/60 dark:border-gray-700
                              hover:bg-gray-100 dark:hover:bg-gray-700/60 hover:border-primary-200 dark:hover:border-primary-500/30
                              transition-colors
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <div class="w-14 h-14 rounded-lg overflow-hidden shrink-0
                                    bg-gradient-to-br from-primary-100 to-primary-50 dark:from-primary-900/40 dark:to-primary-800/20
                                    border border-primary-200/60 dark:border-primary-500/20 flex items-center justify-center">
                            @if($event->image_path)
                                <img src="{{ asset('storage/' . $event->image_path) }}"
                                     class="w-full h-full object-cover"
                                     alt="{{ $event->name }}">
                            @else
                                <svg class="w-6 h-6 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                                {{ $event->type }}
                            </p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate mt-0.5">
                                {{ $event->name }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-1">
                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                {{ $event->start_date?->format('M d, Y') ?? '—' }}
                            </p>
                            @if($event->barangay)
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">{{ $event->barangay }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="p-12 text-center">
                <div class="w-14 h-14 mx-auto rounded-full bg-gray-100 dark:bg-gray-800
                            flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">No upcoming events</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Nothing scheduled at the moment.</p>
            </div>
        @endif
    </div>

    {{-- ═══════════════ SYSTEM OVERVIEW ═══════════════ --}}
    @php $sys = $this->systemInfo; @endphp
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">System Overview</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            @php
                $infoCells = [
                    ['label' => 'PHP',         'value' => $sys['php']],
                    ['label' => 'Laravel',     'value' => $sys['laravel']],
                    ['label' => 'Environment', 'value' => $sys['environment']],
                    ['label' => 'Debug',       'value' => $sys['debug']],
                    ['label' => 'Cache',       'value' => $sys['cache']],
                    ['label' => 'Queue',       'value' => $sys['queue']],
                ];
            @endphp
            @foreach($infoCells as $cell)
                <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-4 border border-gray-200/60 dark:border-gray-700/50">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $cell['label'] }}</p>
                    <p class="text-sm font-bold text-gray-900 dark:text-white mt-1.5 font-mono truncate" title="{{ $cell['value'] }}">
                        {{ $cell['value'] }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    // ──────────────────────────────────────────────────────────
    //  Module-level state on `window` so SPA re-registrations
    //  can clean up the previous instance before rebuilding.
    // ──────────────────────────────────────────────────────────
    if (window.__sparklineChart) {
        try { window.__sparklineChart.destroy(); } catch (e) { /* noop */ }
        window.__sparklineChart = null;
    }

    function getTheme() {
        const isDark = document.documentElement.classList.contains('dark');
        return {
            textColor:   isDark ? '#9ca3af' : '#4b5563',
            gridColor:   isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)',
            lineColor:   isDark ? '#22d3ee' : '#0891b2',
            fillColor:   isDark ? 'rgba(34,211,238,0.15)' : 'rgba(8,145,178,0.12)',
            tooltipBg:   isDark ? '#1f2937' : '#ffffff',
            tooltipText: isDark ? '#f3f4f6' : '#111827',
        };
    }

    function getData() {
        const container = document.getElementById('chart-container');
        if (!container || !container.dataset.sparkline) return [];

        try {
            return JSON.parse(container.dataset.sparkline);
        } catch (e) {
            console.error('Sparkline data parse failed', e);
            return [];
        }
    }

    function buildOptions(theme) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend:  { display: false },
                tooltip: {
                    backgroundColor: theme.tooltipBg,
                    titleColor:      theme.tooltipText,
                    bodyColor:       theme.textColor,
                    padding:         10,
                    cornerRadius:    8,
                    displayColors:   false,
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks:  { color: theme.textColor, precision: 0, font: { size: 11 } },
                    grid:   { color: theme.gridColor, borderDash: [4, 4] },
                    border: { display: false },
                },
                x: {
                    ticks:  { color: theme.textColor, font: { size: 11 } },
                    grid:   { display: false },
                    border: { display: false },
                },
            },
            interaction: { intersect: false, mode: 'index' },
        };
    }

    window.renderDashboardSparkline = function () {
        if (typeof Chart === 'undefined') {
            setTimeout(window.renderDashboardSparkline, 100);
            return;
        }

        const canvas = document.getElementById('sparklineChart');
        if (!canvas) return;

        const data = getData();
        if (!data.length) return;

        const theme = getTheme();
        const labels = data.map(d => d.label);
        const values = data.map(d => d.value);

        // Update in place if the chart still points at the same canvas.
        if (window.__sparklineChart && window.__sparklineChart.canvas === canvas) {
            const chart = window.__sparklineChart;
            chart.data.labels = labels;
            chart.data.datasets[0].data = values;
            chart.data.datasets[0].borderColor = theme.lineColor;
            chart.data.datasets[0].backgroundColor = theme.fillColor;
            chart.data.datasets[0].pointBackgroundColor = theme.lineColor;
            chart.options.scales.y.ticks.color = theme.textColor;
            chart.options.scales.y.grid.color  = theme.gridColor;
            chart.options.scales.x.ticks.color = theme.textColor;
            chart.options.plugins.tooltip.backgroundColor = theme.tooltipBg;
            chart.options.plugins.tooltip.titleColor      = theme.tooltipText;
            chart.options.plugins.tooltip.bodyColor       = theme.textColor;
            chart.update('none');
            return;
        }

        // Otherwise destroy + create.
        if (window.__sparklineChart) {
            try { window.__sparklineChart.destroy(); } catch (e) { /* noop */ }
        }

        window.__sparklineChart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    data: values,
                    borderColor: theme.lineColor,
                    borderWidth: 2.5,
                    tension: 0.4,
                    fill: true,
                    backgroundColor: theme.fillColor,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: theme.lineColor,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                }],
            },
            options: buildOptions(theme),
        });
    };

    // First render.
    window.renderDashboardSparkline();

    // Hook into Livewire morphs + theme changes ONCE per page load.
    if (!window.__sparklineHooked) {
        window.__sparklineHooked = true;

        document.addEventListener('livewire:init', () => {
            Livewire.hook('morph.updated', ({ el }) => {
                if (el && el.id === 'chart-container') {
                    window.renderDashboardSparkline();
                }
            });
        });

        let lastDark = document.documentElement.classList.contains('dark');
        new MutationObserver(() => {
            const isDark = document.documentElement.classList.contains('dark');
            if (isDark !== lastDark) {
                lastDark = isDark;
                window.renderDashboardSparkline();
            }
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    }
})();
</script>