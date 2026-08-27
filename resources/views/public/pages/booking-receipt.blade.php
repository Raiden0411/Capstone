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
        }
    </style>
</head>
<body class="bg-gray-100" x-data>

    <div class="max-w-2xl mx-auto py-8 px-4">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6">

            {{-- Header --}}
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Receipt</h1>
                    <p class="text-sm text-gray-500">Booking Reference: {{ $booking->booking_reference }}</p>
                </div>
                <button onclick="window.print()" class="no-print bg-brand-600 text-white px-4 py-2 rounded-full text-xs font-bold uppercase hover:bg-brand-700 transition">
                    Print
                </button>
            </div>

            {{-- Property / Tenant Info --}}
            <div class="border-t border-gray-200 pt-4">
                <h2 class="font-semibold text-gray-900">{{ $property?->name ?? 'Booking' }}</h2>
                <p class="text-sm text-gray-500">{{ $tenant?->name ?? 'Business' }}</p>
            </div>

            {{-- Booking Summary --}}
            <div class="grid grid-cols-2 gap-4 mt-4 text-sm">
                <div>
                    <p class="text-gray-500">Check-in</p>
                    <p class="font-medium">{{ \Carbon\Carbon::parse($booking->check_in)->format('M d, Y') }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Check-out</p>
                    <p class="font-medium">{{ \Carbon\Carbon::parse($booking->check_out)->format('M d, Y') }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Total Amount</p>
                    <p class="font-medium">₱{{ number_format($booking->total_amount, 2) }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Status</p>
                    <p class="font-medium capitalize">{{ $booking->status }}</p>
                </div>
            </div>

            {{-- Payment History --}}
            <div class="mt-6 border-t border-gray-200 pt-4">
                <h3 class="font-semibold text-sm mb-2">Payment History</h3>
                @forelse($booking->payments as $payment)
                    <div class="flex justify-between text-sm py-1">
                        <span>{{ ucfirst($payment->payment_method) }} ({{ ucfirst($payment->payment_type) }})</span>
                        <span>₱{{ number_format($payment->amount, 2) }} - {{ ucfirst($payment->payment_status) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No payments.</p>
                @endforelse
            </div>

            {{-- Services --}}
            @if($booking->services->isNotEmpty())
                <div class="mt-6 border-t border-gray-200 pt-4">
                    <h3 class="font-semibold text-sm mb-2">Extra Services</h3>
                    @foreach($booking->services as $service)
                        <div class="flex justify-between text-sm py-1">
                            <span>{{ $service->service->name }} ×{{ $service->quantity }}</span>
                            <span>₱{{ number_format($service->subtotal, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Footer --}}
            <div class="mt-6 border-t border-gray-200 pt-4 text-center text-xs text-gray-400">
                Thank you for booking with us!
            </div>
        </div>
    </div>

    {{-- Auto print on load --}}
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