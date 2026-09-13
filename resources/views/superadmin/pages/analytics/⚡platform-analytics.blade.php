{{-- resources/views/superadmin/pages/analytics/⚡platform-analytics.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Booking;
use App\Scopes\TenantScope;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

new
#[Layout('superadmin.layouts.app')]
#[Title('Platform Analytics')]
class extends Component
{
    public ?string $startDate = null;
    public ?string $endDate   = null;
    public string  $preset    = 'this_year';

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');
        $this->applyPreset('this_year');
    }

    public function applyPreset(string $preset): void
    {
        $this->preset = $preset;
        $now          = now();

        switch ($preset) {
            case 'today':
                $this->startDate = $now->toDateString();
                $this->endDate   = $now->toDateString();
                break;

            case '7d':
                $this->startDate = $now->copy()->subDays(6)->toDateString();
                $this->endDate   = $now->toDateString();
                break;

            case '30d':
                $this->startDate = $now->copy()->subDays(29)->toDateString();
                $this->endDate   = $now->toDateString();
                break;

            case 'this_month':
                $this->startDate = $now->copy()->startOfMonth()->toDateString();
                $this->endDate   = $now->copy()->endOfMonth()->toDateString();
                break;

            case 'this_year':
            default:
                $this->startDate = $now->copy()->startOfYear()->toDateString();
                $this->endDate   = $now->copy()->endOfYear()->toDateString();
                break;

            case 'custom':
                // Leave dates untouched.
                break;
        }
    }

    public function updatedStartDate(): void
    {
        $this->preset = 'custom';
    }

    public function updatedEndDate(): void
    {
        $this->preset = 'custom';
    }

    // ─────────────────────────────────────────────────────────
    //  Computed
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

        $tenantStats = Tenant::query()
            ->selectRaw('
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) as active,
                COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END), 0) as pending,
                COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as new_this_week,
                COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as new_this_month
            ', [$weekAgo, $startOfMonth])
            ->first();

        $userStats = User::query()
            ->selectRaw('
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) as active,
                COALESCE(SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END), 0) as new_this_month
            ', [$startOfMonth])
            ->first();

        return [
            'total_tenants'        => (int) ($tenantStats?->total ?? 0),
            'active_tenants'       => (int) ($tenantStats?->active ?? 0),
            'pending_tenants'      => (int) ($tenantStats?->pending ?? 0),
            'new_this_month'       => (int) ($tenantStats?->new_this_month ?? 0),
            'new_this_week'        => (int) ($tenantStats?->new_this_week ?? 0),
            'total_users'          => (int) ($userStats?->total ?? 0),
            'active_users'         => (int) ($userStats?->active ?? 0),
            'new_users_this_month' => (int) ($userStats?->new_this_month ?? 0),
        ];
    }

    /**
     * @return array{labels: array<int, string>, tenants: array<int, int>, users: array<int, int>, isDaily: bool}
     */
    #[Computed]
    public function chartData(): array
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : now()->startOfYear();
        $end   = $this->endDate   ? Carbon::parse($this->endDate)->endOfDay()     : now()->endOfYear();

        $isDaily = $start->diffInDays($end) <= 31;

        $phpFormat   = $isDaily ? 'Y-m-d' : 'Y-m';
        $sqlFormat   = $isDaily ? '%Y-%m-%d' : '%Y-%m';
        $labelFormat = $isDaily ? 'M d' : 'M Y';

        $period = CarbonPeriod::create(
            $isDaily ? $start : $start->copy()->startOfMonth(),
            $isDaily ? '1 day' : '1 month',
            $isDaily ? $end   : $end->copy()->endOfMonth(),
        );

        $labels = [];
        $keys   = [];

        foreach ($period as $dt) {
            $keys[]   = $dt->format($phpFormat);
            $labels[] = $dt->format($labelFormat);
        }

        $tenantGrowth = Tenant::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("DATE_FORMAT(created_at, '{$sqlFormat}') as period, COUNT(*) as total")
            ->groupBy('period')
            ->pluck('total', 'period');

        $userGrowth = User::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("DATE_FORMAT(created_at, '{$sqlFormat}') as period, COUNT(*) as total")
            ->groupBy('period')
            ->pluck('total', 'period');

        $tenants = [];
        $users   = [];

        foreach ($keys as $key) {
            $tenants[] = (int) $tenantGrowth->get($key, 0);
            $users[]   = (int) $userGrowth->get($key, 0);
        }

        return [
            'labels'  => $labels,
            'tenants' => $tenants,
            'users'   => $users,
            'isDaily' => $isDaily,
        ];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>, colors: array<int, string>}
     */
    #[Computed]
    public function tenantStatusData(): array
    {
        $stats = $this->stats;

        return [
            'labels' => ['Active', 'Pending'],
            'values' => [$stats['active_tenants'], $stats['pending_tenants']],
            'colors' => ['#10b981', '#f59e0b'],
        ];
    }

    /**
     * Top 3 most-booked tourist spots — the "Top Picks" chart.
     *
     * @return array{labels: array<int, string>, values: array<int, int>, colors: array<int, string>}
     */
    #[Computed]
    public function topSpotsData(): array
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : now()->startOfYear();
        $end   = $this->endDate   ? Carbon::parse($this->endDate)->endOfDay()     : now()->endOfYear();

        $topSpots = Booking::query()
            ->withoutGlobalScope(TenantScope::class)
            ->join('tenants', 'bookings.tenant_id', '=', 'tenants.id')
            ->whereBetween('bookings.created_at', [$start, $end])
            ->whereNotIn('bookings.status', [Booking::STATUS_CANCELLED])
            ->select(
                'tenants.id',
                'tenants.name',
                DB::raw('COUNT(DISTINCT bookings.id) as total_bookings'),
            )
            ->groupBy('tenants.id', 'tenants.name')
            ->orderByDesc('total_bookings')
            ->limit(3)
            ->get();

        return [
            'labels' => $topSpots->pluck('name')->all(),
            'values' => $topSpots->pluck('total_bookings')->map(fn ($v) => (int) $v)->all(),
            // Gold / silver / bronze — reinforces the podium feel for 3 bars.
            'colors' => ['#f59e0b', '#94a3b8', '#b45309'],
        ];
    }

    #[Computed]
    public function activeRate(): int
    {
        $stats = $this->stats;

        if ($stats['total_tenants'] <= 0) {
            return 0;
        }

        return (int) round(($stats['active_tenants'] / $stats['total_tenants']) * 100);
    }

    public function exportCsv()
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403);

        $data     = $this->chartData;
        $filename = 'platform-analytics-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($data): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Period', 'New Tenants', 'New Users']);

            foreach ($data['labels'] as $i => $label) {
                fputcsv($out, [$label, $data['tenants'][$i], $data['users'][$i]]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1600px] mx-auto space-y-8" wire:poll.60s>

    <div id="analytics-data"
         data-chart="{{ json_encode($this->chartData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-status="{{ json_encode($this->tenantStatusData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-topspots="{{ json_encode($this->topSpotsData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         hidden
         aria-hidden="true"></div>

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-2 border-b border-gray-200/80 dark:border-gray-800">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white">
                    Platform Analytics
                </h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                             bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-400
                             border border-emerald-200 dark:border-emerald-800">
                    <span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-emerald-500 animate-pulse motion-reduce:animate-none"></span>
                    Live · 60s
                </span>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Real-time system metrics, onboarding growth, and usage overview.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" wire:click="exportCsv"
                    wire:loading.attr="disabled"
                    wire:target="exportCsv"
                    class="btn-secondary text-sm active:scale-95 transition-transform
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                           inline-flex items-center gap-2 disabled:opacity-60">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
                <span wire:loading wire:target="exportCsv" class="inline-flex items-center gap-1.5">
                    <svg class="animate-spin h-4 w-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Exporting…
                </span>
            </button>

            <button type="button" onclick="window.print()"
                    class="btn-secondary text-sm active:scale-95 transition-transform
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                           inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-6-4h.01M6 18v4h12v-4"/>
                </svg>
                Print
            </button>
        </div>
    </div>

    <div class="p-2.5 bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-1.5 overflow-x-auto p-1 bg-gray-100/80 dark:bg-gray-900/60 rounded-xl border border-gray-200/50 dark:border-gray-800">
                @foreach([
                    'today' => 'Today',
                    '7d' => '7 Days',
                    '30d' => '30 Days',
                    'this_month' => 'This Month',
                    'this_year' => 'This Year',
                ] as $val => $label)
                    <button type="button"
                            wire:key="preset-{{ $val }}"
                            wire:click="applyPreset('{{ $val }}')"
                            class="px-3.5 py-1.5 rounded-lg text-xs font-semibold tracking-wide whitespace-nowrap
                                   transition-all duration-150 active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                   {{ $preset === $val
                                      ? 'bg-white dark:bg-gray-800 text-primary-600 dark:text-primary-400 shadow-sm border border-gray-200/60 dark:border-gray-700'
                                      : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                        {{ $label }}
                    </button>
                @endforeach

                <button type="button" wire:click="applyPreset('custom')"
                        wire:key="preset-custom"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-semibold tracking-wide whitespace-nowrap
                               transition-all duration-150 active:scale-95
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $preset === 'custom'
                                  ? 'bg-white dark:bg-gray-800 text-primary-600 dark:text-primary-400 shadow-sm border border-gray-200/60 dark:border-gray-700'
                                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                    Custom Range
                </button>
            </div>

            @if($preset === 'custom')
                <div class="flex items-center gap-2 px-2">
                    <input type="date" wire:model.live="startDate"
                           class="px-3 py-1.5 text-xs font-medium bg-gray-50 dark:bg-gray-900
                                  text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-700
                                  rounded-lg focus:ring-2 focus:ring-primary-500 focus:outline-none">
                    <span class="text-gray-400 text-xs font-medium">to</span>
                    <input type="date" wire:model.live="endDate"
                           class="px-3 py-1.5 text-xs font-medium bg-gray-50 dark:bg-gray-900
                                  text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-700
                                  rounded-lg focus:ring-2 focus:ring-primary-500 focus:outline-none">
                </div>
            @endif
        </div>
    </div>

    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        @php
            $kpi = [
                [
                    'label' => 'Total Tenants',
                    'value' => $s['total_tenants'],
                    'sub'   => $s['active_tenants'] . ' active',
                    'sub_color' => 'text-emerald-600 dark:text-emerald-400',
                    'icon_bg' => 'bg-primary-50 dark:bg-primary-950/50',
                    'icon_color' => 'text-primary-600 dark:text-primary-400',
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>',
                ],
                [
                    'label' => 'Active',
                    'value' => $s['active_tenants'],
                    'sub'   => 'Operational',
                    'sub_color' => 'text-gray-500 dark:text-gray-400',
                    'icon_bg' => 'bg-emerald-50 dark:bg-emerald-950/50',
                    'icon_color' => 'text-emerald-600 dark:text-emerald-400',
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                ],
                [
                    'label' => 'Pending',
                    'value' => $s['pending_tenants'],
                    'sub'   => 'Awaiting action',
                    'sub_color' => 'text-gray-500 dark:text-gray-400',
                    'icon_bg' => 'bg-amber-50 dark:bg-amber-950/50',
                    'icon_color' => 'text-amber-600 dark:text-amber-400',
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                    'value_color' => 'text-amber-600 dark:text-amber-400',
                ],
                [
                    'label' => 'Total Users',
                    'value' => $s['total_users'],
                    'sub'   => $s['active_users'] . ' active',
                    'sub_color' => 'text-emerald-600 dark:text-emerald-400',
                    'icon_bg' => 'bg-indigo-50 dark:bg-indigo-950/50',
                    'icon_color' => 'text-indigo-600 dark:text-indigo-400',
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>',
                ],
                [
                    'label' => 'New (Month)',
                    'value' => $s['new_this_month'],
                    'sub'   => $s['new_users_this_month'] . ' new users',
                    'sub_color' => 'text-gray-500 dark:text-gray-400',
                    'icon_bg' => 'bg-sky-50 dark:bg-sky-950/50',
                    'icon_color' => 'text-sky-600 dark:text-sky-400',
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>',
                ],
                [
                    'label' => 'New (Week)',
                    'value' => $s['new_this_week'],
                    'sub'   => 'Onboarded',
                    'sub_color' => 'text-gray-500 dark:text-gray-400',
                    'icon_bg' => 'bg-purple-50 dark:bg-purple-950/50',
                    'icon_color' => 'text-purple-600 dark:text-purple-400',
                    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>',
                ],
            ];
        @endphp
        @foreach($kpi as $i => $card)
            <div wire:key="kpi-{{ $i }}"
                 class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm
                        hover:shadow-md transition-shadow duration-200">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $card['label'] }}</p>
                    <div class="p-2 {{ $card['icon_bg'] }} rounded-xl {{ $card['icon_color'] }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            {!! $card['icon'] !!}
                        </svg>
                    </div>
                </div>
                <p class="text-2xl font-bold {{ $card['value_color'] ?? 'text-gray-900 dark:text-white' }} mt-2 tabular-nums">
                    {{ number_format($card['value']) }}
                </p>
                <p class="text-xs font-medium {{ $card['sub_color'] }} mt-2">{{ $card['sub'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800/90 p-6 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm lg:col-span-2">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Tenant Acquisition Growth</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">New registered tenant accounts over the selected timeframe.</p>
                </div>
                <div class="hidden sm:flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <span class="w-3 h-3 rounded-full bg-cyan-500 inline-block"></span>
                    New Tenants
                </div>
            </div>
            <div class="w-full h-80 relative" wire:ignore>
                <canvas id="tenantChart"></canvas>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800/90 p-6 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">User Growth</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Registration trajectory for platform end-users.</p>
                </div>
                <div class="hidden sm:flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <span class="w-3 h-3 rounded-full bg-indigo-500 inline-block"></span>
                    New Users
                </div>
            </div>
            <div class="w-full h-80 relative" wire:ignore>
                <canvas id="userChart"></canvas>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800/90 p-6 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">Tenant Distribution</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Active operational vs pending tenant accounts.</p>
                </div>
            </div>
            <div class="w-full h-80 relative flex items-center justify-center" wire:ignore>
                <canvas id="statusChart"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-xs text-gray-400 font-medium uppercase tracking-wider">Active Rate</span>
                    <span class="text-xl font-bold text-gray-900 dark:text-white mt-0.5 tabular-nums">
                        {{ $this->activeRate }}%
                    </span>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════
             TOP PICKS — Top 3 most-booked tourist spots
             ══════════════════════════════════════════════════════════ --}}
        <div class="bg-white dark:bg-gray-800/90 p-6 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm lg:col-span-2">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                        Top Picks
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        The 3 most-booked destinations across the platform for the selected period.
                    </p>
                </div>
                <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full
                             bg-amber-100 dark:bg-amber-950/60
                             text-amber-700 dark:text-amber-300
                             border border-amber-200 dark:border-amber-800
                             px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    Top 3
                </span>
            </div>
            <div class="w-full h-80 relative" wire:ignore>
                <canvas id="topSpotsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const state = window.__analyticsState = window.__analyticsState || {
        tenant: null,
        user: null,
        status: null,
        spots: null,
        hooked: false,
    };

    function getData() {
        const el = document.getElementById('analytics-data');
        if (!el) return null;

        try {
            return {
                chart:    JSON.parse(el.dataset.chart    || '{}'),
                status:   JSON.parse(el.dataset.status   || '{}'),
                topspots: JSON.parse(el.dataset.topspots || '{}'),
            };
        } catch (e) {
            console.error('Analytics data parse failed', e);
            return null;
        }
    }

    function getTheme() {
        const isDark = document.documentElement.classList.contains('dark');
        return {
            isDark,
            textColor:     isDark ? '#9ca3af' : '#6b7280',
            gridColor:     isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)',
            tooltipBg:     isDark ? '#1f2937' : '#ffffff',
            tooltipText:   isDark ? '#f3f4f6' : '#111827',
            tooltipBorder: isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.08)',
            tenantColor:   '#06b6d4',
            userColor:     '#6366f1',
        };
    }

    function tooltipOptions(theme) {
        return {
            backgroundColor: theme.tooltipBg,
            titleColor:      theme.tooltipText,
            bodyColor:       theme.textColor,
            borderColor:     theme.tooltipBorder,
            borderWidth:     1,
            padding:         12,
            boxPadding:      6,
            usePointStyle:   true,
            cornerRadius:    12,
            titleFont: { family: 'Inter, sans-serif', size: 13, weight: 'bold' },
            bodyFont:  { family: 'Inter, sans-serif', size: 12 },
        };
    }

    function areaGradient(ctx, hexColor) {
        const g = ctx.createLinearGradient(0, 0, 0, 300);
        g.addColorStop(0, hexColor + '33');
        g.addColorStop(1, hexColor + '00');
        return g;
    }

    function createLineChart(canvasId, labels, values, label, hexColor, theme) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return null;

        const ctx = canvas.getContext('2d');
        return new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label,
                    data: values,
                    borderColor: hexColor,
                    backgroundColor: areaGradient(ctx, hexColor),
                    tension: 0.35,
                    fill: true,
                    borderWidth: 2.5,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: hexColor,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend:  { display: false },
                    tooltip: tooltipOptions(theme),
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: theme.textColor, precision: 0, font: { size: 11 } },
                        grid:  { color: theme.gridColor },
                    },
                    x: {
                        ticks: { color: theme.textColor, font: { size: 11 } },
                        grid:  { display: false },
                    },
                },
            },
        });
    }

    function createDoughnut(canvasId, data, theme) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return null;

        return new Chart(canvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: data.colors,
                    borderWidth: 0,
                    hoverOffset: 6,
                    borderRadius: 6,
                    spacing: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '78%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: theme.textColor,
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: { size: 12, weight: '500' },
                        },
                    },
                    tooltip: tooltipOptions(theme),
                },
            },
        });
    }

    function createBarChart(canvasId, labels, values, label, colors, theme) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return null;

        return new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label,
                    data: values,
                    backgroundColor: colors,
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 60,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend:  { display: false },
                    tooltip: tooltipOptions(theme),
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: theme.textColor, precision: 0, font: { size: 11 } },
                        grid:  { color: theme.gridColor },
                    },
                    x: {
                        ticks: { color: theme.textColor, font: { size: 11 } },
                        grid:  { display: false },
                    },
                },
            },
        });
    }

    function destroyAll() {
        ['tenant', 'user', 'status', 'spots'].forEach(key => {
            if (state[key]) {
                try { state[key].destroy(); } catch (e) { /* noop */ }
                state[key] = null;
            }
        });
    }

    window.renderAnalyticsCharts = function (force) {
        if (typeof Chart === 'undefined') {
            setTimeout(() => window.renderAnalyticsCharts(force), 100);
            return;
        }

        const data = getData();
        if (!data) return;

        const theme = getTheme();

        if (force) {
            destroyAll();
        }

        if (!state.tenant && document.getElementById('tenantChart')) {
            state.tenant = createLineChart('tenantChart', data.chart.labels || [], data.chart.tenants || [], 'New Tenants', theme.tenantColor, theme);
        }
        if (!state.user && document.getElementById('userChart')) {
            state.user = createLineChart('userChart', data.chart.labels || [], data.chart.users || [], 'New Users', theme.userColor, theme);
        }
        if (!state.status && document.getElementById('statusChart')) {
            state.status = createDoughnut('statusChart', data.status, theme);
        }
        if (!state.spots && document.getElementById('topSpotsChart')) {
            state.spots = createBarChart('topSpotsChart', data.topspots.labels || [], data.topspots.values || [], 'Bookings', data.topspots.colors || [], theme);
        }

        if (state.tenant && data.chart.labels) {
            state.tenant.data.labels = data.chart.labels;
            state.tenant.data.datasets[0].data = data.chart.tenants;
            state.tenant.update('none');
        }
        if (state.user && data.chart.labels) {
            state.user.data.labels = data.chart.labels;
            state.user.data.datasets[0].data = data.chart.users;
            state.user.update('none');
        }
        if (state.status && data.status.labels) {
            state.status.data.labels = data.status.labels;
            state.status.data.datasets[0].data = data.status.values;
            state.status.update('none');
        }
        if (state.spots && data.topspots.labels) {
            state.spots.data.labels = data.topspots.labels;
            state.spots.data.datasets[0].data = data.topspots.values;
            state.spots.update('none');
        }
    };

    window.renderAnalyticsCharts(false);

    if (!state.hooked) {
        state.hooked = true;

        document.addEventListener('livewire:init', () => {
            Livewire.hook('morph.updated', ({ el }) => {
                if (el && el.id === 'analytics-data') {
                    setTimeout(() => window.renderAnalyticsCharts(false), 50);
                }
            });
        });

        let lastDark = document.documentElement.classList.contains('dark');
        new MutationObserver(() => {
            const isDark = document.documentElement.classList.contains('dark');
            if (isDark !== lastDark) {
                lastDark = isDark;
                window.renderAnalyticsCharts(true);
            }
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    }
})();
</script>