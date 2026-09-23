
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Property;
use App\Models\Booking;
use App\Models\Payment;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

new
#[Layout('superadmin.layouts.app')]
#[Title('Preview Tenant')]
class extends Component
{
    public Tenant $tenant;

    public function mount(Tenant $tenant): void
    {
        $this->authorizeSuperadmin();
        $this->tenant = $tenant;
    }

    /**
     * Livewire re-hydrates the bound model by ID on every subsequent
     * request. Route middleware only runs on the original GET — the
     * superadmin guard must be re-verified here or a tampered action
     * can target any tenant.
     */
    public function hydrate(): void
    {
        $this->authorizeSuperadmin();
    }

    protected function authorizeSuperadmin(): void
    {
        abort_unless(
            Auth::check() && Auth::user()->hasRole('super-admin'),
            403,
            'Super Admin access only.'
        );
    }

    // ─────────────────────────────────────────────────────────
    //  Computed
    // ─────────────────────────────────────────────────────────

    /**
     * @return array{users: int, properties: int, bookings: int, payments: int}
     */
    #[Computed]
    public function stats(): array
    {
        $tenantId = $this->tenant->id;

        return [
            'users'      => (int) User::query()->where('tenant_id', $tenantId)->count(),
            'properties' => (int) Property::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenantId)->count(),
            'bookings'   => (int) Booking::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenantId)->count(),
            'payments'   => (int) Payment::withoutGlobalScope(TenantScope::class)->where('tenant_id', $tenantId)->count(),
        ];
    }

    /**
     * True when the tenant has any booking or payment records — the
     * condition under which `reject()` refuses to hard-delete.
     */
    #[Computed]
    public function hasLiveData(): bool
    {
        $s = $this->stats;

        return $s['bookings'] > 0 || $s['payments'] > 0;
    }

    // ─────────────────────────────────────────────────────────
    //  Actions
    // ─────────────────────────────────────────────────────────

    public function approve()
    {
        $this->authorizeSuperadmin();

        try {
            DB::transaction(function (): void {
                $tenant = Tenant::query()
                    ->whereKey($this->tenant->id)
                    ->lockForUpdate()
                    ->first();

                if (!$tenant) {
                    throw new \RuntimeException('Tenant not found.');
                }

                $tenant->update(['is_active' => true]);

                // Activate every user attached to this tenant. The
                // previous implementation only touched the first user
                // (arbitrary ordering), leaving seeded employees or
                // secondary owners inactive.
                $users = User::query()
                    ->where('tenant_id', $tenant->id)
                    ->orderBy('id')
                    ->get();

                foreach ($users as $user) {
                    if (!$user->is_active) {
                        $user->update(['is_active' => true]);
                    }
                }

                // Ensure the primary account carries the `admin` role.
                if ($first = $users->first()) {
                    if (!$first->hasRole('admin')) {
                        $first->assignRole('admin');
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error('Tenant approval failed', [
                'tenant_id' => $this->tenant->id,
                'error'     => $e->getMessage(),
            ]);

            session()->flash('error', 'Failed to approve this tenant. Please try again.');
            return null;
        }

        session()->flash('message', 'Tenant approved and activated.');
        return $this->redirectRoute('superadmin.tenants.index', navigate: true);
    }

    /**
     * Reject the tenant.
     *
     * Safety: if the tenant has any booking or payment records, DO NOT
     * delete it — deactivate instead so the financial history survives.
     * Only tenants with no transaction history are hard-deleted, and
     * even that runs inside a transaction with a row lock.
     */
    public function reject()
    {
        $this->authorizeSuperadmin();

        // ── Guarded path: deactivate instead of delete ────────
        if ($this->hasLiveData) {
            try {
                DB::transaction(function (): void {
                    $tenant = Tenant::query()
                        ->whereKey($this->tenant->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$tenant) {
                        return;
                    }

                    $tenant->update(['is_active' => false]);

                    User::query()
                        ->where('tenant_id', $tenant->id)
                        ->update(['is_active' => false]);
                });
            } catch (\Throwable $e) {
                Log::error('Tenant deactivation failed', [
                    'tenant_id' => $this->tenant->id,
                    'error'     => $e->getMessage(),
                ]);

                session()->flash('error', 'Failed to deactivate this tenant. Please try again.');
                return null;
            }

            session()->flash(
                'warning',
                'This tenant has booking or payment history on file. It has been deactivated instead of deleted — the records are preserved.'
            );

            // Refresh the component state so the UI reflects the change.
            $this->tenant->refresh();
            unset($this->stats, $this->hasLiveData);

            return null;
        }

        // ── Safe path: hard delete an empty tenant ────────────
        try {
            DB::transaction(function (): void {
                $tenant = Tenant::query()
                    ->whereKey($this->tenant->id)
                    ->lockForUpdate()
                    ->first();

                $tenant?->delete();
            });
        } catch (\Throwable $e) {
            Log::error('Tenant delete failed', [
                'tenant_id' => $this->tenant->id,
                'error'     => $e->getMessage(),
            ]);

            session()->flash('error', 'Failed to remove this tenant. Please try again.');
            return null;
        }

        session()->flash('message', 'Tenant application rejected and removed.');
        return $this->redirectRoute('superadmin.tenants.index', navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto space-y-6">

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('message')): ?>
        <div class="flex items-start gap-3 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 border-l-4 border-l-emerald-500 p-4 rounded-md">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <p class="text-sm text-emerald-700 dark:text-emerald-300 font-medium"><?php echo e(session('message')); ?></p>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('warning')): ?>
        <div class="flex items-start gap-3 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 border-l-4 border-l-amber-500 p-4 rounded-md">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-amber-700 dark:text-amber-300 font-medium"><?php echo e(session('warning')); ?></p>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('error')): ?>
        <div class="flex items-start gap-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 border-l-4 border-l-rose-500 p-4 rounded-md">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-rose-700 dark:text-rose-300 font-medium"><?php echo e(session('error')); ?></p>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div class="min-w-0">
            <a href="<?php echo e(route('superadmin.tenants.index')); ?>" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors mb-3 rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Tenants
            </a>

            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white truncate">
                <?php echo e($tenant->name); ?>

            </h1>

            <div class="mt-2 flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-500/30">
                    <?php echo e($tenant->typeOfTenant?->type ?? 'Business'); ?>

                </span>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->is_active): ?>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Active
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-gray-100 dark:bg-gray-500/15 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-500/30">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                        Inactive
                    </span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->is_recommended): ?>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                        Recommended
                    </span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <div class="flex flex-shrink-0 gap-2">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$tenant->is_active): ?>
                <button type="button"
                        wire:click="approve"
                        wire:loading.attr="disabled"
                        wire:target="approve"
                        class="btn-primary active:scale-95 transition-transform focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-60 disabled:cursor-not-allowed inline-flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span wire:loading.remove wire:target="approve">Approve</span>
                    <span wire:loading wire:target="approve">Approving…</span>
                </button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <button type="button"
                    wire:click="reject"
                    wire:confirm="<?php echo e($this->hasLiveData
                        ? 'This tenant has booking or payment history — it will be deactivated instead of deleted. Continue?'
                        : 'Reject and permanently delete this tenant? This cannot be undone.'); ?>"
                    wire:loading.attr="disabled"
                    wire:target="reject"
                    class="btn-danger active:scale-95 transition-transform focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 disabled:opacity-60 disabled:cursor-not-allowed inline-flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <span wire:loading.remove wire:target="reject">Reject</span>
                <span wire:loading wire:target="reject">Processing…</span>
            </button>
        </div>
    </div>

    
    <?php $stats = $this->stats; ?>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-xl p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Users</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1 tabular-nums"><?php echo e($stats['users']); ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-xl p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Properties</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1 tabular-nums"><?php echo e($stats['properties']); ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-xl p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Bookings</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1 tabular-nums"><?php echo e($stats['bookings']); ?></p>
        </div>
        <div class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-xl p-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Payments</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1 tabular-nums"><?php echo e($stats['payments']); ?></p>
        </div>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->hasLiveData): ?>
        <div class="flex items-start gap-3 bg-amber-50 dark:bg-amber-500/[0.06] border border-amber-200 dark:border-amber-500/30 border-l-4 border-l-amber-500 p-4 rounded-md">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Reject will deactivate, not delete</p>
                <p class="text-xs text-amber-800 dark:text-amber-300/90 mt-0.5 leading-relaxed">
                    This tenant has
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($stats['bookings'] > 0): ?> <?php echo e($stats['bookings']); ?> booking<?php echo e($stats['bookings'] === 1 ? '' : 's'); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($stats['bookings'] > 0 && $stats['payments'] > 0): ?> and <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($stats['payments'] > 0): ?> <?php echo e($stats['payments']); ?> payment<?php echo e($stats['payments'] === 1 ? '' : 's'); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    on file. Rejecting will set the tenant and its users to inactive; the records will be preserved.
                </p>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="bg-white dark:bg-gray-800/90 border border-gray-200/80 dark:border-gray-700/80 rounded-2xl shadow-sm p-6 space-y-4">
        <h2 class="text-base font-bold text-gray-900 dark:text-white">Business Details</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Email</p>
                <p class="text-sm font-medium text-gray-900 dark:text-white break-all">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->email): ?>
                        <a href="mailto:<?php echo e($tenant->email); ?>"
                           class="hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                            <?php echo e($tenant->email); ?>

                        </a>
                    <?php else: ?>
                        —
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </p>
            </div>

            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Contact</p>
                <p class="text-sm font-medium text-gray-900 dark:text-white">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->contact_number): ?>
                        <a href="tel:<?php echo e($tenant->contact_number); ?>"
                           class="hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                            <?php echo e($tenant->contact_number); ?>

                        </a>
                    <?php else: ?>
                        —
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </p>
            </div>

            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Address</p>
                <p class="text-sm font-medium text-gray-900 dark:text-white">
                    <?php echo e($tenant->address ?: '—'); ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->barangay): ?>
                        <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5"><?php echo e($tenant->barangay); ?></span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </p>
            </div>

            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Type</p>
                <p class="text-sm font-medium text-gray-900 dark:text-white">
                    <?php echo e($tenant->typeOfTenant?->type ?? '—'); ?>

                </p>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->verified_at): ?>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Verified</p>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        <?php echo e($tenant->verified_at->format('M d, Y')); ?>

                    </p>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->permit_expires_at): ?>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Permit Expires</p>
                    <p class="text-sm font-medium <?php echo e($tenant->isPermitExpired() ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-white'); ?>">
                        <?php echo e($tenant->permit_expires_at->format('M d, Y')); ?>

                    </p>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    
    <p class="text-xs text-gray-500 dark:text-gray-400 text-center leading-relaxed">
        Approving activates the tenant and every linked user, and grants the primary account the
        <span class="font-mono">admin</span> role.
    </p>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views\superadmin\pages\tenant\⚡preview-tenant.blade.php ENDPATH**/ ?>