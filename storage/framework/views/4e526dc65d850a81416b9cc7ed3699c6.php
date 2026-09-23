
<?php

use App\Models\AccountDeletionRequest;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\SuperadminNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Delete Account')]
class extends Component
{
    public string $scope        = AccountDeletionRequest::SCOPE_BOTH;
    public string $password     = '';
    public string $confirmation = '';
    public string $reason       = '';

    // ─────────────────────────────────────────────────────────
    //  Lifecycle
    // ─────────────────────────────────────────────────────────

    public function mount(): void
    {
        abort_unless(Auth::check(), 403);
    }

    /**
     * Livewire update requests bypass route middleware. Re-verify auth on
     * every request so a de-authed user with a stale snapshot can't drive
     * cancelRequest() or the model updater hooks.
     */
    public function hydrate(): void
    {
        abort_unless(Auth::check(), 403, 'Your session has expired. Please sign in again.');
    }

    // ─────────────────────────────────────────────────────────
    //  Computed
    // ─────────────────────────────────────────────────────────

    #[Computed]
    public function viewer(): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user;
    }

    #[Computed]
    public function isBusinessOwner(): bool
    {
        return $this->viewer?->isBusinessOwner() ?? false;
    }

    #[Computed]
    public function pendingRequest(): ?AccountDeletionRequest
    {
        if (! $this->viewer) {
            return null;
        }

        return AccountDeletionRequest::query()
            ->where('user_id', $this->viewer->id)
            ->where('status', AccountDeletionRequest::STATUS_PENDING)
            ->latest()
            ->first();
    }

    // ─────────────────────────────────────────────────────────
    //  Validation
    // ─────────────────────────────────────────────────────────

    protected function rules(): array
    {
        $rules = [
            'password'     => ['required', 'string'],
            'confirmation' => ['required', 'string', 'in:DELETE'],
            'reason'       => ['nullable', 'string', 'max:500'],
        ];

        if ($this->isBusinessOwner) {
            $rules['scope'] = [
                'required',
                'in:' . AccountDeletionRequest::SCOPE_BUSINESS_ONLY . ',' . AccountDeletionRequest::SCOPE_BOTH,
            ];
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'confirmation.in'   => 'Type DELETE in uppercase to confirm.',
            'password.required' => 'Please enter your password to continue.',
        ];
    }

    // ─────────────────────────────────────────────────────────
    //  Actions
    // ─────────────────────────────────────────────────────────

    public function submit()
    {
        $this->validate();

        $user = $this->viewer;
        abort_unless($user, 403);

        if (! Hash::check($this->password, $user->password)) {
            $this->addError('password', 'The password you entered is incorrect.');
            return null;
        }

        return $user->isBusinessOwner()
            ? $this->submitBusinessRequest($user)
            : $this->executeTouristDeletion($user);
    }

    protected function submitBusinessRequest(User $user)
    {
        $pending = AccountDeletionRequest::query()
            ->where('user_id', $user->id)
            ->where('status', AccountDeletionRequest::STATUS_PENDING)
            ->exists();

        if ($pending) {
            $this->addError('scope', 'You already have a pending deletion request. Please wait for review.');
            return null;
        }

        DB::transaction(function () use ($user): void {
            AccountDeletionRequest::create([
                'user_id'   => $user->id,
                'tenant_id' => $user->tenant_id,
                'scope'     => $this->scope,
                'status'    => AccountDeletionRequest::STATUS_PENDING,
                'reason'    => $this->reason ?: null,
            ]);
        });

        // Refresh the superadmin deletion-requests badge so reviewers see
        // the new count on their next page load.
        app(SuperadminNotificationService::class)->flush();

        /*
         * FIXED: business owners land on the dedicated pending-review page,
         * not the public tourist profile. That page is what
         * BlockIfDeletionPending middleware targets and shows the request
         * status plus a Cancel button.
         */
        return redirect()
            ->route('tenant.account.deletion-pending')
            ->with('message', 'Your deletion request has been submitted. A superadmin will review it shortly.');
    }

    protected function executeTouristDeletion(User $user)
    {
        try {
            /** @var AccountDeletionService $service */
            $service = app(AccountDeletionService::class);
            $service->deleteTouristAccount($user);
        } catch (\RuntimeException $e) {
            $this->addError('confirmation', $e->getMessage());
            return null;
        } catch (\Throwable $e) {
            Log::error('Tourist deletion failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            $this->addError('confirmation', 'Could not delete your account. Please try again or contact support.');
            return null;
        }

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('home')
            ->with('message', "Your account has been deleted. We're sorry to see you go.");
    }

    public function cancelRequest(): void
    {
        $user = $this->viewer;
        abort_unless($user, 403);

        DB::transaction(function () use ($user): void {
            $request = AccountDeletionRequest::query()
                ->where('user_id', $user->id)
                ->where('status', AccountDeletionRequest::STATUS_PENDING)
                ->lockForUpdate()
                ->latest()
                ->first();

            if (! $request) {
                return;
            }

            $request->update([
                'status'       => AccountDeletionRequest::STATUS_CANCELLED,
                'reviewed_at'  => now(),
                'review_notes' => 'Cancelled by user.',
            ]);
        });

        app(SuperadminNotificationService::class)->flush();

        unset($this->pendingRequest);

        $this->dispatch('notify', type: 'success', message: 'Deletion request cancelled.');
    }
};
?>

