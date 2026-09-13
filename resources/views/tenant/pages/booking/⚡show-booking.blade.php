{{-- resources/views/tenant/pages/booking/⚡show-booking.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

new
#[Layout('tenant.layouts.app')]
#[Title('Booking Details')]
class extends Component
{
    /** Bound from route. Auto-locked (Eloquent model). */
    public Booking $booking;

    public function mount(Booking $booking): void
    {
        abort_unless($booking->tenant_id === Auth::user()->tenant_id, 403, 'Unauthorized.');

        $booking->load([
            'tenant',
            'user',
            'items.property',
            'services.service',
            'payments',
        ]);

        $this->booking = $booking;
    }

    // ─────────────────────────────────────────────────────────
    //  Computed
    // ─────────────────────────────────────────────────────────

    #[Computed]
    public function paidAmount(): float
    {
        return (float) $this->booking->payments
            ->where('payment_status', 'paid')
            ->sum('amount');
    }

    #[Computed]
    public function balance(): float
    {
        return max(0, (float) $this->booking->total_amount - $this->paidAmount);
    }

    #[Computed]
    public function isSettled(): bool
    {
        return $this->balance <= 0;
    }

    #[Computed]
    public function isReservation(): bool
    {
        return $this->booking->booking_type === Booking::TYPE_RESERVATION;
    }

    #[Computed]
    public function days(): int
    {
        return max(1, (int) $this->booking->check_in->diffInDays($this->booking->check_out));
    }

    #[Computed]
    public function deadline(): ?\Carbon\Carbon
    {
        return $this->booking->status === Booking::STATUS_PENDING
            ? $this->booking->payment_deadline
            : null;
    }

    #[Computed]
    public function statusMeta(): array
    {
        return match ($this->booking->status) {
            Booking::STATUS_PENDING    => ['label' => 'Pending',    'stamp' => 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-500/40',   'stripe' => 'bg-amber-500',  'dot' => 'bg-amber-500'],
            Booking::STATUS_RESERVED   => ['label' => 'Reserved',   'stamp' => 'bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-300 dark:border-blue-500/40',         'stripe' => 'bg-blue-500',   'dot' => 'bg-blue-500'],
            Booking::STATUS_CONFIRMED  => ['label' => 'Confirmed',  'stamp' => 'bg-green-50 dark:bg-green-500/10 text-green-700 dark:text-green-300 border-green-300 dark:border-green-500/40',   'stripe' => 'bg-green-500',  'dot' => 'bg-green-500'],
            Booking::STATUS_CHECKED_IN => ['label' => 'Checked In', 'stamp' => 'bg-purple-50 dark:bg-purple-500/10 text-purple-700 dark:text-purple-300 border-purple-300 dark:border-purple-500/40', 'stripe' => 'bg-purple-500', 'dot' => 'bg-purple-500'],
            Booking::STATUS_COMPLETED  => ['label' => 'Completed',  'stamp' => 'bg-slate-50 dark:bg-slate-500/10 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-500/40',   'stripe' => 'bg-slate-500',  'dot' => 'bg-slate-500'],
            Booking::STATUS_CANCELLED  => ['label' => 'Cancelled',  'stamp' => 'bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-300 border-red-300 dark:border-red-500/40',               'stripe' => 'bg-red-500',    'dot' => 'bg-red-500'],
            default                    => ['label' => ucfirst((string) $this->booking->status), 'stamp' => 'bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600', 'stripe' => 'bg-gray-400', 'dot' => 'bg-gray-400'],
        };
    }

    #[Computed]
    public function canDelete(): bool
    {
        return !in_array($this->booking->status, [
            Booking::STATUS_COMPLETED,
            Booking::STATUS_CANCELLED,
        ], true);
    }
};
?>

<div x-data="{ confirmDelete: false }" class="p-4 sm:p-6 lg:p-8 max-w-3xl mx-auto space-y-6">

    {{-- ═══════════════════════════════════════════════════════════
         Page header — action bar (hidden when printing)
         ═══════════════════════════════════════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700 no-print">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                Bookings
            </p>
            <h1 class="mt-1 text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
                Booking Details
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 font-mono">
                #{{ $booking->booking_reference }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('tenant.bookings.index') }}" wire:navigate
               class="btn-secondary text-xs sm:text-sm active:scale-95 transition-transform
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                      inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back
            </a>

            <livewire:tenant::pages.payment.quick-pay :booking="$booking" />

            @if(!$this->isSettled && !in_array($booking->status, [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED], true))
                <a href="{{ route('tenant.payments.create', ['booking' => $booking->id]) }}" wire:navigate
                   class="btn-primary text-xs sm:text-sm active:scale-95 transition-transform
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                          inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 9v2m0-3.5c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Record Payment
                </a>
            @endif

            <a href="{{ route('tenant.bookings.edit', $booking->id) }}" wire:navigate
               class="btn-secondary text-xs sm:text-sm active:scale-95 transition-transform
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                      inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit
            </a>

            <button type="button" onclick="window.print()"
                    class="btn-secondary text-xs sm:text-sm active:scale-95 transition-transform
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                           inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/>
                </svg>
                Print
            </button>

            @if($this->canDelete)
                <button type="button" @click="confirmDelete = true"
                        class="text-xs sm:text-sm active:scale-95 transition-transform
                               inline-flex items-center gap-2 px-4 py-2.5 rounded-full
                               bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30
                               text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-500/20 font-semibold
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Delete
                </button>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         Receipt card — the printable artifact
         ═══════════════════════════════════════════════════════════ --}}
    <div class="receipt-card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">

        {{-- Status stripe --}}
        <div class="h-1.5 {{ $this->statusMeta['stripe'] }}"></div>

        <div class="p-6 sm:p-8">

            {{-- ─── Receipt header ─────────────────────────────── --}}
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-12 h-12 rounded-lg bg-primary-600 text-white flex items-center justify-center font-bold text-lg shrink-0">
                        {{ strtoupper(substr($booking->tenant?->name ?? 'B', 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-gray-400 dark:text-gray-500">
                            Booking Receipt
                        </p>
                        <p class="font-mono font-bold text-gray-900 dark:text-white mt-0.5 truncate">
                            #{{ $booking->booking_reference }}
                        </p>
                        @if($booking->tenant?->name)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                {{ $booking->tenant->name }}
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Status stamp --}}
                <div class="shrink-0 text-right">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-[11px] font-bold uppercase tracking-wider border-2
                                 {{ $this->statusMeta['stamp'] }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $this->statusMeta['dot'] }}"></span>
                        {{ $this->statusMeta['label'] }}
                    </span>
                    <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1.5">
                        {{ $this->isReservation ? 'Reservation (20% fee)' : 'Full Payment' }}
                    </p>
                </div>
            </div>

            {{-- ─── Live expiry timer (visible inline, hidden on print) ── --}}
            @if($this->deadline)
                <div class="mt-4 no-print"
                     x-data="{
                         deadline: {{ $this->deadline->timestamp * 1000 }},
                         timerText: '',
                         isExpired: false,
                         update() {
                             const diff = this.deadline - Date.now();
                             if (diff <= 0) {
                                 this.isExpired = true;
                                 this.timerText = 'Payment overdue';
                                 return;
                             }
                             const m = Math.floor(diff / 60000);
                             const s = Math.floor((diff % 60000) / 1000);
                             this.timerText = `Payment due in ${m}m ${s.toString().padStart(2, '0')}s`;
                         }
                     }"
                     x-init="update(); setInterval(() => update(), 1000)"
                     :class="isExpired
                         ? 'text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-950/40 border-red-200 dark:border-red-800'
                         : 'text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800/50'"
                     class="text-xs font-semibold px-3 py-2 rounded-lg border tabular-nums flex items-center gap-2">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span x-text="timerText"></span>
                </div>
            @endif

            {{-- ─── Meta grid: guest + stay ───────────────────── --}}
            <div class="mt-6 pt-6 border-t border-dashed border-gray-300 dark:border-gray-600 grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-5">

                {{-- Guest --}}
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500 mb-2">
                        Guest
                    </p>
                    @if($booking->user)
                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                            {{ $booking->user->name }}
                        </p>
                        @if($booking->user->phone)
                            <a href="tel:{{ $booking->user->phone }}"
                               class="text-xs text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors block truncate mt-0.5">
                                {{ $booking->user->phone }}
                            </a>
                        @endif
                        @if($booking->user->email)
                            <a href="mailto:{{ $booking->user->email }}"
                               class="text-xs text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors block truncate mt-0.5">
                                {{ $booking->user->email }}
                            </a>
                        @endif
                    @else
                        <p class="text-sm text-gray-400 dark:text-gray-500 italic">Walk-in · no profile</p>
                    @endif
                </div>

                {{-- Stay --}}
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500 mb-2">
                        Stay
                    </p>
                    <dl class="space-y-1 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Check-in</dt>
                            <dd class="text-gray-900 dark:text-white font-medium text-right">
                                {{ $booking->check_in->format('M d, Y') }}
                                <span class="text-gray-500 dark:text-gray-400 font-normal">· {{ $booking->check_in->format('h:i A') }}</span>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Check-out</dt>
                            <dd class="text-gray-900 dark:text-white font-medium text-right">
                                {{ $booking->check_out->format('M d, Y') }}
                                <span class="text-gray-500 dark:text-gray-400 font-normal">· {{ $booking->check_out->format('h:i A') }}</span>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500 dark:text-gray-400">Duration</dt>
                            <dd class="text-gray-900 dark:text-white font-medium text-right">
                                {{ $this->days }} {{ Str::plural('night', $this->days) }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- ─── Activities ─────────────────────────────────── --}}
            @if($booking->items->isNotEmpty())
                <div class="mt-6 pt-6 border-t border-dashed border-gray-300 dark:border-gray-600">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500 mb-3">
                        Activities
                    </p>
                    <div class="space-y-3">
                        @foreach($booking->items as $item)
                            <div wire:key="item-{{ $item->id }}" class="flex justify-between items-start gap-4 text-sm">
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 dark:text-white truncate">
                                        {{ $item->property?->name ?? 'Unknown Activity' }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 tabular-nums">
                                        ₱{{ number_format((float) $item->price, 2) }} × {{ $this->days }} {{ Str::plural('day', $this->days) }}
                                        @if((int) $item->quantity > 1)
                                            × {{ $item->quantity }}
                                        @endif
                                    </p>
                                </div>
                                <p class="font-mono font-semibold text-gray-900 dark:text-white tabular-nums shrink-0">
                                    ₱{{ number_format((float) $item->subtotal, 2) }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ─── Services ───────────────────────────────────── --}}
            @if($booking->services->isNotEmpty())
                <div class="mt-6 pt-6 border-t border-dashed border-gray-300 dark:border-gray-600">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500 mb-3">
                        Services
                    </p>
                    <div class="space-y-2.5">
                        @foreach($booking->services as $service)
                            <div wire:key="service-{{ $service->id }}" class="flex justify-between items-start gap-4 text-sm">
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 dark:text-white truncate">
                                        {{ $service->service?->name ?? 'Unknown Service' }}
                                    </p>
                                    @if((int) $service->quantity > 1)
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            Qty: {{ $service->quantity }}
                                        </p>
                                    @endif
                                </div>
                                <p class="font-mono font-semibold text-gray-900 dark:text-white tabular-nums shrink-0">
                                    ₱{{ number_format((float) $service->subtotal, 2) }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ─── Totals ─────────────────────────────────────── --}}
            <div class="mt-6 pt-6 border-t-2 border-gray-900 dark:border-gray-300">
                <dl class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Subtotal</dt>
                        <dd class="font-mono text-gray-900 dark:text-white tabular-nums">
                            ₱{{ number_format((float) $booking->total_amount, 2) }}
                        </dd>
                    </div>

                    <div class="flex justify-between text-base font-bold pt-2">
                        <dt class="text-gray-900 dark:text-white">Total</dt>
                        <dd class="font-mono text-gray-900 dark:text-white tabular-nums">
                            ₱{{ number_format((float) $booking->total_amount, 2) }}
                        </dd>
                    </div>

                    @if($this->paidAmount > 0)
                        <div class="flex justify-between text-sm pt-1">
                            <dt class="text-emerald-600 dark:text-emerald-400">Amount Paid</dt>
                            <dd class="font-mono text-emerald-600 dark:text-emerald-400 tabular-nums">
                                −₱{{ number_format($this->paidAmount, 2) }}
                            </dd>
                        </div>
                    @endif

                    <div class="flex justify-between items-baseline pt-3 mt-2 border-t-2 border-dashed border-gray-300 dark:border-gray-600">
                        <dt class="text-sm font-bold uppercase tracking-wider {{ $this->isSettled ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' }}">
                            {{ $this->isSettled ? 'Paid in Full' : 'Balance Due' }}
                        </dt>
                        <dd class="font-mono font-bold text-lg tabular-nums {{ $this->isSettled ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                            ₱{{ number_format($this->balance, 2) }}
                        </dd>
                    </div>
                </dl>

                {{-- Progress bar --}}
                <div class="mt-4">
                    <div class="w-full h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500 {{ $this->isSettled ? 'bg-emerald-500' : 'bg-primary-600' }}"
                             style="width: {{ (float) $booking->total_amount > 0
                                 ? min(100, ($this->paidAmount / (float) $booking->total_amount) * 100)
                                 : 0 }}%;"></div>
                    </div>
                    <div class="flex justify-between text-[11px] mt-1.5 text-gray-500 dark:text-gray-400">
                        <span>{{ number_format((float) $booking->total_amount > 0 ? ($this->paidAmount / (float) $booking->total_amount) * 100 : 0, 0) }}% paid</span>
                        <span>{{ $this->isSettled ? 'Settled' : '₱' . number_format($this->balance, 2) . ' remaining' }}</span>
                    </div>
                </div>

                {{-- Reservation split --}}
                @if($this->isReservation)
                    @php
                        $reservationFee   = round((float) $booking->total_amount * 0.20, 2);
                        $balanceOnArrival = max(0, (float) $booking->total_amount - $reservationFee);
                    @endphp
                    <div class="mt-5 pt-5 border-t border-dashed border-gray-300 dark:border-gray-600 grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500">
                                Reservation Fee (20%)
                            </p>
                            <p class="font-mono font-bold text-gray-900 dark:text-white mt-1 tabular-nums">
                                ₱{{ number_format($reservationFee, 2) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500">
                                Balance on Arrival
                            </p>
                            <p class="font-mono font-bold text-amber-600 dark:text-amber-400 mt-1 tabular-nums">
                                ₱{{ number_format($balanceOnArrival, 2) }}
                            </p>
                        </div>
                    </div>
                @endif
            </div>

            {{-- ─── Payment history ────────────────────────────── --}}
            <div class="mt-6 pt-6 border-t border-dashed border-gray-300 dark:border-gray-600">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500 mb-3">
                    Payment History
                </p>

                @if($booking->payments->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($booking->payments as $payment)
                            <div wire:key="payment-{{ $payment->id }}" class="text-sm">
                                <div class="flex justify-between items-start gap-4">
                                    <div class="min-w-0">
                                        <p class="text-gray-900 dark:text-white font-medium capitalize">
                                            {{ str_replace('_', ' ', (string) $payment->payment_method) }}
                                            <span class="text-gray-400 dark:text-gray-500 font-normal">·</span>
                                            <span class="text-gray-600 dark:text-gray-400 font-normal">
                                                {{ $payment->payment_type === 'reservation' ? 'Reservation Fee' : 'Full Payment' }}
                                            </span>
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            {{ $payment->paid_at?->format('M d, Y · h:i A')
                                                ?? $payment->created_at?->format('M d, Y · h:i A')
                                                ?? '—' }}
                                        </p>
                                    </div>
                                    <p class="font-mono font-semibold tabular-nums shrink-0 {{ $payment->payment_status === 'paid' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                                        ₱{{ number_format((float) $payment->amount, 2) }}
                                    </p>
                                </div>

                                @if($payment->reference_number || $payment->paymongo_session_id)
                                    <p class="text-[10px] text-gray-400 dark:text-gray-500 font-mono mt-1 truncate">
                                        @if($payment->reference_number)
                                            Ref: {{ $payment->reference_number }}
                                        @endif
                                        @if($payment->reference_number && $payment->paymongo_session_id)
                                            <span class="mx-1">·</span>
                                        @endif
                                        @if($payment->paymongo_session_id)
                                            PayMongo: {{ $payment->paymongo_session_id }}
                                        @endif
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-400 dark:text-gray-500 italic">
                        No payments recorded.
                    </p>
                @endif
            </div>

            {{-- ─── Footer ─────────────────────────────────────── --}}
            <div class="mt-8 pt-6 border-t border-dashed border-gray-300 dark:border-gray-600 text-center">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                    Thank you for booking with us!
                </p>
                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">
                    Generated {{ now()->format('M d, Y · h:i A') }}
                </p>
            </div>

        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         Delete confirmation modal
         ═══════════════════════════════════════════════════════════ --}}
    <div x-show="confirmDelete"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @keydown.escape.window="confirmDelete = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm no-print"
         role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
        <div @click.outside="confirmDelete = false"
             class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 max-w-md w-full shadow-2xl">
            <div class="flex items-start gap-3 mb-4">
                <div class="shrink-0 w-10 h-10 rounded-full bg-red-50 dark:bg-red-500/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 id="delete-modal-title" class="text-lg font-bold text-gray-900 dark:text-white">
                        Delete Booking?
                    </h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Are you sure you want to delete booking
                        <strong class="text-gray-900 dark:text-white font-mono">#{{ $booking->booking_reference }}</strong>?
                        This action cannot be undone.
                    </p>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" @click="confirmDelete = false"
                        class="btn-secondary text-sm active:scale-95 transition-transform
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    Cancel
                </button>
                <form action="{{ route('tenant.bookings.destroy', $booking->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="px-4 py-2.5 rounded-full bg-red-600 hover:bg-red-500 text-white font-semibold text-sm transition shadow-md shadow-red-500/20
                                   active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/50">
                        Confirm Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }

    @media print {
        @page { margin: 1cm; size: auto; }
        html, body { background: #fff !important; color: #000 !important; }
        .no-print { display: none !important; }

        .receipt-card {
            max-width: 100% !important;
            margin: 0 !important;
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            background: #fff !important;
        }
        .receipt-card > .h-1\.5 { height: 3px !important; }

        .bg-white, .dark\:bg-gray-800, .dark\:bg-gray-800\/90,
        .bg-gray-50, .dark\:bg-gray-700\/50 {
            background: transparent !important;
            border-color: #d1d5db !important;
            box-shadow: none !important;
        }
        .text-gray-900, .text-gray-700, .text-gray-600, .text-gray-500, .text-gray-400,
        .dark\:text-white, .dark\:text-gray-200, .dark\:text-gray-300, .dark\:text-gray-400 {
            color: #000 !important;
        }
        table { border-collapse: collapse !important; width: 100% !important; }
        th, td { border-bottom: 1px solid #e5e7eb !important; }
    }
</style>