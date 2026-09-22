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

    /**
     * CSV export is handled by a dedicated GET route. Livewire actions
     * cannot return streamed responses — the browser must navigate to
     * the export endpoint directly. We dispatch an event that the
     * module-level listener below turns into a navigation.
     */
    public function exportCsv(): void
    {
        $url = route('superadmin.roles.export', array_filter([
            'search' => $this->search,
        ]));

        $this->dispatch('open-url', url: $url);
    }
};
?>

@php $stats = $this->stats; @endphp

<div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6">

    {{-- ═══ Page header ═══ --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Access Control</span>
            </div>
            <h1 class="font-display text-3xl md:text-4xl font-semibold text-gray-900 dark:text-white">
                Role <em class="italic text-primary-600 dark:text-primary-400">Management</em>
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-2">
                Configure system security roles and manage tied user access permissions.
            </p>
        </div>
        <a href="{{ route('superadmin.roles.create') }}" wire:navigate
           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                  transition-all duration-200 active:scale-95
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <span>Create New Role</span>
        </a>
    </div>

    {{-- ═══ Flash: success ═══ --}}
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
                    class="inline-flex items-center justify-center h-7 w-7 rounded-md text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/10
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    {{-- ═══ Flash: error ═══ --}}
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
                    class="inline-flex items-center justify-center h-7 w-7 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    {{-- ═══ Compact KPI strip ═══ --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @php
            $kpis = [
                ['label' => 'Total Roles',      'value' => number_format($stats['total_roles']),       'dot' => 'bg-primary-500'],
                ['label' => 'Protected',        'value' => number_format($stats['protected_roles']),   'dot' => 'bg-purple-500'],
                ['label' => 'Admin Roles',      'value' => number_format($stats['admin_roles']),       'dot' => 'bg-emerald-500'],
                ['label' => 'System Rights',    'value' => number_format($stats['total_permissions']), 'dot' => 'bg-amber-500'],
            ];
        @endphp
        @foreach($kpis as $kpi)
            <div wire:key="kpi-{{ $loop->index }}"
                 class="bg-white dark:bg-gray-800/90 rounded-xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-3.5">
                <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full {{ $kpi['dot'] }}"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</span>
                </div>
                <p class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white tabular-nums">{{ $kpi['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ═══ Filter + Actions toolbar ═══ --}}
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4">
        <div class="flex flex-wrap gap-2 items-center">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Search roles by title…"
                       class="input w-full"
                       style="padding-left: 2.5rem;">
            </div>

            @if($search !== '')
                <button type="button" wire:click="clearFilters"
                        class="inline-flex items-center gap-1 h-9 px-3.5 rounded-full text-xs font-semibold uppercase tracking-wide
                               border border-rose-300 dark:border-rose-500/40
                               bg-white dark:bg-gray-800 text-rose-700 dark:text-rose-300
                               transition-all duration-200 active:scale-95
                               hover:bg-rose-50 dark:hover:bg-rose-500/10
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Clear
                </button>
            @endif

            <button type="button"
                    wire:click="exportCsv"
                    wire:loading.attr="disabled"
                    wire:target="exportCsv"
                    class="inline-flex items-center justify-center gap-2 h-9 px-3.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-xs font-semibold
                           transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                           disabled:opacity-60 disabled:cursor-not-allowed">
                <svg wire:loading.remove wire:target="exportCsv" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
                <span wire:loading wire:target="exportCsv" class="inline-flex items-center gap-1.5">
                    <svg class="animate-spin h-3.5 w-3.5 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Preparing…
                </span>
            </button>
        </div>
    </div>

    {{-- ═══ Card grid ═══ --}}
    @if($this->roles->isEmpty())
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-12 text-center">
            <div class="flex flex-col items-center max-w-md mx-auto">
                <div class="p-3 rounded-2xl bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
                <p class="mt-4 text-base font-semibold text-gray-900 dark:text-white">
                    No security roles found
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if($search !== '')
                        No roles match "{{ $search }}". Try adjusting or clearing your search.
                    @else
                        Get started by creating your first user access role.
                    @endif
                </p>
                <div class="mt-5 flex flex-wrap gap-2 justify-center">
                    @if($search !== '')
                        <button type="button" wire:click="clearFilters"
                                class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                       transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                            Clear Filter
                        </button>
                    @endif
                    <a href="{{ route('superadmin.roles.create') }}" wire:navigate
                       class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                              transition-all duration-200 active:scale-95
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span>Create New Role</span>
                    </a>
                </div>
            </div>
        </div>
    @else
        <div wire:loading.class="opacity-40 pointer-events-none"
             wire:target="search,clearFilters,delete,gotoPage,nextPage,previousPage"
             class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 transition-opacity duration-200">
            @foreach($this->roles as $role)
                @php
                    $isProtected     = $role->name === 'super-admin';
                    $permissionCount = $role->permissions_count;
                @endphp

                <article wire:key="role-{{ $role->id }}"
                         x-data="{ expanded: false }"
                         class="group relative bg-white dark:bg-gray-800/90 rounded-2xl border shadow-sm hover:shadow-md transition-all duration-200 flex flex-col overflow-hidden
                                {{ $isProtected ? 'border-purple-200 dark:border-purple-500/40' : 'border-gray-200/80 dark:border-gray-700/80' }}">

                    {{-- Top: icon + name + protected badge --}}
                    <div class="p-4 pb-3 flex items-start gap-3">
                        <div class="w-11 h-11 rounded-xl shrink-0 flex items-center justify-center
                                    {{ $isProtected
                                       ? 'bg-purple-50 dark:bg-purple-500/15 text-purple-600 dark:text-purple-400'
                                       : 'bg-primary-50 dark:bg-primary-500/15 text-primary-600 dark:text-primary-400' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                @if($isProtected)
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                @else
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                @endif
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-900 dark:text-white truncate leading-tight">
                                {{ ucwords(str_replace(['-', '_'], ' ', $role->name)) }}
                            </p>
                            <p class="text-[10px] font-mono text-gray-400 dark:text-gray-500 mt-0.5">
                                #{{ sprintf('%02d', $role->id) }}
                            </p>
                            @if($isProtected)
                                <span class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                                             bg-purple-100 dark:bg-purple-500/15 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-500/40">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                    Protected
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Permission count + toggle --}}
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700/60">
                        <button type="button"
                                x-on:click="expanded = !expanded"
                                :aria-expanded="expanded ? 'true' : 'false'"
                                @if($permissionCount === 0) disabled @endif
                                class="w-full inline-flex items-center justify-between gap-2 px-3 h-9 rounded-lg text-xs font-semibold
                                       bg-gray-50 dark:bg-gray-900/40 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700
                                       transition-all duration-200
                                       @if($permissionCount > 0) active:scale-[0.98] hover:bg-gray-100 dark:hover:bg-gray-800/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 @else cursor-default @endif">
                            <span class="inline-flex items-center gap-2 tabular-nums">
                                <svg class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                </svg>
                                {{ $permissionCount }} {{ \Illuminate\Support\Str::plural('permission', $permissionCount) }}
                            </span>
                            @if($permissionCount > 0)
                                <svg class="w-3.5 h-3.5 text-gray-400 transition-transform duration-200 motion-reduce:transition-none"
                                     :class="expanded ? 'rotate-180' : ''"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            @endif
                        </button>

                        {{-- Rule 69-compliant inline expand --}}
                        @if($permissionCount > 0)
                            <div x-cloak
                                 :class="expanded ? 'block mt-3' : 'hidden'"
                                 class="flex flex-wrap gap-1 max-h-40 overflow-y-auto pr-1">
                                @foreach($role->permissions as $permission)
                                    <span wire:key="perm-{{ $role->id }}-{{ $permission->id }}"
                                          class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium
                                                 bg-gray-100 dark:bg-gray-700/60 text-gray-700 dark:text-gray-300
                                                 border border-gray-200/80 dark:border-gray-600/60 font-mono">
                                        {{ $permission->name }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div class="mt-auto px-3 py-2.5 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-end gap-1">
                        @if(!$isProtected)
                            <a href="{{ route('superadmin.roles.edit', $role->id) }}" wire:navigate
                               aria-label="Edit {{ $role->name }}"
                               title="Edit role"
                               class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-500/10
                                      transition-all duration-200 active:scale-95
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>

                            {{-- Rule 19: Alpine confirm() --}}
                            <button type="button"
                                    x-on:click="if (confirm('Are you sure you want to delete {{ addslashes($role->name) }}? Users assigned to this role will lose associated privileges.')) $wire.delete({{ $role->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="delete"
                                    aria-label="Delete {{ $role->name }}"
                                    title="Delete role"
                                    class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10
                                           transition-all duration-200 active:scale-95
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        @else
                            <span class="text-[10px] text-gray-400 dark:text-gray-500 italic uppercase tracking-wider font-semibold">Immutable</span>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        @if($this->roles->hasPages())
            <div class="pt-2">
                {{ $this->roles->links() }}
            </div>
        @endif
    @endif
</div>

<script>
    // Bridge the Livewire `open-url` dispatch to a real browser
    // navigation so the CSV download endpoint streams correctly.
    if (! window.__roleExportUrlBound) {
        window.__roleExportUrlBound = true;

        window.addEventListener('open-url', (e) => {
            window.location.href = e.detail.url;
        });
    }
</script>