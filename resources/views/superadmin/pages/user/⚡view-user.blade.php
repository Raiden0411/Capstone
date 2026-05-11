<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\User;
use Spatie\Permission\Models\Role;

new 
#[Layout('superadmin.layouts.app')] 
#[Title('Global Users')] 
class extends Component {
    use WithPagination;

    public string $search = '';
    public string $roleFilter = '';
    public string $statusFilter = '';
    public array $selectedUsers = [];
    public bool $selectAll = false;

    public function updatingSearch() { $this->resetPage(); }
    public function updatingRoleFilter() { $this->resetPage(); }
    public function updatingStatusFilter() { $this->resetPage(); }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedUsers = $this->users->pluck('id')->map(fn($id) => (string) $id)->toArray();
        } else {
            $this->selectedUsers = [];
        }
    }

    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);
        if ($user->id === auth()->id() && $user->is_active) {
            session()->flash('error', 'You cannot deactivate your own account.');
            return;
        }
        $user->update(['is_active' => !$user->is_active]);
        session()->flash('message', "User '{$user->name}' " . ($user->is_active ? 'activated' : 'deactivated') . " successfully.");
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) {
            session()->flash('error', 'You cannot delete your own account.');
            return;
        }
        $user->delete();
        session()->flash('message', "User '{$user->name}' deleted successfully.");
    }

    public function bulkDelete()
    {
        if (empty($this->selectedUsers)) { session()->flash('error', 'No users selected.'); return; }
        if (in_array((string) auth()->id(), $this->selectedUsers)) {
            session()->flash('error', 'You cannot delete your own account.');
            return;
        }
        $count = User::whereIn('id', $this->selectedUsers)->delete();
        $this->selectedUsers = [];
        $this->selectAll = false;
        session()->flash('message', "{$count} user(s) deleted successfully.");
    }

    public function bulkActivate()
    {
        if (empty($this->selectedUsers)) { session()->flash('error', 'No users selected.'); return; }
        User::whereIn('id', $this->selectedUsers)->update(['is_active' => true]);
        session()->flash('message', count($this->selectedUsers) . ' user(s) activated.');
    }

    public function bulkDeactivate()
    {
        if (empty($this->selectedUsers)) { session()->flash('error', 'No users selected.'); return; }
        if (in_array((string) auth()->id(), $this->selectedUsers)) {
            session()->flash('error', 'You cannot deactivate your own account.');
            return;
        }
        User::whereIn('id', $this->selectedUsers)->update(['is_active' => false]);
        session()->flash('message', count($this->selectedUsers) . ' user(s) deactivated.');
    }

    public function clearFilters()
    {
        $this->reset(['search', 'roleFilter', 'statusFilter']);
    }

    #[Computed]
    public function availableRoles()
    {
        return Role::orderBy('name')->get();
    }

    #[Computed]
    public function users()
    {
        return User::with(['tenant', 'roles'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->roleFilter, function ($query) {
                $query->whereHas('roles', function ($q) {
                    $q->where('name', $this->roleFilter);
                });
            })
            ->when($this->statusFilter !== '', function ($query) {
                $query->where('is_active', $this->statusFilter === 'active');
            })
            ->latest()
            ->paginate(10);
    }
};
?>

