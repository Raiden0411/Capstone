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

    public function mount()
    {
        $user = Auth::user();
        if (!$user || !$user->tenant_id) {
            abort(403);
        }

        // Use getAllPermissions()->contains() to avoid PermissionDoesNotExist
        $canManage = $user->hasAnyRole(['admin', 'super-admin'])
            || $user->getAllPermissions()->contains('name', 'manage roles');

        if (!$canManage) {
            abort(403, 'You are not authorized to view roles.');
        }

        $this->loadRoles();
    }

    public function loadRoles()
    {
        $setting = TenantSetting::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('key', 'custom_roles')
            ->first();

        $this->customRoles = ($setting && is_array($setting->value)) ? $setting->value : [];
    }

    public function deleteRole(int $index): void
    {
        $user = Auth::user();
        $canManage = $user->hasAnyRole(['admin', 'super-admin'])
            || $user->getAllPermissions()->contains('name', 'manage roles');

        if (!$canManage) {
            abort(403);
        }

        if (!isset($this->customRoles[$index])) {
            session()->flash('error', 'Role not found. It may have already been deleted.');
            return;
        }

        $roleData = $this->customRoles[$index];
        $roleId   = isset($roleData['role_id']) ? (int) $roleData['role_id'] : null;
        $roleName = $roleData['name'] ?? 'Unknown';

        try {
            DB::transaction(function () use ($index, $roleId, $user) {
                // 1. Delete the real Spatie role (and its role_has_permissions pivot)
                if ($roleId) {
                    $spatieRole = Role::find($roleId);

                    if ($spatieRole) {
                        // Verify tenant ownership when teams are enabled
                        if (config('permission.teams')) {
                            $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
                            if ((int) $spatieRole->{$teamKey} !== (int) $user->tenant_id) {
                                throw new \RuntimeException('Role does not belong to your tenant.');
                            }
                        }

                        // Detach from any users (Spatie handles the pivot when the role is deleted)
                        $spatieRole->delete();
                    }
                }

                // 2. Remove from JSON tracker
                unset($this->customRoles[$index]);
                $this->customRoles = array_values($this->customRoles);

                TenantSetting::updateOrCreate(
                    ['tenant_id' => $user->tenant_id, 'key' => 'custom_roles'],
                    ['value'     => $this->customRoles]
                );

                // 3. Clear permission cache so stale grants don't linger
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            });

            session()->flash('message', "Role '{$roleName}' deleted successfully.");
        } catch (\RuntimeException $e) {
            Log::warning('Role deletion rejected: ' . $e->getMessage(), [
                'tenant_id' => $user->tenant_id,
                'role_id'   => $roleId,
            ]);
            session()->flash('error', 'You are not authorized to delete this role.');
        } catch (\Exception $e) {
            Log::error('Role deletion failed: ' . $e->getMessage(), [
                'tenant_id' => $user->tenant_id,
                'role_id'   => $roleId,
            ]);
            session()->flash('error', 'Failed to delete role. Please try again.');
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search');
    }

    /**
     * Filtered roles — preserves the ORIGINAL array keys so the edit link
     * (which uses {index}) still works after filtering.
     *
     * @return array<int, array{name?: string, permissions?: array, role_id?: int}>
     */
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

    /**
     * Aggregate stats for the summary cards.
     *
     * @return array{total_roles: int, total_permissions: int, employees: int, system_rights: int}
     */
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

    public function exportCsv()
    {
        $roles = $this->filteredRoles;

        $filename = 'custom-roles-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($roles) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Role Name', 'Permissions Count', 'Permissions']);

            foreach ($roles as $role) {
                $permissions = $role['permissions'] ?? [];
                fputcsv($out, [
                    $role['name'] ?? '',
                    count($permissions),
                    implode(', ', $permissions),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-800">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Access Control</span>
            </div>
            <h1 class="font-display text-3xl md:text-4xl font-semibold text-gray-900 dark:text-white">
                Custom <em class="italic text-primary-600 dark:text-primary-400">Roles</em>
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-2">
                Manage your team's access by defining custom roles with specific permissions.
            </p>
        </div>
        <a href="{{ route('tenant.roles.create') }}" wire:navigate
           class="btn-primary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Create Role
        </a>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-transition.opacity
             class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('message') }}</span>
            </div>
            <button type="button" @click="show = false" class="text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif
    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-transition.opacity
             class="flex items-center justify-between bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" @click="show = false" class="text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif

    {{-- Quick Stats --}}
    @php $stats = $this->stats; @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm transition-all duration-200 hover:border-primary-500/30">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Custom Roles</p>
                <div class="p-2 bg-primary-50 dark:bg-primary-950/50 rounded-xl text-primary-600 dark:text-primary-400 border border-primary-100 dark:border-primary-900/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white mt-3">{{ number_format($stats['total_roles']) }}</p>
        </div>

        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm transition-all duration-200 hover:border-emerald-500/30">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Granted Rights</p>
                <div class="p-2 bg-emerald-50 dark:bg-emerald-950/50 rounded-xl text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-3">{{ number_format($stats['total_permissions']) }}</p>
        </div>

        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm transition-all duration-200 hover:border-purple-500/30">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Employees</p>
                <div class="p-2 bg-purple-50 dark:bg-purple-950/50 rounded-xl text-purple-600 dark:text-purple-400 border border-purple-100 dark:border-purple-900/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-purple-600 dark:text-purple-400 mt-3">{{ number_format($stats['employees']) }}</p>
        </div>

        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm transition-all duration-200 hover:border-amber-500/30">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">System Rights</p>
                <div class="p-2 bg-amber-50 dark:bg-amber-950/50 rounded-xl text-amber-600 dark:text-amber-400 border border-amber-100 dark:border-amber-900/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-3">{{ number_format($stats['system_rights']) }}</p>
        </div>
    </div>

    {{-- Filter & Actions Toolbar --}}
    <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between bg-white dark:bg-gray-800/90 p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm">
        <div class="relative flex-1 max-w-md">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search custom roles…"
                   class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2 pl-10 pr-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
        </div>

        <div class="flex items-center gap-2">
            @if($search)
                <button type="button" wire:click="clearFilters"
                        class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 text-xs font-semibold transition focus:ring-2 focus:ring-primary-500/50">
                    <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Clear Filter
                </button>
            @endif

            <button wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-gray-900 dark:bg-gray-700 hover:bg-black dark:hover:bg-gray-600 text-white text-xs sm:text-sm font-semibold shadow-sm transition disabled:opacity-50 focus:ring-2 focus:ring-primary-500/50">
                <svg wire:loading.remove wire:target="exportCsv" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
                <span wire:loading wire:target="exportCsv" class="inline-flex items-center gap-1.5">
                    <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Generating…
                </span>
            </button>
        </div>
    </div>

    {{-- Roles Table --}}
    <div class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm overflow-hidden relative">
        {{-- Table Loader Overlay --}}
        <div wire:loading.flex wire:target="search,clearFilters"
             class="absolute inset-0 bg-white/60 dark:bg-gray-900/60 backdrop-blur-[1px] z-10 items-center justify-center">
            <svg class="animate-spin h-7 w-7 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50/70 dark:bg-gray-900/50 border-b border-gray-200/80 dark:border-gray-700/80 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">
                    <tr>
                        <th class="px-6 py-4 w-20">ID</th>
                        <th class="px-6 py-4">Role Title</th>
                        <th class="px-6 py-4">Assigned Permissions</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 text-sm text-gray-700 dark:text-gray-200">
                    @forelse($this->filteredRoles as $index => $role)
                        @php
                            $permissions = $role['permissions'] ?? [];
                            $permCount = count($permissions);
                            $permLabel = $permCount . ' ' . Str::plural('right', $permCount);
                            $roleId = $role['role_id'] ?? null;
                        @endphp
                        <tr wire:key="role-{{ $roleId ?? $index }}" class="hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-6 py-4 font-mono text-xs font-semibold text-gray-400 dark:text-gray-500">
                                #{{ sprintf('%02d', $index + 1) }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2.5">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-primary-50 dark:bg-primary-500/10 text-primary-700 dark:text-primary-300 border border-primary-200 dark:border-primary-500/30 uppercase tracking-wider">
                                        Custom
                                    </span>
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        {{ $role['name'] ?? 'Unnamed Role' }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div x-data="{ popover: false }" class="relative inline-block">
                                    <button type="button" @click="popover = !popover" @click.outside="popover = false"
                                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-300 transition">
                                        <span>{{ $permLabel }}</span>
                                        @if($permCount > 0)
                                            <svg class="w-3.5 h-3.5 text-gray-400 transition-transform duration-150" :class="{ 'rotate-180': popover }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        @endif
                                    </button>

                                    @if($permCount > 0)
                                        <div x-show="popover" x-cloak x-transition.opacity.duration.150ms
                                             class="absolute left-0 mt-2 w-72 p-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl z-30 text-xs space-y-2">
                                            <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-1.5">
                                                <span class="font-semibold text-gray-900 dark:text-white">Assigned Permissions</span>
                                                <span class="text-gray-400 font-mono">{{ $permCount }} total</span>
                                            </div>
                                            <div class="flex flex-wrap gap-1 max-h-48 overflow-y-auto pr-1">
                                                @foreach($permissions as $i => $perm)
                                                    <span wire:key="perm-{{ $index }}-{{ $i }}"
                                                          class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-2 py-0.5 rounded-md font-mono text-[11px] border border-gray-200/50 dark:border-gray-700/50">
                                                        {{ $perm }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('tenant.roles.edit', $index) }}" wire:navigate
                                       class="p-1.5 text-gray-500 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-950/50 rounded-lg transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95"
                                       title="Edit Role">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <button wire:click="deleteRole({{ $index }})"
                                            wire:confirm="Are you sure you want to delete '{{ $role['name'] ?? 'this role' }}'? Users assigned to this role will lose their access."
                                            wire:loading.attr="disabled"
                                            wire:target="deleteRole({{ $index }})"
                                            class="p-1.5 text-gray-500 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-lg transition disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 active:scale-95"
                                            title="Delete Role">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                                    <div class="p-3 bg-gray-100 dark:bg-gray-800 rounded-2xl mb-3 text-gray-400 dark:text-gray-500">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ $search ? 'No roles match your search' : 'No custom roles yet' }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ $search
                                            ? "Try a different search term or clear the filter."
                                            : "Create a role to give your staff specific access without making them full admins." }}
                                    </p>
                                    @if($search)
                                        <button type="button" wire:click="clearFilters" class="mt-3 text-xs text-primary-600 dark:text-primary-400 hover:underline font-semibold">
                                            Clear active search filter
                                        </button>
                                    @else
                                        <a href="{{ route('tenant.roles.create') }}" wire:navigate
                                           class="mt-3 inline-flex items-center gap-1.5 text-xs text-primary-600 dark:text-primary-400 hover:underline font-semibold">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            Create your first role
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>