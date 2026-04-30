
<div x-data="{ mobileOpen: false, secondaryOpen: false }">
    <header class="flex flex-wrap md:justify-start md:flex-nowrap z-50 w-full bg-white border-b border-slate-200 shadow-sm">
        <nav class="relative max-w-[85rem] w-full mx-auto md:flex md:items-center md:justify-between md:gap-3 py-3 px-4 sm:px-6 lg:px-8">
            
            <div class="flex justify-between items-center gap-x-1">
                <a class="flex-none font-bold text-2xl text-slate-800 focus:outline-hidden focus:opacity-80 flex items-center gap-2" href="<?php echo e(route('tenant.dashboard')); ?>" wire:navigate aria-label="Brand">
                    <svg class="w-7 h-7 text-lime-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    <span>System<span class="text-lime-600">Tenant</span></span>
                </a>

                
                <button @click="mobileOpen = !mobileOpen" type="button" class="md:hidden relative size-9 flex justify-center items-center font-medium text-sm rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100">
                    <svg x-show="!mobileOpen" class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg x-show="mobileOpen" class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <span class="sr-only">Toggle navigation</span>
                </button>
            </div>

            
            <div class="hidden md:flex md:items-center md:gap-1 md:ml-6 grow">
                <a class="py-2 px-3 flex items-center text-sm font-medium rounded-lg hover:bg-slate-100 <?php echo e(request()->routeIs('tenant.dashboard') ? 'bg-lime-50 text-lime-700' : 'text-slate-600'); ?>" href="<?php echo e(route('tenant.dashboard')); ?>" wire:navigate>
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Dashboard
                </a>
                <a class="py-2 px-3 flex items-center text-sm font-medium rounded-lg hover:bg-slate-100 <?php echo e(request()->routeIs('tenant.properties.*') ? 'bg-lime-50 text-lime-700' : 'text-slate-600'); ?>" href="<?php echo e(route('tenant.properties.index')); ?>" wire:navigate>
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    Properties
                </a>
                <a class="py-2 px-3 flex items-center text-sm font-medium rounded-lg hover:bg-slate-100 <?php echo e(request()->routeIs('tenant.bookings.*') ? 'bg-lime-50 text-lime-700' : 'text-slate-600'); ?>" href="<?php echo e(route('tenant.bookings.index')); ?>" wire:navigate>
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Bookings
                </a>
                <a class="py-2 px-3 flex items-center text-sm font-medium rounded-lg hover:bg-slate-100 <?php echo e(request()->routeIs('tenant.services.*') ? 'bg-lime-50 text-lime-700' : 'text-slate-600'); ?>" href="<?php echo e(route('tenant.services.index')); ?>" wire:navigate>
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    Services
                </a>
                
                <a class="py-2 px-3 flex items-center text-sm font-medium rounded-lg hover:bg-slate-100 <?php echo e(request()->routeIs('tenant.analytics.*') ? 'bg-lime-50 text-lime-700' : 'text-slate-600'); ?>" href="<?php echo e(route('tenant.analytics.index')); ?>" wire:navigate>
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Analytics
                </a>
            </div>

            
            <div class="hidden md:block mx-2">
                <div class="w-px h-6 bg-slate-200"></div>
            </div>

            
            <div class="hidden md:block relative" x-data="{ open: false }">
                <button @click="open = !open" type="button" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg bg-white text-slate-700 shadow-sm align-middle hover:bg-slate-50 border border-slate-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <span>More</span>
                    <svg :class="{ 'rotate-180': open }" class="size-4 text-slate-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div x-show="open" @click.away="open = false" x-transition.opacity.duration.200ms class="absolute right-0 mt-2 min-w-48 bg-white shadow-md rounded-lg p-1 border border-slate-200 z-50">
                    
                    <a class="flex items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-slate-700 hover:bg-slate-100" href="<?php echo e(route('tenant.analytics.index')); ?>" wire:navigate>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        Analytics
                    </a>
                    <a class="flex items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-slate-700 hover:bg-slate-100" href="<?php echo e(route('tenant.employees.index')); ?>" wire:navigate>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Employees
                    </a>
                    <a class="flex items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-slate-700 hover:bg-slate-100" href="<?php echo e(route('tenant.roles.index')); ?>" wire:navigate>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Roles
                    </a>
                    <a class="flex items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-slate-700 hover:bg-slate-100" href="<?php echo e(route('tenant.property-types.index')); ?>" wire:navigate>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 01.586 1.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
                        Property Types
                    </a>
                    <a class="flex items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-slate-700 hover:bg-slate-100" href="<?php echo e(route('tenant.payments.index')); ?>" wire:navigate>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Payments
                    </a>
                    <a class="flex items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-slate-700 hover:bg-slate-100" href="<?php echo e(route('tenant.transactions.index')); ?>" wire:navigate>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        Transactions
                    </a>
                    <div class="border-t border-slate-100 my-1"></div>
                    <a class="flex items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-slate-700 hover:bg-slate-100" href="<?php echo e(route('tenant.settings.index')); ?>" wire:navigate>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Settings
                    </a>
                </div>
            </div>

            
            <div class="hidden md:flex md:items-center md:gap-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" type="button" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg bg-white text-slate-700 shadow-sm align-middle hover:bg-slate-50 border border-slate-200">
                            <div class="w-6 h-6 rounded-full bg-lime-600 text-white flex items-center justify-center font-bold text-xs">
                                <?php echo e(substr(auth()->user()->name ?? 'T', 0, 1)); ?>

                            </div>
                            <span class="hidden sm:inline max-w-[120px] truncate"><?php echo e(auth()->user()->name ?? 'Tenant Admin'); ?></span>
                            <svg :class="{ 'rotate-180': open }" class="size-4 text-slate-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        <div x-show="open" @click.away="open = false" x-transition.opacity.duration.200ms class="absolute right-0 mt-2 min-w-60 bg-white shadow-md rounded-lg p-1 border border-slate-200 z-50">
                            <div class="py-2 px-3 border-b border-slate-200">
                                <p class="text-sm font-medium text-slate-800 truncate"><?php echo e(auth()->user()->name); ?></p>
                                <p class="text-xs text-slate-500 truncate"><?php echo e(auth()->user()->email); ?></p>
                                <p class="text-xs text-slate-500 mt-1"><?php echo e(auth()->user()->tenant->name ?? ''); ?></p>
                            </div>
                            <form method="POST" action="<?php echo e(route('logout')); ?>">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="w-full flex items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-red-600 hover:bg-red-50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?php echo e(route('login')); ?>" class="py-2 px-4 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg bg-lime-600 text-white hover:bg-lime-700">Sign in</a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </nav>
    </header>

    
    <nav class="bg-slate-50 border-b border-slate-200">
        <div class="max-w-[85rem] w-full mx-auto sm:flex sm:flex-row sm:justify-between sm:items-center sm:gap-x-3 py-2 px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center gap-x-3">
                <div class="grow">
                    <span class="font-semibold whitespace-nowrap text-slate-600 text-xs uppercase tracking-wider">Quick Actions</span>
                </div>
                <button @click="secondaryOpen = !secondaryOpen" type="button" class="sm:hidden py-1.5 px-2 inline-flex items-center font-medium text-xs rounded-md bg-white border border-slate-200 text-slate-600 shadow-sm hover:bg-slate-50">
                    Menu
                    <svg :class="{ 'rotate-180': secondaryOpen }" class="size-4 ms-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
            </div>

            <div x-show="secondaryOpen" x-collapse x-cloak class="sm:hidden">
                <div class="py-2 flex flex-col gap-y-2">
                    <a class="text-sm text-slate-600 hover:text-lime-600" href="<?php echo e(route('tenant.bookings.create')); ?>" wire:navigate>+ New Booking</a>
                    <a class="text-sm text-slate-600 hover:text-lime-600" href="<?php echo e(route('tenant.properties.create')); ?>" wire:navigate>+ New Property</a>
                    <a class="text-sm text-slate-600 hover:text-lime-600" href="<?php echo e(route('tenant.customers.create')); ?>" wire:navigate>+ New Reservation</a>
                </div>
            </div>

            <div class="hidden sm:flex sm:flex-row sm:justify-end gap-x-6">
                <a class="text-sm font-medium text-slate-600 hover:text-lime-600 flex items-center gap-1" href="<?php echo e(route('tenant.bookings.create')); ?>" wire:navigate>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Booking
                </a>
                <a class="text-sm font-medium text-slate-600 hover:text-lime-600 flex items-center gap-1" href="<?php echo e(route('tenant.properties.create')); ?>" wire:navigate>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Property
                </a>
                <a class="text-sm font-medium text-slate-600 hover:text-lime-600 flex items-center gap-1" href="<?php echo e(route('tenant.customers.create')); ?>" wire:navigate>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Reservation
                </a>
            </div>
        </div>
    </nav>

    
    <div x-show="mobileOpen" x-collapse x-cloak class="md:hidden bg-white border-b border-slate-200">
        <div class="px-4 py-3 space-y-1">
            <a class="block py-2 px-3 rounded-lg <?php echo e(request()->routeIs('tenant.dashboard') ? 'bg-lime-50 text-lime-700' : 'text-slate-600'); ?>" href="<?php echo e(route('tenant.dashboard')); ?>" wire:navigate>Dashboard</a>
            <a class="block py-2 px-3 rounded-lg <?php echo e(request()->routeIs('tenant.properties.*') ? 'bg-lime-50 text-lime-700' : 'text-slate-600'); ?>" href="<?php echo e(route('tenant.properties.index')); ?>" wire:navigate>Properties</a>
            <a class="block py-2 px-3 rounded-lg <?php echo e(request()->routeIs('tenant.bookings.*') ? 'bg-lime-50 text-lime-700' : 'text-slate-600'); ?>" href="<?php echo e(route('tenant.bookings.index')); ?>" wire:navigate>Bookings</a>
            <a class="block py-2 px-3 rounded-lg <?php echo e(request()->routeIs('tenant.services.*') ? 'bg-lime-50 text-lime-700' : 'text-slate-600'); ?>" href="<?php echo e(route('tenant.services.index')); ?>" wire:navigate>Services</a>
            
            <a class="block py-2 px-3 rounded-lg <?php echo e(request()->routeIs('tenant.analytics.*') ? 'bg-lime-50 text-lime-700' : 'text-slate-600'); ?>" href="<?php echo e(route('tenant.analytics.index')); ?>" wire:navigate>Analytics</a>
            <a class="block py-2 px-3 rounded-lg <?php echo e(request()->routeIs('tenant.employees.*') ? 'bg-lime-50 text-lime-700' : 'text-slate-600'); ?>" href="<?php echo e(route('tenant.employees.index')); ?>" wire:navigate>Employees</a>
            <a class="block py-2 px-3 rounded-lg <?php echo e(request()->routeIs('tenant.roles.*') ? 'bg-lime-50 text-lime-700' : 'text-slate-600'); ?>" href="<?php echo e(route('tenant.roles.index')); ?>" wire:navigate>Roles</a>
            <a class="block py-2 px-3 rounded-lg <?php echo e(request()->routeIs('tenant.property-types.*') ? 'bg-lime-50 text-lime-700' : 'text-slate-600'); ?>" href="<?php echo e(route('tenant.property-types.index')); ?>" wire:navigate>Property Types</a>
            <a class="block py-2 px-3 rounded-lg <?php echo e(request()->routeIs('tenant.payments.*') ? 'bg-lime-50 text-lime-700' : 'text-slate-600'); ?>" href="<?php echo e(route('tenant.payments.index')); ?>" wire:navigate>Payments</a>
            <a class="block py-2 px-3 rounded-lg <?php echo e(request()->routeIs('tenant.transactions.*') ? 'bg-lime-50 text-lime-700' : 'text-slate-600'); ?>" href="<?php echo e(route('tenant.transactions.index')); ?>" wire:navigate>Transactions</a>
            <a class="block py-2 px-3 rounded-lg <?php echo e(request()->routeIs('tenant.settings.*') ? 'bg-lime-50 text-lime-700' : 'text-slate-600'); ?>" href="<?php echo e(route('tenant.settings.index')); ?>" wire:navigate>Settings</a>
            <div class="border-t border-slate-200 my-2"></div>
            <div class="px-3 py-2">
                <p class="text-sm font-medium text-slate-800"><?php echo e(auth()->user()->name); ?></p>
                <p class="text-xs text-slate-500"><?php echo e(auth()->user()->email); ?></p>
            </div>
            <form method="POST" action="<?php echo e(route('logout')); ?>">
                <?php echo csrf_field(); ?>
                <button type="submit" class="w-full py-2 px-3 text-left text-sm text-red-600 hover:bg-red-50 rounded-lg">Sign out</button>
            </form>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views/components/headers/tenant/tenant-header.blade.php ENDPATH**/ ?>