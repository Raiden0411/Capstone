
<?php
    $logoPath = \App\Models\SiteSetting::getValue('site_logo');
    $siteName = \App\Models\SiteSetting::getValue('site_name', config('app.name'));

    $logoUrl = null;
    if ($logoPath) {
        $fullPath = public_path('storage/' . $logoPath);
        $logoUrl = asset('storage/' . $logoPath);
        if (file_exists($fullPath)) {
            $logoUrl .= '?v=' . filemtime($fullPath);
        } else {
            $logoUrl .= '?v=' . time();
        }
    }
?>

<div>
    
    <div x-cloak
         x-show="mobileOpen"
         x-transition:enter="transition-opacity duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm lg:hidden"
         @click="mobileOpen = false"
         aria-hidden="true"></div>

    <div
        x-data="{
            mobileOpen: false,
            minified: localStorage.getItem('sidebar_minified') === '1',
            toggleMinified() {
                this.minified = !this.minified;
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
        :class="[
            minified ? 'lg:w-20' : 'lg:w-64',
            mobileOpen ? 'translate-x-0' : '-translate-x-full',
            'lg:translate-x-0'
        ]"
        class="fixed inset-y-0 start-0 z-50 h-full transform border-e border-gray-200 bg-white transition-all duration-300 ease-out dark:border-gray-700 dark:bg-gray-900 lg:block"
        role="dialog" tabindex="-1" aria-label="Sidebar"
    >
        <div class="relative flex h-full max-h-full flex-col">

            
            <div class="flex h-16 shrink-0 items-center justify-between border-b border-gray-200 px-4 dark:border-gray-700"
                 :class="minified ? 'lg:justify-center lg:px-2' : ''">
                <div class="flex items-center gap-2 overflow-hidden">
                    <a href="<?php echo e(route('superadmin.dashboard')); ?>"
                       class="flex items-center gap-2 font-display text-xl font-bold tracking-tight whitespace-nowrap text-gray-900 dark:text-white focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logoUrl): ?>
                            <img src="<?php echo e($logoUrl); ?>" alt="<?php echo e($siteName); ?> logo"
                                 class="h-8 w-8 shrink-0 rounded-lg object-contain">
                        <?php else: ?>
                            <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary-600 text-white">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/>
                                </svg>
                            </span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <span x-show="!minified" x-cloak><?php echo e($siteName); ?></span>
                    </a>
                </div>

                
                <button type="button"
                        class="flex size-8 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition-colors hover:bg-gray-200 hover:text-gray-800 lg:hidden dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white focus-visible:ring-2 focus-visible:ring-primary-500/50"
                        @click="mobileOpen = false"
                        aria-label="Close sidebar">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>

            
            <div class="flex-1 overflow-y-auto overflow-x-hidden [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-gray-300 dark:[&::-webkit-scrollbar-thumb]:bg-gray-700">
                <nav class="flex w-full flex-col p-3">
                    <ul class="flex flex-col space-y-1"
                        :class="minified ? '[&_a]:justify-center [&_a]:px-0' : ''">

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.dashboard')); ?>"
                               aria-current="<?php echo e(request()->routeIs('superadmin.dashboard') ? 'page' : 'false'); ?>"
                               title="<?php echo e($siteName); ?> Dashboard"
                               class="group relative flex items-center gap-x-3.5 rounded-lg px-3 py-2.5 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50
                                      <?php echo e(request()->routeIs('superadmin.dashboard') ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300 border-l-2 border-l-primary-600' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white border-l-2 border-l-transparent'); ?>">
                                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                                <span x-show="!minified" x-cloak>Dashboard</span>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo e(route('superadmin.analytics')); ?>"
                               aria-current="<?php echo e(request()->routeIs('superadmin.analytics') ? 'page' : 'false'); ?>"
                               title="Analytics"
                               class="group relative flex items-center gap-x-3.5 rounded-lg px-3 py-2.5 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50
                                      <?php echo e(request()->routeIs('superadmin.analytics') ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300 border-l-2 border-l-primary-600' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white border-l-2 border-l-transparent'); ?>">
                                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 16l4-4 4 4 5-5"/><path d="M7 8h1l4 4 4-4h1"/></svg>
                                <span x-show="!minified" x-cloak>Analytics</span>
                            </a>
                        </li>

                        
                        <li x-show="!minified" class="pt-4 pb-1" x-cloak>
                            <span class="block px-3 text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Platform Management</span>
                        </li>

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.tenants.index')); ?>"
                               aria-current="<?php echo e(request()->routeIs('superadmin.tenants.*') ? 'page' : 'false'); ?>"
                               title="Tenants"
                               class="group relative flex items-center gap-x-3.5 rounded-lg px-3 py-2.5 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50
                                      <?php echo e(request()->routeIs('superadmin.tenants.*') ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300 border-l-2 border-l-primary-600' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white border-l-2 border-l-transparent'); ?>">
                                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5m-4 0h4"/></svg>
                                <span x-show="!minified" x-cloak>Tenants</span>
                            </a>
                        </li>

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.users.index')); ?>"
                               aria-current="<?php echo e(request()->routeIs('superadmin.users.*') ? 'page' : 'false'); ?>"
                               title="Users"
                               class="group relative flex items-center gap-x-3.5 rounded-lg px-3 py-2.5 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50
                                      <?php echo e(request()->routeIs('superadmin.users.*') ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300 border-l-2 border-l-primary-600' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white border-l-2 border-l-transparent'); ?>">
                                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4.354a4 4 0 1 1 0 5.292M15 21H3v-1a6 6 0 0 1 12 0v1zm0 0h6v-1a6 6 0 0 0-9-5.197M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0z"/></svg>
                                <span x-show="!minified" x-cloak>Users</span>
                            </a>
                        </li>

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.tenant-types.index')); ?>"
                               aria-current="<?php echo e(request()->routeIs('superadmin.tenant-types.*') ? 'page' : 'false'); ?>"
                               title="Tenant Types"
                               class="group relative flex items-center gap-x-3.5 rounded-lg px-3 py-2.5 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50
                                      <?php echo e(request()->routeIs('superadmin.tenant-types.*') ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300 border-l-2 border-l-primary-600' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white border-l-2 border-l-transparent'); ?>">
                                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 0 1 .586 1.414V19a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/></svg>
                                <span x-show="!minified" x-cloak>Tenant Types</span>
                            </a>
                        </li>

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.roles.index')); ?>"
                               aria-current="<?php echo e(request()->routeIs('superadmin.roles.*') ? 'page' : 'false'); ?>"
                               title="Roles"
                               class="group relative flex items-center gap-x-3.5 rounded-lg px-3 py-2.5 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50
                                      <?php echo e(request()->routeIs('superadmin.roles.*') ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300 border-l-2 border-l-primary-600' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white border-l-2 border-l-transparent'); ?>">
                                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0 1 12 2.944a11.955 11.955 0 0 1-8.618 3.04A12.02 12.02 0 0 0 3 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                <span x-show="!minified" x-cloak>Roles</span>
                            </a>
                        </li>

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.events.index')); ?>"
                               aria-current="<?php echo e(request()->routeIs('superadmin.events.*') ? 'page' : 'false'); ?>"
                               title="Events"
                               class="group relative flex items-center gap-x-3.5 rounded-lg px-3 py-2.5 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50
                                      <?php echo e(request()->routeIs('superadmin.events.*') ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300 border-l-2 border-l-primary-600' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white border-l-2 border-l-transparent'); ?>">
                                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
                                <span x-show="!minified" x-cloak>Events</span>
                            </a>
                        </li>

                        
                        <li x-show="!minified" class="pt-4 pb-1" x-cloak>
                            <span class="block px-3 text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Content Management</span>
                        </li>

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.homepage.editor')); ?>"
                               aria-current="<?php echo e(request()->routeIs('superadmin.homepage.editor') ? 'page' : 'false'); ?>"
                               title="Homepage Editor"
                               class="group relative flex items-center gap-x-3.5 rounded-lg px-3 py-2.5 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50
                                      <?php echo e(request()->routeIs('superadmin.homepage.editor') ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300 border-l-2 border-l-primary-600' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white border-l-2 border-l-transparent'); ?>">
                                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                <span x-show="!minified" x-cloak>Homepage Editor</span>
                            </a>
                        </li>

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.about.editor')); ?>"
                               aria-current="<?php echo e(request()->routeIs('superadmin.about.editor') ? 'page' : 'false'); ?>"
                               title="About Page Editor"
                               class="group relative flex items-center gap-x-3.5 rounded-lg px-3 py-2.5 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50
                                      <?php echo e(request()->routeIs('superadmin.about.editor') ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300 border-l-2 border-l-primary-600' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white border-l-2 border-l-transparent'); ?>">
                                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                <span x-show="!minified" x-cloak>About Page Editor</span>
                            </a>
                        </li>

                        
                        <li>
                            <a href="<?php echo e(route('superadmin.map-markers.index')); ?>"
                               aria-current="<?php echo e(request()->routeIs('superadmin.map-markers.*') ? 'page' : 'false'); ?>"
                               title="Map Markers"
                               class="group relative flex items-center gap-x-3.5 rounded-lg px-3 py-2.5 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50
                                      <?php echo e(request()->routeIs('superadmin.map-markers.*') ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300 border-l-2 border-l-primary-600' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white border-l-2 border-l-transparent'); ?>">
                                <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.657 16.657 13.414 20.9a1.998 1.998 0 0 1-2.827 0l-4.244-4.243a8 8 0 1 1 11.314 0z"/><path d="M15 11a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/></svg>
                                <span x-show="!minified" x-cloak>Map Markers</span>
                            </a>
                        </li>

                    </ul>
                </nav>
            </div>

            
            <button type="button"
                    @click="toggleMinified()"
                    class="absolute -right-3 top-1/2 z-50 hidden size-7 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 shadow-sm transition-colors hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 lg:flex dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white"
                    :class="minified ? 'rotate-180' : ''"
                    aria-label="Toggle sidebar">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/>
                </svg>
            </button>

        </div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views/components/headers/admin/sidebar.blade.php ENDPATH**/ ?>