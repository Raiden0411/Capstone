<div>
    
    <header class="fixed top-0 left-0 right-0 z-50 flex flex-wrap sm:justify-start sm:flex-nowrap w-full bg-[#062c1f] dark:bg-[#0b0f19] border-b border-emerald-700/50 dark:border-slate-700/50 text-sm py-4 transition-colors duration-300 h-[64px]"
            x-data="{ mobileOpen: false, userDropdownOpen: false }">
        <nav class="max-w-[85rem] w-full mx-auto px-4 flex flex-wrap basis-full items-center justify-between" aria-label="Global">
            
            <a class="flex flex-col group" href="<?php echo e(route('home')); ?>" wire:navigate>
                <span class="bg-clip-text text-transparent bg-gradient-to-r from-yellow-500 via-yellow-200 to-yellow-500 bg-[length:200%_auto] group-hover:bg-[100%_center] transition-all duration-500 font-bold text-xl">
                    Victorias
                </span>
                <span class="text-emerald-100/80 dark:text-slate-400 font-light text-xs tracking-[0.2em] uppercase block leading-none mt-1">
                    Tourism Management
                </span>
            </a>

            <div class="flex items-center gap-x-6 ms-auto">
                
                <div class="hidden lg:flex lg:items-center lg:gap-x-8">
                    <a class="relative text-xs font-bold tracking-[0.15em] uppercase transition-all duration-300 <?php echo e(request()->routeIs('home') ? 'text-yellow-400 after:absolute after:bottom-[-4px] after:left-0 after:h-[2px] after:w-full after:bg-yellow-400' : 'text-emerald-100/80 dark:text-slate-300 hover:text-yellow-400 dark:hover:text-white hover:translate-y-[-1px]'); ?>" href="<?php echo e(route('home')); ?>" wire:navigate>Home</a>
                    <a class="text-xs font-bold tracking-[0.15em] uppercase transition-all duration-300 <?php echo e(request()->routeIs('about') ? 'text-yellow-400 after:absolute after:bottom-[-4px] after:left-0 after:h-[2px] after:w-full after:bg-yellow-400' : 'text-emerald-100/80 dark:text-slate-300 hover:text-yellow-400 dark:hover:text-white hover:translate-y-[-1px]'); ?>" href="<?php echo e(route('about')); ?>" wire:navigate>About</a>
                    <a class="text-xs font-bold tracking-[0.15em] uppercase text-emerald-100/80 dark:text-slate-300 hover:text-yellow-400 dark:hover:text-white hover:translate-y-[-1px] transition-all duration-300" href="<?php echo e(route('explore.map')); ?>" wire:navigate>Explore</a>
                </div>

                <div class="flex items-center gap-x-3">
                    <a href="<?php echo e(route('explore.map')); ?>" wire:navigate class="hidden sm:inline-flex py-2 px-5 items-center text-xs font-bold tracking-[0.1em] uppercase rounded-full text-emerald-100 dark:text-slate-300 border border-emerald-700/50 dark:border-slate-600 hover:border-yellow-400 dark:hover:border-blue-400 hover:text-yellow-400 dark:hover:text-white transition-all duration-300">
                        Places
                    </a>

                    
                    <button type="button"
                            x-data="{ dark: localStorage.getItem('hs_theme') === 'dark' }"
                            x-init="
                                document.documentElement.classList.toggle('dark', dark);
                                $watch('dark', val => {
                                    localStorage.setItem('hs_theme', val ? 'dark' : 'light');
                                    document.documentElement.classList.toggle('dark', val);
                                });
                            "
                            @click="dark = !dark"
                            class="flex justify-center items-center size-9 rounded-lg text-emerald-100/80 dark:text-slate-400 hover:text-yellow-400 dark:hover:text-white hover:bg-emerald-900/40 dark:hover:bg-slate-800 focus:outline-none transition-colors"
                            aria-label="Toggle dark mode">
                        <svg x-show="dark" class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                        <svg x-show="!dark" class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                    </button>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->guest()): ?>
                        <a href="<?php echo e(route('login')); ?>" wire:navigate class="py-2 px-6 inline-flex items-center text-xs font-bold tracking-[0.1em] uppercase rounded-full bg-yellow-400 dark:bg-blue-600 border border-yellow-400 dark:border-blue-600 text-[#062c1f] dark:text-white hover:bg-transparent dark:hover:bg-blue-700 hover:text-yellow-400 dark:hover:text-white transition-all duration-300">
                            Sign in
                        </a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                        
                        <div class="relative" @click.outside="userDropdownOpen = false">
                            <button type="button" @click="userDropdownOpen = !userDropdownOpen"
                                    class="py-2 px-4 inline-flex items-center gap-x-2 text-xs font-bold tracking-[0.1em] uppercase rounded-full bg-yellow-400 dark:bg-blue-600 border border-yellow-400 dark:border-blue-600 text-[#062c1f] dark:text-white hover:bg-transparent dark:hover:bg-blue-700 hover:text-yellow-400 dark:hover:text-white transition-all duration-300">
                                <?php echo e(Auth::user()->name); ?>

                                <svg class="transition-transform duration-200" :class="userDropdownOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="16" height="16"><path d="m19 9-7 7-7-7"/></svg>
                            </button>

                            <div x-cloak x-show="userDropdownOpen" x-transition
                                 class="absolute right-0 mt-2 min-w-[200px] bg-[#062c1f] dark:bg-slate-800 border border-emerald-700/50 dark:border-slate-700 rounded-xl p-2 shadow-xl z-50">
                                <a class="flex items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-emerald-100/80 dark:text-slate-300 hover:bg-emerald-900/40 dark:hover:bg-slate-700 hover:text-yellow-400 dark:hover:text-white transition-colors" href="<?php echo e(route('explore.map')); ?>" wire:navigate>
                                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    Explore Map
                                </a>
                                
                                <a class="flex items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-emerald-100/80 dark:text-slate-300 hover:bg-emerald-900/40 dark:hover:bg-slate-700 hover:text-yellow-400 dark:hover:text-white transition-colors" href="<?php echo e(route('my-bookings')); ?>" wire:navigate>
                                    <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    My Bookings
                                </a>
                                <div class="border-t border-emerald-700/50 dark:border-slate-700 my-2"></div>
                                <form method="POST" action="<?php echo e(route('logout')); ?>">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="flex w-full items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-red-400 dark:text-red-400 hover:bg-red-900/10 dark:hover:bg-red-500/10 transition-colors">
                                        <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                                        Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <div class="lg:hidden">
                        <button type="button" @click="mobileOpen = !mobileOpen"
                                class="size-9 flex justify-center items-center text-white dark:text-slate-400 rounded-full bg-emerald-900/40 dark:bg-slate-800 border border-emerald-700/50 dark:border-slate-700"
                                aria-label="Toggle navigation">
                            <svg x-show="!mobileOpen" class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" x2="21" y1="6" y2="6"/><line x1="3" x2="21" y1="12" y2="12"/><line x1="3" x2="21" y1="18" y2="18"/></svg>
                            <svg x-show="mobileOpen" class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            
            <div x-cloak x-show="mobileOpen" x-transition
                 class="lg:hidden w-full mt-4 pt-4 border-t border-emerald-700/50 dark:border-slate-700/50 space-y-3">
                <a class="block text-xs font-bold tracking-[0.15em] uppercase <?php echo e(request()->routeIs('home') ? 'text-yellow-400 dark:text-white' : 'text-emerald-100/80 dark:text-slate-300'); ?>" href="<?php echo e(route('home')); ?>" wire:navigate>Home</a>
                <a class="block text-xs font-bold tracking-[0.15em] uppercase <?php echo e(request()->routeIs('about') ? 'text-yellow-400 dark:text-white' : 'text-emerald-100/80 dark:text-slate-300'); ?>" href="<?php echo e(route('about')); ?>" wire:navigate>About</a>
                <a class="block text-xs font-bold tracking-[0.15em] uppercase text-emerald-100/80 dark:text-slate-300" href="<?php echo e(route('explore.map')); ?>" wire:navigate>Explore</a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                    
                    <a class="block text-xs font-bold tracking-[0.15em] uppercase text-emerald-100/80 dark:text-slate-300" href="<?php echo e(route('my-bookings')); ?>" wire:navigate>My Bookings</a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </nav>
    </header>

    
    <div class="pt-[64px]"></div>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views/components/headers/public-header.blade.php ENDPATH**/ ?>