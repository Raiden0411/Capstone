<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new 
#[Layout('superadmin.layouts.app')] 
#[Title('Manage Roles')] 
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
        return Role::with('permissions')
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->orderBy('id', 'desc')
            ->paginate(10);
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
};
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto text-gray-900 dark:text-white space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Manage Roles</h1>
            <p class="text-gray-500 dark:text-slate-400 mt-1">Global directory of system roles available to all businesses.</p>
        </div>
        <a href="{{ route('superadmin.roles.create') }}" wire:navigate class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-xl shadow-sm transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Add New Role
        </a>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="p-4 bg-green-50 dark:bg-green-500/10 border-l-4 border-green-500 rounded-md shadow-sm">
            <p class="text-sm text-green-700 dark:text-green-400 font-medium">{{ session('message') }}</p>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="p-4 bg-red-50 dark:bg-red-500/10 border-l-4 border-red-500 rounded-md shadow-sm">
            <p class="text-sm text-red-700 dark:text-red-400 font-medium">{{ session('error') }}</p>
        </div>
    @endif

    {{-- Search Bar --}}
    <div class="relative max-w-md">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search roles by name..." 
               class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 rounded-lg focus:ring-blue-500 focus:border-blue-500 shadow-sm">
        <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
    </div>

    {{-- Roles Table --}}
    <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700/30">
                <thead class="bg-gray-50 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 sm:px-6 py-4 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">ID</th>
                        <th class="px-4 sm:px-6 py-4 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Role Name</th>
                        <th class="px-4 sm:px-6 py-4 text-left text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider hidden sm:table-cell">Permissions</th>
                        <th class="px-4 sm:px-6 py-4 text-right text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-slate-700/30 text-gray-700 dark:text-slate-300">
                    @forelse($this->roles as $role)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-slate-400 font-mono">#{{ $role->id }}</td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $role->name === 'super-admin' ? 'bg-purple-100 dark:bg-purple-500/20 text-purple-800 dark:text-purple-400 border border-purple-200 dark:border-purple-500/30' : 'bg-blue-100 dark:bg-blue-500/20 text-blue-800 dark:text-blue-400 border border-blue-200 dark:border-blue-500/30' }}">
                                        {{ ucwords(str_replace(['-', '_'], ' ', $role->name)) }}
                                    </span>
                                    @if($role->name === 'super-admin')
                                        <span class="text-xs text-purple-500 dark:text-purple-400 font-medium">🔒 Protected</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-sm hidden sm:table-cell">
                                <div class="flex items-center gap-2">
                                    <span class="bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300 py-1 px-3 rounded-full text-xs font-medium border border-gray-200 dark:border-slate-700/50">
                                        {{ $role->permissions->count() }} access rights
                                    </span>
                                    @if($role->permissions->isNotEmpty())
                                        <div class="relative group">
                                            <button type="button" class="text-gray-400 dark:text-slate-400 hover:text-gray-600 dark:hover:text-slate-300">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </button>
                                            <div class="absolute z-10 left-0 mt-2 w-64 p-3 bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all">
                                                <p class="text-xs font-semibold text-gray-500 dark:text-slate-400 mb-2">Assigned Permissions:</p>
                                                <div class="flex flex-wrap gap-1 max-h-40 overflow-y-auto">
                                                    @foreach($role->permissions->take(15) as $permission)
                                                        <span class="text-xs bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300 px-2 py-0.5 rounded">{{ $permission->name }}</span>
                                                    @endforeach
                                                    @if($role->permissions->count() > 15)
                                                        <span class="text-xs text-gray-400 dark:text-slate-500">+{{ $role->permissions->count() - 15 }} more</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                @if($role->name !== 'super-admin')
                                    <a href="{{ route('superadmin.roles.edit', $role->id) }}" wire:navigate class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 mr-3 transition-colors inline-flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        Edit
                                    </a>
                                    <button wire:click="delete({{ $role->id }})" wire:confirm="Are you sure you want to delete this role? Any users assigned to this role will lose their permissions." class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors inline-flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        Delete
                                    </button>
                                @else
                                    <span class="text-gray-400 dark:text-slate-500 text-sm italic">Protected</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-12 h-12 text-gray-300 dark:text-slate-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 9.75h4.5m-4.5 4.5h4.5M6.75 21v-2.25M17.25 21v-2.25M3.75 9.75h16.5M3.75 14.25h16.5M6.75 3.75v2.25M17.25 3.75v2.25"></path></svg>
                                    <span class="text-gray-500 dark:text-slate-400">No roles found{{ $search ? ' matching "' . $search . '"' : '' }}.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($this->roles->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-slate-700/50 bg-gray-50 dark:bg-slate-800/50">
                {{ $this->roles->links() }}
            </div>
        @endif
    </div>
</div>