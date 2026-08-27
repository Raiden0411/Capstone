{{-- resources/views/tenant/pages/payment/⚡view-payment.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Booking;
use App\Jobs\ProcessPayMongoPayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

new 
#[Layout('tenant.layouts.app')]
#[Title('Payments')]
class extends Component {
    use WithPagination;

    public string $search        = '';
    public string $statusFilter  = '';
    public string $methodFilter  = '';
    public ?string $fromDate     = null;
    public ?string $toDate       = null;
    public string $sortBy        = 'newest';

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

    public function syncUnpaidPayments(int $limit = 20)
    {
        $payments = Payment::where('tenant_id', Auth::user()->tenant_id)
            ->where('payment_status', 'unpaid')
            ->whereNotNull('paymongo_session_id')
            ->latest()
            ->limit($limit)
            ->get();

        foreach ($payments as $payment) {
            try {
                ProcessPayMongoPayment::dispatchSync($payment->paymongo_session_id);
            } catch (\Exception $e) {
                Log::error('Failed to dispatch PayMongo sync: ' . $e->getMessage());
            }
        }
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
        return Payment::with('booking.user')
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
                switch ($this->sortBy) {
                    case 'newest': $q->latest(); break;
                    case 'oldest': $q->oldest(); break;
                    case 'amount_high': $q->orderByDesc('amount'); break;
                    case 'amount_low': $q->orderBy('amount'); break;
                    default: $q->latest();
                }
            })
            ->paginate(15);
    }

    #[Computed]
    public function stats()
    {
        $tid = Auth::user()->tenant_id;

        return [
            'total_received'   => Payment::where('tenant_id', $tid)->where('payment_status', 'paid')->sum('amount'),
            'total_pending'    => Payment::where('tenant_id', $tid)->where('payment_status', 'unpaid')->sum('amount'),
            'paid_count'       => Payment::where('tenant_id', $tid)->where('payment_status', 'paid')->count(),
            'unpaid_count'     => Payment::where('tenant_id', $tid)->where('payment_status', 'unpaid')->count(),
            'reservation_fees' => Payment::where('tenant_id', $tid)->where('payment_type', Payment::TYPE_RESERVATION)->where('payment_status', 'paid')->sum('amount'),
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
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Payments</h1>
        </div>
        <div class="flex flex-wrap gap-2">
            <button wire:click="refreshSync"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition disabled:opacity-50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M4 9a9 9 0 0014.5 4.5M20 20v-5h-5M20 15a9 9 0 00-14.5-4.5"/></svg>
                <span wire:loading.remove>Sync PayMongo</span>
                <span wire:loading>Syncing…</span>
            </button>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium">
            ✔ {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md text-sm text-red-700 dark:text-red-300 font-medium">
            ✖ {{ session('error') }}
        </div>
    @endif

    {{-- Stats --}}
    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Received</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">₱{{ number_format($s['total_received'], 2) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Pending</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-2">₱{{ number_format($s['total_pending'], 2) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Paid Transactions</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $s['paid_count'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Reservation Fees</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">₱{{ number_format($s['reservation_fees'], 2) }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-4 shadow-sm space-y-3">
        <div class="flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search by reference, guest, or PayMongo ID…"
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 pl-10 pr-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition">
            </div>

            <select wire:model.live="statusFilter"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[#376df1]/50 transition">
                <option value="">All Status</option>
                <option value="paid">Paid</option>
                <option value="unpaid">Unpaid</option>
            </select>

            <select wire:model.live="methodFilter"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[#376df1]/50 transition">
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
                <input type="date" wire:model.live="fromDate"
                       class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2 px-3 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[#376df1]/50 transition">
                <span class="text-xs text-gray-500 dark:text-gray-400">To:</span>
                <input type="date" wire:model.live="toDate"
                       class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2 px-3 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[#376df1]/50 transition">
            </div>

            <select wire:model.live="sortBy"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[#376df1]/50 transition">
                <option value="newest">Newest First</option>
                <option value="oldest">Oldest First</option>
                <option value="amount_high">Amount (High to Low)</option>
                <option value="amount_low">Amount (Low to High)</option>
            </select>

            @if($search || $statusFilter || $methodFilter || $fromDate || $toDate)
                <button wire:click="clearFilters"
                        class="px-4 py-2 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 text-xs font-semibold uppercase tracking-wider transition">
                    ✕ Clear
                </button>
            @endif
        </div>
    </div>

    {{-- Payments Table --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
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
                            $balance = $booking?->total_amount - $totalPaid ?? 0;
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 sm:px-6 py-4 font-mono text-sm">
                                @if($booking)
                                    <a href="{{ route('tenant.bookings.show', $booking->id) }}" wire:navigate class="text-[#376df1] dark:text-blue-400 hover:text-blue-700 hover:underline">
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
                                    <span class="text-green-600 dark:text-green-400">Settled ✓</span>
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