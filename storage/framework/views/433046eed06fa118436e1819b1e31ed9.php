<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;
?>

<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-white/10">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-brand-600 dark:text-brand-400 flex items-center gap-2 mb-2">
                <span class="w-1.5 h-1.5 rounded-full bg-brand-500 shadow-[0_0_8px_var(--color-brand-500)]"></span>
                Super Admin
            </p>
            <h1 class="font-display text-3xl md:text-4xl font-bold text-gray-900 dark:text-white">Platform Dashboard</h1>
        </div>
        <div class="text-xs font-mono text-gray-400 dark:text-white/40 text-right space-y-1">
            <div>System time <span class="text-gray-600 dark:text-white/60"><?php echo e(now()->format('D, d M Y · H:i')); ?></span></div>
            <div>Environment <span class="text-gray-600 dark:text-white/60"><?php echo e(app()->environment()); ?></span></div>
        </div>
    </div>

    
    <?php $s = $this->stats; ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <div class="bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl p-6 shadow-sm dark:shadow-none relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-0.5 bg-brand-500 shadow-[0_0_12px_var(--color-brand-500)]"></div>
            <p class="text-xs font-mono uppercase tracking-widest text-gray-400 dark:text-white/40 mb-3">Businesses / tenants</p>
            <p class="font-mono text-4xl font-light text-brand-600 dark:text-brand-400 mb-4"><?php echo e($s['total_tenants']); ?></p>
            <div class="flex flex-wrap gap-2 mb-4">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-mono font-medium bg-green-100 dark:bg-green-500/20 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-500/30">
                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span> <?php echo e($s['active_tenants']); ?> active
                </span>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-mono font-medium bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30">
                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span> <?php echo e($s['pending_tenants']); ?> pending
                </span>
            </div>
            <div class="space-y-1">
                <div class="flex justify-between text-xs font-mono text-gray-400 dark:text-white/40">
                    <span>Activation rate</span>
                    <span><?php echo e($s['total_tenants'] > 0 ? round(($s['active_tenants'] / $s['total_tenants']) * 100) : 0); ?>%</span>
                </div>
                <div class="w-full h-1 bg-gray-200 dark:bg-white/10 rounded-full overflow-hidden">
                    <div class="h-full rounded-full bg-brand-500 transition-all duration-700" style="width:<?php echo e($s['total_tenants'] > 0 ? round(($s['active_tenants'] / $s['total_tenants']) * 100) : 0); ?>%"></div>
                </div>
            </div>
        </div>

        
        <div class="bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl p-6 shadow-sm dark:shadow-none relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-0.5 bg-amber-400 shadow-[0_0_12px_var(--color-amber-400)]"></div>
            <p class="text-xs font-mono uppercase tracking-widest text-gray-400 dark:text-white/40 mb-3">Awaiting activation</p>
            <p class="font-mono text-4xl font-light text-amber-600 dark:text-amber-400 mb-4"><?php echo e($s['pending_tenants']); ?></p>
            <div class="flex flex-wrap gap-2 mb-4">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-mono font-medium bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30">
                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span> Pending approval
                </span>
            </div>
            <div class="space-y-1">
                <div class="flex justify-between text-xs font-mono text-gray-400 dark:text-white/40">
                    <span>Of total tenants</span>
                    <span><?php echo e($s['total_tenants'] > 0 ? round(($s['pending_tenants'] / $s['total_tenants']) * 100) : 0); ?>%</span>
                </div>
                <div class="w-full h-1 bg-gray-200 dark:bg-white/10 rounded-full overflow-hidden">
                    <div class="h-full rounded-full bg-amber-400 transition-all duration-700" style="width:<?php echo e($s['total_tenants'] > 0 ? round(($s['pending_tenants'] / $s['total_tenants']) * 100) : 0); ?>%"></div>
                </div>
            </div>
        </div>

        
        <div class="bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl p-6 shadow-sm dark:shadow-none relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-0.5 bg-green-500 shadow-[0_0_12px_var(--color-green-500)]"></div>
            <p class="text-xs font-mono uppercase tracking-widest text-gray-400 dark:text-white/40 mb-3">New this week</p>
            <p class="font-mono text-4xl font-light text-green-600 dark:text-green-400 mb-4"><?php echo e($s['new_this_week']); ?></p>
            <div class="flex flex-wrap gap-2 mb-4">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-mono font-medium bg-purple-100 dark:bg-purple-500/20 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-500/30">
                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span> Businesses onboarded
                </span>
            </div>
            <div class="space-y-1">
                <div class="flex justify-between text-xs font-mono text-gray-400 dark:text-white/40">
                    <span>Weekly share</span>
                    <span><?php echo e($s['total_tenants'] > 0 ? round(($s['new_this_week'] / $s['total_tenants']) * 100) : 0); ?>%</span>
                </div>
                <div class="w-full h-1 bg-gray-200 dark:bg-white/10 rounded-full overflow-hidden">
                    <div class="h-full rounded-full bg-green-500 transition-all duration-700" style="width:<?php echo e($s['total_tenants'] > 0 ? round(($s['new_this_week'] / $s['total_tenants']) * 100) : 0); ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    
    <div class="bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl overflow-hidden shadow-sm dark:shadow-none">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-white/10">
            <h2 class="font-display text-lg font-semibold text-gray-900 dark:text-white">System overview</h2>
            <p class="text-xs text-gray-500 dark:text-white/40 mt-1">Platform‑wide counters — read only</p>
        </div>
        <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-gray-50 dark:bg-white/5 rounded-xl p-5">
                <p class="text-xs font-mono uppercase tracking-widest text-gray-400 dark:text-white/40 mb-2">Total users</p>
                <p class="font-mono text-3xl font-light text-gray-900 dark:text-white"><?php echo e($s['total_users']); ?></p>
            </div>
            <div class="bg-gray-50 dark:bg-white/5 rounded-xl p-5">
                <p class="text-xs font-mono uppercase tracking-widest text-gray-400 dark:text-white/40 mb-2">System roles</p>
                <p class="font-mono text-3xl font-light text-gray-900 dark:text-white"><?php echo e($s['total_roles']); ?></p>
            </div>
            <div class="bg-gray-50 dark:bg-white/5 rounded-xl p-5">
                <p class="text-xs font-mono uppercase tracking-widest text-gray-400 dark:text-white/40 mb-2">Total businesses</p>
                <p class="font-mono text-3xl font-light text-gray-900 dark:text-white"><?php echo e($s['total_tenants']); ?></p>
            </div>
        </div>
    </div>

    
    <div class="bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl overflow-hidden shadow-sm dark:shadow-none">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-white/10">
            <h2 class="font-display text-lg font-semibold text-gray-900 dark:text-white">Recently onboarded</h2>
            <p class="text-xs text-gray-500 dark:text-white/40 mt-1">Last 6 businesses registered on the platform</p>
        </div>
        <div class="p-6">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->recentTenants->isEmpty()): ?>
                <div class="text-center py-8 font-mono text-sm text-gray-400 dark:text-white/40">No tenants onboarded yet.</div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->recentTenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 hover:bg-gray-100 dark:hover:bg-white/10 transition-colors group" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 't-'.e($tenant->id).''; ?>wire:key="t-<?php echo e($tenant->id); ?>">
                            <div class="w-9 h-9 rounded-lg bg-brand-100 dark:bg-brand-500/20 border border-brand-200 dark:border-brand-400/20 flex items-center justify-center font-mono text-sm font-medium text-brand-700 dark:text-brand-400 shrink-0">
                                <?php echo e(strtoupper(substr($tenant->name, 0, 1))); ?>

                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate"><?php echo e(Str::limit($tenant->name, 22)); ?></p>
                                <p class="text-xs font-mono text-gray-400 dark:text-white/40"><?php echo e($tenant->created_at->diffForHumans()); ?></p>
                            </div>
                            <a href="<?php echo e(route('superadmin.tenants.edit', $tenant->id)); ?>" wire:navigate class="shrink-0 text-xs font-mono text-gray-400 dark:text-white/40 group-hover:text-brand-600 dark:group-hover:text-brand-400 border border-gray-300 dark:border-white/10 rounded-md px-2 py-1 hover:border-brand-300 dark:hover:border-brand-400/50 transition-colors">
                                Manage →
                            </a>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/e5cc221c.blade.php ENDPATH**/ ?>