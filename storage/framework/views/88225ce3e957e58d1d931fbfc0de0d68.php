
<?php

use App\Models\AccountDeletionRequest;
use App\Services\SuperadminNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Deletion Request Pending')]
class extends Component
{
    public ?AccountDeletionRequest $pendingRequest = null;

    public function mount(): void
    {
        abort_unless(Auth::check(), 403);

        $user = Auth::user();

        // Tenant users only. Super-admins and pure tourists shouldn't
        // land here.
        abort_unless(
            $user->tenant_id && ! $user->hasRole('super-admin'),
            403
        );

        $this->pendingRequest = AccountDeletionRequest::query()
            ->with('tenant:id,name')
            ->where('user_id', $user->id)
            ->where('status', AccountDeletionRequest::STATUS_PENDING)
            ->latest()
            ->first();

        // If there's no pending request (already cancelled or already
        // processed by a superadmin), send them back to the dashboard.
        if (! $this->pendingRequest) {
            $this->redirect(route('tenant.dashboard'), navigate: true);
        }
    }

    public function hydrate(): void
    {
        abort_unless(Auth::check(), 403, 'Your session has expired. Please sign in again.');
    }

    public function cancelRequest()
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $request = AccountDeletionRequest::query()
            ->where('user_id', $user->id)
            ->where('status', AccountDeletionRequest::STATUS_PENDING)
            ->latest()
            ->first();

        if (! $request) {
            return redirect()->route('tenant.dashboard');
        }

        DB::transaction(function () use ($request): void {
            $request->update([
                'status'       => AccountDeletionRequest::STATUS_CANCELLED,
                'reviewed_at'  => now(),
                'review_notes' => 'Cancelled by user.',
            ]);
        });

        // Refresh BOTH superadmin badges (KYB + deletion-requests) on
        // their next page load. Routing through the service guarantees
        // the cache keys stay in sync with the writers in
        // DeletionRequestController and BusinessApplicationService.
        app(SuperadminNotificationService::class)->flush();

        return redirect()
            ->route('tenant.dashboard')
            ->with('message', 'Your deletion request has been cancelled. Your business account is fully active again.');
    }
};
?>

