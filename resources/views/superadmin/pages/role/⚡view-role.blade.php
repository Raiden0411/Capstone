{{-- resources/views/superadmin/pages/role/⚡view-role.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

new 
#[Layout('superadmin.layouts.app')] 
#[Title('Role Management')] 
class extends Component {
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    public function mount()
    {
        // Defensive: the route already uses IsSuperAdmin middleware,
        // but reject any request that somehow bypassed it.
        if (!Auth::user()?->hasRole('super-admin')) {
            abort(403, 'Super-admin access only.');
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    private function getFilteredQuery()
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            });
    }

    #[Computed]
    public function roles()
    {
        return $this->getFilteredQuery()
            ->select('id', 'name', 'created_at')
            ->withCount('permissions')
            ->with(['permissions:id,name'])
            ->orderBy('id', 'desc')
            ->paginate(10);
    }

    #[Computed]
    public function stats(): array
    {
        $roleStats = Role::query()
            ->where('guard_name', 'web')
            ->selectRaw("
                COUNT(*) as total_roles,
                COALESCE(SUM(CASE WHEN name = 'super-admin' THEN 1 ELSE 0 END), 0) as protected_roles,
                COALESCE(SUM(CASE WHEN name != 'super-admin' THEN 1 ELSE 0 END), 0) as admin_roles
            ")
            ->first();

        return [
            'total_roles'       => (int) ($roleStats->total_roles ?? 0),
            'protected_roles'   => (int) ($roleStats->protected_roles ?? 0),
            'admin_roles'       => (int) ($roleStats->admin_roles ?? 0),
            'total_permissions' => Permission::where('guard_name', 'web')->count(),
        ];
    }

    public function delete(int $id): void
    {
        // Defensive authorization check
        if (!Auth::user()?->hasRole('super-admin')) {
            abort(403);
        }

        $role = Role::find($id);

        if (!$role) {
            session()->flash('error', 'Role not found. It may have already been deleted.');
            return;
        }

        if ($role->name === 'super-admin') {
            session()->flash('error', 'Security Alert: The system Super Admin role cannot be deleted.');
            return;
        }

        // Warn if the role is still assigned to users
        $assignedCount = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->count();

        try {
            DB::transaction(function () use ($role) {
                $role->delete();
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            });
        } catch (\Exception $e) {
            Log::error('Superadmin role delete failed: ' . $e->getMessage(), [
                'role_id'  => $id,
                'actor_id' => Auth::id(),
            ]);
            session()->flash('error', 'Failed to delete role. Please try again.');
            return;
        }

        $message = "Role '{$role->name}' deleted successfully.";
        if ($assignedCount > 0) {
            $message .= " {$assignedCount} user" . ($assignedCount === 1 ? '' : 's') . " lost access via this role.";
        }

        session()->flash('message', $message);
    }

    public function clearFilters(): void
    {
        $this->reset('search');
        $this->resetPage();
    }

    public function exportCsv()
    {
        $roles = $this->getFilteredQuery()
            ->select('id', 'name')
            ->withCount('permissions')
            ->orderBy('name')
            ->cursor();

        $filename = 'roles-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($roles) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Role Name', 'Permissions Count', 'Protected System Role']);

            foreach ($roles as $role) {
                fputcsv($out, [
                    $role->id,
                    $role->name,
                    $role->permissions_count,
                    $role->name === 'super-admin' ? 'Yes' : 'No',
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
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Role Management</h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">Configure system security roles and manage tied user access permissions.</p>
        </div>
        <a href="{{ route('superadmin.roles.create') }}" wire:navigate
           class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white font-semibold text-xs sm:text-sm shadow-sm transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Create New Role
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
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Roles</p>
                <div class="p-2 bg-primary-50 dark:bg-primary-950/50 rounded-xl text-primary-600 dark:text-primary-400 border border-primary-100 dark:border-primary-900/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white mt-3">{{ number_format($stats['total_roles']) }}</p>
        </div>

        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm transition-all duration-200 hover:border-purple-500/30">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Protected</p>
                <div class="p-2 bg-purple-50 dark:bg-purple-950/50 rounded-xl text-purple-600 dark:text-purple-400 border border-purple-100 dark:border-purple-900/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-purple-600 dark:text-purple-400 mt-3">{{ number_format($stats['protected_roles']) }}</p>
        </div>

        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm transition-all duration-200 hover:border-emerald-500/30">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Admin Roles</p>
                <div class="p-2 bg-emerald-50 dark:bg-emerald-950/50 rounded-xl text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-3">{{ number_format($stats['admin_roles']) }}</p>
        </div>

        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm transition-all duration-200 hover:border-amber-500/30">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">System Rights</p>
                <div class="p-2 bg-amber-50 dark:bg-amber-950/50 rounded-xl text-amber-600 dark:text-amber-400 border border-amber-100 dark:border-amber-900/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-3">{{ number_format($stats['total_permissions']) }}</p>
        </div>
    </div>

    {{-- Filter & Actions Toolbar --}}
    <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between bg-white dark:bg-gray-800/90 p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm">
        <div class="relative flex-1 max-w-md">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search roles by title..."
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
        <div wire:loading.flex wire:target="search,clearFilters,gotoPage,nextPage,previousPage"
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
                    @forelse($this->roles as $role)
                        @php
                            $permissionCount = $role->permissions_count;
                            $permissionLabel = $permissionCount . ' ' . \Illuminate\Support\Str::plural('right', $permissionCount);
                        @endphp
                        <tr wire:key="role-{{ $role->id }}" class="hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-6 py-4 font-mono text-xs font-semibold text-gray-400 dark:text-gray-500">
                                #{{ sprintf('%02d', $role->id) }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2.5">
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        {{ ucwords(str_replace(['-', '_'], ' ', $role->name)) }}
                                    </span>
                                    @if($role->name === 'super-admin')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800/50">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            Protected
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div x-data="{ popover: false }" class="relative inline-block">
                                    <button type="button" @click="popover = !popover" @click.outside="popover = false"
                                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-300 transition">
                                        <span>{{ $permissionLabel }}</span>
                                        @if($permissionCount > 0)
                                            <svg class="w-3.5 h-3.5 text-gray-400 transition-transform duration-150" :class="{ 'rotate-180': popover }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        @endif
                                    </button>

                                    @if($role->permissions->isNotEmpty())
                                        <div x-show="popover" x-cloak x-transition.opacity.duration.150ms
                                             class="absolute left-0 mt-2 w-72 p-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl z-30 text-xs space-y-2">
                                            <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-1.5">
                                                <span class="font-semibold text-gray-900 dark:text-white">Assigned Permissions</span>
                                                <span class="text-gray-400 font-mono">{{ $permissionCount }} total</span>
                                            </div>
                                            <div class="flex flex-wrap gap-1 max-h-48 overflow-y-auto pr-1">
                                                @foreach($role->permissions as $permission)
                                                    <span wire:key="perm-{{ $role->id }}-{{ $permission->id }}"
                                                          class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-2 py-0.5 rounded-md font-mono text-[11px] border border-gray-200/50 dark:border-gray-700/50">
                                                        {{ $permission->name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                @if($role->name !== 'super-admin')
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('superadmin.roles.edit', $role->id) }}" wire:navigate
                                           class="p-1.5 text-gray-500 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-950/50 rounded-lg transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95"
                                           title="Edit Role">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        <button wire:click="delete({{ $role->id }})"
                                                wire:confirm="Are you sure you want to delete '{{ $role->name }}'? Users assigned to this role will lose associated privileges."
                                                wire:loading.attr="disabled"
                                                wire:target="delete({{ $role->id }})"
                                                class="p-1.5 text-gray-500 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-lg transition disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 active:scale-95"
                                                title="Delete Role">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                @else
                                    <span class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Immutable</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                                    <div class="p-3 bg-gray-100 dark:bg-gray-800 rounded-2xl mb-3 text-gray-400 dark:text-gray-500">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">No security roles found</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ $search ? "No roles match your search title \"{$search}\"." : "Get started by creating your first user access role." }}
                                    </p>
                                    @if($search)
                                        <button type="button" wire:click="clearFilters" class="mt-3 text-xs text-primary-600 dark:text-primary-400 hover:underline font-semibold">
                                            Clear active search filter
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->roles->hasPages())
            <div class="px-6 py-4 border-t border-gray-200/80 dark:border-gray-700/80 bg-gray-50/50 dark:bg-gray-900/50">
                {{ $this->roles->links() }}
            </div>
        @endif
    </div>
</div>