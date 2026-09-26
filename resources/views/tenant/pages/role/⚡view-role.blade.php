{{-- resources/views/tenant/pages/role/⚡view-role.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Models\TenantSetting;
use App\Models\Employee;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

new
#[Layout('tenant.layouts.app')]
#[Title('Custom Roles')]
class extends Component {

    public array $customRoles = [];

    #[Url(history: true)]
    public string $search = '';

    public function mount(): void
    {
        $this->authorizeManageRoles();

        $user = Auth::user();
        abort_unless($user?->tenant_id, 403, 'Your account is not linked to a business.');

        $this->loadRoles();
    }

    public function hydrate(): void
    {
        $this->authorizeManageRoles();
        abort_unless(Auth::user()?->tenant_id, 403);

        $this->loadRoles();
    }

    protected function authorizeManageRoles(): void
    {
        $user = Auth::user();

        abort_unless($user, 403);

        $canManage = $user->hasAnyRole(['admin', 'super-admin'])
            || $user->getAllPermissions()->contains('name', 'manage roles');

        abort_unless($canManage, 403, 'You are not authorized to view roles.');
    }

    public function loadRoles(): void
    {
        $setting = TenantSetting::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('key', 'custom_roles')
            ->first();

        $this->customRoles = ($setting && is_array($setting->value)) ? $setting->value : [];
    }

    public function deleteRole(int $index): void
    {
        $this->authorizeManageRoles();

        $user     = Auth::user();
        $tenantId = (int) $user->tenant_id;

        $this->loadRoles();

        if (!isset($this->customRoles[$index])) {
            session()->flash('error', 'Role not found. It may have already been deleted.');
            return;
        }

        $roleData = $this->customRoles[$index];
        $roleId   = isset($roleData['role_id']) ? (int) $roleData['role_id'] : null;
        $roleName = $roleData['name'] ?? 'Unknown';

        try {
            DB::transaction(function () use ($index, $roleId, $tenantId): void {
                if ($roleId) {
                    $spatieRole = Role::find($roleId);

                    if ($spatieRole) {
                        if (config('permission.teams')) {
                            $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
                            if ((int) $spatieRole->{$teamKey} !== $tenantId) {
                                throw new \RuntimeException('Role does not belong to your tenant.');
                            }
                        }

                        $spatieRole->delete();
                    }
                }

                unset($this->customRoles[$index]);
                $this->customRoles = array_values($this->customRoles);

                TenantSetting::updateOrCreate(
                    ['tenant_id' => $tenantId, 'key' => 'custom_roles'],
                    ['value'     => $this->customRoles]
                );
            });
        } catch (\RuntimeException $e) {
            Log::warning('Role deletion rejected: ' . $e->getMessage(), [
                'tenant_id' => $tenantId,
                'role_id'   => $roleId,
            ]);
            session()->flash('error', 'You are not authorized to delete this role.');
            return;
        } catch (\Throwable $e) {
            Log::error('Role deletion failed: ' . $e->getMessage(), [
                'tenant_id' => $tenantId,
                'role_id'   => $roleId,
            ]);
            session()->flash('error', 'Failed to delete role. Please try again.');
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        session()->flash('message', "Role '{$roleName}' deleted successfully.");
    }

    public function clearFilters(): void
    {
        $this->reset('search');
    }

    #[Computed]
    public function filteredRoles(): array
    {
        if ($this->search === '') {
            return $this->customRoles;
        }

        $needle = strtolower($this->search);

        return array_filter(
            $this->customRoles,
            fn (array $role) => isset($role['name'])
                && str_contains(strtolower($role['name']), $needle)
        );
    }

    #[Computed]
    public function stats(): array
    {
        $totalPermissions = 0;
        foreach ($this->customRoles as $role) {
            $totalPermissions += count($role['permissions'] ?? []);
        }

        return [
            'total_roles'       => count($this->customRoles),
            'total_permissions' => $totalPermissions,
            'employees'         => Employee::where('tenant_id', Auth::user()->tenant_id)->count(),
            'system_rights'     => Permission::where('guard_name', 'web')->count(),
        ];
    }

    #[Computed]
    public function hasActiveFilter(): bool
    {
        return $this->search !== '';
    }
};
?>

@push('styles')
    @once
        <style>
            .tenant-roles-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.06) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.05) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.04) 0%, transparent 60%);
            }
            .dark .tenant-roles-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.08) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.07) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.06) 0%, transparent 60%);
            }
        </style>
    @endonce
