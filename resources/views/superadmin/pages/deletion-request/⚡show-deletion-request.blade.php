{{-- resources/views/superadmin/pages/deletion-request/⚡show-deletion-request.blade.php --}}
<?php

use App\Models\AccountDeletionRequest;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('superadmin.layouts.app')]
#[Title('Deletion Request')]
class extends Component
{
    public int $requestId = 0;

    public function mount(int $request): void
    {
        abort_unless(
            Auth::check() && Auth::user()->hasRole('super-admin'),
            403,
            'Super Admin access only.'
        );

        $this->requestId = $request;
    }

    public function hydrate(): void
    {
        abort_unless(
            Auth::check() && Auth::user()->hasRole('super-admin'),
            403
        );
    }

    #[Computed]
    public function deletionRequest(): ?AccountDeletionRequest
    {
        return AccountDeletionRequest::query()
            ->with(['user', 'tenant', 'reviewer'])
            ->find($this->requestId);
    }
};
?>

@php
    $request = $this->deletionRequest;
@endphp

@if(!$request)
    <div class="p-4 sm:p-6 lg:p-8 max-w-3xl mx-auto">
        <div class="rounded-2xl border border-rose-200 dark:border-rose-500/30 bg-rose-50 dark:bg-rose-500/10 p-6 text-center">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-rose-100 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-rose-900 dark:text-rose-200">This deletion request no longer exists.</p>
            <a href="{{ route('superadmin.deletion-requests.index') }}" wire:navigate
               class="mt-4 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-rose-300 dark:border-rose-500/40 bg-white dark:bg-gray-800 text-rose-700 dark:text-rose-300 text-sm font-semibold
                      transition-all duration-200 active:scale-95 hover:bg-rose-50 dark:hover:bg-rose-500/10
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                <span>Back to all requests</span>
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </div>
@else
    <div class="p-4 sm:p-6 lg:p-8 max-w-3xl mx-auto space-y-6">

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

        {{-- ═══ Validation errors ═══ --}}
        @if($errors->any())
            <div x-data="{ show: true }"
                 x-init="setTimeout(() => show = false, 6000)"
                 :class="show ? '' : 'hidden'"
                 class="flex items-start justify-between gap-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl">
                <div class="flex items-start gap-2.5 min-w-0">
                    <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div class="text-xs sm:text-sm text-rose-800 dark:text-rose-300 min-w-0">
                        <p class="font-semibold mb-1">Please fix the following:</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($errors->all() as $error)
                                <li wire:key="err-{{ $loop->index }}">{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <button type="button" @click="show = false"
                        class="inline-flex items-center justify-center h-7 w-7 shrink-0 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                               transition-all duration-200 active:scale-95
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                        aria-label="Dismiss">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        {{-- ═══ Back link ═══ --}}
        <a href="{{ route('superadmin.deletion-requests.index') }}" wire:navigate
           class="inline-flex items-center gap-2 text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400
                  transition-all duration-200 active:scale-95
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            <span>All requests</span>
        </a>

        {{-- ═══ Page header ═══ --}}
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">
                    Deletion Request #{{ $request->id }}
                </span>
            </div>
            <div class="flex items-start justify-between flex-wrap gap-3">
                <h1 class="font-display text-3xl md:text-4xl font-semibold text-gray-900 dark:text-white">
                    {{ $request->user?->name ?? 'Unknown user' }}
                </h1>

                @php
                    $statusClasses = match ($request->status) {
                        \App\Models\AccountDeletionRequest::STATUS_PENDING  => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300 border-amber-200 dark:border-amber-500/30',
                        \App\Models\AccountDeletionRequest::STATUS_APPROVED => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30',
                        \App\Models\AccountDeletionRequest::STATUS_REJECTED => 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300 border-rose-200 dark:border-rose-500/30',
                        default                                             => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-600',
                    };
                @endphp
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider border {{ $statusClasses }}">
                    <span class="w-1 h-1 rounded-full bg-current"></span>
                    {{ $request->statusLabel() }}
                </span>
            </div>
        </div>

        {{-- ═══ Summary card ═══ --}}
        <div class="rounded-2xl border border-gray-200/80 dark:border-gray-700/80 bg-white dark:bg-gray-800/90 p-5 sm:p-6 space-y-5 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Request Details</span>
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                <div class="min-w-0">
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">User email</dt>
                    <dd class="text-gray-900 dark:text-white break-all">{{ $request->user?->email ?? '—' }}</dd>
                </div>

                <div class="min-w-0">
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">User ID</dt>
                    <dd class="font-mono text-gray-900 dark:text-white">#{{ $request->user_id }}</dd>
                </div>

                <div class="min-w-0">
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">Business</dt>
                    <dd class="text-gray-900 dark:text-white">{{ $request->tenant?->name ?? '—' }}</dd>
                </div>

                <div class="min-w-0">
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">Scope</dt>
                    <dd class="text-gray-900 dark:text-white font-medium">{{ $request->scopeLabel() }}</dd>
                </div>

                <div class="min-w-0">
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">Requested</dt>
                    <dd class="text-gray-900 dark:text-white tabular-nums">
                        {{ $request->created_at->format('M j, Y \a\t g:i A') }}
                        <span class="text-xs text-gray-500 dark:text-gray-400 font-normal">({{ $request->created_at->diffForHumans() }})</span>
                    </dd>
                </div>

                @if($request->reviewed_at)
                    <div class="min-w-0">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">Reviewed</dt>
                        <dd class="text-gray-900 dark:text-white tabular-nums">
                            {{ $request->reviewed_at->format('M j, Y \a\t g:i A') }}
                            @if($request->reviewer)
                                <span class="text-xs text-gray-500 dark:text-gray-400 font-normal">by {{ $request->reviewer->name }}</span>
                            @endif
                        </dd>
                    </div>
                @endif
            </dl>

            @if($request->reason)
                <div class="pt-5 mt-1 border-t border-gray-100 dark:border-gray-700/60">
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">User's reason</dt>
                    <p class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-line leading-relaxed">
                        {{ $request->reason }}
                    </p>
                </div>
            @endif

            @if($request->review_notes)
                <div class="pt-5 mt-1 border-t border-gray-100 dark:border-gray-700/60">
                    <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">Review notes</dt>
                    <p class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-line leading-relaxed">
                        {{ $request->review_notes }}
                    </p>
                </div>
            @endif
        </div>

        {{-- ═══ Action card ═══
             Plain form POST with `formaction` override + `onclick="return confirm(...)"`.
             Handoff fact #19: prefer this over wire:confirm for destructive flows. --}}
        @if($request->isPending())
            <div class="rounded-2xl border-2 border-rose-200 dark:border-rose-500/30 bg-rose-50 dark:bg-rose-500/[0.06] p-5 sm:p-6 space-y-5">

                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-rose-900 dark:text-rose-200">
                            This action is irreversible
                        </h2>
                        <p class="mt-1 text-xs sm:text-sm text-rose-800 dark:text-rose-300 leading-relaxed">
                            @if($request->isBoth())
                                Approving will permanently delete the tenant, all its data, and the entire user account.
                            @else
                                Approving will permanently delete the tenant and all its data. The user account will remain as a tourist.
                            @endif
                        </p>
                    </div>
                </div>

                <form method="POST"
                      action="{{ route('superadmin.deletion-requests.reject', ['deletionRequest' => $request->id]) }}"
                      class="space-y-5">
                    @csrf

                    <div>
                        <label for="review_notes" class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1">
                            Review notes
                            <span class="text-[10px] font-normal text-gray-500 dark:text-gray-400">(required to reject; optional to approve)</span>
                        </label>
                        <textarea id="review_notes"
                                  name="review_notes"
                                  rows="3"
                                  maxlength="1000"
                                  placeholder="Optional context…"
                                  class="input w-full resize-y placeholder:text-gray-400 dark:placeholder:text-gray-500 focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:border-primary-500">{{ old('review_notes') }}</textarea>
                        @error('review_notes')
                            <p class="mt-1 text-xs text-rose-500 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col sm:flex-row gap-2.5">
                        {{-- Reject — uses the form's default action --}}
                        <button type="submit"
                                class="flex-1 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                       transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            <span>Reject request</span>
                        </button>

                        {{-- Approve — overrides form action + skips client-side validation --}}
                        <button type="submit"
                                formaction="{{ route('superadmin.deletion-requests.approve', ['deletionRequest' => $request->id]) }}"
                                formnovalidate
                                onclick="return confirm('Approve this deletion? This will permanently delete the account(s) and cannot be undone.')"
                                class="flex-1 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold shadow-sm
                                       transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            <span>Approve &amp; delete</span>
                        </button>
                    </div>
                </form>
            </div>
        @else
            <div class="rounded-2xl border border-gray-200/80 dark:border-gray-700/80 bg-white dark:bg-gray-800/90 p-5 shadow-sm">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    This request has been <strong class="text-gray-700 dark:text-gray-200">{{ $request->status }}</strong>. No further action is possible.
                </p>
            </div>
        @endif

    </div>
@endif