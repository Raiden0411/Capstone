{{-- resources/views/tenant/pages/booking/history.blade.php --}}
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

    public function updatedSearch()       { $this->resetPage(); }
    public function updatedStatusFilter() { $this->resetPage(); }
    public function updatedFromDate()     { $this->resetPage(); }
    public function updatedToDate()       { $this->resetPage(); }
    public function updatedSortBy()       { $this->resetPage(); }

    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'fromDate', 'toDate', 'sortBy']);
        $this->resetPage();
    }

    public function exportCsv()
    {
        $bookings = $this->query()->get();
        $filename = 'booking_history_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($bookings) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Reference', 'Guest Name', 'Email', 'Phone', 'Check-in', 'Check-out', 'Total (PHP)', 'Status']);

            foreach ($bookings as $b) {
                fputcsv($file, [
                    $b->booking_reference,
                    $b->user->name ?? 'N/A',
                    $b->user->email ?? '',
                    $b->user->phone ?? '',
                    $b->check_in?->format('Y-m-d') ?? '',
                    $b->check_out?->format('Y-m-d') ?? '',
                    number_format($b->total_amount, 2, '.', ''),
                    ucfirst($b->status),
                ]);
            }
            fclose($file);
        };

        return response()->streamDownload($callback, $filename, $headers);
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
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(function ($sq) use ($term) {
                    $sq->where('booking_reference', 'like', $term)
                       ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', $term)->orWhere('email', 'like', $term));
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
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Booking History</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Review finalized and cancelled booking archives.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5 no-print">
            <button wire:click="exportCsv" wire:loading.attr="disabled"
                    class="px-4 py-2 rounded-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 text-xs sm:text-sm font-medium transition shadow-sm inline-flex items-center gap-2 focus:ring-2 focus:ring-blue-500/50">
                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
                <span wire:loading wire:target="exportCsv" class="inline-flex items-center gap-1.5">
                    <svg class="animate-spin h-3.5 w-3.5 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Exporting…
                </span>
            </button>

            <button type="button" onclick="window.print()"
                    class="px-4 py-2 rounded-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 text-xs sm:text-sm font-medium transition shadow-sm inline-flex items-center gap-2 focus:ring-2 focus:ring-blue-500/50">
                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-6-4h.01M6 18v4h12v-4"/></svg>
                Print
            </button>

            <a href="{{ route('tenant.bookings.index') }}" wire:navigate
               class="px-4 py-2 rounded-full bg-[#376df1] hover:bg-blue-700 text-white text-xs sm:text-sm font-semibold transition shadow-md shadow-blue-500/10 inline-flex items-center gap-2 focus:ring-2 focus:ring-blue-500/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Active Bookings
            </a>
        </div>
    </div>

    {{-- Aggregate Analytics --}}
    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Bookings</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($s['total_bookings']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Completed</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1">{{ number_format($s['total_completed']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Cancelled</p>
            <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">{{ number_format($s['total_cancelled']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Revenue</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">₱{{ number_format($s['revenue_completed'], 2) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm col-span-2 lg:col-span-1">
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Avg Booking Value</p>
            <p class="text-2xl font-bold text-[#376df1] dark:text-blue-400 mt-1">₱{{ number_format($s['avg_booking_value'], 2) }}</p>
        </div>
    </div>

    {{-- Filter Toolbar --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 space-y-3 shadow-sm no-print">
        <div class="flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-[240px]">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search by reference, guest name or email…"
                       class="w-full pl-10 pr-4 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition">
            </div>
            
            <select wire:model.live="statusFilter" class="px-3.5 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500/50">
                <option value="">All Statuses</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>

            <select wire:model.live="sortBy" class="px-3.5 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500/50">
                <option value="newest">Newest First</option>
                <option value="oldest">Oldest First</option>
                <option value="check_in_asc">Check-in (Earliest)</option>
                <option value="check_in_desc">Check-in (Latest)</option>
                <option value="amount_high">Amount (High to Low)</option>
                <option value="amount_low">Amount (Low to High)</option>
            </select>
        </div>

        <div class="flex flex-wrap gap-3 items-center justify-between pt-1">
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">From:</span>
                <input type="date" wire:model.live="fromDate" class="px-3 py-1.5 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-xs text-gray-900 dark:text-white">
                <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">To:</span>
                <input type="date" wire:model.live="toDate" class="px-3 py-1.5 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg text-xs text-gray-900 dark:text-white">
            </div>

            @if($search || $statusFilter || $fromDate || $toDate || $sortBy !== 'newest')
                <button wire:click="clearFilters"
                        class="px-3 py-1.5 text-xs text-gray-600 dark:text-gray-300 hover:text-red-600 dark:hover:text-red-400 transition font-medium inline-flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Reset Filters
                </button>
            @endif
        </div>
    </div>

    {{-- Data Table --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm relative">
        {{-- Loading Overlay --}}
        <div wire:loading.flex wire:target="search, statusFilter, fromDate, toDate, sortBy, clearFilters, gotoPage, nextPage, previousPage"
             class="absolute inset-0 bg-white/50 dark:bg-gray-800/50 backdrop-blur-[1px] z-10 items-center justify-center">
            <svg class="animate-spin h-6 w-6 text-[#376df1]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50/50 dark:bg-gray-700/30 border-b border-gray-200 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="w-10 px-4 py-3.5"></th>
                        <th class="px-4 sm:px-6 py-3.5">Booking Ref</th>
                        <th class="px-4 sm:px-6 py-3.5">Guest</th>
                        <th class="px-4 sm:px-6 py-3.5 hidden md:table-cell">Check In / Out</th>
                        <th class="px-4 sm:px-6 py-3.5">Total</th>
                        <th class="px-4 sm:px-6 py-3.5">Status</th>
                        <th class="px-4 sm:px-6 py-3.5 text-right no-print">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50 text-sm text-gray-700 dark:text-gray-200">
                    @forelse($this->bookings as $booking)
                        <tr wire:key="booking-{{ $booking->id }}"
                            wire:click="toggleExpand({{ $booking->id }})"
                            class="hover:bg-gray-50/80 dark:hover:bg-gray-700/40 transition-colors cursor-pointer select-none">
                            <td class="px-4 py-4 text-center">
                                <svg class="w-4 h-4 text-gray-400 transition-transform duration-200 {{ $expandedId === $booking->id ? 'rotate-90 text-[#376df1]' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </td>
                            <td class="px-4 sm:px-6 py-4 font-mono font-medium text-gray-900 dark:text-white">
                                #{{ $booking->booking_reference }}
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $booking->user->name ?? 'N/A' }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $booking->user->email ?? '' }}</div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 hidden md:table-cell text-xs text-gray-600 dark:text-gray-300">
                                {{ $booking->check_in?->format('M d, Y') ?? '—' }} &rarr; {{ $booking->check_out?->format('M d, Y') ?? '—' }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 font-semibold text-gray-900 dark:text-white">
                                ₱{{ number_format($booking->total_amount, 2) }}
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border
                                    {{ $booking->status === 'completed' 
                                        ? 'bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 border-green-200 dark:border-green-500/30' 
                                        : 'bg-red-100 dark:bg-red-500/15 text-red-700 dark:text-red-300 border-red-200 dark:border-red-500/30' }}">
                                    {{ ucfirst($booking->status) }}
                                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-right no-print" wire:click.stop>
                                <a href="{{ route('tenant.bookings.show', $booking->id) }}" wire:navigate
                                   class="p-1.5 text-gray-400 hover:text-[#376df1] dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-gray-700 rounded-lg transition" 
                                   title="View Full Booking">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                            </td>
                        </tr>

                        {{-- Expanded Quick View Row --}}
                        @if($expandedId === $booking->id)
                            <tr wire:key="expanded-{{ $booking->id }}">
                                <td colspan="7" class="p-0 bg-gray-50/70 dark:bg-gray-900/40 border-b border-gray-200 dark:border-gray-700">
                                    <div class="p-5 sm:p-6 grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wider text-[#376df1] dark:text-blue-400 mb-3">Guest Profile</h4>
                                            <div class="space-y-1.5">
                                                <p><span class="text-gray-500 dark:text-gray-400">Name:</span> <strong class="text-gray-900 dark:text-white">{{ $booking->user->name ?? 'N/A' }}</strong></p>
                                                @if($booking->user?->phone)
                                                    <p><span class="text-gray-500 dark:text-gray-400">Phone:</span> <a href="tel:{{ $booking->user->phone }}" class="text-[#376df1] hover:underline">{{ $booking->user->phone }}</a></p>
                                                @endif
                                                @if($booking->user?->email)
                                                    <p><span class="text-gray-500 dark:text-gray-400">Email:</span> <a href="mailto:{{ $booking->user->email }}" class="text-[#376df1] hover:underline">{{ $booking->user->email }}</a></p>
                                                @endif
                                            </div>
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wider text-[#376df1] dark:text-blue-400 mb-3">Activity Breakdowns</h4>
                                            <div class="space-y-2 divide-y divide-gray-200/50 dark:divide-gray-700/50">
                                                @forelse($booking->items as $item)
                                                    <div class="flex justify-between pt-1">
                                                        <span class="text-gray-700 dark:text-gray-300 font-medium">{{ $item->property->name ?? 'Property Activity' }}</span>
                                                        <span class="text-gray-900 dark:text-white font-semibold">₱{{ number_format($item->subtotal, 2) }}</span>
                                                    </div>
                                                @empty
                                                    <p class="text-xs text-gray-500">No items listed.</p>
                                                @endforelse

                                                @foreach($booking->services as $bs)
                                                    <div class="flex justify-between pt-1 text-xs">
                                                        <span class="text-gray-500 dark:text-gray-400">+ {{ $bs->service->name ?? 'Extra Service' }} (x{{ $bs->quantity }})</span>
                                                        <span class="text-gray-600 dark:text-gray-300">₱{{ number_format($bs->subtotal, 2) }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <p class="text-base font-medium">No archived bookings match your query.</p>
                                @if($search || $statusFilter || $fromDate || $toDate)
                                    <button wire:click="clearFilters" class="mt-2 text-xs text-[#376df1] hover:underline font-semibold">Clear active filters</button>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->bookings->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-gray-700 no-print">
                {{ $this->bookings->links() }}
            </div>
        @endif
    </div>
</div>

@push('styles')
<style>
    @media print {
        @page { size: auto; margin: 1cm; }
        body { background: white !important; color: black !important; }
        .no-print { display: none !important; }
        .bg-white, .dark\:bg-gray-800 { background: transparent !important; border-color: #e5e7eb !important; box-shadow: none !important; }
        table { width: 100% !important; border-collapse: collapse !important; }
        th, td { border-bottom: 1px solid #e5e7eb !important; }
    }
</style>
@endpush