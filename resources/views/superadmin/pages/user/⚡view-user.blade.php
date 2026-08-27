{{-- resources/views/superadmin/pages/user/⚡view-user.blade.php --}}
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
#[Title('Users')] 
class extends Component {
    use WithPagination;

    public string $search = '';
    public string $roleFilter = '';
    public string $statusFilter = '';
    public string $sortOption = 'latest';
    public int $perPage = 10;
    public array $selectedUsers = [];
    public bool $selectAll = false;

    public function updatingSearch() { $this->resetPage(); }
    public function updatingRoleFilter() { $this->resetPage(); }
    public function updatingStatusFilter() { $this->resetPage(); }
    public function updatingSortOption() { $this->resetPage(); }
    public function updatingPerPage() { $this->resetPage(); }

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
        $this->reset(['search', 'roleFilter', 'statusFilter', 'sortOption']);
    }

    public function exportCsv()
    {
        $users = User::with(['tenant', 'roles'])
            ->when($this->search, fn($q) => $q->where(function($sub) {
                $sub->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%');
            }))
            ->when($this->roleFilter, fn($q) => $q->whereHas('roles', fn($r) => $r->where('name', $this->roleFilter)))
            ->when($this->statusFilter !== '', fn($q) => $q->where('is_active', $this->statusFilter === 'active'))
            ->orderBy('name')
            ->get();

        $filename = 'users-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($users) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Email', 'Role', 'Business', 'Status', 'Created']);
            foreach ($users as $u) {
                fputcsv($out, [
                    $u->name,
                    $u->email,
                    $u->roles->first()?->name ?? 'No role',
                    $u->tenant?->name ?? 'Platform Level',
                    $u->is_active ? 'Active' : 'Inactive',
                    $u->created_at->format('Y-m-d'),
                ]);
            }
            fclose($out);
        }, $filename);
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
            ->when($this->sortOption === 'name_asc', fn($q) => $q->orderBy('name', 'asc'))
            ->when($this->sortOption === 'name_desc', fn($q) => $q->orderBy('name', 'desc'))
            ->when($this->sortOption === 'email_asc', fn($q) => $q->orderBy('email', 'asc'))
            ->when($this->sortOption === 'oldest', fn($q) => $q->orderBy('created_at', 'asc'))
            ->when($this->sortOption === 'latest', fn($q) => $q->orderBy('created_at', 'desc'))
            ->paginate($this->perPage);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Users</h1>
        </div>
        <a href="{{ route('superadmin.users.create') }}" wire:navigate
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-lg shadow-primary-500/20 transition hover:scale-105 focus-visible:ring-2 focus-visible:ring-primary-500/50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add User
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
    @php
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $platformUsers = User::whereNull('tenant_id')->count();
        $businessUsers = User::whereNotNull('tenant_id')->count();
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Users</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $totalUsers }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Active</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-2">{{ $activeUsers }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Platform Users</p>
            <p class="text-2xl font-bold text-primary-600 dark:text-primary-400 mt-2">{{ $platformUsers }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Business Users</p>
            <p class="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-2">{{ $businessUsers }}</p>
        </div>
    </div>

    {{-- Filters Panel --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-4 shadow-sm space-y-4">
        <div class="flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search by name or email..."
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 pl-10 pr-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
            </div>
            <select wire:model.live="roleFilter"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                <option value="">All Roles</option>
                @foreach($this->availableRoles as $role)
                    <option value="{{ $role->name }}">{{ ucwords(str_replace(['-', '_'], ' ', $role->name)) }}</option>
                @endforeach
            </select>
            <select wire:model.live="statusFilter"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <select wire:model.live="sortOption"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                <option value="latest">Newest first</option>
                <option value="oldest">Oldest first</option>
                <option value="name_asc">Name A–Z</option>
                <option value="name_desc">Name Z–A</option>
                <option value="email_asc">Email A–Z</option>
            </select>
            <select wire:model.live="perPage"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
            @if($search || $roleFilter || $statusFilter !== '')
                <button wire:click="clearFilters"
                        class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 text-xs font-semibold transition focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    ✕ Clear
                </button>
            @endif
        </div>

        {{-- Bulk Actions & Summary --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <span class="font-semibold text-gray-900 dark:text-white">{{ $this->users->total() }}</span> users
            </div>
            <div class="flex gap-2">
                <button wire:click="exportCsv"
                        class="px-4 py-2 rounded-xl bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Export CSV
                </button>
                @if(count($selectedUsers) > 0)
                    <button wire:click="bulkActivate"
                            class="px-4 py-2 rounded-xl bg-green-100 dark:bg-green-500/15 border border-green-200 dark:border-green-500/30 text-green-700 dark:text-green-300 text-sm font-semibold hover:bg-green-200 dark:hover:bg-green-500/25 transition focus-visible:ring-2 focus-visible:ring-green-500/50">
                        Activate ({{ count($selectedUsers) }})
                    </button>
                    <button wire:click="bulkDeactivate"
                            class="px-4 py-2 rounded-xl bg-amber-100 dark:bg-amber-500/15 border border-amber-200 dark:border-amber-500/30 text-amber-700 dark:text-amber-300 text-sm font-semibold hover:bg-amber-200 dark:hover:bg-amber-500/25 transition focus-visible:ring-2 focus-visible:ring-amber-500/50">
                        Deactivate
                    </button>
                    <button wire:click="bulkDelete" wire:confirm="Delete selected users? This cannot be undone."
                            class="px-4 py-2 rounded-xl bg-red-100 dark:bg-red-500/15 border border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-300 text-sm font-semibold hover:bg-red-200 dark:hover:bg-red-500/25 transition focus-visible:ring-2 focus-visible:ring-red-500/50">
                        Delete
                    </button>
                    <button wire:click="$set('selectedUsers', [])"
                            class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        Cancel
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Users Table --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="border-b border-gray-200 dark:border-gray-700 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="px-4 sm:px-6 py-4 w-10 hidden sm:table-cell">
                            <input type="checkbox" wire:model.live="selectAll" class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500">
                        </th>
                        <th class="px-4 sm:px-6 py-4 font-medium">User</th>
                        <th class="px-4 sm:px-6 py-4 font-medium hidden md:table-cell">System Role</th>
                        <th class="px-4 sm:px-6 py-4 font-medium hidden lg:table-cell">Business</th>
                        <th class="px-4 sm:px-6 py-4 font-medium">Status</th>
                        <th class="px-4 sm:px-6 py-4 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-200">
                    @forelse ($this->users as $user)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 py-4 hidden sm:table-cell">
                                <input type="checkbox" wire:model.live="selectedUsers" value="{{ $user->id }}" class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500">
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-primary-50 dark:bg-primary-500/10 border border-primary-200 dark:border-primary-500/20 flex items-center justify-center text-primary-700 dark:text-primary-300 font-semibold text-sm shrink-0">
                                        {{ substr($user->name, 0, 1) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-medium text-gray-900 dark:text-white truncate">{{ $user->name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $user->email }}</div>
                                        <div class="md:hidden mt-1 flex flex-wrap gap-1">
                                            @foreach($user->roles as $role)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $role->name === 'super-admin' ? 'bg-purple-100 dark:bg-purple-500/15 text-purple-700 dark:text-purple-300' : 'bg-primary-100 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300' }}">
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
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $role->name === 'super-admin' ? 'bg-purple-100 dark:bg-purple-500/15 text-purple-700 dark:text-purple-300' : 'bg-primary-100 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300' }}">
                                            {{ ucwords(str_replace(['-', '_'], ' ', $role->name)) }}
                                        </span>
                                    @endforeach
                                    @if($user->roles->isEmpty())
                                        <span class="text-gray-400 dark:text-gray-500 text-xs">No role</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 hidden lg:table-cell text-gray-600 dark:text-gray-300">
                                @if($user->tenant)
                                    <span class="flex items-center gap-1">
                                        <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        <span class="truncate max-w-[150px]">{{ $user->tenant->name }}</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                        Platform Level
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                <button wire:click="toggleStatus({{ $user->id }})"
                                        wire:confirm="{{ $user->is_active ? 'Deactivate' : 'Activate' }} this user?"
                                        class="cursor-pointer focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                                    @if($user->is_active)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-500/30 hover:bg-green-200 dark:hover:bg-green-500/25 transition">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1.5"></span> Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-500/15 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-500/30 hover:bg-red-200 dark:hover:bg-red-500/25 transition">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-1.5"></span> Inactive
                                        </span>
                                    @endif
                                </button>
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('superadmin.users.edit', $user->id) }}" wire:navigate
                                       class="p-1.5 text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-500/10 rounded-lg transition focus-visible:ring-2 focus-visible:ring-primary-500/50" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    @if($user->id !== auth()->id())
                                        <button wire:click="deleteUser({{ $user->id }})"
                                                wire:confirm="Are you sure you want to delete this user?"
                                                class="p-1.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition focus-visible:ring-2 focus-visible:ring-red-500/50" title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    <span class="text-gray-500 dark:text-gray-400">No users found{{ $search || $roleFilter || $statusFilter !== '' ? ' matching your filters' : '' }}.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->users->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                {{ $this->users->links() }}
            </div>
        @endif
    </div>
</div>