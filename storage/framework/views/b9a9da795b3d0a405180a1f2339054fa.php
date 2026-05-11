<?php
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\TypeOfTenant;
?>

<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">

    
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-white/10">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-brand-600 dark:text-brand-400 flex items-center gap-2 mb-2">
                <span class="w-1.5 h-1.5 rounded-full bg-brand-500 shadow-[0_0_8px_var(--color-brand-500)]"></span>
                Super Admin · System
            </p>
            <h1 class="font-display text-3xl md:text-4xl font-bold text-gray-900 dark:text-white">Tenant Types</h1>
            <p class="text-sm text-gray-500 dark:text-white/50 mt-1">Manage the categories used to classify businesses.</p>
        </div>
        <a href="<?php echo e(route('superadmin.tenant-types.create')); ?>" wire:navigate class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-sm font-semibold shadow-lg shadow-brand-500/20 transition hover:scale-105">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Add New Type
        </a>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('success')): ?>
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-400 font-medium">
            <?php echo e(session('success')); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('error')): ?>
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md text-sm text-red-700 dark:text-red-400 font-medium">
            <?php echo e(session('error')); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="relative w-full md:w-1/3">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search types..." 
               class="w-full bg-white dark:bg-white/5 border border-gray-300 dark:border-white/10 rounded-xl py-3 pl-10 pr-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition shadow-sm">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
    </div>

    
    <div class="bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl overflow-hidden shadow-sm dark:shadow-none">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 text-gray-600 dark:text-white/40 uppercase text-xs font-semibold">
                    <tr>
                        <th class="px-4 sm:px-6 py-4">Type</th>
                        <th class="px-4 sm:px-6 py-4 hidden sm:table-cell">Description</th>
                        <th class="px-4 sm:px-6 py-4 text-center">Tenants</th>
                        <th class="px-4 sm:px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5 text-gray-700 dark:text-white/80">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->types; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                            <td class="px-4 sm:px-6 py-4 font-medium text-gray-900 dark:text-white"><?php echo e($type->type); ?></td>
                            <td class="px-4 sm:px-6 py-4 hidden sm:table-cell text-gray-600 dark:text-white/60"><?php echo e($type->description ?? '—'); ?></td>
                            <td class="px-4 sm:px-6 py-4 text-center font-semibold text-gray-900 dark:text-white"><?php echo e($type->tenants_count); ?></td>
                            <td class="px-4 sm:px-6 py-4 text-right whitespace-nowrap space-x-2">
                                <a href="<?php echo e(route('superadmin.tenant-types.edit', $type->id)); ?>" wire:navigate class="text-brand-600 dark:text-brand-400 hover:text-brand-500 dark:hover:text-brand-300 font-medium transition-colors">Edit</a>
                                <button wire:click="delete(<?php echo e($type->id); ?>)" wire:confirm="Delete this tenant type?" class="text-red-500 dark:text-red-400 hover:text-red-600 dark:hover:text-red-300 font-medium transition-colors">Delete</button>
                            </td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-400 dark:text-white/40">
                                <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-white/10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 01.586 1.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
                                <p class="text-sm text-gray-500 dark:text-white/40">No tenant types found.</p>
                            </td>
                        </tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->types->hasPages()): ?>
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                <?php echo e($this->types->links()); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/e37b98c2.blade.php ENDPATH**/ ?>