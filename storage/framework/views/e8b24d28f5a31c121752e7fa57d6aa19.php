
<?php

use App\Models\AccountDeletionRequest;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('superadmin.layouts.app')]
#[Title('Account Deletion Requests')]
class extends Component
{
    use WithPagination;

    public string $statusFilter = AccountDeletionRequest::STATUS_PENDING;
    public string $search = '';

    public function mount(): void
    {
        abort_unless(
            Auth::check() && Auth::user()->hasRole('super-admin'),
            403,
            'Super Admin access only.'
        );
    }

    public function hydrate(): void
    {
        abort_unless(
            Auth::check() && Auth::user()->hasRole('super-admin'),
            403
        );
    }

    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedSearch():       void { $this->resetPage(); }

    #[Computed]
    public function requests()
    {
        return AccountDeletionRequest::query()
            ->with([
                'user:id,name,email,tenant_id',
                'user.tenant:id,name',
                'reviewer:id,name',
            ])
            ->when($this->statusFilter !== 'all', fn ($q) =>
                $q->where('status', $this->statusFilter)
            )
            ->when($this->search, fn ($q) => $q->where(fn ($sub) =>
                $sub->whereHas('user', fn ($u) => $u
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                )
            ))
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function pendingCount(): int
    {
        return AccountDeletionRequest::query()
            ->where('status', AccountDeletionRequest::STATUS_PENDING)
            ->count();
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-5xl mx-auto space-y-6">

    
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Platform</span>
            </div>
            <h1 class="font-display text-3xl md:text-4xl font-semibold text-gray-900 dark:text-white">
                Account <em class="italic text-primary-600 dark:text-primary-400">Deletion Requests</em>
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-2">
                Review and act on pending account deletion requests.
            </p>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->pendingCount > 0): ?>
            <div class="rounded-xl border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 px-4 py-2.5">
                <p class="flex items-center gap-2 text-sm font-semibold text-amber-900 dark:text-amber-200 tabular-nums">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" aria-hidden="true"></span>
                    <?php echo e($this->pendingCount); ?> pending
                </p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4">
        <div class="flex flex-col sm:flex-row gap-3 sm:items-center">

            <div class="relative flex-1 min-w-[200px]">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 w-4 h-4 -translate-y-1/2 text-gray-400 dark:text-gray-500"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Search by name or email…"
                       enterkeyhint="search"
                       aria-label="Search deletion requests"
                       class="input w-full"
                       style="padding-left: 2.5rem;">
            </div>

            
            <div class="flex flex-wrap gap-2 items-center">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                    \App\Models\AccountDeletionRequest::STATUS_PENDING  => 'Pending',
                    \App\Models\AccountDeletionRequest::STATUS_APPROVED => 'Approved',
                    \App\Models\AccountDeletionRequest::STATUS_REJECTED => 'Rejected',
                    'all'                                               => 'All',
                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php $isActive = $statusFilter === $value; ?>
                    <button type="button"
                            wire:click="$set('statusFilter', '<?php echo e($value); ?>')"
                            <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'status-pill-'.e($value).''; ?>wire:key="status-pill-<?php echo e($value); ?>"
                            aria-pressed="<?php echo e($isActive ? 'true' : 'false'); ?>"
                            class="inline-flex items-center gap-2 h-9 pl-3.5 pr-1.5 rounded-full text-xs font-semibold uppercase tracking-wide border
                                   transition-all duration-200 active:scale-95 shrink-0
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                   <?php echo e($isActive
                                      ? 'bg-primary-600 border-primary-600 text-white shadow-sm'
                                      : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400'); ?>">
                        <span><?php echo e($label); ?></span>
                    </button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </div>
    </div>

    
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm overflow-hidden">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->requests->isEmpty()): ?>
            <div class="px-6 py-16 text-center">
                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-400 dark:text-gray-500">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <p class="text-base font-semibold text-gray-900 dark:text-white">
                    No requests found
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Try a different filter or search term.
                </p>
            </div>
        <?php else: ?>
            <div wire:loading.class="opacity-40 pointer-events-none"
                 wire:target="search,statusFilter,gotoPage,nextPage,previousPage"
                 class="divide-y divide-gray-100 dark:divide-gray-700/60 transition-opacity duration-200">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->requests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $request): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $statusClasses = match ($request->status) {
                            \App\Models\AccountDeletionRequest::STATUS_PENDING  => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300 border-amber-200 dark:border-amber-500/30',
                            \App\Models\AccountDeletionRequest::STATUS_APPROVED => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30',
                            \App\Models\AccountDeletionRequest::STATUS_REJECTED => 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300 border-rose-200 dark:border-rose-500/30',
                            default                                             => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-600',
                        };
                    ?>
                    <a href="<?php echo e(route('superadmin.deletion-requests.show', ['request' => $request->id])); ?>"
                       wire:navigate
                       <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'adr-'.e($request->id).''; ?>wire:key="adr-<?php echo e($request->id); ?>"
                       aria-label="View deletion request for <?php echo e($request->user?->name ?? 'unknown user'); ?>"
                       class="block px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/40
                              transition-all duration-200 active:scale-[0.995]
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-inset">

                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                        <?php echo e($request->user?->name ?? 'Unknown user'); ?>

                                    </p>
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider border <?php echo e($statusClasses); ?>">
                                        <span class="w-1 h-1 rounded-full bg-current"></span>
                                        <?php echo e($request->statusLabel()); ?>

                                    </span>
                                </div>

                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate">
                                    <?php echo e($request->user?->email ?? '—'); ?>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($request->tenant): ?>
                                        · <span class="font-medium text-gray-700 dark:text-gray-300"><?php echo e($request->tenant->name); ?></span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </p>

                                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                                    Scope: <span class="font-medium text-gray-700 dark:text-gray-300"><?php echo e($request->scopeLabel()); ?></span>
                                    · Requested <span class="tabular-nums"><?php echo e($request->created_at->diffForHumans()); ?></span>
                                </p>
                            </div>

                            <svg class="w-4 h-4 shrink-0 text-gray-400 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->requests->hasPages()): ?>
                <div class="px-5 py-4 border-t border-gray-200/80 dark:border-gray-700/80 bg-gray-50/50 dark:bg-gray-900/30">
                    <?php echo e($this->requests->links()); ?>

                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

</div><?php /**PATH C:\laragon\www\Capstone\resources\views\superadmin\pages\deletion-request\⚡view-deletion-requests.blade.php ENDPATH**/ ?>