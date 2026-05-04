<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;

new 
#[Layout('tenant.layouts.app')]
#[Title('Payments')]
class extends Component {
    use WithPagination;

    public string $search = '';

    public function updatingSearch() { $this->resetPage(); }

    #[Computed]
    public function payments()
    {
        return Payment::with('booking.customer')
            ->where('tenant_id', Auth::user()->tenant_id)
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('reference_number', 'like', '%' . $this->search . '%')
                      ->orWhereHas('booking', function ($bq) {
                          $bq->where('booking_reference', 'like', '%' . $this->search . '%')
                             ->orWhereHas('customer', fn($cq) => $cq->where('name', 'like', '%' . $this->search . '%'));
                      });
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto text-gray-900 dark:text-white space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold">Payments</h1>
            <p class="text-gray-500 dark:text-slate-400 mt-1">All recorded payments across your bookings.</p>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="p-4 bg-green-50 dark:bg-green-500/10 border-l-4 border-green-500 rounded-md shadow-sm">
            <p class="text-sm text-green-700 dark:text-green-400 font-medium">{{ session('message') }}</p>
        </div>
    @endif

    {{-- Search --}}
    <div class="relative w-full md:w-1/3">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by ref, customer or reference..." 
               class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 rounded-lg focus:ring-blue-500 focus:border-blue-500 shadow-sm">
        <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
    </div>

    {{-- Payments Table --}}
    <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-gray-50 dark:bg-slate-800/50 border-b border-gray-200 dark:border-slate-700/50">
                    <tr>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Booking Ref</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase hidden sm:table-cell">Customer</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Method</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Amount</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase hidden md:table-cell">Status</th>
                        <th class="px-4 sm:px-6 py-4 text-right text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-slate-700/30 text-gray-700 dark:text-slate-300">
                    @forelse($this->payments as $payment)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-4 sm:px-6 py-4 font-mono text-sm">
                                @if($payment->booking)
                                    <a href="{{ route('tenant.bookings.show', $payment->booking->id) }}" wire:navigate class="text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ $payment->booking->booking_reference }}
                                    </a>
                                @else
                                    <span class="text-gray-500 dark:text-slate-500">N/A</span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-sm hidden sm:table-cell">
                                {{ $payment->booking->customer->name ?? '—' }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-sm capitalize">
                                {{ str_replace('_', ' ', $payment->payment_method) }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-sm font-medium">
                                ₱{{ number_format($payment->amount, 2) }}
                            </td>
                            <td class="px-4 sm:px-6 py-4 hidden md:table-cell">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $payment->payment_status === 'paid' ? 'bg-green-100 dark:bg-green-500/20 text-green-800 dark:text-green-400' : 'bg-yellow-100 dark:bg-yellow-500/20 text-yellow-800 dark:text-yellow-400' }}">
                                    {{ ucfirst($payment->payment_status) }}
                                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-right text-sm whitespace-nowrap">
                                {{ $payment->paid_at ? $payment->paid_at->format('M d, Y h:i A') : $payment->created_at->format('M d, Y h:i A') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-slate-400">
                                <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-slate-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="text-sm">No payments found{{ $search ? ' matching "' . $search . '"' : '' }}.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->payments->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-slate-700/50 bg-gray-50 dark:bg-slate-800/50">
                {{ $this->payments->links() }}
            </div>
        @endif
    </div>
</div>