@extends('tenant.layouts.app')

@section('content')
<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto space-y-6">

    {{-- Back & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <a href="{{ route('tenant.bookings.index') }}" wire:navigate class="text-sm text-white/50 hover:text-white transition-colors">
                &larr; Back to Bookings
            </a>
            <h1 class="text-2xl sm:text-3xl font-bold text-white mt-1">Booking #{{ $booking->booking_reference }}</h1>
            <p class="text-white/60 mt-1">
                {{ $booking->customer->name }} • 
                {{ $booking->check_in->format('M d, Y') }} – {{ $booking->check_out->format('M d, Y') }}
            </p>
        </div>
        <div class="flex gap-3">
            @php
                $paidAmount = $booking->payments->where('payment_status', 'paid')->sum('amount');
                $balance = $booking->total_amount - $paidAmount;
            @endphp

            @livewire('tenant::pages.payment.quick-pay', ['booking' => $booking])

            <a href="{{ route('tenant.bookings.edit', $booking->id) }}" wire:navigate class="glass px-5 py-2.5 rounded-xl text-white/80 hover:bg-white/10 font-medium transition">
                Edit Booking
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Column – Items, Services, Payments --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Booked Properties --}}
            <div class="glass-card !rounded-xl p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Properties</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-white/10 text-white/50">
                            <tr>
                                <th class="text-left py-2 pr-4">Property</th>
                                <th class="text-center py-2 px-2">Price/Night</th>
                                <th class="text-center py-2 px-2">Qty</th>
                                <th class="text-right py-2 pl-2">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="text-white/80">
                            @foreach($booking->items as $item)
                            <tr class="border-b border-white/5">
                                <td class="py-2 pr-4">{{ $item->property->name ?? 'Unknown' }}</td>
                                <td class="py-2 px-2 text-center">₱{{ number_format($item->price, 2) }}</td>
                                <td class="py-2 px-2 text-center">{{ $item->quantity }}</td>
                                <td class="py-2 pl-2 text-right font-medium text-white">₱{{ number_format($item->subtotal, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Additional Services --}}
            @if($booking->services->count())
            <div class="glass-card !rounded-xl p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Additional Services</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-white/10 text-white/50">
                            <tr>
                                <th class="text-left py-2 pr-4">Service</th>
                                <th class="text-center py-2 px-2">Price</th>
                                <th class="text-center py-2 px-2">Qty</th>
                                <th class="text-right py-2 pl-2">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="text-white/80">
                            @foreach($booking->services as $service)
                            <tr class="border-b border-white/5">
                                <td class="py-2 pr-4">{{ $service->service->name ?? 'Unknown' }}</td>
                                <td class="py-2 px-2 text-center">₱{{ number_format($service->service->price ?? 0, 2) }}</td>
                                <td class="py-2 px-2 text-center">{{ $service->quantity }}</td>
                                <td class="py-2 pl-2 text-right font-medium text-white">₱{{ number_format($service->subtotal, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- Payment History --}}
            <div class="glass-card !rounded-xl p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Payment History</h2>
                @if($booking->payments->count())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-white/10 text-white/50">
                            <tr>
                                <th class="text-left py-2">Date</th>
                                <th class="text-left py-2">Method</th>
                                <th class="text-right py-2">Amount</th>
                                <th class="text-right py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="text-white/80">
                            @foreach($booking->payments as $payment)
                            <tr class="border-b border-white/5">
                                <td class="py-2">{{ $payment->paid_at?->format('M d, Y') ?? $payment->created_at->format('M d, Y') }}</td>
                                <td class="py-2">{{ ucfirst($payment->payment_method) }}</td>
                                <td class="py-2 text-right font-medium text-white">₱{{ number_format($payment->amount, 2) }}</td>
                                <td class="py-2 text-right">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $payment->payment_status === 'paid' ? 'bg-green-500/20 text-green-300' : 'bg-amber-500/20 text-amber-300' }}">
                                        {{ ucfirst($payment->payment_status) }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-white/40 text-sm">No payments recorded yet.</p>
                @endif
            </div>
        </div>

        {{-- Right Column – Summary & Customer --}}
        <div class="space-y-6">
            {{-- Summary Card --}}
            <div class="glass-card !rounded-xl p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Summary</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-white/50">Total Amount</dt>
                        <dd class="font-medium text-white">₱{{ number_format($booking->total_amount, 2) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-white/50">Paid</dt>
                        <dd class="text-brand-400 font-medium">₱{{ number_format($paidAmount, 2) }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-white/10 pt-3">
                        <dt class="text-white/50">Balance Due</dt>
                        <dd class="font-bold {{ $balance > 0 ? 'text-red-400' : 'text-brand-400' }}">₱{{ number_format($balance, 2) }}</dd>
                    </div>
                </dl>
                <div class="mt-4">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                        {{ $booking->status === 'confirmed' ? 'bg-green-500/20 text-green-300' : '' }}
                        {{ $booking->status === 'pending' ? 'bg-amber-500/20 text-amber-300' : '' }}
                        {{ $booking->status === 'checked_in' ? 'bg-purple-500/20 text-purple-300' : '' }}
                        {{ $booking->status === 'completed' ? 'bg-blue-500/20 text-blue-300' : '' }}
                        {{ $booking->status === 'cancelled' ? 'bg-red-500/20 text-red-300' : '' }}">
                        {{ ucfirst($booking->status) }}
                    </span>
                </div>
            </div>

            {{-- Customer Info --}}
            @if($booking->customer)
            <div class="glass-card !rounded-xl p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Customer</h2>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-white/50">Name</dt>
                        <dd class="font-medium text-white">{{ $booking->customer->name }}</dd>
                    </div>
                    @if($booking->customer->phone)
                    <div>
                        <dt class="text-white/50">Phone</dt>
                        <dd class="text-white/80">{{ $booking->customer->phone }}</dd>
                    </div>
                    @endif
                    @if($booking->customer->email)
                    <div>
                        <dt class="text-white/50">Email</dt>
                        <dd class="text-white/80">{{ $booking->customer->email }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection