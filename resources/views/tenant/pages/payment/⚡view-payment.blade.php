{{-- resources/views/tenant/pages/payment/⚡view-payment.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Models\Payment;
use App\Models\Booking;
use App\Jobs\ProcessPayMongoPayment;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

new 
#[Layout('tenant.layouts.app')]
#[Title('Payments')]
class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $statusFilter = '';
    #[Url]
    public string $methodFilter = '';
    #[Url]
    public ?string $fromDate = null;
    #[Url]
    public ?string $toDate = null;
    #[Url]
    public string $sortBy = 'newest';

    public function mount()
    {
        $this->syncUnpaidPayments(20);
    }

    public function updatingSearch()       { $this->resetPage(); }
    public function updatingStatusFilter() { $this->resetPage(); }
    public function updatingMethodFilter() { $this->resetPage(); }
    public function updatingFromDate()     { $this->resetPage(); }
    public function updatingToDate()       { $this->resetPage(); }
    public function updatingSortBy()       { $this->resetPage(); }

    /**
     * Sync status of recent unpaid PayMongo payments.
     * Uses a transaction to avoid multiple syncs overlapping.
     */
    public function syncUnpaidPayments(int $limit = 20)
    {
        DB::transaction(function () use ($limit) {
            $payments = Payment::where('tenant_id', Auth::user()->tenant_id)
                ->where('payment_status', 'unpaid')
                ->whereNotNull('paymongo_session_id')
                ->latest()
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            foreach ($payments as $payment) {
                try {
                    // Use async dispatch to avoid blocking the UI
                    ProcessPayMongoPayment::dispatch($payment->paymongo_session_id);
                } catch (\Exception $e) {
                    Log::error('Failed to dispatch PayMongo sync: ' . $e->getMessage());
                }
            }
        });
    }

    public function refreshSync()
    {
        $this->syncUnpaidPayments(50);
        session()->flash('message', 'Payment statuses synced with PayMongo.');
        $this->resetPage();
    }

    #[Computed]
    public function payments()
    {
        return Payment::query()
            ->with([
                'booking' => fn($q) => $q
                    ->withoutGlobalScope(TenantScope::class) // ensure all related bookings load
                    ->with([
                        'user:id,name,email,phone',
                        'payments' => fn($q) => $q->withoutGlobalScope(TenantScope::class)
                            ->select('id', 'booking_id', 'payment_status', 'amount')
                    ])
                    ->select('id', 'tenant_id', 'user_id', 'total_amount', 'booking_reference', 'status')
            ])
            ->where('tenant_id', Auth::user()->tenant_id)
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('reference_number', 'like', '%'.$this->search.'%')
                       ->orWhere('paymongo_session_id', 'like', '%'.$this->search.'%')
                       ->orWhereHas('booking', function ($bq) {
                           $bq->where('booking_reference', 'like', '%'.$this->search.'%')
                              ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', '%'.$this->search.'%'));
                       });
                });
            })
            ->when($this->statusFilter, fn($q) => $q->where('payment_status', $this->statusFilter))
            ->when($this->methodFilter, fn($q) => $q->where('payment_method', $this->methodFilter))
            ->when($this->fromDate && $this->toDate, function ($q) {
                $q->whereBetween('created_at', [
                    Carbon::parse($this->fromDate)->startOfDay(),
                    Carbon::parse($this->toDate)->endOfDay(),
                ]);
            })
            ->when($this->sortBy, function ($q) {
                match ($this->sortBy) {
                    'oldest'      => $q->oldest(),
                    'amount_high' => $q->orderByDesc('amount'),
                    'amount_low'  => $q->orderBy('amount'),
                    default       => $q->latest(),
                };
            })
            ->paginate(15);
    }

    #[Computed]
    public function stats()
    {
        $tid = Auth::user()->tenant_id;

        $agg = Payment::where('tenant_id', $tid)
            ->selectRaw("
                SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as total_received,
                SUM(CASE WHEN payment_status = 'unpaid' THEN amount ELSE 0 END) as total_pending,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
                SUM(CASE WHEN payment_type = 'reservation' AND payment_status = 'paid' THEN amount ELSE 0 END) as reservation_fees
            ")
            ->first();

        return [
            'total_received'   => $agg->total_received ?? 0,
            'total_pending'    => $agg->total_pending ?? 0,
            'paid_count'       => $agg->paid_count ?? 0,
            'unpaid_count'     => $agg->unpaid_count ?? 0,
            'reservation_fees' => $agg->reservation_fees ?? 0,
        ];
    }

    public function clearFilters()
    {
        $this->reset(['search', 'statusFilter', 'methodFilter', 'fromDate', 'toDate', 'sortBy']);
        $this->resetPage();
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Transactions</span>
            </div>
            <h1 class="font-display text-3xl md:text-4xl font-semibold text-gray-900 dark:text-white">
                Payments <em class="italic text-primary-600 dark:text-primary-400">Overview</em>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Monitor all received and pending payments.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button wire:click="refreshSync"
                    wire:loading.attr="disabled"
                    class="btn-secondary text-xs sm:text-sm active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-2"
                    data-loading:opacity-50>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M4 9a9 9 0 0014.5 4.5M20 20v-5h-5M20 15a9 9 0 00-14.5-4.5"/></svg>
                <span wire:loading.remove wire:target="refreshSync">Sync PayMongo</span>
                <span wire:loading wire:target="refreshSync" class="inline-flex items-center gap-1">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Syncing…
                </span>
            </button>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md text-sm text-red-700 dark:text-red-300 font-medium flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Stats Cards --}}
    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Received</p>
                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">₱{{ number_format($s['total_received'], 2) }}</p>
        </div>
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Pending</p>
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-2">₱{{ number_format($s['total_pending'], 2) }}</p>
        </div>
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Paid Transactions</p>
                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $s['paid_count'] }}</p>
        </div>
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Reservation Fees</p>
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v10a2 2 0 002 2h14a2 2 0 002-2V7a2 2 0 00-2-2H5z"/></svg>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">₱{{ number_format($s['reservation_fees'], 2) }}</p>
        </div>
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Unpaid Count</p>
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </div>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $s['unpaid_count'] }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card p-4 space-y-3">
        <div class="flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search by reference, guest, or PayMongo ID…"
                       class="input pl-10">
            </div>

            <select wire:model.live="statusFilter" class="select w-full sm:w-auto">
                <option value="">All Status</option>
                <option value="paid">Paid</option>
                <option value="unpaid">Unpaid</option>
            </select>

            <select wire:model.live="methodFilter" class="select w-full sm:w-auto">
                <option value="">All Methods</option>
                <option value="cash">Cash</option>
                <option value="gcash">GCash</option>
                <option value="paymaya">Maya</option>
                <option value="card">Card</option>
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
                <option value="amount_high">Amount (High to Low)</option>
                <option value="amount_low">Amount (Low to High)</option>
            </select>

            @if($search || $statusFilter || $methodFilter || $fromDate || $toDate)
                <button wire:click="clearFilters"
                        class="btn-secondary text-xs active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Clear
                </button>
            @endif
        </div>
    </div>

    {{-- Payments Table --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto" wire:loading.class="opacity-50">
            <table class="w-full text-left">
                <thead class="border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Booking Ref</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase hidden sm:table-cell">Guest</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Method</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Type</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase hidden md:table-cell">Balance Due</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Status</th>
                        <th class="px-4 sm:px-6 py-4 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-200">
                    @forelse($this->payments as $payment)
                        @php
                            $booking = $payment->booking;
                            $totalPaid = $booking?->payments?->where('payment_status','paid')->sum('amount') ?? 0;
                            $balance = $booking ? $booking->total_amount - $totalPaid : 0;
                        @endphp
                        <tr wire:key="payment-row-{{ $payment->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 sm:px-6 py-4 font-mono text-sm">
                                @if($booking)
                                    <a href="{{ route('tenant.bookings.show', $booking->id) }}" wire:navigate
                                       class="text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 hover:underline focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95 transition-transform">
                                        {{ $booking->booking_reference }}
                                    </a>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">N/A</span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-sm hidden sm:table-cell">
                                {{ $booking?->user?->name ?? '—' }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-sm capitalize">
                                {{ str_replace('_', ' ', $payment->payment_method) }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-sm">
                                @if($payment->payment_type === 'reservation')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-500/30">Reservation Fee</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-600">Full Payment</span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                ₱{{ number_format($payment->amount, 2) }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 hidden md:table-cell">
                                @if($booking && $balance > 0)
                                    <span class="text-amber-600 dark:text-amber-400">₱{{ number_format($balance, 2) }}</span>
                                @elseif($booking)
                                    <span class="text-green-600 dark:text-green-400 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Settled
                                    </span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">—</span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $payment->payment_status === 'paid' ? 'bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-500/30' : 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30' }}">
                                    {{ ucfirst($payment->payment_status) }}
                                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-right text-sm whitespace-nowrap">
                                {{ $payment->paid_at ? $payment->paid_at->format('M d, Y h:i A') : $payment->created_at->format('M d, Y h:i A') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="text-sm">No payments found{{ $search ? ' matching "' . $search . '"' : '' }}.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->payments->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $this->payments->links() }}
            </div>
        @endif
    </div>
</div>