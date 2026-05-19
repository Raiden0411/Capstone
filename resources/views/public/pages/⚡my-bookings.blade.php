{{-- resources/views/public/pages/my-bookings.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Booking;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;

new
#[Layout('layouts.app')]
#[Title('My Bookings · Victorias City')]
class extends Component
{
    public string $statusFilter = '';

    #[Computed]
    public function bookings()
    {
        $q = Booking::withoutGlobalScope(TenantScope::class)
            ->with([
                'customer'              => fn($q) => $q->withoutGlobalScope(TenantScope::class),
                'items'                 => fn($q) => $q->withoutGlobalScope(TenantScope::class),
                'payments'              => fn($q) => $q->withoutGlobalScope(TenantScope::class),
                'items.property'        => fn($q) => $q->withoutGlobalScope(TenantScope::class),
                'items.property.tenant',
                'items.property.images' => fn($q) => $q->withoutGlobalScope(TenantScope::class),
            ])
            ->whereHas('customer', fn($q) =>
                $q->withoutGlobalScope(TenantScope::class)
                  ->where('email', Auth::user()->email)
            )
            ->orderByDesc('created_at');

        if ($this->statusFilter) {
            $q->where('status', $this->statusFilter);
        }

        return $q->get();
    }

    #[Computed]
    public function counts(): array
    {
        $all = Booking::withoutGlobalScope(TenantScope::class)
            ->whereHas('customer', fn($q) =>
                $q->withoutGlobalScope(TenantScope::class)
                  ->where('email', Auth::user()->email)
            )
            ->pluck('status');

        return [
            'total'     => $all->count(),
            'pending'   => $all->filter(fn($s) => $s === 'pending')->count(),
            'confirmed' => $all->filter(fn($s) => $s === 'confirmed')->count(),
            'completed' => $all->filter(fn($s) => $s === 'completed')->count(),
            'cancelled' => $all->filter(fn($s) => $s === 'cancelled')->count(),
        ];
    }

    public function statusBadgeClasses(string $status): string
    {
        return match ($status) {
            'pending'    => 'bg-amber-500/[0.12]  border border-amber-500/30   text-amber-300',
            'confirmed'  => 'bg-blue-500/[0.12]   border border-blue-500/30    text-blue-300',
            'checked_in' => 'bg-violet-500/[0.12] border border-violet-500/30  text-violet-300',
            'completed'  => 'bg-emerald-500/[0.12] border border-emerald-500/30 text-emerald-300',
            'cancelled'  => 'bg-red-500/[0.12]    border border-red-500/30     text-red-300',
            default      => 'bg-zinc-500/[0.12]   border border-zinc-500/30    text-zinc-300',
        };
    }

    public function statusAccentClass(string $status): string
    {
        return match ($status) {
            'pending'    => 'bg-amber-500',
            'confirmed'  => 'bg-blue-500',
            'checked_in' => 'bg-violet-500',
            'completed'  => 'bg-emerald-500',
            'cancelled'  => 'bg-red-500',
            default      => 'bg-zinc-600',
        };
    }

    public function statusDotClass(string $status): string
    {
        return match ($status) {
            'pending'    => 'bg-amber-400',
            'confirmed'  => 'bg-blue-400',
            'checked_in' => 'bg-violet-400',
            'completed'  => 'bg-emerald-400',
            'cancelled'  => 'bg-red-400',
            default      => 'bg-zinc-500',
        };
    }

    public function statusStepIndex(string $status): int
    {
        return match ($status) {
            'pending'    => 0,
            'confirmed'  => 1,
            'checked_in' => 2,
            'completed'  => 3,
            'cancelled'  => -1,
            default      => 0,
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'pending'    => 'Pending',
            'confirmed'  => 'Confirmed',
            'checked_in' => 'Checked In',
            'completed'  => 'Completed',
            'cancelled'  => 'Cancelled',
            default      => ucwords(str_replace('_', ' ', $status)),
        };
    }
};
?>

<div class="relative z-10 min-h-screen">

    {{-- ══════ HERO ══════ --}}
    <section class="relative py-20 md:py-28 overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-black/70 via-black/40 to-transparent pointer-events-none"></div>
        <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[600px] h-[400px]
                    bg-emerald-500/[0.06] rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative max-w-7xl mx-auto px-6 md:px-16">
            <div class="flex items-center gap-2 mb-4">
                <span class="w-6 h-px bg-emerald-500"></span>
                <span class="text-[11px] font-bold uppercase tracking-[0.22em] text-emerald-500">
                    Traveller Portal
                </span>
            </div>

            <h1 class="font-display text-4xl md:text-6xl font-semibold text-white leading-none tracking-tight">
                My <em class="italic bg-gradient-to-r from-emerald-400 to-cyan-400
                               bg-clip-text text-transparent not-italic">
                    Reservations
                </em>
            </h1>
            <p class="text-sm text-zinc-500 mt-3 max-w-md">
                All your bookings, stays, and travel history in one place.
            </p>

            {{-- Stats --}}
            @php $c = $this->counts; @endphp
            <div class="flex flex-wrap items-center gap-8 mt-10 pt-8 border-t border-white/[0.06]">
                @foreach([
                    ['Total',     $c['total'],     'text-emerald-400'],
                    ['Pending',   $c['pending'],   'text-amber-400'],
                    ['Confirmed', $c['confirmed'], 'text-blue-400'],
                    ['Completed', $c['completed'], 'text-emerald-400'],
                    ['Cancelled', $c['cancelled'], 'text-red-400'],
                ] as [$label, $val, $color])
                    <div class="text-center md:text-left">
                        <div class="font-display text-3xl font-bold {{ $color }}">{{ $val }}</div>
                        <div class="text-[10px] uppercase tracking-[0.18em] text-zinc-600 mt-1">{{ $label }}</div>
                    </div>
                    @if(!$loop->last)
                        <div class="hidden md:block w-px h-10 bg-white/[0.06]"></div>
                    @endif
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════ STICKY FILTER BAR ══════ --}}
    <div class="sticky top-[64px] z-20 bg-[rgba(10,14,24,0.92)] backdrop-blur-xl border-b border-white/[0.06]">
        <div class="max-w-7xl mx-auto px-6 md:px-16 py-3 flex gap-2 overflow-x-auto scrollbar-none">
            @php
                $filterOptions = [
                    ''           => ['All',        $c['total']],
                    'pending'    => ['Pending',     $c['pending']],
                    'confirmed'  => ['Confirmed',   $c['confirmed']],
                    'checked_in' => ['Checked In',  null],
                    'completed'  => ['Completed',   $c['completed']],
                    'cancelled'  => ['Cancelled',   $c['cancelled']],
                ];
            @endphp

            @foreach($filterOptions as $val => [$label, $count])
                <button wire:click="$set('statusFilter','{{ $val }}')"
                        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded-full
                               text-[11px] font-bold uppercase tracking-wider transition-all border
                               {{ $statusFilter === $val
                                   ? 'bg-emerald-600 border-emerald-600 text-white shadow-lg shadow-emerald-500/20'
                                   : 'border-white/[0.08] text-zinc-600 hover:border-white/20 hover:text-white' }}">
                    {{ $label }}
                    @if($count !== null && $count > 0)
                        <span class="inline-flex items-center justify-center min-w-[16px] h-4 px-1
                                     rounded-full text-[9px] font-bold
                                     {{ $statusFilter === $val ? 'bg-white/20 text-white' : 'bg-white/[0.06] text-zinc-500' }}">
                            {{ $count }}
                        </span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    {{-- ══════ BOOKING LIST ══════ --}}
    <div class="max-w-7xl mx-auto px-6 md:px-16 py-10 pb-24">
        @if($this->bookings->isEmpty())
            {{-- Empty state --}}
            <div class="text-center py-24">
                <div class="relative inline-flex mb-8">
                    <div class="w-20 h-20 rounded-2xl bg-white/[0.03] border border-white/[0.06]
                                flex items-center justify-center">
                        <svg class="w-9 h-9 text-zinc-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2"
                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0
                                     00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>
                <h3 class="text-2xl font-semibold text-white/40">
                    {{ $statusFilter ? 'No ' . $this->statusLabel($statusFilter) . ' bookings' : 'No reservations yet' }}
                </h3>
                <p class="text-sm text-zinc-600 mt-2 max-w-sm mx-auto">
                    {{ $statusFilter
                        ? 'Try a different filter or clear it to see all bookings.'
                        : 'Your travel story starts with your first reservation.' }}
                </p>
                <div class="flex items-center justify-center gap-3 mt-8">
                    @if($statusFilter)
                        <button wire:click="$set('statusFilter','')"
                                class="px-6 py-2.5 rounded-full border border-white/10 text-white/60
                                       hover:border-white/30 hover:text-white text-sm font-semibold
                                       uppercase tracking-wider transition">
                            ← Clear Filter
                        </button>
                    @else
                        <a href="{{ route('explore.map') }}" wire:navigate
                           class="px-6 py-2.5 rounded-full bg-emerald-600 hover:bg-emerald-500 text-white
                                  text-sm font-semibold uppercase tracking-wider transition
                                  shadow-lg shadow-emerald-500/20">
                            Explore Map
                        </a>
                    @endif
                </div>
            </div>
        @else
            <div class="space-y-3">
                @foreach($this->bookings as $booking)
                    @php
                        $property     = $booking->items->first()?->property;
                        $businessName = $property?->tenant?->name ?? 'Business';
                        $businessSlug = $property?->tenant?->slug;
                        $paid         = $booking->payments->where('payment_status','paid')->sum('amount');
                        $balance      = $booking->total_amount - $paid;
                        $paidPct      = $booking->total_amount > 0
                                          ? min(100, ($paid / $booking->total_amount) * 100)
                                          : 0;
                        $nights       = $booking->check_in && $booking->check_out
                                          ? max(1, $booking->check_in->diffInDays($booking->check_out))
                                          : 0;
                        $imagePath    = $property?->images?->first()?->image_path;
                        $status       = $booking->status;
                        $stepIdx      = $this->statusStepIndex($status);
                        $isCancelled  = $status === 'cancelled';
                        $isCompleted  = $status === 'completed';
                    @endphp

                    <div wire:key="bk-{{ $booking->id }}"
                         x-data="{ expanded: false }"
                         class="rounded-2xl overflow-hidden border border-white/[0.06] bg-white/[0.025]
                                hover:bg-white/[0.035] transition-colors duration-200 group">

                        {{-- ─ Card header (always visible) ─ --}}
                        <div class="grid grid-cols-1 sm:grid-cols-[auto_1fr_auto]">

                            {{-- Thumbnail --}}
                            <div class="relative sm:w-44 h-40 sm:h-auto overflow-hidden flex-shrink-0">
                                <div class="absolute left-0 top-0 bottom-0 w-1 z-10 {{ $this->statusAccentClass($status) }}"></div>

                                @if($imagePath)
                                    <img src="{{ asset('storage/'.$imagePath) }}"
                                         alt="{{ $property?->name }}"
                                         class="w-full h-full object-cover brightness-90
                                                group-hover:brightness-100 transition-all duration-300"
                                         loading="lazy">
                                @else
                                    <div class="w-full h-full bg-white/[0.03] flex items-center justify-center">
                                        <svg class="w-10 h-10 text-zinc-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2"
                                                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9
                                                     0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0
                                                     011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                        </svg>
                                    </div>
                                @endif
                            </div>

                            {{-- Main info --}}
                            <div class="px-5 py-4 flex flex-col min-w-0">
                                {{-- Top row: ref + badge --}}
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-zinc-600">
                                        #{{ $booking->booking_reference }}
                                    </span>
                                    <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full
                                                 {{ $this->statusBadgeClasses($status) }}">
                                        {{ $this->statusLabel($status) }}
                                    </span>
                                    @if($nights > 0)
                                        <span class="text-[11px] px-2.5 py-0.5 rounded-full
                                                     bg-white/[0.05] border border-white/[0.08] text-zinc-400">
                                            {{ $nights }} night{{ $nights !== 1 ? 's' : '' }}
                                        </span>
                                    @endif
                                </div>

                                <h3 class="text-lg font-semibold text-white leading-tight truncate">
                                    {{ $property?->name ?? 'Booking' }}
                                </h3>
                                <p class="text-xs text-zinc-500 mt-0.5">{{ $businessName }}</p>

                                {{-- Key details grid --}}
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 text-sm">
                                    <div>
                                        <span class="block text-[10px] uppercase tracking-wider text-zinc-600 mb-0.5">Check-in</span>
                                        <span class="font-medium text-white">
                                            {{ $booking->check_in?->format('M d, Y') ?? '—' }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="block text-[10px] uppercase tracking-wider text-zinc-600 mb-0.5">Check-out</span>
                                        <span class="font-medium text-white">
                                            {{ $booking->check_out?->format('M d, Y') ?? '—' }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="block text-[10px] uppercase tracking-wider text-zinc-600 mb-0.5">Total</span>
                                        <span class="font-medium text-white">
                                            ₱{{ number_format($booking->total_amount, 2) }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="block text-[10px] uppercase tracking-wider text-zinc-600 mb-0.5">Paid</span>
                                        <span class="font-semibold {{ $balance > 0 ? 'text-amber-400' : 'text-emerald-400' }}">
                                            ₱{{ number_format($paid, 2) }}
                                        </span>
                                        @if($balance > 0)
                                            <span class="block text-[10px] text-red-400 mt-0.5">
                                                ₱{{ number_format($balance, 2) }} due
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Payment progress bar --}}
                                <div class="mt-4">
                                    <div class="flex justify-between items-center text-[10px] uppercase tracking-wider
                                                text-zinc-600 mb-1.5">
                                        <span>Payment Progress</span>
                                        <span>{{ round($paidPct) }}%</span>
                                    </div>
                                    <div class="w-full h-1 bg-white/[0.06] rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-700"
                                             style="width:{{ $paidPct }}%;
                                                    background:{{ $paidPct >= 100 ? '#34d399' : '#fbbf24' }};"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Actions column --}}
                            <div class="px-4 py-4 border-t sm:border-t-0 sm:border-l border-white/[0.05]
                                        flex flex-col justify-between items-stretch gap-2 min-w-[152px]">

                                {{-- Expand/collapse toggle --}}
                                <button @click="expanded = !expanded"
                                        class="w-full flex items-center justify-center gap-1.5 px-4 py-2
                                               rounded-xl border border-white/[0.08] bg-white/[0.03]
                                               text-[11px] font-bold uppercase tracking-wider text-zinc-400
                                               hover:bg-white/[0.06] hover:text-white transition">
                                    <span x-text="expanded ? 'Hide Details' : 'View Details'"></span>
                                    <svg class="w-3 h-3 transition-transform duration-200"
                                         :class="expanded ? 'rotate-180' : ''"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>

                                @if($businessSlug)
                                    {{-- ✅ Changed to offerings page --}}
                                    <a href="{{ route('business.offerings', $businessSlug) }}" wire:navigate
                                       class="w-full px-4 py-2 rounded-xl border border-white/[0.08]
                                              text-center text-[11px] font-bold uppercase tracking-wider
                                              text-zinc-400 hover:text-white hover:bg-white/[0.06] transition">
                                        View Spot
                                    </a>

                                    @if($balance > 0 && in_array($status, ['pending','confirmed']))
                                        <a href="{{ route('business.offerings', $businessSlug) }}" wire:navigate
                                           class="w-full px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500
                                                  text-center text-[11px] font-bold uppercase tracking-wider
                                                  text-white transition shadow-lg shadow-emerald-500/15">
                                            Pay Balance
                                        </a>
                                    @endif
                                @endif

                                @if($isCompleted)
                                    <button onclick="window.print()"
                                            class="w-full px-4 py-2 rounded-xl border border-white/[0.08]
                                                   text-center text-[11px] font-bold uppercase tracking-wider
                                                   text-zinc-500 hover:text-white hover:border-white/20 transition">
                                        Receipt
                                    </button>
                                @endif

                                <span class="text-center text-[10px] text-zinc-700">
                                    {{ $booking->created_at?->format('M d, Y') }}
                                </span>
                            </div>
                        </div>

                        {{-- ─ Expanded detail panel ─ --}}
                        <div x-show="expanded"
                             x-transition:enter="transition ease-out duration-250"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 -translate-y-1"
                             class="border-t border-white/[0.05] px-5 py-5 grid grid-cols-1 lg:grid-cols-3 gap-6">

                            {{-- ── Status Timeline ── --}}
                            <div class="lg:col-span-3">
                                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-zinc-600 mb-4">
                                    Booking Journey
                                </p>

                                @if($isCancelled)
                                    {{-- Cancelled state --}}
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-6 h-6 rounded-full bg-emerald-500/20 border border-emerald-500/40
                                                        flex items-center justify-center text-[10px] text-emerald-400">✓</div>
                                            <span class="text-xs text-zinc-500">Placed</span>
                                        </div>
                                        <div class="flex-1 h-px bg-red-500/30 relative">
                                            <div class="absolute inset-y-0 left-0 right-0 flex items-center justify-center">
                                                <span class="text-[9px] text-red-400 bg-[rgba(10,14,24,1)] px-2">Cancelled</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-6 h-6 rounded-full bg-red-500/20 border border-red-500/40
                                                        flex items-center justify-center text-[10px] text-red-400">✕</div>
                                            <span class="text-xs text-zinc-500">Cancelled</span>
                                        </div>
                                    </div>
                                @else
                                    @php
                                        $timelineSteps = [
                                            ['pending',    'Pending',    'Booking received'],
                                            ['confirmed',  'Confirmed',  'Vendor confirmed'],
                                            ['checked_in', 'Checked In', 'Guest arrived'],
                                            ['completed',  'Completed',  'Stay complete'],
                                        ];
                                    @endphp
                                    <div class="flex items-start">
                                        @foreach($timelineSteps as $i => [$sKey, $sLabel, $sDesc])
                                            @php
                                                $isPast    = $i < $stepIdx;
                                                $isCurrent = $i === $stepIdx;
                                                $isFuture  = $i > $stepIdx;
                                            @endphp
                                            <div class="flex flex-col items-center flex-1">
                                                {{-- Connector line (before dot) --}}
                                                @if(!$loop->first)
                                                    <div class="w-full h-px mt-3 mb-1 -mx-1
                                                                {{ $isPast || $isCurrent ? 'bg-emerald-500/50' : 'bg-white/[0.06]' }}"></div>
                                                @endif
                                            </div>

                                            {{-- Dot + label (wrapped to prevent flex issues) --}}
                                            <div class="flex flex-col items-center shrink-0 w-20">
                                                <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center
                                                            text-[10px] font-bold transition-all
                                                            {{ $isPast    ? 'bg-emerald-500 border-emerald-500 text-white' : '' }}
                                                            {{ $isCurrent ? 'bg-transparent border-emerald-500 text-emerald-400' : '' }}
                                                            {{ $isFuture  ? 'bg-transparent border-zinc-700   text-zinc-600' : '' }}">
                                                    @if($isPast) ✓
                                                    @elseif($isCurrent) ●
                                                    @else ○
                                                    @endif
                                                </div>
                                                <span class="mt-1.5 text-[10px] font-bold text-center leading-tight
                                                             {{ $isCurrent ? 'text-emerald-400' : ($isPast ? 'text-zinc-400' : 'text-zinc-700') }}">
                                                    {{ $sLabel }}
                                                </span>
                                                <span class="text-[9px] text-zinc-700 text-center leading-tight mt-0.5 hidden md:block">
                                                    {{ $sDesc }}
                                                </span>
                                            </div>

                                            @if(!$loop->last)
                                                <div class="flex-1 h-px mt-3
                                                            {{ $isPast ? 'bg-emerald-500/50' : 'bg-white/[0.06]' }}"></div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            {{-- ── Booked Items ── --}}
                            <div class="lg:col-span-2">
                                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-zinc-600 mb-3">
                                    Items Booked
                                </p>
                                @if($booking->items->isEmpty())
                                    <p class="text-xs text-zinc-600">No items on record.</p>
                                @else
                                    <div class="space-y-2">
                                        @foreach($booking->items as $item)
                                            <div class="flex items-center justify-between py-2.5 px-3
                                                        rounded-xl bg-white/[0.025] border border-white/[0.05]">
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-sm font-medium text-white truncate">
                                                        {{ $item->property?->name ?? 'Service' }}
                                                    </p>
                                                    <p class="text-[11px] text-zinc-600 mt-0.5">
                                                        @if($item->quantity && $item->quantity > 1)
                                                            {{ $item->quantity }}× ·
                                                        @endif
                                                        {{ $item->property?->tenant?->name ?? '' }}
                                                    </p>
                                                </div>
                                                <span class="text-sm font-semibold text-white ml-4 flex-shrink-0">
                                                    ₱{{ number_format($item->amount ?? 0, 2) }}
                                                </span>
                                            </div>
                                        @endforeach

                                        <div class="flex justify-between items-center pt-2 px-3">
                                            <span class="text-[11px] uppercase tracking-wider text-zinc-600">Total</span>
                                            <span class="text-base font-bold text-white">
                                                ₱{{ number_format($booking->total_amount, 2) }}
                                            </span>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- ── Payment History ── --}}
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-zinc-600 mb-3">
                                    Payment History
                                </p>
                                @if($booking->payments->isEmpty())
                                    <div class="rounded-xl bg-amber-500/[0.06] border border-amber-500/20 p-3">
                                        <p class="text-xs text-amber-400 font-semibold">No payments recorded yet.</p>
                                        @if($businessSlug && $balance > 0)
                                            <a href="{{ route('business.offerings', $businessSlug) }}"
                                               wire:navigate
                                               class="inline-block mt-2 text-[11px] text-emerald-400
                                                      hover:text-emerald-300 underline transition">
                                                Make a payment →
                                            </a>
                                        @endif
                                    </div>
                                @else
                                    <div class="space-y-2">
                                        @foreach($booking->payments as $payment)
                                            <div class="flex items-center justify-between py-2 px-3
                                                        rounded-xl bg-white/[0.025] border border-white/[0.05]">
                                                <div>
                                                    <p class="text-xs font-semibold text-white capitalize">
                                                        {{ $payment->payment_method ?? 'Payment' }}
                                                    </p>
                                                    <p class="text-[10px] text-zinc-600">
                                                        {{ $payment->created_at?->format('M d, Y') }}
                                                    </p>
                                                </div>
                                                <div class="text-right">
                                                    <span class="text-sm font-bold
                                                                 {{ $payment->payment_status === 'paid' ? 'text-emerald-400' : 'text-amber-400' }}">
                                                        ₱{{ number_format($payment->amount, 2) }}
                                                    </span>
                                                    <p class="text-[9px] uppercase tracking-wider
                                                               {{ $payment->payment_status === 'paid' ? 'text-emerald-600' : 'text-amber-600' }} mt-0.5">
                                                        {{ $payment->payment_status }}
                                                    </p>
                                                </div>
                                            </div>
                                        @endforeach

                                        {{-- Balance due --}}
                                        @if($balance > 0)
                                            <div class="flex items-center justify-between py-2 px-3
                                                        rounded-xl bg-red-500/[0.06] border border-red-500/20">
                                                <span class="text-xs text-red-400 font-bold uppercase tracking-wider">
                                                    Balance Due
                                                </span>
                                                <span class="text-sm font-bold text-red-300">
                                                    ₱{{ number_format($balance, 2) }}
                                                </span>
                                            </div>
                                        @else
                                            <div class="flex items-center gap-2 py-2 px-3
                                                        rounded-xl bg-emerald-500/[0.06] border border-emerald-500/20">
                                                <svg class="w-3.5 h-3.5 text-emerald-400" fill="none"
                                                     stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                                </svg>
                                                <span class="text-xs text-emerald-400 font-bold uppercase tracking-wider">
                                                    Fully Paid
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                {{-- Special notes --}}
                                @if($booking->notes)
                                    <div class="mt-4">
                                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-zinc-600 mb-2">
                                            Notes
                                        </p>
                                        <p class="text-xs text-zinc-500 bg-white/[0.025] border border-white/[0.05]
                                                  rounded-xl px-3 py-2.5 leading-relaxed">
                                            {{ $booking->notes }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>