@endpush

@php $s = $this->stats; @endphp

<div class="relative">
    <div class="tenant-roles-ambient fixed inset-0 -z-10 pointer-events-none" aria-hidden="true"></div>

    <div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6
                pb-[max(1rem,env(safe-area-inset-bottom))]">

        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200/70 dark:border-gray-800/70">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Access Control</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Custom Roles
                </h1>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Manage your team's access by defining custom roles with specific permissions.
                </p>
            </div>
            <a href="{{ route('tenant.roles.create') }}" wire:navigate
               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                      transition-all duration-200 active:scale-95
                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Create Role</span>
            </a>
        </div>

        @if(session()->has('message'))
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

        @if(session()->has('error'))
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

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @php
                $kpis = [
                    ['label' => 'Custom Roles',    'value' => number_format($s['total_roles']),       'dot' => 'bg-primary-500'],
                    ['label' => 'Granted Rights',  'value' => number_format($s['total_permissions']), 'dot' => 'bg-emerald-500'],
                    ['label' => 'Employees',       'value' => number_format($s['employees']),         'dot' => 'bg-purple-500'],
                    ['label' => 'System Rights',   'value' => number_format($s['system_rights']),     'dot' => 'bg-amber-500'],
                ];
            @endphp
            @foreach($kpis as $kpi)
                <div wire:key="kpi-{{ $loop->index }}"
                     class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                            rounded-xl border border-gray-200/60 dark:border-white/[0.06]
                            shadow-sm p-3.5">
                    <div class="flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full {{ $kpi['dot'] }}"></span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</span>
                    </div>
                    <p class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white tabular-nums">{{ $kpi['value'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                    rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                    shadow-sm p-4">
            <div class="flex flex-wrap gap-2 items-center">
                <div class="relative flex-1 min-w-[200px]">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="Search custom roles…"
                           class="input w-full"
                           style="padding-left: 2.5rem;">
                </div>

                @if($this->hasActiveFilter)
                    <button type="button" wire:click="clearFilters"
                            class="inline-flex items-center gap-1 h-11 sm:h-9 px-3.5 rounded-full text-xs font-semibold uppercase tracking-wide
                                   border border-rose-300 dark:border-rose-500/40
                                   bg-white dark:bg-gray-800 text-rose-700 dark:text-rose-300
                                   transition-all duration-200 active:scale-95
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   hover:bg-rose-50 dark:hover:bg-rose-500/10
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Clear
                    </button>
                @endif
            </div>
        </div>

        @if(empty($this->filteredRoles))
            <div class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                        rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                        shadow-sm p-12 text-center">
                <div class="flex flex-col items-center max-w-md mx-auto">
                    <div class="p-3 rounded-2xl bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <p class="mt-4 text-base font-semibold text-gray-900 dark:text-white">
                        {{ $this->hasActiveFilter ? 'No roles match your search' : 'No custom roles yet' }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $this->hasActiveFilter
                            ? 'Try a different search term or clear the filter.'
                            : 'Create a role to give your staff specific access without making them full admins.' }}
                    </p>
                    <div class="mt-5 flex flex-wrap gap-2 justify-center">
                        @if($this->hasActiveFilter)
                            <button type="button" wire:click="clearFilters"
                                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                           transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                Clear Filter
                            </button>
                        @else
                            <a href="{{ route('tenant.roles.create') }}" wire:navigate
                               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                      transition-all duration-200 active:scale-95
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                <span>Create First Role</span>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div wire:loading.class="opacity-40 pointer-events-none"
                 wire:target="search,clearFilters,deleteRole"
                 class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 transition-opacity duration-200">
                @foreach($this->filteredRoles as $index => $role)
                    @php
                        $permissions = $role['permissions'] ?? [];
                        $permCount   = count($permissions);
                        $roleName    = $role['name'] ?? 'Unnamed Role';
                        $roleId      = $role['role_id'] ?? null;
                    @endphp

                    <article wire:key="role-{{ $roleId ?? $index }}"
                             x-data="{ expanded: false }"
                             class="group relative bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                                    rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                                    shadow-sm
                                    hover:shadow-md hover:border-primary-300/80 dark:hover:border-primary-500/40
                                    transition-all duration-200 flex flex-col overflow-hidden">

                        <div class="p-4 pb-3 flex items-start gap-3">
                            <div class="w-11 h-11 rounded-xl bg-primary-50 dark:bg-primary-500/15 text-primary-600 dark:text-primary-400 shrink-0 flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                                             bg-primary-50 dark:bg-primary-500/10 text-primary-700 dark:text-primary-300 border border-primary-200 dark:border-primary-500/30">
                                    Custom
                                </span>
                                <p class="mt-1 font-semibold text-gray-900 dark:text-white truncate leading-tight">
                                    {{ $roleName }}
                                </p>
                            </div>
                        </div>

                        <div class="px-4 py-3 border-t border-gray-100/80 dark:border-white/[0.04]">
                            <button type="button"
                                    x-on:click="expanded = !expanded"
                                    :aria-expanded="expanded ? 'true' : 'false'"
                                    @if($permCount === 0) disabled @endif
                                    class="w-full inline-flex items-center justify-between gap-2 px-3 h-11 sm:h-9 rounded-lg text-xs font-semibold
                                           bg-gray-50 dark:bg-gray-900/40 text-gray-700 dark:text-gray-300 border border-gray-200/70 dark:border-gray-700/60
                                           transition-all duration-200
                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           @if($permCount > 0) active:scale-[0.98] hover:bg-gray-100 dark:hover:bg-gray-800/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 @else cursor-default @endif">
                                <span class="inline-flex items-center gap-2 tabular-nums">
                                    <svg class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                    </svg>
                                    {{ $permCount }} {{ Str::plural('permission', $permCount) }}
                                </span>
                                @if($permCount > 0)
                                    <svg class="w-3.5 h-3.5 text-gray-400 transition-transform duration-200 motion-reduce:transition-none"
                                         :class="expanded ? 'rotate-180' : ''"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                @endif
                            </button>

                            @if($permCount > 0)
                                <div x-cloak
                                     :class="expanded ? 'block mt-3' : 'hidden'"
                                     class="flex flex-wrap gap-1 max-h-40 overflow-y-auto pr-1">
                                    @foreach($permissions as $i => $perm)
                                        <span wire:key="perm-{{ $index }}-{{ $i }}"
                                              class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium
                                                     bg-gray-100 dark:bg-gray-700/60 text-gray-700 dark:text-gray-300
                                                     border border-gray-200/80 dark:border-gray-600/60 font-mono">
                                            {{ Str::headline($perm) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="mt-auto px-3 py-2.5 border-t border-gray-100/80 dark:border-white/[0.04] flex items-center justify-end gap-1">
                            <a href="{{ route('tenant.roles.edit', $index) }}" wire:navigate
                               aria-label="Edit {{ $roleName }}"
                               title="Edit role"
                               class="inline-flex items-center justify-center h-11 w-11 sm:h-9 sm:w-9 rounded-lg text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-500/10
                                      transition-all duration-200 active:scale-95
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>

                            {{-- Delete — two-click arm replaces confirm() --}}
                            <button type="button"
                                    x-data="{
                                        armed: false,
                                        _t: null,
                                        arm() { this.armed = true; clearTimeout(this._t); this._t = setTimeout(() => { this.armed = false; this._t = null; }, 4000); },
                                        unarm() { clearTimeout(this._t); this._t = null; this.armed = false; },
                                        destroy() { clearTimeout(this._t); }
                                    }"
                                    @click="armed ? (unarm(), $wire.deleteRole({{ $index }})) : arm()"
                                    wire:loading.attr="disabled"
                                    wire:target="deleteRole"
                                    :aria-label="armed ? 'Click again to confirm delete' : 'Delete {{ addslashes($roleName) }}'"
                                    :title="armed ? 'Click again to confirm' : 'Delete role'"
                                    :class="armed
                                        ? 'text-rose-700 dark:text-rose-300 bg-rose-100 dark:bg-rose-500/20 ring-2 ring-rose-400/60'
                                        : 'text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10'"
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
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</div>