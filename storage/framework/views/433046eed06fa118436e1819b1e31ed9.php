<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Event;
use Spatie\Permission\Models\Role;
?>




<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-8" wire:poll.60s>

    
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                Platform Dashboard
            </h1>
            <p class="text-sm sm:text-base text-gray-600 dark:text-gray-300 mt-1">
                Super Admin Overview · <?php echo e(now()->format('F j, Y')); ?>

            </p>
        </div>
        <div class="text-right">
            <div class="text-xs text-gray-500 dark:text-gray-400">
                System time <span class="text-gray-700 dark:text-gray-200"><?php echo e(now()->format('D, d M Y · H:i')); ?></span>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                Environment <span class="text-gray-700 dark:text-gray-200"><?php echo e(app()->environment()); ?></span>
            </div>
        </div>
    </div>

    
    <div class="flex flex-wrap gap-2">
        <a href="<?php echo e(route('superadmin.tenants.create')); ?>" wire:navigate
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-lg shadow-primary-500/20 transition-all duration-200 hover:scale-105 focus-visible:ring-2 focus-visible:ring-primary-500/50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Tenant
        </a>
        <a href="<?php echo e(route('superadmin.users.index')); ?>" wire:navigate
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm font-semibold transition-all duration-200 focus-visible:ring-2 focus-visible:ring-primary-500/50">
            Manage Users
        </a>
        <a href="<?php echo e(route('superadmin.analytics')); ?>" wire:navigate
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm font-semibold transition-all duration-200 focus-visible:ring-2 focus-visible:ring-primary-500/50">
            View Reports
        </a>
        <a href="<?php echo e(route('superadmin.homepage.editor')); ?>" wire:navigate
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm font-semibold transition-all duration-200 focus-visible:ring-2 focus-visible:ring-primary-500/50">
            Edit Site Settings
        </a>
        <a href="<?php echo e(route('superadmin.events.index')); ?>" wire:navigate
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm font-semibold transition-all duration-200 focus-visible:ring-2 focus-visible:ring-primary-500/50">
            Manage Events
        </a>
    </div>

    
    <?php $s = $this->stats; ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Tenants</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2"><?php echo e($s['total_tenants']); ?></p>
            <p class="text-xs text-green-600 dark:text-green-400 mt-1"><?php echo e($s['active_tenants']); ?> active</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Active Tenants</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2"><?php echo e($s['active_tenants']); ?></p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">operational</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pending Tenants</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-2"><?php echo e($s['pending_tenants']); ?></p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">awaiting activation</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">New This Week</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2"><?php echo e($s['new_this_week']); ?></p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">businesses onboarded</p>
        </div>
    </div>

    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Events</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2"><?php echo e($s['total_events']); ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Upcoming Events</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2"><?php echo e($s['upcoming_events']); ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Featured Events</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2"><?php echo e($s['featured_events']); ?></p>
        </div>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($s['pending_tenants'] > 0): ?>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 border-l-4 border-l-amber-500 rounded-xl p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="font-semibold text-amber-600 dark:text-amber-400">Pending Approval</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-1"><?php echo e($s['pending_tenants']); ?> businesses are waiting for activation.</p>
                </div>
                <a href="<?php echo e(route('superadmin.tenants.index')); ?>" wire:navigate
                   class="px-4 py-2 rounded-full bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 text-sm font-semibold border border-amber-200 dark:border-amber-500/20 hover:bg-amber-100 dark:hover:bg-amber-500/20 transition focus-visible:ring-2 focus-visible:ring-amber-500/50">
                    Review Tenants →
                </a>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Tenant Growth — Last 6 Months</h2>
        <div class="w-full h-40">
            <canvas id="sparklineChart"></canvas>
        </div>
    </div>

    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <h2 class="font-bold text-gray-900 dark:text-white">Recently Onboarded</h2>
                <a href="<?php echo e(route('superadmin.tenants.index')); ?>" wire:navigate class="text-sm font-semibold text-primary-600 hover:underline focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">View all →</a>
            </div>
            <div class="p-6 space-y-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->recentTenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <div class="w-9 h-9 rounded-lg bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 flex items-center justify-center font-mono text-sm font-medium text-blue-700 dark:text-blue-300 shrink-0">
                            <?php echo e(strtoupper(substr($tenant->name, 0, 1))); ?>

                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate"><?php echo e(Str::limit($tenant->name, 22)); ?></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($tenant->created_at->diffForHumans()); ?></p>
                        </div>
                        <a href="<?php echo e(route('superadmin.tenants.edit', $tenant->id)); ?>" wire:navigate class="text-xs font-semibold text-primary-600 hover:underline shrink-0 focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">Manage →</a>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400 py-6 text-center">No tenants onboarded yet.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <h2 class="font-bold text-gray-900 dark:text-white">Recent User Registrations</h2>
                <a href="<?php echo e(route('superadmin.users.index')); ?>" wire:navigate class="text-sm font-semibold text-primary-600 hover:underline focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">View all →</a>
            </div>
            <div class="p-6 space-y-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->recentUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <div class="w-9 h-9 rounded-lg bg-purple-50 dark:bg-purple-500/10 border border-purple-200 dark:border-purple-500/20 flex items-center justify-center font-mono text-sm font-medium text-purple-700 dark:text-purple-300 shrink-0">
                            <?php echo e(strtoupper(substr($user->name, 0, 1))); ?>

                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate"><?php echo e(Str::limit($user->name, 22)); ?></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($user->email); ?></p>
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400 shrink-0"><?php echo e($user->created_at->diffForHumans()); ?></span>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400 py-6 text-center">No users registered yet.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>

    
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h2 class="font-bold text-gray-900 dark:text-white">Upcoming Events</h2>
            <a href="<?php echo e(route('superadmin.events.index')); ?>" wire:navigate class="text-sm font-semibold text-primary-600 hover:underline focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">View all →</a>
        </div>
        <div class="p-6 space-y-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->recentEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <div class="w-10 h-10 rounded-lg bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 flex items-center justify-center text-lg shrink-0">
                        <?php echo e($event->category->icon ?? '🎉'); ?>

                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate"><?php echo e($event->name); ?></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($event->start_date->format('M d, Y')); ?> · <?php echo e($event->barangay); ?></p>
                    </div>
                    <a href="<?php echo e(route('superadmin.events.edit', $event->id)); ?>" wire:navigate class="text-xs font-semibold text-primary-600 hover:underline shrink-0 focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">Manage →</a>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <p class="text-sm text-gray-500 dark:text-gray-400 py-6 text-center">No upcoming events.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">System Overview</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">PHP Version</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white mt-1"><?php echo e(PHP_VERSION); ?></p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Laravel Version</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white mt-1"><?php echo e(app()->version()); ?></p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Users</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white mt-1"><?php echo e($s['total_users']); ?></p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">System Roles</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white mt-1"><?php echo e($s['total_roles']); ?></p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Businesses</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white mt-1"><?php echo e($s['total_tenants']); ?></p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">New This Month</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white mt-1"><?php echo e($s['new_this_month']); ?></p>
            </div>
        </div>
    </div>

        <?php
        $__scriptKey = '709987871-0';
        ob_start();
    ?>
    <script>
        let sparklineChart = null;

        function getChartTheme() {
            return document.documentElement.classList.contains('dark') ? {
                textColor: '#9ca3af',
                gridColor: 'rgba(255,255,255,0.06)',
                lineColor: '#22d3ee',
                fillColor: 'rgba(34,211,238,0.10)',
            } : {
                textColor: '#4b5563',
                gridColor: 'rgba(0,0,0,0.08)',
                lineColor: '#0891b2',
                fillColor: 'rgba(8,145,178,0.10)',
            };
        }

        function renderSparkline(data) {
            if (sparklineChart) {
                sparklineChart.destroy();
                sparklineChart = null;
            }

            const canvas = document.getElementById('sparklineChart');
            if (!canvas || !data || data.length === 0) return;

            const theme = getChartTheme();
            const ctx = canvas.getContext('2d');
            sparklineChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.label),
                    datasets: [{
                        data: data.map(d => d.value),
                        borderColor: theme.lineColor,
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        backgroundColor: theme.fillColor,
                        pointRadius: 3,
                        pointBackgroundColor: theme.lineColor,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { color: theme.textColor }, grid: { color: theme.gridColor } },
                        x: { ticks: { color: theme.textColor }, grid: { display: false } }
                    }
                }
            });
        }

        const sparklineData = <?php echo \Illuminate\Support\Js::from($this->tenantSparkline)->toHtml() ?>;

        function initCharts() {
            if (typeof Chart !== 'undefined') {
                renderSparkline(sparklineData);
            } else {
                document.addEventListener('DOMContentLoaded', () => {
                    renderSparkline(sparklineData);
                });
            }
        }

        initCharts();

        Livewire.hook('morphed', () => {
            renderSparkline(sparklineData);
        });

        const observer = new MutationObserver(() => {
            renderSparkline(sparklineData);
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    </script>
        <?php
        $__output = ob_get_clean();

        \Livewire\store($this)->push('scripts', $__output, $__scriptKey)
    ?>

</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/e5cc221c.blade.php ENDPATH**/ ?>