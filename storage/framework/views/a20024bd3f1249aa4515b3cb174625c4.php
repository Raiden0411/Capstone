<div :class="minified ? 'w-13' : 'w-65'"
     id="hs-application-sidebar-tenant"
     class="hs-overlay [--body-scroll:true] lg:[--overlay-backdrop:false] [--is-layout-affect:true] [--auto-close:lg]
            hs-overlay-open:translate-x-0
            -translate-x-full transition-all duration-300 transform
            h-full
            hidden lg:block
            fixed inset-y-0 start-0 z-60
            lg:translate-x-0
            bg-white dark:bg-[#0b0f19] border-e border-gray-200 dark:border-slate-700/50"
     role="dialog" tabindex="-1" aria-label="Tenant Sidebar">

    <div class="relative flex flex-col h-full max-h-full">
        
        <div class="py-2.5 px-4 flex justify-between items-center gap-x-2">
            <div class="-ms-2 flex items-center gap-x-1" x-show="!minified">
                <a class="flex items-center gap-2 font-bold text-xl text-gray-900 dark:text-white" href="<?php echo e(route('tenant.dashboard')); ?>" wire:navigate aria-label="Tenant Dashboard">
                    <svg class="w-7 h-7 text-blue-600 dark:text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span><?php echo e(auth()->user()->tenant->name ?? 'Business'); ?></span>
                </a>
            </div>
            
            <button type="button" @click="minified = !minified"
                    class="hidden lg:flex justify-center items-center flex-none gap-x-3 size-9 text-sm text-gray-500 dark:text-slate-400 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 focus:outline-none"
                    aria-label="Toggle sidebar">
                <svg x-show="!minified" class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M15 3v18"/><path d="m10 15-3-3 3-3"/></svg>
                <svg x-show="minified" class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M15 3v18"/><path d="m8 9 3 3-3 3"/></svg>
            </button>

            
            <button type="button"
                    class="flex lg:hidden justify-center items-center size-8 rounded-full bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-white"
                    data-hs-overlay="#hs-application-sidebar-tenant"
                    aria-label="Close sidebar">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        
        <div class="h-full overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-thumb]:rounded-none [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-gray-200 dark:[&::-webkit-scrollbar-thumb]:bg-slate-700">
            <nav class="p-3 w-full flex flex-col flex-wrap">
                <ul class="flex flex-col space-y-1">
                    
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.dashboard') ? 'bg-blue-50 dark:bg-blue-600/20 text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'); ?>" href="<?php echo e(route('tenant.dashboard')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            <span x-show="!minified">Dashboard</span>
                        </a>
                    </li>

                    
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.bookings.*') ? 'bg-blue-50 dark:bg-blue-600/20 text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'); ?>" href="<?php echo e(route('tenant.bookings.index')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span x-show="!minified">Bookings</span>
                        </a>
                    </li>

                    
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.bookings.history') ? 'bg-blue-50 dark:bg-blue-600/20 text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'); ?>" href="<?php echo e(route('tenant.bookings.history')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span x-show="!minified">History</span>
                        </a>
                    </li>

                    
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.customers.*') ? 'bg-blue-50 dark:bg-blue-600/20 text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'); ?>" href="<?php echo e(route('tenant.customers.create')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <span x-show="!minified">New Customer</span>
                        </a>
                    </li>

                    
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.properties.*') ? 'bg-blue-50 dark:bg-blue-600/20 text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'); ?>" href="<?php echo e(route('tenant.properties.index')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            <span x-show="!minified">Properties</span>
                        </a>
                    </li>

                    
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.property-types.*') ? 'bg-blue-50 dark:bg-blue-600/20 text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'); ?>" href="<?php echo e(route('tenant.property-types.index')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 01.586 1.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
                            <span x-show="!minified">Property Types</span>
                        </a>
                    </li>

                    
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.services.*') ? 'bg-blue-50 dark:bg-blue-600/20 text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'); ?>" href="<?php echo e(route('tenant.services.index')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path d="M9 12l2 2 4-4"/></svg>
                            <span x-show="!minified">Services</span>
                        </a>
                    </li>

                    
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.payments.*') ? 'bg-blue-50 dark:bg-blue-600/20 text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'); ?>" href="<?php echo e(route('tenant.payments.index')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span x-show="!minified">Payments</span>
                        </a>
                    </li>

                    
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.employees.*') ? 'bg-blue-50 dark:bg-blue-600/20 text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'); ?>" href="<?php echo e(route('tenant.employees.index')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            <span x-show="!minified">Employees</span>
                        </a>
                    </li>

                    
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.roles.*') ? 'bg-blue-50 dark:bg-blue-600/20 text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'); ?>" href="<?php echo e(route('tenant.roles.index')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <span x-show="!minified">Roles</span>
                        </a>
                    </li>

                    
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.analytics.*') ? 'bg-blue-50 dark:bg-blue-600/20 text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'); ?>" href="<?php echo e(route('tenant.analytics.index')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 16l4-4 4 4 5-5"/><path d="M7 8h1l4 4 4-4h1"/></svg>
                            <span x-show="!minified">Analytics</span>
                        </a>
                    </li>

                    
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.settings.overview') ? 'bg-blue-50 dark:bg-blue-600/20 text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'); ?>" href="<?php echo e(route('tenant.settings.overview')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span x-show="!minified">Tourist Spot</span>
                        </a>
                    </li>

                    
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors <?php echo e(request()->routeIs('tenant.settings.*') ? 'bg-blue-50 dark:bg-blue-600/20 text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'); ?>" href="<?php echo e(route('tenant.settings.index')); ?>" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span x-show="!minified">Settings</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>

        
        <div class="border-t border-gray-200 dark:border-slate-700/50 p-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-xs shrink-0">
                        <?php echo e(substr(auth()->user()->name ?? 'U', 0, 1)); ?>

                    </div>
                    <div class="flex-1 min-w-0" x-show="!minified">
                        <p class="text-sm font-medium text-gray-800 dark:text-slate-300 truncate"><?php echo e(auth()->user()->name); ?></p>
                        <p class="text-xs text-gray-500 dark:text-slate-500 truncate"><?php echo e(auth()->user()->email); ?></p>
                    </div>
                    <form method="POST" action="<?php echo e(route('logout')); ?>" class="shrink-0" x-show="!minified">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="p-1.5 text-gray-400 dark:text-slate-500 hover:text-red-500 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <a href="<?php echo e(route('login')); ?>" class="flex items-center justify-center gap-2 w-full py-2 px-3 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700">Sign in</a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views/components/headers/tenant/sidebar.blade.php ENDPATH**/ ?>