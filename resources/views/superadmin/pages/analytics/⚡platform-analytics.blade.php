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
use App\Models\Event;
use App\Models\TypeOfTenant;
use App\Scopes\TenantScope;
use App\Services\SuperadminNotificationService;
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

    /** @return array<string, int> */
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

        $eventStats = Event::query()
            ->selectRaw('
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN start_date >= ? AND is_active = 1 THEN 1 ELSE 0 END), 0) as upcoming
            ', [$now])
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
            'total_events'         => (int) ($eventStats?->total ?? 0),
            'upcoming_events'      => (int) ($eventStats?->upcoming ?? 0),
        ];
    }

    /**
     * Period metadata only — no revenue, no booking counts. The super-admin
     * manages the system, not the money. The custom range still drives the
     * Top Spots ranking (only computed that reads the dates).
     *
     * @return array{period_start: Carbon, period_end: Carbon, period_days: int}
     */
    #[Computed]
    public function periodInfo(): array
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : now()->startOfYear();
        $end   = $this->endDate   ? Carbon::parse($this->endDate)->endOfDay()     : now()->endOfYear();

        return [
            'period_start' => $start,
            'period_end'   => $end,
            'period_days'  => max(1, $start->diffInDays($end) + 1),
        ];
    }

    /** @return array{applications: int, deletions: int, tenants: int, total: int} */
    #[Computed]
    public function reviewQueue(): array
    {
        $notifications = app(SuperadminNotificationService::class);

        $apps      = $notifications->pendingCount();
        $deletions = $notifications->deletionRequestCount();
        $tenants   = (int) Tenant::query()->where('is_active', false)->count();

        return [
            'applications' => $apps,
            'deletions'    => $deletions,
            'tenants'      => $tenants,
            'total'        => $apps + $deletions + $tenants,
        ];
    }

    /** @return array{labels: array<int, string>, values: array<int, int|null>} */
    #[Computed]
    public function tenantAcquisition(): array
    {
        $start = now()->subMonths(5)->startOfMonth();
        $end   = now()->endOfMonth();

        $labels = [];
        $values = [];

        $period = CarbonPeriod::create($start, '1 month', $end);

        foreach ($period as $dt) {
            $mStart = $dt->copy()->startOfMonth();
            $mEnd   = $dt->copy()->endOfMonth();

            $labels[] = $dt->format('M');

            $count = (int) Tenant::query()
                ->whereBetween('created_at', [$mStart, $mEnd])
                ->count();

            $values[] = $count === 0 ? null : $count;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** @return array{labels: array<int, string>, values: array<int, int|null>} */
    #[Computed]
    public function userGrowth(): array
    {
        $start = now()->subMonths(5)->startOfMonth();
        $end   = now()->endOfMonth();

        $labels = [];
        $values = [];

        $period = CarbonPeriod::create($start, '1 month', $end);

        foreach ($period as $dt) {
            $mStart = $dt->copy()->startOfMonth();
            $mEnd   = $dt->copy()->endOfMonth();

            $labels[] = $dt->format('M');

            $count = (int) User::query()
                ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'super-admin'))
                ->whereBetween('created_at', [$mStart, $mEnd])
                ->count();

            $values[] = $count === 0 ? null : $count;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /** @return array{labels: array<int, string>, values: array<int, int>, colors: array<int, string>} */
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

    /** @return array{labels: array<int, string>, values: array<int, int>, colors: array<int, string>} */
    #[Computed]
    public function topSpotsData(): array
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : now()->startOfYear();
        $end   = $this->endDate   ? Carbon::parse($this->endDate)->endOfDay()     : now()->endOfYear();

        $topSpots = \App\Models\Booking::query()
            ->withoutGlobalScope(TenantScope::class)
            ->join('booking_items', 'bookings.id', '=', 'booking_items.booking_id')
            ->join('properties',   'booking_items.property_id', '=', 'properties.id')
            ->whereBetween('bookings.created_at', [$start, $end])
            ->whereIn('bookings.status', [
                \App\Models\Booking::STATUS_CONFIRMED,
                \App\Models\Booking::STATUS_COMPLETED,
            ])
            ->select(
                'properties.id',
                'properties.name',
                DB::raw('COUNT(DISTINCT bookings.id) as total_bookings'),
            )
            ->groupBy('properties.id', 'properties.name')
            ->orderByDesc('total_bookings')
            ->limit(5)
            ->get();

        $ramp = ['#1e3a8a', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd'];

        return [
            'labels' => $topSpots->pluck('name')->all(),
            'values' => $topSpots->pluck('total_bookings')->map(fn ($v) => (int) $v)->all(),
            'colors' => array_slice($ramp, 0, $topSpots->count()),
        ];
    }

    /** @return array{labels: array<int, string>, values: array<int, int>, colors: array<int, string>} */
    #[Computed]
    public function categoryDistribution(): array
    {
        $types = TypeOfTenant::query()
            ->withCount('tenants')
            ->having('tenants_count', '>', 0)
            ->orderByDesc('tenants_count')
            ->limit(6)
            ->get();

        $palette = [
            'rgba(16, 185, 129, 0.75)',
            'rgba(59, 130, 246, 0.75)',
            'rgba(139, 92, 246, 0.75)',
            'rgba(245, 158, 11, 0.75)',
            'rgba(244, 63, 94, 0.75)',
            'rgba(100, 116, 139, 0.75)',
        ];

        return [
            'labels' => $types->pluck('type')->all(),
            'values' => $types->pluck('tenants_count')->map(fn ($v) => (int) $v)->all(),
            'colors' => array_slice($palette, 0, $types->count()),
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

    public function exportCsv(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403);

        $csv = $this->buildAnalyticsCsv();

        $this->dispatch(
            'download-file',
            filename:      'platform-analytics-' . now()->format('Y-m-d-His') . '.csv',
            mimeType:      'text/csv;charset=UTF-8',
            contentBase64: base64_encode($csv),
        );
    }

    private function buildAnalyticsCsv(): string
    {
        $s   = $this->stats;
        $p   = $this->periodInfo;
        $rq  = $this->reviewQueue;

        $rows = [
            ['Metric', 'Value'],
            ['Period start',  $p['period_start']->toDateString()],
            ['Period end',    $p['period_end']->toDateString()],
            ['Period days',   (string) $p['period_days']],
            ['', ''],
            ['Total Tenants',    (string) $s['total_tenants']],
            ['Active Tenants',   (string) $s['active_tenants']],
            ['Pending Tenants',  (string) $s['pending_tenants']],
            ['New This Week',    (string) $s['new_this_week']],
            ['New This Month',   (string) $s['new_this_month']],
            ['Total Users',      (string) $s['total_users']],
            ['Active Users',     (string) $s['active_users']],
            ['New Users (Month)',(string) $s['new_users_this_month']],
            ['Total Events',     (string) $s['total_events']],
            ['Upcoming Events',  (string) $s['upcoming_events']],
            ['Active Rate %',    (string) $this->activeRate],
            ['', ''],
            ['Pending KYB Applications',  (string) $rq['applications']],
            ['Pending Deletion Requests', (string) $rq['deletions']],
            ['Pending Tenants (Review)',  (string) $rq['tenants']],
        ];

        $out = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return (string) $csv;
    }
};
?>

@push('styles')
    @once
        <style>
            .analytics-page canvas {
                display:    block !important;
                width:      100%  !important;
                height:     100%  !important;
                max-height: 100% !important;
            }

            .platform-analytics-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.06) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.05) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.04) 0%, transparent 60%);
            }
            .dark .platform-analytics-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.08) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.07) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.06) 0%, transparent 60%);
            }

            @media print {
                @page { size: auto; margin: 12mm; }

                html, body {
                    background: #fff !important;
                    color: #000 !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    min-height: 0 !important;
                    height: auto !important;
                }

                .analytics-page { padding: 0 !important; margin: 0 !important; }

                .no-print { display: none !important; }

                canvas { print-color-adjust: exact; -webkit-print-color-adjust: exact; }

                .analytics-page > * {
                    break-inside: avoid;
                    page-break-inside: avoid;
                }

                .analytics-page > .grid {
                    display: grid !important;
                    grid-template-columns: repeat(4, 1fr) !important;
                    gap: 3mm !important;
                }
            }
        </style>
    @endonce
