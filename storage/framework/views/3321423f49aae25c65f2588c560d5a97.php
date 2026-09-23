
<?php
    use App\Models\User;

    // Cached site settings.
    $logoPath = \App\Models\SiteSetting::getValue('site_logo');
    $siteName = \App\Models\SiteSetting::getValue('site_name', 'Victorias City Tourism');

    /*
     * Logo URL — only build a URL when the file is actually on disk.
     *
     * WHY THE `file_exists` GUARD:
     *   The previous implementation fell back to `time()` when the
     *   file was missing. That produced a unique URL on every request,
     *   defeating browser caching for a resource that would 404 anyway.
     *   Returning null (so the initial-letter fallback renders instead)
     *   is both faster and more honest — no broken-image icon, no
     *   repeated 404s.
     *
     * WHY THE `?v=` CACHE-BUSTER:
     *   When the admin swaps the logo via the homepage editor, the
     *   path stays the same. Without a version query, browsers keep
     *   serving the old image. filemtime() gives a stable fingerprint
     *   that only changes when the file itself changes.
     */
    $logoUrl = null;
    if ($logoPath) {
        $fullPath = public_path('storage/' . $logoPath);
        if (file_exists($fullPath)) {
            $logoUrl = asset('storage/' . $logoPath) . '?v=' . filemtime($fullPath);
        }
    }

    $authUser = Auth::user();

    $avatarUrl = null;
    if ($authUser?->avatar) {
        $avatarFullPath = public_path('storage/' . $authUser->avatar);
        if (file_exists($avatarFullPath)) {
            $avatarUrl = asset('storage/' . $authUser->avatar)
                . '?v=' . filemtime($avatarFullPath);
        }
    }
    $avatarInitial = $authUser ? strtoupper(substr($authUser->name, 0, 1)) : '';

    $showRegisterBusiness = !$authUser
        || ($authUser instanceof User && $authUser->canRegisterBusiness());

    // Business registration is behind auth middleware. Guests clicking
    // "Register Business" from the public header would be silently
    // bounced to /login with no context. Route them through account
    // creation first, preserving /register-business as the return target.
    $registerBusinessUrl = $authUser
        ? route('register_business')
        : route('register', ['redirect' => route('register_business')]);

    // Dual-role (business owner) flag + current mode.
    $canSwitchModes = $authUser instanceof User && $authUser->canSwitchModes();
    $isBusinessMode = $canSwitchModes && $authUser->active_mode === User::MODE_BUSINESS;
    $isTouristMode  = $canSwitchModes && $authUser->active_mode === User::MODE_TOURIST;

    // Nav order — discovery-first. "About" is a static info page and
    // sits last; the four discovery-focused links cluster together.
    $navLinks = [
        ['route' => 'home',                'label' => 'Home'],
        ['route' => 'explore.map',         'label' => 'Explore'],
        ['route' => 'tourist-spots.index', 'label' => 'Tourist Spots'],
        ['route' => 'events',              'label' => 'Events'],
        ['route' => 'about',               'label' => 'About'],
    ];
?>

<?php $__env->startPush('styles'); ?>
    <?php if (! $__env->hasRenderedOnce('363db6e1-3d04-4c70-a001-4306febfa588')): $__env->markAsRenderedOnce('363db6e1-3d04-4c70-a001-4306febfa588'); ?>
        <style>
            /* Rule 69 replacements — CSS keyframes in place of x-transition. */

            /* User dropdown — fade + scale + slide from top-right. */
            .public-header-dropdown {
                animation: publicHeaderDropdownIn .15s cubic-bezier(.16,1,.3,1);
            }
            @keyframes publicHeaderDropdownIn {
                from { opacity: 0; transform: scale(.96) translateY(-4px); }
                to   { opacity: 1; transform: scale(1) translateY(0); }
            }

            /* Mobile drawer — fade + slide down from the header. */
            .public-header-drawer {
                animation: publicHeaderDrawerIn .2s cubic-bezier(.16,1,.3,1);
            }
            @keyframes publicHeaderDrawerIn {
                from { opacity: 0; transform: translateY(-8px); }
                to   { opacity: 1; transform: translateY(0); }
            }

            @media (prefers-reduced-motion: reduce) {
                .public-header-dropdown,
                .public-header-drawer { animation: none; }
            }
        </style>
    <?php endif; ?>
