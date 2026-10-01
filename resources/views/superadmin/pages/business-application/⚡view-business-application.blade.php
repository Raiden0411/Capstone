{{-- resources/views/superadmin/pages/business-application/⚡view-business-application.blade.php --}}
<?php

use App\Models\BusinessApplication;
use App\Models\BusinessMembership;
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

    /**
     * Per-accent class strings for the KPI strip.
     *
     * Complete class strings ONLY — no runtime concatenation. Tailwind v4's
     * JIT scans source text; `'ring-' . $x . '-500/50'` produces the
     * fragments `ring-` and `-500/50` but never the actual class, so the
     * compiled CSS omits it entirely. Return complete strings from a
     * lookup and Tailwind finds them.
     *
     * @return array<string, array{ring: string, active: string, hover: string}>
     */
    #[Computed]
    public function accentClasses(): array
    {
        return [
            'primary' => [
                'ring'   => 'focus-visible:ring-primary-500/50',
                'active' => 'border-primary-500/60 ring-2 ring-primary-500/20',
                'hover'  => 'border-gray-200/80 dark:border-gray-700/80 hover:border-primary-500/30',
            ],
            'amber' => [
                'ring'   => 'focus-visible:ring-amber-500/50',
                'active' => 'border-amber-500/60 ring-2 ring-amber-500/20',
                'hover'  => 'border-gray-200/80 dark:border-gray-700/80 hover:border-amber-500/30',
            ],
            'emerald' => [
                'ring'   => 'focus-visible:ring-emerald-500/50',
                'active' => 'border-emerald-500/60 ring-2 ring-emerald-500/20',
                'hover'  => 'border-gray-200/80 dark:border-gray-700/80 hover:border-emerald-500/30',
            ],
            'rose' => [
                'ring'   => 'focus-visible:ring-rose-500/50',
                'active' => 'border-rose-500/60 ring-2 ring-rose-500/20',
                'hover'  => 'border-gray-200/80 dark:border-gray-700/80 hover:border-rose-500/30',
            ],
        ];
    }

    #[Computed]
    public function applications()
    {
        return BusinessApplication::query()
            ->with([
                'user:id,name,email,avatar',
                // Owner/admin memberships only — a user who is merely an
                // employee of some other business should NOT light up the
                // "Additional business" chip. Employees have no business
                // of their own to add another to.
                //
                // This is a plain HasMany; it does NOT go through Spatie,
                // so eager-loading it is safe under team-scoped roles.
                'user.businessMemberships' => fn ($q) => $q
                    ->whereIn('role', [
                        BusinessMembership::ROLE_OWNER,
                        BusinessMembership::ROLE_ADMIN,
                    ])
                    ->select('id', 'user_id', 'tenant_id', 'role'),
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
     * Uses the query builder — the result is a plain stdClass, not a Model
     * with phantom attributes. Matters if caching is ever added here:
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

<div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6">
    @php
        $stats   = $this->stats;
        $accents = $this->accentClasses;
    @endphp

    {{-- ═══ Page header ═══ --}}
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

    {{-- ═══ Flash: success ═══ --}}
    @if (session('message'))
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
                           transition-all duration-200 active:scale-95 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    {{-- ═══ Flash: error ═══ --}}
    @if (session('error'))
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
                           transition-all duration-200 active:scale-95 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    {{-- ═══ Compact KPI strip — clickable to filter ═══ --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @php
            $kpis = [
                ['value' => '',                'label' => 'Total',           'count' => $stats->total ?? 0,          'dot' => 'bg-primary-500',  'accent' => 'primary'],
                ['value' => 'awaiting_review', 'label' => 'Awaiting Review', 'count' => $stats->awaiting_review ?? 0, 'dot' => 'bg-amber-500',    'accent' => 'amber'],
                ['value' => 'approved',        'label' => 'Approved',        'count' => $stats->approved ?? 0,        'dot' => 'bg-emerald-500',  'accent' => 'emerald'],
                ['value' => 'rejected',        'label' => 'Rejected',        'count' => $stats->rejected ?? 0,        'dot' => 'bg-rose-500',     'accent' => 'rose'],
            ];
        @endphp

        @foreach($kpis as $kpi)
            @php
                $isActive = $status === $kpi['value'];
                $a = $accents[$kpi['accent']];
            @endphp
            <button type="button"
                    wire:click="filterBy('{{ $kpi['value'] }}')"
                    wire:key="kpi-{{ $kpi['value'] !== '' ? $kpi['value'] : 'all' }}"
                    aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                    class="text-left bg-white dark:bg-gray-800/90 rounded-xl border shadow-sm p-3.5
                           transition-all duration-200 active:scale-[0.98] [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                           focus-visible:outline-none {{ $a['ring'] }}
                           {{ $isActive ? $a['active'] : $a['hover'] }}">
                <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full {{ $kpi['dot'] }}"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</span>
                </div>
                <p class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white tabular-nums">{{ number_format($kpi['count']) }}</p>
            </button>
        @endforeach
    </div>

    {{-- ═══ Filter card ═══ --}}
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
                       class="input w-full text-base sm:text-sm"
                       style="padding-left: 2.5rem;">
            </div>

            <select wire:model.live="status"
                    aria-label="Filter by status"
                    class="input w-full sm:w-auto sm:min-w-[180px] text-base sm:text-sm">
                <option value="">All statuses</option>
                <option value="awaiting_review">Awaiting Review</option>
                @foreach ($this->statusLabels as $value => $label)
                    <option wire:key="status-opt-{{ $value }}" value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            @if ($this->hasActiveFilter)
                <button type="button" wire:click="clearFilters"
                        class="inline-flex items-center gap-1 h-11 sm:h-9 px-3.5 rounded-full text-xs font-semibold uppercase tracking-wide
                               border border-rose-300 dark:border-rose-500/40
                               bg-white dark:bg-gray-800 text-rose-700 dark:text-rose-300
                               transition-all duration-200 active:scale-95 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               hover:bg-rose-50 dark:hover:bg-rose-500/10
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Clear
                </button>
            @endif
        </div>
    </div>

    {{-- ═══ Card grid ═══ --}}
    @if ($this->applications->isEmpty())
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-12 text-center">
            <div class="flex flex-col items-center max-w-md mx-auto">
                <div class="p-3 rounded-2xl bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <p class="mt-4 text-base font-semibold text-gray-900 dark:text-white">
                    {{ $this->hasActiveFilter ? 'No applications match your filters' : 'No applications yet' }}
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->hasActiveFilter
                        ? 'Try a different search term or status.'
                        : 'KYB submissions from business owners will appear here.' }}
                </p>
                @if ($this->hasActiveFilter)
                    <button type="button" wire:click="clearFilters"
                            class="mt-5 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                   transition-all duration-200 active:scale-95 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent] hover:bg-gray-50 dark:hover:bg-gray-700
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        Clear Filters
                    </button>
                @endif
            </div>
        </div>
    @else
        <div wire:loading.class="opacity-40 pointer-events-none"
             wire:target="search,status,clearFilters,filterBy,gotoPage,nextPage,previousPage"
             class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 transition-opacity duration-200">
            @foreach ($this->applications as $app)
                @php
                    $statusConfig  = $this->statusConfigFor($app->status);
                    $docsLabel     = $app->documents_count . '/' . $this->requiredDocumentsCount;
                    $mismatchCount = (int) $app->mismatches_count;
                    $isComplete    = $app->documents_count >= $this->requiredDocumentsCount;

                    // Additional-business detection: the applicant already
                    // has at least one owner/admin membership on file.
                    // Non-blocking — reviewers still see all the same data,
                    // and approval logic is unchanged. The chip is purely
                    // a signal that this is a follow-up application from a
                    // user who is already onboarded, so the reviewer can
                    // skip baseline identity re-verification.
                    $isAdditional = $app->user !== null
                        && $app->user->relationLoaded('businessMemberships')
                        && $app->user->businessMemberships->isNotEmpty();
                @endphp

                <article wire:key="app-{{ $app->id }}"
                         class="group bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm
                                hover:shadow-md hover:border-primary-300 dark:hover:border-primary-500/40
                                overflow-hidden flex flex-col transition-all duration-200">

                    {{-- Cover thumbnail --}}
                    <div class="relative aspect-[3/1] bg-gray-100 dark:bg-gray-900 overflow-hidden">
                        @if ($app->cover_photo_path)
                            <img src="{{ asset('storage/' . $app->cover_photo_path) }}"
                                 alt="{{ $app->business_name }}"
                                 loading="lazy"
                                 decoding="async"
                                 class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                        @else
                            <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-primary-500 via-primary-600 to-primary-700 text-white/70">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        @endif

                        {{-- Status badge overlay --}}
                        <div class="absolute top-3 right-3">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border shadow-sm backdrop-blur-sm
                                         {{ $statusConfig['classes'] }}">
                                <span class="w-1 h-1 rounded-full bg-current"></span>
                                {{ $statusConfig['label'] }}
                            </span>
                        </div>

                        {{-- Mismatch badge --}}
                        @if ($mismatchCount > 0)
                            <div class="absolute top-3 left-3">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                                             bg-rose-600 text-white shadow-sm backdrop-blur-sm"
                                      title="{{ $mismatchCount }} automated verification check{{ $mismatchCount === 1 ? '' : 's' }} failed.">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    {{ $mismatchCount }}
                                </span>
                            </div>
                        @endif
                    </div>

                    {{-- Content --}}
                    <div class="p-4 flex-1 flex flex-col gap-3">

                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white text-base leading-snug line-clamp-2 min-h-[2.5rem]">
                                {{ $app->business_name ?? '—' }}
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                {{ $app->typeOfTenant?->type ?? 'Uncategorized' }}
                            </p>

                            {{-- Additional-business chip: only for applicants
                                 who already own/administer ≥1 other business. --}}
                            @if ($isAdditional)
                                <div class="mt-2">
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider
                                                 bg-indigo-100 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300
                                                 border border-indigo-200 dark:border-indigo-500/30"
                                          title="This applicant already owns another business on the platform. Consider whether baseline identity re-verification is needed.">
                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Additional business
                                    </span>
                                </div>
                            @endif
                        </div>

                        {{-- Applicant row --}}
                        <div class="flex items-center gap-2 pt-2 border-t border-gray-100 dark:border-gray-700/60">
                            @if ($app->user?->avatar)
                                <img src="{{ asset('storage/' . $app->user->avatar) }}"
                                     alt=""
                                     loading="lazy"
                                     decoding="async"
                                     class="w-7 h-7 rounded-full object-cover shrink-0">
                            @else
                                <div class="w-7 h-7 rounded-full bg-primary-600 text-white flex items-center justify-center text-[10px] font-bold shrink-0">
                                    {{ strtoupper(substr($app->user?->name ?? '?', 0, 1)) }}
                                </div>
                            @endif
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-gray-900 dark:text-white truncate">
                                    {{ $app->user?->name ?? '—' }}
                                </p>
                                <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate">
                                    {{ $app->user?->email ?? '—' }}
                                </p>
                            </div>
                        </div>

                        {{-- Meta rows --}}
                        <div class="space-y-1.5 text-xs">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-gray-500 dark:text-gray-400 inline-flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Documents
                                </span>
                                <span class="font-semibold tabular-nums {{ $isComplete ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                                    {{ $docsLabel }}
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
                                    @if ($app->submitted_at)
                                        {{ $app->submitted_at->format('M j, Y') }}
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500 italic">Not yet</span>
                                    @endif
                                </span>
                            </div>

                            @if ($app->submitted_at)
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 pl-[22px]">
                                    {{ $app->submitted_at->diffForHumans() }}
                                </p>
                            @endif
                        </div>

                        {{-- Actions --}}
                        <div class="mt-auto pt-3 border-t border-gray-100 dark:border-gray-700/60">
                            <a href="{{ route('superadmin.business-applications.show', $app) }}" wire:navigate
                               aria-label="Review application for {{ $app->business_name }}"
                               class="w-full inline-flex items-center justify-center gap-2 h-11 px-3.5 rounded-lg
                                      border border-primary-300 dark:border-primary-500/40
                                      bg-white dark:bg-gray-800 text-primary-700 dark:text-primary-300
                                      text-xs font-semibold
                                      transition-all duration-200 active:scale-95 [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
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
            @endforeach
        </div>

        @if ($this->applications->hasPages())
            <div class="pt-2">
                {{ $this->applications->links() }}
            </div>
        @endif
    @endif
</div>