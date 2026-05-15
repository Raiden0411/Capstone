<div>
    <header
        class="fixed top-0 left-0 right-0 z-50 w-full h-[68px] flex items-center bg-[#061f14] border-b border-white/[0.07] transition-colors duration-300"
        x-data="{ mobileOpen: false, userDropdownOpen: false, dark: localStorage.getItem('hs_theme') === 'dark' }"
        x-init="
            document.documentElement.classList.toggle('dark', dark);
            $watch('dark', val => {
                localStorage.setItem('hs_theme', val ? 'dark' : 'light');
                document.documentElement.classList.toggle('dark', val);
            });
        ">

        <nav class="max-w-[85rem] w-full mx-auto px-8 flex items-center justify-between">

            
            <a class="flex flex-col gap-[2px] group" href="<?php echo e(route('home')); ?>" wire:navigate>
                <span class="font-serif text-[22px] font-semibold tracking-[0.04em] text-amber-300 leading-none">
                    Victorias
                </span>
                <span class="text-[9px] font-light tracking-[0.22em] uppercase text-white/35 leading-none">
                    Tourism Management
                </span>
            </a>

            
            <div class="hidden lg:flex items-center gap-10">
                <a href="<?php echo e(route('home')); ?>" wire:navigate
                   class="relative text-[11px] font-medium tracking-[0.16em] uppercase transition-colors duration-200 <?php echo e(request()->routeIs('home') ? 'text-amber-300 after:absolute after:bottom-[-4px] after:left-0 after:right-0' : 'text-white/45 hover:text-white/85'); ?>">Home</a>
                <a href="<?php echo e(route('about')); ?>" wire:navigate
                   class="text-[11px] font-medium tracking-[0.16em] uppercase transition-colors duration-200 <?php echo e(request()->routeIs('about') ? 'text-amber-300' : 'text-white/45 hover:text-white/85'); ?>">About</a>
                <a href="<?php echo e(route('explore.map')); ?>" wire:navigate
                   class="text-[11px] font-medium tracking-[0.16em] uppercase text-white/45 hover:text-white/85 transition-colors duration-200">Explore</a>
            </div>

            
            <div class="flex items-center gap-2.5">

                <a href="<?php echo e(route('learnmore')); ?>" wire:navigate
                   class="hidden sm:inline-flex items-center text-[10px] font-medium tracking-[0.14em] uppercase py-[7px] px-[18px] rounded-full border border-white/15 text-white/55 hover:border-amber-300/50 hover:text-amber-300 transition-all duration-200">
                    Places
                </a>

                <div class="w-px h-5 bg-white/10 hidden sm:block"></div>

                
                

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->guest()): ?>
                    <div class="flex items-center gap-2">
                        
                        <a href="<?php echo e(route('register')); ?>" wire:navigate
                           class="hidden md:inline-flex text-[10px] font-medium tracking-[0.14em] uppercase py-2 px-5 rounded-full border border-white/15 text-white/55 hover:border-amber-300/50 hover:text-amber-300 transition-all duration-200">
                            Register
                        </a>

                        
                        <a href="<?php echo e(route('login')); ?>" wire:navigate
                           class="text-[10px] font-medium tracking-[0.14em] uppercase py-2 px-5 rounded-full bg-amber-300 border border-amber-300 text-[#061f14] hover:bg-transparent hover:text-amber-300 transition-all duration-200">
                            Sign in
                        </a>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                    
                    <div class="relative" @click.outside="userDropdownOpen = false">
                        <button type="button" @click="userDropdownOpen = !userDropdownOpen"
                                class="flex items-center gap-2 text-[10px] font-medium tracking-[0.14em] uppercase py-1.5 px-3 rounded-full bg-amber-300 border border-amber-300 text-[#061f14] hover:bg-transparent hover:text-amber-300 transition-all duration-200">
                            
                            <div x-data="{ 
                                avatarUrl: '<?php echo e(Auth::user()->avatar ? Storage::url(Auth::user()->avatar) : ''); ?>' 
                            }"
                            @avatar-updated.window="avatarUrl = $event.detail.url">
                                <img x-show="avatarUrl" :src="avatarUrl"
                                     class="w-5 h-5 rounded-full object-cover shrink-0">
                                <div x-show="!avatarUrl"
                                     class="w-5 h-5 rounded-full bg-[#061f14]/20 flex items-center justify-center text-[#061f14] text-[10px] font-bold shrink-0">
                                    <?php echo e(strtoupper(substr(Auth::user()->name, 0, 1))); ?>

                                </div>
                            </div>
                            <span class="hidden sm:inline max-w-[120px] truncate"><?php echo e(Auth::user()->name); ?></span>
                            <svg class="hidden sm:block w-3 h-3 transition-transform duration-200" :class="userDropdownOpen ? 'rotate-180' : ''" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m19 9-7 7-7-7"/></svg>
                        </button>

                        <div x-cloak x-show="userDropdownOpen" x-transition
                             class="absolute right-0 mt-2 min-w-[200px] bg-[#061f14] border border-white/10 rounded-xl p-2 shadow-2xl z-50">
                            
                            <div class="px-4 py-3 border-b border-white/[0.08]">
                                <div class="flex items-center gap-3">
                                    <div x-data="{ 
                                        avatarUrl: '<?php echo e(Auth::user()->avatar ? Storage::url(Auth::user()->avatar) : ''); ?>' 
                                    }"
                                    @avatar-updated.window="avatarUrl = $event.detail.url">
                                        <img x-show="avatarUrl" :src="avatarUrl"
                                             class="w-9 h-9 rounded-full object-cover shrink-0">
                                        <div x-show="!avatarUrl"
                                             class="w-9 h-9 rounded-full bg-amber-300 flex items-center justify-center text-[#061f14] font-bold text-sm shrink-0">
                                            <?php echo e(strtoupper(substr(Auth::user()->name, 0, 1))); ?>

                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-white truncate"><?php echo e(Auth::user()->name); ?></p>
                                        <p class="text-xs text-white/50 truncate"><?php echo e(Auth::user()->email); ?></p>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::user()->hasRole('tourist')): ?>
                                            <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-400/20 text-blue-300">
                                                Tourist
                                            </span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            
                            <div class="p-1.5 space-y-0.5">
                                <a class="flex items-center gap-3 py-2 px-3 rounded-lg text-sm text-white/55 hover:bg-white/[0.05] hover:text-amber-300 transition-colors"
                                   href="<?php echo e(route('explore.map')); ?>" wire:navigate>
                                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    Explore Map
                                </a>
                                <a class="flex items-center gap-3 py-2 px-3 rounded-lg text-sm text-white/55 hover:bg-white/[0.05] hover:text-amber-300 transition-colors"
                                   href="<?php echo e(route('my-bookings')); ?>" wire:navigate>
                                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    My Bookings
                                </a>
                                <a class="flex items-center gap-3 py-2 px-3 rounded-lg text-sm text-white/55 hover:bg-white/[0.05] hover:text-amber-300 transition-colors"
                                   href="<?php echo e(route('profile')); ?>" wire:navigate>
                                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    My Profile
                                </a>
                                <div class="border-t border-white/[0.08] my-2"></div>
                                <form method="POST" action="<?php echo e(route('logout')); ?>">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="flex w-full items-center gap-3 py-2 px-3 rounded-lg text-sm text-red-400 hover:bg-red-400/[0.08] transition-colors">
                                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                                        Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <div class="lg:hidden">
                    <button type="button" @click="mobileOpen = !mobileOpen"
                            class="w-[34px] h-[34px] rounded-full border border-white/10 bg-white/[0.04] flex items-center justify-center text-white/55 transition-colors"
                            aria-label="Toggle navigation">
                        <svg x-show="!mobileOpen" class="w-[14px] h-[14px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <line x1="3" x2="21" y1="6" y2="6"/><line x1="3" x2="21" y1="12" y2="12"/><line x1="3" x2="21" y1="18" y2="18"/>
                        </svg>
                        <svg x-show="mobileOpen" class="w-[14px] h-[14px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        </nav>

        
        <div x-cloak x-show="mobileOpen" x-transition
             class="lg:hidden absolute top-[68px] left-0 right-0 bg-[#0a2b1c] border-b border-white/[0.07] px-8 py-5 space-y-4 z-40">
            <a class="block text-[11px] font-medium tracking-[0.16em] uppercase <?php echo e(request()->routeIs('home') ? 'text-amber-300' : 'text-white/45'); ?>" href="<?php echo e(route('home')); ?>" wire:navigate>Home</a>
            <a class="block text-[11px] font-medium tracking-[0.16em] uppercase <?php echo e(request()->routeIs('about') ? 'text-amber-300' : 'text-white/45'); ?>" href="<?php echo e(route('about')); ?>" wire:navigate>About</a>
            <a class="block text-[11px] font-medium tracking-[0.16em] uppercase text-white/45" href="<?php echo e(route('explore.map')); ?>" wire:navigate>Explore</a>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                <a class="block text-[11px] font-medium tracking-[0.16em] uppercase text-white/45" href="<?php echo e(route('my-bookings')); ?>" wire:navigate>My Bookings</a>
                <a class="block text-[11px] font-medium tracking-[0.16em] uppercase text-white/45" href="<?php echo e(route('profile')); ?>" wire:navigate>My Profile</a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <div class="border-t border-white/[0.08] pt-4 flex gap-3">
                <a href="<?php echo e(route('learnmore')); ?>" wire:navigate
                   class="flex-1 text-center text-[10px] font-medium tracking-[0.14em] uppercase py-2 rounded-full border border-white/15 text-white/55">Places</a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->guest()): ?>
                    <a href="<?php echo e(route('register')); ?>" wire:navigate
                       class="flex-1 text-center text-[10px] font-medium tracking-[0.14em] uppercase py-2 rounded-full border border-white/15 text-white/55">Register</a>
                    <a href="<?php echo e(route('login')); ?>" wire:navigate
                       class="flex-1 text-center text-[10px] font-medium tracking-[0.14em] uppercase py-2 rounded-full bg-amber-300 text-[#061f14]">Sign in</a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

    </header>
    
</div><?php /**PATH C:\laragon\www\Capstone\resources\views/components/headers/public-header.blade.php ENDPATH**/ ?>