@endpush

@push('scripts')
    @once
        <script>
            (function () {
                if (window.__analyticsDownloadBound) return;
                window.__analyticsDownloadBound = true;

                window.addEventListener('download-file', (e) => {
                    try {
                        const detail = e.detail || {};
                        const { filename, mimeType, contentBase64 } = detail;
                        if (!contentBase64) return;

                        const binary = atob(contentBase64);
                        const bytes  = new Uint8Array(binary.length);
                        for (let i = 0; i < binary.length; i++) {
                            bytes[i] = binary.charCodeAt(i);
                        }

                        const blob = new Blob([bytes], { type: mimeType || 'application/octet-stream' });
                        const url  = URL.createObjectURL(blob);
                        const a    = document.createElement('a');
                        a.href     = url;
                        a.download = filename || 'download.csv';
                        a.style.display = 'none';

                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);

                        setTimeout(() => URL.revokeObjectURL(url), 1500);
                    } catch (err) {
                        console.error('[analytics] download failed', err);
                    }
                });
            })();
        </script>

        <script>
            (function () {
                if (window.__analyticsPrintScopeInstalled) return;
                window.__analyticsPrintScopeInstalled = true;

                function applyPrintScope() {
                    const root = document.querySelector('.analytics-page');
                    if (!root) return;

                    const ancestors = new Set();
                    let el = root;
                    while (el && el !== document.body) {
                        ancestors.add(el);
                        el = el.parentElement;
                    }

                    Array.from(document.body.children).forEach(function (child) {
                        if (!ancestors.has(child)) {
                            if (child.dataset.printHidden !== '1') {
                                child.dataset.printHidden = '1';
                                child.dataset.printOldDisplay = child.style.display || '';
                            }
                            child.style.setProperty('display', 'none', 'important');
                        }
                    });

                    ancestors.forEach(function (node) {
                        if (node === root) return;
                        if (node.dataset.printReset !== '1') {
                            node.dataset.printReset = '1';
                            node.dataset.printOldPadding = node.style.padding || '';
                            node.dataset.printOldMargin = node.style.margin || '';
                            node.dataset.printOldBackground = node.style.background || '';
                        }
                        node.style.setProperty('padding', '0', 'important');
                        node.style.setProperty('margin', '0', 'important');
                        node.style.setProperty('background', 'transparent', 'important');
                    });
                }

                function restorePrintScope() {
                    document.querySelectorAll('[data-print-hidden="1"]').forEach(function (node) {
                        node.style.removeProperty('display');
                        if (node.dataset.printOldDisplay) {
                            node.style.display = node.dataset.printOldDisplay;
                        }
                        delete node.dataset.printHidden;
                        delete node.dataset.printOldDisplay;
                    });

                    document.querySelectorAll('[data-print-reset="1"]').forEach(function (node) {
                        node.style.removeProperty('padding');
                        node.style.removeProperty('margin');
                        node.style.removeProperty('background');
                        if (node.dataset.printOldPadding) node.style.padding = node.dataset.printOldPadding;
                        if (node.dataset.printOldMargin)  node.style.margin  = node.dataset.printOldMargin;
                        if (node.dataset.printOldBackground) node.style.background = node.dataset.printOldBackground;
                        delete node.dataset.printReset;
                        delete node.dataset.printOldPadding;
                        delete node.dataset.printOldMargin;
                        delete node.dataset.printOldBackground;
                    });
                }

                window.addEventListener('beforeprint', applyPrintScope);
                window.addEventListener('afterprint', restorePrintScope);
            })();
        </script>

        <script>
            (function () {
                'use strict';

                const state = window.__analyticsState = window.__analyticsState || {
                    tenantAcq:  null,
                    userGrowth: null,
                    status:     null,
                    topSpots:   null,
                    categories: null,
                    review:     null,
                    hooked:     false,
                    pluginsReady: false,
                    resizeObserver: null,
                };

                if (typeof state.pluginsReady !== 'boolean') state.pluginsReady = false;

                const barValueLabel = {
                    id: 'barValueLabel',
                    afterDatasetsDraw(chart, args, opts) {
                        if (!opts || !opts.enabled) return;
                        const ctx = chart.ctx;
                        const isDark = document.documentElement.classList.contains('dark');
                        ctx.save();
                        ctx.font = '700 11px Inter, system-ui, sans-serif';
                        ctx.fillStyle = isDark ? '#e5e7eb' : '#111827';
                        ctx.textBaseline = 'middle';
                        ctx.textAlign = 'left';
                        chart.data.datasets.forEach((ds, di) => {
                            const meta = chart.getDatasetMeta(di);
                            if (meta.hidden) return;
                            meta.data.forEach((bar, i) => {
                                const v = ds.data[i];
                                if (v === undefined || v === null) return;
                                const pos = bar.tooltipPosition();
                                ctx.fillText(String(v), pos.x + 8, pos.y);
                            });
                        });
                        ctx.restore();
                    },
                };

                function ensurePluginsRegistered() {
                    if (state.pluginsReady) return;
                    if (typeof Chart === 'undefined') return;
                    Chart.register(barValueLabel);
                    state.pluginsReady = true;
                }

                function getData() {
                    const el = document.getElementById('analytics-data');
                    if (!el) return null;
                    try {
                        return {
                            tenantAcq:  JSON.parse(el.dataset.tenantAcq  || '{}'),
                            userGrowth: JSON.parse(el.dataset.userGrowth || '{}'),
                            status:     JSON.parse(el.dataset.status     || '{}'),
                            topspots:   JSON.parse(el.dataset.topspots   || '{}'),
                            categories: JSON.parse(el.dataset.categories || '{}'),
                            review:     JSON.parse(el.dataset.review     || '{}'),
                        };
                    } catch (e) {
                        console.error('Analytics data parse failed', e);
                        return null;
                    }
                }

                function theme() {
                    const isDark = document.documentElement.classList.contains('dark');
                    return {
                        isDark,
                        text:      isDark ? '#9ca3af' : '#6b7280',
                        textBold:  isDark ? '#f3f4f6' : '#111827',
                        grid:      isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)',
                        gridLight: isDark ? 'rgba(255,255,255,0.04)' : 'rgba(0,0,0,0.04)',
                        tooltipBg: '#111827',
                        tooltipText: '#f9fafb',
                        doughnutBorder: isDark ? '#111827' : '#ffffff',
                    };
                }

                function tooltipConfig(t) {
                    return {
                        backgroundColor: t.tooltipBg,
                        titleColor:      t.tooltipText,
                        bodyColor:       t.tooltipText,
                        borderColor:     'rgba(255,255,255,0.08)',
                        borderWidth:     1,
                        padding:         12,
                        cornerRadius:    10,
                        displayColors:   true,
                        boxWidth:        8,
                        boxHeight:       8,
                        boxPadding:      4,
                        titleFont: { family: 'Inter, system-ui, sans-serif', size: 12, weight: '700' },
                        bodyFont:  { family: 'Inter, system-ui, sans-serif', size: 12, weight: '500' },
                    };
                }

                function scaleX(t) {
                    return {
                        grid:   { display: false },
                        border: { display: false },
                        ticks:  { color: t.text, font: { size: 11, weight: '600' }, padding: 6, maxRotation: 0, autoSkipPadding: 12 },
                    };
                }

                function scaleY(t, { dashed = true, position = 'left' } = {}) {
                    return {
                        type: 'linear',
                        position,
                        beginAtZero: true,
                        grid: {
                            color: dashed ? t.grid : 'transparent',
                            borderDash: dashed ? [4, 4] : undefined,
                            drawTicks: false,
                        },
                        border: { display: false },
                        ticks:  { color: t.text, precision: 0, font: { size: 11, weight: '600' }, padding: 8 },
                    };
                }

                function hexToRgba(hex, alpha) {
                    const h = hex.replace('#', '');
                    const n = parseInt(h.length === 3 ? h.split('').map(c => c + c).join('') : h, 16);
                    const r = (n >> 16) & 255;
                    const g = (n >> 8)  & 255;
                    const b = n         & 255;
                    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
                }

                function buildGrowthBar(canvasId, labels, values, color, t) {
                    const canvas = document.getElementById(canvasId);
                    if (!canvas) return null;

                    const ctx = canvas.getContext('2d');
                    const H   = canvas.clientHeight || 260;

                    const gradient = ctx.createLinearGradient(0, 0, 0, H);
                    gradient.addColorStop(0, hexToRgba(color, 1.00));
                    gradient.addColorStop(1, hexToRgba(color, 0.55));

                    return new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels,
                            datasets: [{
                                data: values,
                                backgroundColor: gradient,
                                hoverBackgroundColor: color,
                                borderRadius: { topLeft: 8, topRight: 8, bottomLeft: 0, bottomRight: 0 },
                                borderSkipped: false,
                                maxBarThickness: 48,
                                barPercentage: 0.65,
                                categoryPercentage: 0.75,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: { duration: 700, easing: 'easeOutQuart' },
                            interaction: { mode: 'index', intersect: false },
                            layout: { padding: { top: 8, right: 8, bottom: 0, left: 0 } },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    ...tooltipConfig(t),
                                    callbacks: {
                                        label(ctx) {
                                            const v = ctx.parsed.y;
                                            if (v === null || v === undefined) return ' —';
                                            return ' ' + Number(v).toLocaleString();
                                        },
                                    },
                                },
                            },
                            scales: {
                                x: scaleX(t),
                                y: {
                                    ...scaleY(t, { dashed: true, position: 'left' }),
                                    beginAtZero: true,
                                    grace: '15%',
                                    ticks: {
                                        color: t.text,
                                        precision: 0,
                                        font: { size: 11, weight: '600' },
                                        padding: 8,
                                    },
                                },
                            },
                        },
                    });
                }

                function buildStatus(status, t) {
                    const canvas = document.getElementById('statusChart');
                    if (!canvas) return null;

                    return new Chart(canvas.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: status.labels || [],
                            datasets: [{
                                data: status.values || [],
                                backgroundColor: status.colors || ['#10b981', '#f59e0b'],
                                borderWidth: 3,
                                borderColor: t.doughnutBorder,
                                hoverOffset: 6,
                                borderRadius: 6,
                                spacing: 2,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '76%',
                            animation: { animateRotate: true, duration: 800, easing: 'easeOutQuart' },
                            plugins: {
                                legend:  { display: false },
                                tooltip: tooltipConfig(t),
                            },
                        },
                    });
                }

                function buildHBar(canvasId, labels, values, colors, t, { showLabels = true, thickness = 22 } = {}) {
                    const canvas = document.getElementById(canvasId);
                    if (!canvas) return null;

                    return new Chart(canvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels,
                            datasets: [{
                                data: values,
                                backgroundColor: colors,
                                borderRadius: 6,
                                borderSkipped: false,
                                maxBarThickness: thickness,
                            }],
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: { duration: 700, easing: 'easeOutQuart' },
                            layout: { padding: { right: showLabels ? 40 : 8 } },
                            plugins: {
                                legend: { display: false },
                                tooltip: tooltipConfig(t),
                                barValueLabel: { enabled: showLabels },
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    grid:   { color: t.gridLight, drawTicks: false },
                                    border: { display: false },
                                    ticks:  { color: t.text, precision: 0, font: { size: 10, weight: '600' }, padding: 4 },
                                },
                                y: {
                                    grid:   { display: false },
                                    border: { display: false },
                                    ticks: {
                                        color: t.textBold,
                                        font: { size: 11, weight: '600' },
                                        autoSkip: false,
                                        padding: 4,
                                        callback: function (v) {
                                            const label = this.getLabelForValue(v);
                                            return label.length > 28 ? label.slice(0, 26) + '…' : label;
                                        },
                                    },
                                },
                            },
                        },
                    });
                }

                function buildPolar(categories, t) {
                    const canvas = document.getElementById('categoriesChart');
                    if (!canvas) return null;

                    return new Chart(canvas.getContext('2d'), {
                        type: 'polarArea',
                        data: {
                            labels: categories.labels || [],
                            datasets: [{
                                data: categories.values || [],
                                backgroundColor: categories.colors || [],
                                borderWidth: 2,
                                borderColor: t.doughnutBorder,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: { duration: 700, easing: 'easeOutQuart' },
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        color: t.text,
                                        boxWidth: 10,
                                        boxHeight: 10,
                                        usePointStyle: true,
                                        pointStyle: 'circle',
                                        padding: 10,
                                        font: { size: 10, weight: '600' },
                                    },
                                },
                                tooltip: tooltipConfig(t),
                            },
                            scales: {
                                r: {
                                    grid:  { color: t.grid, circular: true },
                                    ticks: { display: false, backdropColor: 'transparent' },
                                    angleLines: { color: t.gridLight },
                                },
                            },
                        },
                    });
                }

                function destroyAll() {
                    ['tenantAcq', 'userGrowth', 'status', 'topSpots', 'categories', 'review'].forEach(k => {
                        if (state[k]) { try { state[k].destroy(); } catch (e) {} state[k] = null; }
                    });
                }

                function resizeAllCharts() {
                    ['tenantAcq', 'userGrowth', 'status', 'topSpots', 'categories', 'review'].forEach(k => {
                        if (state[k]) { try { state[k].resize(); } catch (e) {} }
                    });
                }

                window.renderAnalyticsCharts = function (force) {
                    if (typeof Chart === 'undefined') {
                        setTimeout(() => window.renderAnalyticsCharts(force), 100);
                        return;
                    }

                    ensurePluginsRegistered();

                    const data = getData();
                    if (!data) return;

                    const t = theme();
                    if (force) destroyAll();

                    if (!state.tenantAcq && document.getElementById('tenantAcqChart')) {
                        state.tenantAcq = buildGrowthBar(
                            'tenantAcqChart',
                            data.tenantAcq.labels || [],
                            data.tenantAcq.values || [],
                            '#8b5cf6',
                            t
                        );
                    }
                    if (!state.userGrowth && document.getElementById('userGrowthChart')) {
                        state.userGrowth = buildGrowthBar(
                            'userGrowthChart',
                            data.userGrowth.labels || [],
                            data.userGrowth.values || [],
                            '#3b82f6',
                            t
                        );
                    }
                    if (!state.status && document.getElementById('statusChart')) {
                        state.status = buildStatus(data.status, t);
                    }
                    if (!state.topSpots && document.getElementById('topSpotsChart')) {
                        state.topSpots = buildHBar(
                            'topSpotsChart',
                            data.topspots.labels || [],
                            data.topspots.values || [],
                            data.topspots.colors || ['#1e3a8a', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd'],
                            t,
                            { showLabels: true, thickness: 22 }
                        );
                    }
                    if (!state.categories && document.getElementById('categoriesChart')) {
                        state.categories = buildPolar(data.categories, t);
                    }
                    if (!state.review && document.getElementById('reviewChart')) {
                        const rq = data.review || {};
                        state.review = buildHBar(
                            'reviewChart',
                            ['KYB Applications', 'Deletion Requests', 'Pending Tenants'],
                            [rq.applications || 0, rq.deletions || 0, rq.tenants || 0],
                            ['#f59e0b', '#f43f5e', '#3b82f6'],
                            t,
                            { showLabels: true, thickness: 20 }
                        );
                    }

                    if (state.tenantAcq && data.tenantAcq.labels) {
                        state.tenantAcq.data.labels = data.tenantAcq.labels;
                        state.tenantAcq.data.datasets[0].data = data.tenantAcq.values;
                        state.tenantAcq.update('none');
                    }
                    if (state.userGrowth && data.userGrowth.labels) {
                        state.userGrowth.data.labels = data.userGrowth.labels;
                        state.userGrowth.data.datasets[0].data = data.userGrowth.values;
                        state.userGrowth.update('none');
                    }
                    if (state.status && data.status.labels) {
                        state.status.data.labels = data.status.labels;
                        state.status.data.datasets[0].data = data.status.values;
                        state.status.update('none');
                    }
                    if (state.topSpots && data.topspots.labels) {
                        state.topSpots.data.labels = data.topspots.labels;
                        state.topSpots.data.datasets[0].data = data.topspots.values;
                        state.topSpots.update('none');
                    }
                    if (state.categories && data.categories.labels) {
                        state.categories.data.labels = data.categories.labels;
                        state.categories.data.datasets[0].data = data.categories.values;
                        state.categories.update('none');
                    }
                    if (state.review && data.review) {
                        state.review.data.datasets[0].data = [
                            data.review.applications || 0,
                            data.review.deletions || 0,
                            data.review.tenants || 0,
                        ];
                        state.review.update('none');
                    }
                };

                requestAnimationFrame(() => {
                    requestAnimationFrame(() => window.renderAnalyticsCharts(false));
                });

                if (!state.hooked) {
                    state.hooked = true;

                    const hookMorph = () => {
                        window.Livewire.hook('morph.updated', ({ el }) => {
                            if (el && el.id === 'analytics-data') {
                                setTimeout(() => window.renderAnalyticsCharts(false), 50);
                            }
                        });
                    };

                    if (window.Livewire) {
                        hookMorph();
                    } else {
                        document.addEventListener('livewire:init', hookMorph);
                    }

                    window.addEventListener('resize', () => {
                        requestAnimationFrame(resizeAllCharts);
                    });

                    if ('ResizeObserver' in window) {
                        state.resizeObserver = new ResizeObserver(() => {
                            requestAnimationFrame(resizeAllCharts);
                        });
                        [
                            'tenantAcqChart',
                            'userGrowthChart',
                            'statusChart',
                            'topSpotsChart',
                            'categoriesChart',
                            'reviewChart',
                        ].forEach(id => {
                            const canvas = document.getElementById(id);
                            if (canvas && canvas.parentElement) {
                                state.resizeObserver.observe(canvas.parentElement);
                            }
                        });
                    }

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
    @endonce
@endpush

@php
    $s           = $this->stats;
    $p           = $this->periodInfo;
    $rq          = $this->reviewQueue;
    $tenantAcq   = $this->tenantAcquisition;
    $userGrowth  = $this->userGrowth;
    $topSpots    = $this->topSpotsData;
    $categories  = $this->categoryDistribution;
    $statusData  = $this->tenantStatusData;

    $tenantAcqHasData   = !empty(array_filter($tenantAcq['values'],  fn ($v) => $v !== null));
    $userGrowthHasData  = !empty(array_filter($userGrowth['values'], fn ($v) => $v !== null));
    $statusHasData      = array_sum($statusData['values']) > 0;
    $categoriesHasData  = !empty($categories['labels']);
    $topSpotsHasData    = !empty($topSpots['labels']);
@endphp

<div class="analytics-page relative min-h-[100dvh] bg-[#F8F7F3] dark:bg-[#0F172A]" wire:poll.60s>

    <div class="platform-analytics-ambient fixed inset-0 -z-10 pointer-events-none" aria-hidden="true"></div>

    <div id="analytics-data"
         data-tenant-acq="{{ json_encode($tenantAcq, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-user-growth="{{ json_encode($userGrowth, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-status="{{ json_encode($statusData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-topspots="{{ json_encode($topSpots, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-categories="{{ json_encode($categories, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-review="{{ json_encode($rq, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         hidden
         aria-hidden="true"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 space-y-8 sm:space-y-12
                pb-[max(1.5rem,env(safe-area-inset-bottom))]">

        {{-- ═══ HERO ═══ --}}
        <section class="relative overflow-hidden rounded-3xl
                        bg-white/70 dark:bg-gray-800/40
                        backdrop-blur-xl
                        border border-gray-200/60 dark:border-white/[0.06]
                        shadow-sm">
            <div class="relative px-6 sm:px-10 py-8 sm:py-12">

                {{-- Title + period selector + actions --}}
                <div class="flex flex-wrap items-start justify-between gap-4 mb-10 sm:mb-12">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2.5 mb-2">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full
                                         bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300
                                         text-[10px] font-bold uppercase tracking-wider shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse motion-reduce:animate-none"></span>
                                Live
                            </span>
                            <span class="text-[11px] text-gray-500 dark:text-gray-400 truncate">Updates every 60s</span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white tracking-tight leading-tight">
                            Platform Analytics
                        </h1>
                    </div>

                    <div class="flex items-center gap-2 shrink-0 flex-wrap no-print">
                        <div class="inline-flex items-center gap-0.5 p-0.5 rounded-full
                                    bg-gray-100/80 dark:bg-gray-900/60
                                    border border-gray-200/60 dark:border-white/[0.04] max-w-full overflow-x-auto"
                             role="group"
                             aria-label="Date range">
                            @foreach([
                                'today'      => 'Today',
                                '7d'         => '7D',
                                '30d'        => '30D',
                                'this_month' => 'Month',
                                'this_year'  => 'Year',
                                'custom'     => 'Custom',
                            ] as $val => $label)
                                @php $isActive = $preset === $val; @endphp
                                <button type="button"
                                        wire:key="preset-{{ $val }}"
                                        wire:click="applyPreset('{{ $val }}')"
                                        aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                                        class="inline-flex items-center justify-center h-9 px-3 rounded-full
                                               text-[11px] font-semibold tracking-wide whitespace-nowrap
                                               transition-all duration-200 active:scale-95
                                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                               {{ $isActive
                                                  ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm'
                                                  : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100' }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>

                        <button type="button"
                                wire:click="$refresh"
                                wire:loading.attr="disabled"
                                wire:target="$refresh"
                                aria-label="Refresh analytics data"
                                class="inline-flex items-center justify-center w-9 h-9 rounded-full
                                       text-gray-500 dark:text-gray-400
                                       bg-gray-100/80 dark:bg-gray-900/60
                                       border border-gray-200/60 dark:border-white/[0.04]
                                       hover:text-gray-900 dark:hover:text-gray-100
                                       transition-all duration-200 active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M4 9a9 9 0 0014.5 4.5M20 20v-5h-5M20 15a9 9 0 00-14.5-4.5"/>
                            </svg>
                            <span class="sr-only">Refresh</span>
                        </button>

                        <button type="button"
                                wire:click="exportCsv"
                                wire:loading.attr="disabled"
                                wire:target="exportCsv"
                                aria-label="Export analytics as CSV"
                                class="inline-flex items-center justify-center w-9 h-9 rounded-full
                                       text-gray-500 dark:text-gray-400
                                       bg-gray-100/80 dark:bg-gray-900/60
                                       border border-gray-200/60 dark:border-white/[0.04]
                                       hover:text-gray-900 dark:hover:text-gray-100
                                       transition-all duration-200 active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <span class="sr-only">Export</span>
                        </button>

                        <button type="button"
                                onclick="window.print()"
                                aria-label="Print report"
                                class="inline-flex items-center justify-center w-9 h-9 rounded-full
                                       text-gray-500 dark:text-gray-400
                                       bg-gray-100/80 dark:bg-gray-900/60
                                       border border-gray-200/60 dark:border-white/[0.04]
                                       hover:text-gray-900 dark:hover:text-gray-100
                                       transition-all duration-200 active:scale-95
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-6-4h.01M6 18v4h12v-4"/>
                            </svg>
                            <span class="sr-only">Print</span>
                        </button>
                    </div>
                </div>

                {{-- Custom range --}}
                @if($preset === 'custom')
                    <div class="flex flex-wrap items-center gap-3 mb-10 pb-10 border-b border-gray-200/60 dark:border-white/[0.06] no-print">
                        <input type="date" wire:model.live="startDate" aria-label="Start date"
                               class="h-10 px-3 text-sm bg-white/70 dark:bg-gray-900/60 border border-gray-200/70 dark:border-white/[0.06] rounded-xl
                                      focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                      [touch-action:manipulation]">
                        <span class="text-gray-400 dark:text-gray-500 text-xs">to</span>
                        <input type="date" wire:model.live="endDate" aria-label="End date"
                               class="h-10 px-3 text-sm bg-white/70 dark:bg-gray-900/60 border border-gray-200/70 dark:border-white/[0.06] rounded-xl
                                      focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                      [touch-action:manipulation]">
                        <span class="ml-auto text-[11px] text-gray-500 dark:text-gray-400 tabular-nums">
                            {{ $p['period_start']->format('M j, Y') }} – {{ $p['period_end']->format('M j, Y') }}
                            · {{ $p['period_days'] }} day{{ $p['period_days'] === 1 ? '' : 's' }}
                        </span>
                    </div>
                @endif

                {{-- The number — Total Tenants (system-admin metric) --}}
                <div class="mb-10">
                    <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400 mb-2">
                        Total Tenants
                    </p>

                    <div class="flex items-baseline gap-2 flex-wrap">
                        <span class="text-5xl sm:text-6xl lg:text-7xl font-bold text-gray-900 dark:text-white tabular-nums tracking-tight leading-none">
                            {{ number_format($s['total_tenants']) }}
                        </span>
                    </div>

                    <p class="mt-3 text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                        <span class="text-emerald-600 dark:text-emerald-400 font-semibold">{{ number_format($s['active_tenants']) }} active</span>
                        · <span class="{{ $s['pending_tenants'] > 0 ? 'text-amber-600 dark:text-amber-400 font-semibold' : '' }}">{{ number_format($s['pending_tenants']) }} pending</span>
                        · {{ number_format($s['new_this_month']) }} new this month
                    </p>
                </div>

                {{-- Quiet KPI strip --}}
                <div class="pt-6 border-t border-gray-200/60 dark:border-white/[0.06]">
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-y-6 gap-x-4 sm:divide-x sm:divide-gray-200/60 dark:sm:divide-white/[0.06]">

                        <div class="sm:pr-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">New (Month)</p>
                            <p class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">{{ number_format($s['new_this_month']) }}</p>
                        </div>

                        <div class="sm:px-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Active</p>
                            <p class="mt-1.5 text-xl font-bold text-emerald-600 dark:text-emerald-400 tabular-nums leading-none">{{ number_format($s['active_tenants']) }}</p>
                        </div>

                        <div class="sm:px-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] {{ $s['pending_tenants'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}">Pending</p>
                            <p class="mt-1.5 text-xl font-bold tabular-nums leading-none {{ $s['pending_tenants'] > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">{{ number_format($s['pending_tenants']) }}</p>
                        </div>

                        <div class="sm:px-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Users</p>
                            <p class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">{{ number_format($s['total_users']) }}</p>
                        </div>

                        <div class="sm:px-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Events</p>
                            <p class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">{{ number_format($s['total_events']) }}</p>
                        </div>

                        <div class="sm:pl-4">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Active Rate</p>
                            <p class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">{{ $this->activeRate }}<span class="text-sm font-medium text-gray-400 dark:text-gray-500">%</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ═══ REVIEW QUEUE ═══ --}}
        @if($rq['total'] > 0)
            <section>
                <div class="rounded-3xl overflow-hidden
                            bg-amber-50/60 dark:bg-amber-500/[0.05] backdrop-blur-xl
                            border border-amber-200/70 dark:border-amber-500/25">
                    <div class="px-5 sm:px-6 py-4 flex items-center justify-between gap-3
                                border-b border-amber-200/70 dark:border-amber-500/25">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse motion-reduce:animate-none shrink-0" aria-hidden="true"></span>
                            <h2 class="text-sm font-bold text-amber-900 dark:text-amber-200">Review Queue</h2>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300 tabular-nums shrink-0">
                            {{ $rq['total'] }} pending
                        </span>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-0 divide-y lg:divide-y-0 lg:divide-x divide-amber-200/60 dark:divide-amber-500/20">
                        <div class="p-5 sm:p-6">
                            <div class="w-full h-40 sm:h-44 relative overflow-hidden" wire:ignore>
                                <canvas id="reviewChart"
                                        role="img"
                                        aria-label="Bar chart: pending reviews by category"></canvas>
                            </div>
                        </div>

                        <div class="p-5 space-y-2">
                            <a href="{{ route('superadmin.business-applications.index') }}" wire:navigate
                               class="group flex items-center gap-3 p-3 rounded-xl border transition-all duration-200 active:scale-[0.98]
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50
                                      {{ $rq['applications'] > 0
                                         ? 'border-amber-200 dark:border-amber-500/40 bg-white/70 dark:bg-gray-800/40 hover:bg-white dark:hover:bg-gray-800/60'
                                         : 'border-gray-200/60 dark:border-gray-700/40 bg-white/40 dark:bg-gray-900/20 hover:border-amber-300 dark:hover:border-amber-500/40' }}">
                                <span class="w-7 h-7 rounded-lg shrink-0 flex items-center justify-center
                                             {{ $rq['applications'] > 0 ? 'bg-amber-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400' }}">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">KYB Applications</p>
                                    <p class="text-base font-bold text-gray-900 dark:text-white tabular-nums leading-none mt-0.5">{{ $rq['applications'] }}</p>
                                </div>
                                <svg class="w-3.5 h-3.5 shrink-0 text-gray-400 group-hover:text-amber-500 transition-transform duration-200 group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>

                            <a href="{{ route('superadmin.deletion-requests.index') }}" wire:navigate
                               class="group flex items-center gap-3 p-3 rounded-xl border transition-all duration-200 active:scale-[0.98]
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                                      {{ $rq['deletions'] > 0
                                         ? 'border-rose-200 dark:border-rose-500/40 bg-white/70 dark:bg-gray-800/40 hover:bg-white dark:hover:bg-gray-800/60'
                                         : 'border-gray-200/60 dark:border-gray-700/40 bg-white/40 dark:bg-gray-900/20 hover:border-rose-300 dark:hover:border-rose-500/40' }}">
                                <span class="w-7 h-7 rounded-lg shrink-0 flex items-center justify-center
                                             {{ $rq['deletions'] > 0 ? 'bg-rose-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400' }}">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Deletion Requests</p>
                                    <p class="text-base font-bold text-gray-900 dark:text-white tabular-nums leading-none mt-0.5">{{ $rq['deletions'] }}</p>
                                </div>
                                <svg class="w-3.5 h-3.5 shrink-0 text-gray-400 group-hover:text-rose-500 transition-transform duration-200 group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>

                            <a href="{{ route('superadmin.tenants.index') }}" wire:navigate
                               class="group flex items-center gap-3 p-3 rounded-xl border transition-all duration-200 active:scale-[0.98]
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                      {{ $rq['tenants'] > 0
                                         ? 'border-primary-200 dark:border-primary-500/40 bg-white/70 dark:bg-gray-800/40 hover:bg-white dark:hover:bg-gray-800/60'
                                         : 'border-gray-200/60 dark:border-gray-700/40 bg-white/40 dark:bg-gray-900/20 hover:border-primary-300 dark:hover:border-primary-500/40' }}">
                                <span class="w-7 h-7 rounded-lg shrink-0 flex items-center justify-center
                                             {{ $rq['tenants'] > 0 ? 'bg-primary-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400' }}">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Pending Tenants</p>
                                    <p class="text-base font-bold text-gray-900 dark:text-white tabular-nums leading-none mt-0.5">{{ $rq['tenants'] }}</p>
                                </div>
                                <svg class="w-3.5 h-3.5 shrink-0 text-gray-400 group-hover:text-primary-500 transition-transform duration-200 group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- ═══ TRENDS ═══ --}}
        <section>
            <h2 class="text-[10px] font-bold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400 mb-4">
                Trends
            </h2>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                <div class="rounded-3xl p-5 sm:p-6
                            bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                            border border-gray-200/60 dark:border-white/[0.06]">
                    <div class="flex items-baseline justify-between gap-3 mb-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white tracking-tight">Tenant Acquisition</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">New tenants per month, last 6 months.</p>
                        </div>
                        <span class="inline-flex items-center gap-2 text-xs font-semibold text-purple-600 dark:text-purple-400 shrink-0">
                            <span class="w-2.5 h-2.5 rounded-sm bg-purple-500" aria-hidden="true"></span>
                            New
                        </span>
                    </div>

                    @if($tenantAcqHasData)
                        <div class="w-full h-56 relative overflow-hidden" wire:ignore>
                            <canvas id="tenantAcqChart"
                                    role="img"
                                    aria-label="Bar chart: new tenants per month over the last 6 months"></canvas>
                        </div>
                    @else
                        <div class="h-56 flex flex-col items-center justify-center text-center rounded-2xl border border-dashed border-gray-200/80 dark:border-gray-700/60">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">No new tenants yet</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Appears as businesses register.</p>
                        </div>
                    @endif
                </div>

                <div class="rounded-3xl p-5 sm:p-6
                            bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                            border border-gray-200/60 dark:border-white/[0.06]">
                    <div class="flex items-baseline justify-between gap-3 mb-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white tracking-tight">User Growth</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">End-user registrations, last 6 months.</p>
                        </div>
                        <span class="inline-flex items-center gap-2 text-xs font-semibold text-blue-600 dark:text-blue-400 shrink-0">
                            <span class="w-2.5 h-2.5 rounded-sm bg-blue-500" aria-hidden="true"></span>
                            New
                        </span>
                    </div>

                    @if($userGrowthHasData)
                        <div class="w-full h-56 relative overflow-hidden" wire:ignore>
                            <canvas id="userGrowthChart"
                                    role="img"
                                    aria-label="Bar chart: new end-user registrations per month over the last 6 months"></canvas>
                        </div>
                    @else
                        <div class="h-56 flex flex-col items-center justify-center text-center rounded-2xl border border-dashed border-gray-200/80 dark:border-gray-700/60">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">No registrations yet</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Appears as users sign up.</p>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- ═══ COMPOSITION ═══ --}}
        <section>
            <h2 class="text-[10px] font-bold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400 mb-4">
                Composition
            </h2>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                <div class="rounded-3xl p-5 sm:p-6
                            bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                            border border-gray-200/60 dark:border-white/[0.06] flex flex-col">
                    <div class="mb-4">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white tracking-tight">Tenant Status</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Active vs pending.</p>
                    </div>

                    @if($statusHasData)
                        <div class="relative flex-1 min-h-[200px] overflow-hidden" wire:ignore>
                            <canvas id="statusChart"
                                    role="img"
                                    aria-label="Doughnut chart: active versus pending tenant distribution"></canvas>
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                <span class="text-4xl font-bold text-gray-900 dark:text-white tabular-nums leading-none">{{ $this->activeRate }}%</span>
                                <span class="mt-1.5 text-[10px] font-bold uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">Active Rate</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-center gap-6 mt-4 pt-4 border-t border-gray-100/80 dark:border-white/[0.04]">
                            <div class="inline-flex items-center gap-2 text-[10px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                Active
                            </div>
                            <div class="inline-flex items-center gap-2 text-[10px] font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                <span class="w-2.5 h-2.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                                Pending
                            </div>
                        </div>
                    @else
                        <div class="flex-1 min-h-[200px] flex flex-col items-center justify-center text-center rounded-2xl border border-dashed border-gray-200/80 dark:border-gray-700/60">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">No tenants yet</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Distribution appears once businesses are live.</p>
                        </div>
                    @endif
                </div>

                <div class="lg:col-span-2 rounded-3xl p-5 sm:p-6
                            bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                            border border-gray-200/60 dark:border-white/[0.06]">
                    <div class="mb-4">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white tracking-tight">Attraction Categories</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Distribution of tenants by business type.</p>
                    </div>

                    @if($categoriesHasData)
                        <div class="h-56 sm:h-64 relative w-full overflow-hidden" wire:ignore>
                            <canvas id="categoriesChart"
                                    role="img"
                                    aria-label="Polar area chart: tenant distribution by business category"></canvas>
                        </div>
                    @else
                        <div class="h-56 sm:h-64 flex flex-col items-center justify-center text-center rounded-2xl border border-dashed border-gray-200/80 dark:border-gray-700/60">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">No categories yet</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Breakdown appears when tenants are assigned types.</p>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- ═══ TOP SPOTS ═══ --}}
        <section>
            <div class="flex items-end justify-between gap-3 mb-4">
                <h2 class="text-[10px] font-bold uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400">
                    Top Performing Spots
                </h2>
                <a href="{{ route('superadmin.tenants.index') }}" wire:navigate
                   class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 dark:text-primary-400
                          hover:text-primary-800 dark:hover:text-primary-300 transition-colors shrink-0
                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                    View full list
                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>

            <div class="rounded-3xl p-5 sm:p-6
                        bg-white/60 dark:bg-gray-800/30 backdrop-blur-xl
                        border border-gray-200/60 dark:border-white/[0.06]">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Activity ranking — properties with the most successful bookings in the selected period.</p>

                @if($topSpotsHasData)
                    <div class="w-full h-64 sm:h-72 relative overflow-hidden" wire:ignore>
                        <canvas id="topSpotsChart"
                                role="img"
                                aria-label="Horizontal bar chart: top performing spots by total bookings"></canvas>
                    </div>
                @else
                    <div class="h-64 flex flex-col items-center justify-center text-center rounded-2xl border border-dashed border-gray-200/80 dark:border-gray-700/60">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">No bookings this period</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Rankings populate once bookings are created in the selected range.</p>
                    </div>
                @endif
            </div>
        </section>
    </div>
</div>