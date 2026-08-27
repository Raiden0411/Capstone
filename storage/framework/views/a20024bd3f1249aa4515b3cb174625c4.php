
<?php
    $tenant = auth()->user()?->tenant;
    $isEmployee = !auth()->user()->hasRole('admin') && auth()->user()->tenant_id;
    $dashboardRoute = $isEmployee ? route('tenant.employee.dashboard') : route('tenant.dashboard');

    $tenantLogoUrl = null;
    if ($tenant && $tenant->logo) {
        $fullPath = public_path('storage/' . $tenant->logo);
        $tenantLogoUrl = asset('storage/' . $tenant->logo);
        if (file_exists($fullPath)) {
            $tenantLogoUrl .= '?v=' . filemtime($fullPath);
        } else {
            $tenantLogoUrl .= '?v=' . time();
        }
    }
?>

<div
    x-data="{
        mobileOpen: false,
        minified: localStorage.getItem('tenant_sidebar_minified') === '1',
        toggleMinified() {
            this.minified = !this.minified;
            localStorage.setItem('tenant_sidebar_minified', this.minified ? '1' : '0');
            window.dispatchEvent(new CustomEvent('sidebar-minified-tenant', { detail: this.minified }));
        }
    }"
    @toggle-tenant-sidebar.window="mobileOpen = !mobileOpen"
    @keydown.escape.window="mobileOpen = false"
    :class="[
        minified ? 'lg:w-20' : 'lg:w-64',
        mobileOpen ? 'translate-x-0' : '-translate-x-full',
        'lg:translate-x-0'
    ]"
    class="
        fixed inset-y-0 start-0 z-50 h-full
        bg-white dark:bg-gray-900 border-e border-gray-200 dark:border-gray-700
        transition-all duration-300 transform
        lg:block
    "
    role="dialog" tabindex="-1" aria-label="Sidebar"
