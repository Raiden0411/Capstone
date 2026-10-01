{{-- resources/views/components/headers/tenant/sidebar.blade.php --}}
@php
    $user       = auth()->user();
    $tenant     = $user?->tenant;
    $isAdmin    = $user?->hasAnyRole(['admin', 'super-admin']) ?? false;
    $isEmployee = $user && ! $user->hasRole('admin') && $user->tenant_id;
    $dashboardRoute = $isEmployee ? route('tenant.employee.dashboard') : route('tenant.dashboard');

    $userPermissions = [];

    if ($user instanceof \App\Models\User && $user->tenant_id && ! $user->hasRole('super-admin')) {
        $modelType = \App\Models\User::class;
        $userId    = $user->getKey();
        $teamId    = $user->tenant_id;

        $direct = \Illuminate\Support\Facades\DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_id', $userId)
            ->where('model_has_permissions.model_type', $modelType)
            ->where('model_has_permissions.team_id', $teamId)
            ->where('permissions.guard_name', 'web')
            ->pluck('permissions.name');

        $viaRoles = \Illuminate\Support\Facades\DB::table('model_has_roles')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.model_type', $modelType)
            ->where('model_has_roles.team_id', $teamId)
            ->where('permissions.guard_name', 'web')
            ->pluck('permissions.name');

        $userPermissions = $direct->merge($viaRoles)->unique()->values()->all();
    }

    $can = fn (string $permission) => in_array($permission, $userPermissions, true);

    $tenantLogoUrl = null;
    if ($tenant && $tenant->logo) {
        $fullPath = public_path('storage/' . ltrim($tenant->logo, '/'));
        if (file_exists($fullPath)) {
            $tenantLogoUrl = '/storage/' . ltrim($tenant->logo, '/') . '?v=' . filemtime($fullPath);
        }
    }

    $__businesses = $user instanceof \App\Models\User
        ? $user->businesses()
            ->wherePivotIn('role', [
                \App\Models\BusinessMembership::ROLE_OWNER,
                \App\Models\BusinessMembership::ROLE_ADMIN,
            ])
            ->orderBy('tenants.name')
            ->get()
        : collect();

    $__showSwitcher = $__businesses->count() > 1;

    $canAddBusiness = $user instanceof \App\Models\User
        && $user->isBusinessOwner()
        && $user->canRegisterBusiness();

    $__showBusinessSection = $__showSwitcher || $canAddBusiness;

    $showOverview   = true;
    $showOperations = $can('view bookings') || $can('view events');
    $showInventory  = $can('view properties') || $can('view services');
    $showFinance    = $can('view payments');
    $showTeam       = $can('view employees') || $isAdmin;

    $navLinkClass = 'group flex items-center gap-x-3 py-2 px-3 text-sm rounded-lg '
        . 'min-h-[44px] '
        . 'transition-colors duration-150 active:scale-[0.98] '
        . '[touch-action:manipulation] [-webkit-tap-highlight-color:transparent] '
        . 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 '
        . 'focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900';

    $navLinkActive   = 'bg-primary-50 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300 font-semibold';
    $navLinkInactive = 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800/60 hover:text-gray-900 dark:hover:text-white';

    $navIconClass = 'shrink-0 size-4 transition-transform duration-200 group-hover:scale-110 group-active:scale-95';

    $sectionLabelClass = 'text-[10px] font-bold uppercase tracking-[0.14em] text-gray-400 dark:text-gray-500';
    $sectionRuleClass  = 'h-px flex-1 bg-gradient-to-r from-gray-200 to-transparent dark:from-gray-700';
@endphp

