
<?php

use App\Models\BusinessApplication;
use App\Models\Tenant;
use App\Models\TypeOfTenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('superadmin.layouts.app')]
#[Title('Tenants')]
class extends Component
{
    use WithPagination;

    public string  $search       = '';
    public string  $statusFilter = 'all';
    public ?int    $typeFilter   = null;
    public ?string $startDate    = null;
    public ?string $endDate      = null;
    public string  $sortOption   = 'latest';
    public int     $perPage      = 12;

    /** @var array<int, string> */
    public array $selected = [];

    public bool $selectAll = false;

    /**
     * Which tenant's action is currently in flight (approve / deactivate /
     * delete). Drives per-card loading state — Livewire v4's `wire:target`
     * cannot distinguish approve(1) from approve(2), so we track it here.
     */
    public ?int $processingId = null;

    /** True while a bulk action (approveSelected / deleteSelected) runs. */
    public bool $bulkProcessing = false;

    // ─────────────────────────────────────────────────────
    //  Lifecycle — defense in depth (route middleware already gates)
    // ─────────────────────────────────────────────────────

    public function mount(): void
    {
        abort_unless(Auth::check() && Auth::user()->hasRole('super-admin'), 403);
    }

    public function hydrate(): void
    {
        abort_unless(Auth::check() && Auth::user()->hasRole('super-admin'), 403);
    }

    // ─────────────────────────────────────────────────────
    //  Filter hooks
    // ─────────────────────────────────────────────────────

    public function updated(string $property): void
    {
        $resets = [
            'search', 'statusFilter', 'typeFilter',
            'startDate', 'endDate', 'sortOption', 'perPage',
        ];

        if (in_array($property, $resets, true)) {
            if (in_array($property, ['startDate', 'endDate'], true) && $this->$property !== null && $this->$property !== '') {
                try {
                    $this->validateOnly($property, [
                        $property => ['nullable', 'date'],
                    ]);
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $this->$property = null;
                    throw $e;
                }
            }

            $this->resetPage();
            $this->resetSelection();
        }
    }

    public function updatedPage(): void
    {
        $this->resetSelection();
    }

    public function updatedSelectAll(bool $value): void
    {
        $this->resetSelection();

        if ($value) {
            $this->selected = $this->tenants
                ->getCollection()
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all();

            $this->selectAll = true;
        }
    }

    public function updatedSelected(): void
    {
        $pageCount = $this->tenants->count();
        $this->selectAll = $pageCount > 0 && count($this->selected) === $pageCount;
    }

    private function resetSelection(): void
    {
        $this->selected  = [];
        $this->selectAll = false;
    }

    public function clearFilters(): void
    {
        $this->reset([
            'search', 'statusFilter', 'typeFilter',
            'startDate', 'endDate', 'sortOption', 'perPage',
        ]);

        $this->resetPage();
        $this->resetSelection();
        $this->dispatch('toast', message: 'All filters cleared.', type: 'info');
    }

