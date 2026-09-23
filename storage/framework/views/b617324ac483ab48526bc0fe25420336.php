
<?php

use App\Models\BusinessApplication;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('superadmin.layouts.app')]
#[Title('Business Applications')]
class extends Component
{
    use WithPagination;

    #[Url(keep: true)]
    public string $search = '';

    #[Url(keep: true)]
    public string $status = '';

    #[Url(keep: true)]
    public int $perPage = 20;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->resetPage();
    }

    public function filterBy(string $value): void
    {
        // Clicking the currently-active stat card toggles the filter off.
        $this->status = $this->status === $value ? '' : $value;
        $this->resetPage();
    }

    // ─────────────────────────────────────────────────────────────
    //  Computed
    // ─────────────────────────────────────────────────────────────

    /** @return array<string, string> */
    #[Computed]
    public function statusLabels(): array
    {
        return BusinessApplication::STATUS_LABELS;
    }

    #[Computed]
    public function requiredDocumentsCount(): int
    {
        return count(BusinessApplication::REQUIRED_DOCUMENTS);
    }

    #[Computed]
    public function hasActiveFilter(): bool
    {
        return $this->search !== '' || $this->status !== '';
    }

    #[Computed]
    public function applications()
    {
        return BusinessApplication::query()
            ->with([
                'user:id,name,email,avatar',
                'typeOfTenant:id,type',
            ])
            ->withCount([
                'documents',
                'verifications as mismatches_count' => fn ($q) => $q->where('matched', false),
            ])
            ->when(trim($this->search) !== '', function ($q) {
                $search = trim($this->search);
                $q->where(function ($sub) use ($search) {
                    $sub->where('business_name', 'like', "%{$search}%")
                        ->orWhere('owner_full_name', 'like', "%{$search}%")
                        ->orWhere('contact_email', 'like', "%{$search}%")
                        ->orWhere('business_registration_number', 'like', "%{$search}%")
                        ->orWhere('tin_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%"));
                });
            })
            ->when($this->status === 'awaiting_review', function ($q) {
                $q->whereIn('status', ['pending', 'under_review']);
            })
            ->when(
                $this->status !== ''
                && $this->status !== 'awaiting_review'
                && array_key_exists($this->status, BusinessApplication::STATUS_LABELS),
                function ($q) {
                    $q->where('status', $this->status);
                }
            )
            ->orderByRaw("FIELD(status, 'pending', 'under_review', 'needs_revision', 'draft', 'approved', 'rejected')")
            ->latest('submitted_at')
            ->paginate($this->perPage);
    }

    /**
     * Aggregate counts for the four stat cards.
     *
     * Uses the query builder instead of Eloquent — the result is a plain
     * stdClass with the four SUM aliases, not a Model with phantom
     * attributes. This matters if anyone ever adds caching here:
     * Rule 79 forbids caching Eloquent instances with the database driver.
     */
    #[Computed]
    public function stats(): object
    {
        return DB::table('business_applications')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('pending','under_review') THEN 1 ELSE 0 END) as awaiting_review,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            ")
            ->first();
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Status chip config — label + tailwind class string.
     *
     * NOT a #[Computed] — the return value depends on the argument, and
     * Livewire's #[Computed] memoizes per method name, not per arg pair.
     * Calling this with different statuses would return the first cached
     * value for every row.
     *
     * @return array{label: string, classes: string}
     */
    public function statusConfigFor(?string $status): array
    {
        return match ($status) {
            'pending'        => ['label' => 'Pending',        'classes' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300 border-amber-200 dark:border-amber-500/30'],
            'under_review'   => ['label' => 'Under Review',   'classes' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300 border-blue-200 dark:border-blue-500/30'],
            'needs_revision' => ['label' => 'Needs Revision', 'classes' => 'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300 border-orange-200 dark:border-orange-500/30'],
            'draft'          => ['label' => 'Draft',          'classes' => 'bg-slate-100 text-slate-700 dark:bg-slate-500/15 dark:text-slate-300 border-slate-200 dark:border-slate-500/30'],
            'approved'       => ['label' => 'Approved',       'classes' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30'],
            'rejected'       => ['label' => 'Rejected',       'classes' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300 border-rose-200 dark:border-rose-500/30'],
            default          => ['label' => ucfirst((string) $status), 'classes' => 'bg-gray-100 text-gray-700 dark:bg-gray-500/15 dark:text-gray-300 border-gray-200 dark:border-gray-500/30'],
        };
    }
};
?>

<?php $stats = $this->stats; ?>

<div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6">

    
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">KYB Queue</span>
            </div>
            <h1 class="font-display text-3xl md:text-4xl font-semibold text-gray-900 dark:text-white">
                Business <em class="italic text-primary-600 dark:text-primary-400">Applications</em>
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-2">
                Review and process KYB submissions from business owners. Approving or rejecting sends an email to the applicant.
            </p>
        </div>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('message')): ?>
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 4000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><?php echo e(session('message')); ?></span>
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
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?>
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 5000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span><?php echo e(session('error')); ?></span>
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
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <?php
            $kpis = [
                ['value' => '',                'label' => 'Total',           'count' => $stats->total ?? 0,          'dot' => 'bg-primary-500',  'accent' => 'primary'],
                ['value' => 'awaiting_review', 'label' => 'Awaiting Review', 'count' => $stats->awaiting_review ?? 0, 'dot' => 'bg-amber-500',    'accent' => 'amber'],
                ['value' => 'approved',        'label' => 'Approved',        'count' => $stats->approved ?? 0,        'dot' => 'bg-emerald-500',  'accent' => 'emerald'],
                ['value' => 'rejected',        'label' => 'Rejected',        'count' => $stats->rejected ?? 0,        'dot' => 'bg-rose-500',     'accent' => 'rose'],
            ];
        ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $kpis; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $kpi): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php $isActive = $status === $kpi['value']; ?>
            <button type="button"
                    wire:click="filterBy('<?php echo e($kpi['value']); ?>')"
                    <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'kpi-'.e($kpi['value'] !== '' ? $kpi['value'] : 'all').''; ?>wire:key="kpi-<?php echo e($kpi['value'] !== '' ? $kpi['value'] : 'all'); ?>"
                    aria-pressed="<?php echo e($isActive ? 'true' : 'false'); ?>"
                    class="text-left bg-white dark:bg-gray-800/90 rounded-xl border shadow-sm p-3.5
                           transition-all duration-200 active:scale-[0.98]
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-<?php echo e($kpi['accent']); ?>-500/50
                           <?php echo e($isActive
                              ? 'border-' . $kpi['accent'] . '-500/60 ring-2 ring-' . $kpi['accent'] . '-500/20'
                              : 'border-gray-200/80 dark:border-gray-700/80 hover:border-' . $kpi['accent'] . '-500/30'); ?>">
                <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full <?php echo e($kpi['dot']); ?>"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400"><?php echo e($kpi['label']); ?></span>
                </div>
                <p class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white tabular-nums"><?php echo e(number_format($kpi['count'])); ?></p>
            </button>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4">
        <div class="flex flex-wrap gap-2 items-center">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input wire:model.live.debounce.300ms="search"
                       type="search"
                       enterkeyhint="search"
                       aria-label="Search applications"
                       placeholder="Search business, owner, email, TIN, or reg #…"
                       class="input w-full"
                       style="padding-left: 2.5rem;">
            </div>

            <select wire:model.live="status"
                    aria-label="Filter by status"
                    class="input w-full sm:w-auto sm:min-w-[180px]">
                <option value="">All statuses</option>
                <option value="awaiting_review">Awaiting Review</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->statusLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <option <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'status-opt-'.e($value).''; ?>wire:key="status-opt-<?php echo e($value); ?>" value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->hasActiveFilter): ?>
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
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->applications->isEmpty()): ?>
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-12 text-center">
            <div class="flex flex-col items-center max-w-md mx-auto">
                <div class="p-3 rounded-2xl bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <p class="mt-4 text-base font-semibold text-gray-900 dark:text-white">
                    <?php echo e($this->hasActiveFilter ? 'No applications match your filters' : 'No applications yet'); ?>

                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    <?php echo e($this->hasActiveFilter
                        ? 'Try a different search term or status.'
                        : 'KYB submissions from business owners will appear here.'); ?>

                </p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->hasActiveFilter): ?>
                    <button type="button" wire:click="clearFilters"
                            class="mt-5 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                   transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        Clear Filters
                    </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div wire:loading.class="opacity-40 pointer-events-none"
             wire:target="search,status,clearFilters,filterBy,gotoPage,nextPage,previousPage"
             class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 transition-opacity duration-200">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->applications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $app): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $statusConfig  = $this->statusConfigFor($app->status);
                    $docsLabel     = $app->documents_count . '/' . $this->requiredDocumentsCount;
                    $mismatchCount = (int) $app->mismatches_count;
                    $isComplete    = $app->documents_count >= $this->requiredDocumentsCount;
                ?>

                <article <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'app-'.e($app->id).''; ?>wire:key="app-<?php echo e($app->id); ?>"
                         class="group bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm
                                hover:shadow-md hover:border-primary-300 dark:hover:border-primary-500/40
                                overflow-hidden flex flex-col transition-all duration-200">

                    
                    <div class="relative aspect-[3/1] bg-gray-100 dark:bg-gray-900 overflow-hidden">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($app->cover_photo_path): ?>
                            <img src="<?php echo e(asset('storage/' . $app->cover_photo_path)); ?>"
                                 alt="<?php echo e($app->business_name); ?>"
                                 loading="lazy"
                                 decoding="async"
                                 class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-primary-500 via-primary-600 to-primary-700 text-white/70">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        
                        <div class="absolute top-3 right-3">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border shadow-sm backdrop-blur-sm
                                         <?php echo e($statusConfig['classes']); ?>">
                                <span class="w-1 h-1 rounded-full bg-current"></span>
                                <?php echo e($statusConfig['label']); ?>

                            </span>
                        </div>

                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mismatchCount > 0): ?>
                            <div class="absolute top-3 left-3">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                                             bg-rose-600 text-white shadow-sm backdrop-blur-sm"
                                      title="<?php echo e($mismatchCount); ?> automated verification check<?php echo e($mismatchCount === 1 ? '' : 's'); ?> failed.">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    <?php echo e($mismatchCount); ?>

                                </span>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    
                    <div class="p-4 flex-1 flex flex-col gap-3">

                        
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white text-base leading-snug line-clamp-2 min-h-[2.5rem]">
                                <?php echo e($app->business_name ?? '—'); ?>

                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                <?php echo e($app->typeOfTenant?->type ?? 'Uncategorized'); ?>

                            </p>
                        </div>

                        
                        <div class="flex items-center gap-2 pt-2 border-t border-gray-100 dark:border-gray-700/60">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($app->user?->avatar): ?>
                                <img src="<?php echo e(asset('storage/' . $app->user->avatar)); ?>"
                                     alt=""
                                     loading="lazy"
                                     decoding="async"
                                     class="w-7 h-7 rounded-full object-cover shrink-0">
                            <?php else: ?>
                                <div class="w-7 h-7 rounded-full bg-primary-600 text-white flex items-center justify-center text-[10px] font-bold shrink-0">
                                    <?php echo e(strtoupper(substr($app->user?->name ?? '?', 0, 1))); ?>

                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-gray-900 dark:text-white truncate">
                                    <?php echo e($app->user?->name ?? '—'); ?>

                                </p>
                                <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate">
                                    <?php echo e($app->user?->email ?? '—'); ?>

                                </p>
                            </div>
                        </div>

                        
                        <div class="space-y-1.5 text-xs">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-gray-500 dark:text-gray-400 inline-flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Documents
                                </span>
                                <span class="font-semibold tabular-nums <?php echo e($isComplete ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'); ?>">
                                    <?php echo e($docsLabel); ?>

                                </span>
                            </div>

                            <div class="flex items-center justify-between gap-2">
                                <span class="text-gray-500 dark:text-gray-400 inline-flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    Submitted
                                </span>
                                <span class="text-gray-900 dark:text-white tabular-nums">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($app->submitted_at): ?>
                                        <?php echo e($app->submitted_at->format('M j, Y')); ?>

                                    <?php else: ?>
                                        <span class="text-gray-400 dark:text-gray-500 italic">Not yet</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </span>
                            </div>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($app->submitted_at): ?>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 pl-[22px]">
                                    <?php echo e($app->submitted_at->diffForHumans()); ?>

                                </p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        
                        <div class="mt-auto pt-3 border-t border-gray-100 dark:border-gray-700/60">
                            <a href="<?php echo e(route('superadmin.business-applications.show', $app)); ?>" wire:navigate
                               aria-label="Review application for <?php echo e($app->business_name); ?>"
                               class="w-full inline-flex items-center justify-center gap-2 h-9 px-3.5 rounded-lg
                                      border border-primary-300 dark:border-primary-500/40
                                      bg-white dark:bg-gray-800 text-primary-700 dark:text-primary-300
                                      text-xs font-semibold
                                      transition-all duration-200 active:scale-95
                                      hover:bg-primary-50 dark:hover:bg-primary-500/10
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                <span>Review application</span>
                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </article>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->applications->hasPages()): ?>
            <div class="pt-2">
                <?php echo e($this->applications->links()); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views\superadmin\pages\business-application\⚡view-business-application.blade.php ENDPATH**/ ?>