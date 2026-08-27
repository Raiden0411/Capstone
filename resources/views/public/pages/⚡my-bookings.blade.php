{{-- resources/views/public/pages/⚡my-bookings.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PayMongoService;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new
#[Layout('layouts.app')]
#[Title('My Bookings')]
class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public string $sortBy = 'newest';

    public function mount()
    {
        $this->cancelOverdueBookings();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingSortBy()
    {
        $this->resetPage();
    }

    public function cancelOverdueBookings()
    {
        $bookings = Booking::withoutGlobalScope(TenantScope::class)
            ->where('user_id', Auth::id())
            ->where('status', Booking::STATUS_PENDING)
            ->where('created_at', '<=', now()->subMinutes(Booking::PAYMENT_DEADLINE_MINUTES))
            ->get();

        foreach ($bookings as $booking) {
            if ($booking->isOverdue()) {
                $booking->update(['status' => Booking::STATUS_CANCELLED]);
            }
        }
    }

    public function cancelOverdue(Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) abort(403);

        if ($booking->isOverdue()) {
            $booking->update(['status' => Booking::STATUS_CANCELLED]);
            session()->flash('message', 'Booking cancelled due to payment timeout.');
        }
    }

    public function getBookingsProperty()
    {
        $query = Booking::withoutGlobalScope(TenantScope::class)
            ->with([
                'user',
                'items' => fn($q) => $q->withoutGlobalScope(TenantScope::class),
                'items.property' => fn($q) => $q->withoutGlobalScope(TenantScope::class),
                'items.property.tenant' => fn($q) => $q->withoutGlobalScope(TenantScope::class),
                'items.property.images' => fn($q) => $q->withoutGlobalScope(TenantScope::class),
                'services' => fn($q) => $q->withoutGlobalScope(TenantScope::class),
                'services.service' => fn($q) => $q->withoutGlobalScope(TenantScope::class),
                'payments' => fn($q) => $q->withoutGlobalScope(TenantScope::class),
            ])
            ->where('user_id', Auth::id());

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('booking_reference', 'like', '%' . $this->search . '%')
                  ->orWhereHas('items.property', function ($sub) {
                      $sub->withoutGlobalScope(TenantScope::class)
                          ->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }

        if ($this->statusFilter && $this->statusFilter !== 'all') {
            if (in_array($this->statusFilter, ['pending', 'confirmed', 'reserved', 'cancelled', 'completed'])) {
                $query->where('status', $this->statusFilter);
            } elseif ($this->statusFilter === 'upcoming') {
                $query->where('check_in', '>', now());
            } elseif ($this->statusFilter === 'ongoing') {
                $query->where('check_in', '<=', now())
                      ->where('check_out', '>=', now());
            } elseif ($this->statusFilter === 'past') {
                $query->where('check_out', '<', now());
            }
        }

        switch ($this->sortBy) {
            case 'newest':
                $query->latest();
                break;
            case 'oldest':
                $query->oldest();
                break;
            case 'check_in_asc':
                $query->orderBy('check_in', 'asc');
                break;
            case 'check_in_desc':
                $query->orderBy('check_in', 'desc');
                break;
        }

        return $query->paginate(6);
    }

    public function getCountsProperty()
    {
        $base = Booking::withoutGlobalScope(TenantScope::class)->where('user_id', Auth::id());

        return [
            'all'       => (clone $base)->count(),
            'pending'   => (clone $base)->where('status', Booking::STATUS_PENDING)->count(),
            'confirmed' => (clone $base)->where('status', Booking::STATUS_CONFIRMED)->count(),
            'reserved'  => (clone $base)->where('status', Booking::STATUS_RESERVED)->count(),
            'cancelled' => (clone $base)->where('status', Booking::STATUS_CANCELLED)->count(),
            'completed' => (clone $base)->where('status', Booking::STATUS_COMPLETED)->count(),
            'upcoming'  => (clone $base)->where('check_in', '>', now())->count(),
            'ongoing'   => (clone $base)->where('check_in', '<=', now())->where('check_out', '>=', now())->count(),
            'past'      => (clone $base)->where('check_out', '<', now())->count(),
        ];
    }

    public function getBookingClassification(Booking $booking): string
    {
        $now = now();
        if ($booking->check_out < $now) return 'past';
        if ($booking->check_in > $now) return 'upcoming';
        return 'ongoing';
    }

    public function payFull(Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) abort(403);
        if ($booking->status !== Booking::STATUS_PENDING || $booking->booking_type !== Booking::TYPE_FULL) return;

        if ($booking->isOverdue()) {
            $booking->update(['status' => Booking::STATUS_CANCELLED]);
            session()->flash('error', 'Payment deadline has passed. This booking has been cancelled.');
            return;
        }

        $payMongo = app(PayMongoService::class);
        $session = $payMongo->createCheckoutSession([
            'customer_name'        => $booking->user->name,
            'customer_email'       => $booking->user->email,
            'customer_phone'       => $booking->user->phone,
            'amount'               => $booking->total_amount,
            'description'          => "Full payment for Booking #{$booking->booking_reference}",
            'item_name'            => 'Activity Booking',
            'success_url'          => route('booking.payment.success', ['booking' => $booking->id]),
            'cancel_url'           => route('booking.payment.cancel', ['booking' => $booking->id]),
            'metadata'             => ['booking_id' => $booking->id],
            'payment_method_types' => ['gcash', 'paymaya', 'card'],
        ]);

        if (!$session) {
            session()->flash('error', 'Unable to initiate payment.');
            return;
        }

        $this->updateOrCreateUnpaidPayment($booking, $booking->total_amount, Booking::TYPE_FULL, $session['id']);
        return redirect()->away($session['checkout_url']);
    }

    public function payReservation(Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) abort(403);
        if ($booking->status !== Booking::STATUS_PENDING || $booking->booking_type !== Booking::TYPE_RESERVATION) return;

        if ($booking->isOverdue()) {
            $booking->update(['status' => Booking::STATUS_CANCELLED]);
            session()->flash('error', 'Payment deadline has passed. This booking has been cancelled.');
            return;
        }

        $reservationFee = round($booking->total_amount * 0.20, 2);

        $payMongo = app(PayMongoService::class);
        $session = $payMongo->createCheckoutSession([
            'customer_name'        => $booking->user->name,
            'customer_email'       => $booking->user->email,
            'customer_phone'       => $booking->user->phone,
            'amount'               => $reservationFee,
            'description'          => "Reservation fee for Booking #{$booking->booking_reference}",
            'item_name'            => 'Reservation Fee',
            'success_url'          => route('booking.payment.success', ['booking' => $booking->id]),
            'cancel_url'           => route('booking.payment.cancel', ['booking' => $booking->id]),
            'metadata'             => ['booking_id' => $booking->id],
            'payment_method_types' => ['gcash', 'paymaya', 'card'],
        ]);

        if (!$session) {
            session()->flash('error', 'Unable to initiate payment.');
            return;
        }

        $this->updateOrCreateUnpaidPayment($booking, $reservationFee, Booking::TYPE_RESERVATION, $session['id']);
        return redirect()->away($session['checkout_url']);
    }

    private function updateOrCreateUnpaidPayment(Booking $booking, float $amount, string $type, string $sessionId): void
    {
        $existing = Payment::withoutGlobalScope(TenantScope::class)
            ->where('booking_id', $booking->id)
            ->where('payment_status', 'unpaid')
            ->first();

        if ($existing) {
            $existing->update([
                'amount'              => $amount,
                'payment_type'        => $type,
                'paymongo_session_id' => $sessionId,
            ]);
        } else {
            Payment::create([
                'tenant_id'            => $booking->tenant_id,
                'booking_id'           => $booking->id,
                'amount'               => $amount,
                'payment_method'       => 'gcash',
                'payment_status'       => 'unpaid',
                'payment_type'         => $type,
                'paymongo_session_id'  => $sessionId,
            ]);
        }
    }

    public function getRemainingBalance(Booking $booking)
    {
        $paid = Payment::withoutGlobalScope(TenantScope::class)
            ->where('booking_id', $booking->id)
            ->where('payment_status', 'paid')
            ->sum('amount');

        return max(0, $booking->total_amount - $paid);
    }

    public function requestCancellation(Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) abort(403);

        if (in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_RESERVED])) {
            $booking->update(['status' => Booking::STATUS_CANCELLED]);
            session()->flash('message', 'Booking cancelled successfully.');
        } else {
            session()->flash('error', 'This booking cannot be cancelled.');
        }
    }

    public function printReceipt(Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) abort(403);

        return redirect()->route('booking.receipt', ['booking' => $booking->id]);
    }
};
?>

