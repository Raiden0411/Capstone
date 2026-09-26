{{-- resources/views/tenant/pages/employee/⚡view-employee.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Models\Employee;
use App\Scopes\TenantScope;
use App\Traits\ChecksTenantPermissions;
use Illuminate\Support\Facades\Auth;

new
#[Layout('tenant.layouts.app')]
#[Title('Employees')]
class extends Component {
    use WithPagination;
    use ChecksTenantPermissions;

    #[Url(keep: true)] public string $search       = '';
    #[Url(keep: true)] public string $roleFilter   = '';
    #[Url(keep: true)] public string $statusFilter = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->tenant_id, 403);
        abort_unless($this->tenantCan('view employees'), 403);
    }

    public function hydrate(): void
    {
        abort_unless(Auth::user()?->tenant_id, 403);
        abort_unless($this->tenantCan('view employees'), 403);
    }

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingRoleFilter(): void   { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->reset(['search', 'roleFilter', 'statusFilter']);
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $this->requirePermission('manage employees');

        $employee = Employee::withoutGlobalScope(TenantScope::class)
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->firstOrFail();

        $employee->update(['is_active' => ! $employee->is_active]);

        session()->flash('message', "{$employee->name} " . ($employee->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function delete(int $id): void
    {
        $this->requirePermission('manage employees');

        $employee = Employee::withoutGlobalScope(TenantScope::class)
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->firstOrFail();

        $name = $employee->name;
        $employee->delete();

        session()->flash('message', "{$name} deleted.");
    }

    #[Computed]
    public function employees()
    {
        return Employee::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->with(['user.roles'])
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('name', 'like', '%'.$this->search.'%')
                       ->orWhere('code', 'like', '%'.$this->search.'%')
                       ->orWhereHas('user', function ($uq) {
                           $uq->where('name', 'like', '%'.$this->search.'%')
                              ->orWhere('email', 'like', '%'.$this->search.'%')
                              ->orWhere('phone', 'like', '%'.$this->search.'%');
                       });
                });
            })
            ->when($this->roleFilter, fn ($q) => $q->where('role', $this->roleFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('is_active', $this->statusFilter === 'active'))
            ->orderByRaw("CASE WHEN LOWER(role) = 'manager' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->paginate(12);
    }

    #[Computed]
    public function roles()
    {
        return Employee::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereNotNull('role')
            ->distinct()
            ->orderBy('role')
            ->pluck('role');
    }

    #[Computed]
    public function stats(): array
    {
        $tid = Auth::user()->tenant_id;

        return [
            'total'    => Employee::where('tenant_id', $tid)->count(),
            'active'   => Employee::where('tenant_id', $tid)->where('is_active', true)->count(),
            'inactive' => Employee::where('tenant_id', $tid)->where('is_active', false)->count(),
        ];
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->roleFilter !== ''
            || $this->statusFilter !== '';
    }
};
?>

@push('styles')
    @once
        <style>
            .tenant-employees-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.06) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.05) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.04) 0%, transparent 60%);
            }
            .dark .tenant-employees-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.08) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.07) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.06) 0%, transparent 60%);
            }
        </style>
    @endonce
@endpush