    // ─────────────────────────────────────────────────────
    //  Computed
    // ─────────────────────────────────────────────────────

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->statusFilter !== 'all'
            || $this->typeFilter !== null
            || $this->startDate !== null
            || $this->endDate !== null
            || $this->sortOption !== 'latest';
    }

    #[Computed]
    public function tenantTypes()
    {
        return TypeOfTenant::query()->select('id', 'type')->orderBy('type')->get();
    }

    /**
     * Aggregate counts for the stat cards.
     *
     * Uses the query builder instead of Eloquent — the result is a plain
     * stdClass with two SUM aliases, not a Model with phantom attributes.
     * This matters if anyone ever adds caching here: Rule 79 forbids
     * caching Eloquent instances with the database driver.
     */
    #[Computed]
    public function stats(): array
    {
        $row = DB::table('tenants')
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(is_active), 0) as active_count')
            ->first();

        $total  = (int) ($row->total ?? 0);
        $active = (int) ($row->active_count ?? 0);

        return [
            'total'   => $total,
            'active'  => $active,
            'pending' => max(0, $total - $active),
        ];
    }

    #[Computed]
    public function tenants()
    {
        return $this->getBaseQuery()
            ->when($this->sortOption === 'name_asc',  fn ($q) => $q->orderBy('name', 'asc'))
            ->when($this->sortOption === 'name_desc', fn ($q) => $q->orderBy('name', 'desc'))
            ->when($this->sortOption === 'oldest',    fn ($q) => $q->orderBy('created_at', 'asc'))
            ->when($this->sortOption === 'latest',    fn ($q) => $q->orderByDesc('created_at'))
            ->paginate($this->perPage);
    }

    #[Computed]
    public function hasInactiveSelected(): bool
    {
        if (empty($this->selected)) {
            return false;
        }

        $selectedSet = array_flip($this->selected);

        return $this->tenants
            ->getCollection()
            ->contains(fn (Tenant $t) => isset($selectedSet[(string) $t->id]) && ! $t->is_active);
    }

    // ─────────────────────────────────────────────────────
    //  Base query
    // ─────────────────────────────────────────────────────

    private function getBaseQuery()
    {
        return Tenant::query()
            ->with([
                'typeOfTenant:id,type',
                // Eager-loaded so `hasRole('admin')` doesn't trigger an
                // N+1 — the admin resolver below hits every row.
                'users:id,tenant_id,name,email,is_active',
                'users.roles:id,name',
                // KYB record for the compliance badge + button. Nullable —
                // legacy tenants created before the flow have no record.
                'businessApplication:id,approved_tenant_id,status,source,reviewed_at',
            ])
            ->withCount(['properties', 'bookings'])
            ->when($this->search !== '', function ($q): void {
                $s = trim($this->search);
                $q->where(function ($sub) use ($s): void {
                    $sub->where('name', 'like', "%{$s}%")
                        ->orWhere('email', 'like', "%{$s}%")
                        ->orWhere('contact_number', 'like', "%{$s}%")
                        ->orWhere('address', 'like', "%{$s}%")
                        ->orWhere('slug', 'like', "%{$s}%");
                });
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('is_active', $this->statusFilter === 'active'))
            ->when($this->typeFilter, fn ($q) => $q->where('type_of_tenant_id', $this->typeFilter))
            ->when($this->startDate && $this->endDate, function ($q): void {
                $s = rescue(fn () => Carbon::parse($this->startDate)->startOfDay());
                $e = rescue(fn () => Carbon::parse($this->endDate)->endOfDay());

                if ($s && $e) {
                    $q->whereBetween('created_at', [$s, $e]);
                }
            });
    }

    /**
     * Resolve the admin user of a loaded tenant.
     * Prefers a user with the explicit `admin` role; falls back to the
     * first user only for tenants that were created before role tagging
     * existed.
     */
    public function resolveAdmin(Tenant $tenant): ?User
    {
        return $tenant->users->first(fn ($u) => $u->hasRole('admin'))
            ?? $tenant->users->first();
    }

    // ─────────────────────────────────────────────────────
    //  Single-row actions
    // ─────────────────────────────────────────────────────

    public function approve(int $id): void
    {
        if ($this->processingId !== null) {
            return;
        }

        $this->processingId = $id;

        try {
            $tenant = Tenant::with(['users.roles'])->find($id);

            if (! $tenant) {
                $this->dispatch('toast', message: 'That tenant no longer exists.', type: 'error');
                return;
            }

            DB::transaction(function () use ($tenant): void {
                $tenant->update(['is_active' => true]);

                // Resolve the actual admin — never blindly take the
                // first user of the tenant (that could be an employee).
                $admin = $this->resolveAdmin($tenant);

                if ($admin) {
                    $admin->update(['is_active' => true]);

                    if (! $admin->hasRole('admin')) {
                        $admin->assignRole('admin');
                    }
                }
            });

            unset($this->stats, $this->tenants);
            $this->dispatch('toast', message: "{$tenant->name} has been approved.", type: 'success');
        } finally {
            $this->processingId = null;
        }
    }

    public function deactivate(int $id): void
    {
        if ($this->processingId !== null) {
            return;
        }

        $this->processingId = $id;

        try {
            $tenant = Tenant::find($id);

            if (! $tenant) {
                $this->dispatch('toast', message: 'That tenant no longer exists.', type: 'error');
                return;
            }

            $tenant->update(['is_active' => false]);
            unset($this->stats, $this->tenants);
            $this->dispatch('toast', message: "{$tenant->name} has been suspended.", type: 'info');
        } finally {
            $this->processingId = null;
        }
    }

    public function deleteTenant(int $id): void
    {
        if ($this->processingId !== null) {
            return;
        }

        $this->processingId = $id;

        try {
            $tenant = Tenant::find($id);

            if (! $tenant) {
                $this->dispatch('toast', message: 'That tenant no longer exists.', type: 'error');
                return;
            }

            $name = $tenant->name;
            $tenant->delete();

            unset($this->stats, $this->tenants);
            $this->dispatch('toast', message: "{$name} deleted.", type: 'success');
        } finally {
            $this->processingId = null;
        }
    }

    // ─────────────────────────────────────────────────────
    //  Bulk actions
    // ─────────────────────────────────────────────────────

    public function approveSelected(): void
    {
        if ($this->bulkProcessing) {
            return;
        }

        if (empty($this->selected)) {
            $this->dispatch('toast', message: 'No businesses selected.', type: 'error');
            return;
        }

        $this->bulkProcessing = true;

        try {
            $count = 0;

            DB::transaction(function () use (&$count): void {
                $tenants = Tenant::with(['users.roles'])
                    ->whereIn('id', $this->selected)
                    ->get();

                foreach ($tenants as $tenant) {
                    $tenant->update(['is_active' => true]);

                    $admin = $this->resolveAdmin($tenant);

                    if ($admin) {
                        $admin->update(['is_active' => true]);

                        if (! $admin->hasRole('admin')) {
                            $admin->assignRole('admin');
                        }
                    }
                }

                $count = $tenants->count();
            });

            $this->resetSelection();
            unset($this->stats, $this->tenants);
            $this->dispatch('toast', message: "{$count} business(es) approved.", type: 'success');
        } finally {
            $this->bulkProcessing = false;
        }
    }

    public function deleteSelected(): void
    {
        if ($this->bulkProcessing) {
            return;
        }

        if (empty($this->selected)) {
            $this->dispatch('toast', message: 'No businesses selected.', type: 'error');
            return;
        }

        $this->bulkProcessing = true;

        try {
            $count = count($this->selected);
            Tenant::whereIn('id', $this->selected)->delete();

            $this->resetSelection();
            unset($this->stats, $this->tenants);
            $this->dispatch('toast', message: "{$count} business(es) deleted.", type: 'success');
        } finally {
            $this->bulkProcessing = false;
        }
    }

    // ─────────────────────────────────────────────────────
    //  Export
    // ─────────────────────────────────────────────────────

    public function exportCsv(): void
    {
        $url = route('superadmin.tenants.export', array_filter([
            'search' => $this->search,
            'status' => $this->statusFilter,
            'type'   => $this->typeFilter,
            'start'  => $this->startDate,
            'end'    => $this->endDate,
        ]));

        $this->dispatch('open-url', url: $url);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    
    <div x-data="{ toasts: [] }"
         x-on:toast.window="
            const id = Date.now() + Math.random();
            toasts.push({ id, message: $event.detail.message, type: $event.detail.type || 'info' });
            setTimeout(() => { toasts = toasts.filter(t => t.id !== id) }, 4000);
         "
         class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 w-full max-w-sm pointer-events-none">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="pointer-events-auto rounded-xl px-4 py-3 shadow-lg text-sm font-medium flex items-center gap-2 border"
                 :class="{
                    'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-300': toast.type === 'success',
                    'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-500/10 dark:border-rose-500/30 dark:text-rose-300': toast.type === 'error',
                    'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-500/10 dark:border-blue-500/30 dark:text-blue-300': toast.type === 'info',
                 }">
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>

    
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Platform</span>
            </div>
            <h1 class="font-display text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Tenants</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage all businesses registered on the platform.</p>
        </div>
        <a href="<?php echo e(route('superadmin.tenants.create')); ?>" wire:navigate
           class="btn-primary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Add Tenant
        </a>
    </div>

    
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2"><?php echo e($this->stats['total']); ?></p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Active</p>
            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-2"><?php echo e($this->stats['active']); ?></p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pending</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-2"><?php echo e($this->stats['pending']); ?></p>
        </div>
    </div>

    
    <div class="card p-4 space-y-4">
        <div class="flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-[220px]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search"
                       enterkeyhint="search"
                       aria-label="Search tenants"
                       placeholder="Search tenants..."
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 pl-10 pr-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
            </div>

            <select wire:model.live="statusFilter" aria-label="Filter by status"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                <option value="all">All status</option>
                <option value="active">Active</option>
                <option value="inactive">Pending</option>
            </select>

            <select wire:model.live="typeFilter" aria-label="Filter by type"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                <option value="">All types</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->tenantTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <option <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'type-filter-'.e($type->id).''; ?>wire:key="type-filter-<?php echo e($type->id); ?>" value="<?php echo e($type->id); ?>"><?php echo e($type->type); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>

            
            <div class="flex items-center gap-2 bg-gray-50 dark:bg-gray-800/50 p-1 rounded-xl border border-gray-200 dark:border-gray-700">
                <input type="date" wire:model.live.blur="startDate" aria-label="Start date" class="bg-transparent border-none py-1.5 px-3 text-sm text-gray-900 dark:text-white focus:ring-0">
                <span class="text-gray-400 text-sm">to</span>
                <input type="date" wire:model.live.blur="endDate" aria-label="End date" class="bg-transparent border-none py-1.5 px-3 text-sm text-gray-900 dark:text-white focus:ring-0">
            </div>

            <select wire:model.live="sortOption" aria-label="Sort order"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                <option value="latest">Newest first</option>
                <option value="oldest">Oldest first</option>
                <option value="name_asc">Name A–Z</option>
                <option value="name_desc">Name Z–A</option>
            </select>

            <select wire:model.live="perPage" aria-label="Rows per page"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                <option value="12">12 per page</option>
                <option value="25">25 per page</option>
                <option value="50">50 per page</option>
            </select>

            <button type="button" wire:click="clearFilters"
                    <?php if(!$this->hasActiveFilters): echo 'disabled'; endif; ?>
                    class="px-4 py-2.5 rounded-xl bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-40 disabled:cursor-not-allowed">
                Clear
            </button>
        </div>

        
        <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-gray-100 dark:border-gray-700/50">
            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model.live="selectAll" class="rounded bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500">
                    <span class="font-medium">Select All on Page</span>
                </label>

                <span class="text-sm text-gray-500 dark:text-gray-400">|</span>

                <div class="text-sm text-gray-600 dark:text-gray-300">
                    <span class="font-semibold text-gray-900 dark:text-white"><?php echo e($this->tenants->total()); ?></span> total tenants
                </div>
            </div>

            <div class="flex gap-2 flex-wrap">
                <button type="button" wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv"
                        title="Export the current filtered list as CSV"
                        class="px-4 py-2 rounded-xl bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
                    <span wire:loading wire:target="exportCsv" class="inline-flex items-center gap-1">
                        <svg class="animate-spin h-3 w-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Preparing…
                    </span>
                </button>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($selected) > 0): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->hasInactiveSelected): ?>
                        <button type="button" wire:click="approveSelected"
                                wire:confirm="Activate selected businesses?"
                                wire:loading.attr="disabled" wire:target="approveSelected"
                                <?php if($bulkProcessing): echo 'disabled'; endif; ?>
                                class="px-4 py-2 rounded-xl bg-emerald-100 dark:bg-emerald-500/15 border border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-sm font-semibold hover:bg-emerald-200 dark:hover:bg-emerald-500/25 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-emerald-500/50 disabled:opacity-60 disabled:cursor-wait">
                            <span wire:loading.remove wire:target="approveSelected">Activate Selected (<?php echo e(count($selected)); ?>)</span>
                            <span wire:loading wire:target="approveSelected">Processing…</span>
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <button type="button" wire:click="deleteSelected"
                            wire:confirm="Delete selected businesses permanently?"
                            wire:loading.attr="disabled" wire:target="deleteSelected"
                            <?php if($bulkProcessing): echo 'disabled'; endif; ?>
                            class="px-4 py-2 rounded-xl bg-rose-100 dark:bg-rose-500/15 border border-rose-200 dark:border-rose-500/30 text-rose-700 dark:text-rose-300 text-sm font-semibold hover:bg-rose-200 dark:hover:bg-rose-500/25 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-rose-500/50 disabled:opacity-60 disabled:cursor-wait">
                        <span wire:loading.remove wire:target="deleteSelected">Delete Selected (<?php echo e(count($selected)); ?>)</span>
                        <span wire:loading wire:target="deleteSelected">Deleting…</span>
                    </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>

    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"
         wire:loading.class="opacity-60"
         wire:target="search,statusFilter,typeFilter,startDate,endDate,sortOption,perPage,clearFilters,previousPage,nextPage,gotoPage">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->tenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php
                $admin       = $this->resolveAdmin($tenant);
                $coords      = is_string($tenant->coordinates) ? json_decode($tenant->coordinates, true) : ($tenant->coordinates ?? []);
                $markerNames = collect($coords)->pluck('name')->filter()->implode(', ');
                $isSelected  = in_array((string) $tenant->id, $selected, true);
                $isProcessing = $processingId === $tenant->id;

                // KYB compliance state — a tenant with no BusinessApplication
                // was created before the KYB flow, or was added by superadmin
                // without documents on file.
                $kyb    = $tenant->businessApplication;
                $hasKyb = $kyb !== null && $kyb->status === BusinessApplication::STATUS_APPROVED;
            ?>
            <div class="card p-5 hover:shadow-md transition relative <?php echo e($isSelected ? 'ring-2 ring-primary-500' : ''); ?> <?php echo e($isProcessing ? 'opacity-75 pointer-events-none' : ''); ?>"
                 <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'card-'.e($tenant->id).''; ?>wire:key="card-<?php echo e($tenant->id); ?>">
                <div class="absolute top-4 left-4">
                    <input type="checkbox" wire:model.live="selected" value="<?php echo e($tenant->id); ?>"
                           aria-label="Select <?php echo e($tenant->name); ?>"
                           <?php if($processingId !== null || $bulkProcessing): echo 'disabled'; endif; ?>
                           class="rounded bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                </div>

                <div class="flex flex-col h-full">
                    <div class="flex items-start gap-3 mb-3 pl-8">
                        <div class="w-12 h-12 rounded-xl bg-primary-50 dark:bg-primary-500/10 border border-primary-200 dark:border-primary-500/20 flex items-center justify-center shrink-0 overflow-hidden">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->logo): ?>
                                <img src="<?php echo e(asset('storage/' . $tenant->logo)); ?>"
                                     alt="<?php echo e($tenant->name); ?>"
                                     loading="lazy"
                                     decoding="async"
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <span class="text-lg font-medium text-primary-700 dark:text-primary-300"><?php echo e(strtoupper(substr($tenant->name, 0, 1))); ?></span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-gray-900 dark:text-white truncate"><?php echo e($tenant->name); ?></h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">#<?php echo e($tenant->id); ?> · <?php echo e($tenant->typeOfTenant->type ?? 'Uncategorized'); ?></p>
                        </div>
                        <div class="flex items-center gap-1">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->is_active): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30">Active</span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30">Pending</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>

                    
                    <div class="mb-3">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasKyb): ?>
                            <div class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-500/[0.06] border border-emerald-200/70 dark:border-emerald-500/30 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                KYB on file
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($kyb->source === BusinessApplication::SOURCE_SUPERADMIN_DIRECT): ?>
                                    <span class="font-normal text-emerald-600/70 dark:text-emerald-400/70">· superadmin</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        <?php else: ?>
                            <a href="<?php echo e(route('superadmin.tenants.edit', $tenant->id)); ?>" wire:navigate
                               title="No approved KYB application on file. Open this tenant to add legal documents — a KYB record will be created automatically."
                               class="inline-flex items-center gap-1.5 rounded-lg bg-amber-50 dark:bg-amber-500/[0.06] border border-amber-200/70 dark:border-amber-500/30 px-2.5 py-1 text-[11px] font-semibold text-amber-700 dark:text-amber-300 hover:bg-amber-100 dark:hover:bg-amber-500/15 transition active:scale-95">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                                </svg>
                                No KYB record
                                <svg class="w-3 h-3 ml-0.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="space-y-2 text-sm flex-1">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Email</span>
                            <span class="text-gray-900 dark:text-white truncate ml-4"><?php echo e($tenant->email); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Contact</span>
                            <span class="text-gray-900 dark:text-white"><?php echo e($tenant->contact_number ?? '—'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Admin</span>
                            <span class="text-gray-900 dark:text-white truncate ml-4"><?php echo e($admin ? $admin->name : 'Not assigned'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Properties</span>
                            <span class="text-gray-900 dark:text-white"><?php echo e($tenant->properties_count); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Bookings</span>
                            <span class="text-gray-900 dark:text-white"><?php echo e($tenant->bookings_count); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Created</span>
                            <span class="text-gray-900 dark:text-white text-xs"><?php echo e($tenant->created_at->format('M d, Y')); ?></span>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($markerNames): ?>
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">Markers</span>
                                <span class="text-gray-900 dark:text-white text-xs truncate ml-4"><?php echo e($markerNames); ?></span>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex flex-wrap gap-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$tenant->is_active): ?>
                            <button type="button" wire:click="approve(<?php echo e($tenant->id); ?>)"
                                    wire:confirm="Approve this business and activate its owner account?"
                                    <?php if($processingId !== null || $bulkProcessing): echo 'disabled'; endif; ?>
                                    class="text-xs font-medium bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30 hover:bg-emerald-200 dark:hover:bg-emerald-500/25 px-3 py-1.5 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-emerald-500/50 disabled:opacity-60 disabled:cursor-wait">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isProcessing): ?>
                                    Saving…
                                <?php else: ?>
                                    Approve
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </button>
                        <?php else: ?>
                            <button type="button" wire:click="deactivate(<?php echo e($tenant->id); ?>)"
                                    wire:confirm="Suspend this business? Its owner will lose access."
                                    <?php if($processingId !== null || $bulkProcessing): echo 'disabled'; endif; ?>
                                    class="text-xs font-medium bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30 hover:bg-amber-200 dark:hover:bg-amber-500/25 px-3 py-1.5 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-amber-500/50 disabled:opacity-60 disabled:cursor-wait">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isProcessing): ?>
                                    Saving…
                                <?php else: ?>
                                    Suspend
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <a href="<?php echo e(route('superadmin.tenants.edit', $tenant->id)); ?>" wire:navigate
                           class="text-xs font-medium text-primary-600 dark:text-primary-400 border border-primary-200 dark:border-primary-500/30 hover:bg-primary-50 dark:hover:bg-primary-500/10 px-3 py-1.5 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50 text-center">
                            Edit
                        </a>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($kyb): ?>
                            <a href="<?php echo e(route('superadmin.business-applications.show', $kyb)); ?>" wire:navigate
                               title="View this tenant's KYB application and uploaded documents"
                               class="text-xs font-medium text-primary-600 dark:text-primary-400 border border-primary-200 dark:border-primary-500/30 hover:bg-primary-50 dark:hover:bg-primary-500/10 px-3 py-1.5 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50 text-center">
                                KYB
                            </a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <button type="button" wire:click="deleteTenant(<?php echo e($tenant->id); ?>)"
                                wire:confirm="Delete this business permanently? This will remove all properties, bookings, and users."
                                <?php if($processingId !== null || $bulkProcessing): echo 'disabled'; endif; ?>
                                class="text-xs font-medium text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-500/30 hover:bg-rose-50 dark:hover:bg-rose-500/10 px-3 py-1.5 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-rose-500/50 disabled:opacity-60 disabled:cursor-wait">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isProcessing): ?>
                                Deleting…
                            <?php else: ?>
                                Delete
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </button>
                    </div>
                </div>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <div class="col-span-full text-center py-12 card">
                <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->hasActiveFilters): ?>
                    <p class="text-lg text-gray-500 dark:text-gray-400 mb-1">No tenants match your filters</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mb-4">Try a different search or clear the filters to see everything.</p>
                    <button type="button" wire:click="clearFilters"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Clear all filters
                    </button>
                <?php else: ?>
                    <p class="text-lg text-gray-500 dark:text-gray-400 mb-1">No tenants yet</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mb-4">Onboard your first business to get started.</p>
                    <a href="<?php echo e(route('superadmin.tenants.create')); ?>" wire:navigate
                       class="btn-primary active:scale-95 transition-transform inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        Add your first tenant
                    </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->tenants->hasPages()): ?>
        <div class="card px-4 py-3">
            <?php echo e($this->tenants->links()); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

</div>

<script>
    // Global listener for the CSV download dispatch. Guarded against
    // wire:navigate re-runs (Livewire v4 re-executes SFC <script> blocks).
    if (!window.__viewTenantOpenUrlBound) {
        window.__viewTenantOpenUrlBound = true;

        window.addEventListener('open-url', (e) => {
            window.location.href = e.detail.url;
        });
    }
</script><?php /**PATH C:\laragon\www\Capstone\resources\views\superadmin\pages\tenant\⚡view-tenant.blade.php ENDPATH**/ ?>