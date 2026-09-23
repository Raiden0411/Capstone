
<?php
    use App\Models\User;

    $user   = auth()->user();
    $tenant = $user?->tenant;

    /* Tenant logo — used as the brand fallback in the profile dropdown. */
    $tenantLogoUrl = null;
    if ($tenant && $tenant->logo) {
        $fullPath      = public_path('storage/' . $tenant->logo);
        $tenantLogoUrl = asset('storage/' . $tenant->logo)
            . '?v=' . (file_exists($fullPath) ? filemtime($fullPath) : time());
    }

    /* User's own avatar — preferred over the tenant logo in the profile
       button and dropdown (identity vs. brand). */
    $userAvatarPath = $user?->avatar;
    $userAvatarUrl  = null;
    if ($userAvatarPath) {
        $fullPath      = public_path('storage/' . $userAvatarPath);
        $userAvatarUrl = asset('storage/' . $userAvatarPath)
            . '?v=' . (file_exists($fullPath) ? filemtime($fullPath) : time());
    }

    $userName    = $user?->name ?? 'U';
    $userInitial = strtoupper(substr($userName, 0, 1));
    $userRole    = $user?->roles->first();
    $roleLabel   = $userRole ? ucwords(str_replace(['-', '_'], ' ', $userRole->name)) : null;

    /* True when the caller holds the admin/super-admin role. Used to gate
       links whose routes carry the `role:admin|super-admin` middleware
       (Business Settings, etc). Without this, an employee sees the link
       and gets a dead-end 403 when they click it. */
    $isAdmin = $user?->hasAnyRole(['admin', 'super-admin']) ?? false;

    /* Determine the correct dashboard route for the header link. */
    $isEmployee     = $user && ! $user->hasRole('admin') && $user->tenant_id;
    $dashboardRoute = $isEmployee
        ? route('tenant.employee.dashboard')
        : route('tenant.dashboard');

    /* Dual-role accounts (business owners) can switch back to tourist mode. */
    $canSwitchModes = $user instanceof User && $user->canSwitchModes();

    /* Canonical dropdown-link class — one source of truth for the six
       links inside the profile dropdown. */
    $dropdownLinkClass = 'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm '
        . 'text-gray-700 dark:text-gray-200 '
        . 'hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white '
        . 'transition-all duration-200 active:scale-[0.98] '
        . 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 '
        . 'focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900';
?>

<?php $__env->startPush('styles'); ?>
    <?php if (! $__env->hasRenderedOnce('9be596cb-ad0f-44c3-affb-344904129b62')): $__env->markAsRenderedOnce('9be596cb-ad0f-44c3-affb-344904129b62'); ?>
        <style>
            /* Rule 69 replacement — CSS keyframe for the profile dropdown. */
            .tenant-header-dropdown {
                animation: tenantHeaderDropdownIn .15s cubic-bezier(.16,1,.3,1);
            }
            @keyframes tenantHeaderDropdownIn {
                from { opacity: 0; transform: scale(.96) translateY(-4px); }
                to   { opacity: 1; transform: scale(1) translateY(0); }
            }
            @media (prefers-reduced-motion: reduce) {
                .tenant-header-dropdown { animation: none; }
            }
        </style>
    <?php endif; ?>
<?php $__env->stopPush(); ?>

<header
    x-data="{
        minified: localStorage.getItem('tenant_sidebar_minified') === '1',

        /* Mirror the theme the layout's applyTheme() already applied to
           <html>. Reading localStorage directly desyncs for first-time
           visitors (hs_theme === null + system prefers dark). */
        dark: document.documentElement.classList.contains('dark'),

        toggleDark() {
            this.dark = ! this.dark;
            localStorage.setItem('hs_theme', this.dark ? 'dark' : 'light');
            document.documentElement.classList.toggle('dark', this.dark);
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: this.dark }));
        }
    }"
    @sidebar-minified-tenant.window="minified = $event.detail"
    :class="minified ? 'lg:ps-20' : 'lg:ps-64'"
    class="sticky top-0 inset-x-0 flex flex-wrap md:justify-start md:flex-nowrap z-30 w-full bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 text-sm h-16 md:h-20 transition-all duration-300"
