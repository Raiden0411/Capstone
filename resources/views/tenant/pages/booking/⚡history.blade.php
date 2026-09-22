{{-- resources/views/tenant/pages/booking/⚡history.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Models\Booking;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new
#[Layout('tenant.layouts.app')]
#[Title('Booking History')]
class extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $statusFilter = '';

    #[Url(history: true)]
    public ?string $fromDate = null;

    #[Url(history: true)]
    public ?string $toDate = null;

    #[Url(history: true)]
    public string $sortBy = 'newest';

    public ?int $expandedId = null;

    /**
     * Guard every subsequent Livewire request. mount() runs once per page
     * load; every action (clearFilters, toggleExpand, pagination…) is a
     * separate HTTP request. A revoked session must not be able to invoke
     * them against the tenant scope.
     */
    public function hydrate(): void
    {
        abort_unless(Auth::user()?->tenant_id, 403);
    }

    public function updatedSearch(): void       { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedFromDate(): void     { $this->resetPage(); }
    public function updatedToDate(): void       { $this->resetPage(); }
    public function updatedSortBy(): void       { $this->resetPage(); }

    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'fromDate', 'toDate', 'sortBy']);
        $this->resetPage();
    }

    private function query()
    {
        return Booking::withoutGlobalScope(TenantScope::class)
            ->with([
                'user:id,name,email,phone',
                'items.property:id,name',
                'services.service:id,name',
            ])
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereIn('status', [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(function ($sq) use ($term) {
                    $sq->where('booking_reference', 'like', $term)
                       ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->when($this->fromDate && $this->toDate, function ($q) {
                $q->whereBetween('check_in', [
                    Carbon::parse($this->fromDate)->startOfDay(),
                    Carbon::parse($this->toDate)->endOfDay(),
                ]);
            })
            ->when($this->sortBy, function ($q) {
                match ($this->sortBy) {
                    'oldest'        => $q->oldest(),
                    'check_in_asc'  => $q->orderBy('check_in', 'asc'),
                    'check_in_desc' => $q->orderBy('check_in', 'desc'),
                    'amount_high'   => $q->orderByDesc('total_amount'),
                    'amount_low'    => $q->orderBy('total_amount'),
                    default         => $q->latest(),
                };
            });
    }

    #[Computed]
    public function bookings()
    {
        return $this->query()->paginate(15);
    }

    #[Computed]
    public function stats(): array
    {
        $raw = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereIn('status', [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED])
            ->selectRaw("
                COUNT(CASE WHEN status = ? THEN 1 END) as total_completed,
                COUNT(CASE WHEN status = ? THEN 1 END) as total_cancelled,
                COALESCE(SUM(CASE WHEN status = ? THEN total_amount ELSE 0 END), 0) as revenue_completed
            ", [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
            ->first();

        $completed = (int) ($raw->total_completed ?? 0);
        $cancelled = (int) ($raw->total_cancelled ?? 0);
        $revenue   = (float) ($raw->revenue_completed ?? 0);

        return [
            'total_completed'   => $completed,
            'total_cancelled'   => $cancelled,
            'total_bookings'    => $completed + $cancelled,
            'revenue_completed' => $revenue,
            'avg_booking_value' => $completed > 0 ? $revenue / $completed : 0,
        ];
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->statusFilter !== ''
            || ! empty($this->fromDate)
            || ! empty($this->toDate);
    }
};
?>

@php $s = $this->stats; @endphp

<div class="history-page p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6">

    {{-- ═══ Page header ═══ --}}
    <div class="no-print flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Bookings</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                Booking History
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                Review finalized and cancelled booking archives.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" onclick="window.print()"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                           transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-6-4h.01M6 18v4h12v-4"/>
                </svg>
                <span>Print Report</span>
            </button>

            <a href="{{ route('tenant.bookings.index') }}" wire:navigate
               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                      transition-all duration-200 active:scale-95
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>Active Bookings</span>
            </a>
        </div>
    </div>

    {{-- ═══ Compact KPI strip ═══ --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @php
            $kpis = [
                [
                    'label' => 'Total Archived',
                    'value' => number_format($s['total_bookings']),
                    'dot'   => 'bg-slate-500',
                    'sub'   => null,
                ],
                [
                    'label' => 'Completed',
                    'value' => number_format($s['total_completed']),
                    'dot'   => 'bg-emerald-500',
                    'sub'   => null,
                ],
                [
                    'label' => 'Cancelled',
                    'value' => number_format($s['total_cancelled']),
                    'dot'   => 'bg-rose-500',
                    'sub'   => null,
                ],
                [
                    'label' => 'Total Revenue',
                    'value' => '₱' . number_format($s['revenue_completed'], 0),
                    'dot'   => 'bg-emerald-500',
                    'sub'   => $s['total_completed'] > 0
                        ? 'Avg ₱' . number_format($s['avg_booking_value'], 0) . '/booking'
                        : null,
                ],
            ];
        @endphp
        @foreach($kpis as $kpi)
            <div wire:key="kpi-{{ $loop->index }}"
                 class="bg-white dark:bg-gray-800/90 rounded-xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-3.5">
                <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full {{ $kpi['dot'] }}"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</span>
                </div>
                <p class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white tabular-nums">{{ $kpi['value'] }}</p>
                @if(!empty($kpi['sub']))
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wider mt-0.5">{{ $kpi['sub'] }}</p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ═══ Filters ═══ --}}
    <div class="no-print bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4 space-y-4">

        {{-- Row 1: Search --}}
        <div class="relative">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   class="input w-full"
                   style="padding-left: 2.5rem;"
                   placeholder="Search reference, guest name, or email…">
        </div>

        {{-- Row 2: Sort --}}
        <div>
            <select wire:model.live="sortBy" class="input w-full">
                <option value="newest">Newest First</option>
                <option value="oldest">Oldest First</option>
                <option value="check_in_asc">Start (Earliest)</option>
                <option value="check_in_desc">Start (Latest)</option>
                <option value="amount_high">Amount (High → Low)</option>
                <option value="amount_low">Amount (Low → High)</option>
            </select>
        </div>

        {{-- Row 3: Date range --}}
        <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Date Range</p>
            <div class="grid grid-cols-2 gap-2">
                <input type="date"
                       wire:model.live="fromDate"
                       class="input w-full"
                       aria-label="From date">
                <input type="date"
                       wire:model.live="toDate"
                       class="input w-full"
                       aria-label="To date">
            </div>
        </div>

        {{-- Row 4: Status pills with inline counts --}}
        @php
            $pills = [
                ['value' => '',            'label' => 'All',       'count' => $s['total_bookings']],
                ['value' => 'completed',   'label' => 'Completed', 'count' => $s['total_completed']],
                ['value' => 'cancelled',   'label' => 'Cancelled', 'count' => $s['total_cancelled']],
            ];
        @endphp
        <div class="flex flex-wrap gap-2 items-center pt-1">
            @foreach($pills as $pill)
                @php $isActive = $statusFilter === $pill['value']; @endphp
                <button type="button"
                        wire:click="$set('statusFilter', '{{ $pill['value'] }}')"
                        wire:key="pill-{{ $pill['value'] !== '' ? $pill['value'] : 'all' }}"
                        aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                        class="inline-flex items-center gap-2 h-9 pl-3.5 pr-1.5 rounded-full text-xs font-semibold uppercase tracking-wide border
                               transition-all duration-200 active:scale-95 shrink-0
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $isActive
                                  ? 'bg-primary-600 border-primary-600 text-white shadow-sm'
                                  : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400' }}">
                    <span>{{ $pill['label'] }}</span>
                    <span class="inline-flex items-center justify-center min-w-[22px] h-5 px-1.5 rounded-full text-[10px] font-bold tabular-nums
                                 {{ $isActive
                                    ? 'bg-white/20 text-white'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                        {{ $pill['count'] }}
                    </span>
                </button>
            @endforeach

            @if($this->hasActiveFilters || $sortBy !== 'newest')
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
            @endif
        </div>
    </div>

    {{-- ═══ Card grid ═══ --}}
    @if($this->bookings->isEmpty())
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-12 text-center">
            <div class="flex flex-col items-center max-w-md mx-auto">
                <svg class="w-14 h-14 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <p class="mt-4 text-base font-semibold text-gray-900 dark:text-white">
                    No archived bookings found
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if($this->hasActiveFilters)
                        No bookings match your current filters. Try adjusting or clearing them.
                    @else
                        Completed and cancelled bookings will appear here once they are archived.
                    @endif
                </p>
                @if($this->hasActiveFilters)
                    <button type="button" wire:click="clearFilters"
                            class="mt-5 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                   transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        Clear Filters
                    </button>
                @endif
            </div>
        </div>
    @else
        <div wire:loading.class="opacity-40 pointer-events-none"
             wire:target="search,statusFilter,fromDate,toDate,sortBy,clearFilters,gotoPage,nextPage,previousPage"
             class="print-grid grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 transition-opacity duration-200">
            @foreach($this->bookings as $booking)
                @php
                    $isCompleted = $booking->status === 'completed';
                    $days = ($booking->check_in && $booking->check_out)
                        ? max(1, $booking->check_in->diffInDays($booking->check_out))
                        : 0;
                @endphp

                <article wire:key="booking-{{ $booking->id }}"
                         class="print-card group relative bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm hover:shadow-md transition-all duration-200 flex flex-col overflow-hidden">

                    {{-- Guest + status badge --}}
                    <div class="p-4 pb-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-11 h-11 rounded-full {{ $isCompleted ? 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-rose-50 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400' }} flex items-center justify-center font-bold text-sm shrink-0">
                                    {{ strtoupper(substr($booking->user->name ?? 'G', 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 dark:text-white truncate leading-tight">
                                        {{ $booking->user->name ?? 'Walk-in Guest' }}
                                    </p>
                                    <p class="text-[11px] font-mono text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                        {{ $booking->booking_reference }}
                                    </p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border shrink-0
                                         {{ $isCompleted
                                            ? 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/40'
                                            : 'bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-500/40' }}">
                                <span class="w-1 h-1 rounded-full bg-current"></span>
                                {{ ucfirst($booking->status) }}
                            </span>
                        </div>
                    </div>

                    {{-- Dates --}}
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700/60 space-y-1">
                        <div class="flex items-center gap-2 text-xs">
                            <svg class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-gray-700 dark:text-gray-300 tabular-nums truncate">
                                {{ $booking->check_in?->format('M d, Y') ?? '—' }}
                                <span class="text-gray-400 dark:text-gray-500 mx-0.5">→</span>
                                {{ $booking->check_out?->format('M d, Y') ?? '—' }}
                            </span>
                        </div>
                        @if($days > 0)
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 pl-5.5">{{ $days }} day{{ $days != 1 ? 's' : '' }}</p>
                        @endif
                    </div>

                    {{-- Items preview --}}
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700/60 space-y-1 text-xs">
                        @foreach($booking->items->take(2) as $item)
                            <div class="flex justify-between gap-2" wire:key="hist-item-{{ $item->id }}">
                                <span class="text-gray-600 dark:text-gray-400 truncate">
                                    {{ $item->property->name ?? 'Activity' }} <span class="text-gray-400 dark:text-gray-500">×{{ $item->quantity }}</span>
                                </span>
                                <span class="text-gray-900 dark:text-white tabular-nums shrink-0">₱{{ number_format((float) $item->subtotal, 0) }}</span>
                            </div>
                        @endforeach
                        @if($booking->items->count() > 2)
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 pt-0.5">
                                +{{ $booking->items->count() - 2 }} more item{{ $booking->items->count() - 2 != 1 ? 's' : '' }}
                            </p>
                        @endif
                        @if($booking->services->isNotEmpty())
                            <div class="pt-1.5 mt-1.5 border-t border-dashed border-gray-200 dark:border-gray-700 flex justify-between text-[11px] text-gray-500 dark:text-gray-400">
                                <span>{{ $booking->services->count() }} add-on service{{ $booking->services->count() != 1 ? 's' : '' }}</span>
                                <span class="tabular-nums">₱{{ number_format((float) $booking->services->sum('subtotal'), 0) }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Total --}}
                    <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700/60">
                        <p class="text-lg font-bold text-gray-900 dark:text-white tabular-nums leading-none">
                            ₱{{ number_format((float) $booking->total_amount, 2) }}
                        </p>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">Total booking value</p>
                    </div>

                    {{-- Actions --}}
                    <div class="no-print mt-auto px-3 py-2.5 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-end gap-1">
                        <a href="{{ route('tenant.bookings.show', $booking->id) }}" wire:navigate
                           aria-label="View booking {{ $booking->booking_reference }}"
                           title="View booking"
                           class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-gray-500 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700
                                  transition-all duration-200 active:scale-95
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </a>
                    </div>
                </article>
            @endforeach
        </div>

        @if($this->bookings->hasPages())
            <div class="no-print pt-2">
                {{ $this->bookings->links() }}
            </div>
        @endif
    @endif


    {{-- ═══════════════════════════════════════════════════════════════
         INLINE PRINT CSS + JS SCOPE HANDLER
         Guarantees print styles are in the DOM (SFC @push('styles') may
         not reach the layout's @stack('styles') in <head>) and hides
         the tenant layout chrome (header/sidebar) via inline styles.
         ═══════════════════════════════════════════════════════════════ --}}
    <style>
        @media print {
            @page { size: auto; margin: 12mm; }

            html, body {
                background: #fff !important;
                color: #000 !important;
                margin: 0 !important;
                padding: 0 !important;
                min-height: 0 !important;
                height: auto !important;
            }

            /* Reveal only our root; JS strips everything else. */
            body * { visibility: hidden !important; }
            .history-page,
            .history-page * { visibility: visible !important; }

            .history-page {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                box-sizing: border-box;
                background: #fff !important;
            }

            /* Hide interactive elements. */
            .no-print { display: none !important; }

            /* Print 2-column grid for density. */
            .print-grid {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 6mm !important;
            }

            /* Flatten cards to a clean paper-friendly look. */
            .print-card {
                border: 1px solid #ddd !important;
                border-radius: 4px !important;
                box-shadow: none !important;
                background: #fff !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            /* Force black text on paper regardless of dark-mode classes. */
            .print-card .text-gray-900,
            .print-card .text-gray-700,
            .print-card .text-gray-600,
            .print-card .text-gray-500,
            .print-card .text-gray-400,
            .print-card .dark\:text-white,
            .print-card .dark\:text-gray-200,
            .print-card .dark\:text-gray-300,
            .print-card .dark\:text-gray-400 {
                color: #111 !important;
            }

            .print-card .bg-white,
            .print-card .dark\:bg-gray-800\/90,
            .print-card .bg-gray-50,
            .print-card .dark\:bg-gray-700\/50 {
                background: #fff !important;
            }

            .print-card .border-gray-200\/80,
            .print-card .dark\:border-gray-700\/80,
            .print-card .border-gray-100,
            .print-card .dark\:border-gray-700\/60 {
                border-color: #ddd !important;
            }

            /* KPI strip renders as a compact row on paper. */
            .history-page > .grid {
                display: grid !important;
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 3mm !important;
            }
        }
    </style>

    <script>
        (function () {
            if (window.__historyPrintScopeInstalled) return;
            window.__historyPrintScopeInstalled = true;

            function applyPrintScope() {
                const root = document.querySelector('.history-page');
                if (!root) return;

                // Walk up from the root to <body>, collecting ancestors.
                const ancestors = new Set();
                let el = root;
                while (el && el !== document.body) {
                    ancestors.add(el);
                    el = el.parentElement;
                }

                // Hide every direct child of body that is not an ancestor
                // of our root. Inline !important beats every stylesheet rule.
                Array.from(document.body.children).forEach(function (child) {
                    if (!ancestors.has(child)) {
                        if (child.dataset.printHidden !== '1') {
                            child.dataset.printHidden = '1';
                            child.dataset.printOldDisplay = child.style.display || '';
                        }
                        child.style.setProperty('display', 'none', 'important');
                    }
                });

                // Strip padding/margin/background from ancestors so nothing
                // pushes the printable area down or right.
                ancestors.forEach(function (node) {
                    if (node === root) return;
                    if (node.dataset.printReset !== '1') {
                        node.dataset.printReset = '1';
                        node.dataset.printOldPadding = node.style.padding || '';
                        node.dataset.printOldMargin = node.style.margin || '';
                        node.dataset.printOldBackground = node.style.background || '';
                    }
                    node.style.setProperty('padding', '0', 'important');
                    node.style.setProperty('margin', '0', 'important');
                    node.style.setProperty('background', 'transparent', 'important');
                });
            }

            function restorePrintScope() {
                document.querySelectorAll('[data-print-hidden="1"]').forEach(function (node) {
                    node.style.removeProperty('display');
                    if (node.dataset.printOldDisplay) {
                        node.style.display = node.dataset.printOldDisplay;
                    }
                    delete node.dataset.printHidden;
                    delete node.dataset.printOldDisplay;
                });

                document.querySelectorAll('[data-print-reset="1"]').forEach(function (node) {
                    node.style.removeProperty('padding');
                    node.style.removeProperty('margin');
                    node.style.removeProperty('background');
                    if (node.dataset.printOldPadding) node.style.padding = node.dataset.printOldPadding;
                    if (node.dataset.printOldMargin)  node.style.margin  = node.dataset.printOldMargin;
                    if (node.dataset.printOldBackground) node.style.background = node.dataset.printOldBackground;
                    delete node.dataset.printReset;
                    delete node.dataset.printOldPadding;
                    delete node.dataset.printOldMargin;
                    delete node.dataset.printOldBackground;
                });
            }

            window.addEventListener('beforeprint', applyPrintScope);
            window.addEventListener('afterprint', restorePrintScope);
        })();
    </script>
</div>