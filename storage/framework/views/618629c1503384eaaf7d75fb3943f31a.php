
<?php
    use App\Services\SuperadminNotificationService;

    // Cached site settings lookups.
    $logoPath = \App\Models\SiteSetting::getValue('site_logo');
    $siteName = \App\Models\SiteSetting::getValue('site_name', config('app.name'));

    $logoUrl = null;
    if ($logoPath) {
        $fullPath = public_path('storage/' . $logoPath);
        $logoUrl  = asset('storage/' . $logoPath)
            . '?v=' . (file_exists($fullPath) ? filemtime($fullPath) : time());
    }

    /*
     * Both badges come from the same service — single source of truth
     * for cache key, TTL, and count semantics.
     */
    $notifications       = app(SuperadminNotificationService::class);
    $pendingApplications = $notifications->pendingCount();
    $pendingDeletions    = $notifications->deletionRequestCount();

    /*
     * Canonical nav-link class + active/inactive variants.
     * One source of truth for the ~17 identical link class strings.
     */
    $navLinkClass = 'group relative flex items-center gap-x-3.5 rounded-lg px-3 py-2.5 text-sm '
        . 'transition-all duration-200 active:scale-[0.98] '
        . 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 '
        . 'focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 '
        . 'border-l-2';

    $navLinkActive   = 'bg-primary-50 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300 border-l-primary-600';
    $navLinkInactive = 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white border-l-transparent';
?>

<div
    x-data="{
        mobileOpen: false,
        minified: localStorage.getItem('sidebar_minified') === '1',
        toggleMinified() {
            this.minified = ! this.minified;
            localStorage.setItem('sidebar_minified', this.minified ? '1' : '0');
            window.dispatchEvent(new CustomEvent('sidebar-minified', { detail: this.minified }));
        }
    }"
    x-init="
        if (minified) {
            window.dispatchEvent(new CustomEvent('sidebar-minified', { detail: minified }));
        }
    "
    @toggle-superadmin-sidebar.window="mobileOpen = !mobileOpen"
    @keydown.escape.window="mobileOpen = false"