<div class="relative">
    <div class="tenant-employees-ambient fixed inset-0 -z-10 pointer-events-none" aria-hidden="true"></div>

    <div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6
                pb-[max(1rem,env(safe-area-inset-bottom))]">

        {{-- Flash: success --}}
        @if (session()->has('message'))
            <div x-data="{ show: true }"
                 x-init="setTimeout(() => show = false, 4000)"
                 :class="show ? '' : 'hidden'"
                 class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ session('message') }}</span>
                </div>
                <button type="button" @click="show = false"
                        class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 rounded-md text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/10
                               transition-all duration-200 active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                        aria-label="Dismiss">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        {{-- Flash: error --}}
        @if (session()->has('error'))
            <div x-data="{ show: true }"
                 x-init="setTimeout(() => show = false, 5000)"
                 :class="show ? '' : 'hidden'"
                 class="flex items-center justify-between bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
                <button type="button" @click="show = false"
                        class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                               transition-all duration-200 active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                        aria-label="Dismiss">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        {{-- Page header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200/70 dark:border-gray-800/70">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Team</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Employees
                </h1>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Manage your team members and their system access.
                </p>
            </div>
            @if($this->tenantCan('manage employees'))
                <a href="{{ route('tenant.employees.create') }}" wire:navigate
                   class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                          transition-all duration-200 active:scale-95
                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Add Employee</span>
                </a>
            @endif
        </div>

        {{-- Filter card --}}
        @php $s = $this->stats; @endphp
        <div class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                    rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                    shadow-sm p-4 space-y-3">

            <div class="flex flex-wrap gap-2 items-center">
                <div class="relative flex-1 min-w-[200px]">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="Search by name, email, phone, or code…"
                           enterkeyhint="search"
                           aria-label="Search employees"
                           class="w-full h-11 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl pl-10 pr-4 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400
                                  focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition
                                  [touch-action:manipulation]">
                </div>

                <select wire:model.live="roleFilter"
                        aria-label="Filter by job title"
                        class="h-11 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl px-3 text-base sm:text-sm text-gray-900 dark:text-white
                               focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition
                               [touch-action:manipulation]">
                    <option value="">All Roles</option>
                    @foreach($this->roles as $role)
                        <option value="{{ $role }}" wire:key="role-opt-{{ \Illuminate\Support\Str::slug($role) }}">{{ $role }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-wrap gap-2 items-center">
                @php
                    $pills = [
                        ['value' => '',         'label' => 'All',      'count' => $s['total'],    'active' => 'bg-primary-600 border-primary-600 text-white shadow-sm shadow-primary-600/20', 'rest' => 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-gray-400 dark:hover:border-gray-600'],
                        ['value' => 'active',   'label' => 'Active',   'count' => $s['active'],   'active' => 'bg-emerald-600 border-emerald-600 text-white shadow-sm shadow-emerald-600/20', 'rest' => 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-emerald-400 dark:hover:border-emerald-500/60'],
                        ['value' => 'inactive', 'label' => 'Inactive', 'count' => $s['inactive'], 'active' => 'bg-gray-600 border-gray-600 text-white shadow-sm shadow-gray-600/20',          'rest' => 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-gray-400 dark:hover:border-gray-600'],
                    ];
                @endphp

                @foreach($pills as $pill)
                    @php $isActive = $statusFilter === $pill['value']; @endphp
                    <button type="button"
                            wire:click="$set('statusFilter', '{{ $pill['value'] }}')"
                            wire:key="pill-{{ $pill['value'] !== '' ? $pill['value'] : 'all' }}"
                            aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                            class="inline-flex items-center gap-2 h-11 sm:h-9 pl-3.5 pr-1.5 rounded-full text-xs font-semibold uppercase tracking-wide border
                                   transition-all duration-200 active:scale-95 shrink-0
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                   {{ $isActive ? $pill['active'] : $pill['rest'] }}">
                        <span>{{ $pill['label'] }}</span>
                        <span class="inline-flex items-center justify-center min-w-[22px] h-5 px-1.5 rounded-full text-[10px] font-bold tabular-nums
                                     {{ $isActive ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                            {{ $pill['count'] }}
                        </span>
                    </button>
                @endforeach

                @if($this->hasActiveFilters)
                    <button type="button"
                            wire:click="clearFilters"
                            class="inline-flex items-center justify-center gap-1.5 h-11 sm:h-9 px-3.5 rounded-full text-xs font-semibold uppercase tracking-wide
                                   border border-rose-300 dark:border-rose-500/40
                                   bg-white dark:bg-gray-800
                                   text-rose-700 dark:text-rose-300
                                   transition-all duration-200 active:scale-95 shrink-0
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   hover:bg-rose-50 dark:hover:bg-rose-500/10
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <span>Clear</span>
                    </button>
                @endif
            </div>
        </div>

        {{-- Employee grid --}}
        @if($this->employees->isEmpty())
            <div class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                        rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                        p-12">
                <div class="flex flex-col items-center max-w-md mx-auto text-center">
                    <div class="p-4 rounded-2xl bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 mb-4">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <p class="text-base font-semibold text-gray-900 dark:text-white">
                        No employees found
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        @if($this->hasActiveFilters)
                            No employees match your current filters. Try adjusting or clearing them.
                        @else
                            Get started by adding your first team member.
                        @endif
                    </p>
                    <div class="mt-6 flex flex-wrap gap-2 justify-center">
                        @if($this->hasActiveFilters)
                            <button type="button"
                                    wire:click="clearFilters"
                                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                           transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                <span>Clear Filters</span>
                            </button>
                        @endif
                        @if($this->tenantCan('manage employees'))
                            <a href="{{ route('tenant.employees.create') }}" wire:navigate
                               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                      transition-all duration-200 active:scale-95
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                <span>Add Employee</span>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div wire:loading.class="opacity-40 pointer-events-none"
                 wire:target="search,roleFilter,statusFilter,gotoPage,nextPage,previousPage"
                 class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 transition-opacity duration-200">
                @foreach($this->employees as $employee)
                    @php
                        $isManager  = strtolower($employee->role ?? '') === 'manager';
                        $canManage  = $this->tenantCan('manage employees');
                    @endphp
                    <article wire:key="employee-{{ $employee->id }}"
                             class="group bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                                    rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                                    shadow-sm
                                    hover:shadow-md hover:border-primary-300/80 dark:hover:border-primary-500/40
                                    overflow-hidden flex flex-col transition-all duration-200">

                        <div class="p-4 flex-1 flex flex-col gap-3">

                            {{-- Avatar + name --}}
                            <div class="flex items-start gap-3">
                                <div class="w-14 h-14 rounded-xl bg-primary-50 dark:bg-primary-500/10 border border-primary-200/50 dark:border-primary-500/20 flex items-center justify-center text-primary-700 dark:text-primary-300 font-bold text-lg shrink-0 overflow-hidden
                                            {{ !$employee->is_active ? 'grayscale-[0.7] opacity-70' : '' }}">
                                    @if($employee->avatar)
                                        <img src="{{ '/storage/' . ltrim($employee->avatar, '/') }}"
                                             alt="{{ $employee->name }}"
                                             class="w-full h-full object-cover"
                                             loading="lazy"
                                             decoding="async">
                                    @else
                                        {{ strtoupper(substr($employee->name, 0, 1)) }}
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="font-semibold text-gray-900 dark:text-white text-base leading-tight truncate">
                                            {{ $employee->name }}
                                        </h3>
                                        @if($isManager)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                                                         bg-primary-100 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300 border border-primary-200 dark:border-primary-500/30">
                                                <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                    <path d="M10 2l2 4h4l-3 3 1 4-4-2-4 2 1-4-3-3h4l2-4z"/>
                                                </svg>
                                                Manager
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">
                                        {{ $employee->role ?: 'No job title' }}
                                    </p>
                                    @if($employee->code)
                                        <p class="text-[10px] font-mono text-gray-400 dark:text-gray-500 mt-1 tabular-nums">{{ $employee->code }}</p>
                                    @endif
                                </div>
                            </div>

                            {{-- Contact --}}
                            <div class="space-y-1.5 text-xs text-gray-600 dark:text-gray-400">
                                @if($employee->phone)
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <svg class="w-3.5 h-3.5 shrink-0 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                        </svg>
                                        <span class="truncate">{{ $employee->phone }}</span>
                                    </div>
                                @endif

                                @if($employee->user)
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <svg class="w-3.5 h-3.5 shrink-0 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                        <span class="truncate">{{ $employee->user->email }}</span>
                                    </div>
                                    <div class="flex flex-wrap gap-1 pt-0.5">
                                        @forelse($employee->user->roles as $r)
                                            <span wire:key="emp-role-{{ $employee->id }}-{{ $r->id }}"
                                                  class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-medium
                                                         {{ in_array($r->name, ['admin', 'super-admin'], true)
                                                            ? 'bg-purple-100 dark:bg-purple-500/15 text-purple-700 dark:text-purple-300'
                                                            : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
                                                {{ \Illuminate\Support\Str::headline($r->name) }}
                                            </span>
                                        @empty
                                            <span class="text-[10px] text-gray-400 dark:text-gray-500 italic">No roles assigned</span>
                                        @endforelse
                                    </div>
                                @else
                                    <div class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 shrink-0 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                        </svg>
                                        <span class="text-gray-400 dark:text-gray-500 italic">No user account</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Status + actions --}}
                            <div class="mt-auto pt-3 border-t border-gray-100/70 dark:border-white/[0.04] flex items-center justify-between gap-2">
                                @if($canManage)
                                    <button type="button"
                                            wire:click="toggleActive({{ $employee->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="toggleActive"
                                            aria-label="{{ $employee->is_active ? 'Deactivate' : 'Activate' }} {{ $employee->name }}"
                                            class="inline-flex items-center justify-center rounded-full transition-all duration-200 active:scale-95
                                                   min-h-[44px]
                                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                                   disabled:opacity-60 disabled:cursor-not-allowed">
                                        @if($employee->is_active)
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                                                         bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300
                                                         border border-emerald-200 dark:border-emerald-500/30
                                                         hover:bg-emerald-200 dark:hover:bg-emerald-500/25 transition">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5" aria-hidden="true"></span> Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                                                         bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300
                                                         border border-rose-200 dark:border-rose-500/30
                                                         hover:bg-rose-200 dark:hover:bg-rose-500/25 transition">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-1.5" aria-hidden="true"></span> Inactive
                                            </span>
                                        @endif
                                    </button>
                                @else
                                    @if($employee->is_active)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5" aria-hidden="true"></span> Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-1.5" aria-hidden="true"></span> Inactive
                                        </span>
                                    @endif
                                @endif

                                <div class="flex items-center gap-2">
                                    @if($canManage)
                                        <a href="{{ route('tenant.employees.edit', $employee->id) }}" wire:navigate
                                           aria-label="Edit {{ $employee->name }}"
                                           title="Edit"
                                           class="inline-flex items-center justify-center h-11 w-11 sm:h-9 sm:w-9 rounded-lg
                                                  text-gray-500 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-950/50
                                                  transition-all duration-200 active:scale-95
                                                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>

                                        {{-- Delete — two-click arm replaces wire:confirm --}}
                                        <button type="button"
                                                x-data="{
                                                    armed: false,
                                                    _t: null,
                                                    arm() { this.armed = true; clearTimeout(this._t); this._t = setTimeout(() => { this.armed = false; this._t = null; }, 4000); },
                                                    unarm() { clearTimeout(this._t); this._t = null; this.armed = false; },
                                                    destroy() { clearTimeout(this._t); }
                                                }"
                                                @click="armed ? (unarm(), $wire.delete({{ $employee->id }})) : arm()"
                                                wire:loading.attr="disabled"
                                                wire:target="delete"
                                                :aria-label="armed ? 'Click again to confirm delete' : 'Delete {{ $employee->name }}'"
                                                :title="armed ? 'Click again to confirm' : 'Delete'"
                                                :class="armed
                                                    ? 'text-rose-700 dark:text-rose-300 bg-rose-100 dark:bg-rose-500/20 ring-2 ring-rose-400/60'
                                                    : 'text-gray-500 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50'"
                                                class="inline-flex items-center justify-center h-11 w-11 sm:h-9 sm:w-9 rounded-lg
                                                       transition-all duration-200 active:scale-95
                                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                                                       disabled:opacity-60 disabled:cursor-not-allowed">
                                            <svg x-show="!armed" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            <svg x-show="armed" x-cloak class="w-4 h-4 animate-pulse motion-reduce:animate-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500 italic">View only</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if($this->employees->hasPages())
                <div class="pt-2">
                    {{ $this->employees->links() }}
                </div>
            @endif
        @endif
    </div>
</div>