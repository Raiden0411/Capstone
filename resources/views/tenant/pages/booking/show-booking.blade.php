{{-- resources/views/tenant/pages/booking/show-booking.blade.php --}}
@extends('tenant.layouts.app')

@php
    // Calculate balances and common variables at the top for cleaner HTML
    $paidAmount = $booking->payments->where('payment_status', 'paid')->sum('amount');
    $balance = max(0, $booking->total_amount - $paidAmount);
    $days = max(1, $booking->check_in->diffInDays($booking->check_out));
    $isReservation = $booking->booking_type === 'reservation';
    $isSettled = $balance <= 0;

    // Status color mapping for cleaner badges
    $statusColors = [
        'pending' => 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/30',
        'reserved' => 'bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-500/30',
        'confirmed' => 'bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 border-green-200 dark:border-green-500/30',
        'checked_in' => 'bg-purple-100 dark:bg-purple-500/15 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-500/30',
        'completed' => 'bg-slate-100 dark:bg-slate-500/15 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-500/30',
        'cancelled' => 'bg-red-100 dark:bg-red-500/15 text-red-700 dark:text-red-300 border-red-200 dark:border-red-500/30',
    ];
    $statusClass = $statusColors[$booking->status] ?? 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300';
@endphp

@section('content')
<div x-data="{ confirmDelete: false }" class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <a href="{{ route('tenant.bookings.index') }}" wire:navigate class="no-print text-sm text-gray-500 dark:text-gray-400 hover:text-[#376df1] dark:hover:text-blue-400 transition-colors">
                &larr; Back to Bookings
            </a>
            <div class="flex items-center gap-3 mt-1">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
                    Booking #{{ $booking->booking_reference }}
                </h1>
                
                {{-- Refactored Alpine Copy Button --}}
                <div x-data="{ copied: false }" class="no-print">
                    <button 
                        @click="navigator.clipboard.writeText('{{ $booking->booking_reference }}').then(() => { copied = true; setTimeout(() => copied = false, 2000); })"
                        class="p-1.5 rounded-lg text-gray-400 dark:text-gray-500 hover:text-[#376df1] hover:bg-blue-50 dark:hover:bg-gray-700 transition"
                        title="Copy reference">
                        
                        {{-- Copy Icon --}}
                        <svg x-show="!copied" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        
                        {{-- Check Icon --}}
                        <svg x-show="copied" x-cloak class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </button>
                </div>
            </div>
            <p class="text-gray-600 dark:text-gray-300 mt-1">
                {{ $booking->user->name ?? 'Guest User' }} • 
                {{ $booking->check_in->format('M d, Y') }} – {{ $booking->check_out->format('M d, Y') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2 no-print">
            @livewire('tenant::pages.payment.quick-pay', ['booking' => $booking])

            @if(!$isSettled)
                <a href="{{ route('tenant.payments.create', ['booking' => $booking->id]) }}" wire:navigate
                   class="px-5 py-2.5 rounded-full bg-[#376df1] hover:bg-blue-700 text-white text-sm font-semibold transition shadow-lg shadow-blue-500/20">
                    Record Payment
                </a>
            @endif

            <a href="{{ route('tenant.bookings.edit', $booking->id) }}" wire:navigate
               class="px-5 py-2.5 rounded-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 font-medium transition text-sm">
                Edit Booking
            </a>

            <button onclick="window.print()"
                    class="px-5 py-2.5 rounded-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 font-medium transition text-sm">
                Print
            </button>

            @if(!in_array($booking->status, ['completed', 'cancelled']))
                <button @click="confirmDelete = true"
                        class="px-5 py-2.5 rounded-full bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-500/20 transition font-medium text-sm">
                    Delete
                </button>
            @endif
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    <div x-show="confirmDelete" 
         x-cloak 
         x-transition.opacity 
         @keydown.escape.window="confirmDelete = false"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm no-print">
        <div @click.outside="confirmDelete = false" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 max-w-md w-full mx-4 shadow-xl">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Delete Booking?</h3>
            <p class="text-sm text-gray-600 dark:text-gray-300 mb-6">Are you sure you want to delete booking <strong>#{{ $booking->booking_reference }}</strong>? This action cannot be undone.</p>
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
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border {{ $statusClass }}">
                    {{ ucfirst(str_replace('_', ' ', $booking->status)) }}
                </span>
                <span class="text-sm text-gray-500 dark:text-gray-400 border-l border-gray-300 dark:border-gray-600 pl-3">
                    {{ $isReservation ? 'Reservation (20% fee)' : 'Full Payment' }}
                </span>
            </div>

            @if($booking->status === 'pending')
                @php
                    $deadline = $booking->getPaymentDeadlineAttribute();
                    $minsLeft = $deadline ? now()->diffInMinutes($deadline, false) : 0;
                @endphp
                @if($minsLeft > 0)
                    <div class="text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-3 py-1.5 rounded-lg border border-amber-100 dark:border-amber-800">
                        Payment expires in: <strong>{{ floor($minsLeft) }}m {{ ($minsLeft*60)%60 }}s</strong>
                    </div>
                @else
                    <div class="text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30 px-3 py-1.5 rounded-lg border border-red-100 dark:border-red-800">
                        Payment overdue
                    </div>
                @endif
            @endif
        </div>

        <div class="mt-5">
            <div class="w-full h-2.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden shadow-inner">
                <div class="h-full rounded-full transition-all duration-500"
                     style="width: {{ $booking->total_amount > 0 ? min(100, ($paidAmount / $booking->total_amount) * 100) : 0 }}%;
                            background: {{ $isSettled ? '#22c55e' : '#376df1' }};"></div>
            </div>
            <div class="flex justify-between text-sm mt-2 font-medium">
                <span class="text-gray-600 dark:text-gray-400">Paid: ₱{{ number_format($paidAmount, 2) }}</span>
                <span class="{{ $isSettled ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ $isSettled ? 'Settled ✓' : 'Balance: ₱'.number_format($balance, 2) }}
                </span>
            </div>
        </div>

        @if($isReservation)
            @php
                $reservationFee = round($booking->total_amount * 0.20, 2);
                $balanceOnArrival = max(0, $booking->total_amount - $reservationFee);
            @endphp
            <div class="grid grid-cols-2 gap-4 mt-5 pt-5 border-t border-gray-200 dark:border-gray-700">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reservation Fee (20%)</p>
                    <p class="text-lg font-semibold text-[#376df1] dark:text-blue-400 mt-1">₱{{ number_format($reservationFee, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Balance on Arrival</p>
                    <p class="text-lg font-semibold text-amber-600 dark:text-amber-400 mt-1">₱{{ number_format($balanceOnArrival, 2) }}</p>
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
                    <table class="w-full text-sm whitespace-nowrap">
                        <thead class="border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="text-left py-3 pr-4 font-medium">Activity</th>
                                <th class="text-center py-3 px-2 font-medium">Price/Day</th>
                                <th class="text-center py-3 px-2 font-medium">Days</th>
                                <th class="text-right py-3 pl-2 font-medium">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 dark:text-gray-300">
                            @foreach($booking->items as $item)
                                <tr class="border-b border-gray-100 dark:border-gray-700/50 last:border-0">
                                    <td class="py-3 pr-4">{{ $item->property->name ?? 'Unknown Property' }}</td>
                                    <td class="py-3 px-2 text-center">₱{{ number_format($item->price, 2) }}</td>
                                    <td class="py-3 px-2 text-center">{{ $days }}</td>
                                    <td class="py-3 pl-2 text-right font-medium text-gray-900 dark:text-white">₱{{ number_format($item->subtotal, 2) }}</td>
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
                        <table class="w-full text-sm whitespace-nowrap">
                            <thead class="border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="text-left py-3 pr-4 font-medium">Service</th>
                                    <th class="text-center py-3 px-2 font-medium">Price</th>
                                    <th class="text-center py-3 px-2 font-medium">Qty</th>
                                    <th class="text-right py-3 pl-2 font-medium">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 dark:text-gray-300">
                                @foreach($booking->services as $service)
                                    <tr class="border-b border-gray-100 dark:border-gray-700/50 last:border-0">
                                        <td class="py-3 pr-4">{{ $service->service->name ?? 'Unknown Service' }}</td>
                                        <td class="py-3 px-2 text-center">₱{{ number_format($service->service->price ?? 0, 2) }}</td>
                                        <td class="py-3 px-2 text-center">{{ $service->quantity }}</td>
                                        <td class="py-3 pl-2 text-right font-medium text-gray-900 dark:text-white">₱{{ number_format($service->subtotal, 2) }}</td>
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
                        <table class="w-full text-sm whitespace-nowrap">
                            <thead class="border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="text-left py-3 font-medium">Date</th>
                                    <th class="text-left py-3 font-medium">Method</th>
                                    <th class="text-left py-3 font-medium">Type</th>
                                    <th class="text-right py-3 font-medium">Amount</th>
                                    <th class="text-right py-3 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 dark:text-gray-300">
                                @foreach($booking->payments as $payment)
                                    <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                        <td class="py-3">{{ $payment->paid_at?->format('M d, Y h:i A') ?? $payment->created_at->format('M d, Y h:i A') }}</td>
                                        <td class="py-3 capitalize">{{ str_replace('_', ' ', $payment->payment_method) }}</td>
                                        <td class="py-3">
                                            @if($payment->payment_type === 'reservation')
                                                <span class="text-xs text-[#376df1] dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-2 py-1 rounded">Reservation Fee</span>
                                            @else
                                                <span class="text-xs text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">Full Payment</span>
                                            @endif
                                        </td>
                                        <td class="py-3 text-right font-medium text-gray-900 dark:text-white">₱{{ number_format($payment->amount, 2) }}</td>
                                        <td class="py-3 text-right">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border
                                                {{ $payment->payment_status === 'paid' ? 'bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 border-green-200 dark:border-green-500/30' : 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/30' }}">
                                                {{ ucfirst($payment->payment_status) }}
                                            </span>
                                        </td>
                                    </tr>
                                    @if($payment->reference_number || $payment->paymongo_session_id)
                                        <tr class="border-b border-gray-100 dark:border-gray-700/50 bg-gray-50/50 dark:bg-gray-800/50">
                                            <td colspan="5" class="py-2 px-3 text-xs text-gray-500 dark:text-gray-400">
                                                @if($payment->reference_number) <span class="mr-4"><strong>Ref:</strong> {{ $payment->reference_number }}</span> @endif
                                                @if($payment->paymongo_session_id) <span><strong>PayMongo:</strong> {{ $payment->paymongo_session_id }}</span> @endif
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-6 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-lg">
                        <p class="text-gray-500 dark:text-gray-400 text-sm">No payments recorded yet.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Right Column – Summary & Guest --}}
        <div class="space-y-6">
            {{-- Summary Card --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 sm:p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Summary</h2>
                <dl class="space-y-4 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Total Amount</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">₱{{ number_format($booking->total_amount, 2) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Paid Amount</dt>
                        <dd class="text-[#376df1] dark:text-blue-400 font-medium">₱{{ number_format($paidAmount, 2) }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-4">
                        <dt class="text-gray-700 dark:text-gray-300 font-semibold">Balance Due</dt>
                        <dd class="font-bold text-base {{ $isSettled ? 'text-[#376df1] dark:text-blue-400' : 'text-red-600 dark:text-red-400' }}">
                            ₱{{ number_format($balance, 2) }}
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Guest Info --}}
            @if($booking->user)
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 sm:p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Guest Info</h2>
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-[#376df1]/10 border border-[#376df1]/20 flex items-center justify-center text-[#376df1] font-bold text-lg shrink-0">
                            {{ strtoupper(substr($booking->user->name, 0, 1)) }}
                        </div>
                        <div class="overflow-hidden">
                            <p class="text-gray-900 dark:text-white font-medium truncate">{{ $booking->user->name }}</p>
                            @if($booking->user->phone)<p class="text-gray-600 dark:text-gray-400 text-sm truncate mt-0.5">{{ $booking->user->phone }}</p>@endif
                            @if($booking->user->email)<p class="text-gray-600 dark:text-gray-400 text-sm truncate mt-0.5">{{ $booking->user->email }}</p>@endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Booking Reference & Dates --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 sm:p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">Details</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between items-center">
                        <dt class="text-gray-500 dark:text-gray-400">Check-in</dt>
                        <dd class="text-gray-900 dark:text-white font-medium">{{ $booking->check_in->format('M d, Y') }}</dd>
                    </div>
                    <div class="flex justify-between items-center">
                        <dt class="text-gray-500 dark:text-gray-400">Check-out</dt>
                        <dd class="text-gray-900 dark:text-white font-medium">{{ $booking->check_out->format('M d, Y') }}</dd>
                    </div>
                    <div class="flex justify-between items-center">
                        <dt class="text-gray-500 dark:text-gray-400">Duration</dt>
                        <dd class="text-gray-900 dark:text-white font-medium">{{ $days }} {{ Str::plural('Night', $days) }}</dd>
                    </div>
                    <div class="flex justify-between items-center">
                        <dt class="text-gray-500 dark:text-gray-400">Booking Type</dt>
                        <dd class="text-gray-900 dark:text-white font-medium">{{ $isReservation ? 'Reservation' : 'Full Payment' }}</dd>
                    </div>
                    @if($booking->created_at)
                    <div class="flex justify-between items-center pt-3 border-t border-gray-100 dark:border-gray-700">
                        <dt class="text-gray-500 dark:text-gray-400">Created At</dt>
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
    /* Prevent Alpine unstyled flash */
    [x-cloak] { display: none !important; }
    
    @media print {
        @page { margin: 1cm; }
        body { 
            background: white !important; 
            color: black !important;
        }
        .bg-white, .dark\:bg-gray-800 {
            background: white !important;
            border-color: #e5e7eb !important;
            box-shadow: none !important;
        }
        .text-gray-900, .text-gray-700, .text-gray-600, .text-gray-500, .text-gray-400,
        .dark\:text-white, .dark\:text-gray-200, .dark\:text-gray-300, .dark\:text-gray-400 {
            color: #111827 !important;
        }
        /* Ensure specific items explicitly don't print */
        button, a, .no-print, [x-show="confirmDelete"] {
            display: none !important;
        }
        /* Make borders strictly visible in print */
        table { border-collapse: collapse !important; }
        tr { border-bottom: 1px solid #e5e7eb !important; }
    }
</style>
@endpush
@endsection