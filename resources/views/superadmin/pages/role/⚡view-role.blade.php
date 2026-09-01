{{-- resources/views/superadmin/pages/role/⚡view-role.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

new 
#[Layout('superadmin.layouts.app')] 
#[Title('Roles')] 
class extends Component {
    use WithPagination;

    public string $search = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    #[Computed]
    public function roles()
    {
        return Role::query()
            ->select('id', 'name')
            ->with(['permissions:id,name'])
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->orderBy('id', 'desc')
            ->paginate(10);
    }

    #[Computed]
    public function stats()
    {
        return Role::query()
            ->selectRaw('
                COUNT(*) as total_roles,
                COALESCE(SUM(name = ?), 0) as protected_roles,
                COALESCE(SUM(name != ?), 0) as admin_roles
            ', ['super-admin', 'super-admin'])
            ->first();
    }

    public function delete($id)
    {
        $role = Role::findOrFail($id);

        if ($role->name === 'super-admin') {
            session()->flash('error', 'Security Alert: The system Super Admin role cannot be deleted.');
            return;
        }

        $role->delete();
        session()->flash('message', "Role '{$role->name}' deleted successfully.");
    }

    public function exportCsv()
    {
        $roles = Role::query()
            ->select('id', 'name')
            ->withCount('permissions')
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy('name')
            ->cursor();

        $filename = 'roles-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($roles) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Role', 'Permissions Count', 'Protected']);
            foreach ($roles as $role) {
                fputcsv($out, [
                    $role->name,
                    $role->permissions_count,
                    $role->name === 'super-admin' ? 'Yes' : 'No',
                ]);
            }
            fclose($out);
        }, $filename);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Roles</h1>
        </div>
        <a href="{{ route('superadmin.roles.create') }}" wire:navigate
           class="btn-primary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Role
        </a>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium">
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md text-sm text-red-700 dark:text-red-300 font-medium">
            {{ session('error') }}
        </div>
    @endif

    {{-- Quick Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Roles</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $this->stats->total_roles ?? 0 }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Protected</p>
            <p class="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-2">{{ $this->stats->protected_roles ?? 0 }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Admin Roles</p>
            <p class="text-2xl font-bold text-primary-600 dark:text-primary-400 mt-2">{{ $this->stats->admin_roles ?? 0 }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Permissions</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-2">{{ Permission::count() }}</p>
        </div>
    </div>

    {{-- Search & Export --}}
    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
        <div class="relative flex-1 max-w-md w-full">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search roles..."
                   class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 pl-10 pr-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
        </div>
        <button wire:click="exportCsv" wire:loading.attr="disabled"
                class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
            <span wire:loading wire:target="exportCsv" class="inline-flex items-center gap-1">
                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                Exporting…
            </span>
        </button>
    </div>

    {{-- Roles Table --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left">
                <thead class="border-b border-gray-200 dark:border-gray-700 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 sm:px-6 py-4 font-medium">ID</th>
                        <th class="px-4 sm:px-6 py-4 font-medium">Role Name</th>
                        <th class="px-4 sm:px-6 py-4 font-medium hidden sm:table-cell">Permissions</th>
                        <th class="px-4 sm:px-6 py-4 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-200">
                    @forelse($this->roles as $role)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-400 dark:text-gray-500 font-mono">#{{ $role->id }}</td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                 {{ $role->name === 'super-admin' ? 'bg-purple-100 dark:bg-purple-500/15 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-500/30' : 'bg-primary-100 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300 border border-primary-200 dark:border-primary-500/30' }}">
                                        {{ ucwords(str_replace(['-', '_'], ' ', $role->name)) }}
                                    </span>
                                    @if($role->name === 'super-admin')
                                        <span class="inline-flex items-center gap-1 text-xs text-purple-600 dark:text-purple-400 font-medium">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            Protected
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-sm hidden sm:table-cell">
                                <div class="flex items-center gap-2">
                                    <span class="bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 py-1 px-3 rounded-full text-xs font-medium">
                                        {{ $role->permissions->count() }} access rights
                                    </span>
                                    @if($role->permissions->isNotEmpty())
                                        <div class="relative group">
                                            <button type="button" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95 transition-transform">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </button>
                                            <div class="absolute z-10 left-0 mt-2 w-64 p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all">
                                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">Assigned Permissions:</p>
                                                <div class="flex flex-wrap gap-1 max-h-40 overflow-y-auto">
                                                    @foreach($role->permissions->take(15) as $permission)
                                                        <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 px-2 py-0.5 rounded">{{ $permission->name }}</span>
                                                    @endforeach
                                                    @if($role->permissions->count() > 15)
                                                        <span class="text-xs text-gray-500 dark:text-gray-400">+{{ $role->permissions->count() - 15 }} more</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                @if($role->name !== 'super-admin')
                                    <a href="{{ route('superadmin.roles.edit', $role->id) }}" wire:navigate
                                       class="inline-flex items-center gap-1 p-1.5 text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-500/10 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </a>
                                    <button wire:click="delete({{ $role->id }})"
                                            wire:confirm="Are you sure you want to delete this role? Any users assigned to this role will lose their permissions."
                                            class="inline-flex items-center gap-1 p-1.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-red-500/50" title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500 text-sm italic">Protected</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 9.75h4.5m-4.5 4.5h4.5M6.75 21v-2.25M17.25 21v-2.25M3.75 9.75h16.5M3.75 14.25h16.5M6.75 3.75v2.25M17.25 3.75v2.25"/></svg>
                                    <span class="text-gray-500 dark:text-gray-400">No roles found{{ $search ? ' matching "' . $search . '"' : '' }}.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->roles->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                {{ $this->roles->links() }}
            </div>
        @endif
    </div>
</div>