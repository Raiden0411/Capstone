{{-- resources/views/components/headers/tenant/tenant-header.blade.php --}}
@php
    $tenant = auth()->user()?->tenant;

    $tenantLogoUrl = null;
    if ($tenant && $tenant->logo) {
        $fullPath = public_path('storage/' . $tenant->logo);
        $tenantLogoUrl = asset('storage/' . $tenant->logo);
        if (file_exists($fullPath)) {
            $tenantLogoUrl .= '?v=' . filemtime($fullPath);
        } else {
            $tenantLogoUrl .= '?v=' . time();
        }
    }
@endphp

<header
    x-data="{
        minified: localStorage.getItem('tenant_sidebar_minified') === '1',
        dark: localStorage.getItem('hs_theme') === 'dark',
        toggleDark() {
            this.dark = !this.dark;
            localStorage.setItem('hs_theme', this.dark ? 'dark' : 'light');
            document.documentElement.classList.toggle('dark', this.dark);
        }
    }"
    x-init="document.documentElement.classList.toggle('dark', dark)"
    @sidebar-minified-tenant.window="minified = $event.detail"
    :class="minified ? 'lg:ps-[3.25rem]' : 'lg:ps-64'"
    class="sticky top-0 inset-x-0 flex flex-wrap md:justify-start md:flex-nowrap z-30 w-full bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 text-sm py-2.5 transition-all duration-300"
>
  <nav class="px-4 sm:px-6 flex basis-full items-center w-full mx-auto justify-between gap-2">

    {{-- Left: Mobile sidebar toggle --}}
    <div class="flex items-center gap-2 lg:hidden">
      <button type="button"
              class="flex items-center justify-center size-8 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50"
              @click="$dispatch('toggle-tenant-sidebar')"
              aria-label="Toggle navigation">
        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
    </div>

    {{-- Center: Tenant name --}}
    <div class="hidden md:flex flex-1 items-center gap-2 px-2">
        @if($tenant)
            <span class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $tenant->name }}</span>
        @endif
    </div>

    {{-- Right: Actions --}}
    <div class="flex items-center gap-2 ms-auto">

      {{-- View Public Site --}}
      @if($tenant)
        <a href="{{ route('business.offerings', $tenant->slug) }}" target="_blank" rel="noopener"
           class="hidden sm:inline-flex items-center gap-2 py-2 px-3 rounded-lg text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-xs font-medium active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50">
          <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
          View Site
        </a>
      @endif

      {{-- Dark mode toggle --}}
      <button type="button"
              @click="toggleDark()"
              class="flex items-center justify-center size-9 rounded-lg text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none transition-colors active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50"
              aria-label="Toggle dark mode">
        <svg x-show="dark" class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
        <svg x-show="!dark" class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
      </button>

      @auth
        {{-- Profile Dropdown --}}
        <div class="relative"
             x-data="{ open: false }"
             @click.outside="open = false"
             @keydown.escape.window="open = false">

          <button type="button"
                  @click="open = !open"
                  class="flex items-center gap-2 py-1.5 px-2 rounded-lg text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors focus:outline-none active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50"
                  aria-expanded="open"
                  aria-haspopup="true">
            @if($tenantLogoUrl)
              <img src="{{ $tenantLogoUrl }}"
                   alt="{{ $tenant->name }}"
                   class="w-6 h-6 rounded-full object-cover shrink-0">
            @else
              <div class="w-6 h-6 rounded-full bg-primary-600 text-white flex items-center justify-center font-bold text-xs shrink-0">
                {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
              </div>
            @endif
            <span class="hidden sm:inline max-w-[120px] truncate text-sm font-medium">{{ auth()->user()->name }}</span>
            <svg class="hidden sm:block w-3 h-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </button>

          {{-- Dropdown Panel --}}
          <div x-cloak
               x-show="open"
               x-transition:enter="transition ease-out duration-150"
               x-transition:enter-start="opacity-0 scale-95 translate-y-1"
               x-transition:enter-end="opacity-100 scale-100 translate-y-0"
               x-transition:leave="transition ease-in duration-100"
               x-transition:leave-start="opacity-100 scale-100 translate-y-0"
               x-transition:leave-end="opacity-0 scale-95 translate-y-1"
               class="absolute right-0 mt-2 w-64 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-2xl z-50 overflow-hidden">

            {{-- User Info --}}
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
              <div class="flex items-center gap-3">
                @if($tenantLogoUrl)
                  <img src="{{ $tenantLogoUrl }}"
                       alt="{{ $tenant->name }}"
                       class="w-10 h-10 rounded-full object-cover shrink-0">
                @else
                  <div class="w-10 h-10 rounded-full bg-primary-600 text-white flex items-center justify-center font-bold text-sm shrink-0">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                  </div>
                @endif
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ auth()->user()->name }}</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ auth()->user()->email }}</p>
                  @php $role = auth()->user()->roles->first(); @endphp
                  @if($role)
                    <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-300">
                      {{ ucwords(str_replace(['-', '_'], ' ', $role->name)) }}
                    </span>
                  @endif
                </div>
              </div>
            </div>

            {{-- Menu Items --}}
            <div class="p-1.5 space-y-0.5">
              @if($tenant)
                <a href="{{ route('tenant.settings.index') }}" wire:navigate
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white transition-colors active:scale-[0.98] focus-visible:ring-2 focus-visible:ring-primary-500/50"
                   @click="open = false">
                  <svg class="w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                  Business Settings
                </a>
              @endif

              <form method="POST" action="{{ route('logout') }}" class="block">
                @csrf
                <button type="submit"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors active:scale-[0.98] focus-visible:ring-2 focus-visible:ring-red-500/50">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                  Sign Out
                </button>
              </form>
            </div>
          </div>
        </div>
      @else
        <a href="{{ route('login') }}" class="py-2 px-4 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg bg-primary-600 text-white hover:bg-primary-700 transition-colors active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50">Sign in</a>
      @endauth
    </div>
  </nav>
</header>