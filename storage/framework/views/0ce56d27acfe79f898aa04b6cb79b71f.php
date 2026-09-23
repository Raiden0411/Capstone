
<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>"
      class="<?php echo e(session('theme', 'light') === 'dark' ? 'dark' : ''); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Receipt · <?php echo e($booking->booking_reference); ?></title>

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css']); ?>

    <?php
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
    ?>

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

    
    <div class="receipt-screen max-w-2xl mx-auto my-6 sm:my-8 px-4 sm:px-0">

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm p-6 sm:p-8">

            
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-5 border-b border-gray-200 dark:border-gray-700">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">
                            Booking Receipt
                        </span>
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        #<?php echo e($booking->booking_reference); ?>

                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1 tabular-nums">
                        Issued <?php echo e(now()->format('M d, Y · g:i A')); ?>

                    </p>

                    
                    <div class="mt-3">
                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo e($badgeClasses); ?>">
                            <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                            <?php echo e($statusStamp); ?>

                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 no-print">
                    <a href="<?php echo e(route('my-bookings')); ?>"
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

            
            <div class="flex items-start gap-3">
                <div class="w-12 h-12 rounded-xl bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 flex items-center justify-center font-bold text-base shrink-0">
                    <?php echo e(strtoupper(substr($tenant?->name ?? 'B', 0, 1))); ?>

                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="font-semibold text-gray-900 dark:text-white text-lg leading-tight truncate">
                        <?php echo e($property?->name ?? 'Booking'); ?>

                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 truncate"><?php echo e($tenant?->name ?? 'Business'); ?></p>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant && ($tenant->address || $tenant->contact_number || $tenant->email)): ?>
                        <div class="mt-1.5 text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenantAddress !== ''): ?>
                                <p><?php echo e($tenantAddress); ?></p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenantContact !== ''): ?>
                                <p><?php echo e($tenantContact); ?></p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 text-sm bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4">
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-[10px] font-bold uppercase tracking-wider">Guest</p>
                    <p class="font-semibold text-gray-900 dark:text-white mt-1 truncate">
                        <?php echo e($booking->user?->name ?? 'Guest'); ?>

                    </p>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->user?->phone): ?>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate"><?php echo e($booking->user->phone); ?></p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-[10px] font-bold uppercase tracking-wider">Booking Type</p>
                    <p class="font-semibold text-gray-900 dark:text-white mt-1">
                        <?php echo e($isReservation ? 'Reservation (20%)' : 'Full Payment'); ?>

                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"><?php echo e($durationLabel); ?></p>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-[10px] font-bold uppercase tracking-wider">Start</p>
                    <p class="font-semibold text-gray-900 dark:text-white mt-1 tabular-nums">
                        <?php echo e($booking->check_in->format('M d, Y')); ?>

                    </p>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($timeLabel): ?>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 tabular-nums">
                            <?php echo e($timeLabel); ?>

                        </p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-[10px] font-bold uppercase tracking-wider">End</p>
                    <p class="font-semibold text-gray-900 dark:text-white mt-1 tabular-nums">
                        <?php echo e($booking->check_out->format('M d, Y')); ?>

                    </p>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($timeLabel): ?>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 tabular-nums">
                            <?php echo e($timeLabel); ?>

                        </p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->items->isNotEmpty()): ?>
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
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <tr class="border-b border-gray-100 dark:border-gray-700 last:border-0" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'item-'.e($item->id).''; ?>wire:key="item-<?php echo e($item->id); ?>">
                                        <td class="py-2 text-gray-700 dark:text-gray-300">
                                            <?php echo e($item->property?->name ?? 'Unknown Activity'); ?>

                                        </td>
                                        <td class="py-2 text-center text-gray-700 dark:text-gray-300 tabular-nums">
                                            ₱<?php echo e(number_format((float) $item->price, 2)); ?>

                                        </td>
                                        <td class="py-2 text-center text-gray-700 dark:text-gray-300 tabular-nums"><?php echo e($durationLabel); ?></td>
                                        <td class="py-2 text-center text-gray-700 dark:text-gray-300 tabular-nums"><?php echo e($item->quantity); ?></td>
                                        <td class="py-2 text-right text-gray-900 dark:text-white font-medium tabular-nums">
                                            ₱<?php echo e(number_format((float) $item->subtotal, 2)); ?>

                                        </td>
                                    </tr>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->services->isNotEmpty()): ?>
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Extra Services
                        </h3>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->services; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="flex justify-between text-sm py-2 border-b border-gray-100 dark:border-gray-700 last:border-0" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'svc-'.e($service->id).''; ?>wire:key="svc-<?php echo e($service->id); ?>">
                            <span class="text-gray-700 dark:text-gray-300">
                                <?php echo e($service->service?->name ?? 'Service'); ?> ×<?php echo e($service->quantity); ?>

                            </span>
                            <span class="text-gray-900 dark:text-white font-medium tabular-nums">
                                ₱<?php echo e(number_format((float) $service->subtotal, 2)); ?>

                            </span>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
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
                            ₱<?php echo e(number_format((float) $booking->total_amount, 2)); ?>

                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Amount Paid</span>
                        <span class="font-semibold text-emerald-600 dark:text-emerald-400 tabular-nums">
                            ₱<?php echo e(number_format($paid, 2)); ?>

                        </span>
                    </div>
                    <div class="flex justify-between pt-2 mt-2 border-t border-gray-200 dark:border-gray-700">
                        <span class="font-semibold <?php echo e($isSettled ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'); ?>">
                            <?php echo e($isSettled ? 'Paid in Full' : 'Balance Due'); ?>

                        </span>
                        <span class="font-bold tabular-nums <?php echo e($isSettled ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'); ?>">
                            ₱<?php echo e(number_format($balance, 2)); ?>

                        </span>
                    </div>
                </div>
            </div>

            
            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-3 mb-4">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Payment History
                    </h3>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $booking->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="py-2 border-b border-gray-100 dark:border-gray-700 last:border-0" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'pay-'.e($payment->id).''; ?>wire:key="pay-<?php echo e($payment->id); ?>">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between text-sm gap-1">
                            <span class="text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment->payment_method === 'gcash'): ?>
                                    <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v20M2 12h20"/>
                                    </svg>
                                <?php elseif($payment->payment_method === 'paymaya'): ?>
                                    <svg class="w-4 h-4 text-cyan-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2l2 4h4l-3 4 3 4h-4l-2 4-2-4H4l3-4-3-4h4z"/>
                                    </svg>
                                <?php else: ?>
                                    <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <rect x="2" y="5" width="20" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
                                        <line x1="2" y1="10" x2="22" y2="10" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php echo e(ucfirst(str_replace('_', ' ', $payment->payment_method))); ?>

                                <span class="text-gray-400">·</span>
                                <?php echo e(ucfirst($payment->payment_type)); ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment->paid_at): ?>
                                    <span class="text-gray-400 dark:text-gray-500 text-xs tabular-nums">· <?php echo e($payment->paid_at->format('M d, Y h:i A')); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </span>
                            <span class="text-gray-900 dark:text-white font-medium tabular-nums">
                                ₱<?php echo e(number_format((float) $payment->amount, 2)); ?>

                                <span class="text-xs ml-2
                                    <?php echo e($payment->payment_status === 'paid'
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-amber-600 dark:text-amber-400'); ?>">
                                    <?php echo e(ucfirst($payment->payment_status)); ?>

                                </span>
                            </span>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment->reference_number): ?>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500 font-mono mt-0.5 pl-6">
                                Ref: <?php echo e($payment->reference_number); ?>

                            </p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <p class="text-sm text-gray-400 dark:text-gray-500 italic">No payments recorded.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <p class="text-center text-sm font-semibold text-gray-700 dark:text-gray-200">
                    Thank you for booking with us!
                </p>
                <p class="text-center text-[10px] text-gray-400 dark:text-gray-500 mt-1 tabular-nums">
                    Generated <?php echo e(now()->format('M d, Y h:i A')); ?>

                </p>
            </div>
        </div>
    </div>
    


    
    <div class="receipt-print-only">
        <div class="receipt-document">

            
            <header class="receipt-letterhead">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant?->logo): ?>
                    <img src="<?php echo e(asset('storage/' . $tenant->logo)); ?>"
                         alt="<?php echo e($tenant->name); ?>"
                         class="receipt-logo">
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <h1 class="receipt-business-name">
                    <?php echo e($tenant?->name ?? config('app.name')); ?>

                </h1>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenantAddress !== ''): ?>
                    <p class="receipt-letterhead-meta"><?php echo e($tenantAddress); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenantContact !== ''): ?>
                    <p class="receipt-letterhead-meta"><?php echo e($tenantContact); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <p class="receipt-document-title">Booking Receipt</p>
            </header>

            
            <div class="receipt-status-box"><?php echo e($statusStamp); ?></div>

            
            <div class="receipt-row">
                <span class="receipt-row-label">Reference</span>
                <span class="receipt-row-value receipt-mono">#<?php echo e($booking->booking_reference); ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-row-label">Status</span>
                <span class="receipt-row-value"><?php echo e($statusLabel); ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-row-label">Issued</span>
                <span class="receipt-row-value"><?php echo e(now()->format('M j, Y · g:i A')); ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-row-label">Type</span>
                <span class="receipt-row-value"><?php echo e($isReservation ? 'Reservation (20% fee)' : 'Full Payment'); ?></span>
            </div>

            <div class="receipt-rule-dashed"></div>

            
            <h2 class="receipt-section-title">Guest</h2>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->user): ?>
                <p class="receipt-line-bold"><?php echo e($booking->user->name); ?></p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->user->phone): ?>
                    <p class="receipt-line"><?php echo e($booking->user->phone); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->user->email): ?>
                    <p class="receipt-line"><?php echo e($booking->user->email); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php else: ?>
                <p class="receipt-line-italic">Walk-in guest</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="receipt-rule-dashed"></div>

            
            <h2 class="receipt-section-title">Booking Dates</h2>
            <div class="receipt-row">
                <span class="receipt-row-label">Start</span>
                <span class="receipt-row-value">
                    <?php echo e($booking->check_in->format('M j, Y')); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($timeLabel): ?> · <?php echo e($timeLabel); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </span>
            </div>
            <div class="receipt-row">
                <span class="receipt-row-label">End</span>
                <span class="receipt-row-value">
                    <?php echo e($booking->check_out->format('M j, Y')); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($timeLabel): ?> · <?php echo e($timeLabel); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </span>
            </div>
            <div class="receipt-row">
                <span class="receipt-row-label">Duration</span>
                <span class="receipt-row-value"><?php echo e($durationLabel); ?></span>
            </div>

            <div class="receipt-rule-dashed"></div>

            
            <h2 class="receipt-section-title">Activities &amp; Services</h2>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->items->isEmpty() && $booking->services->isEmpty()): ?>
                <p class="receipt-line-italic">No items recorded.</p>
            <?php else: ?>
                <table class="receipt-table">
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'print-item-'.e($item->id).''; ?>wire:key="print-item-<?php echo e($item->id); ?>">
                                <td>
                                    <p class="receipt-item-name"><?php echo e($item->property?->name ?? 'Unknown Activity'); ?></p>
                                    <p class="receipt-item-detail">
                                        ₱<?php echo e(number_format((float) $item->price, 2)); ?> × <?php echo e($durationLabel); ?>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $item->quantity > 1): ?>
                                            × <?php echo e($item->quantity); ?>

                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </p>
                                </td>
                                <td class="receipt-text-right receipt-item-amount receipt-mono">
                                    ₱<?php echo e(number_format((float) $item->subtotal, 2)); ?>

                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->services; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'print-service-'.e($service->id).''; ?>wire:key="print-service-<?php echo e($service->id); ?>">
                                <td>
                                    <p class="receipt-item-name"><?php echo e($service->service?->name ?? 'Unknown Service'); ?></p>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $service->quantity > 1): ?>
                                        <p class="receipt-item-detail">Qty: <?php echo e($service->quantity); ?></p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td class="receipt-text-right receipt-item-amount receipt-mono">
                                    ₱<?php echo e(number_format((float) $service->subtotal, 2)); ?>

                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="receipt-rule"></div>

            
            <div class="receipt-row">
                <span class="receipt-row-label">Total</span>
                <span class="receipt-row-value receipt-mono">₱<?php echo e(number_format((float) $booking->total_amount, 2)); ?></span>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paid > 0): ?>
                <div class="receipt-row">
                    <span class="receipt-row-label">Amount Paid</span>
                    <span class="receipt-row-value receipt-mono">−₱<?php echo e(number_format($paid, 2)); ?></span>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="receipt-row receipt-row-total">
                <span class="receipt-row-label"><?php echo e($isSettled ? 'Paid in Full' : 'Balance Due'); ?></span>
                <span class="receipt-row-value receipt-mono">₱<?php echo e(number_format($balance, 2)); ?></span>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isReservation): ?>
                <?php
                    $reservationFee   = round((float) $booking->total_amount * 0.20, 2);
                    $balanceOnArrival = max(0, (float) $booking->total_amount - $reservationFee);
                ?>
                <div class="receipt-rule-dashed"></div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Reservation Fee (20%)</span>
                    <span class="receipt-row-value receipt-mono">₱<?php echo e(number_format($reservationFee, 2)); ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Balance on Arrival</span>
                    <span class="receipt-row-value receipt-mono">₱<?php echo e(number_format($balanceOnArrival, 2)); ?></span>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking->payments->isNotEmpty()): ?>
                <div class="receipt-rule-dashed"></div>

                <h2 class="receipt-section-title">Payment History</h2>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $booking->payments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="receipt-payment" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'print-payment-'.e($payment->id).''; ?>wire:key="print-payment-<?php echo e($payment->id); ?>">
                        <div class="receipt-row">
                            <span class="receipt-row-label capitalize">
                                <?php echo e(str_replace('_', ' ', (string) $payment->payment_method)); ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment->payment_type === 'reservation'): ?>
                                    · Fee
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </span>
                            <span class="receipt-row-value receipt-mono">₱<?php echo e(number_format((float) $payment->amount, 2)); ?></span>
                        </div>
                        <p class="receipt-item-detail">
                            <?php echo e($payment->paid_at?->format('M j, Y · g:i A')
                                ?? $payment->created_at?->format('M j, Y · g:i A')
                                ?? '—'); ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment->reference_number): ?>
                                · Ref: <?php echo e($payment->reference_number); ?>

                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </p>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="receipt-rule-thick"></div>

            
            <div class="receipt-signature">
                <div class="receipt-signature-line"></div>
                <p class="receipt-signature-label">Authorized Signature</p>
            </div>

            
            <footer class="receipt-footer">
                <p class="receipt-footer-primary">Thank you for booking with us!</p>
                <p class="receipt-footer-meta">Generated <?php echo e(now()->format('M j, Y · g:i A')); ?></p>
                <p class="receipt-footer-ref receipt-mono">#<?php echo e($booking->booking_reference); ?></p>
            </footer>
        </div>
    </div>
    


    
    <script>
        window.addEventListener('load', function () {
            const params = new URLSearchParams(window.location.search);
            if (params.get('print') === '1') {
                setTimeout(function () { window.print(); }, 400);
            }
        });
    </script>
</body>
</html><?php /**PATH C:\laragon\www\Capstone\resources\views\public\pages\booking-receipt.blade.php ENDPATH**/ ?>