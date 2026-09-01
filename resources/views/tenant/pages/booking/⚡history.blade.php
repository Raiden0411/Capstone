{{-- resources/views/tenant/pages/booking/⚡history.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
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

    public string $search       = '';
    public string $statusFilter = '';
    public ?string $fromDate    = null;
    public ?string $toDate      = null;
    public string $sortBy       = 'newest';

    public ?int $expandedId = null;

    public function updatingSearch()       { $this->resetPage(); }
    public function updatingStatusFilter() { $this->resetPage(); }
    public function updatingFromDate()     { $this->resetPage(); }
    public function updatingToDate()       { $this->resetPage(); }
    public function updatingSortBy()       { $this->resetPage(); }

    public function toggleExpand(int $id)
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function clearFilters()
    {
        $this->reset(['search', 'statusFilter', 'fromDate', 'toDate', 'sortBy']);
        $this->resetPage();
    }

    public function exportCsv()
    {
        $bookings = $this->query()->get();

        $filename = 'booking_history_' . now()->format('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($bookings) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Reference', 'Guest', 'Email', 'Phone', 'Check-in', 'Check-out', 'Total', 'Status']);
            foreach ($bookings as $b) {
                fputcsv($file, [
                    $b->booking_reference,
                    $b->user->name ?? 'N/A',
                    $b->user->email ?? '',
                    $b->user->phone ?? '',
                    $b->check_in?->format('Y-m-d') ?? '',
                    $b->check_out?->format('Y-m-d') ?? '',
                    number_format($b->total_amount, 2),
                    $b->status,
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
                $q->where(function ($sq) {
                    $sq->where('booking_reference', 'like', '%'.$this->search.'%')
                       ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->fromDate && $this->toDate, function ($q) {
                $q->whereBetween('check_in', [
                    Carbon::parse($this->fromDate)->startOfDay(),
                    Carbon::parse($this->toDate)->endOfDay(),
                ]);
            })
            ->when($this->sortBy, function ($q) {
                switch ($this->sortBy) {
                    case 'newest':        $q->latest(); break;
                    case 'oldest':        $q->oldest(); break;
                    case 'check_in_asc':  $q->orderBy('check_in', 'asc'); break;
                    case 'check_in_desc': $q->orderBy('check_in', 'desc'); break;
                    case 'amount_high':   $q->orderByDesc('total_amount'); break;
                    case 'amount_low':    $q->orderBy('total_amount'); break;
                    default: $q->latest();
                }
            });
    }

    #[Computed]
    public function bookings()
    {
        return $this->query()->paginate(15);
    }

    #[Computed]
    public function stats()
    {
        $tid = Auth::user()->tenant_id;

        $completed = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tid)
            ->where('status', Booking::STATUS_COMPLETED);

        $cancelled = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tid)
            ->where('status', Booking::STATUS_CANCELLED);

        $totalCompleted = (clone $completed)->count();
        $totalCancelled = (clone $cancelled)->count();
        $revenueCompleted = (clone $completed)->sum('total_amount');
        $avgBookingValue = $totalCompleted > 0 ? $revenueCompleted / $totalCompleted : 0;

        return [
            'total_completed'   => $totalCompleted,
            'total_cancelled'   => $totalCancelled,
            'total_bookings'    => $totalCompleted + $totalCancelled,
            'revenue_completed' => $revenueCompleted,
            'avg_booking_value' => $avgBookingValue,
        ];
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Booking History</h1>
        </div>
        <div class="flex flex-wrap gap-2">
            <button wire:click="exportCsv" wire:loading.attr="disabled"
                    class="btn-secondary text-xs sm:text-sm active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
                <span wire:loading wire:target="exportCsv" class="inline-flex items-center gap-1">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Exporting…
                </span>
            </button>
            <button type="button" onclick="window.print()"
                    class="btn-secondary text-xs sm:text-sm active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-6-4h.01M6 18v4h12v-4"/></svg>
                Print
            </button>
            <a href="{{ route('tenant.bookings.index') }}" wire:navigate
               class="btn-primary text-xs sm:text-sm active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Active Bookings
            </a>
        </div>
    </div>

    {{-- Stats --}}
    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="card p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Bookings</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $s['total_bookings'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Completed</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-2">{{ $s['total_completed'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Cancelled</p>
            <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-2">{{ $s['total_cancelled'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Revenue (Completed)</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">₱{{ number_format($s['revenue_completed'], 2) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Avg Booking Value</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">₱{{ number_format($s['avg_booking_value'], 2) }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card p-4 space-y-3">
        <div class="flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search by reference or guest…"
                       class="input pl-10">
            </div>
            <select wire:model.live="statusFilter" class="select w-full sm:w-auto">
                <option value="">All Status</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
        <div class="flex flex-wrap gap-3 items-center">
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400">From:</span>
                <input type="date" wire:model.live="fromDate" class="input !py-2 !w-auto">
                <span class="text-xs text-gray-500 dark:text-gray-400">To:</span>
                <input type="date" wire:model.live="toDate" class="input !py-2 !w-auto">
            </div>
            <select wire:model.live="sortBy" class="select w-full sm:w-auto">
                <option value="newest">Newest First</option>
                <option value="oldest">Oldest First</option>
                <option value="check_in_asc">Check-in (Earliest)</option>
                <option value="check_in_desc">Check-in (Latest)</option>
                <option value="amount_high">Amount (High to Low)</option>
                <option value="amount_low">Amount (Low to High)</option>
            </select>
            @if($search || $statusFilter || $fromDate || $toDate)
                <button wire:click="clearFilters"
                        class="btn-secondary text-xs active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Clear
                </button>
            @endif
        </div>
    </div>

    {{-- Bookings Table --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Booking Ref</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Guest</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase hidden md:table-cell">Check In/Out</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Total</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Status</th>
                        <th class="px-4 sm:px-6 py-4 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-200">
                    @forelse($this->bookings as $booking)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors cursor-pointer"
                            wire:click="toggleExpand({{ $booking->id }})">
                            <td class="px-4 sm:px-6 py-4 font-mono text-sm text-gray-900 dark:text-white">
                                {{ $booking->booking_reference }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-sm">
                                {{ $booking->user->name ?? 'N/A' }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-sm hidden md:table-cell">
                                {{ $booking->check_in?->format('M d, Y') ?? '—' }} → {{ $booking->check_out?->format('M d, Y') ?? '—' }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                ₱{{ number_format($booking->total_amount, 2) }}
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $booking->status === 'completed' ? 'bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-500/30' : 'bg-red-100 dark:bg-red-500/15 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-500/30' }}">
                                    {{ ucfirst($booking->status) }}
                                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-right" wire:click.stop>
                                <a href="{{ route('tenant.bookings.show', $booking->id) }}" wire:navigate
                                   class="p-1.5 text-gray-400 dark:text-gray-500 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50" title="View Details">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                            </td>
                        </tr>

                        @if($expandedId === $booking->id)
                            <tr>
                                <td colspan="6" class="p-0 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400 mb-3">Guest Details</h4>
                                            <div class="space-y-1 text-sm">
                                                <p><span class="text-gray-500 dark:text-gray-400">Name:</span> {{ $booking->user->name ?? 'N/A' }}</p>
                                                <p><span class="text-gray-500 dark:text-gray-400">Phone:</span> {{ $booking->user->phone ?? '—' }}</p>
                                                <p><span class="text-gray-500 dark:text-gray-400">Email:</span> {{ $booking->user->email ?? '—' }}</p>
                                            </div>
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400 mb-3">Activities & Services</h4>
                                            @foreach($booking->items as $item)
                                                <div class="flex justify-between text-sm py-1">
                                                    <span class="text-gray-700 dark:text-gray-300">{{ $item->property->name ?? 'Activity' }}</span>
                                                    <span class="text-gray-900 dark:text-white">₱{{ number_format($item->subtotal, 2) }}</span>
                                                </div>
                                            @endforeach
                                            @foreach($booking->services as $bs)
                                                <div class="flex justify-between text-sm py-1">
                                                    <span class="text-gray-500 dark:text-gray-400">+ {{ $bs->service->name ?? 'Service' }}</span>
                                                    <span class="text-gray-500 dark:text-gray-400">₱{{ number_format($bs->subtotal, 2) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <p class="text-lg font-medium">No history found.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->bookings->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $this->bookings->links() }}
            </div>
        @endif
    </div>
</div>