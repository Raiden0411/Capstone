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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

new 
#[Layout('tenant.layouts.app')]
#[Title('Employees')]
class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $roleFilter = '';
    #[Url]
    public string $statusFilter = '';

    public function updatingSearch()       { $this->resetPage(); }
    public function updatingRoleFilter()   { $this->resetPage(); }
    public function updatingStatusFilter() { $this->resetPage(); }

    /**
     * Guard mutations. Uses getAllPermissions()->contains() instead of
     * hasPermissionTo() so a missing permission never throws — it just
     * returns false and the caller gets a clean 403.
     */
    protected function authorizeManage(): void
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        $canManage = $user->hasAnyRole(['admin', 'super-admin'])
            || $user->getAllPermissions()->contains('name', 'manage employees');

        if (!$canManage) {
            abort(403, 'You are not authorized to manage employees.');
        }
    }

    public function toggleActive(int $id)
    {
        $this->authorizeManage();

        $employee = Employee::withoutGlobalScope(TenantScope::class)
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->firstOrFail();

        // Prevent deactivating your own linked account
        if ($employee->user_id === Auth::id() && $employee->is_active) {
            session()->flash('error', 'You cannot deactivate your own account.');
            return;
        }

        $employee->update(['is_active' => !$employee->is_active]);

        session()->flash(
            'message',
            "{$employee->name} " . ($employee->is_active ? 'activated' : 'deactivated') . '.'
        );
    }

    public function delete(int $id)
    {
        $this->authorizeManage();

        $employee = Employee::withoutGlobalScope(TenantScope::class)
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->firstOrFail();

        // Guard: never let an admin delete their own employee record
        if ($employee->user_id === Auth::id()) {
            session()->flash('error', 'You cannot delete your own employee record.');
            return;
        }

        // Guard: never leave the tenant without an active manager
        if (strtolower($employee->role ?? '') === 'manager' && $employee->is_active) {
            $otherManagers = Employee::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', Auth::user()->tenant_id)
                ->where('id', '!=', $employee->id)
                ->where('is_active', true)
                ->whereRaw("LOWER(role) = 'manager'")
                ->count();

            if ($otherManagers === 0) {
                session()->flash('error', 'Cannot delete the last active manager.');
                return;
            }
        }

        try {
            $name = $employee->name;

            DB::transaction(function () use ($employee) {
                // Deleting the employee record does NOT delete the linked user.
                // The user may still be referenced elsewhere (bookings, another
                // employee record on a different tenant, etc.), and the admin
                // can remove the user from the superadmin panel if needed.
                $employee->delete();
            });

            session()->flash('message', "{$name} deleted.");
        } catch (\Exception $e) {
            Log::error('Employee delete failed: ' . $e->getMessage(), [
                'tenant_id'   => Auth::user()->tenant_id,
                'employee_id' => $id,
            ]);
            session()->flash('error', 'Failed to delete employee. Please try again.');
        }
    }

    public function clearFilters()
    {
        $this->reset(['search', 'roleFilter', 'statusFilter']);
        $this->resetPage();
    }

    #[Computed]
    public function employees()
    {
        return Employee::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->with([
                'user' => fn ($q) => $q->select('id', 'tenant_id', 'name', 'email', 'phone', 'avatar', 'is_active')
                    ->with('roles:id,name'),
            ])
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('name', 'like', '%'.$this->search.'%')
                       ->orWhere('code', 'like', '%'.$this->search.'%')
                       ->orWhere('phone', 'like', '%'.$this->search.'%')
                       ->orWhereHas('user', function ($uq) {
                           $uq->where('name', 'like', '%'.$this->search.'%')
                              ->orWhere('email', 'like', '%'.$this->search.'%')
                              ->orWhere('phone', 'like', '%'.$this->search.'%');
                       });
                });
            })
            ->when($this->roleFilter, fn ($q) => $q->where('role', $this->roleFilter))
            ->when($this->statusFilter === 'active',   fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByRaw("CASE WHEN LOWER(role) = 'manager' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->paginate(10);
    }

    #[Computed]
    public function roles()
    {
        return Employee::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereNotNull('role')
            ->where('role', '!=', '')
            ->distinct()
            ->orderBy('role')
            ->pluck('role');
    }

    #[Computed]
    public function stats()
    {
        $tid = Auth::user()->tenant_id;

        $agg = Employee::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tid)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN LOWER(role) = 'manager' AND is_active = 1 THEN 1 ELSE 0 END) as managers
            ")
            ->first();

        return [
            'total'    => (int) ($agg->total ?? 0),
            'active'   => (int) ($agg->active ?? 0),
            'inactive' => (int) ($agg->inactive ?? 0),
            'managers' => (int) ($agg->managers ?? 0),
        ];
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Team</span>
            </div>
            <h1 class="font-display text-3xl md:text-4xl font-semibold text-gray-900 dark:text-white">
                <em class="italic text-primary-600 dark:text-primary-400">Employees</em>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Manage your team members and their access.</p>
        </div>
        <a href="{{ route('tenant.employees.create') }}" wire:navigate
           class="btn-primary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Employee
        </a>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium flex items-center gap-2">
            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md text-sm text-red-700 dark:text-red-300 font-medium flex items-center gap-2">
            <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Stats --}}
    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total</p>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $s['total'] }}</p>
        </div>
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Active</p>
                <span class="w-2 h-2 rounded-full bg-green-500"></span>
            </div>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-2">{{ $s['active'] }}</p>
        </div>
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Inactive</p>
                <span class="w-2 h-2 rounded-full bg-gray-400"></span>
            </div>
            <p class="text-2xl font-bold text-gray-400 dark:text-gray-500 mt-2">{{ $s['inactive'] }}</p>
        </div>
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Managers</p>
                <svg class="w-4 h-4 text-primary-500" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2l2 4h4l-3 3 1 4-4-2-4 2 1-4-3-3h4l2-4z"/></svg>
            </div>
            <p class="text-2xl font-bold text-primary-600 dark:text-primary-400 mt-2">{{ $s['managers'] }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card p-4">
        <div class="flex flex-col md:flex-row gap-3 items-stretch md:items-center">
            <div class="relative flex-1">
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search by name, email, phone, or code…"
                       class="input pl-10">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <select wire:model.live="roleFilter" class="select w-full md:w-auto">
                <option value="">All Roles</option>
                @foreach($this->roles as $role)
                    <option value="{{ $role }}">{{ $role }}</option>
                @endforeach
            </select>
            <select wire:model.live="statusFilter" class="select w-full md:w-auto">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            @if($search || $roleFilter || $statusFilter)
                <button wire:click="clearFilters"
                        class="btn-secondary text-xs active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-1 whitespace-nowrap">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Clear
                </button>
            @endif
        </div>
    </div>

    {{-- Table --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto" wire:loading.class="opacity-50">
            <table class="w-full text-left">
                <thead class="border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Employee</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase hidden sm:table-cell">Code</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase hidden sm:table-cell">Job Title</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase hidden lg:table-cell">Account</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Status</th>
                        <th class="px-4 sm:px-6 py-4 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-200">
                    @forelse($this->employees as $employee)
                        @php
                            $isSelf    = $employee->user_id === Auth::id();
                            $isManager = strtolower($employee->role ?? '') === 'manager';
                            $avatarSrc = $employee->avatar ?: ($employee->user->avatar ?? null);
                        @endphp
                        <tr wire:key="employee-row-{{ $employee->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 sm:px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-lg bg-primary-50 dark:bg-primary-500/10 flex items-center justify-center text-primary-600 dark:text-primary-400 font-semibold text-sm shrink-0 overflow-hidden">
                                        @if($avatarSrc)
                                            <img src="{{ asset('storage/'. $avatarSrc) }}" alt="{{ $employee->name }}" class="h-full w-full object-cover">
                                        @else
                                            {{ strtoupper(substr($employee->name, 0, 1)) }}
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="font-medium text-gray-900 dark:text-white truncate">{{ $employee->name }}</p>
                                            @if($isManager)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-primary-100 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300 border border-primary-200 dark:border-primary-500/30">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2l2 4h4l-3 3 1 4-4-2-4 2 1-4-3-3h4l2-4z"/></svg>
                                                    Manager
                                                </span>
                                            @endif
                                            @if($isSelf)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-500/30">
                                                    You
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $employee->phone ?? '—' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 hidden sm:table-cell font-mono text-xs text-gray-500 dark:text-gray-400">
                                {{ $employee->code ?? '—' }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 hidden sm:table-cell">
                                {{ $employee->role ?? '—' }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 hidden lg:table-cell">
                                @if($employee->user)
                                    <div class="min-w-0">
                                        <p class="text-sm text-gray-700 dark:text-gray-300 truncate">{{ $employee->user->email }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                            {{ $employee->user->roles->pluck('name')->join(', ') ?: 'No roles' }}
                                        </p>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400 dark:text-gray-500">No account</span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                @if($isSelf)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-500/30 cursor-not-allowed opacity-70"
                                          title="You cannot deactivate your own account">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                        Active
                                    </span>
                                @else
                                    <button type="button"
                                            wire:click="toggleActive({{ $employee->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="toggleActive({{ $employee->id }})"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition-all duration-200 active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-60
                                                   {{ $employee->is_active
                                                       ? 'bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-500/30 hover:bg-green-200 dark:hover:bg-green-500/25'
                                                       : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 border border-gray-300 dark:border-gray-600 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $employee->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                        {{ $employee->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('tenant.employees.edit', $employee->id) }}" wire:navigate
                                       class="p-1.5 text-gray-400 dark:text-gray-500 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                       title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    @if(!$isSelf)
                                        <button type="button"
                                                wire:click="delete({{ $employee->id }})"
                                                wire:confirm="Delete {{ $employee->name }}? This action cannot be undone."
                                                wire:loading.attr="disabled"
                                                wire:target="delete({{ $employee->id }})"
                                                class="p-1.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/20 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-red-500/50 disabled:opacity-60"
                                                title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                <span class="text-sm">No employees found{{ ($search || $roleFilter || $statusFilter) ? ' matching your filters' : '' }}.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->employees->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $this->employees->links() }}
            </div>
        @endif
    </div>
</div>