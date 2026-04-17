<div x-data="{ mobileOpen: false, userDropdownOpen: false }">
    <header class="flex flex-wrap md:justify-start md:flex-nowrap z-50 w-full bg-white border-b border-slate-200 shadow-sm">
        <nav class="relative max-w-[85rem] w-full mx-auto md:flex md:items-center md:justify-between md:gap-3 py-3 px-4 sm:px-6 lg:px-8">
            
            {{-- Logo & Brand --}}
            <div class="flex justify-between items-center gap-x-1">
                <a class="flex-none font-bold text-2xl text-slate-800 focus:outline-hidden focus:opacity-80 flex items-center gap-2" href="{{ route('superadmin.dashboard') }}" wire:navigate aria-label="Brand">
                    <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5"></path></svg>
                    <span>System<span class="text-blue-600">Admin</span></span>
                </a>

                {{-- Mobile Toggle (Alpine) --}}
                <button @click="mobileOpen = !mobileOpen" type="button" class="md:hidden relative size-9 flex justify-center items-center font-medium text-sm rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 focus:outline-hidden focus:bg-slate-100">
                    <svg x-show="!mobileOpen" class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" x2="21" y1="6" y2="6"/><line x1="3" x2="21" y1="12" y2="12"/><line x1="3" x2="21" y1="18" y2="18"/></svg>
                    <svg x-show="mobileOpen" class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    <span class="sr-only">Toggle navigation</span>
                </button>
            </div>

            {{-- Navigation Menu (Alpine) --}}
            <div x-show="mobileOpen" x-collapse x-cloak class="md:hidden overflow-hidden transition-all duration-300 basis-full grow">
                {{-- Mobile menu content --}}
                <div class="py-2 flex flex-col gap-1">
                    <a class="py-2 px-3 flex items-center text-sm font-medium rounded-lg hover:bg-slate-100 {{ request()->routeIs('superadmin.dashboard') ? 'bg-blue-50 text-blue-700' : 'text-slate-600' }}" href="{{ route('superadmin.dashboard') }}" wire:navigate>Dashboard</a>
                    <a class="py-2 px-3 flex items-center text-sm font-medium rounded-lg hover:bg-slate-100 {{ request()->routeIs('superadmin.tenants.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600' }}" href="{{ route('superadmin.tenants.index') }}" wire:navigate>Tenants</a>
                    <a class="py-2 px-3 flex items-center text-sm font-medium rounded-lg hover:bg-slate-100 {{ request()->routeIs('superadmin.tenant-types.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600' }}" href="{{ route('superadmin.tenant-types.index') }}" wire:navigate>Tenant Types</a>
                    <a class="py-2 px-3 flex items-center text-sm font-medium rounded-lg hover:bg-slate-100 {{ request()->routeIs('superadmin.users.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600' }}" href="{{ route('superadmin.users.index') }}" wire:navigate>Users</a>
                    <a class="py-2 px-3 flex items-center text-sm font-medium rounded-lg hover:bg-slate-100 {{ request()->routeIs('superadmin.roles.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600' }}" href="{{ route('superadmin.roles.index') }}" wire:navigate>Roles</a>
                    <a class="py-2 px-3 flex items-center text-sm font-medium rounded-lg hover:bg-slate-100 {{ request()->routeIs('superadmin.property-types.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600' }}" href="{{ route('superadmin.property-types.index') }}" wire:navigate>Property Types</a>
                    <a class="py-2 px-3 flex items-center text-sm font-medium rounded-lg hover:bg-slate-100 {{ request()->routeIs('superadmin.map-markers.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600' }}" href="{{ route('superadmin.map-markers.index') }}" wire:navigate>Map Markers</a>
                    
                    <div class="border-t border-slate-200 my-2"></div>
                    
                    @auth
                        <div class="px-3 py-2">
                            <p class="text-sm font-medium text-slate-800">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-500">{{ auth()->user()->email }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full py-2 px-3 flex items-center text-sm font-medium text-red-600 hover:bg-red-50 rounded-lg">Sign out</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="py-2 px-3 text-sm font-medium text-blue-600">Sign in</a>
                    @endauth
                </div>
            </div>

            {{-- Desktop Navigation --}}
            <div class="hidden md:flex md:items-center md:gap-1 md:ml-6 grow">
                <a class="py-2 px-3 flex items-center text-sm font-medium rounded-lg hover:bg-slate-100 {{ request()->routeIs('superadmin.dashboard') ? 'bg-blue-50 text-blue-700' : 'text-slate-600' }}" href="{{ route('superadmin.dashboard') }}" wire:navigate>
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Dashboard
                </a>
                <a class="py-2 px-3 flex items-center text-sm font-medium rounded-lg hover:bg-slate-100 {{ request()->routeIs('superadmin.tenants.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600' }}" href="{{ route('superadmin.tenants.index') }}" wire:navigate>
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    Tenants
                </a>
                <a class="py-2 px-3 flex items-center text-sm font-medium rounded-lg hover:bg-slate-100 {{ request()->routeIs('superadmin.tenant-types.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600' }}" href="{{ route('superadmin.tenant-types.index') }}" wire:navigate>
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 01.586 1.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"></path></svg>
                    Tenant Types
                </a>
                <a class="py-2 px-3 flex items-center text-sm font-medium rounded-lg hover:bg-slate-100 {{ request()->routeIs('superadmin.users.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600' }}" href="{{ route('superadmin.users.index') }}" wire:navigate>
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    Users
                </a>
                <a class="py-2 px-3 flex items-center text-sm font-medium rounded-lg hover:bg-slate-100 {{ request()->routeIs('superadmin.roles.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600' }}" href="{{ route('superadmin.roles.index') }}" wire:navigate>
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    Roles
                </a>
                <a class="py-2 px-3 flex items-center text-sm font-medium rounded-lg hover:bg-slate-100 {{ request()->routeIs('superadmin.property-types.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600' }}" href="{{ route('superadmin.property-types.index') }}" wire:navigate>
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    Property Types
                </a>
                <a class="py-2 px-3 flex items-center text-sm font-medium rounded-lg hover:bg-slate-100 {{ request()->routeIs('superadmin.map-markers.*') ? 'bg-blue-50 text-blue-700' : 'text-slate-600' }}" href="{{ route('superadmin.map-markers.index') }}" wire:navigate>
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Map Markers
                </a>
            </div>

            {{-- Divider --}}
            <div class="hidden md:block mx-2">
                <div class="w-px h-6 bg-slate-200"></div>
            </div>

            {{-- User Dropdown (Alpine) --}}
            @auth
                <div class="relative hidden md:block" x-data="{ open: false }">
                    <button @click="open = !open" type="button" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg bg-white text-slate-700 shadow-sm align-middle hover:bg-slate-50 focus:outline-hidden focus:bg-slate-100 border border-slate-200">
                        <div class="w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-xs">
                            {{ substr(auth()->user()->name ?? 'SA', 0, 1) }}
                        </div>
                        <span class="hidden sm:inline max-w-[120px] truncate">{{ auth()->user()->name ?? 'Super Admin' }}</span>
                        <svg :class="{ 'rotate-180': open }" class="size-4 text-slate-500 transition-transform" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                    </button>

                    <div x-show="open" @click.away="open = false" x-transition.opacity.duration.200ms class="absolute right-0 mt-2 min-w-60 bg-white shadow-md rounded-lg p-1 border border-slate-200 z-50">
                        <div class="py-2 px-3 border-b border-slate-200">
                            <p class="text-sm font-medium text-slate-800 truncate">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-500 truncate">{{ auth()->user()->email }}</p>
                            <span class="inline-block mt-1 px-2 py-0.5 text-xs rounded-full bg-purple-100 text-purple-800">Super Admin</span>
                        </div>
                        
                        <a class="flex items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-slate-700 hover:bg-slate-100 focus:outline-hidden focus:bg-slate-100" href="{{ route('superadmin.dashboard') }}" wire:navigate>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                            Dashboard
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-red-600 hover:bg-red-50 focus:outline-hidden focus:bg-red-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="hidden md:inline-flex py-2 px-4 items-center gap-x-2 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700">
                    Sign in
                </a>
            @endauth
        </nav>
    </header>
</div>