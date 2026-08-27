{{-- resources/views/tenant/pages/booking/show-booking.blade.php --}}
@extends('tenant.layouts.app')

@section('content')
<div x-data="{ confirmDelete: false }" class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <a href="{{ route('tenant.bookings.index') }}" wire:navigate class="text-sm text-gray-500 dark:text-gray-400 hover:text-[#376df1] dark:hover:text-blue-400 transition-colors">
                &larr; Back to Bookings
            </a>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mt-1 flex items-center gap-3">
                Booking #{{ $booking->booking_reference }}
                <button
                    @click="navigator.clipboard.writeText('{{ $booking->booking_reference }}').then(() => { const el = $event.target; el.textContent = '✓ Copied'; setTimeout(() => el.textContent = '⧉', 1500); })"
                    class="ml-2 p-1.5 rounded-lg text-gray-400 dark:text-gray-500 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                    title="Copy reference">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                </button>
            </h1>
            <p class="text-gray-600 dark:text-gray-300 mt-1">
                {{ $booking->user->name }} •
                {{ $booking->check_in->format('M d, Y') }} – {{ $booking->check_out->format('M d, Y') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @php
                $paidAmount = $booking->payments->where('payment_status', 'paid')->sum('amount');
                $balance = $booking->total_amount - $paidAmount;
            @endphp

            @livewire('tenant::pages.payment.quick-pay', ['booking' => $booking])

            @if($balance > 0)
                <a href="{{ route('tenant.payments.create', ['booking' => $booking->id]) }}" wire:navigate
                   class="px-5 py-2.5 rounded-full bg-[#376df1] hover:bg-blue-700 text-white text-sm font-semibold transition shadow-lg shadow-blue-500/20">
                    Record Payment
                </a>
            @endif

            <a href="{{ route('tenant.bookings.edit', $booking->id) }}" wire:navigate
               class="px-5 py-2.5 rounded-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 font-medium transition">
                Edit Booking
            </a>

            <button onclick="window.print()"
                    class="px-5 py-2.5 rounded-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 font-medium transition">
                Print
            </button>

            @if($booking->status !== 'completed' && $booking->status !== 'cancelled')
                <button @click="confirmDelete = true"
                        class="px-5 py-2.5 rounded-full bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-500/20 transition font-medium">
                    Delete
                </button>
            @endif
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    <div x-show="confirmDelete" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 max-w-md w-full mx-4 shadow-xl">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Delete Booking?</h3>
            <p class="text-sm text-gray-600 dark:text-gray-300 mb-6">Are you sure you want to delete booking #{{ $booking->booking_reference }}? This action cannot be undone.</p>
            <div class="flex justify-end gap-2">
                <button @click="confirmDelete = false" class="px-4 py-2 rounded-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">Cancel</button>
                <form action="{{ route('tenant.bookings.destroy', $booking->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 rounded-full bg-red-600 hover:bg-red-500 text-white font-semibold transition">Delete</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Status & Progress --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 sm:p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                    {{ $booking->status === 'pending' ? 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30' : '' }}
                    {{ $booking->status === 'reserved' ? 'bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-500/30' : '' }}
                    {{ $booking->status === 'confirmed' ? 'bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-500/30' : '' }}
                    {{ $booking->status === 'checked_in' ? 'bg-purple-100 dark:bg-purple-500/15 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-500/30' : '' }}
                    {{ $booking->status === 'completed' ? 'bg-slate-100 dark:bg-slate-500/15 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-500/30' : '' }}
                    {{ $booking->status === 'cancelled' ? 'bg-red-100 dark:bg-red-500/15 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-500/30' : '' }}">
                    {{ ucfirst(str_replace('_', ' ', $booking->status)) }}
                </span>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Type: {{ $booking->booking_type === 'reservation' ? 'Reservation (20% fee)' : 'Full Payment' }}
                </span>
            </div>

            @if($booking->status === 'pending')
                @php
                    $deadline = $booking->getPaymentDeadlineAttribute();
                    $minsLeft = $deadline ? now()->diffInMinutes($deadline, false) : 0;
                @endphp
                @if($minsLeft > 0)
                    <div class="text-xs text-amber-600 dark:text-amber-400">
                        Payment expires in: <strong>{{ floor($minsLeft) }}m {{ ($minsLeft*60)%60 }}s</strong>
                    </div>
                @else
                    <div class="text-xs text-red-600 dark:text-red-400">Payment overdue</div>
                @endif
            @endif
        </div>

        <div class="mt-4">
            <div class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500"
                     style="width: {{ $booking->total_amount > 0 ? min(100, ($paidAmount / $booking->total_amount) * 100) : 0 }}%;
                            background: {{ $balance <= 0 ? '#22c55e' : '#f59e0b' }};"></div>
            </div>
            <div class="flex justify-between text-xs mt-1">
                <span class="text-gray-500 dark:text-gray-400">Paid: ₱{{ number_format($paidAmount, 2) }}</span>
                <span class="{{ $balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                    Balance: {{ $balance > 0 ? '₱'.number_format($balance, 2) : 'Settled ✓' }}
                </span>
            </div>
        </div>

        @if($booking->booking_type === 'reservation')
            @php
                $reservationFee = round($booking->total_amount * 0.20, 2);
                $balanceOnArrival = $booking->total_amount - $reservationFee;
            @endphp
            <div class="grid grid-cols-2 gap-4 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Reservation Fee (20%)</p>
                    <p class="text-lg font-semibold text-[#376df1] dark:text-blue-400">₱{{ number_format($reservationFee, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Balance on Arrival</p>
                    <p class="text-lg font-semibold text-amber-600 dark:text-amber-400">₱{{ number_format($balanceOnArrival, 2) }}</p>
                </div>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left Column – Items, Services, Payments --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Booked Activities --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 sm:p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Activities</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="text-left py-2 pr-4">Activity</th>
                                <th class="text-center py-2 px-2">Price/Day</th>
                                <th class="text-center py-2 px-2">Days</th>
                                <th class="text-right py-2 pl-2">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 dark:text-gray-300">
                            @foreach($booking->items as $item)
                                @php
                                    $days = max(1, $booking->check_in->diffInDays($booking->check_out));
                                @endphp
                                <tr class="border-b border-gray-100 dark:border-gray-700">
                                    <td class="py-2 pr-4">{{ $item->property->name ?? 'Unknown' }}</td>
                                    <td class="py-2 px-2 text-center">₱{{ number_format($item->price, 2) }}</td>
                                    <td class="py-2 px-2 text-center">{{ $days }}</td>
                                    <td class="py-2 pl-2 text-right font-medium text-gray-900 dark:text-white">₱{{ number_format($item->subtotal, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Additional Services --}}
            @if($booking->services->count())
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 sm:p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Additional Services</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="text-left py-2 pr-4">Service</th>
                                    <th class="text-center py-2 px-2">Price</th>
                                    <th class="text-center py-2 px-2">Qty</th>
                                    <th class="text-right py-2 pl-2">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 dark:text-gray-300">
                                @foreach($booking->services as $service)
                                    <tr class="border-b border-gray-100 dark:border-gray-700">
                                        <td class="py-2 pr-4">{{ $service->service->name ?? 'Unknown' }}</td>
                                        <td class="py-2 px-2 text-center">₱{{ number_format($service->service->price ?? 0, 2) }}</td>
                                        <td class="py-2 px-2 text-center">{{ $service->quantity }}</td>
                                        <td class="py-2 pl-2 text-right font-medium text-gray-900 dark:text-white">₱{{ number_format($service->subtotal, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Payment History --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 sm:p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Payment History</h2>
                @if($booking->payments->count())
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="text-left py-2">Date</th>
                                    <th class="text-left py-2">Method</th>
                                    <th class="text-left py-2">Type</th>
                                    <th class="text-right py-2">Amount</th>
                                    <th class="text-right py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 dark:text-gray-300">
                                @foreach($booking->payments as $payment)
                                    <tr class="border-b border-gray-100 dark:border-gray-700">
                                        <td class="py-2">{{ $payment->paid_at?->format('M d, Y h:i A') ?? $payment->created_at->format('M d, Y h:i A') }}</td>
                                        <td class="py-2 capitalize">{{ str_replace('_', ' ', $payment->payment_method) }}</td>
                                        <td class="py-2">
                                            @if($payment->payment_type === 'reservation')
                                                <span class="text-xs text-[#376df1] dark:text-blue-400">Reservation Fee</span>
                                            @else
                                                <span class="text-xs text-gray-500 dark:text-gray-400">Full Payment</span>
                                            @endif
                                        </td>
                                        <td class="py-2 text-right font-medium text-gray-900 dark:text-white">₱{{ number_format($payment->amount, 2) }}</td>
                                        <td class="py-2 text-right">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                {{ $payment->payment_status === 'paid' ? 'bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-500/30' : 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30' }}">
                                                {{ ucfirst($payment->payment_status) }}
                                            </span>
                                        </td>
                                    </tr>
                                    @if($payment->reference_number || $payment->paymongo_session_id)
                                        <tr class="border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                                            <td colspan="5" class="py-1 px-2 text-xs text-gray-500 dark:text-gray-400">
                                                @if($payment->reference_number) Ref: {{ $payment->reference_number }} @endif
                                                @if($payment->paymongo_session_id) | PayMongo: {{ $payment->paymongo_session_id }} @endif
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-gray-500 dark:text-gray-400 text-sm">No payments recorded yet.</p>
                @endif
            </div>
        </div>

        {{-- Right Column – Summary & Guest --}}
        <div class="space-y-6">
            {{-- Summary Card --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 sm:p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Summary</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Total Amount</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">₱{{ number_format($booking->total_amount, 2) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Paid</dt>
                        <dd class="text-[#376df1] dark:text-blue-400 font-medium">₱{{ number_format($paidAmount, 2) }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-3">
                        <dt class="text-gray-500 dark:text-gray-400">Balance Due</dt>
                        <dd class="font-bold {{ $balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-[#376df1] dark:text-blue-400' }}">₱{{ number_format($balance, 2) }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Guest Info --}}
            @if($booking->user)
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 sm:p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Guest</h2>
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full bg-[#376df1] flex items-center justify-center text-white font-bold text-lg">
                            {{ strtoupper(substr($booking->user->name, 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-gray-900 dark:text-white font-medium">{{ $booking->user->name }}</p>
                            @if($booking->user->phone)<p class="text-gray-600 dark:text-gray-400 text-sm">{{ $booking->user->phone }}</p>@endif
                            @if($booking->user->email)<p class="text-gray-600 dark:text-gray-400 text-sm">{{ $booking->user->email }}</p>@endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Booking Reference & Dates --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 sm:p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Details</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Check-in</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $booking->check_in->format('M d, Y') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Check-out</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $booking->check_out->format('M d, Y') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Days</dt>
                        <dd class="text-gray-900 dark:text-white">{{ max(1, $booking->check_in->diffInDays($booking->check_out)) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Booking Type</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $booking->booking_type === 'reservation' ? 'Reservation' : 'Full Payment' }}</dd>
                    </div>
                    @if($booking->created_at)
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Created</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $booking->created_at->format('M d, Y h:i A') }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    @media print {
        .bg-white, .bg-white\/5, .bg-white\/10, .bg-white\/\[0\.02\] {
            background: #f8f8f8 !important;
        }
        .text-gray-900, .text-gray-700, .text-gray-600, .text-gray-500, .text-gray-400 {
            color: #333 !important;
        }
        button, a, .no-print {
            display: none !important;
        }
    }
</style>
@endpush
@endsection