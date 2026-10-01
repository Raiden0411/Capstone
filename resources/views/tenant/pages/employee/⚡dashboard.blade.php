{{-- resources/views/tenant/pages/employee/⚡dashboard.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Models\Employee;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Service;
use App\Scopes\TenantScope;
use App\Traits\ChecksTenantPermissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

new
#[Layout('tenant.layouts.app')]
#[Title('Dashboard')]
class extends Component {
    use ChecksTenantPermissions;

    public function mount(): void
    {
        $this->authorizeDashboard();
    }

    public function hydrate(): void
    {
        $this->authorizeDashboard();
    }

    protected function authorizeDashboard(): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login', navigate: true);
            return;
        }

        abort_unless(Auth::user()->tenant_id, 403, 'Your account is not linked to a business.');
    }

    #[Computed]
    public function employee(): ?Employee
    {
        return Auth::user()?->employee;
    }

    #[Computed]
    public function jobTitle(): string
    {
        $role = $this->employee?->role;

        return $role ? Str::headline($role) : 'Team Member';
    }

    #[Computed]
    public function tenantName(): string
    {
        return Auth::user()?->tenant?->name ?? config('app.name');
    }

    #[Computed]
    public function todayArrivals(): int
    {
        return Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('check_in', today()->toDateString())
            ->whereNotIn('status', [Booking::STATUS_CANCELLED])
            ->count();
    }

    #[Computed]
    public function todayDepartures(): int
    {
        return Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('check_out', today()->toDateString())
            ->whereNotIn('status', [Booking::STATUS_CANCELLED])
            ->count();
    }

    #[Computed]
    public function pendingBookings(): int
    {
        return Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('status', Booking::STATUS_PENDING)
            ->count();
    }

    #[Computed]
    public function upcomingBookings(): int
    {
        return Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_RESERVED])
            ->where('check_in', '>=', today())
            ->count();
    }

    #[Computed]
    public function recentBookings()
    {
        return Booking::withoutGlobalScope(TenantScope::class)
            ->with([
                'user:id,name,email',
                'items'          => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                'items.property' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->select('id', 'name'),
            ])
            ->where('tenant_id', Auth::user()->tenant_id)
            ->latest('created_at')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function pendingPayments(): int
    {
        return Payment::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('payment_status', 'pending')
            ->count();
    }

    #[Computed]
    public function availableProperties(): int
    {
        return Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('is_active', true)
            ->where('status', 'available')
            ->count();
    }

    #[Computed]
    public function totalProperties(): int
    {
        return Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('is_active', true)
            ->count();
    }

    #[Computed]
    public function activeServices(): int
    {
        return Service::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('is_active', true)
            ->count();
    }

    #[Computed]
    public function activeEvents(): int
    {
        return Event::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->count();
    }

    #[Computed]
    public function teamMembers(): int
    {
        return Employee::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('is_active', true)
            ->count();
    }

    #[Computed]
    public function statTiles(): array
    {
        $tiles = [];

        if ($this->tenantCan('view bookings')) {
            $tiles[] = ['key' => 'arrivals',   'label' => "Today's Arrivals",   'value' => $this->todayArrivals,   'suffix' => '', 'icon' => 'inbox-check',  'href' => route('tenant.bookings.index'), 'color' => 'emerald'];
            $tiles[] = ['key' => 'departures', 'label' => "Today's Departures", 'value' => $this->todayDepartures, 'suffix' => '', 'icon' => 'arrow-up-right','href' => route('tenant.bookings.index'), 'color' => 'rose'];
            $tiles[] = ['key' => 'pending',    'label' => 'Pending Bookings',   'value' => $this->pendingBookings, 'suffix' => '', 'icon' => 'clock',        'href' => route('tenant.bookings.index'), 'color' => 'amber'];
            $tiles[] = ['key' => 'upcoming',   'label' => 'Upcoming',           'value' => $this->upcomingBookings,'suffix' => '', 'icon' => 'calendar',     'href' => route('tenant.bookings.index'), 'color' => 'blue'];
        }

        if ($this->tenantCan('view payments')) {
            $tiles[] = ['key' => 'pending-payments', 'label' => 'Pending Payments', 'value' => $this->pendingPayments, 'suffix' => '', 'icon' => 'cash', 'href' => route('tenant.payments.index'), 'color' => 'amber'];
        }

        if ($this->tenantCan('view properties')) {
            $tiles[] = ['key' => 'available-properties', 'label' => 'Available Now',    'value' => $this->availableProperties, 'suffix' => '', 'icon' => 'building', 'href' => route('tenant.properties.index'), 'color' => 'teal'];
            $tiles[] = ['key' => 'total-properties',     'label' => 'Total Properties', 'value' => $this->totalProperties,     'suffix' => '', 'icon' => 'key',      'href' => route('tenant.properties.index'), 'color' => 'indigo'];
        }

        if ($this->tenantCan('view services')) {
            $tiles[] = ['key' => 'services', 'label' => 'Active Services', 'value' => $this->activeServices, 'suffix' => '', 'icon' => 'sparkles', 'href' => route('tenant.services.index'), 'color' => 'purple'];
        }

        if ($this->tenantCan('view events')) {
            $tiles[] = ['key' => 'events', 'label' => 'Active Events', 'value' => $this->activeEvents, 'suffix' => '', 'icon' => 'calendar-star', 'href' => route('tenant.events.index'), 'color' => 'pink'];
        }

        if ($this->tenantCan('view employees')) {
            $tiles[] = ['key' => 'team', 'label' => 'Active Team Members', 'value' => $this->teamMembers, 'suffix' => '', 'icon' => 'users', 'href' => route('tenant.employees.index'), 'color' => 'slate'];
        }

        return $tiles;
    }

    #[Computed]
    public function quickActions(): array
    {
        $actions = [];

        if ($this->tenantCan('create bookings')) {
            $actions[] = ['label' => 'New Reservation', 'description' => 'Create a walk-in booking',  'href' => route('tenant.bookings.create'), 'icon' => 'plus-circle'];
        }
        if ($this->tenantCan('view bookings')) {
            $actions[] = ['label' => 'Active Bookings', 'description' => 'View current reservations',  'href' => route('tenant.bookings.index'),  'icon' => 'list'];
            $actions[] = ['label' => 'Booking History', 'description' => 'Archived and cancelled',     'href' => route('tenant.bookings.history'), 'icon' => 'archive'];
        }
        if ($this->tenantCan('view payments')) {
            $actions[] = ['label' => 'Payments',        'description' => 'Review payment records',     'href' => route('tenant.payments.index'),  'icon' => 'cash'];
        }
        if ($this->tenantCan('manage properties')) {
            $actions[] = ['label' => 'Add Property',    'description' => 'Register a new listing',     'href' => route('tenant.properties.create'), 'icon' => 'plus-circle'];
        } elseif ($this->tenantCan('view properties')) {
            $actions[] = ['label' => 'Properties',      'description' => 'Manage your listings',       'href' => route('tenant.properties.index'), 'icon' => 'building'];
        }
        if ($this->tenantCan('view services')) {
            $actions[] = ['label' => 'Services',        'description' => 'Add-ons and amenities',      'href' => route('tenant.services.index'),   'icon' => 'sparkles'];
        }
        if ($this->tenantCan('view events')) {
            $actions[] = ['label' => 'Events',          'description' => 'Festivals and activities',   'href' => route('tenant.events.index'),     'icon' => 'calendar'];
        }
        if ($this->tenantCan('view employees')) {
            $actions[] = ['label' => 'Team',            'description' => 'People and access',          'href' => route('tenant.employees.index'),  'icon' => 'users'];
        }
        if ($this->tenantCan('view analytics')) {
            $actions[] = ['label' => 'Analytics',       'description' => 'Performance and trends',     'href' => route('tenant.analytics.index'),  'icon' => 'chart'];
        }

        return $actions;
    }

    #[Computed]
    public function hasAnyAccess(): bool
    {
        return ! empty($this->statTiles) || ! empty($this->quickActions);
    }
};
?>

