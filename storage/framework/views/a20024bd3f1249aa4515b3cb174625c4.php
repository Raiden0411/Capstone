
<div :class="minified ? 'w-13' : 'w-65'"
     id="hs-application-sidebar"
     class="hs-overlay [--body-scroll:true] lg:[--overlay-backdrop:false] [--is-layout-affect:true] [--auto-close:lg]
            hs-overlay-open:translate-x-0
            -translate-x-full transition-all duration-300 transform
            h-full
            hidden lg:block
            fixed inset-y-0 start-0 z-60
            lg:translate-x-0
            bg-black/60 backdrop-blur-xl border-e border-white/10"
     role="dialog" tabindex="-1" aria-label="Sidebar">
    <div class="relative flex flex-col h-full max-h-full">
        
        <!-- Header with dynamic business profile -->
        <div class="py-2.5 px-4 flex justify-between items-center gap-x-2">
            <div class="-ms-2 flex items-center gap-x-1" x-show="!minified">
                <?php
                    $tenant = auth()->user()?->tenant;
                    $isEmployee = !auth()->user()->hasRole('admin') && auth()->user()->tenant_id;
                    $dashboardRoute = $isEmployee ? route('tenant.employee.dashboard') : route('tenant.dashboard');
                ?>
                <a class="flex items-center gap-2 font-bold text-xl text-white"
                   href="<?php echo e($dashboardRoute); ?>" wire:navigate aria-label="Brand">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant && $tenant->logo): ?>
                        <img src="<?php echo e(Storage::url($tenant->logo)); ?>"
                             alt="<?php echo e($tenant->name); ?>"
                             class="w-7 h-7 rounded-full object-cover shrink-0"
                             loading="lazy">
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <span><?php echo e($tenant?->name ?? 'Victorias Tourism'); ?></span>
                </a>
            </div>

            <!-- Desktop Minify Toggle -->
            <button type="button" @click="minified = !minified"
                    class="hidden lg:flex justify-center items-center flex-none gap-x-3 size-9 text-sm text-white/50 rounded-lg hover:bg-white/10 focus:outline-none"
                    aria-label="Toggle sidebar">
                <svg x-show="!minified" class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M15 3v18"/><path d="m10 15-3-3 3-3"/></svg>
                <svg x-show="minified" class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M15 3v18"/><path d="m8 9 3 3-3 3"/></svg>
            </button>

            <!-- Mobile Close Button -->
            <button type="button"
                    class="flex lg:hidden justify-center items-center size-8 rounded-full bg-white/10 text-white/50 hover:text-white hover:bg-white/20"
                    data-hs-overlay="#hs-application-sidebar"
                    aria-label="Close sidebar">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <!-- Navigation -->
        <div class="h-full overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-thumb]:rounded-none [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-white/10">
            <nav class="p-3 w-full flex flex-col flex-wrap">
                <ul class="flex flex-col space-y-1">
                    
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.dashboard') || request()->routeIs('tenant.employee.dashboard') ? 'bg-brand-500/20 text-brand-300' : 'text-white/60 hover:bg-white/10 hover:text-white'); ?>" href="<?php echo e($dashboardRoute); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            <span x-show="!minified">Dashboard</span>
                        </a>
                    </li>

                    
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view analytics')): ?>
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.analytics.*') ? 'bg-brand-500/20 text-brand-300' : 'text-white/60 hover:bg-white/10 hover:text-white'); ?>" href="<?php echo e(route('tenant.analytics.index')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 16l4-4 4 4 5-5"/><path d="M7 8h1l4 4 4-4h1"/></svg>
                            <span x-show="!minified">Analytics</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (\Illuminate\Support\Facades\Blade::check('hasanyrole', 'admin|super-admin')): ?>
                    <li class="pt-3" x-show="!minified">
                        <span class="px-3 text-[10px] font-bold text-white/40 uppercase tracking-widest">Business Management</span>
                    </li>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view bookings')): ?>
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.bookings.*') ? 'bg-brand-500/20 text-brand-300' : 'text-white/60 hover:bg-white/10 hover:text-white'); ?>" href="<?php echo e(route('tenant.bookings.index')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span x-show="!minified">Active Bookings</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view bookings')): ?>
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.bookings.history') ? 'bg-brand-500/20 text-brand-300' : 'text-white/60 hover:bg-white/10 hover:text-white'); ?>" href="<?php echo e(route('tenant.bookings.history')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span x-show="!minified">Booking History</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.settings.overview') ? 'bg-brand-500/20 text-brand-300' : 'text-white/60 hover:bg-white/10 hover:text-white'); ?>"
                           href="<?php echo e(route('tenant.settings.overview')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                            </svg>
                            <span x-show="!minified">Tourist Spot Profile</span>
                        </a>
                    </li>

                    
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view properties')): ?>
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.properties.*') ? 'bg-brand-500/20 text-brand-300' : 'text-white/60 hover:bg-white/10 hover:text-white'); ?>" href="<?php echo e(route('tenant.properties.index')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            <span x-show="!minified">Properties</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view properties')): ?>
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.property-types.*') ? 'bg-brand-500/20 text-brand-300' : 'text-white/60 hover:bg-white/10 hover:text-white'); ?>" href="<?php echo e(route('tenant.property-types.index')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 01.586 1.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
                            <span x-show="!minified">Property Types</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view services')): ?>
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.services.*') ? 'bg-brand-500/20 text-brand-300' : 'text-white/60 hover:bg-white/10 hover:text-white'); ?>" href="<?php echo e(route('tenant.services.index')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span x-show="!minified">Services</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view payments')): ?>
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.payments.*') ? 'bg-brand-500/20 text-brand-300' : 'text-white/60 hover:bg-white/10 hover:text-white'); ?>" href="<?php echo e(route('tenant.payments.index')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span x-show="!minified">Payments</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view employees')): ?>
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.employees.*') ? 'bg-brand-500/20 text-brand-300' : 'text-white/60 hover:bg-white/10 hover:text-white'); ?>" href="<?php echo e(route('tenant.employees.index')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            <span x-show="!minified">Employees</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (\Illuminate\Support\Facades\Blade::check('hasanyrole', 'admin|super-admin')): ?>
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.roles.*') ? 'bg-brand-500/20 text-brand-300' : 'text-white/60 hover:bg-white/10 hover:text-white'); ?>" href="<?php echo e(route('tenant.roles.index')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <span x-show="!minified">Roles</span>
                        </a>
                    </li>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (\Illuminate\Support\Facades\Blade::check('hasanyrole', 'admin|super-admin')): ?>
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.settings.*') ? 'bg-brand-500/20 text-brand-300' : 'text-white/60 hover:bg-white/10 hover:text-white'); ?>" href="<?php echo e(route('tenant.settings.index')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span x-show="!minified">Settings</span>
                        </a>
                    </li>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                </ul>
            </nav>
        </div>

        <!-- Footer -->
        <div class="border-t border-white/10 p-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-brand-600 text-white flex items-center justify-center font-bold text-xs shrink-0">
                        <?php echo e(substr(auth()->user()->name ?? 'U', 0, 1)); ?>

                    </div>
                    <div class="flex-1 min-w-0" x-show="!minified">
                        <p class="text-sm font-medium text-white truncate"><?php echo e(auth()->user()->name); ?></p>
                        <p class="text-xs text-white/50 truncate"><?php echo e(auth()->user()->email); ?></p>
                    </div>
                    <form method="POST" action="<?php echo e(route('logout')); ?>" class="shrink-0" x-show="!minified">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="p-1.5 text-white/40 hover:text-red-400 rounded-lg hover:bg-white/10 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <a href="<?php echo e(route('login')); ?>" class="flex items-center justify-center gap-2 w-full py-2 px-3 text-sm font-medium rounded-lg bg-brand-600 text-white hover:bg-brand-500">Sign in</a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views/components/headers/tenant/sidebar.blade.php ENDPATH**/ ?>