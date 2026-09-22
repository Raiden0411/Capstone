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

    @php
        $days      = max(1, (int) $booking->check_in->diffInDays($booking->check_out));
        $paid      = (float) $booking->payments->where('payment_status', 'paid')->sum('amount');
        $balance   = max(0, (float) $booking->total_amount - $paid);
        $isSettled = $balance <= 0;
        $isReservation = $booking->booking_type === \App\Models\Booking::TYPE_RESERVATION;
        $isCancelled   = $booking->status === 'cancelled';
        $isSameDay     = $booking->check_in->isSameDay($booking->check_out);

        // FIX: time now reads from booking_time. The DATE columns can't
        // hold a time component, so check_in->format('h:i A') always
        // returned 12:00 AM.
        $timeLabel = null;
        if (!empty($booking->booking_time)) {
            $raw = trim((string) $booking->booking_time);
            try {
                $c = \Illuminate\Support\Carbon::createFromFormat('H:i', $raw);
                if ($c !== false) {
                    $timeLabel = $c->format('g:i A');
                }
            } catch (\Throwable) {
                try {
                    $c = \Illuminate\Support\Carbon::parse($raw);
                    $timeLabel = $c->format('g:i A');
                } catch (\Throwable) { /* leave null */ }
            }
        }

        $durationLabel = $isSameDay
            ? 'Day trip'
            : $days . ' ' . \Illuminate\Support\Str::plural('day', $days);

        $tenantAddress = trim(implode(', ', array_filter([
            $tenant?->address,
            $tenant?->barangay,
        ])));
        $tenantContact = trim(implode(' · ', array_filter([
            $tenant?->contact_number,
            $tenant?->email,
        ])));

        $statusLabel = match ($booking->status) {
            'pending'    => 'Pending',
            'reserved'   => 'Reserved',
            'confirmed'  => 'Confirmed',
            'checked_in' => 'Checked In',
            'completed'  => 'Completed',
            'cancelled'  => 'Cancelled',
            default      => ucfirst((string) $booking->status),
        };

        // Short, uppercase status stamp used in the boxed indicator.
        $statusStamp = match (true) {
            $isCancelled                            => 'Cancelled',
            $isSettled                              => 'Paid in Full',
            $booking->status === 'pending' && $paid == 0 => 'Awaiting Payment',
            $booking->status === 'reserved'         => 'Reserved — Balance on Arrival',
            default                                 => 'Balance Due',
        };

        // Screen badge (colored) — different palette from the print stamp.
        $badgeClasses = match (true) {
            $isCancelled => 'bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30',
            $isSettled   => 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30',
            default      => 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30',
        };
    @endphp

    <style>
        /* ══════════════════════════════════════════════════════════════
           SCREEN vs PRINT
           ══════════════════════════════════════════════════════════════ */

        .receipt-print-only { display: none; }

        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }

            html, body {
                background: #fff !important;
                color: #000 !important;
                margin: 0 !important;
                padding: 0 !important;
                min-height: 0 !important;
                height: auto !important;
            }

            .receipt-screen { display: none !important; }
            .no-print       { display: none !important; }

            .receipt-print-only {
                display: block !important;
                width: 80mm !important;
                max-width: 80mm !important;
                margin: 0 auto !important;
                padding: 3mm !important;
                box-sizing: border-box;
                background: #fff !important;
                font-size: 10px !important;
            }
        }


        /* ══════════════════════════════════════════════════════════════
           RECEIPT DOCUMENT — 80mm single-column, print-safe
           Plain CSS, no Tailwind. Black-on-white in every mode.
           ══════════════════════════════════════════════════════════════ */

        .receipt-document {
            width: 100%;
            color: #111;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            font-variant-numeric: tabular-nums;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Letterhead ── */
        .receipt-letterhead {
            text-align: center;
            padding-bottom: 2mm;
            page-break-after: avoid;
        }

        .receipt-logo {
            display: block;
            max-width: 40mm;
            max-height: 15mm;
            width: auto;
            margin: 0 auto 2mm;
            object-fit: contain;
        }

        .receipt-business-name {
            font-size: 13px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.01em;
            line-height: 1.2;
        }

        .receipt-letterhead-meta {
            font-size: 8.5px;
            color: #666;
            margin: 0.5mm 0 0;
            line-height: 1.35;
        }

        .receipt-document-title {
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: #888;
            margin: 2mm 0 0;
        }

        /* ── Status box (print) ── */
        .receipt-status-box {
            display: block;
            text-align: center;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #111;
            padding: 2mm 1mm;
            margin: 3mm 0;
            border: 1.2pt solid #111;
            page-break-inside: avoid;
        }

        /* ── Rules (separators) ── */
        .receipt-rule        { border-top: 1px solid #111; margin: 2.5mm 0; }
        .receipt-rule-dashed { border-top: 1px dashed #aaa; margin: 2.5mm 0; }
        .receipt-rule-thick  { border-top: 1.5px solid #111; margin: 3mm 0 2mm; }

        /* ── Section headings ── */
        .receipt-section-title {
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #666;
            margin: 0 0 1.5mm;
            page-break-after: avoid;
        }

        /* ── Rows (label / value pairs) ── */
        .receipt-row {
            display: flex;
            justify-content: space-between;
            gap: 2mm;
            padding: 0.4mm 0;
            page-break-inside: avoid;
        }

        .receipt-row-label {
            color: #555;
            flex: 0 0 auto;
            min-width: 0;
        }

        .receipt-row-value {
            font-weight: 600;
            text-align: right;
            flex: 1 1 auto;
            min-width: 0;
            word-break: break-word;
        }

        .receipt-row-total {
            font-size: 12px;
            font-weight: 700;
            padding-top: 1.5mm;
            margin-top: 1mm;
            border-top: 1px dashed #999;
        }

        .receipt-row-total .receipt-row-label { color: #111; }

        /* ── Guest / text blocks ── */
        .receipt-line-bold {
            font-size: 11px;
            font-weight: 600;
            margin: 0;
            line-height: 1.35;
        }

        .receipt-line {
            font-size: 9px;
            color: #555;
            margin: 0.3mm 0 0;
            word-break: break-word;
            line-height: 1.35;
        }

        .receipt-line-italic {
            font-size: 9px;
            font-style: italic;
            color: #888;
            margin: 0;
        }

        /* ── Items table ── */
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
        }

        .receipt-table td {
            padding: 1.2mm 0;
            vertical-align: top;
            border-bottom: 1px dotted #ccc;
            page-break-inside: avoid;
        }

        .receipt-table tbody tr:last-child td { border-bottom: none; }
        .receipt-table td.receipt-text-right   { text-align: right; padding-left: 2mm; }

        .receipt-item-name {
            font-weight: 500;
            font-size: 10px;
            margin: 0;
            line-height: 1.35;
        }

        .receipt-item-detail {
            font-size: 8.5px;
            color: #666;
            margin: 0.4mm 0 0;
            line-height: 1.35;
        }

        .receipt-item-amount {
            font-weight: 600;
            font-size: 10px;
        }

        /* ── Payments ── */
        .receipt-payment {
            padding: 0.8mm 0;
            page-break-inside: avoid;
        }

        /* ── Footer ── */
        .receipt-footer {
            text-align: center;
            padding-top: 2mm;
            page-break-inside: avoid;
        }

        .receipt-footer p { margin: 0.8mm 0; }

        .receipt-footer-primary {
            font-size: 10px;
            font-weight: 600;
            color: #111;
        }

        .receipt-footer-meta {
            font-size: 8.5px;
            color: #666;
        }

        .receipt-footer-ref {
            font-size: 8.5px;
            font-weight: 700;
            color: #111;
            margin-top: 1.5mm !important;
            letter-spacing: 0.05em;
        }

        /* ── Signature ── */
        .receipt-signature {
            margin-top: 8mm;
            page-break-inside: avoid;
        }

        .receipt-signature-line {
            width: 60%;
            margin: 0 auto;
            border-top: 1px solid #111;
        }

        .receipt-signature-label {
            text-align: center;
            font-size: 8.5px;
            color: #666;
            margin: 1mm 0 0;
        }

        /* ── Monospace numbers ── */
        .receipt-mono {
            font-family: ui-monospace, 'SF Mono', 'Cascadia Mono', Menlo, Consolas, monospace;
            font-variant-numeric: tabular-nums;
        }

        /* ── Capitalized labels ── */
        .receipt-table .capitalize,
        .receipt-payment .capitalize { text-transform: capitalize; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-950 font-sans antialiased">

    {{-- ═══════════════════════════════════════════════════════════════
         SCREEN — the tourist-facing receipt card (hidden in print)
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="receipt-screen max-w-2xl mx-auto my-6 sm:my-8 px-4 sm:px-0">

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm p-6 sm:p-8">

            {{-- Header + actions --}}
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-5 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">
                            Booking Receipt
                        </span>
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        #{{ $booking->booking_reference }}
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1 tabular-nums">
                        Issued {{ now()->format('M d, Y · g:i A') }}
                    </p>

                    {{-- Status badge --}}
                    <div class="mt-3">
                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $badgeClasses }}">
                            <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                            {{ $statusStamp }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 no-print">
                    <a href="{{ route('my-bookings') }}"
                       class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                              transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        <span>Back</span>
                    </a>

                    <button type="button" onclick="window.print()"
                            class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                   transition-all duration-200 active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2m-6-4h.01M6 18v4h12v-4"/>
                        </svg>
                        <span>Print Receipt</span>
                    </button>
                </div>
            </div>

            {{-- Business + property identity --}}
            <div class="flex items-start gap-3">
                <div class="w-12 h-12 rounded-xl bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 flex items-center justify-center font-bold text-base shrink-0">
                    {{ strtoupper(substr($tenant?->name ?? 'B', 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="font-semibold text-gray-900 dark:text-white text-lg leading-tight truncate">
                        {{ $property?->name ?? 'Booking' }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $tenant?->name ?? 'Business' }}</p>

                    @if($tenant && ($tenant->address || $tenant->contact_number || $tenant->email))
                        <div class="mt-1.5 text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                            @if($tenantAddress !== '')
                                <p>{{ $tenantAddress }}</p>
                            @endif
                            @if($tenantContact !== '')
                                <p>{{ $tenantContact }}</p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            {{-- Guest + booking summary --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 text-sm bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-[10px] font-bold uppercase tracking-wider">Guest</p>
                    <p class="font-semibold text-gray-900 dark:text-white mt-1 truncate">
                        {{ $booking->user?->name ?? 'Guest' }}
                    </p>
                    @if($booking->user?->phone)
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">{{ $booking->user->phone }}</p>
                    @endif
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-[10px] font-bold uppercase tracking-wider">Booking Type</p>
                    <p class="font-semibold text-gray-900 dark:text-white mt-1">
                        {{ $isReservation ? 'Reservation (20%)' : 'Full Payment' }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $durationLabel }}</p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-[10px] font-bold uppercase tracking-wider">Start</p>
                    <p class="font-semibold text-gray-900 dark:text-white mt-1 tabular-nums">
                        {{ $booking->check_in->format('M d, Y') }}
                    </p>
                    @if($timeLabel)
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 tabular-nums">
                            {{ $timeLabel }}
                        </p>
                    @endif
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-[10px] font-bold uppercase tracking-wider">End</p>
                    <p class="font-semibold text-gray-900 dark:text-white mt-1 tabular-nums">
                        {{ $booking->check_out->format('M d, Y') }}
                    </p>
                    @if($timeLabel)
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 tabular-nums">
                            {{ $timeLabel }}
                        </p>
                    @endif
                </div>
            </div>

            {{-- Booked activities --}}
            @if($booking->items->isNotEmpty())
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Booked Activities
                        </h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-gray-500 dark:text-gray-400 text-[10px] uppercase tracking-wider">
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="text-left pb-2 font-semibold">Activity</th>
                                    <th class="text-center pb-2 font-semibold">Price/Day</th>
                                    <th class="text-center pb-2 font-semibold">Duration</th>
                                    <th class="text-center pb-2 font-semibold">Qty</th>
                                    <th class="text-right pb-2 font-semibold">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($booking->items as $item)
                                    <tr class="border-b border-gray-100 dark:border-gray-700 last:border-0" wire:key="item-{{ $item->id }}">
                                        <td class="py-2 text-gray-700 dark:text-gray-300">
                                            {{ $item->property?->name ?? 'Unknown Activity' }}
                                        </td>
                                        <td class="py-2 text-center text-gray-700 dark:text-gray-300 tabular-nums">
                                            ₱{{ number_format((float) $item->price, 2) }}
                                        </td>
                                        <td class="py-2 text-center text-gray-700 dark:text-gray-300 tabular-nums">{{ $durationLabel }}</td>
                                        <td class="py-2 text-center text-gray-700 dark:text-gray-300 tabular-nums">{{ $item->quantity }}</td>
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
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Extra Services
                        </h3>
                    </div>
                    @foreach($booking->services as $service)
                        <div class="flex justify-between text-sm py-2 border-b border-gray-100 dark:border-gray-700 last:border-0" wire:key="svc-{{ $service->id }}">
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
            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-3 mb-4">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Payment Summary
                    </h3>
                </div>

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
                        <span class="font-semibold {{ $isSettled ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                            {{ $isSettled ? 'Paid in Full' : 'Balance Due' }}
                        </span>
                        <span class="font-bold tabular-nums {{ $isSettled ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            ₱{{ number_format($balance, 2) }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Payment history --}}
            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-3 mb-4">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Payment History
                    </h3>
                </div>

                @forelse($booking->payments as $payment)
                    <div class="py-2 border-b border-gray-100 dark:border-gray-700 last:border-0" wire:key="pay-{{ $payment->id }}">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between text-sm gap-1">
                            <span class="text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                @if($payment->payment_method === 'gcash')
                                    <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v20M2 12h20"/>
                                    </svg>
                                @elseif($payment->payment_method === 'paymaya')
                                    <svg class="w-4 h-4 text-cyan-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2l2 4h4l-3 4 3 4h-4l-2 4-2-4H4l3-4-3-4h4z"/>
                                    </svg>
                                @else
                                    <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <rect x="2" y="5" width="20" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
                                        <line x1="2" y1="10" x2="22" y2="10" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                @endif
                                {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}
                                <span class="text-gray-400">·</span>
                                {{ ucfirst($payment->payment_type) }}
                                @if($payment->paid_at)
                                    <span class="text-gray-400 dark:text-gray-500 text-xs tabular-nums">· {{ $payment->paid_at->format('M d, Y h:i A') }}</span>
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
            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <p class="text-center text-sm font-semibold text-gray-700 dark:text-gray-200">
                    Thank you for booking with us!
                </p>
                <p class="text-center text-[10px] text-gray-400 dark:text-gray-500 mt-1 tabular-nums">
                    Generated {{ now()->format('M d, Y h:i A') }}
                </p>
            </div>
        </div>
    </div>
    {{-- ═══ /SCREEN ═══ --}}


    {{-- ═══════════════════════════════════════════════════════════════
         PRINT-ONLY RECEIPT — 80mm thermal receipt, centered
         ═══════════════════════════════════════════════════════════════ --}}
    <div class="receipt-print-only">
        <div class="receipt-document">

            {{-- ─── Letterhead ─────────────────────────────────── --}}
            <header class="receipt-letterhead">
                @if($tenant?->logo)
                    <img src="{{ asset('storage/' . $tenant->logo) }}"
                         alt="{{ $tenant->name }}"
                         class="receipt-logo">
                @endif

                <h1 class="receipt-business-name">
                    {{ $tenant?->name ?? config('app.name') }}
                </h1>

                @if($tenantAddress !== '')
                    <p class="receipt-letterhead-meta">{{ $tenantAddress }}</p>
                @endif

                @if($tenantContact !== '')
                    <p class="receipt-letterhead-meta">{{ $tenantContact }}</p>
                @endif

                <p class="receipt-document-title">Booking Receipt</p>
            </header>

            {{-- ─── Status stamp ───────────────────────────────── --}}
            <div class="receipt-status-box">{{ $statusStamp }}</div>

            {{-- ─── Reference / Status / Issued / Type ─────────── --}}
            <div class="receipt-row">
                <span class="receipt-row-label">Reference</span>
                <span class="receipt-row-value receipt-mono">#{{ $booking->booking_reference }}</span>
            </div>
            <div class="receipt-row">
                <span class="receipt-row-label">Status</span>
                <span class="receipt-row-value">{{ $statusLabel }}</span>
            </div>
            <div class="receipt-row">
                <span class="receipt-row-label">Issued</span>
                <span class="receipt-row-value">{{ now()->format('M j, Y · g:i A') }}</span>
            </div>
            <div class="receipt-row">
                <span class="receipt-row-label">Type</span>
                <span class="receipt-row-value">{{ $isReservation ? 'Reservation (20% fee)' : 'Full Payment' }}</span>
            </div>

            <div class="receipt-rule-dashed"></div>

            {{-- ─── Guest ──────────────────────────────────────── --}}
            <h2 class="receipt-section-title">Guest</h2>
            @if($booking->user)
                <p class="receipt-line-bold">{{ $booking->user->name }}</p>
                @if($booking->user->phone)
                    <p class="receipt-line">{{ $booking->user->phone }}</p>
                @endif
                @if($booking->user->email)
                    <p class="receipt-line">{{ $booking->user->email }}</p>
                @endif
            @else
                <p class="receipt-line-italic">Walk-in guest</p>
            @endif

            <div class="receipt-rule-dashed"></div>

            {{-- ─── Booking Dates ──────────────────────────────── --}}
            <h2 class="receipt-section-title">Booking Dates</h2>
            <div class="receipt-row">
                <span class="receipt-row-label">Start</span>
                <span class="receipt-row-value">
                    {{ $booking->check_in->format('M j, Y') }}@if($timeLabel) · {{ $timeLabel }}@endif
                </span>
            </div>
            <div class="receipt-row">
                <span class="receipt-row-label">End</span>
                <span class="receipt-row-value">
                    {{ $booking->check_out->format('M j, Y') }}@if($timeLabel) · {{ $timeLabel }}@endif
                </span>
            </div>
            <div class="receipt-row">
                <span class="receipt-row-label">Duration</span>
                <span class="receipt-row-value">{{ $durationLabel }}</span>
            </div>

            <div class="receipt-rule-dashed"></div>

            {{-- ─── Activities & Services ──────────────────────── --}}
            <h2 class="receipt-section-title">Activities &amp; Services</h2>

            @if($booking->items->isEmpty() && $booking->services->isEmpty())
                <p class="receipt-line-italic">No items recorded.</p>
            @else
                <table class="receipt-table">
                    <tbody>
                        @foreach($booking->items as $item)
                            <tr wire:key="print-item-{{ $item->id }}">
                                <td>
                                    <p class="receipt-item-name">{{ $item->property?->name ?? 'Unknown Activity' }}</p>
                                    <p class="receipt-item-detail">
                                        ₱{{ number_format((float) $item->price, 2) }} × {{ $durationLabel }}
                                        @if((int) $item->quantity > 1)
                                            × {{ $item->quantity }}
                                        @endif
                                    </p>
                                </td>
                                <td class="receipt-text-right receipt-item-amount receipt-mono">
                                    ₱{{ number_format((float) $item->subtotal, 2) }}
                                </td>
                            </tr>
                        @endforeach
                        @foreach($booking->services as $service)
                            <tr wire:key="print-service-{{ $service->id }}">
                                <td>
                                    <p class="receipt-item-name">{{ $service->service?->name ?? 'Unknown Service' }}</p>
                                    @if((int) $service->quantity > 1)
                                        <p class="receipt-item-detail">Qty: {{ $service->quantity }}</p>
                                    @endif
                                </td>
                                <td class="receipt-text-right receipt-item-amount receipt-mono">
                                    ₱{{ number_format((float) $service->subtotal, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="receipt-rule"></div>

            {{-- ─── Totals ─────────────────────────────────────── --}}
            <div class="receipt-row">
                <span class="receipt-row-label">Total</span>
                <span class="receipt-row-value receipt-mono">₱{{ number_format((float) $booking->total_amount, 2) }}</span>
            </div>

            @if($paid > 0)
                <div class="receipt-row">
                    <span class="receipt-row-label">Amount Paid</span>
                    <span class="receipt-row-value receipt-mono">−₱{{ number_format($paid, 2) }}</span>
                </div>
            @endif

            <div class="receipt-row receipt-row-total">
                <span class="receipt-row-label">{{ $isSettled ? 'Paid in Full' : 'Balance Due' }}</span>
                <span class="receipt-row-value receipt-mono">₱{{ number_format($balance, 2) }}</span>
            </div>

            @if($isReservation)
                @php
                    $reservationFee   = round((float) $booking->total_amount * 0.20, 2);
                    $balanceOnArrival = max(0, (float) $booking->total_amount - $reservationFee);
                @endphp
                <div class="receipt-rule-dashed"></div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Reservation Fee (20%)</span>
                    <span class="receipt-row-value receipt-mono">₱{{ number_format($reservationFee, 2) }}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Balance on Arrival</span>
                    <span class="receipt-row-value receipt-mono">₱{{ number_format($balanceOnArrival, 2) }}</span>
                </div>
            @endif

            {{-- ─── Payment History ─────────────────────────────── --}}
            @if($booking->payments->isNotEmpty())
                <div class="receipt-rule-dashed"></div>

                <h2 class="receipt-section-title">Payment History</h2>

                @foreach($booking->payments as $payment)
                    <div class="receipt-payment" wire:key="print-payment-{{ $payment->id }}">
                        <div class="receipt-row">
                            <span class="receipt-row-label capitalize">
                                {{ str_replace('_', ' ', (string) $payment->payment_method) }}
                                @if($payment->payment_type === 'reservation')
                                    · Fee
                                @endif
                            </span>
                            <span class="receipt-row-value receipt-mono">₱{{ number_format((float) $payment->amount, 2) }}</span>
                        </div>
                        <p class="receipt-item-detail">
                            {{ $payment->paid_at?->format('M j, Y · g:i A')
                                ?? $payment->created_at?->format('M j, Y · g:i A')
                                ?? '—' }}
                            @if($payment->reference_number)
                                · Ref: {{ $payment->reference_number }}
                            @endif
                        </p>
                    </div>
                @endforeach
            @endif

            <div class="receipt-rule-thick"></div>

            {{-- ─── Signature ──────────────────────────────────── --}}
            <div class="receipt-signature">
                <div class="receipt-signature-line"></div>
                <p class="receipt-signature-label">Authorized Signature</p>
            </div>

            {{-- ─── Footer ─────────────────────────────────────── --}}
            <footer class="receipt-footer">
                <p class="receipt-footer-primary">Thank you for booking with us!</p>
                <p class="receipt-footer-meta">Generated {{ now()->format('M j, Y · g:i A') }}</p>
                <p class="receipt-footer-ref receipt-mono">#{{ $booking->booking_reference }}</p>
            </footer>
        </div>
    </div>
    {{-- ═══ /PRINT-ONLY RECEIPT ═══ --}}


    {{-- Auto-print when ?print=1. Controller-rendered view — bare <script> is fine. --}}
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