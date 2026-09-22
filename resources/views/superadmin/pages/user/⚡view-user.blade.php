{{-- resources/views/superadmin/pages/user/⚡view-user.blade.php --}}
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

    public function hydrate(): void
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
    //  Computed
    // ─────────────────────────────────────────────────────────

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
     * Aggregate counts via the query builder — returns a plain stdClass
     * rather than a phantom Eloquent User with only the selectRaw
     * attributes. Safe to cache later if the numbers ever warrant it
     * (Rule 79).
     *
     * @return array{total: int, active: int, platform: int, business: int}
     */
    #[Computed]
    public function stats(): array
    {
        $agg = DB::table('users')
            ->selectRaw("
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) as active_count,
                COALESCE(SUM(CASE WHEN tenant_id IS NULL THEN 1 ELSE 0 END), 0) as platform_count,
                COALESCE(SUM(CASE WHEN tenant_id IS NOT NULL THEN 1 ELSE 0 END), 0) as business_count
            ")
            ->first();

        return [
            'total'    => (int) ($agg->total          ?? 0),
            'active'   => (int) ($agg->active_count   ?? 0),
            'platform' => (int) ($agg->platform_count ?? 0),
            'business' => (int) ($agg->business_count ?? 0),
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

@php $s = $this->stats; @endphp

<div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6">

    {{-- ═══ Page header ═══ --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Platform Users</span>
            </div>
            <h1 class="font-display text-3xl md:text-4xl font-semibold text-gray-900 dark:text-white">
                Users <em class="italic text-primary-600 dark:text-primary-400">Management</em>
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-2">
                Manage system accounts, assign roles, and audit platform access.
            </p>
        </div>
        <a href="{{ route('superadmin.users.create') }}" wire:navigate
           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                  transition-all duration-200 active:scale-95
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <span>Add User</span>
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
                ['label' => 'Total Users',    'value' => number_format($s['total']),    'dot' => 'bg-primary-500'],
                ['label' => 'Active',         'value' => number_format($s['active']),   'dot' => 'bg-emerald-500'],
                ['label' => 'Platform Users', 'value' => number_format($s['platform']), 'dot' => 'bg-amber-500'],
                ['label' => 'Business Users', 'value' => number_format($s['business']), 'dot' => 'bg-purple-500'],
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

    {{-- ═══ Filter card ═══ --}}
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4 space-y-3">

        {{-- Row 1: Search --}}
        <div class="relative">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="Search by name or email…"
                   enterkeyhint="search"
                   aria-label="Search users by name or email"
                   class="input w-full"
                   style="padding-left: 2.5rem;">
        </div>

        {{-- Row 2: role + status + sort + per-page --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <select wire:model.live="roleFilter" aria-label="Filter by role" class="input w-full">
                <option value="">All Roles</option>
                @foreach($this->availableRoles as $role)
                    <option wire:key="role-opt-{{ $role->name }}" value="{{ $role->name }}">{{ Str::headline($role->name) }}</option>
                @endforeach
            </select>

            <select wire:model.live="sortOption" aria-label="Sort users" class="input w-full">
                <option value="latest">Newest first</option>
                <option value="oldest">Oldest first</option>
                <option value="name_asc">Name A–Z</option>
                <option value="name_desc">Name Z–A</option>
                <option value="email_asc">Email A–Z</option>
            </select>

            <select wire:model.live="perPage" aria-label="Users per page" class="input w-full">
                <option value="10">10 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
                <option value="100">100 / page</option>
            </select>

            @if($this->activeFiltersCount > 0)
                <button type="button" wire:click="clearFilters"
                        class="inline-flex items-center justify-center gap-1 h-11 rounded-xl
                               border border-rose-300 dark:border-rose-500/40
                               bg-white dark:bg-gray-800 text-rose-700 dark:text-rose-300 text-xs font-semibold uppercase tracking-wide
                               transition-all duration-200 active:scale-95
                               hover:bg-rose-50 dark:hover:bg-rose-500/10
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    <span>Clear ({{ $this->activeFiltersCount }})</span>
                </button>
            @endif
        </div>

        {{-- Row 3: status pills --}}
        <div class="flex flex-wrap gap-2 items-center pt-1">
            @php
                $pills = [
                    ['value' => '',         'label' => 'All',      'count' => $s['total']],
                    ['value' => 'active',   'label' => 'Active',   'count' => $s['active']],
                    ['value' => 'inactive', 'label' => 'Inactive', 'count' => max(0, $s['total'] - $s['active'])],
                ];
            @endphp
            @foreach($pills as $pill)
                @php $isActive = $statusFilter === $pill['value']; @endphp
                <button type="button"
                        wire:click="$set('statusFilter', '{{ $pill['value'] }}')"
                        wire:key="pill-{{ $pill['value'] !== '' ? $pill['value'] : 'all' }}"
                        aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                        class="inline-flex items-center gap-2 h-9 pl-3.5 pr-1.5 rounded-full text-xs font-semibold uppercase tracking-wide border
                               transition-all duration-200 active:scale-95 shrink-0
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $isActive
                                  ? 'bg-primary-600 border-primary-600 text-white shadow-sm'
                                  : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400' }}">
                    <span>{{ $pill['label'] }}</span>
                    <span class="inline-flex items-center justify-center min-w-[22px] h-5 px-1.5 rounded-full text-[10px] font-bold tabular-nums
                                 {{ $isActive
                                    ? 'bg-white/20 text-white'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                        {{ $pill['count'] }}
                    </span>
                </button>
            @endforeach

            {{-- Select-all helper --}}
            <div class="ml-auto flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
                    <input type="checkbox"
                           wire:model.live="selectAll"
                           aria-label="Select all users on this page"
                           class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500 focus-visible:outline-none">
                    <span>Select all on page</span>
                </label>
            </div>
        </div>
    </div>

    {{-- ═══ Bulk actions toolbar ═══ --}}
    @if(count($selectedUsers) > 0)
        <div class="flex flex-wrap items-center justify-between gap-3 bg-primary-50 dark:bg-primary-500/10 border border-primary-200 dark:border-primary-500/30 p-4 rounded-2xl">
            <div class="flex items-center gap-2 text-sm text-primary-800 dark:text-primary-200">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><strong>{{ count($selectedUsers) }}</strong> user(s) selected</span>
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                <button type="button" wire:click="bulkActivate"
                        wire:loading.attr="disabled"
                        wire:target="bulkActivate"
                        class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-emerald-300 dark:border-emerald-500/40 bg-white dark:bg-gray-800 text-emerald-700 dark:text-emerald-300 text-sm font-semibold
                               transition-all duration-200 active:scale-95 hover:bg-emerald-50 dark:hover:bg-emerald-500/10
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                               disabled:opacity-60 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Activate</span>
                </button>
                <button type="button" wire:click="bulkDeactivate"
                        wire:loading.attr="disabled"
                        wire:target="bulkDeactivate"
                        class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-amber-300 dark:border-amber-500/40 bg-white dark:bg-gray-800 text-amber-700 dark:text-amber-300 text-sm font-semibold
                               transition-all duration-200 active:scale-95 hover:bg-amber-50 dark:hover:bg-amber-500/10
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                               disabled:opacity-60 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                    <span>Deactivate</span>
                </button>
                <button type="button"
                        x-on:click="if (confirm('Are you sure you want to delete the selected user(s)? This action cannot be undone.')) $wire.bulkDelete()"
                        wire:loading.attr="disabled"
                        wire:target="bulkDelete"
                        class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-rose-300 dark:border-rose-500/40 bg-white dark:bg-gray-800 text-rose-700 dark:text-rose-300 text-sm font-semibold
                               transition-all duration-200 active:scale-95 hover:bg-rose-50 dark:hover:bg-rose-500/10
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                               disabled:opacity-60 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <span>Delete</span>
                </button>
                <button type="button" wire:click="clearSelection"
                        class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                               transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <span>Cancel</span>
                </button>
            </div>
        </div>
    @endif

    {{-- ═══ Card grid ═══ --}}
    @if($this->users->isEmpty())
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-12 text-center">
            <div class="flex flex-col items-center max-w-md mx-auto">
                <div class="p-3 rounded-2xl bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <p class="mt-4 text-base font-semibold text-gray-900 dark:text-white">
                    No users found
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->activeFiltersCount > 0
                        ? 'Try adjusting your search or filters.'
                        : 'Get started by adding your first user.' }}
                </p>
                <div class="mt-5 flex flex-wrap gap-2 justify-center">
                    @if($this->activeFiltersCount > 0)
                        <button type="button" wire:click="clearFilters"
                                class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                       transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                            Clear Filters
                        </button>
                    @endif
                    <a href="{{ route('superadmin.users.create') }}" wire:navigate
                       class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                              transition-all duration-200 active:scale-95
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span>Add User</span>
                    </a>
                </div>
            </div>
        </div>
    @else
        <div wire:loading.class="opacity-40 pointer-events-none"
             wire:target="search,roleFilter,statusFilter,sortOption,perPage,clearFilters,toggleStatus,deleteUser,gotoPage,nextPage,previousPage"
             class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 transition-opacity duration-200">
            @foreach($this->users as $user)
                @php
                    $isSelf       = $user->id === Auth::id();
                    $isSelected   = in_array((string) $user->id, $this->selectedUsers, true);
                    $isSuperAdmin = $user->hasRole('super-admin');
                @endphp

                <article wire:key="user-{{ $user->id }}"
                         class="group relative bg-white dark:bg-gray-800/90 rounded-2xl border shadow-sm hover:shadow-md transition-all duration-200 flex flex-col overflow-hidden
                                {{ $isSelected
                                    ? 'border-primary-400 dark:border-primary-500/50 ring-2 ring-primary-500/20'
                                    : ($user->is_active
                                        ? 'border-gray-200/80 dark:border-gray-700/80'
                                        : 'border-rose-200 dark:border-rose-500/30') }}">

                    {{-- Top: avatar + name + select checkbox + You badge --}}
                    <div class="p-4 pb-3 flex items-start gap-3">
                        {{-- Avatar --}}
                        <div class="w-12 h-12 rounded-xl bg-primary-50 dark:bg-primary-500/10 border border-primary-200/50 dark:border-primary-500/20 flex items-center justify-center text-primary-700 dark:text-primary-300 font-bold text-base shrink-0 uppercase overflow-hidden">
                            @if($user->avatar)
                                <img src="{{ asset('storage/' . $user->avatar) }}"
                                     class="w-full h-full object-cover"
                                     alt="{{ $user->name }}"
                                     loading="lazy"
                                     decoding="async">
                            @else
                                {{ substr($user->name, 0, 1) }}
                            @endif
                        </div>

                        {{-- Name + email + role badges --}}
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <p class="font-semibold text-gray-900 dark:text-white truncate leading-tight">
                                    {{ $user->name }}
                                </p>
                                @if($isSelf)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold
                                                 bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-500/30">
                                        You
                                    </span>
                                @endif
                            </div>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                {{ $user->email }}
                            </p>

                            {{-- Role pills --}}
                            <div class="flex flex-wrap gap-1 mt-1.5">
                                @forelse($user->roles as $role)
                                    <span wire:key="card-role-{{ $user->id }}-{{ $role->id }}"
                                          class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-semibold
                                                 {{ $role->name === 'super-admin'
                                                    ? 'bg-purple-100 dark:bg-purple-500/15 text-purple-700 dark:text-purple-300'
                                                    : 'bg-primary-100 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300' }}">
                                        {{ Str::headline($role->name) }}
                                    </span>
                                @empty
                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 italic">No role</span>
                                @endforelse
                            </div>
                        </div>

                        {{-- Selection checkbox (top-right corner) --}}
                        <label class="relative inline-flex items-center cursor-pointer shrink-0 mt-0.5">
                            <input type="checkbox"
                                   wire:model.live="selectedUsers"
                                   value="{{ $user->id }}"
                                   aria-label="Select {{ $user->name }}"
                                   class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500 cursor-pointer">
                        </label>
                    </div>

                    {{-- Tenant --}}
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700/60">
                        <div class="flex items-center gap-1.5 text-xs">
                            <svg class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            @if($user->tenant)
                                <span class="text-gray-700 dark:text-gray-300 truncate">{{ $user->tenant->name }}</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                                             bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-600">
                                    Platform Level
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Status pill --}}
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-between gap-2">
                        @if($isSelf)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider
                                         bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/40">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                Active
                            </span>
                            <span class="text-[10px] text-gray-400 dark:text-gray-500 italic uppercase tracking-wider font-semibold">Locked</span>
                        @else
                            <button type="button"
                                    x-on:click="if (confirm('{{ $user->is_active ? 'Deactivate' : 'Activate' }} {{ addslashes($user->name) }}?')) $wire.toggleStatus({{ $user->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="toggleStatus"
                                    aria-label="{{ $user->is_active ? 'Deactivate' : 'Activate' }} {{ $user->name }}"
                                    class="inline-flex items-center justify-center rounded-full transition-all duration-200 active:scale-95
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                @if($user->is_active)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider
                                                 bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/40
                                                 hover:bg-emerald-200 dark:hover:bg-emerald-500/25 transition">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider
                                                 bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-500/40
                                                 hover:bg-rose-200 dark:hover:bg-rose-500/25 transition">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                        Inactive
                                    </span>
                                @endif
                            </button>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div class="mt-auto px-3 py-2.5 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-end gap-1">
                        <a href="{{ route('superadmin.users.edit', $user->id) }}" wire:navigate
                           aria-label="Edit {{ $user->name }}"
                           title="Edit user"
                           class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-500/10
                                  transition-all duration-200 active:scale-95
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </a>
                        @if(!$isSelf)
                            <button type="button"
                                    x-on:click="if (confirm('Are you sure you want to delete user {{ addslashes($user->name) }}?')) $wire.deleteUser({{ $user->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="deleteUser"
                                    aria-label="Delete {{ $user->name }}"
                                    title="Delete user"
                                    class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10
                                           transition-all duration-200 active:scale-95
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        @if($this->users->hasPages())
            <div class="pt-2">
                {{ $this->users->links() }}
            </div>
        @endif
    @endif
</div>