<div class="py-10 sm:py-14 px-4 sm:px-6">
    <div class="mx-auto w-full max-w-xl space-y-5">

        
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-amber-200/80 dark:border-amber-500/30 shadow-sm overflow-hidden">

            
            <div class="px-6 sm:px-7 pt-7 pb-6 border-b border-amber-100 dark:border-amber-500/20">
                <div class="flex items-start gap-3 mb-4">
                    <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                            Account paused
                        </p>
                        <h1 class="mt-0.5 text-xl sm:text-2xl font-bold tracking-tight text-gray-900 dark:text-white leading-tight">
                            Deletion request pending
                        </h1>
                    </div>
                </div>

                <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                    Your business account is on hold while a superadmin reviews your deletion request. Until it's processed, you can't access the admin area.
                </p>
            </div>

            
            <div class="px-6 sm:px-7 py-6">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400 shrink-0">Requested</dt>
                        <dd class="font-medium text-gray-900 dark:text-white text-right tabular-nums">
                            <?php echo e($pendingRequest->created_at->format('M j, Y \a\t g:i A')); ?>

                        </dd>
                    </div>

                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400 shrink-0">Scope</dt>
                        <dd class="font-medium text-gray-900 dark:text-white text-right">
                            <?php echo e($pendingRequest->scopeLabel()); ?>

                        </dd>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pendingRequest->tenant): ?>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400 shrink-0">Business</dt>
                            <dd class="font-medium text-gray-900 dark:text-white text-right truncate">
                                <?php echo e($pendingRequest->tenant->name); ?>

                            </dd>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-gray-500 dark:text-gray-400 shrink-0">Status</dt>
                        <dd class="text-right">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 dark:bg-amber-500/20 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider text-amber-800 dark:text-amber-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
                                Pending review
                            </span>
                        </dd>
                    </div>
                </dl>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pendingRequest->reason): ?>
                    <div class="mt-5 pt-5 border-t border-gray-100 dark:border-gray-700/60">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1.5">
                            Your reason
                        </p>
                        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">
                            <?php echo e($pendingRequest->reason); ?>

                        </p>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div class="px-6 sm:px-7 pb-6">
                <div class="rounded-xl border border-blue-200/70 dark:border-blue-500/30 bg-blue-50/60 dark:bg-blue-500/[0.06] px-3.5 py-3 text-xs text-blue-800 dark:text-blue-300 flex items-start gap-2.5">
                    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="leading-relaxed">
                        Changed your mind? You can cancel the request below — your account will unlock immediately and everything stays exactly as it was.
                    </span>
                </div>
            </div>

            
            <div class="px-6 sm:px-7 pb-7 flex flex-col sm:flex-row gap-2.5">
                <button type="button"
                        wire:click="cancelRequest"
                        wire:confirm="Cancel your deletion request? Your account will unlock immediately."
                        wire:loading.attr="disabled"
                        wire:target="cancelRequest"
                        class="flex-1 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl
                               bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                               transition-all duration-200 active:scale-95
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                               disabled:opacity-60 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="cancelRequest">Cancel request &amp; unlock account</span>
                    <span wire:loading wire:target="cancelRequest" class="inline-flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Cancelling…
                    </span>
                </button>

                <form method="POST" action="<?php echo e(route('logout')); ?>" class="sm:w-auto">
                    <?php echo csrf_field(); ?>
                    <button type="submit"
                            class="w-full sm:w-auto inline-flex items-center justify-center h-11 px-5 rounded-xl
                                   border border-gray-300 dark:border-gray-600
                                   bg-white dark:bg-gray-800
                                   text-gray-700 dark:text-gray-200
                                   text-sm font-semibold
                                   transition-all duration-200 active:scale-95
                                   hover:bg-gray-50 dark:hover:bg-gray-700
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        Sign out
                    </button>
                </form>
            </div>
        </div>

        
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 space-y-4">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    What happens next
                </h2>
            </div>

            <ol class="space-y-3 text-xs text-gray-600 dark:text-gray-400">
                <li class="flex items-start gap-2.5">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 shrink-0 font-bold text-[10px] tabular-nums">
                        1
                    </span>
                    <span class="leading-relaxed">
                        A superadmin reviews your request and the reason you provided.
                    </span>
                </li>
                <li class="flex items-start gap-2.5">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 shrink-0 font-bold text-[10px] tabular-nums">
                        2
                    </span>
                    <span class="leading-relaxed">
                        <strong class="font-semibold text-gray-700 dark:text-gray-300">If approved:</strong>
                        your business account and all associated data are permanently deleted. This cannot be undone.
                    </span>
                </li>
                <li class="flex items-start gap-2.5">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300 shrink-0 font-bold text-[10px] tabular-nums">
                        3
                    </span>
                    <span class="leading-relaxed">
                        <strong class="font-semibold text-gray-700 dark:text-gray-300">If rejected:</strong>
                        your account unlocks automatically and you're back in — no further action needed.
                    </span>
                </li>
            </ol>

            <p class="pt-3 border-t border-gray-100 dark:border-gray-700/60 text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                You'll stay on this page until the review completes. Refreshing is safe.
            </p>
        </div>

        
        <p class="text-center text-xs text-gray-400 dark:text-gray-500 leading-relaxed">
            Need help?
            <?php
                $supportEmail = config('legal.data_controller.email');
            ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($supportEmail): ?>
                Email
                <a href="mailto:<?php echo e($supportEmail); ?>"
                   class="font-medium text-primary-600 dark:text-primary-400 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                    <?php echo e($supportEmail); ?>

                </a>
                if you believe this was made in error.
            <?php else: ?>
                Contact your platform administrator if you believe this was made in error.
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </p>

    </div>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views\tenant\pages\settings\⚡deletion-pending.blade.php ENDPATH**/ ?>