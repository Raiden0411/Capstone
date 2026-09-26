{{-- resources/views/superadmin/pages/deletion-request/⚡view-deletion-requests.blade.php --}}
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

    {{-- ═══ Page header — CARD, matching analytics / dashboard / business-applications ═══ --}}
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">

            {{-- Left: brand block --}}
            <div class="flex items-center gap-4 min-w-0">
                <div class="hidden sm:flex w-12 h-12 rounded-xl bg-rose-600 text-white items-center justify-center shrink-0 shadow-md shadow-rose-500/30">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Platform</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="font-display text-2xl sm:text-3xl font-semibold text-gray-900 dark:text-white tracking-tight leading-tight">
                            Account <em class="italic text-primary-600 dark:text-primary-400">Deletion Requests</em>
                        </h1>
                        @if($this->pendingCount > 0)
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                                         bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300
                                         border border-amber-200 dark:border-amber-500/30 tabular-nums">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse motion-reduce:animate-none"></span>
                                {{ $this->pendingCount }} pending
                            </span>
                        @endif
                    </div>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Review and act on pending account deletion requests. Approving removes the account permanently.
                    </p>
                </div>
            </div>

            {{-- Right: actions --}}
            <div class="flex flex-wrap items-center gap-2 shrink-0 no-print">
                <button type="button"
                        wire:click="$refresh"
                        wire:loading.attr="disabled"
                        wire:target="$refresh"
                        aria-label="Refresh request list"
                        class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                               transition-all duration-200 active:scale-95 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent] hover:bg-gray-50 dark:hover:bg-gray-700
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                               disabled:opacity-60 disabled:cursor-not-allowed">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M4 9a9 9 0 0014.5 4.5M20 20v-5h-5M20 15a9 9 0 00-14.5-4.5"/>
                    </svg>
                    <span class="hidden sm:inline">Refresh</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ═══ Filter card ═══ --}}
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
                       class="input w-full text-base sm:text-sm"
                       style="padding-left: 2.5rem;">
            </div>

            {{-- Status pills — canonical chip tier --}}
            <div class="flex flex-wrap gap-2 items-center">
                @foreach([
                    \App\Models\AccountDeletionRequest::STATUS_PENDING  => 'Pending',
                    \App\Models\AccountDeletionRequest::STATUS_APPROVED => 'Approved',
                    \App\Models\AccountDeletionRequest::STATUS_REJECTED => 'Rejected',
                    'all'                                               => 'All',
                ] as $value => $label)
                    @php $isActive = $statusFilter === $value; @endphp
                    <button type="button"
                            wire:click="$set('statusFilter', '{{ $value }}')"
                            wire:key="status-pill-{{ $value }}"
                            aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                            class="inline-flex items-center gap-2 h-11 sm:h-9 pl-3.5 pr-1.5 rounded-full text-xs font-semibold uppercase tracking-wide border
                                   transition-all duration-200 active:scale-95 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent] shrink-0
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                   {{ $isActive
                                      ? 'bg-primary-600 border-primary-600 text-white shadow-sm'
                                      : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400' }}">
                        <span>{{ $label }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ═══ Request list ═══ --}}
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm overflow-hidden">
        @if($this->requests->isEmpty())
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
        @else
            <div wire:loading.class="opacity-40 pointer-events-none"
                 wire:target="search,statusFilter,gotoPage,nextPage,previousPage"
                 class="divide-y divide-gray-100 dark:divide-gray-700/60 transition-opacity duration-200">
                @foreach($this->requests as $request)
                    @php
                        $statusClasses = match ($request->status) {
                            \App\Models\AccountDeletionRequest::STATUS_PENDING  => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300 border-amber-200 dark:border-amber-500/30',
                            \App\Models\AccountDeletionRequest::STATUS_APPROVED => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30',
                            \App\Models\AccountDeletionRequest::STATUS_REJECTED => 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300 border-rose-200 dark:border-rose-500/30',
                            default                                             => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-600',
                        };
                    @endphp
                    <a href="{{ route('superadmin.deletion-requests.show', ['request' => $request->id]) }}"
                       wire:navigate
                       wire:key="adr-{{ $request->id }}"
                       aria-label="View deletion request for {{ $request->user?->name ?? 'unknown user' }}"
                       class="block px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/40
                              transition-all duration-200 active:scale-[0.995] [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-inset">

                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                        {{ $request->user?->name ?? 'Unknown user' }}
                                    </p>
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider border {{ $statusClasses }}">
                                        <span class="w-1 h-1 rounded-full bg-current"></span>
                                        {{ $request->statusLabel() }}
                                    </span>
                                </div>

                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 truncate">
                                    {{ $request->user?->email ?? '—' }}
                                    @if($request->tenant)
                                        · <span class="font-medium text-gray-700 dark:text-gray-300">{{ $request->tenant->name }}</span>
                                    @endif
                                </p>

                                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                                    Scope: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $request->scopeLabel() }}</span>
                                    · Requested <span class="tabular-nums">{{ $request->created_at->diffForHumans() }}</span>
                                </p>
                            </div>

                            <svg class="w-4 h-4 shrink-0 text-gray-400 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </a>
                @endforeach
            </div>

            @if($this->requests->hasPages())
                <div class="px-5 py-4 border-t border-gray-200/80 dark:border-gray-700/80 bg-gray-50/50 dark:bg-gray-900/30">
                    {{ $this->requests->links() }}
                </div>
            @endif
        @endif
    </div>

</div>