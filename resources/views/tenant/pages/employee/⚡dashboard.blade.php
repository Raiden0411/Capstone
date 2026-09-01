{{-- resources/views/tenant/pages/employee/⚡view-employee.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Employee;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;

new 
#[Layout('tenant.layouts.app')]
#[Title('Employees')]
class extends Component {
    use WithPagination;

    public string $search = '';
    public string $roleFilter = '';

    public function updatingSearch() { $this->resetPage(); }
    public function updatingRoleFilter() { $this->resetPage(); }

    public function toggleActive(int $id)
    {
        $employee = Employee::withoutGlobalScope(TenantScope::class)
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->firstOrFail();

        $employee->update(['is_active' => !$employee->is_active]);

        session()->flash('message', "{$employee->name} " . ($employee->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function delete(int $id)
    {
        $employee = Employee::withoutGlobalScope(TenantScope::class)
            ->where('id', $id)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->firstOrFail();

        $name = $employee->name;
        $employee->delete();

        session()->flash('message', "{$name} deleted.");
    }

    public function clearFilters()
    {
        $this->reset(['search', 'roleFilter']);
        $this->resetPage();
    }

    #[Computed]
    public function employees()
    {
        return Employee::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->with(['user.roles']) // eager load roles to avoid N+1 in table
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
            ->when($this->roleFilter, fn($q) => $q->where('role', $this->roleFilter))
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
            ->distinct()
            ->orderBy('role')
            ->pluck('role');
    }

    #[Computed]
    public function stats()
    {
        $tid = Auth::user()->tenant_id;
        return [
            'total'    => Employee::where('tenant_id', $tid)->count(),
            'active'   => Employee::where('tenant_id', $tid)->where('is_active', true)->count(),
            'inactive' => Employee::where('tenant_id', $tid)->where('is_active', false)->count(),
        ];
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Employees</h1>
        </div>
        <a href="{{ route('tenant.employees.create') }}" wire:navigate
           class="btn-primary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Employee
        </a>
    </div>

    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium flex items-center gap-2">
            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('message') }}
        </div>
    @endif

    {{-- Stats --}}
    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-3 gap-4">
        <div class="card p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $s['total'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Active</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-2">{{ $s['active'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Inactive</p>
            <p class="text-2xl font-bold text-gray-400 dark:text-gray-500 mt-2">{{ $s['inactive'] }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col md:flex-row gap-4 items-start md:items-center">
        <div class="relative flex-1 w-full">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name, email, phone, or code..."
                   class="input pl-10">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
        <div class="flex flex-wrap gap-2 w-full md:w-auto">
            <select wire:model.live="roleFilter" class="select w-full sm:w-auto">
                <option value="">All Roles</option>
                @foreach($this->roles as $role)
                    <option value="{{ $role }}">{{ $role }}</option>
                @endforeach
            </select>
            @if($search || $roleFilter)
                <button wire:click="clearFilters"
                        class="btn-secondary text-xs active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Clear
                </button>
            @endif
        </div>
    </div>

    {{-- Table --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
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
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 sm:px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-lg bg-primary-50 dark:bg-primary-500/10 flex items-center justify-center text-primary-600 dark:text-primary-400 font-semibold text-sm shrink-0 overflow-hidden">
                                        @if($employee->avatar)
                                            <img src="{{ asset('storage/'. $employee->avatar) }}" alt="{{ $employee->name }}" class="h-full w-full object-cover">
                                        @else
                                            {{ strtoupper(substr($employee->name, 0, 1)) }}
                                        @endif
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="font-medium text-gray-900 dark:text-white">{{ $employee->name }}</p>
                                            @if(strtolower($employee->role ?? '') === 'manager')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-primary-100 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300 border border-primary-200 dark:border-primary-500/30">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2l2 4h4l-3 3 1 4-4-2-4 2 1-4-3-3h4l2-4z"/></svg>
                                                    Manager
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $employee->phone ?? '—' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 hidden sm:table-cell font-mono text-xs text-gray-500 dark:text-gray-400">{{ $employee->code ?? '—' }}</td>
                            <td class="px-4 sm:px-6 py-4 hidden sm:table-cell">{{ $employee->role ?? '—' }}</td>
                            <td class="px-4 sm:px-6 py-4 hidden lg:table-cell">
                                @if($employee->user)
                                    <div>
                                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $employee->user->email }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $employee->user->getRoleNames()->join(', ') ?: 'No roles' }}
                                        </p>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400 dark:text-gray-500">No account</span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                <button wire:click="toggleActive({{ $employee->id }})"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium transition-all duration-200 active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50
                                               {{ $employee->is_active ? 'bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-500/30 hover:bg-green-200 dark:hover:bg-green-500/25' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 border border-gray-300 dark:border-gray-600 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                    {{ $employee->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('tenant.employees.edit', $employee->id) }}" wire:navigate
                                       class="p-1.5 text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-500/10 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <button wire:click="delete({{ $employee->id }})" wire:confirm="Delete this employee?"
                                            class="p-1.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-red-500/50" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                <span class="text-sm">No employees found{{ $search || $roleFilter ? ' matching your filters' : '' }}.</span>
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