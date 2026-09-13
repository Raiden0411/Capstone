<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

new
#[Layout('superadmin.layouts.app')]
#[Title('Users')]
class extends Component {
    use WithPagination;

    #[Url(keep: true)]
    public string $search = '';

    #[Url(keep: true)]
    public string $roleFilter = '';

    #[Url(keep: true)]
    public string $statusFilter = '';

    #[Url(keep: true)]
    public string $sortOption = 'latest';

    #[Url(keep: true)]
    public int $perPage = 10;

    /** @var array<int, int|string> */
    public array $selectedUsers = [];

    public bool $selectAll = false;

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');
    }

    // ─────────────────────────────────────────────────────────
    //  Selection / filter resets
    // ─────────────────────────────────────────────────────────

    public function updatedSearch(): void       { $this->resetTableSelection(); }
    public function updatedRoleFilter(): void   { $this->resetTableSelection(); }
    public function updatedStatusFilter(): void { $this->resetTableSelection(); }
    public function updatedSortOption(): void   { $this->resetTableSelection(); }
    public function updatedPerPage(): void      { $this->resetTableSelection(); }

    /**
     * Clear selection whenever the page changes — otherwise a user can
     * "select all" on page 1, navigate to page 2, and unknowingly carry
     * the previous page's selection into a bulk action.
     */
    public function updatedPage(): void
    {
        $this->selectedUsers = [];
        $this->selectAll = false;
    }

    protected function resetTableSelection(): void
    {
        $this->resetPage();
        $this->selectedUsers = [];
        $this->selectAll = false;
    }

    public function updatedSelectAll(bool $value): void
    {
        $this->selectedUsers = $value
            ? $this->users->pluck('id')->map(fn ($id) => (string) $id)->all()
            : [];
    }

    public function updatedSelectedUsers(): void
    {
        $this->selectAll = count($this->selectedUsers) === $this->users->count()
            && $this->users->count() > 0;
    }

    public function clearSelection(): void
    {
        $this->selectedUsers = [];
        $this->selectAll = false;
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'roleFilter', 'statusFilter', 'sortOption']);
        $this->resetTableSelection();
    }

    // ─────────────────────────────────────────────────────────
    //  Query
    // ─────────────────────────────────────────────────────────

    protected function getUserQuery(): Builder
    {
        return User::query()
            ->with([
                'tenant:id,name',
                'roles:id,name',
            ])
            ->select('id', 'name', 'email', 'phone', 'avatar', 'is_active', 'created_at', 'tenant_id')
            ->when($this->search !== '', function (Builder $query) {
                $q = trim($this->search);
                $query->where(function (Builder $sub) use ($q) {
                    $sub->where('name', 'like', '%' . $q . '%')
                        ->orWhere('email', 'like', '%' . $q . '%');
                });
            })
            ->when($this->roleFilter !== '', function (Builder $query) {
                $query->whereHas('roles', fn (Builder $q) => $q->where('name', $this->roleFilter));
            })
            ->when($this->statusFilter !== '', function (Builder $query) {
                $query->where('is_active', $this->statusFilter === 'active');
            })
            ->when($this->sortOption === 'name_asc',  fn (Builder $q) => $q->orderBy('name', 'asc'))
            ->when($this->sortOption === 'name_desc', fn (Builder $q) => $q->orderBy('name', 'desc'))
            ->when($this->sortOption === 'email_asc', fn (Builder $q) => $q->orderBy('email', 'asc'))
            ->when($this->sortOption === 'oldest',    fn (Builder $q) => $q->orderBy('created_at', 'asc'))
            ->when($this->sortOption === 'latest',    fn (Builder $q) => $q->orderBy('created_at', 'desc'));
    }

    // ─────────────────────────────────────────────────────────
    //  Single-user actions
    // ─────────────────────────────────────────────────────────

    public function toggleStatus(int $id): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');

        if ($id === Auth::id()) {
            session()->flash('error', 'You cannot change your own account status.');
            return;
        }

        try {
            $user = DB::transaction(function () use ($id) {
                $locked = User::query()->lockForUpdate()->find($id);

                if (!$locked) {
                    return null;
                }

                $locked->update(['is_active' => !$locked->is_active]);

                return $locked;
            });
        } catch (\Throwable $e) {
            Log::error('Superadmin toggle status failed: ' . $e->getMessage(), [
                'user_id'  => $id,
                'actor_id' => Auth::id(),
            ]);
            session()->flash('error', 'Failed to update user status. Please try again.');
            return;
        }

        if (!$user) {
            session()->flash('error', 'User not found.');
            return;
        }

        session()->flash(
            'message',
            "User '{$user->name}' " . ($user->is_active ? 'activated' : 'deactivated') . ' successfully.'
        );
    }

    public function deleteUser(int $id): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');

        if ($id === Auth::id()) {
            session()->flash('error', 'You cannot delete your own account.');
            return;
        }

        $user = User::query()->select('id', 'name')->find($id);
        if (!$user) {
            session()->flash('error', 'User not found.');
            return;
        }

        $name = $user->name;

        try {
            DB::transaction(function () use ($id): void {
                User::query()->whereKey($id)->delete();
            });

            session()->flash('message', "User '{$name}' deleted successfully.");
        } catch (\Throwable $e) {
            Log::error('Superadmin user delete failed: ' . $e->getMessage(), [
                'user_id'  => $id,
                'actor_id' => Auth::id(),
            ]);
            session()->flash('error', 'Failed to delete user. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────
    //  Bulk actions
    // ─────────────────────────────────────────────────────────

    public function bulkDelete(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');

        if (empty($this->selectedUsers)) {
            session()->flash('error', 'No users selected.');
            return;
        }

        $targets         = array_filter($this->selectedUsers, fn ($id) => (string) $id !== (string) Auth::id());
        $wasSelfSelected = count($targets) < count($this->selectedUsers);

        if (empty($targets)) {
            session()->flash('error', 'You cannot delete your own account.');
            $this->clearSelection();
            return;
        }

        try {
            $count = DB::transaction(fn () => User::query()->whereIn('id', $targets)->delete());

            session()->flash(
                'message',
                "{$count} user(s) deleted successfully." . ($wasSelfSelected ? ' Your own account was excluded.' : '')
            );
        } catch (\Throwable $e) {
            Log::error('Bulk user delete failed: ' . $e->getMessage(), [
                'actor_id' => Auth::id(),
                'count'    => count($targets),
            ]);
            session()->flash('error', 'Failed to delete users. Please try again.');
        }

        $this->clearSelection();
    }

    public function bulkActivate(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');

        if (empty($this->selectedUsers)) {
            session()->flash('error', 'No users selected.');
            return;
        }

        try {
            $count = DB::transaction(
                fn () => User::query()->whereIn('id', $this->selectedUsers)->update(['is_active' => true]),
            );

            session()->flash('message', "{$count} user(s) activated successfully.");
        } catch (\Throwable $e) {
            Log::error('Bulk user activate failed: ' . $e->getMessage(), ['actor_id' => Auth::id()]);
            session()->flash('error', 'Failed to activate users. Please try again.');
        }

        $this->clearSelection();
    }

    public function bulkDeactivate(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');

        if (empty($this->selectedUsers)) {
            session()->flash('error', 'No users selected.');
            return;
        }

        $targets         = array_filter($this->selectedUsers, fn ($id) => (string) $id !== (string) Auth::id());
        $wasSelfSelected = count($targets) < count($this->selectedUsers);

        if (empty($targets)) {
            session()->flash('error', 'You cannot deactivate your own account.');
            $this->clearSelection();
            return;
        }

        try {
            $count = DB::transaction(
                fn () => User::query()->whereIn('id', $targets)->update(['is_active' => false]),
            );

            session()->flash(
                'message',
                "{$count} user(s) deactivated successfully." . ($wasSelfSelected ? ' Your own account was excluded.' : '')
            );
        } catch (\Throwable $e) {
            Log::error('Bulk user deactivate failed: ' . $e->getMessage(), ['actor_id' => Auth::id()]);
            session()->flash('error', 'Failed to deactivate users. Please try again.');
        }

        $this->clearSelection();
    }

    // ─────────────────────────────────────────────────────────
    //  Export
    // ─────────────────────────────────────────────────────────

    public function exportCsv()
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');

        $users    = $this->getUserQuery()->cursor();
        $filename = 'users-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($users): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Name', 'Email', 'Role', 'Business', 'Status', 'Created']);

            foreach ($users as $u) {
                fputcsv($out, [
                    $u->id,
                    $u->name,
                    $u->email,
                    $u->roles->pluck('name')->implode(', ') ?: 'No role',
                    $u->tenant?->name ?? 'Platform Level',
                    $u->is_active ? 'Active' : 'Inactive',
                    $u->created_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // ─────────────────────────────────────────────────────────
    //  Computed
    // ─────────────────────────────────────────────────────────

    /**
     * @return \Illuminate\Support\Collection<int, Role>
     */
    #[Computed]
    public function availableRoles()
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->select('name')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function users()
    {
        return $this->getUserQuery()->paginate($this->perPage);
    }

    /**
     * @return array{total: int, active: int, platform: int, business: int}
     */
    #[Computed]
    public function stats(): array
    {
        $agg = User::query()
            ->selectRaw("
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) as active_count,
                COALESCE(SUM(CASE WHEN tenant_id IS NULL THEN 1 ELSE 0 END), 0) as platform_count,
                COALESCE(SUM(CASE WHEN tenant_id IS NOT NULL THEN 1 ELSE 0 END), 0) as business_count
            ")
            ->first();

        return [
            'total'    => (int) ($agg?->total ?? 0),
            'active'   => (int) ($agg?->active_count ?? 0),
            'platform' => (int) ($agg?->platform_count ?? 0),
            'business' => (int) ($agg?->business_count ?? 0),
        ];
    }

    #[Computed]
    public function activeFiltersCount(): int
    {
        return ($this->search !== '' ? 1 : 0)
            + ($this->roleFilter !== '' ? 1 : 0)
            + ($this->statusFilter !== '' ? 1 : 0);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-800">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Users Management</h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">Manage system accounts, assign roles, and audit platform access.</p>
        </div>
        <a href="{{ route('superadmin.users.create') }}" wire:navigate
           class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white text-sm font-semibold shadow-sm shadow-primary-500/20 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add User
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
    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm transition-all duration-200 hover:border-primary-500/30">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Users</p>
                <div class="p-2 bg-primary-50 dark:bg-primary-950/50 rounded-xl text-primary-600 dark:text-primary-400 border border-primary-100 dark:border-primary-900/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white mt-3">{{ number_format($s['total']) }}</p>
        </div>

        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm transition-all duration-200 hover:border-emerald-500/30">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Active</p>
                <div class="p-2 bg-emerald-50 dark:bg-emerald-950/50 rounded-xl text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-3">{{ number_format($s['active']) }}</p>
        </div>

        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm transition-all duration-200 hover:border-amber-500/30">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Platform Users</p>
                <div class="p-2 bg-amber-50 dark:bg-amber-950/50 rounded-xl text-amber-600 dark:text-amber-400 border border-amber-100 dark:border-amber-900/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-3">{{ number_format($s['platform']) }}</p>
        </div>

        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm transition-all duration-200 hover:border-purple-500/30">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Business Users</p>
                <div class="p-2 bg-purple-50 dark:bg-purple-950/50 rounded-xl text-purple-600 dark:text-purple-400 border border-purple-100 dark:border-purple-900/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-purple-600 dark:text-purple-400 mt-3">{{ number_format($s['business']) }}</p>
        </div>
    </div>

    {{-- Filter & Actions Toolbar --}}
    <div class="flex flex-col lg:flex-row gap-3 items-stretch lg:items-center justify-between bg-white dark:bg-gray-800/90 p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm">
        <div class="flex flex-wrap gap-2 flex-1">
            <div class="relative flex-1 min-w-[200px] max-w-md">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search by name or email..."
                       class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2 pl-10 pr-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
            </div>

            <select wire:model.live="roleFilter"
                    class="bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2 px-3 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                <option value="">All Roles</option>
                @foreach($this->availableRoles as $role)
                    <option wire:key="role-opt-{{ $role->name }}" value="{{ $role->name }}">{{ Str::headline($role->name) }}</option>
                @endforeach
            </select>

            <select wire:model.live="statusFilter"
                    class="bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2 px-3 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>

            <select wire:model.live="sortOption"
                    class="bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2 px-3 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                <option value="latest">Newest first</option>
                <option value="oldest">Oldest first</option>
                <option value="name_asc">Name A–Z</option>
                <option value="name_desc">Name Z–A</option>
                <option value="email_asc">Email A–Z</option>
            </select>

            <select wire:model.live="perPage"
                    class="bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2 px-3 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                <option value="10">10 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
                <option value="100">100 / page</option>
            </select>

            @if($this->activeFiltersCount > 0)
                <button type="button" wire:click="clearFilters"
                        class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 text-xs font-semibold transition focus:ring-2 focus:ring-primary-500/50">
                    <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Clear ({{ $this->activeFiltersCount }})
                </button>
            @endif
        </div>

        <div class="flex items-center gap-2 shrink-0">
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

    {{-- Bulk Actions Toolbar --}}
    @if(count($selectedUsers) > 0)
        <div class="flex flex-wrap items-center justify-between gap-3 bg-primary-50 dark:bg-primary-500/10 border border-primary-200 dark:border-primary-500/30 p-4 rounded-2xl">
            <div class="flex items-center gap-2 text-sm text-primary-800 dark:text-primary-200">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span><strong>{{ count($selectedUsers) }}</strong> user(s) selected</span>
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                <button wire:click="bulkActivate" wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center px-3.5 py-2 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-xs font-semibold hover:bg-emerald-100 dark:hover:bg-emerald-500/20 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-emerald-500">
                    Activate
                </button>
                <button wire:click="bulkDeactivate" wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center px-3.5 py-2 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-amber-700 dark:text-amber-300 text-xs font-semibold hover:bg-amber-100 dark:hover:bg-amber-500/20 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-amber-500">
                    Deactivate
                </button>
                <button wire:click="bulkDelete"
                        wire:confirm="Are you sure you want to delete the selected user(s)? This action cannot be undone."
                        wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center px-3.5 py-2 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-700 dark:text-rose-300 text-xs font-semibold hover:bg-rose-100 dark:hover:bg-rose-500/20 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-rose-500">
                    Delete
                </button>
                <button wire:click="clearSelection"
                        class="inline-flex items-center justify-center px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 text-xs font-semibold transition active:scale-95">
                    Cancel
                </button>
            </div>
        </div>
    @endif

    {{-- Users Table --}}
    <div class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm overflow-hidden relative">
        <div wire:loading.flex wire:target="search,roleFilter,statusFilter,sortOption,perPage,gotoPage,nextPage,previousPage"
             class="absolute inset-0 bg-white/60 dark:bg-gray-900/60 backdrop-blur-[1px] z-10 items-center justify-center">
            <svg class="animate-spin h-7 w-7 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50/70 dark:bg-gray-900/50 border-b border-gray-200/80 dark:border-gray-700/80 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">
                    <tr>
                        <th class="px-6 py-4 w-12">
                            <input type="checkbox" wire:model.live="selectAll"
                                   class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500 cursor-pointer">
                        </th>
                        <th class="px-6 py-4">User</th>
                        <th class="px-6 py-4 hidden md:table-cell">Role</th>
                        <th class="px-6 py-4 hidden lg:table-cell">Business</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 text-sm text-gray-700 dark:text-gray-200">
                    @forelse ($this->users as $user)
                        @php $isSelf = $user->id === Auth::id(); @endphp
                        <tr wire:key="user-row-{{ $user->id }}" class="hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-6 py-4">
                                <input type="checkbox" wire:model.live="selectedUsers" value="{{ $user->id }}"
                                       class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500 cursor-pointer">
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-primary-50 dark:bg-primary-500/10 border border-primary-200/50 dark:border-primary-500/20 flex items-center justify-center text-primary-700 dark:text-primary-300 font-bold text-sm shrink-0 uppercase overflow-hidden">
                                        @if($user->avatar)
                                            <img src="{{ asset('storage/' . $user->avatar) }}" class="w-full h-full object-cover" alt="{{ $user->name }}">
                                        @else
                                            {{ substr($user->name, 0, 1) }}
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-semibold text-gray-900 dark:text-white truncate flex items-center gap-2">
                                            {{ $user->name }}
                                            @if($isSelf)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-500/30">
                                                    You
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $user->email }}</div>
                                        <div class="md:hidden mt-1 flex flex-wrap gap-1">
                                            @foreach($user->roles as $role)
                                                <span wire:key="mobile-role-{{ $user->id }}-{{ $role->id }}"
                                                      class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-medium {{ $role->name === 'super-admin' ? 'bg-purple-100 dark:bg-purple-500/15 text-purple-700 dark:text-purple-300' : 'bg-primary-100 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300' }}">
                                                    {{ Str::headline($role->name) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($user->roles as $role)
                                        <span wire:key="role-{{ $user->id }}-{{ $role->id }}"
                                              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $role->name === 'super-admin' ? 'bg-purple-100 dark:bg-purple-500/15 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-500/30' : 'bg-primary-100 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300 border border-primary-200 dark:border-primary-500/30' }}">
                                            {{ Str::headline($role->name) }}
                                        </span>
                                    @empty
                                        <span class="text-gray-400 dark:text-gray-500 text-xs italic">No role assigned</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-6 py-4 hidden lg:table-cell text-gray-600 dark:text-gray-300">
                                @if($user->tenant)
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        <span class="truncate max-w-[160px]">{{ $user->tenant->name }}</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700/80 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-600">
                                        Platform Level
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <button wire:click="toggleStatus({{ $user->id }})"
                                        wire:confirm="{{ $user->is_active ? 'Deactivate' : 'Activate' }} '{{ $user->name }}'?"
                                        wire:loading.attr="disabled"
                                        wire:target="toggleStatus({{ $user->id }})"
                                        @if($isSelf) disabled @endif
                                        class="cursor-pointer focus-visible:ring-2 focus-visible:ring-primary-500 rounded-full active:scale-95 transition-transform disabled:opacity-60 disabled:cursor-not-allowed">
                                    @if($user->is_active)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-500/30 hover:bg-green-200 dark:hover:bg-green-500/25 transition">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1.5"></span> Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 dark:bg-red-500/15 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-500/30 hover:bg-red-200 dark:hover:bg-red-500/25 transition">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-1.5"></span> Inactive
                                        </span>
                                    @endif
                                </button>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('superadmin.users.edit', $user->id) }}" wire:navigate
                                       class="p-1.5 text-gray-500 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-950/50 rounded-lg transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                       title="Edit user">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    @if(!$isSelf)
                                        <button wire:click="deleteUser({{ $user->id }})"
                                                wire:confirm="Are you sure you want to delete user '{{ $user->name }}'?"
                                                wire:loading.attr="disabled"
                                                wire:target="deleteUser({{ $user->id }})"
                                                class="p-1.5 text-gray-500 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-lg transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 disabled:opacity-60"
                                                title="Delete user">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                                    <div class="p-3 bg-gray-100 dark:bg-gray-800 rounded-2xl mb-3 text-gray-400 dark:text-gray-500">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">No users found</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ $this->activeFiltersCount > 0 ? 'Try adjusting your search or filters.' : 'Get started by adding your first user.' }}
                                    </p>
                                    @if($this->activeFiltersCount > 0)
                                        <button type="button" wire:click="clearFilters" class="mt-3 text-xs text-primary-600 dark:text-primary-400 hover:underline font-semibold">
                                            Clear active filters
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->users->hasPages())
            <div class="px-6 py-4 border-t border-gray-200/80 dark:border-gray-700/80 bg-gray-50/50 dark:bg-gray-900/50">
                {{ $this->users->links() }}
            </div>
        @endif
    </div>
</div>