<div class="py-10 sm:py-14 px-4 sm:px-6">
    <div class="mx-auto w-full max-w-2xl space-y-6">

        
        <a href="<?php echo e(route('profile')); ?>" wire:navigate
           class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400
                  transition-colors rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Profile
        </a>

        
        <div class="pb-6 border-b border-gray-200 dark:border-gray-800">
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-rose-500"></span>
                <span class="text-[10px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">Danger zone</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                Delete your account
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                This action is permanent and cannot be undone.
            </p>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->pendingRequest): ?>
            
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-amber-200/80 dark:border-amber-500/30 shadow-sm overflow-hidden">

                <div class="px-5 sm:px-6 pt-6 pb-5 border-b border-amber-100 dark:border-amber-500/20">
                    <div class="flex items-start gap-3">
                        <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                                Pending review
                            </p>
                            <h2 class="mt-0.5 text-lg font-bold text-gray-900 dark:text-white leading-tight">
                                Deletion request pending
                            </h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Requested <?php echo e($this->pendingRequest->created_at->format('M j, Y \a\t g:i A')); ?>

                            </p>
                        </div>
                    </div>
                </div>

                <div class="px-5 sm:px-6 py-5 space-y-4">
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400 shrink-0">Scope</dt>
                            <dd class="font-medium text-gray-900 dark:text-white text-right">
                                <?php echo e($this->pendingRequest->scopeLabel()); ?>

                            </dd>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->pendingRequest->tenant): ?>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-gray-500 dark:text-gray-400 shrink-0">Business</dt>
                                <dd class="font-medium text-gray-900 dark:text-white text-right truncate">
                                    <?php echo e($this->pendingRequest->tenant->name); ?>

                                </dd>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </dl>

                    <div class="rounded-xl border border-blue-200/70 dark:border-blue-500/30 bg-blue-50/60 dark:bg-blue-500/[0.06] px-3.5 py-3 text-xs text-blue-800 dark:text-blue-300 flex items-start gap-2.5">
                        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="leading-relaxed">
                            You can cancel below — your account unlocks immediately and nothing gets deleted.
                        </span>
                    </div>
                </div>

                <div class="px-5 sm:px-6 pb-6 flex flex-col sm:flex-row gap-2.5">
                    <a href="<?php echo e(route('tenant.account.deletion-pending')); ?>" wire:navigate
                       class="flex-1 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                              transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <span>View pending page</span>
                    </a>
                    <button type="button"
                            wire:click="cancelRequest"
                            wire:confirm="Cancel your pending deletion request?"
                            wire:loading.attr="disabled"
                            wire:target="cancelRequest"
                            class="flex-1 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-amber-300 dark:border-amber-500/40 bg-amber-50 dark:bg-amber-500/10 text-amber-900 dark:text-amber-200 text-sm font-semibold
                                   transition-all duration-200 active:scale-95 hover:bg-amber-100 dark:hover:bg-amber-500/20
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="cancelRequest">Cancel request</span>
                        <span wire:loading wire:target="cancelRequest" class="inline-flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Cancelling…
                        </span>
                    </button>
                </div>
            </div>
        <?php else: ?>

            
            <div class="bg-rose-50 dark:bg-rose-500/[0.06] rounded-2xl border border-rose-200/80 dark:border-rose-500/30 shadow-sm p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-rose-100 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-sm font-bold text-rose-900 dark:text-rose-200">
                            <?php echo e($this->isBusinessOwner ? 'What gets deleted' : 'This will permanently delete'); ?>

                        </h2>
                        <ul class="mt-2 space-y-1 text-xs text-rose-800 dark:text-rose-300 list-disc list-inside">
                            <li>Your profile, avatar, and contact details</li>
                            <li>Your personal bookings and history</li>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->isBusinessOwner): ?>
                                <li>Your business listing, properties, and services</li>
                                <li>Your KYB documents and application history</li>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <li>All active sessions across your devices</li>
                        </ul>
                        <p class="mt-2.5 text-[11px] font-semibold text-rose-700 dark:text-rose-400">
                            This cannot be undone. There is no recovery.
                        </p>
                    </div>
                </div>
            </div>

            <form wire:submit="submit" class="space-y-6">

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->isBusinessOwner): ?>
                    
                    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="w-5 h-px bg-primary-600"></span>
                            <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                What would you like to delete?
                            </h2>
                        </div>

                        <div class="space-y-2.5">
                            <label class="relative cursor-pointer block">
                                <input type="radio" wire:model.live="scope" value="<?php echo e(AccountDeletionRequest::SCOPE_BUSINESS_ONLY); ?>" class="peer sr-only">
                                <div class="rounded-xl border-2 border-gray-200 dark:border-gray-700 p-4 transition-all duration-200
                                            hover:border-gray-300 dark:hover:border-gray-600
                                            peer-checked:border-blue-500 peer-checked:bg-blue-50/60 dark:peer-checked:bg-blue-500/10
                                            peer-focus-visible:ring-2 peer-focus-visible:ring-blue-500/50">
                                    <div class="flex items-start gap-3">
                                        <div class="shrink-0 w-9 h-9 rounded-lg bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-400 flex items-center justify-center">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                            </svg>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Business account only</p>
                                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                                Removes your business listing, properties, services, and admin access. Your personal tourist account stays active.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </label>

                            <label class="relative cursor-pointer block">
                                <input type="radio" wire:model.live="scope" value="<?php echo e(AccountDeletionRequest::SCOPE_BOTH); ?>" class="peer sr-only">
                                <div class="rounded-xl border-2 border-gray-200 dark:border-gray-700 p-4 transition-all duration-200
                                            hover:border-gray-300 dark:hover:border-gray-600
                                            peer-checked:border-rose-500 peer-checked:bg-rose-50/60 dark:peer-checked:bg-rose-500/10
                                            peer-focus-visible:ring-2 peer-focus-visible:ring-rose-500/50">
                                    <div class="flex items-start gap-3">
                                        <div class="shrink-0 w-9 h-9 rounded-lg bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-400 flex items-center justify-center">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Everything — business + tourist</p>
                                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                                Your business is deleted first, then your entire account. You'll lose access to everything.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </label>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['scope'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div class="flex items-start gap-2.5 rounded-xl border border-blue-200/70 dark:border-blue-500/30 bg-blue-50/60 dark:bg-blue-500/[0.06] px-3 py-2.5 text-[11px] text-blue-800 dark:text-blue-300">
                            <svg class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="leading-relaxed">
                                As a business owner, your deletion must be reviewed and approved by a superadmin. You'll be redirected to a status page while your request is pending.
                            </span>
                        </div>
                    </div>

                    
                    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="w-5 h-px bg-primary-600"></span>
                            <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Reason <span class="text-gray-400 dark:text-gray-500 font-medium normal-case tracking-normal">(optional)</span>
                            </h2>
                        </div>

                        <textarea id="reason"
                                  wire:model="reason"
                                  rows="3"
                                  maxlength="500"
                                  placeholder="Let us know why you're leaving…"
                                  class="block w-full px-4 py-3 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition
                                         focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none
                                         placeholder:text-gray-400 dark:placeholder-gray-500"></textarea>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['reason'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-4">
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-rose-500"></span>
                        <h2 class="text-[10px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">
                            Confirm
                        </h2>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Your password <span class="text-rose-500">*</span>
                        </label>
                        <input id="password"
                               type="password"
                               wire:model="password"
                               autocomplete="current-password"
                               placeholder="Enter your password"
                               class="input <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-400/60 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div>
                        <label for="confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Type
                            <code class="font-mono font-bold text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-500/10 px-1.5 py-0.5 rounded">DELETE</code>
                            to confirm <span class="text-rose-500">*</span>
                        </label>
                        <input id="confirmation"
                               type="text"
                               wire:model="confirmation"
                               autocomplete="off"
                               placeholder="DELETE"
                               class="input font-mono <?php $__errorArgs = ['confirmation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-400/60 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['confirmation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3 pt-2">
                    <a href="<?php echo e(route('profile')); ?>" wire:navigate
                       class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                              transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <span>Cancel</span>
                    </a>

                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="submit"
                            class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold shadow-sm
                                   transition-all duration-200 active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="submit">
                            <?php echo e($this->isBusinessOwner ? 'Submit deletion request' : 'Delete my account'); ?>

                        </span>
                        <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Processing…
                        </span>
                    </button>
                </div>
            </form>

        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views\public\pages\⚡delete-account.blade.php ENDPATH**/ ?>