>
    <nav class="px-4 sm:px-6 flex basis-full items-center w-full mx-auto justify-between gap-2" aria-label="Tenant header">

        
        <div class="flex items-center gap-2 lg:hidden">
            <button type="button"
                    class="flex items-center justify-center size-8 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                    @click="$dispatch('toggle-tenant-sidebar')"
                    aria-label="Toggle navigation">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>

        
        <div class="hidden md:flex flex-1 items-center gap-2 px-2">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant): ?>
                <a href="<?php echo e($dashboardRoute); ?>"
                   wire:navigate
                   class="text-xs text-gray-500 dark:text-gray-400 font-medium hover:text-gray-700 dark:hover:text-gray-200
                          transition-all duration-200 active:scale-95 rounded
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <?php echo e($tenant->name); ?>

                </a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div class="flex items-center gap-2 ms-auto">

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canSwitchModes): ?>
                <form method="POST" action="<?php echo e(route('mode.switch')); ?>" class="hidden sm:block">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="mode" value="<?php echo e(User::MODE_TOURIST); ?>">
                    <button type="submit"
                            class="inline-flex items-center gap-2 py-2 px-3 rounded-full border border-blue-200 dark:border-blue-500/30 bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-300 text-xs font-semibold
                                   transition-all duration-200 hover:bg-blue-100 dark:hover:bg-blue-500/20 active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v3m0 12v3M5.636 5.636l2.121 2.121m8.486 8.486l2.121 2.121M3 12h3m12 0h3M5.636 18.364l2.121-2.121m8.486-8.486l2.121-2.121"/>
                        </svg>
                        <span>Switch to Tourist</span>
                    </button>
                </form>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <button type="button"
                    @click="toggleDark()"
                    class="flex items-center justify-center size-9 rounded-lg text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900"
                    aria-label="Toggle dark mode">
                <svg x-cloak :class="dark ? '' : 'hidden'" xmlns="http://www.w3.org/2000/svg" class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>
                </svg>
                <svg x-cloak :class="dark ? 'hidden' : ''" xmlns="http://www.w3.org/2000/svg" class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
                </svg>
            </button>

            
            <div class="relative"
                 x-data="{ open: false }"
                 @click.outside="open = false"
                 @keydown.escape.window="open = false">

                <button type="button"
                        @click="open = ! open"
                        class="flex items-center gap-2 py-1.5 px-2 rounded-lg text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700
                               transition-all duration-200 active:scale-95
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900"
                        :aria-expanded="open.toString()"
                        aria-haspopup="true">
                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($userAvatarUrl): ?>
                        <img src="<?php echo e($userAvatarUrl); ?>"
                             alt="<?php echo e($userName); ?>"
                             width="24" height="24"
                             loading="lazy" decoding="async"
                             class="size-6 rounded-full object-cover shrink-0">
                    <?php elseif($tenantLogoUrl): ?>
                        <img src="<?php echo e($tenantLogoUrl); ?>"
                             alt="<?php echo e($tenant?->name); ?>"
                             width="24" height="24"
                             loading="lazy" decoding="async"
                             class="size-6 rounded-full object-cover shrink-0">
                    <?php else: ?>
                        <div class="size-6 rounded-full bg-primary-600 text-white flex items-center justify-center font-bold text-xs shrink-0">
                            <?php echo e($userInitial); ?>

                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <span class="hidden sm:inline max-w-[120px] truncate text-sm font-medium"><?php echo e($userName); ?></span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="hidden sm:block size-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                
                <div x-cloak
                     :class="open ? 'tenant-header-dropdown' : 'hidden'"
                     class="absolute right-0 mt-2 w-64 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-2xl z-50 overflow-hidden"
                     role="menu"
                     aria-label="Account menu">

                    
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($userAvatarUrl): ?>
                                <img src="<?php echo e($userAvatarUrl); ?>"
                                     alt="<?php echo e($userName); ?>"
                                     width="40" height="40"
                                     loading="lazy" decoding="async"
                                     class="size-10 rounded-full object-cover shrink-0">
                            <?php elseif($tenantLogoUrl): ?>
                                <img src="<?php echo e($tenantLogoUrl); ?>"
                                     alt="<?php echo e($tenant?->name); ?>"
                                     width="40" height="40"
                                     loading="lazy" decoding="async"
                                     class="size-10 rounded-full object-cover shrink-0">
                            <?php else: ?>
                                <div class="size-10 rounded-full bg-primary-600 text-white flex items-center justify-center font-bold text-sm shrink-0">
                                    <?php echo e($userInitial); ?>

                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?php echo e($userName); ?></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate"><?php echo e($user?->email); ?></p>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canSwitchModes): ?>
                                    <span class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Business Mode
                                    </span>
                                <?php elseif($roleLabel): ?>
                                    <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-300">
                                        <?php echo e($roleLabel); ?>

                                    </span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    
                    <div class="p-1.5 space-y-0.5">

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canSwitchModes): ?>
                            <form method="POST" action="<?php echo e(route('mode.switch')); ?>" class="block">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="mode" value="<?php echo e(User::MODE_TOURIST); ?>">
                                <button type="submit"
                                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-blue-700 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-500/10
                                               transition-all duration-200 active:scale-[0.98]
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v3m0 12v3M5.636 5.636l2.121 2.121m8.486 8.486l2.121 2.121M3 12h3m12 0h3M5.636 18.364l2.121-2.121m8.486-8.486l2.121-2.121"/>
                                    </svg>
                                    Switch to Tourist Mode
                                </button>
                            </form>

                            <div class="border-t border-gray-200 dark:border-gray-700 my-2"></div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        
                        <a href="<?php echo e(route('tenant.account.index')); ?>" wire:navigate
                           @click="open = false"
                           class="<?php echo e($dropdownLinkClass); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            My Account
                        </a>

                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant && $isAdmin): ?>
                            <a href="<?php echo e(route('tenant.settings.index')); ?>" wire:navigate
                               @click="open = false"
                               class="<?php echo e($dropdownLinkClass); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Business Settings
                            </a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant): ?>
                            <a href="<?php echo e(route('tenant.show', $tenant->slug)); ?>"
                               target="_blank" rel="noopener noreferrer"
                               @click="open = false"
                               class="<?php echo e($dropdownLinkClass); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                                View Public Listing
                            </a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div class="border-t border-gray-200 dark:border-gray-700 my-2"></div>

                        <form method="POST" action="<?php echo e(route('logout')); ?>" class="block">
                            <?php echo csrf_field(); ?>
                            <button type="submit"
                                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10
                                           transition-all duration-200 active:scale-[0.98]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Sign Out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</header><?php /**PATH C:\laragon\www\Capstone\resources\views\components\headers\tenant\tenant-header.blade.php ENDPATH**/ ?>