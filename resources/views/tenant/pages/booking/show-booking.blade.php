@extends('tenant.layouts.app')

@section('content')
<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto text-gray-900 dark:text-white space-y-6">

    {{-- Back & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <a href="{{ route('tenant.bookings.index') }}" wire:navigate class="text-sm text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-white transition-colors">
                &larr; Back to Bookings
            </a>
            <h1 class="text-2xl sm:text-3xl font-bold mt-1">Booking #{{ $booking->booking_reference }}</h1>
            <p class="text-gray-500 dark:text-slate-400 mt-1">
                {{ $booking->customer->name }} • 
                {{ $booking->check_in->format('M d, Y') }} – {{ $booking->check_out->format('M d, Y') }}
            </p>
        </div>
        <div class="flex gap-3">
            @php
                $paidAmount = $booking->payments->where('payment_status', 'paid')->sum('amount');
                $balance = $booking->total_amount - $paidAmount;
            @endphp

            {{-- Quick‑Pay button (cash only) --}}
            @livewire('tenant::pages.payment.quick-pay', ['booking' => $booking])

            <a href="{{ route('tenant.bookings.edit', $booking->id) }}" wire:navigate class="border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 font-medium py-2.5 px-5 rounded-xl transition-colors">
                Edit Booking
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Column – Items, Services, Payments --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Booked Properties --}}
            <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
                <h2 class="text-lg font-semibold mb-4">Properties</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-gray-200 dark:border-slate-700/50 text-gray-500 dark:text-slate-400">
                            <tr>
                                <th class="text-left py-2 pr-4">Property</th>
                                <th class="text-center py-2 px-2">Price/Night</th>
                                <th class="text-center py-2 px-2">Qty</th>
                                <th class="text-right py-2 pl-2">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($booking->items as $item)
                            <tr class="border-b border-gray-100 dark:border-slate-700/30">
                                <td class="py-2 pr-4">{{ $item->property->name ?? 'Unknown' }}</td>
                                <td class="py-2 px-2 text-center">₱{{ number_format($item->price, 2) }}</td>
                                <td class="py-2 px-2 text-center">{{ $item->quantity }}</td>
                                <td class="py-2 pl-2 text-right font-medium">₱{{ number_format($item->subtotal, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Additional Services --}}
            @if($booking->services->count())
            <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
                <h2 class="text-lg font-semibold mb-4">Additional Services</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-gray-200 dark:border-slate-700/50 text-gray-500 dark:text-slate-400">
                            <tr>
                                <th class="text-left py-2 pr-4">Service</th>
                                <th class="text-center py-2 px-2">Price</th>
                                <th class="text-center py-2 px-2">Qty</th>
                                <th class="text-right py-2 pl-2">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($booking->services as $service)
                            <tr class="border-b border-gray-100 dark:border-slate-700/30">
                                <td class="py-2 pr-4">{{ $service->service->name ?? 'Unknown' }}</td>
                                <td class="py-2 px-2 text-center">₱{{ number_format($service->service->price ?? 0, 2) }}</td>
                                <td class="py-2 px-2 text-center">{{ $service->quantity }}</td>
                                <td class="py-2 pl-2 text-right font-medium">₱{{ number_format($service->subtotal, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- Payment History --}}
            <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
                <h2 class="text-lg font-semibold mb-4">Payment History</h2>
                @if($booking->payments->count())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-gray-200 dark:border-slate-700/50 text-gray-500 dark:text-slate-400">
                            <tr>
                                <th class="text-left py-2">Date</th>
                                <th class="text-left py-2">Method</th>
                                <th class="text-right py-2">Amount</th>
                                <th class="text-right py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($booking->payments as $payment)
                            <tr class="border-b border-gray-100 dark:border-slate-700/30">
                                <td class="py-2">{{ $payment->paid_at?->format('M d, Y') ?? $payment->created_at->format('M d, Y') }}</td>
                                <td class="py-2">{{ ucfirst($payment->payment_method) }}</td>
                                <td class="py-2 text-right font-medium">₱{{ number_format($payment->amount, 2) }}</td>
                                <td class="py-2 text-right">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $payment->payment_status === 'paid' ? 'bg-green-100 dark:bg-green-500/20 text-green-800 dark:text-green-400' : 'bg-yellow-100 dark:bg-yellow-500/20 text-yellow-800 dark:text-yellow-400' }}">
                                        {{ ucfirst($payment->payment_status) }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-gray-500 dark:text-slate-400 text-sm">No payments recorded yet.</p>
                @endif
            </div>
        </div>

        {{-- Right Column – Summary & Customer --}}
        <div class="space-y-6">
            {{-- Summary Card --}}
            <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
                <h2 class="text-lg font-semibold mb-4">Summary</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-slate-400">Total Amount</dt>
                        <dd class="font-medium">₱{{ number_format($booking->total_amount, 2) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-slate-400">Paid</dt>
                        <dd class="text-emerald-600 dark:text-emerald-400 font-medium">₱{{ number_format($paidAmount, 2) }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-gray-200 dark:border-slate-700/50 pt-3">
                        <dt class="text-gray-500 dark:text-slate-400">Balance Due</dt>
                        <dd class="font-bold {{ $balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">₱{{ number_format($balance, 2) }}</dd>
                    </div>
                </dl>
                <div class="mt-4">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                        {{ $booking->status === 'confirmed' ? 'bg-green-100 dark:bg-green-500/20 text-green-800 dark:text-green-400' : '' }}
                        {{ $booking->status === 'pending' ? 'bg-amber-100 dark:bg-amber-500/20 text-amber-800 dark:text-amber-400' : '' }}
                        {{ $booking->status === 'checked_in' ? 'bg-purple-100 dark:bg-purple-500/20 text-purple-800 dark:text-purple-400' : '' }}
                        {{ $booking->status === 'completed' ? 'bg-blue-100 dark:bg-blue-500/20 text-blue-800 dark:text-blue-400' : '' }}
                        {{ $booking->status === 'cancelled' ? 'bg-red-100 dark:bg-red-500/20 text-red-800 dark:text-red-400' : '' }}">
                        {{ ucfirst($booking->status) }}
                    </span>
                </div>
            </div>

            {{-- Customer Info --}}
            @if($booking->customer)
            <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
                <h2 class="text-lg font-semibold mb-4">Customer</h2>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-slate-400">Name</dt>
                        <dd class="font-medium">{{ $booking->customer->name }}</dd>
                    </div>
                    @if($booking->customer->phone)
                    <div>
                        <dt class="text-gray-500 dark:text-slate-400">Phone</dt>
                        <dd>{{ $booking->customer->phone }}</dd>
                    </div>
                    @endif
                    @if($booking->customer->email)
                    <div>
                        <dt class="text-gray-500 dark:text-slate-400">Email</dt>
                        <dd>{{ $booking->customer->email }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection