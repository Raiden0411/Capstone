{{-- resources/views/public/pages/⚡delete-account.blade.php --}}
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

    public function mount(): void
    {
        abort_unless(Auth::check(), 403);
    }

    public function hydrate(): void
    {
        abort_unless(Auth::check(), 403, 'Your session has expired. Please sign in again.');
    }

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

        app(SuperadminNotificationService::class)->flush();

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

<div class="py-10 sm:py-14 px-4 sm:px-6" x-data="revealOnScroll">
    <div class="mx-auto w-full max-w-2xl space-y-6">

        {{-- ═══ Back link ═══ --}}
        <a href="{{ route('profile') }}" wire:navigate
           class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400
                  transition-colors rounded px-1 -mx-1 py-1
                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Profile
        </a>

        {{-- ═══ Page header ═══ --}}
        <div data-reveal class="pb-6 border-b border-gray-200 dark:border-gray-800">
            <p class="mb-2 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-rose-600 dark:text-rose-400">
                <span class="h-px w-4 bg-rose-500" aria-hidden="true"></span>
                Danger Zone
            </p>
            <h1 class="font-display text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                Delete your account
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-2">
                This action is permanent and cannot be undone.
            </p>
        </div>

        @if($this->pendingRequest)
            {{-- ═══ PENDING STATE ═══ --}}
            <div data-reveal style="--reveal-delay: 80ms"
                 class="bg-white dark:bg-gray-800/90 rounded-2xl border border-amber-200/80 dark:border-amber-500/30 shadow-sm overflow-hidden">

                <div class="px-5 sm:px-6 pt-6 pb-5 border-b border-amber-100 dark:border-amber-500/20">
                    <div class="flex items-start gap-3">
                        <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-amber-600 dark:text-amber-400">
                                Pending review
                            </p>
                            <h2 class="mt-0.5 text-lg font-bold tracking-tight text-gray-900 dark:text-white leading-tight">
                                Deletion request pending
                            </h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 tabular-nums">
                                Requested {{ $this->pendingRequest->created_at->format('M j, Y \a\t g:i A') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="px-5 sm:px-6 py-5 space-y-4">
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400 shrink-0">Scope</dt>
                            <dd class="font-medium text-gray-900 dark:text-white text-right">
                                {{ $this->pendingRequest->scopeLabel() }}
                            </dd>
                        </div>
                        @if($this->pendingRequest->tenant)
                            <div class="flex items-start justify-between gap-4">
                                <dt class="text-gray-500 dark:text-gray-400 shrink-0">Business</dt>
                                <dd class="font-medium text-gray-900 dark:text-white text-right truncate">
                                    {{ $this->pendingRequest->tenant->name }}
                                </dd>
                            </div>
                        @endif
                    </dl>

                    <div class="rounded-xl border border-blue-200/70 dark:border-blue-500/30 bg-blue-50/60 dark:bg-blue-500/[0.06] px-3.5 py-3 text-xs text-blue-800 dark:text-blue-300 flex items-start gap-2.5">
                        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="leading-relaxed">
                            Nothing has been deleted yet. Cancel below — your account unlocks immediately.
                        </span>
                    </div>
                </div>

                <div class="px-5 sm:px-6 pb-6 flex flex-col sm:flex-row gap-2.5">
                    <a href="{{ route('tenant.account.deletion-pending') }}" wire:navigate
                       class="flex-1 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                              transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        View pending page
                    </a>

                    {{--
                        Two-click arm pattern instead of wire:confirm.
                        Native browser dialogs are visually jarring at the
                        most anxiety-inducing moment on the site. First
                        click arms the button (label changes, color shifts),
                        second click within 4s fires the request; if the
                        user does nothing, the button resets.
                    --}}
                    <button type="button"
                            x-data="{
                                armed: false,
                                _timer: null,
                                arm() {
                                    this.armed = true;
                                    if (this._timer) clearTimeout(this._timer);
                                    this._timer = setTimeout(() => { this.armed = false; this._timer = null; }, 4000);
                                },
                                fire() {
                                    if (this._timer) { clearTimeout(this._timer); this._timer = null; }
                                    this.armed = false;
                                    $wire.cancelRequest();
                                },
                                destroy() { if (this._timer) clearTimeout(this._timer); }
                            }"
                            @click="armed ? fire() : arm()"
                            wire:loading.attr="disabled"
                            wire:target="cancelRequest"
                            :class="armed
                                ? 'bg-amber-500 hover:bg-amber-600 text-white border-amber-500 dark:border-amber-500'
                                : 'bg-amber-50 dark:bg-amber-500/10 text-amber-900 dark:text-amber-200 border-amber-300 dark:border-amber-500/40 hover:bg-amber-100 dark:hover:bg-amber-500/20'"
                            class="flex-1 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border text-sm font-semibold
                                   transition-all duration-200 active:scale-95
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="cancelRequest"
                              x-show="!armed"
                              class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Cancel request
                        </span>
                        <span x-show="armed" x-cloak class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                            Click again to confirm
                        </span>
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
        @else

            {{-- ═══ WARNING BOX ═══ --}}
            <div data-reveal style="--reveal-delay: 80ms"
                 class="bg-rose-50 dark:bg-rose-500/[0.06] rounded-2xl border border-rose-200/80 dark:border-rose-500/30 shadow-sm p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-rose-100 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-sm font-bold tracking-tight text-rose-900 dark:text-rose-200">
                            {{ $this->isBusinessOwner ? 'What gets deleted' : 'This will permanently delete' }}
                        </h2>

                        {{-- Which account is being deleted — removes
                             ambiguity if the user has multiple sessions. --}}
                        <p class="mt-2 text-xs text-rose-800 dark:text-rose-300">
                            Account: <span class="font-semibold">{{ $this->viewer?->email }}</span>
                        </p>

                        <ul class="mt-3 space-y-1.5 text-xs text-rose-800 dark:text-rose-300">
                            <li class="flex items-start gap-2">
                                <svg class="w-3.5 h-3.5 shrink-0 mt-0.5 text-rose-500 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4"/>
                                </svg>
                                <span>Your profile, avatar, and contact details</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <svg class="w-3.5 h-3.5 shrink-0 mt-0.5 text-rose-500 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4"/>
                                </svg>
                                <span>Your personal bookings and history</span>
                            </li>
                            @if($this->isBusinessOwner)
                                <li class="flex items-start gap-2">
                                    <svg class="w-3.5 h-3.5 shrink-0 mt-0.5 text-rose-500 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4"/>
                                    </svg>
                                    <span>Your business listing, properties, and services</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <svg class="w-3.5 h-3.5 shrink-0 mt-0.5 text-rose-500 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4"/>
                                    </svg>
                                    <span>Your KYB documents and application history</span>
                                </li>
                            @endif
                            <li class="flex items-start gap-2">
                                <svg class="w-3.5 h-3.5 shrink-0 mt-0.5 text-rose-500 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4"/>
                                </svg>
                                <span>All active sessions across your devices</span>
                            </li>
                        </ul>

                        <p class="mt-3 text-[11px] font-semibold text-rose-700 dark:text-rose-400">
                            This cannot be undone. There is no recovery.
                        </p>
                    </div>
                </div>
            </div>

            <form wire:submit="submit" class="space-y-6">

                @if($this->isBusinessOwner)
                    {{-- ═══ SCOPE ═══ --}}
                    <div data-reveal style="--reveal-delay: 140ms"
                         class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-4">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                            What would you like to delete?
                        </p>

                        <div class="space-y-2.5">
                            <label class="relative cursor-pointer block
                                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                                <input type="radio" wire:model="scope" value="{{ AccountDeletionRequest::SCOPE_BUSINESS_ONLY }}" class="peer sr-only">
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

                            <label class="relative cursor-pointer block
                                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                                <input type="radio" wire:model="scope" value="{{ AccountDeletionRequest::SCOPE_BOTH }}" class="peer sr-only">
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

                        @error('scope') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror

                        <div class="flex items-start gap-2.5 rounded-xl border border-blue-200/70 dark:border-blue-500/30 bg-blue-50/60 dark:bg-blue-500/[0.06] px-3 py-2.5 text-[11px] text-blue-800 dark:text-blue-300">
                            <svg class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="leading-relaxed">
                                As a business owner, your deletion must be reviewed and approved by a superadmin. You'll be redirected to a status page while your request is pending.
                            </span>
                        </div>
                    </div>

                    {{-- ═══ REASON ═══ --}}
                    <div data-reveal style="--reveal-delay: 200ms"
                         class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-4">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                            Reason <span class="text-gray-400 dark:text-gray-500 font-medium normal-case tracking-normal">— optional</span>
                        </p>

                        <textarea id="reason"
                                  wire:model="reason"
                                  rows="3"
                                  maxlength="500"
                                  placeholder="Let us know why you're leaving…"
                                  class="block w-full px-4 py-3 text-base sm:text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition
                                         focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none
                                         placeholder:text-gray-400 dark:placeholder-gray-500"></textarea>
                        @error('reason') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                @endif

                {{-- ═══ CONFIRMATION ═══ --}}
                <div data-reveal style="--reveal-delay: {{ $this->isBusinessOwner ? '260ms' : '140ms' }}"
                     x-data="{ caps: false, showPassword: false }"
                     class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">

                    <p class="text-[10px] font-bold uppercase tracking-widest text-rose-600 dark:text-rose-400">
                        Confirm
                    </p>

                    {{-- Password --}}
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Your password <span class="text-rose-500" aria-hidden="true">*</span>
                        </label>
                        <div class="relative">
                            <input id="password"
                                   :type="showPassword ? 'text' : 'password'"
                                   wire:model="password"
                                   autocomplete="current-password"
                                   placeholder="Enter your password"
                                   @keyup="caps = $event.getModifierState && $event.getModifierState('CapsLock')"
                                   @keydown="caps = $event.getModifierState && $event.getModifierState('CapsLock')"
                                   @blur="caps = false"
                                   class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 pl-4 pr-11 text-base sm:text-sm text-gray-900 dark:text-white placeholder-gray-400
                                          focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-rose-500/50 focus:border-rose-500 transition
                                          @error('password') border-rose-400/60 @enderror">
                            <button type="button"
                                    @click="showPassword = !showPassword"
                                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                    :aria-pressed="showPassword ? 'true' : 'false'"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800
                                           transition-colors
                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                                <svg x-show="!showPassword" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg x-show="showPassword" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                        <p x-cloak x-show="caps" role="status" aria-live="polite"
                           class="mt-1.5 inline-flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                            Caps Lock is on
                        </p>
                        @error('password') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    {{-- Type DELETE --}}
                    <div x-data="{ matches: false }">
                        <label for="confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Type
                            <code class="font-mono font-bold text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-500/10 px-1.5 py-0.5 rounded">DELETE</code>
                            to confirm <span class="text-rose-500" aria-hidden="true">*</span>
                        </label>
                        <div class="relative">
                            <input id="confirmation"
                                   type="text"
                                   wire:model.live.debounce.200ms="confirmation"
                                   x-on:input="matches = $event.target.value === 'DELETE'"
                                   x-init="matches = $wire.confirmation === 'DELETE'"
                                   autocomplete="off"
                                   autocapitalize="characters"
                                   autocorrect="off"
                                   spellcheck="false"
                                   placeholder="DELETE"
                                   class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-3 pl-4 pr-11 text-base sm:text-sm font-mono tracking-widest text-gray-900 dark:text-white placeholder-gray-400 placeholder:tracking-normal
                                          focus:bg-white dark:focus:bg-gray-900 transition
                                          @error('confirmation') border-rose-400/60 @enderror"
                                   :class="matches ? 'border-emerald-500 focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500' : 'focus:ring-2 focus:ring-rose-500/50 focus:border-rose-500'">
                            <span x-show="matches" x-cloak
                                  class="absolute right-3 top-1/2 -translate-y-1/2 inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400"
                                  aria-hidden="true">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                </svg>
                            </span>
                        </div>
                        @error('confirmation') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- ═══ ACTIONS ═══ --}}
                <div data-reveal style="--reveal-delay: {{ $this->isBusinessOwner ? '320ms' : '200ms' }}"
                     class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3 pt-2">
                    <a href="{{ route('profile') }}" wire:navigate
                       class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                              transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        Cancel
                    </a>

                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="submit"
                            class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold shadow-sm
                                   transition-all duration-200 active:scale-95
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="submit">
                            {{ $this->isBusinessOwner ? 'Submit deletion request' : 'Delete my account' }}
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

        @endif
    </div>
</div>