<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-white/10">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-brand-600 dark:text-brand-400 flex items-center gap-2 mb-2">
                <span class="w-1.5 h-1.5 rounded-full bg-brand-500 shadow-[0_0_8px_var(--color-brand-500)]"></span>
                Super Admin · Users
            </p>
            <h1 class="font-display text-3xl md:text-4xl font-bold text-gray-900 dark:text-white">Global Users</h1>
            <p class="text-sm text-gray-500 dark:text-white/50 mt-1">Manage all platform administrators and tenant staff.</p>
        </div>
        <a href="{{ route('superadmin.users.create') }}" wire:navigate class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-sm font-semibold shadow-lg shadow-brand-500/20 transition hover:scale-105">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Add New User
        </a>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-400 font-medium">
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md text-sm text-red-700 dark:text-red-400 font-medium">
            {{ session('error') }}
        </div>
    @endif

    {{-- Filters --}}
    <div class="bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl p-4 shadow-sm dark:shadow-none">
        <div class="flex flex-col md:flex-row gap-4 items-start md:items-center">
            <div class="relative flex-1 w-full">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name or email..." 
                       class="w-full pl-10 pr-4 py-2.5 bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <div class="flex gap-2 w-full md:w-auto">
                <select wire:model.live="roleFilter" class="bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                    <option value="">All Roles</option>
                    @foreach($this->availableRoles as $role)
                        <option value="{{ $role->name }}">{{ ucwords(str_replace(['-', '_'], ' ', $role->name)) }}</option>
                    @endforeach
                </select>
                <select wire:model.live="statusFilter" class="bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                @if($search || $roleFilter || $statusFilter !== '')
                    <button wire:click="clearFilters" class="px-4 py-2 rounded-xl border border-gray-300 dark:border-white/20 text-gray-600 dark:text-white/60 hover:bg-gray-50 dark:hover:bg-white/10 text-xs font-semibold uppercase tracking-wider transition">
                        ✕ Clear
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Bulk Actions --}}
    @if(count($selectedUsers) > 0)
    <div class="bg-brand-50 dark:bg-brand-500/10 border border-brand-200 dark:border-brand-500/30 rounded-xl p-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
        <span class="text-sm text-brand-700 dark:text-brand-400 font-medium">{{ count($selectedUsers) }} user(s) selected</span>
        <div class="flex flex-wrap gap-2">
            <button wire:click="bulkActivate" class="text-xs bg-green-600 hover:bg-green-500 text-white px-3 py-1.5 rounded-lg transition-colors">Activate</button>
            <button wire:click="bulkDeactivate" class="text-xs bg-yellow-600 hover:bg-yellow-500 text-white px-3 py-1.5 rounded-lg transition-colors">Deactivate</button>
            <button wire:click="bulkDelete" wire:confirm="Delete selected users? This cannot be undone." class="text-xs bg-red-600 hover:bg-red-500 text-white px-3 py-1.5 rounded-lg transition-colors">Delete</button>
            <button wire:click="$set('selectedUsers', [])" class="text-xs text-gray-600 dark:text-white/60 border border-gray-300 dark:border-white/20 px-3 py-1.5 rounded-lg hover:bg-gray-50 dark:hover:bg-white/10 transition-colors">Cancel</button>
        </div>
    </div>
    @endif

    {{-- Users Table --}}
    <div class="bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl overflow-hidden shadow-sm dark:shadow-none">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs text-gray-500 dark:text-white/40 uppercase bg-gray-50 dark:bg-white/5 border-b border-gray-200 dark:border-white/10">
                    <tr>
                        <th class="px-4 py-4 w-10 hidden sm:table-cell">
                            <input type="checkbox" wire:model.live="selectAll" class="rounded border-gray-300 dark:border-white/20 bg-white dark:bg-white/5 text-brand-600 focus:ring-brand-500">
                        </th>
                        <th class="px-4 sm:px-6 py-4 font-medium">User</th>
                        <th class="px-4 sm:px-6 py-4 font-medium hidden md:table-cell">System Role</th>
                        <th class="px-4 sm:px-6 py-4 font-medium hidden lg:table-cell">Business / Tenant</th>
                        <th class="px-4 sm:px-6 py-4 font-medium">Status</th>
                        <th class="px-4 sm:px-6 py-4 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5 text-gray-700 dark:text-white/80">
                    @forelse ($this->users as $user)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                            <td class="px-4 py-4 hidden sm:table-cell">
                                <input type="checkbox" wire:model.live="selectedUsers" value="{{ $user->id }}" class="rounded border-gray-300 dark:border-white/20 bg-white dark:bg-white/5 text-brand-600 focus:ring-brand-500">
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-brand-400 to-brand-700 flex items-center justify-center text-white font-semibold text-sm shrink-0">
                                        {{ substr($user->name, 0, 1) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-medium text-gray-900 dark:text-white truncate">{{ $user->name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-white/40 truncate">{{ $user->email }}</div>
                                        <div class="md:hidden mt-1 flex flex-wrap gap-1">
                                            @foreach($user->roles as $role)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $role->name === 'super-admin' ? 'bg-purple-100 dark:bg-purple-500/20 text-purple-700 dark:text-purple-300' : 'bg-brand-100 dark:bg-brand-500/20 text-brand-700 dark:text-brand-300' }}">
                                                    {{ ucwords(str_replace(['-', '_'], ' ', $role->name)) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 hidden md:table-cell">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($user->roles as $role)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $role->name === 'super-admin' ? 'bg-purple-100 dark:bg-purple-500/20 text-purple-700 dark:text-purple-300' : 'bg-brand-100 dark:bg-brand-500/20 text-brand-700 dark:text-brand-300' }}">
                                            {{ ucwords(str_replace(['-', '_'], ' ', $role->name)) }}
                                        </span>
                                    @endforeach
                                    @if($user->roles->isEmpty())
                                        <span class="text-gray-400 dark:text-white/30 text-xs">No role</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 hidden lg:table-cell text-gray-600 dark:text-white/60">
                                @if($user->tenant)
                                    <span class="flex items-center gap-1">
                                        <svg class="w-4 h-4 text-gray-400 dark:text-white/30 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                        <span class="truncate max-w-[150px]">{{ $user->tenant->name }}</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-white/10 text-gray-600 dark:text-white/50">
                                        Platform Level
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                <button wire:click="toggleStatus({{ $user->id }})" wire:confirm="{{ $user->is_active ? 'Deactivate' : 'Activate' }} this user?" class="cursor-pointer">
                                    @if($user->is_active)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-500/20 text-green-700 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-500/30 transition">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1.5"></span> Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-500/20 text-red-700 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-500/30 transition">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-1.5"></span> Inactive
                                        </span>
                                    @endif
                                </button>
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('superadmin.users.edit', $user->id) }}" wire:navigate class="p-1.5 text-brand-600 dark:text-brand-400 hover:bg-brand-50 dark:hover:bg-brand-500/10 rounded-lg transition" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </a>
                                    @if($user->id !== auth()->id())
                                        <button wire:click="deleteUser({{ $user->id }})" wire:confirm="Are you sure you want to delete this user?" class="p-1.5 text-red-500 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition" title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-12 h-12 text-gray-300 dark:text-white/10 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    <span class="text-gray-500 dark:text-white/40">No users found{{ $search || $roleFilter || $statusFilter !== '' ? ' matching your filters' : '' }}.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($this->users->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                {{ $this->users->links() }}
            </div>
        @endif
    </div>
</div>