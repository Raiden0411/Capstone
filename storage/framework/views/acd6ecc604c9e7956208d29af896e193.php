<?php
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto space-y-6 text-gray-900 dark:text-white">

    
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Manage Businesses</h1>
            <p class="text-gray-500 dark:text-slate-400 mt-1">View, search, approve, edit, and remove tourist spots from the city platform.</p>
        </div>
        <a href="<?php echo e(route('superadmin.tenants.create')); ?>" wire:navigate class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-5 rounded-xl shadow transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Register Business
        </a>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('message')): ?>
        <div class="bg-green-50 dark:bg-green-500/10 border-l-4 border-green-500 p-4 rounded-md shadow-sm">
            <p class="text-sm text-green-700 dark:text-green-400 font-medium"><?php echo e(session('message')); ?></p>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm overflow-hidden">
        
        
        <div class="p-4 border-b border-gray-200 dark:border-slate-700/50 bg-gray-50 dark:bg-slate-800/50 flex flex-col sm:flex-row gap-3">
            <div class="relative w-full sm:w-1/3">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or email..." 
                       class="w-full pl-10 rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-300 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500 text-sm py-2.5">
            </div>
            <select wire:model.live="statusFilter" 
                    class="w-full sm:w-1/4 rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-300 focus:ring-blue-500 focus:border-blue-500 text-sm py-2.5">
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Pending Approval</option>
            </select>
        </div>
        
        
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-gray-50 dark:bg-slate-800/50 border-b border-gray-200 dark:border-slate-700/50 text-gray-600 dark:text-slate-400 uppercase text-xs font-semibold">
                    <tr>
                        <th class="px-4 sm:px-6 py-4">Tourist Spot / Business</th>
                        <th class="px-4 sm:px-6 py-4 hidden sm:table-cell">Contact Details</th>
                        <th class="px-4 sm:px-6 py-4 hidden lg:table-cell">Location</th>
                        <th class="px-4 sm:px-6 py-4">Status</th>
                        <th class="px-4 sm:px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-slate-700/30 text-gray-700 dark:text-slate-300">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->tenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            
                            <td class="px-4 sm:px-6 py-4">
                                <div class="font-bold text-gray-900 dark:text-white"><?php echo e($tenant->name); ?></div>
                                <div class="mt-1 flex items-center gap-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/30">
                                        <?php echo e($tenant->typeOfTenant->type ?? 'Uncategorized'); ?>

                                    </span>
                                    <span class="text-xs text-gray-400 dark:text-slate-500 hidden sm:inline">ID: <?php echo e($tenant->id); ?></span>
                                </div>
                                
                                <div class="sm:hidden mt-1 space-y-0.5 text-xs text-gray-500 dark:text-slate-400">
                                    <div><?php echo e($tenant->email); ?></div>
                                    <div><?php echo e($tenant->contact_number ?? 'No contact number'); ?></div>
                                </div>
                            </td>

                            
                            <td class="px-4 sm:px-6 py-4 hidden sm:table-cell">
                                <div class="text-gray-900 dark:text-white font-medium"><?php echo e($tenant->email); ?></div>
                                <div class="text-xs text-gray-500 dark:text-slate-400 mt-0.5"><?php echo e($tenant->contact_number ?? 'No contact number'); ?></div>
                            </td>

                            
                            <td class="px-4 sm:px-6 py-4 hidden lg:table-cell">
                                <span class="truncate max-w-[200px] block text-gray-600 dark:text-slate-400" title="<?php echo e($tenant->address); ?>">
                                    <?php echo e($tenant->address); ?>

                                </span>
                            </td>

                            
                            <td class="px-4 sm:px-6 py-4">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->is_active): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-500/20 text-green-800 dark:text-green-400">
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-500/20 text-amber-800 dark:text-amber-400">
                                        Pending
                                    </span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>

                            
                            <td class="px-4 sm:px-6 py-4 text-right space-x-2 whitespace-nowrap">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$tenant->is_active): ?>
                                    <button wire:click="approve(<?php echo e($tenant->id); ?>)" wire:confirm="Approve this business and activate its owner account?" 
                                            class="bg-green-600 hover:bg-green-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg shadow-sm transition-colors">
                                        Approve
                                    </button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <a href="<?php echo e(route('superadmin.tenants.edit', $tenant->id)); ?>" wire:navigate class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors font-medium">Edit</a>
                                <button wire:click="deleteTenant(<?php echo e($tenant->id); ?>)" wire:confirm="Are you sure you want to delete this business? This will also remove all their properties and bookings." class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors font-medium">Delete</button>
                            </td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No businesses found</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Get started by registering a new tourist spot.</p>
                            </td>
                        </tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
        
        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->tenants->hasPages()): ?>
            <div class="p-4 border-t border-gray-200 dark:border-slate-700/50 bg-gray-50 dark:bg-slate-800/50">
                <?php echo e($this->tenants->links()); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/295392c8.blade.php ENDPATH**/ ?>