>
    
    <div x-cloak
         :class="mobileOpen ? 'opacity-100' : 'opacity-0 pointer-events-none'"
         class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm lg:hidden transition-opacity duration-300"
         @click="mobileOpen = false"
         aria-hidden="true"></div>

    
    <aside
        :class="[
            minified ? 'lg:w-20' : 'lg:w-64',
            mobileOpen ? 'translate-x-0' : '-translate-x-full',
            'lg:translate-x-0',
        ]"
        class="fixed inset-y-0 start-0 z-50 border-e border-gray-200 bg-white transition-all duration-300 ease-out dark:border-gray-700 dark:bg-gray-900"
        aria-label="Main navigation"
    >
        <div class="relative flex h-full max-h-full flex-col">

            
            <div class="flex h-16 md:h-20 shrink-0 items-center justify-between border-b border-gray-200 px-4 dark:border-gray-700"
                 :class="minified ? 'lg:justify-center lg:px-2' : ''">
                <div class="flex items-center gap-2 overflow-hidden">
                    
                    <a href="<?php echo e(route('superadmin.dashboard')); ?>" wire:navigate
                       class="flex items-center gap-2 text-xl font-bold whitespace-nowrap text-gray-900 dark:text-white
                              rounded transition-all duration-200 hover:opacity-80 active:scale-95
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logoUrl): ?>
                            <img src="<?php echo e($logoUrl); ?>" alt="<?php echo e($siteName); ?> logo"
                                 width="32" height="32"
                                 loading="lazy" decoding="async"
                                 class="h-8 w-8 shrink-0 rounded-lg object-contain">
                        <?php else: ?>
                            <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary-600 text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/>
                                </svg>
                            </span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <span x-cloak :class="minified ? 'lg:hidden' : ''"><?php echo e($siteName); ?></span>
                    </a>
                </div>

                
                <button type="button"
                        class="flex size-8 items-center justify-center rounded-full bg-gray-100 text-gray-500
                               transition-all duration-200 hover:bg-gray-200 hover:text-gray-800 active:scale-95
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                               lg:hidden dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                        @click="mobileOpen = false"
                        aria-label="Close sidebar">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                    </svg>
                </button>
            </div>

            
            <div class="flex-1 overflow-y-auto overflow-x-hidden [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-gray-300 dark:[&::-webkit-scrollbar-thumb]:bg-gray-700">
                <nav class="flex w-full flex-col p-3" aria-label="Platform sections">
                    <ul class="flex flex-col space-y-1"
                        :class="minified ? '[&_a]:justify-center [&_a]:px-0' : ''">

                        
                        <li class="pt-0 pb-1" x-cloak :class="minified ? 'lg:hidden' : ''">
                            <span class="block px-3 text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Overview</span>
                        </li>

                        <li>
                            <a href="<?php echo e(route('superadmin.dashboard')); ?>" wire:navigate
                               aria-current="<?php echo e(request()->routeIs('superadmin.dashboard') ? 'page' : 'false'); ?>"
                               title="Dashboard"
                               class="<?php echo e($navLinkClass); ?> <?php echo e(request()->routeIs('superadmin.dashboard') ? $navLinkActive : $navLinkInactive); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Dashboard</span>
                            </a>
                        </li>

                        <li>
                            <a href="<?php echo e(route('superadmin.analytics')); ?>" wire:navigate
                               aria-current="<?php echo e(request()->routeIs('superadmin.analytics') ? 'page' : 'false'); ?>"
                               title="Analytics"
                               class="<?php echo e($navLinkClass); ?> <?php echo e(request()->routeIs('superadmin.analytics') ? $navLinkActive : $navLinkInactive); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M3 3v18h18"/><path d="M7 16l4-4 4 4 5-5"/><path d="M7 8h1l4 4 4-4h1"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Analytics</span>
                            </a>
                        </li>

                        
                        <li class="pt-4 pb-1" x-cloak :class="minified ? 'lg:hidden' : ''">
                            <span class="block px-3 text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Review Queue</span>
                        </li>

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.business-applications.index')); ?>" wire:navigate
                               aria-current="<?php echo e(request()->routeIs('superadmin.business-applications.*') ? 'page' : 'false'); ?>"
                               title="Business Applications"
                               class="<?php echo e($navLinkClass); ?> <?php echo e(request()->routeIs('superadmin.business-applications.*') ? $navLinkActive : $navLinkInactive); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''" class="flex-1">Business Applications</span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pendingApplications > 0): ?>
                                    <span x-cloak :class="minified ? 'lg:hidden' : ''"
                                          class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-amber-500 text-white text-[10px] font-bold">
                                        <?php echo e($pendingApplications > 99 ? '99+' : $pendingApplications); ?>

                                    </span>
                                    <span x-cloak :class="minified ? 'lg:block' : 'hidden'"
                                          class="hidden absolute end-2 top-2 size-2 rounded-full bg-amber-500 ring-2 ring-white dark:ring-gray-900"
                                          aria-label="<?php echo e($pendingApplications); ?> pending applications"></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </a>
                        </li>

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.deletion-requests.index')); ?>" wire:navigate
                               aria-current="<?php echo e(request()->routeIs('superadmin.deletion-requests.*') ? 'page' : 'false'); ?>"
                               title="Deletion Requests"
                               class="<?php echo e($navLinkClass); ?> <?php echo e(request()->routeIs('superadmin.deletion-requests.*') ? $navLinkActive : $navLinkInactive); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''" class="flex-1">Deletion Requests</span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pendingDeletions > 0): ?>
                                    <span x-cloak :class="minified ? 'lg:hidden' : ''"
                                          class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-amber-500 text-white text-[10px] font-bold">
                                        <?php echo e($pendingDeletions > 99 ? '99+' : $pendingDeletions); ?>

                                    </span>
                                    <span x-cloak :class="minified ? 'lg:block' : 'hidden'"
                                          class="hidden absolute end-2 top-2 size-2 rounded-full bg-amber-500 ring-2 ring-white dark:ring-gray-900"
                                          aria-label="<?php echo e($pendingDeletions); ?> pending deletion requests"></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </a>
                        </li>

                        
                        <li class="pt-4 pb-1" x-cloak :class="minified ? 'lg:hidden' : ''">
                            <span class="block px-3 text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Platform</span>
                        </li>

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.tenants.index')); ?>" wire:navigate
                               aria-current="<?php echo e(request()->routeIs('superadmin.tenants.*') ? 'page' : 'false'); ?>"
                               title="Tenants"
                               class="<?php echo e($navLinkClass); ?> <?php echo e(request()->routeIs('superadmin.tenants.*') ? $navLinkActive : $navLinkInactive); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5m-4 0h4"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Tenants</span>
                            </a>
                        </li>

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.users.index')); ?>" wire:navigate
                               aria-current="<?php echo e(request()->routeIs('superadmin.users.*') ? 'page' : 'false'); ?>"
                               title="Users"
                               class="<?php echo e($navLinkClass); ?> <?php echo e(request()->routeIs('superadmin.users.*') ? $navLinkActive : $navLinkInactive); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M12 4.354a4 4 0 1 1 0 5.292M15 21H3v-1a6 6 0 0 1 12 0v1zm0 0h6v-1a6 6 0 0 0-9-5.197M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0z"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Users</span>
                            </a>
                        </li>

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.tenant-types.index')); ?>" wire:navigate
                               aria-current="<?php echo e(request()->routeIs('superadmin.tenant-types.*') ? 'page' : 'false'); ?>"
                               title="Tenant Types"
                               class="<?php echo e($navLinkClass); ?> <?php echo e(request()->routeIs('superadmin.tenant-types.*') ? $navLinkActive : $navLinkInactive); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 0 1 .586 1.414V19a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Tenant Types</span>
                            </a>
                        </li>

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.roles.index')); ?>" wire:navigate
                               aria-current="<?php echo e(request()->routeIs('superadmin.roles.*') ? 'page' : 'false'); ?>"
                               title="Roles"
                               class="<?php echo e($navLinkClass); ?> <?php echo e(request()->routeIs('superadmin.roles.*') ? $navLinkActive : $navLinkInactive); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0 1 12 2.944a11.955 11.955 0 0 1-8.618 3.04A12.02 12.02 0 0 0 3 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Roles</span>
                            </a>
                        </li>

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.events.index')); ?>" wire:navigate
                               aria-current="<?php echo e(request()->routeIs('superadmin.events.*') ? 'page' : 'false'); ?>"
                               title="Events"
                               class="<?php echo e($navLinkClass); ?> <?php echo e(request()->routeIs('superadmin.events.*') ? $navLinkActive : $navLinkInactive); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Events</span>
                            </a>
                        </li>

                        
                        <li class="pt-4 pb-1" x-cloak :class="minified ? 'lg:hidden' : ''">
                            <span class="block px-3 text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Content</span>
                        </li>

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.homepage.editor')); ?>" wire:navigate
                               aria-current="<?php echo e(request()->routeIs('superadmin.homepage.editor') ? 'page' : 'false'); ?>"
                               title="Homepage Editor"
                               class="<?php echo e($navLinkClass); ?> <?php echo e(request()->routeIs('superadmin.homepage.editor') ? $navLinkActive : $navLinkInactive); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Homepage Editor</span>
                            </a>
                        </li>

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.about.editor')); ?>" wire:navigate
                               aria-current="<?php echo e(request()->routeIs('superadmin.about.editor') ? 'page' : 'false'); ?>"
                               title="About Page Editor"
                               class="<?php echo e($navLinkClass); ?> <?php echo e(request()->routeIs('superadmin.about.editor') ? $navLinkActive : $navLinkInactive); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">About Page Editor</span>
                            </a>
                        </li>

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.map-markers.index')); ?>" wire:navigate
                               aria-current="<?php echo e(request()->routeIs('superadmin.map-markers.*') ? 'page' : 'false'); ?>"
                               title="Map Markers"
                               class="<?php echo e($navLinkClass); ?> <?php echo e(request()->routeIs('superadmin.map-markers.*') ? $navLinkActive : $navLinkInactive); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M17.657 16.657 13.414 20.9a1.998 1.998 0 0 1-2.827 0l-4.244-4.243a8 8 0 1 1 11.314 0z"/><path d="M15 11a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Map Markers</span>
                            </a>
                        </li>

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.marker-categories.index')); ?>" wire:navigate
                               aria-current="<?php echo e(request()->routeIs('superadmin.marker-categories.*') ? 'page' : 'false'); ?>"
                               title="Marker Categories"
                               class="<?php echo e($navLinkClass); ?> <?php echo e(request()->routeIs('superadmin.marker-categories.*') ? $navLinkActive : $navLinkInactive); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 0 1 .586 1.414V19a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Marker Categories</span>
                            </a>
                        </li>

                        
                        
                        <li class="pt-4 pb-1" x-cloak :class="minified ? 'lg:hidden' : ''">
                            <span class="block px-3 text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">System</span>
                        </li>

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.health.queries')); ?>" wire:navigate
                               aria-current="<?php echo e(request()->routeIs('superadmin.health.*') ? 'page' : 'false'); ?>"
                               title="Slow Query Dashboard"
                               class="<?php echo e($navLinkClass); ?> <?php echo e(request()->routeIs('superadmin.health.*') ? $navLinkActive : $navLinkInactive); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Health</span>
                            </a>
                        </li>

                        
                        <li class="pt-4 pb-1" x-cloak :class="minified ? 'lg:hidden' : ''">
                            <span class="block px-3 text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Account</span>
                        </li>

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.profile')); ?>" wire:navigate
                               aria-current="<?php echo e(request()->routeIs('superadmin.profile') ? 'page' : 'false'); ?>"
                               title="My Profile"
                               class="<?php echo e($navLinkClass); ?> <?php echo e(request()->routeIs('superadmin.profile') ? $navLinkActive : $navLinkInactive); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">My Profile</span>
                            </a>
                        </li>

                    </ul>
                </nav>
            </div>

            
            <button type="button"
                    @click="toggleMinified()"
                    class="absolute -right-3 top-1/2 z-50 hidden size-7 -translate-y-1/2 items-center justify-center rounded-full
                           border border-gray-200 bg-white text-gray-500 shadow-sm
                           transition-all duration-200 hover:bg-gray-100 hover:text-gray-900 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                           lg:flex dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white"
                    :class="minified ? 'rotate-180' : ''"
                    aria-label="Toggle sidebar">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/>
                </svg>
            </button>

        </div>
    </aside>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views\components\headers\admin\sidebar.blade.php ENDPATH**/ ?>