@push('styles')
    @once
        <style>
            .tenant-employee-dashboard-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.06) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.05) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.04) 0%, transparent 60%);
            }
            .dark .tenant-employee-dashboard-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.08) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.07) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.06) 0%, transparent 60%);
            }
        </style>
    @endonce
@endpush

<div class="relative">
    <div class="tenant-employee-dashboard-ambient fixed inset-0 -z-10 pointer-events-none" aria-hidden="true"></div>

    <div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6
                pb-[max(1rem,env(safe-area-inset-bottom))]">

        <div class="pb-6 border-b border-gray-200/70 dark:border-gray-800/70">
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Dashboard</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
                Welcome back, {{ Auth::user()->name }}
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ $this->jobTitle }} · {{ $this->tenantName }} · {{ now()->format('l, F j, Y') }}
            </p>
        </div>

        @if(! $this->hasAnyAccess)
            <div class="card p-8 sm:p-12 text-center">
                <div class="mx-auto w-14 h-14 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-400 dark:text-gray-500 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m0 0v2m0-2h2m-2 0H10m2-8V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2h2m8-2a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4 8h4"/>
                    </svg>
                </div>
                <p class="text-base font-semibold text-gray-900 dark:text-white">No modules available</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">
                    Your account does not have access to any tenant modules. Contact your business owner to request permissions.
                </p>
            </div>
        @else

            @php $tiles = $this->statTiles; @endphp
            @if(! empty($tiles))
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    @foreach($tiles as $tile)
                        @php
                            $colorClasses = match ($tile['color']) {
                                'emerald' => ['bg' => 'bg-emerald-50 dark:bg-emerald-500/10', 'text' => 'text-emerald-600 dark:text-emerald-400', 'ring' => 'hover:border-emerald-500/40'],
                                'rose'    => ['bg' => 'bg-rose-50 dark:bg-rose-500/10',       'text' => 'text-rose-600 dark:text-rose-400',       'ring' => 'hover:border-rose-500/40'],
                                'amber'   => ['bg' => 'bg-amber-50 dark:bg-amber-500/10',     'text' => 'text-amber-600 dark:text-amber-400',     'ring' => 'hover:border-amber-500/40'],
                                'blue'    => ['bg' => 'bg-blue-50 dark:bg-blue-500/10',       'text' => 'text-blue-600 dark:text-blue-400',       'ring' => 'hover:border-blue-500/40'],
                                'teal'    => ['bg' => 'bg-teal-50 dark:bg-teal-500/10',       'text' => 'text-teal-600 dark:text-teal-400',       'ring' => 'hover:border-teal-500/40'],
                                'indigo'  => ['bg' => 'bg-indigo-50 dark:bg-indigo-500/10',   'text' => 'text-indigo-600 dark:text-indigo-400',   'ring' => 'hover:border-indigo-500/40'],
                                'purple'  => ['bg' => 'bg-purple-50 dark:bg-purple-500/10',   'text' => 'text-purple-600 dark:text-purple-400',   'ring' => 'hover:border-purple-500/40'],
                                'pink'    => ['bg' => 'bg-pink-50 dark:bg-pink-500/10',       'text' => 'text-pink-600 dark:text-pink-400',       'ring' => 'hover:border-pink-500/40'],
                                'slate'   => ['bg' => 'bg-slate-50 dark:bg-slate-500/10',     'text' => 'text-slate-600 dark:text-slate-400',     'ring' => 'hover:border-slate-500/40'],
                                default   => ['bg' => 'bg-gray-100 dark:bg-gray-700',         'text' => 'text-gray-600 dark:text-gray-300',       'ring' => 'hover:border-gray-500/40'],
                            };
                        @endphp
                        <a href="{{ $tile['href'] }}" wire:navigate
                           wire:key="tile-{{ $tile['key'] }}"
                           class="card p-5 min-h-[44px] {{ $colorClasses['ring'] }} transition-all duration-200 hover:shadow-md active:scale-[0.98]
                                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 truncate">
                                        {{ $tile['label'] }}
                                    </p>
                                    <p class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white mt-2 tabular-nums">
                                        {{ number_format($tile['value']) }}{{ $tile['suffix'] }}
                                    </p>
                                </div>
                                <div class="shrink-0 p-2 rounded-xl {{ $colorClasses['bg'] }} {{ $colorClasses['text'] }}"
                                     aria-hidden="true">
                                    @switch($tile['icon'])
                                        @case('inbox-check')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            @break
                                        @case('arrow-up-right')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M7 7h10v10"/>
                                            </svg>
                                            @break
                                        @case('clock')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            @break
                                        @case('calendar')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            @break
                                        @case('calendar-star')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15l1.5-2.5L16 14l-1.5 1.5L16 17l-2.5-1.5L12 17l1.5-2z"/>
                                            </svg>
                                            @break
                                        @case('cash')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            @break
                                        @case('building')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                            </svg>
                                            @break
                                        @case('key')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 01.586 1.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                                            </svg>
                                            @break
                                        @case('sparkles')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                                            </svg>
                                            @break
                                        @case('users')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                            @break
                                        @default
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <circle cx="12" cy="12" r="10"/>
                                            </svg>
                                    @endswitch
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif

            @php $actions = $this->quickActions; @endphp
            @if(! empty($actions))
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Quick Actions</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                        @foreach($actions as $action)
                            <a href="{{ $action['href'] }}" wire:navigate
                               wire:key="action-{{ md5($action['label']) }}"
                               class="group card p-4 min-h-[44px] hover:border-primary-500/40 hover:shadow-md transition-all duration-200 active:scale-[0.98]
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                <div class="flex items-start gap-3">
                                    <div class="shrink-0 p-2 rounded-lg bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 group-hover:bg-primary-100 dark:group-hover:bg-primary-500/20 transition-colors">
                                        @switch($action['icon'])
                                            @case('plus-circle')
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                </svg>
                                                @break
                                            @case('list')
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                                </svg>
                                                @break
                                            @case('archive')
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                                                </svg>
                                                @break
                                            @case('cash')
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                @break
                                            @case('building')
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                                </svg>
                                                @break
                                            @case('sparkles')
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                                                </svg>
                                                @break
                                            @case('calendar')
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                                @break
                                            @case('users')
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                </svg>
                                                @break
                                            @case('chart')
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                                </svg>
                                                @break
                                        @endswitch
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-sm text-gray-900 dark:text-white truncate">{{ $action['label'] }}</p>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate mt-0.5">{{ $action['description'] }}</p>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($this->tenantCan('view bookings'))
                @php $recent = $this->recentBookings; @endphp
                @if($recent->isNotEmpty())
                    <div class="card overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-4">
                            <div>
                                <h2 class="text-base font-bold text-gray-900 dark:text-white">Recent Bookings</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">The 5 most recently created reservations</p>
                            </div>
                            <a href="{{ route('tenant.bookings.index') }}" wire:navigate
                               class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline
                                      py-2.5 -my-2.5 px-1 -mx-1 rounded
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                View all →
                            </a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30">
                                    <tr>
                                        <th class="px-4 sm:px-6 py-3 text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Reference</th>
                                        <th class="px-4 sm:px-6 py-3 text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Guest</th>
                                        <th class="px-4 sm:px-6 py-3 text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 hidden sm:table-cell">Check-in</th>
                                        <th class="px-4 sm:px-6 py-3 text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 hidden md:table-cell">Property</th>
                                        <th class="px-4 sm:px-6 py-3 text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                                        <th class="px-4 sm:px-6 py-3 text-right text-[10px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                                    @foreach($recent as $booking)
                                        <tr wire:key="recent-{{ $booking->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors">
                                            <td class="px-4 sm:px-6 py-3 font-mono text-xs font-semibold text-primary-600 dark:text-primary-400">
                                                {{ $booking->booking_reference }}
                                            </td>
                                            <td class="px-4 sm:px-6 py-3">
                                                <p class="font-medium text-gray-900 dark:text-white truncate">{{ $booking->user?->name ?? 'Walk-in Guest' }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $booking->user?->email ?? '—' }}</p>
                                            </td>
                                            <td class="px-4 sm:px-6 py-3 hidden sm:table-cell text-gray-700 dark:text-gray-300">
                                                {{ $booking->check_in?->format('M d, Y') ?? '—' }}
                                            </td>
                                            <td class="px-4 sm:px-6 py-3 hidden md:table-cell text-gray-700 dark:text-gray-300 truncate">
                                                {{ $booking->items->first()?->property?->name ?? '—' }}
                                            </td>
                                            <td class="px-4 sm:px-6 py-3">
                                                @php
                                                    $badge = match ($booking->status) {
                                                        Booking::STATUS_PENDING    => 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/30',
                                                        Booking::STATUS_RESERVED   => 'bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-500/30',
                                                        Booking::STATUS_CONFIRMED  => 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30',
                                                        Booking::STATUS_CHECKED_IN => 'bg-purple-100 dark:bg-purple-500/15 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-500/30',
                                                        Booking::STATUS_COMPLETED  => 'bg-slate-100 dark:bg-slate-500/15 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-500/30',
                                                        Booking::STATUS_CANCELLED  => 'bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-500/30',
                                                        default                    => 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-600',
                                                    };
                                                @endphp
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border {{ $badge }}">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                                    {{ ucfirst(str_replace('_', ' ', $booking->status)) }}
                                                </span>
                                            </td>
                                            <td class="px-4 sm:px-6 py-3 text-right">
                                                <a href="{{ route('tenant.bookings.show', $booking->id) }}" wire:navigate
                                                   class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline
                                                          py-2.5 -my-2.5 px-1 -mx-1 rounded
                                                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="card p-8 text-center">
                        <div class="mx-auto w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-400 dark:text-gray-500 mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">No bookings yet</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            When guests book a stay, their reservations will appear here.
                        </p>
                        @if($this->tenantCan('create bookings'))
                            <a href="{{ route('tenant.bookings.create') }}" wire:navigate
                               class="btn-primary mt-4 text-sm inline-flex items-center gap-2
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                New Reservation
                            </a>
                        @endif
                    </div>
                @endif
            @endif

        @endif
    </div>
</div>