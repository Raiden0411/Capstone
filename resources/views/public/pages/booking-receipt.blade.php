<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #{{ $booking->booking_reference }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .receipt-card { box-shadow: none; border: none; }
        }
    </style>
</head>
<body class="bg-[#F8F7F3] font-sans antialiased">

    <div class="max-w-2xl mx-auto py-8 px-4">
        <div class="receipt-card bg-white border border-gray-200 rounded-2xl shadow-sm p-6 sm:p-8">

            {{-- Header --}}
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b border-gray-200">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Receipt</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Booking Reference:
                        <span class="font-mono font-medium text-gray-900">{{ $booking->booking_reference }}</span>
                    </p>
                </div>
                <div class="flex gap-2 no-print">
                    <a href="{{ route('my-bookings') }}"
                       class="text-gray-600 hover:text-gray-900 px-4 py-2 rounded-full text-xs font-bold uppercase border border-gray-300 hover:bg-gray-100 transition">
                        Back
                    </a>
                    <button onclick="window.print()"
                            class="bg-[#376df1] hover:bg-blue-700 text-white px-4 py-2 rounded-full text-xs font-bold uppercase shadow-sm transition">
                        Print
                    </button>
                </div>
            </div>

            {{-- Business / Activity Info --}}
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-50 text-[#376df1] flex items-center justify-center font-bold text-sm shrink-0">
                    {{ strtoupper(substr($tenant?->name ?? 'B', 0, 1)) }}
                </div>
                <div>
                    <h2 class="font-semibold text-gray-900 text-lg leading-tight">
                        {{ $property?->name ?? 'Booking' }}
                    </h2>
                    <p class="text-sm text-gray-500">{{ $tenant?->name ?? 'Business' }}</p>
                </div>
            </div>

            {{-- Booking Summary --}}
            <div class="grid grid-cols-2 gap-4 mt-6 text-sm bg-gray-50 rounded-xl p-4">
                <div>
                    <p class="text-gray-500 text-xs uppercase tracking-wider">Check-in</p>
                    <p class="font-semibold text-gray-900 mt-1">{{ \Carbon\Carbon::parse($booking->check_in)->format('M d, Y') }}</p>
                </div>
                <div>
                    <p class="text-gray-500 text-xs uppercase tracking-wider">Check-out</p>
                    <p class="font-semibold text-gray-900 mt-1">{{ \Carbon\Carbon::parse($booking->check_out)->format('M d, Y') }}</p>
                </div>
                <div>
                    <p class="text-gray-500 text-xs uppercase tracking-wider">Total Amount</p>
                    <p class="font-semibold text-gray-900 mt-1">₱{{ number_format($booking->total_amount, 2) }}</p>
                </div>
                <div>
                    <p class="text-gray-500 text-xs uppercase tracking-wider">Status</p>
                    <p class="font-medium mt-1 capitalize text-gray-900">{{ $booking->status }}</p>
                </div>
            </div>

            {{-- Payment History --}}
            <div class="mt-6 border-t border-gray-200 pt-4">
                <h3 class="font-semibold text-sm text-gray-900 mb-3">Payment History</h3>
                @forelse($booking->payments as $payment)
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between text-sm py-2 border-b border-gray-100 last:border-0">
                        <span class="text-gray-700">
                            {{ ucfirst($payment->payment_method) }}
                            <span class="text-gray-400">·</span>
                            {{ ucfirst($payment->payment_type) }}
                        </span>
                        <span class="text-gray-900 font-medium">
                            ₱{{ number_format($payment->amount, 2) }}
                            <span class="text-xs ml-2 text-gray-400">{{ ucfirst($payment->payment_status) }}</span>
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No payments.</p>
                @endforelse
            </div>

            {{-- Services --}}
            @if($booking->services->isNotEmpty())
                <div class="mt-6 border-t border-gray-200 pt-4">
                    <h3 class="font-semibold text-sm text-gray-900 mb-3">Extra Services</h3>
                    @foreach($booking->services as $service)
                        <div class="flex justify-between text-sm py-2 border-b border-gray-100 last:border-0">
                            <span class="text-gray-700">
                                {{ optional($service->service)->name ?? 'Service' }} ×{{ $service->quantity }}
                            </span>
                            <span class="text-gray-900 font-medium">₱{{ number_format($service->subtotal, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Footer --}}
            <div class="mt-6 border-t border-gray-200 pt-4 text-center text-xs text-gray-500">
                Thank you for booking with us!
            </div>
        </div>
    </div>

    {{-- Auto print on load (disabled if already printed or user came from manual click) --}}
    <script>
        window.addEventListener('load', function () {
            // Slight delay to ensure rendering is complete
            setTimeout(function () {
                window.print();
            }, 500);
        });
    </script>
</body>
</html>