<div
    x-data="{
        mobileOpen: false,
        minified: localStorage.getItem('tenant_sidebar_minified') === '1',
        businessesExpanded: localStorage.getItem('tenant_sidebar_businesses_expanded') !== '0',
        toggleMinified() {
            this.minified = ! this.minified;
            try {
                localStorage.setItem('tenant_sidebar_minified', this.minified ? '1' : '0');
            } catch (e) {}
            window.dispatchEvent(new CustomEvent('sidebar-minified-tenant', { detail: this.minified }));
        },
        toggleBusinesses() {
            this.businessesExpanded = ! this.businessesExpanded;
            try {
                localStorage.setItem('tenant_sidebar_businesses_expanded', this.businessesExpanded ? '1' : '0');
            } catch (e) {}
        }
    }"
    @toggle-tenant-sidebar.window="mobileOpen = ! mobileOpen"
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
        class="fixed inset-y-0 start-0 z-50 w-64
               bg-white dark:bg-gray-900 border-e border-gray-200 dark:border-gray-700
               transition-all duration-300"
        aria-label="Main navigation"
    >
        <div class="relative flex flex-col h-full max-h-full">

            <div class="flex min-h-16 md:min-h-20 items-center justify-between gap-2 px-4
                        pt-[env(safe-area-inset-top)]
                        border-b border-gray-200 dark:border-gray-700 shrink-0"
                 :class="minified ? 'lg:justify-center lg:px-2' : ''">

                <a href="{{ $dashboardRoute }}" wire:navigate
                   title="{{ $tenant?->name ?? 'Victorias Tourism' }}"
                   class="group flex items-center gap-3 -mx-2 px-2 py-2 rounded-xl
                          min-h-[48px] min-w-0
                          transition-all duration-200
                          hover:bg-gray-100/70 dark:hover:bg-white/[0.04]
                          active:scale-[0.98]
                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">

                    <span class="relative shrink-0">
                        <span class="pointer-events-none absolute -inset-1 rounded-2xl
                                     bg-gradient-to-br from-primary-500/40 via-primary-500/10 to-amber-500/25
                                     opacity-0 blur-md transition-opacity duration-300
                                     group-hover:opacity-100 group-focus-visible:opacity-100"
                              aria-hidden="true"></span>

                        @if($tenantLogoUrl)
                            <img src="{{ $tenantLogoUrl }}"
                                 alt="{{ $tenant->name }}"
                                 width="40" height="40"
                                 loading="lazy" decoding="async"
                                 class="relative h-10 w-10 rounded-full object-contain
                                        ring-1 ring-black/5 shadow-sm
                                        dark:ring-white/10">
                        @else
                            <span class="relative inline-flex h-10 w-10 items-center justify-center rounded-full
                                         bg-gradient-to-br from-primary-500 to-primary-700 text-white
                                         ring-1 ring-black/5 shadow-sm dark:ring-white/10">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/>
                                </svg>
                            </span>
                        @endif
                    </span>

                    <span x-cloak :class="minified ? 'lg:hidden' : ''"
                          class="flex min-w-0 flex-col gap-0.5 text-start">

                        <span class="flex items-center gap-1.5">
                            <span class="block h-px w-3 bg-amber-500" aria-hidden="true"></span>
                            <span class="block text-[9px] font-bold uppercase tracking-[0.22em] leading-none
                                         text-amber-600 dark:text-amber-400">
                                Business
                            </span>
                        </span>

                        <span class="block text-sm font-bold leading-tight tracking-tight
                                     text-gray-900 dark:text-white
                                     line-clamp-2 text-balance">
                            {{ $tenant?->name ?? 'Victorias Tourism' }}
                        </span>
                    </span>
                </a>

                <button type="button"
                        class="flex lg:hidden justify-center items-center size-11 shrink-0 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-white hover:bg-gray-200 dark:hover:bg-gray-700
                               transition-all duration-200 active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                        @click="mobileOpen = false"
                        aria-label="Close sidebar">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                    </svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto overflow-x-hidden [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-gray-300 dark:[&::-webkit-scrollbar-thumb]:bg-gray-700">
                <nav class="p-3 w-full flex flex-col" aria-label="Tenant sections">
                    <ul class="flex flex-col space-y-1"
                        :class="minified ? '[&_a]:justify-center [&_a]:px-0 [&_button]:justify-center [&_button]:px-0' : ''">

                        @if($__showBusinessSection)

                            <li x-cloak :class="minified ? 'lg:hidden' : ''">
                                <button type="button"
                                        @click="toggleBusinesses()"
                                        :aria-expanded="businessesExpanded.toString()"
                                        aria-controls="sidebar-businesses-panel"
                                        class="group w-full flex items-center gap-2 px-3 pt-0 pb-1 text-start
                                               min-h-[32px] rounded
                                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                    <span class="{{ $sectionLabelClass }} transition-colors group-hover:text-gray-600 dark:group-hover:text-gray-300">
                                        Your businesses
                                    </span>
                                    <span class="inline-flex items-center justify-center min-w-[16px] h-4 px-1 rounded-full
                                                 text-[9px] font-bold tabular-nums
                                                 bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-300">
                                        {{ $__businesses->count() }}
                                    </span>
                                    <span class="{{ $sectionRuleClass }}" aria-hidden="true"></span>
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                         class="shrink-0 size-3 text-gray-400 dark:text-gray-500
                                                transition-transform duration-200
                                                group-hover:text-gray-600 dark:group-hover:text-gray-300"
                                         :class="businessesExpanded ? 'rotate-180' : ''"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                         aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                            </li>

                            <li id="sidebar-businesses-panel"
                                :class="(businessesExpanded || minified) ? '' : 'hidden'">
                                <ul class="flex flex-col space-y-1">
                                    @foreach($__businesses as $__biz)
                                        @php $__isCurrent = (int) $__biz->id === (int) $user->tenant_id; @endphp

                                        @if($__isCurrent)
                                            <li>
                                                <div aria-current="true"
                                                     title="{{ $__biz->name }}"
                                                     class="{{ $navLinkClass }} {{ $navLinkActive }} cursor-default">
                                                    @if($__biz->logo)
                                                        <img src="/storage/{{ ltrim($__biz->logo, '/') }}"
                                                             alt=""
                                                             width="16" height="16"
                                                             loading="lazy" decoding="async"
                                                             class="shrink-0 size-4 rounded-full object-cover ring-1 ring-black/5 dark:ring-white/10">
                                                    @else
                                                        <span class="shrink-0 inline-flex items-center justify-center size-4 rounded-full
                                                                     bg-primary-600 text-white text-[9px] font-bold">
                                                            {{ strtoupper(substr($__biz->name, 0, 1)) }}
                                                        </span>
                                                    @endif
                                                    <span x-cloak :class="minified ? 'lg:hidden' : ''" class="flex-1 truncate">
                                                        {{ $__biz->name }}
                                                    </span>
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="shrink-0 size-3.5"
                                                         x-cloak :class="minified ? 'lg:hidden' : ''"
                                                         fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </div>
                                            </li>
                                        @else
                                            <li>
                                                <form method="POST" action="{{ route('tenant.businesses.switch', $__biz->id) }}">
                                                    @csrf
                                                    <button type="submit"
                                                            title="Switch to {{ $__biz->name }}"
                                                            class="w-full text-start {{ $navLinkClass }} {{ $navLinkInactive }}">
                                                        @if($__biz->logo)
                                                            <img src="/storage/{{ ltrim($__biz->logo, '/') }}"
                                                                 alt=""
                                                                 width="16" height="16"
                                                                 loading="lazy" decoding="async"
                                                                 class="shrink-0 size-4 rounded-full object-cover ring-1 ring-black/5 dark:ring-white/10">
                                                        @else
                                                            <span class="shrink-0 inline-flex items-center justify-center size-4 rounded-full
                                                                         bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-[9px] font-bold">
                                                                {{ strtoupper(substr($__biz->name, 0, 1)) }}
                                                            </span>
                                                        @endif
                                                        <span x-cloak :class="minified ? 'lg:hidden' : ''" class="flex-1 truncate">
                                                            {{ $__biz->name }}
                                                        </span>
                                                    </button>
                                                </form>
                                            </li>
                                        @endif
                                    @endforeach

                                    @if($__showSwitcher)
                                        <li>
                                            <a href="{{ route('tenant.businesses.index') }}" wire:navigate
                                               aria-current="{{ request()->routeIs('tenant.businesses.*') ? 'page' : 'false' }}"
                                               title="Manage all businesses"
                                               class="{{ $navLinkClass }} {{ request()->routeIs('tenant.businesses.*') ? $navLinkActive : $navLinkInactive }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="{{ $navIconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path d="M3 21h18M3 10h18M5 10v8M9 10v8M15 10v8M19 10v8M12 2l9 5H3z"/>
                                                </svg>
                                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Manage all businesses</span>
                                            </a>
                                        </li>
                                    @endif

                                    @if($canAddBusiness)
                                        <li>
                                            <form method="POST" action="{{ route('register_business.start') }}">
                                                @csrf
                                                <button type="submit"
                                                        title="Add another business"
                                                        class="w-full text-start {{ $navLinkClass }} {{ $navLinkInactive }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="{{ $navIconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                                    </svg>
                                                    <span x-cloak :class="minified ? 'lg:hidden' : ''">Add another business</span>
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                </ul>
                            </li>
                        @endif

                        <li class="flex items-center gap-3 px-3 pb-1 pt-4"
                            x-cloak :class="minified ? 'lg:hidden' : ''">
                            <span class="{{ $sectionLabelClass }}">Overview</span>
                            <span class="{{ $sectionRuleClass }}" aria-hidden="true"></span>
                        </li>

                        <li>
                            <a href="{{ $dashboardRoute }}" wire:navigate
                               aria-current="{{ request()->routeIs('tenant.dashboard') || request()->routeIs('tenant.employee.dashboard') ? 'page' : 'false' }}"
                               title="Dashboard"
                               class="{{ $navLinkClass }} {{ request()->routeIs('tenant.dashboard') || request()->routeIs('tenant.employee.dashboard') ? $navLinkActive : $navLinkInactive }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="{{ $navIconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Dashboard</span>
                            </a>
                        </li>

                        @if($can('view analytics'))
                        <li>
                            <a href="{{ route('tenant.analytics.index') }}" wire:navigate
                               aria-current="{{ request()->routeIs('tenant.analytics.*') ? 'page' : 'false' }}"
                               title="Analytics"
                               class="{{ $navLinkClass }} {{ request()->routeIs('tenant.analytics.*') ? $navLinkActive : $navLinkInactive }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="{{ $navIconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M3 3v18h18"/><path d="M7 16l4-4 4 4 5-5"/><path d="M7 8h1l4 4 4-4h1"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Analytics</span>
                            </a>
                        </li>
                        @endif

                        @if($showOperations)
                        <li class="flex items-center gap-3 px-3 pb-1 pt-4"
                            x-cloak :class="minified ? 'lg:hidden' : ''">
                            <span class="{{ $sectionLabelClass }}">Operations</span>
                            <span class="{{ $sectionRuleClass }}" aria-hidden="true"></span>
                        </li>
                        @endif

                        @if($can('view bookings'))
                        <li>
                            <a href="{{ route('tenant.bookings.index') }}" wire:navigate
                               aria-current="{{ request()->routeIs('tenant.bookings.*') && ! request()->routeIs('tenant.bookings.history') ? 'page' : 'false' }}"
                               title="Active Bookings"
                               class="{{ $navLinkClass }} {{ request()->routeIs('tenant.bookings.*') && ! request()->routeIs('tenant.bookings.history') ? $navLinkActive : $navLinkInactive }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="{{ $navIconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Active Bookings</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('tenant.bookings.history') }}" wire:navigate
                               aria-current="{{ request()->routeIs('tenant.bookings.history') ? 'page' : 'false' }}"
                               title="Booking History"
                               class="{{ $navLinkClass }} {{ request()->routeIs('tenant.bookings.history') ? $navLinkActive : $navLinkInactive }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="{{ $navIconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Booking History</span>
                            </a>
                        </li>
                        @endif

                        @if($can('view events'))
                        <li>
                            <a href="{{ route('tenant.events.index') }}" wire:navigate
                               aria-current="{{ request()->routeIs('tenant.events.*') ? 'page' : 'false' }}"
                               title="Events"
                               class="{{ $navLinkClass }} {{ request()->routeIs('tenant.events.*') ? $navLinkActive : $navLinkInactive }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="{{ $navIconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Events</span>
                            </a>
                        </li>
                        @endif

                        @if($showInventory)
                        <li class="flex items-center gap-3 px-3 pb-1 pt-4"
                            x-cloak :class="minified ? 'lg:hidden' : ''">
                            <span class="{{ $sectionLabelClass }}">Inventory</span>
                            <span class="{{ $sectionRuleClass }}" aria-hidden="true"></span>
                        </li>
                        @endif

                        @if($can('view properties'))
                        <li>
                            <a href="{{ route('tenant.properties.index') }}" wire:navigate
                               aria-current="{{ request()->routeIs('tenant.properties.*') ? 'page' : 'false' }}"
                               title="Properties"
                               class="{{ $navLinkClass }} {{ request()->routeIs('tenant.properties.*') ? $navLinkActive : $navLinkInactive }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="{{ $navIconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Properties</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('tenant.property-types.index') }}" wire:navigate
                               aria-current="{{ request()->routeIs('tenant.property-types.*') ? 'page' : 'false' }}"
                               title="Property Types"
                               class="{{ $navLinkClass }} {{ request()->routeIs('tenant.property-types.*') ? $navLinkActive : $navLinkInactive }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="{{ $navIconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 01.586 1.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Property Types</span>
                            </a>
                        </li>
                        @endif

                        @if($can('view services'))
                        <li>
                            <a href="{{ route('tenant.services.index') }}" wire:navigate
                               aria-current="{{ request()->routeIs('tenant.services.*') ? 'page' : 'false' }}"
                               title="Services"
                               class="{{ $navLinkClass }} {{ request()->routeIs('tenant.services.*') ? $navLinkActive : $navLinkInactive }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="{{ $navIconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Services</span>
                            </a>
                        </li>
                        @endif

                        @if($showFinance)
                        <li class="flex items-center gap-3 px-3 pb-1 pt-4"
                            x-cloak :class="minified ? 'lg:hidden' : ''">
                            <span class="{{ $sectionLabelClass }}">Finance</span>
                            <span class="{{ $sectionRuleClass }}" aria-hidden="true"></span>
                        </li>
                        @endif

                        @if($can('view payments'))
                        <li>
                            <a href="{{ route('tenant.payments.index') }}" wire:navigate
                               aria-current="{{ request()->routeIs('tenant.payments.*') ? 'page' : 'false' }}"
                               title="Payments"
                               class="{{ $navLinkClass }} {{ request()->routeIs('tenant.payments.*') ? $navLinkActive : $navLinkInactive }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="{{ $navIconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Payments</span>
                            </a>
                        </li>
                        @endif

                        @if($showTeam)
                        <li class="flex items-center gap-3 px-3 pb-1 pt-4"
                            x-cloak :class="minified ? 'lg:hidden' : ''">
                            <span class="{{ $sectionLabelClass }}">Team</span>
                            <span class="{{ $sectionRuleClass }}" aria-hidden="true"></span>
                        </li>
                        @endif

                        @if($can('view employees'))
                        <li>
                            <a href="{{ route('tenant.employees.index') }}" wire:navigate
                               aria-current="{{ request()->routeIs('tenant.employees.*') ? 'page' : 'false' }}"
                               title="Employees"
                               class="{{ $navLinkClass }} {{ request()->routeIs('tenant.employees.*') ? $navLinkActive : $navLinkInactive }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="{{ $navIconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Employees</span>
                            </a>
                        </li>
                        @endif

                        @if($isAdmin)
                        <li>
                            <a href="{{ route('tenant.roles.index') }}" wire:navigate
                               aria-current="{{ request()->routeIs('tenant.roles.*') ? 'page' : 'false' }}"
                               title="Roles"
                               class="{{ $navLinkClass }} {{ request()->routeIs('tenant.roles.*') ? $navLinkActive : $navLinkInactive }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="{{ $navIconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Roles</span>
                            </a>
                        </li>
                        @endif

                        <li class="flex items-center gap-3 px-3 pb-1 pt-4"
                            x-cloak :class="minified ? 'lg:hidden' : ''">
                            <span class="{{ $sectionLabelClass }}">Account</span>
                            <span class="{{ $sectionRuleClass }}" aria-hidden="true"></span>
                        </li>

                        <li>
                            <a href="{{ route('tenant.account.index') }}" wire:navigate
                               aria-current="{{ request()->routeIs('tenant.account.*') ? 'page' : 'false' }}"
                               title="My Account"
                               class="{{ $navLinkClass }} {{ request()->routeIs('tenant.account.*') ? $navLinkActive : $navLinkInactive }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="{{ $navIconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">My Account</span>
                            </a>
                        </li>

                        @if($isAdmin)
                        <li>
                            <a href="{{ route('tenant.settings.index') }}" wire:navigate
                               aria-current="{{ request()->routeIs('tenant.settings.*') ? 'page' : 'false' }}"
                               title="Business Settings"
                               class="{{ $navLinkClass }} {{ request()->routeIs('tenant.settings.*') ? $navLinkActive : $navLinkInactive }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="{{ $navIconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Business Settings</span>
                            </a>
                        </li>

                        <li>
                            <a href="{{ route('tenant.gallery.index') }}" wire:navigate
                               aria-current="{{ request()->routeIs('tenant.gallery.*') ? 'page' : 'false' }}"
                               title="Photo Gallery"
                               class="{{ $navLinkClass }} {{ request()->routeIs('tenant.gallery.*') ? $navLinkActive : $navLinkInactive }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="{{ $navIconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Photo Gallery</span>
                            </a>
                        </li>

                        <li>
                            <a href="{{ route('tenant.documents.index') }}" wire:navigate
                               aria-current="{{ request()->routeIs('tenant.documents.*') ? 'page' : 'false' }}"
                               title="Documents"
                               class="{{ $navLinkClass }} {{ request()->routeIs('tenant.documents.*') ? $navLinkActive : $navLinkInactive }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="{{ $navIconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">Documents</span>
                            </a>
                        </li>
                        @endif

                        @if($tenant)
                        <li>
                            <a href="{{ route('tenant.show', $tenant->slug) }}"
                               target="_blank" rel="noopener noreferrer"
                               title="View Public Listing"
                               class="{{ $navLinkClass }} {{ $navLinkInactive }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="{{ $navIconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                                <span x-cloak :class="minified ? 'lg:hidden' : ''">View Public Listing</span>
                            </a>
                        </li>
                        @endif

                    </ul>
                </nav>
            </div>

            <button type="button"
                    @click="toggleMinified()"
                    class="hidden lg:flex absolute top-1/2 -right-3 z-50 size-7 -translate-y-1/2 items-center justify-center rounded-full
                           border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800
                           text-gray-500 dark:text-gray-300
                           shadow-sm hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white
                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                           transition-all duration-200 active:scale-95"
                    :class="minified ? 'rotate-180' : ''"
                    aria-label="Toggle sidebar">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/>
                </svg>
            </button>

        </div>
    </aside>
</div>