<div :class="minified ? 'w-13' : 'w-65'"
     id="hs-application-sidebar"
     class="hs-overlay [--body-scroll:true] lg:[--overlay-backdrop:false] [--is-layout-affect:true] [--auto-close:lg]
            hs-overlay-open:translate-x-0
            -translate-x-full transition-all duration-300 transform
            h-full
            hidden lg:block
            fixed inset-y-0 start-0 z-60
            lg:translate-x-0
            bg-white dark:bg-[#0b0f19] border-e border-gray-200 dark:border-slate-700/50"
     role="dialog" tabindex="-1" aria-label="Sidebar">
    <div class="relative flex flex-col h-full max-h-full">
        
        <!-- Header with logo and minify toggle -->
        <div class="py-2.5 px-4 flex justify-between items-center gap-x-2">
            <div class="-ms-2 flex items-center gap-x-1" x-show="!minified">
                <a class="flex items-center gap-2 font-bold text-xl text-gray-900 dark:text-white" href="{{ route('superadmin.dashboard') }}" wire:navigate aria-label="Brand">
                    <svg class="w-7 h-7 text-blue-600 dark:text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5"/>
                    </svg>
                    <span>System<span class="text-blue-600 dark:text-blue-400">Admin</span></span>
                </a>
            </div>

            <!-- Desktop Minify Toggle -->
            <button type="button" @click="minified = !minified"
                    class="hidden lg:flex justify-center items-center flex-none gap-x-3 size-9 text-sm text-gray-500 dark:text-slate-400 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 focus:outline-none"
                    aria-label="Toggle sidebar">
                <svg x-show="!minified" class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M15 3v18"/><path d="m10 15-3-3 3-3"/></svg>
                <svg x-show="minified" class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M15 3v18"/><path d="m8 9 3 3-3 3"/></svg>
            </button>

            <!-- Mobile Close Button -->
            <button type="button"
                    class="flex lg:hidden justify-center items-center size-8 rounded-full bg-gray-100 dark:bg-slate-800 text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-white"
                    data-hs-overlay="#hs-application-sidebar"
                    aria-label="Close sidebar">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>

        <!-- Navigation -->
        <div class="h-full overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-thumb]:rounded-none [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-gray-200 dark:[&::-webkit-scrollbar-thumb]:bg-slate-700">
            <nav class="p-3 w-full flex flex-col flex-wrap">
                <ul class="flex flex-col space-y-1">
                    {{-- Dashboard --}}
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors {{ request()->routeIs('superadmin.dashboard') ? 'bg-blue-50 dark:bg-blue-600/20 text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white' }}" href="{{ route('superadmin.dashboard') }}" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            <span x-show="!minified">Dashboard</span>
                        </a>
                    </li>

                    {{-- Analytics (NEW) --}}
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors {{ request()->routeIs('superadmin.analytics') ? 'bg-blue-50 dark:bg-blue-600/20 text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white' }}" href="{{ route('superadmin.analytics') }}" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 16l4-4 4 4 5-5"/><path d="M7 8h1l4 4 4-4h1"/></svg>
                            <span x-show="!minified">Analytics</span>
                        </a>
                    </li>

                    {{-- Platform Management divider --}}
                    <li class="pt-3" x-show="!minified">
                        <span class="px-3 text-[10px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-widest">Platform Management</span>
                    </li>

                    {{-- The rest remains unchanged --}}
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors {{ request()->routeIs('superadmin.tenants.*') ? 'bg-blue-50 dark:bg-blue-600/20 text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white' }}" href="{{ route('superadmin.tenants.index') }}" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5m-4 0h4"/></svg>
                            <span x-show="!minified">Tenants</span>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors {{ request()->routeIs('superadmin.tenant-types.*') ? 'bg-blue-50 dark:bg-blue-600/20 text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white' }}" href="{{ route('superadmin.tenant-types.index') }}" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 0 1 .586 1.414V19a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/></svg>
                            <span x-show="!minified">Tenant Types</span>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors {{ request()->routeIs('superadmin.users.*') ? 'bg-blue-50 dark:bg-blue-600/20 text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white' }}" href="{{ route('superadmin.users.index') }}" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4.354a4 4 0 1 1 0 5.292M15 21H3v-1a6 6 0 0 1 12 0v1zm0 0h6v-1a6 6 0 0 0-9-5.197M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0z"/></svg>
                            <span x-show="!minified">Users</span>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors {{ request()->routeIs('superadmin.roles.*') ? 'bg-blue-50 dark:bg-blue-600/20 text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white' }}" href="{{ route('superadmin.roles.index') }}" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0 1 12 2.944a11.955 11.955 0 0 1-8.618 3.04A12.02 12.02 0 0 0 3 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <span x-show="!minified">Roles</span>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-x-3.5 py-2 px-3 text-sm rounded-lg transition-colors {{ request()->routeIs('superadmin.map-markers.*') ? 'bg-blue-50 dark:bg-blue-600/20 text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white' }}" href="{{ route('superadmin.map-markers.index') }}" wire:navigate>
                            <svg class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.657 16.657 13.414 20.9a1.998 1.998 0 0 1-2.827 0l-4.244-4.243a8 8 0 1 1 11.314 0z"/><path d="M15 11a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/></svg>
                            <span x-show="!minified">Map Markers</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>

        <!-- Footer -->
        <div class="border-t border-gray-200 dark:border-slate-700/50 p-4">
            @auth
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-xs shrink-0">
                        {{ substr(auth()->user()->name ?? 'SA', 0, 1) }}
                    </div>
                    <div class="flex-1 min-w-0" x-show="!minified">
                        <p class="text-sm font-medium text-gray-800 dark:text-slate-300 truncate">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-gray-500 dark:text-slate-500 truncate">{{ auth()->user()->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="shrink-0" x-show="!minified">
                        @csrf
                        <button type="submit" class="p-1.5 text-gray-400 dark:text-slate-500 hover:text-red-500 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </button>
                    </form>
                </div>
            @else
                <a href="{{ route('login') }}" class="flex items-center justify-center gap-2 w-full py-2 px-3 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700">Sign in</a>
            @endauth
        </div>
    </div>
</div>