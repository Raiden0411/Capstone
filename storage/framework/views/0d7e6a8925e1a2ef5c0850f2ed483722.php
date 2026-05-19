<?php
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\Booking;
use App\Models\Customer;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
?>

<?php $__env->startPush('styles'); ?>

<?php $__env->stopPush(); ?>

<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto space-y-6">

    
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-white">Property Inventory</h1>
            <p class="text-white/60 mt-1">Manage your tourist‑spot properties, attractions, and bookable items.</p>
        </div>
        <a href="<?php echo e(route('tenant.properties.create')); ?>" wire:navigate class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-sm font-semibold shadow-lg shadow-brand-500/20 transition hover:scale-105">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Property
        </a>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('message')): ?>
        <div class="glass-card border-l-4 border-l-brand-400 p-4 text-sm text-white/80 flex items-center gap-3">
            <svg class="w-4 h-4 text-brand-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <?php echo e(session('message')); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('error')): ?>
        <div class="glass-card border-l-4 border-l-red-400 p-4 text-sm text-white/80 flex items-center gap-3">
            <svg class="w-4 h-4 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.293 10.293a1 1 0 011.414 0L12 10.586l.293-.293a1 1 0 111.414 1.414L13.414 12l.293.293a1 1 0 01-1.414 1.414L12 13.414l-.293.293a1 1 0 01-1.414-1.414L10.586 12l-.293-.293a1 1 0 010-1.414z"/></svg>
            <?php echo e(session('error')); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="glass-card !rounded-xl p-5">
            <p class="text-sm text-white/50">Total</p>
            <p class="text-2xl font-bold text-white"><?php echo e($this->stats['total']); ?></p>
        </div>
        <div class="glass-card !rounded-xl p-5">
            <p class="text-sm text-green-400">Available</p>
            <p class="text-2xl font-bold text-green-400"><?php echo e($this->stats['available']); ?></p>
        </div>
        <div class="glass-card !rounded-xl p-5">
            <p class="text-sm text-amber-400">Occupied</p>
            <p class="text-2xl font-bold text-amber-400"><?php echo e($this->stats['occupied']); ?></p>
        </div>
        <div class="glass-card !rounded-xl p-5">
            <p class="text-sm text-red-400">Maintenance</p>
            <p class="text-2xl font-bold text-red-400"><?php echo e($this->stats['maintenance']); ?></p>
        </div>
    </div>

    
    <div class="glass-card !rounded-xl p-4">
        <div class="flex flex-col md:flex-row gap-4 items-start md:items-center">
            <div class="relative flex-1 w-full">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or description..." 
                       class="w-full bg-white/5 border border-white/10 rounded-xl py-3 pl-10 pr-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <div class="flex flex-wrap gap-2 w-full md:w-auto">
                <select wire:model.live="typeFilter" class="bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white/80 focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition appearance-none">
                    <option value="">All Types</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->propertyTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <option value="<?php echo e($type->id); ?>"><?php echo e($type->name); ?></option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
                <select wire:model.live="statusFilter" class="bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white/80 focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition appearance-none">
                    <option value="">All Status</option>
                    <option value="available">Available</option>
                    <option value="occupied">Occupied</option>
                    <option value="maintenance">Maintenance</option>
                </select>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($search || $typeFilter || $statusFilter !== ''): ?>
                    <button wire:click="clearFilters" class="px-4 py-2 rounded-xl border border-white/20 text-white/60 hover:bg-white/10 text-xs font-semibold uppercase tracking-wider transition">✕ Clear</button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($selectedProperties) > 0): ?>
    <div class="glass-card !rounded-lg p-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-brand-400/30">
        <span class="text-sm text-brand-400 font-medium"><?php echo e(count($selectedProperties)); ?> selected</span>
        <div class="flex flex-wrap gap-2">
            <button wire:click="bulkActivate" class="text-xs bg-green-600 hover:bg-green-500 text-white px-3 py-1.5 rounded-lg shadow-sm transition-colors">Activate</button>
            <button wire:click="bulkDeactivate" class="text-xs bg-yellow-600 hover:bg-yellow-500 text-white px-3 py-1.5 rounded-lg shadow-sm transition-colors">Deactivate</button>
            <button wire:click="bulkChangeStatus('available')" class="text-xs bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1.5 rounded-lg shadow-sm transition-colors">Set Available</button>
            <button wire:click="bulkChangeStatus('occupied')" class="text-xs bg-amber-600 hover:bg-amber-500 text-white px-3 py-1.5 rounded-lg shadow-sm transition-colors">Set Occupied</button>
            <button wire:click="bulkChangeStatus('maintenance')" class="text-xs bg-red-600 hover:bg-red-500 text-white px-3 py-1.5 rounded-lg shadow-sm transition-colors">Set Maintenance</button>
            <button wire:click="bulkDelete" wire:confirm="Delete selected properties? This cannot be undone." class="text-xs bg-red-700 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg shadow-sm transition-colors">Delete</button>
            <button wire:click="$set('selectedProperties', [])" class="text-xs border border-white/20 text-white/60 hover:bg-white/10 px-3 py-1.5 rounded-lg transition-colors">Cancel</button>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="glass-card !rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="border-b border-white/10">
                    <tr>
                        <th class="px-4 py-4 w-10">
                            <input type="checkbox" wire:model.live="selectAll" class="rounded border-white/20 bg-white/5 text-brand-600 focus:ring-brand-500">
                        </th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-white/50 uppercase">Property</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-white/50 uppercase hidden sm:table-cell">Type</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-white/50 uppercase hidden md:table-cell">Capacity</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-white/50 uppercase hidden lg:table-cell">Price</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-white/50 uppercase">Status / Occupancy</th>
                        <th class="px-4 sm:px-6 py-4 text-right text-xs font-semibold text-white/50 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-white/80">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->properties; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $property): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr class="hover:bg-white/5 transition-colors">
                            
                            <td class="px-4 py-4">
                                <input type="checkbox" wire:model.live="selectedProperties" value="<?php echo e($property->id); ?>" class="rounded border-white/20 bg-white/5 text-brand-600 focus:ring-brand-500">
                            </td>

                            
                            <td class="px-4 sm:px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="shrink-0 h-10 w-10 rounded-lg bg-white/5 overflow-hidden">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($property->images->isNotEmpty()): ?>
                                            <img src="<?php echo e(asset('storage/'. $property->images->first()->image_path)); ?>" alt="<?php echo e($property->name); ?>" class="h-full w-full object-cover">
                                        <?php else: ?>
                                            <div class="h-full w-full flex items-center justify-center text-white/20">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                            </div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-medium text-white"><?php echo e($property->name); ?></div>
                                        <div class="text-xs text-white/40 truncate max-w-[150px]"><?php echo e($property->description ?: 'No description'); ?></div>
                                    </div>
                                </div>
                            </td>

                            
                            <td class="px-4 sm:px-6 py-4 text-sm hidden sm:table-cell">
                                <?php echo e($property->propertyType->name ?? '—'); ?>

                            </td>

                            
                            <td class="px-4 sm:px-6 py-4 text-sm hidden md:table-cell">
                                <?php echo e($property->capacity); ?> pers.
                            </td>

                            
                            <td class="px-4 sm:px-6 py-4 text-sm hidden lg:table-cell">
                                ₱<?php echo e(number_format($property->price, 2)); ?>

                            </td>

                            
                            <td class="px-4 sm:px-6 py-4">
                                <select wire:change="updateStatus(<?php echo e($property->id); ?>, $event.target.value)" 
                                        class="text-xs rounded-full px-2 py-1 border-0 font-medium w-28 appearance-none
                                            <?php echo e($property->status === 'available' ? 'bg-green-500/20 text-green-300' : ''); ?>

                                            <?php echo e($property->status === 'occupied' ? 'bg-amber-500/20 text-amber-300' : ''); ?>

                                            <?php echo e($property->status === 'maintenance' ? 'bg-red-500/20 text-red-300' : ''); ?>">
                                    <option value="available" <?php echo e($property->status === 'available' ? 'selected' : ''); ?>>Available</option>
                                    <option value="occupied" <?php echo e($property->status === 'occupied' ? 'selected' : ''); ?>>Occupied</option>
                                    <option value="maintenance" <?php echo e($property->status === 'maintenance' ? 'selected' : ''); ?>>Maintenance</option>
                                </select>

                                <?php
                                    $activeGuest = $this->activeBookings[$property->id][0] ?? null;
                                    $nextArrival = $this->upcomingBookings[$property->id][0] ?? null;
                                ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeGuest): ?>
                                    <div class="text-xs text-white/40 mt-1">
                                        <?php echo e($activeGuest['guest_name']); ?> · until <?php echo e($activeGuest['check_out']); ?>

                                    </div>
                                <?php elseif($nextArrival && $property->status === 'available'): ?>
                                    <div class="text-xs text-blue-400 mt-1">
                                        Next arrival: <?php echo e($nextArrival['check_in']); ?>

                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>

                            
                            <td class="px-4 sm:px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="<?php echo e(route('tenant.properties.edit', $property->id)); ?>" wire:navigate
                                       class="p-1.5 text-brand-400 hover:text-white hover:bg-brand-500/20 rounded-lg transition" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <button wire:click="delete(<?php echo e($property->id); ?>)" wire:confirm="Delete this property?"
                                            class="p-1.5 text-red-400 hover:text-white hover:bg-red-500/20 rounded-lg transition" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-white/40">
                                <svg class="mx-auto h-12 w-12 text-white/10 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                <span class="text-sm">No properties found<?php echo e($search || $typeFilter || $statusFilter !== '' ? ' matching your filters' : ''); ?>.</span>
                            </td>
                        </tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->properties->hasPages()): ?>
            <div class="px-4 sm:px-6 py-4 border-t border-white/10">
                <?php echo e($this->properties->links()); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/a7bab6b7.blade.php ENDPATH**/ ?>