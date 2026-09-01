<!DOCTYPE html>
<html lang="en" class="{{ session('theme', 'light') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #{{ $booking->booking_reference }}</title>

    {{-- Use app compiled assets if available; otherwise fallback to minimal inline styles --}}
    @if(file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css'])
    @else
        <style>
            body { font-family: system-ui, sans-serif; background: #f8f7f3; margin: 0; padding: 1rem; }
            .receipt-card { max-width: 672px; margin: 2rem auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 1.5rem; padding: 2rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
            .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; transition: all 0.2s; cursor: pointer; }
            .btn-outline { border: 1px solid #d1d5db; color: #4b5563; background: #fff; }
            .btn-outline:hover { background: #f9fafb; }
            .btn-primary { background: #376df1; color: #fff; }
            .btn-primary:hover { background: #1d4ed8; }
            @media print { .no-print { display: none !important; } body { background: white; } .receipt-card { box-shadow: none; border: none; } }
        </style>
    @endif
</head>
<body class="bg-[#F8F7F3] dark:bg-gray-950 font-sans antialiased">

    <div class="receipt-card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm p-6 sm:p-8 max-w-2xl mx-auto my-8">

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b border-gray-200 dark:border-gray-700">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Receipt</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Booking Reference:
                    <span class="font-mono font-medium text-gray-900 dark:text-white">{{ $booking->booking_reference }}</span>
                </p>
            </div>
            <div class="flex gap-2 no-print">
                <a href="{{ route('my-bookings') }}" wire:navigate
                   class="btn btn-outline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-500/50 active:scale-95">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Back
                </a>
                <button onclick="window.print()"
                        class="btn btn-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Print
                </button>
            </div>
        </div>

        {{-- Business / Activity Info --}}
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-500/10 text-primary-600 dark:text-primary-400 flex items-center justify-center font-bold text-sm shrink-0">
                {{ strtoupper(substr($tenant?->name ?? 'B', 0, 1)) }}
            </div>
            <div>
                <h2 class="font-semibold text-gray-900 dark:text-white text-lg leading-tight">
                    {{ $property?->name ?? 'Booking' }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $tenant?->name ?? 'Business' }}</p>
            </div>
        </div>

        {{-- Booking Summary --}}
        <div class="grid grid-cols-2 gap-4 mt-6 text-sm bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
            <div>
                <p class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider">Check-in</p>
                <p class="font-semibold text-gray-900 dark:text-white mt-1">{{ \Carbon\Carbon::parse($booking->check_in)->format('M d, Y') }}</p>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider">Check-out</p>
                <p class="font-semibold text-gray-900 dark:text-white mt-1">{{ \Carbon\Carbon::parse($booking->check_out)->format('M d, Y') }}</p>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider">Total Amount</p>
                <p class="font-semibold text-gray-900 dark:text-white mt-1">₱{{ number_format($booking->total_amount, 2) }}</p>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider">Status</p>
                <p class="font-medium mt-1 capitalize text-gray-900 dark:text-white">{{ $booking->status }}</p>
            </div>
        </div>

        {{-- Payment History --}}
        <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
            <h3 class="font-semibold text-sm text-gray-900 dark:text-white mb-3">Payment History</h3>
            @forelse($booking->payments as $payment)
                <div class="flex flex-col sm:flex-row sm:items-center justify-between text-sm py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                    <span class="text-gray-700 dark:text-gray-300 flex items-center gap-2">
                        @if($payment->payment_method === 'gcash')
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v20M2 12h20"/></svg>
                        @elseif($payment->payment_method === 'paymaya')
                            <svg class="w-4 h-4 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2l2 4h4l-3 4 3 4h-4l-2 4-2-4H4l3-4-3-4h4z"/></svg>
                        @else
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2" stroke="currentColor" stroke-width="2"/><line x1="2" y1="10" x2="22" y2="10" stroke="currentColor" stroke-width="2"/></svg>
                        @endif
                        {{ ucfirst($payment->payment_method) }}
                        <span class="text-gray-400">·</span>
                        {{ ucfirst($payment->payment_type) }}
                    </span>
                    <span class="text-gray-900 dark:text-white font-medium">
                        ₱{{ number_format($payment->amount, 2) }}
                        <span class="text-xs ml-2 text-gray-400 dark:text-gray-500">{{ ucfirst($payment->payment_status) }}</span>
                    </span>
                </div>
            @empty
                <p class="text-sm text-gray-400">No payments.</p>
            @endforelse
        </div>

        {{-- Services --}}
        @if($booking->services->isNotEmpty())
            <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                <h3 class="font-semibold text-sm text-gray-900 dark:text-white mb-3">Extra Services</h3>
                @foreach($booking->services as $service)
                    <div class="flex justify-between text-sm py-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                        <span class="text-gray-700 dark:text-gray-300">
                            {{ optional($service->service)->name ?? 'Service' }} ×{{ $service->quantity }}
                        </span>
                        <span class="text-gray-900 dark:text-white font-medium">₱{{ number_format($service->subtotal, 2) }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Footer --}}
        <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4 text-center text-xs text-gray-500 dark:text-gray-400">
            Thank you for booking with us!
        </div>
    </div>

    {{-- Auto print if query parameter print=1 is present, or auto after delay for direct receipt access --}}
    <script>
        window.addEventListener('load', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const shouldPrint = urlParams.get('print') === '1' || {{ request()->has('print') ? 'true' : 'false' }};
            if (shouldPrint) {
                setTimeout(function () {
                    window.print();
                }, 500);
            }
        });
    </script>
</body>
</html>