<div class="relative z-10 min-h-screen py-8 px-4 sm:px-6 lg:px-8 bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
    <div class="max-w-7xl mx-auto">

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">My Bookings</span>
                </div>
                <h1 class="font-display text-3xl md:text-4xl font-semibold text-gray-900 dark:text-white">
                    Your <em class="italic text-primary-600 dark:text-primary-400">Reservations</em>
                </h1>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                {{ $this->bookings->total() }} booking(s) found
            </div>
        </div>

        {{-- Flash messages --}}
        @if(session()->has('message'))
            <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-400/40 text-green-700 dark:text-green-200 p-4 rounded-2xl text-sm mb-6">
                {{ session('message') }}
            </div>
        @endif
        @if(session()->has('error'))
            <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-400/40 text-red-700 dark:text-red-200 p-4 rounded-2xl text-sm mb-6">
                {{ session('error') }}
            </div>
        @endif

        {{-- Filters & Search --}}
        @php $counts = $this->counts; @endphp
        <div class="mb-6 space-y-4">
            <div class="flex gap-2 overflow-x-auto pb-2" style="scrollbar-width:none">
                @foreach([
                    ['all',       'All',        $counts['all']],
                    ['pending',   'Pending',    $counts['pending']],
                    ['confirmed', 'Confirmed',  $counts['confirmed']],
                    ['reserved',  'Reserved',   $counts['reserved']],
                    ['upcoming',  'Upcoming',   $counts['upcoming']],
                    ['ongoing',   'Ongoing',    $counts['ongoing']],
                    ['past',      'Past',       $counts['past']],
                    ['cancelled', 'Cancelled',  $counts['cancelled']],
                ] as [$val, $label, $num])
                    <button wire:click="$set('statusFilter','{{ $val }}')"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-[11px] font-bold uppercase tracking-wide border transition shrink-0
                                   {{ $statusFilter === $val
                                       ? 'bg-primary-600 border-primary-600 text-white shadow-md shadow-primary-600/20'
                                       : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:border-gray-400 dark:hover:border-gray-600' }}">
                        {{ $label }}
                        @if($num > 0)
                            <span class="min-w-[18px] h-[18px] px-1 rounded-full flex items-center justify-center text-[9px] font-bold
                                         {{ $statusFilter === $val ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400' }}">
                                {{ $num }}
                            </span>
                        @endif
                    </button>
                @endforeach
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" wire:model.live.debounce.300ms="search"
                           placeholder="Search by reference or property name..."
                           class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl py-2.5 pl-10 pr-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-600/50 transition">
                </div>
                <select wire:model.live="sortBy"
                        class="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-600/50 transition appearance-none">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="check_in_asc">Check-in Date (Ascending)</option>
                    <option value="check_in_desc">Check-in Date (Descending)</option>
                </select>
            </div>
        </div>

        {{-- Bookings List --}}
        <div class="grid grid-cols-1 gap-6">
            @forelse($this->bookings as $booking)
                @php
                    $property = $booking->items->first()->property ?? null;
                    $tenant = $property?->tenant;
                    $imagePath = $property?->images?->first()?->image_path;
                    $logoPath = $tenant?->logo;
                    $paid = $booking->payments->where('payment_status', 'paid')->sum('amount');
                    $balance = $booking->total_amount - $paid;
                    $deadline = $booking->getPaymentDeadlineAttribute();
                    $services = $booking->services;
                    $classification = $this->getBookingClassification($booking);
                @endphp

                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden shadow-sm"
                     x-data="{ open: false }">

                    {{-- Card Header --}}
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4 p-5 cursor-pointer select-none"
                         @click="open = !open"
                         :aria-expanded="open.toString()">

                        {{-- Spot Logo / Photo --}}
                        @if($logoPath)
                            <img src="{{ asset('storage/'.$logoPath) }}"
                                 alt="{{ $tenant->name ?? 'Business' }}"
                                 class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl object-cover border border-gray-200 dark:border-gray-700 shrink-0">
                        @elseif($imagePath)
                            <img src="{{ asset('storage/'.$imagePath) }}"
                                 alt="{{ $property?->name ?? 'Property' }}"
                                 class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl object-cover border border-gray-200 dark:border-gray-700 shrink-0">
                        @else
                            <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl flex items-center justify-center bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-500/30 text-primary-700 dark:text-primary-300 font-display text-2xl font-bold shrink-0">
                                {{ strtoupper(substr($property?->name ?? 'B', 0, 1)) }}
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider
                                    @if($booking->status === 'confirmed') bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-300
                                    @elseif($booking->status === 'reserved') bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-300
                                    @elseif($booking->status === 'pending') bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300
                                    @elseif($booking->status === 'cancelled') bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-300
                                    @else bg-gray-100 text-gray-800 dark:bg-gray-500/20 dark:text-gray-300 @endif">
                                    {{ $booking->status }}
                                </span>

                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide
                                    @if($classification === 'upcoming') bg-cyan-100 text-cyan-800 dark:bg-cyan-500/20 dark:text-cyan-300
                                    @elseif($classification === 'ongoing') bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300
                                    @else bg-gray-100 text-gray-800 dark:bg-gray-500/20 dark:text-gray-300 @endif">
                                    {{ ucfirst($classification) }}
                                </span>

                                <span class="text-xs font-mono text-gray-500 dark:text-gray-400">{{ $booking->booking_reference }}</span>
                            </div>

                            <h3 class="text-lg font-bold text-gray-900 dark:text-white mt-1">
                                {{ $property?->name ?? 'Booking' }}
                                @if($tenant)
                                    <span class="text-sm font-normal text-gray-500 dark:text-gray-400">· {{ $tenant->name }}</span>
                                @endif
                            </h3>

                            <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                                {{ \Carbon\Carbon::parse($booking->check_in)->format('M d, Y') }} →
                                {{ \Carbon\Carbon::parse($booking->check_out)->format('M d, Y') }}
                                · {{ max(1, \Carbon\Carbon::parse($booking->check_in)->diffInDays($booking->check_out)) }} day(s)
                            </p>

                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Total: ₱{{ number_format($booking->total_amount, 2) }}
                                @if($booking->status === 'reserved')
                                    · Balance on arrival: ₱{{ number_format($this->getRemainingBalance($booking), 2) }}
                                @endif
                            </p>
                        </div>

                        <div class="flex flex-col items-end gap-2 sm:shrink-0">
                            {{-- Countdown & payment only if balance due --}}
                            @if($booking->status === 'pending' && $balance > 0)
                                <div class="text-xs text-amber-700 dark:text-amber-300"
                                     x-data="{
                                         deadline: @js($deadline?->toISOString()),
                                         remaining: null,
                                         timer: null,
                                         init() {
                                             this.updateRemaining();
                                             this.timer = setInterval(() => this.updateRemaining(), 1000);
                                         },
                                         updateRemaining() {
                                             const diff = new Date(this.deadline) - new Date();
                                             if (diff <= 0) {
                                                 clearInterval(this.timer);
                                                 $wire.cancelOverdue({{ $booking->id }});
                                                 return;
                                             }
                                             this.remaining = Math.floor(diff / 1000);
                                         },
                                         formatTime(seconds) {
                                             const m = Math.floor(seconds / 60);
                                             const s = seconds % 60;
                                             return `${m}:${s.toString().padStart(2, '0')}`;
                                         }
                                     }">
                                    <span>Expires in:</span>
                                    <span x-text="remaining !== null ? formatTime(remaining) : ''"></span>
                                </div>

                                @if($booking->booking_type === 'full')
                                    <button wire:click.stop="payFull({{ $booking->id }})"
                                            wire:loading.attr="disabled"
                                            class="bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold uppercase tracking-wider py-2 px-5 rounded-full transition shadow-md shadow-primary-600/20 disabled:opacity-60 disabled:cursor-not-allowed">
                                        <span wire:loading.remove>Pay Now</span>
                                        <span wire:loading>Processing…</span>
                                    </button>
                                @else
                                    <button wire:click.stop="payReservation({{ $booking->id }})"
                                            wire:loading.attr="disabled"
                                            class="bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold uppercase tracking-wider py-2 px-5 rounded-full transition shadow-md shadow-primary-600/20 disabled:opacity-60 disabled:cursor-not-allowed">
                                        <span wire:loading.remove>Pay Reservation Fee (20%)</span>
                                        <span wire:loading>Processing…</span>
                                    </button>
                                @endif
                            @elseif($booking->status === 'pending' && $balance <= 0)
                                <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                    Payment complete – awaiting confirmation
                                </span>
                            @elseif($tenant && $booking->status !== 'cancelled')
                                <a href="{{ route('explore.map', ['marker' => $tenant->id, 'directions' => '1']) }}"
                                   wire:navigate
                                   class="inline-flex items-center gap-1.5 bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold uppercase tracking-wider py-2 px-5 rounded-full transition shadow-md shadow-primary-600/20">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                                    Get Directions
                                </a>
                            @endif

                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $booking->booking_type === 'full' ? 'Full Payment' : 'Reservation (20%)' }}
                            </span>
                        </div>
                    </div>

                    {{-- Expanded Details --}}
                    <div x-show="open"
                         x-collapse
                         x-cloak
                         class="border-t border-gray-200 dark:border-gray-700 p-5 md:p-6 bg-gray-50 dark:bg-gray-900/40">

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                            {{-- Property Details --}}
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">Booking Details</p>
                                @if($property)
                                    @if($imagePath)
                                        <div class="w-full h-40 rounded-xl overflow-hidden mb-3">
                                            <img src="{{ asset('storage/'.$imagePath) }}"
                                                 class="w-full h-full object-cover"
                                                 alt="{{ $property->name }}">
                                        </div>
                                    @endif
                                    <h4 class="text-base font-bold text-gray-900 dark:text-white">{{ $property->name }}</h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $tenant->name ?? 'Business' }}</p>
                                    @if($property->propertyType)
                                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $property->propertyType->name }}</p>
                                    @endif

                                    <div class="mt-3 space-y-1 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-gray-500 dark:text-gray-400">Check-in</span>
                                            <span class="text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($booking->check_in)->format('M d, Y') }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-500 dark:text-gray-400">Check-out</span>
                                            <span class="text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($booking->check_out)->format('M d, Y') }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-500 dark:text-gray-400">Duration</span>
                                            <span class="text-gray-900 dark:text-white">{{ max(1, \Carbon\Carbon::parse($booking->check_in)->diffInDays($booking->check_out)) }} day(s)</span>
                                        </div>
                                    </div>

                                    @if($tenant)
                                        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 text-sm space-y-1">
                                            <p class="text-gray-500 dark:text-gray-400 font-medium">Contact</p>
                                            @if($tenant->contact_number)
                                                <a href="tel:{{ $tenant->contact_number }}" class="text-primary-600 dark:text-primary-400 hover:underline">{{ $tenant->contact_number }}</a>
                                            @endif
                                            @if($tenant->email)
                                                <a href="mailto:{{ $tenant->email }}" class="text-primary-600 dark:text-primary-400 hover:underline block">{{ $tenant->email }}</a>
                                            @endif
                                        </div>
                                    @endif
                                @else
                                    <p class="text-sm text-gray-400 dark:text-gray-500 italic">No property information available.</p>
                                @endif
                            </div>

                            {{-- Services & Payment Breakdown --}}
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">Services & Payments</p>
                                @if($services->isNotEmpty())
                                    <div class="space-y-2">
                                        @foreach($services as $bookingService)
                                            @if($bookingService->service)
                                                <div class="flex justify-between text-sm">
                                                    <span class="text-gray-600 dark:text-gray-300">{{ $bookingService->service->name }} ×{{ $bookingService->quantity }}</span>
                                                    <span class="text-gray-900 dark:text-white">₱{{ number_format($bookingService->subtotal, 2) }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-sm text-gray-400 dark:text-gray-500 italic">No extra services.</p>
                                @endif

                                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500 dark:text-gray-400">Total Amount</span>
                                        <span class="text-gray-900 dark:text-white font-semibold">₱{{ number_format($booking->total_amount, 2) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500 dark:text-gray-400">Paid</span>
                                        <span class="{{ $balance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">₱{{ number_format($paid, 2) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500 dark:text-gray-400">Balance</span>
                                        <span class="text-gray-900 dark:text-white font-semibold">₱{{ number_format($balance, 2) }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Payment History & Quick Actions --}}
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">Payment History</p>
                                @if($booking->payments->isNotEmpty())
                                    <div class="space-y-2">
                                        @foreach($booking->payments as $payment)
                                            <div class="p-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                                <div class="flex justify-between text-xs">
                                                    <span class="text-gray-600 dark:text-gray-300 capitalize">{{ $payment->payment_method }}</span>
                                                    <span class="text-gray-900 dark:text-white">₱{{ number_format($payment->amount, 2) }}</span>
                                                </div>
                                                <div class="flex justify-between text-[10px] mt-1">
                                                    <span class="text-gray-400 dark:text-gray-500">{{ $payment->payment_type === 'full' ? 'Full' : 'Reservation' }}</span>
                                                    <span class="{{ $payment->payment_status === 'paid' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">{{ ucfirst($payment->payment_status) }}</span>
                                                </div>
                                                @if($payment->reference_number)
                                                    <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">Ref: {{ $payment->reference_number }}</p>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-sm text-gray-400 dark:text-gray-500 italic">No payments yet.</p>
                                @endif

                                <div class="mt-4 space-y-2">
                                    @if($booking->status === 'pending' && $balance > 0)
                                        @if($booking->booking_type === 'full')
                                            <button wire:click="payFull({{ $booking->id }})"
                                                    wire:loading.attr="disabled"
                                                    class="w-full py-2 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold uppercase tracking-wider transition disabled:opacity-60">
                                                <span wire:loading.remove>Pay Full Amount</span>
                                                <span wire:loading>Processing…</span>
                                            </button>
                                        @else
                                            <button wire:click="payReservation({{ $booking->id }})"
                                                    wire:loading.attr="disabled"
                                                    class="w-full py-2 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold uppercase tracking-wider transition disabled:opacity-60">
                                                <span wire:loading.remove>Pay Reservation Fee (20%)</span>
                                                <span wire:loading>Processing…</span>
                                            </button>
                                        @endif
                                    @endif

                                    <a href="{{ route('tenant.show', $tenant->slug ?? '#') }}" wire:navigate
                                       class="block text-center py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white text-xs font-semibold uppercase tracking-wider transition">
                                        View Business
                                    </a>

                                    @if($tenant && $booking->status !== 'cancelled')
                                        <a href="{{ route('explore.map', ['marker' => $tenant->id, 'directions' => '1']) }}"
                                           wire:navigate
                                           class="flex items-center justify-center gap-1.5 py-2 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold uppercase tracking-wider transition shadow-md shadow-primary-600/20">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                                            Get Directions
                                        </a>
                                    @endif

                                    <a href="{{ route('booking.receipt', $booking) }}"
                                       wire:navigate
                                       class="block text-center py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white text-xs font-semibold uppercase tracking-wider transition">
                                        Print Receipt
                                    </a>

                                    <a href="https://calendar.google.com/calendar/render?action=TEMPLATE&text={{ urlencode($property?->name ?? 'Booking') }}&dates={{ \Carbon\Carbon::parse($booking->check_in)->format('Ymd\THis') }}/{{ \Carbon\Carbon::parse($booking->check_out)->format('Ymd\THis') }}&details={{ urlencode('Booking reference: '.$booking->booking_reference) }}"
                                       target="_blank"
                                       class="block text-center py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white text-xs font-semibold uppercase tracking-wider transition">
                                        Add to Calendar
                                    </a>

                                    @if(in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_RESERVED]))
                                        <button wire:click="requestCancellation({{ $booking->id }})"
                                                wire:confirm="Are you sure you want to cancel this booking?"
                                                class="w-full py-2 rounded-xl border border-red-300 dark:border-red-500/40 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 text-xs font-bold uppercase tracking-wider transition">
                                            Cancel Booking
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Receipt-style footer --}}
                        <div class="mt-6 pt-4 border-t border-dashed border-gray-300 dark:border-gray-700 flex justify-between items-center">
                            <span class="text-xs text-gray-400 dark:text-gray-500 font-mono">#{{ $booking->booking_reference }}</span>
                            <span class="text-xs text-gray-400 dark:text-gray-500">Thank you for booking with us!</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-24 text-gray-500 dark:text-gray-400">
                    <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-center">
                        <svg class="w-8 h-8 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="font-display text-2xl italic text-gray-400 dark:text-gray-500 mb-2">No bookings found</h3>
                    <p class="text-gray-500 dark:text-gray-400 text-sm max-w-xs mx-auto mb-6">Try adjusting your filters or start planning your next trip.</p>
                    <a href="{{ route('explore.map') }}" wire:navigate
                       class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold uppercase tracking-wider transition shadow-lg shadow-primary-600/20">
                        Explore Destinations
                    </a>
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if($this->bookings->hasPages())
            <div class="mt-8">
                {{ $this->bookings->links() }}
            </div>
        @endif
    </div>
</div>