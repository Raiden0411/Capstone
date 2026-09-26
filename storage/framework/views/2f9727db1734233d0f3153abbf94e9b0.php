<?php
use App\Models\User;
use App\Models\UserNotification;
use App\Services\UserNotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
?>




<?php $__env->startPush('styles'); ?>
    <?php if (! $__env->hasRenderedOnce('baadb4c2-3128-4aca-8657-249a99eb6a3d')): $__env->markAsRenderedOnce('baadb4c2-3128-4aca-8657-249a99eb6a3d'); ?>
        <style>
            .sa-notif-dropdown {
                animation: saNotifDropdownIn .15s cubic-bezier(.16,1,.3,1);
            }
            @keyframes saNotifDropdownIn {
                from { opacity: 0; transform: scale(.96) translateY(-4px); }
                to   { opacity: 1; transform: scale(1) translateY(0); }
            }
            @media (prefers-reduced-motion: reduce) {
                .sa-notif-dropdown { animation: none; }
            }
        </style>
    <?php endif; ?>
<?php $__env->stopPush(); ?>

<div
    x-data="{
        open: false,
        toggle() {
            this.open = ! this.open;
            if (this.open && $wire.unreadCount > 0) {
                $wire.markAllRead();
            }
        }
    }"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
    class="relative"
    wire:poll.30s
>
    <button type="button"
            @click="toggle()"
            :aria-expanded="open.toString()"
            aria-haspopup="true"
            aria-label="Notifications"
            class="relative flex items-center justify-center size-11 sm:size-9 rounded-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800
                   text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white
                   transition-all duration-200 active:scale-95 touch-manipulation
                   [-webkit-tap-highlight-color:transparent]
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->unreadCount > 0): ?>
            <span class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center min-w-4.5 h-4.5 px-1 rounded-full
                         bg-rose-500 text-white text-[10px] font-bold ring-2 ring-white dark:ring-gray-900 tabular-nums"
                  aria-hidden="true">
                <?php echo e($this->unreadCount > 99 ? '99+' : $this->unreadCount); ?>

            </span>
            <span class="sr-only"><?php echo e($this->unreadCount); ?> unread notifications</span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </button>

    
    <div x-cloak
         :class="open ? 'sa-notif-dropdown' : 'hidden'"
         class="fixed inset-x-3 top-[calc(4rem+env(safe-area-inset-top)+0.5rem)] z-50
                sm:absolute sm:inset-auto sm:right-0 sm:mt-2 sm:w-80 md:w-96
                bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700
                rounded-2xl shadow-2xl overflow-hidden"
         role="menu"
         aria-label="Notifications">

        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 min-w-0">
                <span class="w-5 h-px bg-primary-600 shrink-0"></span>
                <p class="text-sm font-bold text-gray-900 dark:text-white">Notifications</p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->unreadCount > 0): ?>
                    <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-rose-500 text-white text-[10px] font-bold tabular-nums shrink-0">
                        <?php echo e($this->unreadCount); ?>

                    </span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <button type="button"
                    @click="open = false"
                    aria-label="Close notifications"
                    class="inline-flex items-center justify-center size-11 sm:size-9 shrink-0 rounded-md text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700
                           transition-all duration-200 touch-manipulation
                           [-webkit-tap-highlight-color:transparent]
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                </svg>
            </button>
        </div>

        <div class="max-h-[420px] overflow-y-auto">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->recent; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $isUnread = $item->isUnread();
                    $itemUrl  = $item->resolvedUrl(auth()->user());
                ?>
                <a href="<?php echo e($itemUrl ?? '#'); ?>"
                   <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'notif-'.e($item->id).''; ?>wire:key="notif-<?php echo e($item->id); ?>"
                   wire:click.prevent="open(<?php echo e($item->id); ?>)"
                   @click="open = false"
                   role="menuitem"
                   class="flex items-start gap-3 px-4 py-3 min-h-[44px] border-b border-gray-100 dark:border-gray-700/60 last:border-b-0
                          <?php echo e($isUnread ? 'bg-primary-50/40 dark:bg-primary-500/5' : ''); ?>

                          hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors touch-manipulation
                          [-webkit-tap-highlight-color:transparent]
                          focus-visible:outline-none focus-visible:bg-gray-50 dark:focus-visible:bg-gray-700/40">
                    <span class="shrink-0 inline-flex items-center justify-center size-9 rounded-lg
                                 <?php echo e($this->colorClasses[$item->color] ?? $this->colorClasses['slate']); ?>">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <?php echo $this->iconPaths[$item->icon] ?? $this->iconPaths['inbox']; ?>

                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isUnread): ?>
                                <span class="w-1.5 h-1.5 rounded-full bg-primary-500 shrink-0" aria-hidden="true"></span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <p class="text-sm <?php echo e($isUnread ? 'font-bold' : 'font-medium'); ?> text-gray-900 dark:text-white truncate">
                                <?php echo e($item->title); ?>

                            </p>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2 leading-relaxed">
                            <?php echo e($item->message); ?>

                        </p>
                        <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1 tabular-nums">
                            <?php echo e($this->timeAgo($item->created_at)); ?>

                        </p>
                    </div>
                    <svg class="size-3.5 shrink-0 text-gray-400 mt-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <div class="px-5 py-10 text-center">
                    <div class="inline-flex items-center justify-center size-12 rounded-2xl bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 mb-3">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">You're all caught up</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-[240px] mx-auto">
                        New business applications, deletions, and tenant activity will show up here.
                    </p>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <div class="border-t border-gray-200 dark:border-gray-700 px-3 py-2 bg-gray-50/60 dark:bg-gray-900/40">
            <a href="<?php echo e(route('superadmin.notifications.index')); ?>"
               wire:navigate
               @click="open = false"
               class="flex items-center justify-center gap-1.5 h-11 sm:h-10 rounded-lg text-xs font-semibold text-primary-600 dark:text-primary-400
                      hover:bg-primary-50 dark:hover:bg-primary-500/10
                      transition-all duration-200 active:scale-95 touch-manipulation
                      [-webkit-tap-highlight-color:transparent]
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <span>View all notifications</span>
                <svg class="size-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework\views/livewire/views/10bd7762.blade.php ENDPATH**/ ?>