>
    
    <div x-show="mobileOpen" x-cloak
         class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[-1] lg:hidden"
         @click="mobileOpen = false" x-transition.opacity></div>

    <div class="relative flex flex-col h-full max-h-full">

        
        <div class="h-16 flex items-center justify-between px-4 border-b border-gray-200 dark:border-gray-700 shrink-0"
             :class="minified ? 'lg:justify-center lg:px-2' : ''">
            <div class="flex items-center gap-2 overflow-hidden">
                <a href="<?php echo e($dashboardRoute); ?>" wire:navigate
                   class="flex items-center gap-2 font-bold text-xl text-gray-900 dark:text-white whitespace-nowrap">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenantLogoUrl): ?>
                        <img src="<?php echo e($tenantLogoUrl); ?>"
                             alt="<?php echo e($tenant->name); ?>"
                             class="w-8 h-8 rounded-lg object-contain shrink-0"
                             loading="lazy">
                    <?php else: ?>
                        <span class="shrink-0 inline-flex items-center justify-center size-8 rounded-lg bg-[#376df1] text-white">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/>
                            </svg>
                        </span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <span x-show="!minified" x-cloak><?php echo e($tenant?->name ?? 'Victorias Tourism'); ?></span>
                </a>
            </div>

            
            <button type="button"
                    class="flex lg:hidden justify-center items-center size-8 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                    @click="mobileOpen = false"
                    aria-label="Close sidebar">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        
        <div class="flex-1 overflow-y-auto overflow-x-hidden [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-gray-300 dark:[&::-webkit-scrollbar-thumb]:bg-gray-700">
            <nav class="p-3 w-full flex flex-col">
                <ul class="flex flex-col space-y-1">

                    
                    <li>
                        <a href="<?php echo e($dashboardRoute); ?>" wire:navigate
                           class="group flex items-center gap-x-3.5 py-2.5 px-3 text-sm rounded-lg transition-colors
                                  <?php echo e(request()->routeIs('tenant.dashboard') || request()->routeIs('tenant.employee.dashboard') ? 'bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'); ?>">
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            <span x-show="!minified" x-cloak>Dashboard</span>
                        </a>
                    </li>

                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view analytics')): ?>
                    <li>
                        <a href="<?php echo e(route('tenant.analytics.index')); ?>" wire:navigate
                           class="group flex items-center gap-x-3.5 py-2.5 px-3 text-sm rounded-lg transition-colors
                                  <?php echo e(request()->routeIs('tenant.analytics.*') ? 'bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'); ?>">
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 16l4-4 4 4 5-5"/><path d="M7 8h1l4 4 4-4h1"/></svg>
                            <span x-show="!minified" x-cloak>Analytics</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (\Illuminate\Support\Facades\Blade::check('hasanyrole', 'admin|super-admin')): ?>
                    <li x-show="!minified" class="pt-4 pb-1" x-cloak>
                        <span class="block px-3 text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">Business Management</span>
                    </li>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view bookings')): ?>
                    <li>
                        <a href="<?php echo e(route('tenant.bookings.index')); ?>" wire:navigate
                           class="group flex items-center gap-x-3.5 py-2.5 px-3 text-sm rounded-lg transition-colors
                                  <?php echo e(request()->routeIs('tenant.bookings.*') && !request()->routeIs('tenant.bookings.history') ? 'bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'); ?>">
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span x-show="!minified" x-cloak>Active Bookings</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo e(route('tenant.bookings.history')); ?>" wire:navigate
                           class="group flex items-center gap-x-3.5 py-2.5 px-3 text-sm rounded-lg transition-colors
                                  <?php echo e(request()->routeIs('tenant.bookings.history') ? 'bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'); ?>">
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span x-show="!minified" x-cloak>Booking History</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    
                    <li>
                        <a href="<?php echo e(route('tenant.settings.overview')); ?>" wire:navigate
                           class="group flex items-center gap-x-3.5 py-2.5 px-3 text-sm rounded-lg transition-colors
                                  <?php echo e(request()->routeIs('tenant.settings.overview') ? 'bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'); ?>">
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                            </svg>
                            <span x-show="!minified" x-cloak>Tourist Spot Profile</span>
                        </a>
                    </li>

                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('manage events')): ?>
                    <li>
                        <a href="<?php echo e(route('tenant.events.index')); ?>" wire:navigate
                           class="group flex items-center gap-x-3.5 py-2.5 px-3 text-sm rounded-lg transition-colors
                                  <?php echo e(request()->routeIs('tenant.events.*') ? 'bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'); ?>">
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
                            <span x-show="!minified" x-cloak>Events</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view properties')): ?>
                    <li>
                        <a href="<?php echo e(route('tenant.properties.index')); ?>" wire:navigate
                           class="group flex items-center gap-x-3.5 py-2.5 px-3 text-sm rounded-lg transition-colors
                                  <?php echo e(request()->routeIs('tenant.properties.*') ? 'bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'); ?>">
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            <span x-show="!minified" x-cloak>Properties</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view properties')): ?>
                    <li>
                        <a href="<?php echo e(route('tenant.property-types.index')); ?>" wire:navigate
                           class="group flex items-center gap-x-3.5 py-2.5 px-3 text-sm rounded-lg transition-colors
                                  <?php echo e(request()->routeIs('tenant.property-types.*') ? 'bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'); ?>">
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 01.586 1.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
                            <span x-show="!minified" x-cloak>Property Types</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view services')): ?>
                    <li>
                        <a href="<?php echo e(route('tenant.services.index')); ?>" wire:navigate
                           class="group flex items-center gap-x-3.5 py-2.5 px-3 text-sm rounded-lg transition-colors
                                  <?php echo e(request()->routeIs('tenant.services.*') ? 'bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'); ?>">
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span x-show="!minified" x-cloak>Services</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view payments')): ?>
                    <li>
                        <a href="<?php echo e(route('tenant.payments.index')); ?>" wire:navigate
                           class="group flex items-center gap-x-3.5 py-2.5 px-3 text-sm rounded-lg transition-colors
                                  <?php echo e(request()->routeIs('tenant.payments.*') ? 'bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'); ?>">
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span x-show="!minified" x-cloak>Payments</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view employees')): ?>
                    <li>
                        <a href="<?php echo e(route('tenant.employees.index')); ?>" wire:navigate
                           class="group flex items-center gap-x-3.5 py-2.5 px-3 text-sm rounded-lg transition-colors
                                  <?php echo e(request()->routeIs('tenant.employees.*') ? 'bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'); ?>">
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            <span x-show="!minified" x-cloak>Employees</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (\Illuminate\Support\Facades\Blade::check('hasanyrole', 'admin|super-admin')): ?>
                    <li>
                        <a href="<?php echo e(route('tenant.roles.index')); ?>" wire:navigate
                           class="group flex items-center gap-x-3.5 py-2.5 px-3 text-sm rounded-lg transition-colors
                                  <?php echo e(request()->routeIs('tenant.roles.*') ? 'bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'); ?>">
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <span x-show="!minified" x-cloak>Roles</span>
                        </a>
                    </li>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (\Illuminate\Support\Facades\Blade::check('hasanyrole', 'admin|super-admin')): ?>
                    <li>
                        <a href="<?php echo e(route('tenant.settings.index')); ?>" wire:navigate
                           class="group flex items-center gap-x-3.5 py-2.5 px-3 text-sm rounded-lg transition-colors
                                  <?php echo e(request()->routeIs('tenant.settings.*') ? 'bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'); ?>">
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span x-show="!minified" x-cloak>Settings</span>
                        </a>
                    </li>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                </ul>
            </nav>
        </div>

        
        <button type="button"
                @click="toggleMinified()"
                class="hidden lg:flex absolute top-1/2 -right-3 z-50 size-7 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-300 shadow-sm hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white focus:outline-none transition-colors"
                :class="minified ? 'rotate-180' : ''"
                aria-label="Toggle sidebar">
            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/>
            </svg>
        </button>

    </div>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views/components/headers/tenant/sidebar.blade.php ENDPATH**/ ?>