<?php $__env->stopPush(); ?>

<div x-data="{
        scrolled: false,
        mobileOpen: false,
        userDropdownOpen: false,
        dark: document.documentElement.classList.contains('dark'),

        init() {
            const stored = localStorage.getItem('hs_theme');
            if (stored === 'dark' || stored === 'light') {
                this.dark = stored === 'dark';
                document.documentElement.classList.toggle('dark', this.dark);
            }

            // Lock body scroll while the mobile drawer is open.
            this.$watch('mobileOpen', open => {
                document.body.classList.toggle(
                    'overflow-hidden',
                    open && window.innerWidth < 1024
                );
            });

            // If the user rotates to landscape or resizes past `lg`,
            // close the mobile drawer so it can't linger off-canvas.
            window.addEventListener('resize', () => {
                if (window.innerWidth >= 1024 && this.mobileOpen) {
                    this.mobileOpen = false;
                }
            });
        }
    }"
     @scroll.window.throttle.100ms.passive="scrolled = window.scrollY > 10"
     @keydown.escape.window="mobileOpen = false; userDropdownOpen = false">

    
    <header
        class="fixed top-0 left-0 right-0 z-50 flex items-center w-full h-16 md:h-20
               bg-white/90 dark:bg-gray-900/90 backdrop-blur border-b
               [transform:translateZ(0)] [backface-visibility:hidden]
               transition-[border-color,box-shadow,background-color] duration-300"
        :class="scrolled ? 'border-gray-200 dark:border-gray-700 shadow-lg shadow-gray-900/5' : 'border-gray-200 dark:border-gray-700'">

        <nav class="w-full max-w-[90rem] mx-auto flex items-center justify-between gap-2 sm:gap-3 lg:gap-4
                    ps-[max(1rem,env(safe-area-inset-left))]
                    pe-[max(1rem,env(safe-area-inset-right))]
                    sm:ps-[max(1.5rem,env(safe-area-inset-left))]
                    sm:pe-[max(1.5rem,env(safe-area-inset-right))]
                    lg:ps-[max(2rem,env(safe-area-inset-left))]
                    lg:pe-[max(2rem,env(safe-area-inset-right))]">

            
            <a class="flex items-center gap-2 sm:gap-3 group min-w-0
                      transition-all duration-200 active:scale-95
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded-md"
               href="<?php echo e(route('home')); ?>" wire:navigate>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logoUrl): ?>
                    <img src="<?php echo e($logoUrl); ?>" alt="<?php echo e($siteName); ?> logo"
                         class="h-8 md:h-10 w-auto object-contain shrink-0"
                         decoding="async">
                <?php else: ?>
                    <div class="flex items-center justify-center h-8 md:h-10 w-8 md:w-10 rounded-lg bg-primary-600 text-white shrink-0"
                         role="img" aria-label="<?php echo e($siteName); ?> logo">
                        <span class="text-base md:text-lg font-bold"><?php echo e(strtoupper(substr($siteName, 0, 1))); ?></span>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <span class="font-display text-base sm:text-lg md:text-2xl font-bold text-primary-700 dark:text-white
                             leading-none tracking-tight truncate">
                    <?php echo e($siteName); ?>

                </span>
            </a>

            
            <div class="hidden lg:flex items-center gap-8 shrink-0">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $navLinks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php $isActive = request()->routeIs($link['route']); ?>
                    <a <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'desktop-'.e($link['route']).''; ?>wire:key="desktop-<?php echo e($link['route']); ?>"
                       href="<?php echo e(route($link['route'])); ?>" wire:navigate
                       <?php if($isActive): ?> aria-current="page" <?php endif; ?>
                       class="text-[15px] transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded-md px-1 <?php echo e($isActive ? 'text-primary-600 dark:text-primary-400 font-bold' : 'font-medium text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-white'); ?>">
                        <?php echo e($link['label']); ?>

                    </a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>

            
            <div class="flex items-center gap-1 sm:gap-1.5 md:gap-3 shrink-0">

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canSwitchModes): ?>
                    <form method="POST" action="<?php echo e(route('mode.switch')); ?>" class="hidden md:block">
                        <?php echo csrf_field(); ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isBusinessMode): ?>
                            <input type="hidden" name="mode" value="<?php echo e(User::MODE_TOURIST); ?>">
                            <button type="submit"
                                    class="group inline-flex items-center gap-2 px-3 lg:px-3.5 py-2 rounded-full border border-blue-200 dark:border-blue-500/30 bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-300 text-xs font-semibold transition-all duration-200 hover:bg-blue-100 dark:hover:bg-blue-500/20 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v3m0 12v3M5.636 5.636l2.121 2.121m8.486 8.486l2.121 2.121M3 12h3m12 0h3M5.636 18.364l2.121-2.121m8.486-8.486l2.121-2.121"/>
                                </svg>
                                <span class="hidden lg:inline">Switch to Tourist</span>
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="mode" value="<?php echo e(User::MODE_BUSINESS); ?>">
                            <button type="submit"
                                    class="group inline-flex items-center gap-2 px-3 lg:px-3.5 py-2 rounded-full border border-emerald-200 dark:border-emerald-500/30 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-xs font-semibold transition-all duration-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.073a2.25 2.25 0 01-1.606 2.16l-6.75 1.93a2.25 2.25 0 01-1.288 0l-6.75-1.93a2.25 2.25 0 01-1.606-2.16V14.15M18 9.75V7.5a3 3 0 00-3-3H9a3 3 0 00-3 3v2.25M3.75 12v.75h16.5V12a2.25 2.25 0 00-2.25-2.25h-12A2.25 2.25 0 003.75 12z"/>
                                </svg>
                                <span class="hidden lg:inline">Switch to Business</span>
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </form>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('public::partials.notification-bell', []);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1877841496-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <button type="button"
                        @click="
                            dark = !dark;
                            document.documentElement.classList.toggle('dark', dark);
                            localStorage.setItem('hs_theme', dark ? 'dark' : 'light');
                            window.dispatchEvent(new CustomEvent('theme-changed'));
                        "
                        class="flex justify-center items-center size-9 md:size-10 rounded-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 transition-all duration-200 active:scale-95 shrink-0"
                        aria-label="Toggle dark mode">
                    <svg xmlns="http://www.w3.org/2000/svg" x-cloak :class="dark ? '' : 'hidden'" class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                    <svg xmlns="http://www.w3.org/2000/svg" x-cloak :class="dark ? 'hidden' : ''" class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                </button>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->guest()): ?>
                    <a href="<?php echo e(route('login')); ?>" wire:navigate
                       class="hidden sm:inline-flex px-4 lg:px-5 py-2.5 text-sm font-medium text-white transition-all duration-200 bg-primary-600 rounded-full hover:bg-primary-700 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 shrink-0">
                        Login / Sign Up
                    </a>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showRegisterBusiness): ?>
                        <a href="<?php echo e($registerBusinessUrl); ?>" wire:navigate
                           class="hidden md:inline-flex px-4 lg:px-5 py-2.5 text-sm font-medium text-primary-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 shrink-0">
                            Register Business
                        </a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                    <div class="relative shrink-0" @click.outside="userDropdownOpen = false">
                        <button type="button"
                                @click="userDropdownOpen = !userDropdownOpen"
                                :aria-expanded="userDropdownOpen.toString()"
                                aria-haspopup="true"
                                class="flex items-center gap-1.5 sm:gap-2 text-sm font-medium
                                       py-1.5 ps-1.5 pe-2 sm:ps-2 sm:pe-3
                                       rounded-full bg-primary-600 text-white hover:bg-primary-700
                                       transition-all duration-200 shadow-sm active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($avatarUrl): ?>
                                <img src="<?php echo e($avatarUrl); ?>" alt="<?php echo e($authUser->name); ?>"
                                     class="object-cover w-7 h-7 sm:w-6 sm:h-6 rounded-full shrink-0"
                                     decoding="async">
                            <?php else: ?>
                                <div class="flex items-center justify-center w-7 h-7 sm:w-6 sm:h-6 text-xs sm:text-sm font-bold text-white rounded-full bg-white/20 shrink-0">
                                    <?php echo e($avatarInitial); ?>

                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <span class="hidden sm:inline max-w-[120px] truncate"><?php echo e($authUser->name); ?></span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="hidden sm:block w-4 h-4 transition-transform duration-200 shrink-0"
                                 :class="userDropdownOpen ? 'rotate-180' : ''"
                                 width="16" height="16" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path d="m19 9-7 7-7-7"/>
                            </svg>
                        </button>

                        
                        <div x-cloak
                             :class="userDropdownOpen ? 'public-header-dropdown' : 'hidden'"
                             class="absolute right-0 mt-2 w-64 max-w-[calc(100vw-2rem)] bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-2 shadow-xl z-50 origin-top-right">

                            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                                <div class="flex items-center gap-3">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($avatarUrl): ?>
                                        <img src="<?php echo e($avatarUrl); ?>" alt="<?php echo e($authUser->name); ?>"
                                             class="object-cover w-9 h-9 rounded-full shrink-0"
                                             decoding="async">
                                    <?php else: ?>
                                        <div class="flex items-center justify-center w-9 h-9 text-sm font-bold text-white bg-primary-600 rounded-full shrink-0">
                                            <?php echo e($avatarInitial); ?>

                                        </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?php echo e($authUser->name); ?></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate"><?php echo e($authUser->email); ?></p>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($authUser->hasRole('super-admin')): ?>
                                            <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-purple-100 dark:bg-purple-500/20 text-purple-700 dark:text-purple-300">
                                                Super Admin
                                            </span>
                                        <?php elseif($canSwitchModes): ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isBusinessMode): ?>
                                                <span class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    Business Mode
                                                </span>
                                            <?php else: ?>
                                                <span class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                                    Tourist Mode
                                                </span>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php elseif($authUser->hasRole('tourist')): ?>
                                            <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300">
                                                Tourist
                                            </span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="p-1.5 space-y-0.5">

                                
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canSwitchModes): ?>
                                    <form method="POST" action="<?php echo e(route('mode.switch')); ?>">
                                        <?php echo csrf_field(); ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isBusinessMode): ?>
                                            <input type="hidden" name="mode" value="<?php echo e(User::MODE_TOURIST); ?>">
                                            <button type="submit"
                                                    class="flex w-full items-center gap-3 py-2 px-3 rounded-lg text-sm text-blue-700 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-500/10 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v3m0 12v3M5.636 5.636l2.121 2.121m8.486 8.486l2.121 2.121M3 12h3m12 0h3M5.636 18.364l2.121-2.121m8.486-8.486l2.121-2.121"/>
                                                </svg>
                                                Switch to Tourist Mode
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="mode" value="<?php echo e(User::MODE_BUSINESS); ?>">
                                            <button type="submit"
                                                    class="flex w-full items-center gap-3 py-2 px-3 rounded-lg text-sm text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.073a2.25 2.25 0 01-1.606 2.16l-6.75 1.93a2.25 2.25 0 01-1.288 0l-6.75-1.93a2.25 2.25 0 01-1.606-2.16V14.15M18 9.75V7.5a3 3 0 00-3-3H9a3 3 0 00-3 3v2.25M3.75 12v.75h16.5V12a2.25 2.25 0 00-2.25-2.25h-12A2.25 2.25 0 003.75 12z"/>
                                                </svg>
                                                Switch to Business Mode
                                            </button>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </form>

                                    <div class="border-t border-gray-100 dark:border-gray-700 my-2"></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canSwitchModes): ?>
                                    <a class="flex items-center gap-3 py-2 px-3 rounded-lg text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary-600 dark:hover:text-white transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                       href="<?php echo e(route('tenant.dashboard')); ?>" wire:navigate
                                       @click="userDropdownOpen = false">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1h3a1 1 0 001-1V10"/>
                                        </svg>
                                        Business Dashboard
                                    </a>
                                    <a class="flex items-center gap-3 py-2 px-3 rounded-lg text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary-600 dark:hover:text-white transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                       href="<?php echo e(route('my-bookings')); ?>" wire:navigate
                                       @click="userDropdownOpen = false">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        My Bookings
                                    </a>
                                <?php else: ?>
                                    <a class="flex items-center gap-3 py-2 px-3 rounded-lg text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary-600 dark:hover:text-white transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                       href="<?php echo e(route('my-bookings')); ?>" wire:navigate
                                       @click="userDropdownOpen = false">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        My Bookings
                                    </a>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <a class="flex items-center gap-3 py-2 px-3 rounded-lg text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary-600 dark:hover:text-white transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                   href="<?php echo e(route('profile')); ?>" wire:navigate
                                   @click="userDropdownOpen = false">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    My Profile
                                </a>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showRegisterBusiness): ?>
                                    <a class="flex items-center gap-3 py-2 px-3 rounded-lg text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary-600 dark:hover:text-white transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                       href="<?php echo e($registerBusinessUrl); ?>" wire:navigate
                                       @click="userDropdownOpen = false">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Register Business
                                    </a>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <div class="border-t border-gray-100 dark:border-gray-700 my-2"></div>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $navLinks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <a <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'dropdown-'.e($link['route']).''; ?>wire:key="dropdown-<?php echo e($link['route']); ?>"
                                       class="flex items-center gap-3 py-2 px-3 rounded-lg text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-primary-600 dark:hover:text-white transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                       href="<?php echo e(route($link['route'])); ?>" wire:navigate
                                       <?php if(request()->routeIs($link['route'])): ?> aria-current="page" <?php endif; ?>
                                       @click="userDropdownOpen = false">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657 13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <?php echo e($link['label']); ?>

                                    </a>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                                <div class="border-t border-gray-100 dark:border-gray-700 my-2"></div>

                                <form method="POST" action="<?php echo e(route('logout')); ?>">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit"
                                            class="flex w-full items-center gap-3 py-2 px-3 rounded-lg text-sm text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/>
                                        </svg>
                                        Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <div class="lg:hidden shrink-0">
                    <button type="button"
                            @click="mobileOpen = !mobileOpen"
                            :aria-expanded="mobileOpen.toString()"
                            class="size-9 md:size-10 rounded-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 flex items-center justify-center text-gray-700 dark:text-gray-200 transition-all duration-200 active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                            aria-label="Toggle navigation">
                        <svg xmlns="http://www.w3.org/2000/svg" x-cloak :class="!mobileOpen ? '' : 'hidden'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <line x1="3" x2="21" y1="6" y2="6"/><line x1="3" x2="21" y1="12" y2="12"/><line x1="3" x2="21" y1="18" y2="18"/>
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" x-cloak :class="mobileOpen ? '' : 'hidden'" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        </nav>

        
        <div x-cloak
             :class="mobileOpen ? 'public-header-drawer' : 'hidden'"
             data-mobile-drawer
             role="navigation"
             aria-label="Mobile navigation"
             class="lg:hidden absolute top-full left-0 right-0
                    bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700
                    pt-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]
                    ps-[max(1rem,env(safe-area-inset-left))]
                    pe-[max(1rem,env(safe-area-inset-right))]
                    sm:ps-[max(1.5rem,env(safe-area-inset-left))]
                    sm:pe-[max(1.5rem,env(safe-area-inset-right))]
                    space-y-2
                    z-40 shadow-lg
                    max-h-[calc(100dvh-4rem)] md:max-h-[calc(100dvh-5rem)]
                    overflow-y-auto overscroll-contain">

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $navLinks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php $isActive = request()->routeIs($link['route']); ?>
                <a <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'mobile-'.e($link['route']).''; ?>wire:key="mobile-<?php echo e($link['route']); ?>"
                   <?php if($isActive): ?> aria-current="page" <?php endif; ?>
                   class="block text-base font-medium active:scale-95 transition-all duration-200 rounded-lg px-3 py-2.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 <?php echo e($isActive ? 'text-primary-600 dark:text-primary-400 font-bold bg-primary-50 dark:bg-primary-500/10' : 'text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-700/50'); ?>"
                   href="<?php echo e(route($link['route'])); ?>" wire:navigate
                   @click="mobileOpen = false">
                    <?php echo e($link['label']); ?>

                </a>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canSwitchModes): ?>
                    <form method="POST" action="<?php echo e(route('mode.switch')); ?>" class="pt-4 mt-3 border-t border-gray-100 dark:border-gray-700">
                        <?php echo csrf_field(); ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isBusinessMode): ?>
                            <input type="hidden" name="mode" value="<?php echo e(User::MODE_TOURIST); ?>">
                            <button type="submit"
                                    class="w-full text-center text-base font-medium py-3 rounded-full bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-500/30 hover:bg-blue-100 dark:hover:bg-blue-500/20 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50">
                                Switch to Tourist Mode
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="mode" value="<?php echo e(User::MODE_BUSINESS); ?>">
                            <button type="submit"
                                    class="w-full text-center text-base font-medium py-3 rounded-full bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50">
                                Switch to Business Mode
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </form>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div class="pt-4 mt-3 border-t border-gray-100 dark:border-gray-700 space-y-1">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canSwitchModes): ?>
                        <a class="block text-base font-medium text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-700/50 active:scale-95 transition-all duration-200 rounded-lg px-3 py-2.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                           href="<?php echo e(route('tenant.dashboard')); ?>" wire:navigate
                           @click="mobileOpen = false">Business Dashboard</a>
                        <a class="block text-base font-medium text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-700/50 active:scale-95 transition-all duration-200 rounded-lg px-3 py-2.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                           href="<?php echo e(route('my-bookings')); ?>" wire:navigate
                           @click="mobileOpen = false">My Bookings</a>
                    <?php else: ?>
                        <a class="block text-base font-medium text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-700/50 active:scale-95 transition-all duration-200 rounded-lg px-3 py-2.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                           href="<?php echo e(route('my-bookings')); ?>" wire:navigate
                           @click="mobileOpen = false">My Bookings</a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <a class="block text-base font-medium text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-700/50 active:scale-95 transition-all duration-200 rounded-lg px-3 py-2.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                       href="<?php echo e(route('profile')); ?>" wire:navigate
                       @click="mobileOpen = false">My Profile</a>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showRegisterBusiness): ?>
                        <a class="block text-base font-medium text-gray-700 dark:text-gray-200 hover:text-primary-600 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-700/50 active:scale-95 transition-all duration-200 rounded-lg px-3 py-2.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                           href="<?php echo e($registerBusinessUrl); ?>" wire:navigate
                           @click="mobileOpen = false">Register Business</a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <form method="POST" action="<?php echo e(route('logout')); ?>" class="pt-3">
                        <?php echo csrf_field(); ?>
                        <button type="submit"
                                class="w-full text-center text-base font-medium py-3 rounded-full bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-500/20 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                            Logout
                        </button>
                    </form>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->guest()): ?>
                <div class="flex flex-col gap-3 pt-4 mt-3 border-t border-gray-200 dark:border-gray-700">
                    <a href="<?php echo e(route('login')); ?>" wire:navigate @click="mobileOpen = false"
                       class="w-full text-center text-base font-medium py-3 rounded-full bg-primary-600 text-white hover:bg-primary-700 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        Login / Sign Up
                    </a>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showRegisterBusiness): ?>
                        <a href="<?php echo e($registerBusinessUrl); ?>" wire:navigate @click="mobileOpen = false"
                           class="w-full text-center text-base font-medium py-3 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            Register Business
                        </a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </header>

    
    <div x-cloak
         :class="mobileOpen ? 'opacity-100' : 'opacity-0 pointer-events-none'"
         class="fixed inset-0 z-40 bg-black/50 lg:hidden transition-opacity duration-200"
         @click="mobileOpen = false"
         aria-hidden="true"></div>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views/components/headers/public-header.blade.php ENDPATH**/ ?>