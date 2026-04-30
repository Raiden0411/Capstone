<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;
?>

<div class="p-6 sm:p-10 max-w-7xl mx-auto space-y-8">
    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Platform Dashboard</h1>
            <p class="text-slate-500">Manage businesses, users, and system settings.</p>
        </div>
        <div class="flex gap-2">
            <a href="<?php echo e(route('superadmin.tenants.create')); ?>" wire:navigate class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-lg shadow-sm transition-all flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Onboard Business
            </a>
            <a href="<?php echo e(route('superadmin.users.create')); ?>" wire:navigate class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-medium py-2.5 px-5 rounded-lg shadow-sm transition-all flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                Create User
            </a>
        </div>
    </div>

    
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-slate-500">Total Businesses</h3>
                <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m3-4h1m-1 4h1m-5 8h8"></path></svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-slate-800"><?php echo e(number_format($this->stats['total_tenants'])); ?></p>
            <p class="text-xs text-slate-500 mt-2"><?php echo e($this->stats['active_tenants']); ?> active</p>
        </div>

        
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-slate-500">Total Users</h3>
                <div class="p-2 bg-indigo-50 rounded-lg text-indigo-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-slate-800"><?php echo e(number_format($this->stats['total_users'])); ?></p>
            <p class="text-xs text-slate-400 mt-2">Across all tenants</p>
        </div>

        
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-slate-500">System Roles</h3>
                <div class="p-2 bg-purple-50 rounded-lg text-purple-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-slate-800"><?php echo e(number_format($this->stats['total_roles'])); ?></p>
            <p class="text-xs text-slate-400 mt-2">Global roles</p>
        </div>

        
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-slate-500">New This Week</h3>
                <div class="p-2 bg-emerald-50 rounded-lg text-emerald-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-slate-800"><?php echo e(number_format($this->stats['recent_tenants_count'])); ?></p>
            <p class="text-xs text-slate-400 mt-2">Businesses onboarded</p>
        </div>
    </div>

    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-2 gap-4">
                <a href="<?php echo e(route('superadmin.tenants.index')); ?>" wire:navigate class="p-4 border border-slate-200 rounded-xl hover:bg-slate-50 transition group">
                    <div class="p-2 bg-blue-50 rounded-lg w-fit mb-3 group-hover:bg-blue-100">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m3-4h1m-1 4h1m-5 8h8"></path></svg>
                    </div>
                    <p class="font-medium text-slate-800">Manage Tenants</p>
                    <p class="text-sm text-slate-500">View and edit businesses</p>
                </a>
                <a href="<?php echo e(route('superadmin.users.index')); ?>" wire:navigate class="p-4 border border-slate-200 rounded-xl hover:bg-slate-50 transition group">
                    <div class="p-2 bg-indigo-50 rounded-lg w-fit mb-3 group-hover:bg-indigo-100">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </div>
                    <p class="font-medium text-slate-800">Manage Users</p>
                    <p class="text-sm text-slate-500">Platform user accounts</p>
                </a>
                <a href="<?php echo e(route('superadmin.roles.index')); ?>" wire:navigate class="p-4 border border-slate-200 rounded-xl hover:bg-slate-50 transition group">
                    <div class="p-2 bg-purple-50 rounded-lg w-fit mb-3 group-hover:bg-purple-100">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <p class="font-medium text-slate-800">Manage Roles</p>
                    <p class="text-sm text-slate-500">Global permissions</p>
                </a>
                <a href="<?php echo e(route('superadmin.tenant-types.index')); ?>" wire:navigate class="p-4 border border-slate-200 rounded-xl hover:bg-slate-50 transition group">
                    <div class="p-2 bg-amber-50 rounded-lg w-fit mb-3 group-hover:bg-amber-100">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 01.586 1.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"></path></svg>
                    </div>
                    <p class="font-medium text-slate-800">Tenant Types</p>
                    <p class="text-sm text-slate-500">Business categories</p>
                </a>
            </div>
        </div>

        
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
                <h3 class="text-md font-semibold text-slate-800">Recently Onboarded</h3>
                <a href="<?php echo e(route('superadmin.tenants.index')); ?>" wire:navigate class="text-xs text-blue-600 hover:text-blue-800">View All &rarr;</a>
            </div>
            <div class="divide-y divide-slate-200">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->recentTenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="px-6 py-4 flex items-center justify-between hover:bg-slate-50">
                        <div class="flex items-center gap-3">
                            <div class="h-8 w-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-sm">
                                <?php echo e(substr($tenant->name, 0, 1)); ?>

                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-800"><?php echo e($tenant->name); ?></p>
                                <p class="text-xs text-slate-500"><?php echo e($tenant->created_at->diffForHumans()); ?></p>
                            </div>
                        </div>
                        <a href="<?php echo e(route('superadmin.tenants.edit', $tenant->id)); ?>" wire:navigate class="text-xs text-blue-600 hover:text-blue-800">Manage</a>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="px-6 py-4 text-center text-slate-500 text-sm">No tenants yet.</div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/e5cc221c.blade.php ENDPATH**/ ?>