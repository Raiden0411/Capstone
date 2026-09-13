{{-- resources/views/public/pages/booking-receipt.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      class="{{ session('theme', 'light') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Receipt · {{ $booking->booking_reference }}</title>

    @vite(['resources/css/app.css'])

    <style>
        @media print {
            @page { margin: 1cm; size: auto; }
            html, body { background: #fff !important; }
            .no-print { display: none !important; }
            .receipt-card {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                background: #fff !important;
            }
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-950 font-sans antialiased">

    <div class="max-w-2xl mx-auto my-6 sm:my-8 px-4 sm:px-0">
        <div class="receipt-card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm p-6 sm:p-8">

            {{-- Header --}}
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">
                            Official Receipt
                        </span>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Receipt</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Booking Reference:
                        <span class="font-mono font-medium text-gray-900 dark:text-white">{{ $booking->booking_reference }}</span>
                    </p>
                </div>

                <div class="flex gap-2 no-print">
                    <a href="{{ route('my-bookings') }}"
                       class="btn btn-outline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-500/50 active:scale-95">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Back
                    </a>
                    <button type="button" onclick="window.print()"
                            class="btn btn-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-6-4h.01M6 18v4h12v-4"/>
                        </svg>
                        Print
                    </button>
                </div>
            </div>

            {{-- Business info --}}
            <div class="flex items-start gap-3">
                <div class="w-12 h-12 rounded-lg bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 flex items-center justify-center font-bold text-base shrink-0">
                    {{ strtoupper(substr($tenant?->name ?? 'B', 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="font-semibold text-gray-900 dark:text-white text-lg leading-tight">
                        {{ $property?->name ?? 'Booking' }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $tenant?->name ?? 'Business' }}</p>

                    @if($tenant && ($tenant->address || $tenant->contact_number || $tenant->email))
                        <div class="mt-1.5 text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                            @if($tenant->address)
                                <p>{{ $tenant->address }}{{ $tenant->barangay ? ', ' . $tenant->barangay : '' }}</p>
                            @endif
                            <p>
                                @if($tenant->contact_number){{ $tenant->contact_number }}@endif
                                @if($tenant->contact_number && $tenant->email) · @endif
                                @if($tenant->email){{ $tenant->email }}@endif
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Guest + booking summary --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 text-sm bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-[10px] uppercase tracking-wider">Guest</p>
                    <p class="font-semibold text-gray-900 dark:text-white mt-1 truncate">
                        {{ $booking->user?->name ?? 'Guest' }}
                    </p>
                    @if($booking->user?->phone)
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">{{ $booking->user->phone }}</p>
                    @endif
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-[10px] uppercase tracking-wider">Booking Type</p>
                    <p class="font-semibold text-gray-900 dark:text-white mt-1">
                        {{ $booking->booking_type === \App\Models\Booking::TYPE_RESERVATION ? 'Reservation (20%)' : 'Full Payment' }}
                    </p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-[10px] uppercase tracking-wider">Check-in</p>
                    <p class="font-semibold text-gray-900 dark:text-white mt-1">
                        {{ $booking->check_in->format('M d, Y') }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ $booking->check_in->format('h:i A') }}
                    </p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-[10px] uppercase tracking-wider">Check-out</p>
                    <p class="font-semibold text-gray-900 dark:text-white mt-1">
                        {{ $booking->check_out->format('M d, Y') }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ $booking->check_out->format('h:i A') }}
                    </p>
                </div>
            </div>

            @php
                $days = max(1, (int) $booking->check_in->diffInDays($booking->check_out));
            @endphp

            {{-- Booked activities --}}
            @if($booking->items->isNotEmpty())
                <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h3 class="font-semibold text-sm text-gray-900 dark:text-white mb-3">Booked Activities</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-gray-500 dark:text-gray-400 text-[10px] uppercase tracking-wider">
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="text-left pb-2 font-semibold">Activity</th>
                                    <th class="text-center pb-2 font-semibold">Price/Day</th>
                                    <th class="text-center pb-2 font-semibold">Days</th>
                                    <th class="text-center pb-2 font-semibold">Qty</th>
                                    <th class="text-right pb-2 font-semibold">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($booking->items as $item)
                                    <tr class="border-b border-gray-100 dark:border-gray-700 last:border-0">
                                        <td class="py-2 text-gray-700 dark:text-gray-300">
                                            {{ $item->property?->name ?? 'Unknown Activity' }}
                                        </td>
                                        <td class="py-2 text-center text-gray-700 dark:text-gray-300 tabular-nums">
                                            ₱{{ number_format((float) $item->price, 2) }}
                                        </td>
                                        <td class="py-2 text-center text-gray-700 dark:text-gray-300">{{ $days }}</td>
                                        <td class="py-2 text-center text-gray-700 dark:text-gray-300">{{ $item->quantity }}</td>
                                        <td class="py-2 text-right text-gray-900 dark:text-white font-medium tabular-nums">
                                            ₱{{ number_format((float) $item->subtotal, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Extra services --}}
            @if($booking->services->isNotEmpty())
                <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h3 class="font-semibold text-sm text-gray-900 dark:text-white mb-3">Extra Services</h3>
                    @foreach($booking->services as $service)
                        <div class="flex justify-between text-sm py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                            <span class="text-gray-700 dark:text-gray-300">
                                {{ $service->service?->name ?? 'Service' }} ×{{ $service->quantity }}
                            </span>
                            <span class="text-gray-900 dark:text-white font-medium tabular-nums">
                                ₱{{ number_format((float) $service->subtotal, 2) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Financial summary --}}
            @php
                $paid     = (float) $booking->payments->where('payment_status', 'paid')->sum('amount');
                $balance  = max(0, (float) $booking->total_amount - $paid);
                $isSettled = $balance <= 0;
            @endphp
            <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Total Amount</span>
                        <span class="font-semibold text-gray-900 dark:text-white tabular-nums">
                            ₱{{ number_format((float) $booking->total_amount, 2) }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Amount Paid</span>
                        <span class="font-semibold text-emerald-600 dark:text-emerald-400 tabular-nums">
                            ₱{{ number_format($paid, 2) }}
                        </span>
                    </div>
                    <div class="flex justify-between pt-2 mt-2 border-t border-gray-200 dark:border-gray-700">
                        <span class="font-semibold text-gray-700 dark:text-gray-200">Balance Due</span>
                        <span class="font-bold tabular-nums {{ $isSettled ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                            ₱{{ number_format($balance, 2) }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Payment history --}}
            <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                <h3 class="font-semibold text-sm text-gray-900 dark:text-white mb-3">Payment History</h3>
                @forelse($booking->payments as $payment)
                    <div class="py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between text-sm">
                            <span class="text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                @if($payment->payment_method === 'gcash')
                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v20M2 12h20"/>
                                    </svg>
                                @elseif($payment->payment_method === 'paymaya')
                                    <svg class="w-4 h-4 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2l2 4h4l-3 4 3 4h-4l-2 4-2-4H4l3-4-3-4h4z"/>
                                    </svg>
                                @else
                                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <rect x="2" y="5" width="20" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
                                        <line x1="2" y1="10" x2="22" y2="10" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                @endif
                                {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}
                                <span class="text-gray-400">·</span>
                                {{ ucfirst($payment->payment_type) }}
                                @if($payment->paid_at)
                                    <span class="text-gray-400 dark:text-gray-500 text-xs">· {{ $payment->paid_at->format('M d, Y h:i A') }}</span>
                                @endif
                            </span>
                            <span class="text-gray-900 dark:text-white font-medium tabular-nums">
                                ₱{{ number_format((float) $payment->amount, 2) }}
                                <span class="text-xs ml-2
                                    {{ $payment->payment_status === 'paid'
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-amber-600 dark:text-amber-400' }}">
                                    {{ ucfirst($payment->payment_status) }}
                                </span>
                            </span>
                        </div>
                        @if($payment->reference_number)
                            <p class="text-[10px] text-gray-400 dark:text-gray-500 font-mono mt-0.5 pl-6">
                                Ref: {{ $payment->reference_number }}
                            </p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-400 dark:text-gray-500 italic">No payments recorded.</p>
                @endforelse
            </div>

            {{-- Footer --}}
            <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                <p class="text-center text-xs text-gray-500 dark:text-gray-400">
                    Thank you for booking with us!
                </p>
                <p class="text-center text-[10px] text-gray-400 dark:text-gray-500 mt-1">
                    Generated {{ now()->format('M d, Y h:i A') }}
                </p>
            </div>
        </div>
    </div>

    {{-- Auto-print when ?print=1 --}}
    <script>
        window.addEventListener('load', function () {
            const params = new URLSearchParams(window.location.search);
            if (params.get('print') === '1') {
                setTimeout(function () { window.print(); }, 400);
            }
        });
    </script>
</body>
</html>