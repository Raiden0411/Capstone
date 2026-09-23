
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

    /** @return array<int, array{label: string, value: int}> */
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

    /** @return array<string, string> */
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

<?php $__env->startPush('scripts'); ?>
    <?php if (! $__env->hasRenderedOnce('28828c04-0853-4fbd-a5cf-7e1c5b2d2a7c')): $__env->markAsRenderedOnce('28828c04-0853-4fbd-a5cf-7e1c5b2d2a7c'); ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
    <?php endif; ?>
<?php $__env->stopPush(); ?>

<?php $s = $this->stats; ?>

<div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6" wire:poll.60s>

    
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Platform</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="font-display text-3xl md:text-4xl font-semibold text-gray-900 dark:text-white">
                    Platform <em class="italic text-primary-600 dark:text-primary-400">Dashboard</em>
                </h1>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                             bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300
                             border border-emerald-200 dark:border-emerald-500/30">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse motion-reduce:animate-none"></span>
                    Live · 60s
                </span>
            </div>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-2 tabular-nums">
                <?php echo e($this->serverTime->format('l, F j, Y · H:i')); ?>

                · <span class="font-medium text-gray-700 dark:text-gray-300"><?php echo e(app()->environment()); ?></span>
            </p>
        </div>

        <a href="<?php echo e(route('superadmin.analytics')); ?>" wire:navigate
           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                  transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            <span>View Analytics</span>
        </a>
    </div>

    
    <div class="flex flex-wrap items-center gap-2">
        <a href="<?php echo e(route('superadmin.tenants.create')); ?>" wire:navigate
           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                  transition-all duration-200 active:scale-95
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <span>Add Tenant</span>
        </a>
        <a href="<?php echo e(route('superadmin.users.index')); ?>" wire:navigate
           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                  transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span>Manage Users</span>
        </a>
        <a href="<?php echo e(route('superadmin.events.index')); ?>" wire:navigate
           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                  transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span>Manage Events</span>
        </a>
        <a href="<?php echo e(route('superadmin.homepage.editor')); ?>" wire:navigate
           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                  transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            <span>Site Settings</span>
        </a>
    </div>

    
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
        <?php
            $kpi = [
                [
                    'label' => 'Total Tenants',
                    'value' => $s['total_tenants'],
                    'sub'   => $s['active_tenants'] . ' active',
                    'dot'   => 'bg-primary-500',
                    'sub_color' => 'text-emerald-600 dark:text-emerald-400',
                ],
                [
                    'label' => 'Active',
                    'value' => $s['active_tenants'],
                    'sub'   => 'Operational',
                    'dot'   => 'bg-emerald-500',
                    'sub_color' => 'text-gray-500 dark:text-gray-400',
                ],
                [
                    'label' => 'Pending',
                    'value' => $s['pending_tenants'],
                    'sub'   => 'Awaiting action',
                    'dot'   => 'bg-amber-500',
                    'sub_color' => 'text-gray-500 dark:text-gray-400',
                    'value_color' => 'text-amber-600 dark:text-amber-400',
                ],
                [
                    'label' => 'New (Week)',
                    'value' => $s['new_this_week'],
                    'sub'   => 'Onboarded',
                    'dot'   => 'bg-purple-500',
                    'sub_color' => 'text-gray-500 dark:text-gray-400',
                ],
                [
                    'label' => 'Events',
                    'value' => $s['total_events'],
                    'sub'   => $s['upcoming_events'] . ' upcoming',
                    'dot'   => 'bg-sky-500',
                    'sub_color' => 'text-gray-500 dark:text-gray-400',
                ],
                [
                    'label' => 'Featured',
                    'value' => $s['featured_events'],
                    'sub'   => 'Highlighted',
                    'dot'   => 'bg-amber-500',
                    'sub_color' => 'text-gray-500 dark:text-gray-400',
                ],
                [
                    'label' => 'Users',
                    'value' => $s['total_users'],
                    'sub'   => $s['total_roles'] . ' custom roles',
                    'dot'   => 'bg-indigo-500',
                    'sub_color' => 'text-gray-500 dark:text-gray-400',
                ],
            ];
        ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $kpi; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'kpi-'.e($i).''; ?>wire:key="kpi-<?php echo e($i); ?>"
                 class="bg-white dark:bg-gray-800/90 rounded-xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-3.5">
                <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full <?php echo e($card['dot']); ?>"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 truncate"><?php echo e($card['label']); ?></span>
                </div>
                <p class="mt-1.5 text-xl font-bold <?php echo e($card['value_color'] ?? 'text-gray-900 dark:text-white'); ?> tabular-nums">
                    <?php echo e(number_format($card['value'])); ?>

                </p>
                <p class="text-[10px] font-medium <?php echo e($card['sub_color']); ?> mt-0.5 uppercase tracking-wider truncate"><?php echo e($card['sub']); ?></p>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($s['pending_tenants'] > 0): ?>
        <div class="bg-amber-50 dark:bg-amber-500/[0.06] border-l-4 border-amber-500 rounded-2xl p-4 sm:p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-start gap-3 min-w-0">
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="min-w-0">
                        <p class="font-semibold text-amber-900 dark:text-amber-200">
                            Pending Approvals
                        </p>
                        <p class="text-xs sm:text-sm text-amber-800/80 dark:text-amber-300/80 mt-0.5">
                            <?php echo e($s['pending_tenants']); ?> <?php echo e(\Illuminate\Support\Str::plural('business', $s['pending_tenants'])); ?> waiting for activation.
                        </p>
                    </div>
                </div>
                <a href="<?php echo e(route('superadmin.tenants.index')); ?>" wire:navigate
                   class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg
                          border border-amber-300 dark:border-amber-500/40
                          bg-white dark:bg-gray-800 text-amber-700 dark:text-amber-300
                          text-xs font-semibold
                          transition-all duration-200 active:scale-95
                          hover:bg-amber-50 dark:hover:bg-amber-500/10
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50">
                    <span>Review Tenants</span>
                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6">
        <div class="flex items-start justify-between gap-3 mb-4">
            <div class="min-w-0">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white truncate">Tenant Growth</h2>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 truncate">New registrations over the last 6 months.</p>
            </div>
            <span class="inline-flex items-center gap-1.5 text-[10px] text-gray-500 dark:text-gray-400 shrink-0">
                <span class="w-2 h-2 rounded-full bg-cyan-500 inline-block" aria-hidden="true"></span>
                New Tenants
            </span>
        </div>

        <div id="chart-container"
             data-sparkline="<?php echo e(json_encode($this->tenantSparkline, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG)); ?>"
             class="w-full h-40 relative">
            <div class="w-full h-full" wire:ignore>
                <canvas id="sparklineChart"></canvas>
            </div>
        </div>
    </div>

    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm overflow-hidden flex flex-col">
            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700/60 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Recently Onboarded
                    </h2>
                </div>
                <a href="<?php echo e(route('superadmin.tenants.index')); ?>" wire:navigate
                   class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 dark:text-primary-400
                          hover:text-primary-700 dark:hover:text-primary-300
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded
                          active:scale-95 transition">
                    <span>View all</span>
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
            <div class="p-4 space-y-2 flex-1">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->recentTenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'tenant-'.e($tenant->id).''; ?>wire:key="tenant-<?php echo e($tenant->id); ?>"
                         class="flex items-center gap-3 p-3 rounded-xl
                                bg-gray-50 dark:bg-gray-900/40 border border-gray-200/60 dark:border-gray-700/60
                                hover:bg-gray-100 dark:hover:bg-gray-700/40 transition-colors">
                        <div class="w-10 h-10 rounded-lg bg-primary-50 dark:bg-primary-500/15
                                    border border-primary-200/50 dark:border-primary-500/20
                                    flex items-center justify-center font-bold text-sm
                                    text-primary-700 dark:text-primary-300 shrink-0">
                            <?php echo e(strtoupper(substr($tenant->name, 0, 1))); ?>

                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?php echo e($tenant->name); ?></p>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate tabular-nums">
                                <?php echo e($tenant->created_at->diffForHumans()); ?>

                            </p>
                        </div>
                        <a href="<?php echo e(route('superadmin.tenants.edit', $tenant->id)); ?>" wire:navigate
                           aria-label="Manage <?php echo e($tenant->name); ?>"
                           title="Manage"
                           class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-primary-600 dark:text-primary-400
                                  hover:bg-primary-50 dark:hover:bg-primary-500/10
                                  transition-all duration-200 active:scale-95
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="flex flex-col items-center justify-center py-8 text-center text-gray-500 dark:text-gray-400">
                        <svg class="w-10 h-10 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <p class="text-sm">No tenants onboarded yet.</p>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm overflow-hidden flex flex-col">
            <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700/60 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Recent Registrations
                    </h2>
                </div>
                <a href="<?php echo e(route('superadmin.users.index')); ?>" wire:navigate
                   class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 dark:text-primary-400
                          hover:text-primary-700 dark:hover:text-primary-300
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded
                          active:scale-95 transition">
                    <span>View all</span>
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
            <div class="p-4 space-y-2 flex-1">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->recentUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'user-'.e($user->id).''; ?>wire:key="user-<?php echo e($user->id); ?>"
                         class="flex items-center gap-3 p-3 rounded-xl
                                bg-gray-50 dark:bg-gray-900/40 border border-gray-200/60 dark:border-gray-700/60
                                hover:bg-gray-100 dark:hover:bg-gray-700/40 transition-colors">
                        <div class="w-10 h-10 rounded-lg bg-indigo-50 dark:bg-indigo-500/15
                                    border border-indigo-200/50 dark:border-indigo-500/20
                                    flex items-center justify-center font-bold text-sm
                                    text-indigo-700 dark:text-indigo-300 shrink-0">
                            <?php echo e(strtoupper(substr($user->name, 0, 1))); ?>

                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?php echo e($user->name); ?></p>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate"><?php echo e($user->email); ?></p>
                        </div>
                        <span class="text-[10px] text-gray-500 dark:text-gray-400 shrink-0
                                     bg-white dark:bg-gray-900 px-2 py-1 rounded-md tabular-nums
                                     border border-gray-200 dark:border-gray-700">
                            <?php echo e($user->created_at->diffForHumans()); ?>

                        </span>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="flex flex-col items-center justify-center py-8 text-center text-gray-500 dark:text-gray-400">
                        <svg class="w-10 h-10 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <p class="text-sm">No users registered yet.</p>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>

    
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700/60 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <div>
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Upcoming Events
                    </h2>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Next active events across the platform.</p>
                </div>
            </div>
            <a href="<?php echo e(route('superadmin.events.index')); ?>" wire:navigate
               class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 dark:text-primary-400
                      hover:text-primary-700 dark:hover:text-primary-300
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded
                      active:scale-95 transition">
                <span>View all</span>
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->upcomingEvents->isNotEmpty()): ?>
            <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->upcomingEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <a href="<?php echo e(route('superadmin.events.edit', $event)); ?>" wire:navigate
                       <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'upcoming-'.e($event->id).''; ?>wire:key="upcoming-<?php echo e($event->id); ?>"
                       class="group flex items-start gap-3 p-3 rounded-xl
                              bg-gray-50 dark:bg-gray-900/40 border border-gray-200/60 dark:border-gray-700/60
                              hover:bg-gray-100 dark:hover:bg-gray-700/40 hover:border-primary-200 dark:hover:border-primary-500/30
                              transition-all duration-200 active:scale-[0.99]
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <div class="w-14 h-14 rounded-lg overflow-hidden shrink-0
                                    bg-gradient-to-br from-primary-100 to-primary-50 dark:from-primary-500/15 dark:to-primary-500/5
                                    border border-primary-200/60 dark:border-primary-500/20 flex items-center justify-center">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->image_path): ?>
                                <img src="<?php echo e(asset('storage/' . $event->image_path)); ?>"
                                     class="w-full h-full object-cover"
                                     alt="<?php echo e($event->name); ?>"
                                     loading="lazy"
                                     decoding="async">
                            <?php else: ?>
                                <svg class="w-6 h-6 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                                <?php echo e($event->type); ?>

                            </p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate mt-0.5">
                                <?php echo e($event->name); ?>

                            </p>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-1 tabular-nums">
                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <?php echo e($event->start_date?->format('M d, Y') ?? '—'); ?>

                            </p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->barangay): ?>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 truncate"><?php echo e($event->barangay); ?></p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="p-10 text-center">
                <div class="w-14 h-14 mx-auto rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">No upcoming events</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Nothing scheduled at the moment.</p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <?php $sys = $this->systemInfo; ?>
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6">
        <div class="flex items-center gap-3 mb-4">
            <span class="w-5 h-px bg-primary-600"></span>
            <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                System Overview
            </h2>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <?php
                $infoCells = [
                    ['label' => 'PHP',         'value' => $sys['php']],
                    ['label' => 'Laravel',     'value' => $sys['laravel']],
                    ['label' => 'Environment', 'value' => $sys['environment']],
                    ['label' => 'Debug',       'value' => $sys['debug']],
                    ['label' => 'Cache',       'value' => $sys['cache']],
                    ['label' => 'Queue',       'value' => $sys['queue']],
                ];
            ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $infoCells; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cell): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="bg-gray-50 dark:bg-gray-900/40 rounded-xl p-3.5 border border-gray-200/60 dark:border-gray-700/60">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400"><?php echo e($cell['label']); ?></p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white mt-1 font-mono truncate" title="<?php echo e($cell['value']); ?>">
                        <?php echo e($cell['value']); ?>

                    </p>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    </div>
</div>


<script>
(function () {
    'use strict';

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

    window.renderDashboardSparkline();

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
</script><?php /**PATH C:\laragon\www\Capstone\resources\views\superadmin\pages\dashboard\⚡dashboard-page.blade.php ENDPATH**/ ?>