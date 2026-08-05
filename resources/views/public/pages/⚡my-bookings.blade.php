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
#[Title('My Bookings')]
class extends Component {

    public string $statusFilter = '';

    #[Computed]
    public function bookings()
    {
        return Booking::withoutGlobalScope(TenantScope::class)
            ->with([
                'customer'              => fn($q) => $q->withoutGlobalScope(TenantScope::class),
                'items'                 => fn($q) => $q->withoutGlobalScope(TenantScope::class),
                'services'              => fn($q) => $q->withoutGlobalScope(TenantScope::class),
                'payments'              => fn($q) => $q->withoutGlobalScope(TenantScope::class),
                'items.property'        => fn($q) => $q->withoutGlobalScope(TenantScope::class),
                'items.property.tenant',
                'items.property.images' => fn($q) => $q->withoutGlobalScope(TenantScope::class),
            ])
            ->whereHas('customer', fn($q) =>
                $q->withoutGlobalScope(TenantScope::class)->where('email', Auth::user()->email)
            )
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function counts()
    {
        $all = Booking::withoutGlobalScope(TenantScope::class)
            ->whereHas('customer', fn($q) =>
                $q->withoutGlobalScope(TenantScope::class)->where('email', Auth::user()->email)
            )->get();
        return [
            'all'        => $all->count(),
            'pending'    => $all->where('status', 'pending')->count(),
            'confirmed'  => $all->where('status', 'confirmed')->count(),
            'checked_in' => $all->where('status', 'checked_in')->count(),
            'completed'  => $all->where('status', 'completed')->count(),
            'cancelled'  => $all->where('status', 'cancelled')->count(),
        ];
    }

    public function statusClasses(string $status): array
    {
        return match($status) {
            'pending'    => ['dot' => 'bg-amber-400',  'badge' => 'bg-amber-400/12 text-amber-300 border-amber-400/25',   'bar' => 'bg-amber-400',  'label' => 'Pending'],
            'confirmed'  => ['dot' => 'bg-blue-400',   'badge' => 'bg-blue-400/12 text-blue-300 border-blue-400/25',      'bar' => 'bg-blue-400',   'label' => 'Confirmed'],
            'checked_in' => ['dot' => 'bg-violet-400', 'badge' => 'bg-violet-400/12 text-violet-300 border-violet-400/25','bar' => 'bg-violet-400', 'label' => 'Checked In'],
            'completed'  => ['dot' => 'bg-slate-400',  'badge' => 'bg-slate-400/12 text-slate-300 border-slate-400/25',   'bar' => 'bg-slate-400',  'label' => 'Completed'],
            'cancelled'  => ['dot' => 'bg-red-400',    'badge' => 'bg-red-400/12 text-red-300 border-red-400/25',         'bar' => 'bg-red-400',    'label' => 'Cancelled'],
            default      => ['dot' => 'bg-white/30',   'badge' => 'bg-white/8 text-white/40 border-white/15',             'bar' => 'bg-white/30',   'label' => ucfirst($status)],
        };
    }

    public function statusTimeline(): array
    {
        return ['pending', 'confirmed', 'checked_in', 'completed'];
    }
};
?>

@push('styles')
<style>
@keyframes fadeUp  { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
@keyframes fadeIn  { from{opacity:0}                             to{opacity:1} }
@keyframes countUp { from{opacity:0;transform:translateY(8px)}  to{opacity:1;transform:translateY(0)} }

.bk-card {
    background: rgba(255,255,255,0.035);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 20px; overflow: hidden;
    transition: border-color .3s ease, box-shadow .3s ease;
}
.bk-card:hover { border-color: rgba(255,255,255,.12); }
.bk-card.expanded { border-color: rgba(34,197,94,.2); box-shadow: 0 0 0 1px rgba(34,197,94,.08); }

.bk-status-bar { width: 4px; flex-shrink: 0; border-radius: 4px 0 0 4px; }

.tl-dot {
    width: 10px; height: 10px; border-radius: 50%;
    flex-shrink: 0; position: relative; z-index: 1;
    transition: transform .2s;
}
.tl-dot.active { transform: scale(1.3); }
.tl-line {
    flex: 1; height: 1px; background: rgba(255,255,255,.1);
}
.tl-line.done { background: rgba(34,197,94,.35); }

.filter-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 14px; border-radius: 9999px; font-size: 11px;
    font-weight: 700; text-transform: uppercase; letter-spacing: .08em;
    border: 1px solid rgba(255,255,255,.12); color: rgba(255,255,255,.45);
    background: transparent; cursor: pointer; transition: all .2s ease;
    white-space: nowrap;
}
.filter-pill:hover { border-color: rgba(255,255,255,.25); color: rgba(255,255,255,.75); }
.filter-pill.active { background: rgba(34,197,94,.12); border-color: rgba(34,197,94,.4); color: #86efac; }

.ref-code {
    font-family: ui-monospace, monospace; font-size: 12px;
    background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1);
    border-radius: 8px; padding: 4px 10px; color: rgba(255,255,255,.7);
    cursor: pointer; transition: all .2s; display: inline-flex; align-items: center; gap: 6px;
    letter-spacing: .05em;
}
.ref-code:hover { background: rgba(255,255,255,.09); color: #fff; border-color: rgba(34,197,94,.3); }

.pay-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,.05);
    font-size: 12px;
}
.pay-row:last-child { border-bottom: none; }

.expand-chevron { transition: transform .3s cubic-bezier(.34,1.56,.64,1); }
.expanded .expand-chevron { transform: rotate(180deg); }

.reveal { opacity:0; transform:translateY(18px);
          transition: opacity .6s cubic-bezier(.16,1,.3,1), transform .6s cubic-bezier(.16,1,.3,1); }
.reveal.in { opacity:1; transform:translateY(0); }
</style>
@endpush

<div class="relative z-10 min-h-screen"
     x-data='{ expanded: null }'
     x-init='setupReveal()'>

    {{-- HERO --}}
    <section class="relative py-20 md:py-28 overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-black/70 to-transparent pointer-events-none" aria-hidden="true"></div>
        <div class="absolute top-1/2 left-1/3 -translate-y-1/2 w-96 h-96 bg-brand-500/8 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>

        <div class="relative z-10 max-w-7xl mx-auto px-6 md:px-16">
            <div class="flex items-center gap-2 mb-3" style="animation:fadeUp .5s .05s both">
                <span class="w-5 h-px bg-brand-500"></span>
                <span class="text-xs tracking-[0.25em] uppercase text-brand-500 font-bold">Traveller Portal</span>
            </div>
            <h1 class="font-display text-4xl md:text-6xl font-semibold text-white leading-none" style="animation:fadeUp .5s .12s both">
                My <em class="italic bg-gradient-to-r from-brand-400 to-cyan-400 bg-clip-text text-transparent">Reservations</em>
            </h1>
            <p class="text-sm text-white/45 mt-4" style="animation:fadeUp .5s .18s both">
                All your bookings, stays, and travel history in one place.
            </p>

            @php $c = $this->counts; @endphp
            <div class="flex flex-wrap gap-8 mt-10 pt-6 border-t border-white/[0.08]" style="animation:fadeUp .5s .26s both">
                @foreach([
                    ['Total',     $c['all'],       ''],
                    ['Pending',   $c['pending'],   'text-amber-400'],
                    ['Confirmed', $c['confirmed'], 'text-blue-400'],
                    ['Completed', $c['completed'], 'text-slate-400'],
                ] as [$label, $num, $color])
                    <div>
                        <div class="font-display text-3xl font-medium {{ $color ?: 'text-brand-400' }}"
                             style="animation:countUp .6s {{ 0.3 + $loop->index * .06 }}s both">{{ $num }}</div>
                        <div class="text-[10px] uppercase tracking-widest text-white/35 mt-1">{{ $label }}</div>
                    </div>
                    @if(!$loop->last) <div class="w-px h-10 bg-white/[0.08] self-center"></div> @endif
                @endforeach
            </div>
        </div>
    </section>

    {{-- FILTER PILLS --}}
    <div class="max-w-7xl mx-auto px-6 md:px-16 py-6">
        <div class="flex gap-2 overflow-x-auto pb-2 reveal" style="scrollbar-width:none">
            @foreach([
                [''          , 'All',        $c['all']],
                ['pending'   , 'Pending',    $c['pending']],
                ['confirmed' , 'Confirmed',  $c['confirmed']],
                ['checked_in', 'Checked In', $c['checked_in']],
                ['completed' , 'Completed',  $c['completed']],
                ['cancelled' , 'Cancelled',  $c['cancelled']],
            ] as [$val, $label, $num])
                <button wire:click="$set('statusFilter','{{ $val }}')"
                        class="filter-pill shrink-0 {{ $statusFilter === $val ? 'active' : '' }}">
                    {{ $label }}
                    @if($num > 0)
                        <span class="w-4 h-4 rounded-full flex items-center justify-center text-[9px]
                                     {{ $statusFilter === $val ? 'bg-brand-500/30 text-brand-300' : 'bg-white/8 text-white/30' }}">
                            {{ $num }}
                        </span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    {{-- BOOKING LIST --}}
    <div class="max-w-7xl mx-auto px-6 md:px-16 pb-24">

        <div wire:loading.flex class="items-center justify-center py-16">
            <div class="flex items-center gap-3 text-white/30">
                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                Loading reservations…
            </div>
        </div>

        <div wire:loading.remove>
            @if($this->bookings->isEmpty())
                <div class="flex flex-col items-center justify-center py-24 text-center reveal">
                    <div class="relative w-24 h-24 mb-6">
                        <div class="absolute inset-0 rounded-full bg-brand-500/8 animate-ping" style="animation-duration:2.5s"></div>
                        <div class="relative w-24 h-24 rounded-full bg-white/[0.04] border border-white/[0.08] flex items-center justify-center">
                            <svg class="w-10 h-10 text-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </div>
                    <h3 class="font-display text-2xl italic text-white/35 mb-2">
                        {{ $statusFilter ? 'No ' . $statusFilter . ' bookings' : 'No reservations yet' }}
                    </h3>
                    <p class="text-white/25 text-sm max-w-xs leading-relaxed">
                        {{ $statusFilter ? 'Try a different filter to see other bookings.' : 'Your adventure starts with your first booking.' }}
                    </p>
                    <div class="flex gap-3 mt-6">
                        @if($statusFilter)
                            <button wire:click="$set('statusFilter','')"
                                    class="px-5 py-2.5 rounded-full border border-white/15 text-white/50 hover:text-white hover:border-white/30 text-xs font-bold uppercase tracking-wider transition">
                                Clear Filter
                            </button>
                        @endif
                        <a href="{{ route('explore.map') }}" wire:navigate
                           class="inline-flex items-center gap-2 px-6 py-2.5 rounded-full bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold uppercase tracking-wider transition shadow-lg shadow-brand-500/20">
                            Explore Destinations
                        </a>
                    </div>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($this->bookings as $index => $booking)
                        @php
                            $property     = $booking->items->first()?->property;
                            $businessName = $property?->tenant?->name ?? 'Business';
                            $businessSlug = $property?->tenant?->slug;
                            $paid         = $booking->payments->where('payment_status','paid')->sum('amount');
                            $balance      = $booking->total_amount - $paid;
                            $paidPct      = $booking->total_amount > 0 ? min(100, ($paid / $booking->total_amount) * 100) : 0;
                            $nights       = ($booking->check_in && $booking->check_out)
                                            ? max(1, $booking->check_in->diffInDays($booking->check_out)) : 0;
                            $imagePath    = $property?->images?->first()?->image_path;
                            $sc           = $this->statusClasses($booking->status);
                            $timeline     = $this->statusTimeline();
                            $currentStage = array_search($booking->status, $timeline);
                            $tenantLat    = $property?->tenant?->latitude;
                            $tenantLng    = $property?->tenant?->longitude;
                        @endphp

                        <div class="bk-card reveal" wire:key="bk-{{ $booking->id }}"
                             style="transition-delay:{{ $index * 55 }}ms"
                             :class="expanded === {{ $booking->id }} ? 'expanded' : ''">

                            {{-- Card header --}}
                            <div class="flex cursor-pointer select-none"
                                 @click="expanded = (expanded === {{ $booking->id }}) ? null : {{ $booking->id }}">

                                <div class="bk-status-bar {{ $sc['bar'] }}"></div>

                                <div class="w-24 sm:w-36 md:w-44 shrink-0 overflow-hidden">
                                    @if($imagePath)
                                        <img src="{{ asset('storage/'.$imagePath) }}"
                                             alt="{{ $property->name }}"
                                             class="w-full h-full object-cover"
                                             loading="lazy">
                                    @else
                                        <div class="w-full h-full min-h-[100px] bg-white/[0.04] flex items-center justify-center text-white/15">
                                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                        </div>
                                    @endif
                                </div>

                                <div class="flex-1 min-w-0 p-4 sm:p-5">
                                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                        <span class="font-mono text-[11px] text-white/35 bg-white/[0.04] border border-white/[0.07] rounded-lg px-2 py-0.5">
                                            #{{ $booking->booking_reference }}
                                        </span>
                                        <span class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full border {{ $sc['badge'] }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $sc['dot'] }} {{ $booking->status === 'pending' ? 'animate-pulse' : '' }}"></span>
                                            {{ $sc['label'] }}
                                        </span>
                                        @if($nights > 0)
                                            <span class="bg-brand-500/10 border border-brand-400/18 rounded-full px-2 py-0.5 text-[11px] text-white/55">
                                                {{ $nights }}N
                                            </span>
                                        @endif
                                    </div>

                                    <h3 class="font-display text-lg font-medium text-white leading-tight truncate">
                                        {{ $property?->name ?? 'Booking' }}
                                    </h3>
                                    <p class="text-sm text-white/40 mt-0.5">{{ $businessName }}</p>

                                    <div class="flex flex-wrap gap-4 mt-3 text-xs">
                                        <div>
                                            <span class="text-white/30 uppercase tracking-wider block text-[10px]">Check-in</span>
                                            <span class="text-white font-medium">{{ $booking->check_in?->format('M d, Y') ?? '—' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-white/30 uppercase tracking-wider block text-[10px]">Check-out</span>
                                            <span class="text-white font-medium">{{ $booking->check_out?->format('M d, Y') ?? '—' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-white/30 uppercase tracking-wider block text-[10px]">Total</span>
                                            <span class="text-white font-medium">₱{{ number_format($booking->total_amount, 2) }}</span>
                                        </div>
                                        <div>
                                            <span class="text-white/30 uppercase tracking-wider block text-[10px]">Paid</span>
                                            <span class="font-medium {{ $balance > 0 ? 'text-amber-400' : 'text-emerald-400' }}">
                                                ₱{{ number_format($paid, 2) }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <div class="w-full h-1 bg-white/[0.07] rounded-full overflow-hidden">
                                            <div class="h-full rounded-full transition-all duration-700"
                                                 style="width:{{ $paidPct }}%; background:{{ $paidPct >= 100 ? '#34d399' : '#fbbf24' }}"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-center w-12 shrink-0 text-white/25">
                                    <svg class="expand-chevron w-4 h-4" :class="expanded === {{ $booking->id }} ? 'rotate-180' : ''"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                            </div>

                            {{-- Expanded details --}}
                            <div x-show="expanded === {{ $booking->id }}"
                                 x-transition:enter="transition duration-300 ease-out"
                                 x-transition:enter-start="opacity-0 -translate-y-2"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition duration-200 ease-in"
                                 x-transition:leave-start="opacity-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 -translate-y-2"
                                 x-cloak
                                 class="border-t border-white/[0.07] p-5 md:p-6 grid grid-cols-1 md:grid-cols-3 gap-6">

                                {{-- Timeline --}}
                                <div class="md:col-span-3">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-white/30 mb-3">Booking Journey</p>
                                    <div class="flex items-center">
                                        @foreach($timeline as $ti => $stage)
                                            @php
                                                $stageSc    = $this->statusClasses($stage);
                                                $isDone     = $currentStage !== false && $ti <= $currentStage && $booking->status !== 'cancelled';
                                                $isCurrent  = $booking->status === $stage;
                                            @endphp
                                            <div class="flex flex-col items-center min-w-0 flex-1">
                                                <div class="tl-dot {{ $isDone ? $stageSc['dot'] : 'bg-white/10 border border-white/15' }} {{ $isCurrent ? 'active' : '' }}"></div>
                                                <span class="text-[9px] uppercase tracking-wider mt-1.5 text-center
                                                             {{ $isCurrent ? 'text-white font-bold' : ($isDone ? 'text-white/45' : 'text-white/20') }}">
                                                    {{ $stageSc['label'] }}
                                                </span>
                                            </div>
                                            @if($ti < count($timeline)-1)
                                                <div class="tl-line {{ ($currentStage !== false && $ti < $currentStage && $booking->status !== 'cancelled') ? 'done' : '' }} mb-4"></div>
                                            @endif
                                        @endforeach
                                        @if($booking->status === 'cancelled')
                                            <div class="flex-1"></div>
                                            <div class="flex flex-col items-center">
                                                <div class="tl-dot bg-red-400"></div>
                                                <span class="text-[9px] uppercase tracking-wider mt-1.5 text-red-400 font-bold">Cancelled</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Reference --}}
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-white/30 mb-2">Reference</p>
                                    <button class="ref-code"
                                            onclick="navigator.clipboard.writeText('{{ $booking->booking_reference }}').then(() => { this.textContent = '✓ Copied!'; setTimeout(() => this.innerHTML = `<svg xmlns='http://www.w3.org/2000/svg' width='11' height='11' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z'/></svg> {{ $booking->booking_reference }}`, 2000); })">
                                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        {{ $booking->booking_reference }}
                                    </button>
                                    <p class="text-[10px] text-white/25 mt-2">Booked {{ $booking->created_at?->format('M d, Y') }}</p>
                                </div>

                                {{-- Payment history --}}
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-white/30 mb-2">Payment History</p>
                                    @if($booking->payments->isEmpty())
                                        <p class="text-xs text-white/25 italic">No payment records yet.</p>
                                    @else
                                        @foreach($booking->payments as $payment)
                                            <div class="pay-row">
                                                <div>
                                                    <p class="text-white/65 font-medium capitalize">{{ $payment->payment_method }}</p>
                                                    <p class="text-white/30 text-[10px]">{{ $payment->paid_at?->format('M d, Y h:ia') ?? 'Pending' }}</p>
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-white font-semibold text-sm">₱{{ number_format($payment->amount, 2) }}</p>
                                                    <p class="text-[10px] {{ $payment->payment_status === 'paid' ? 'text-emerald-400' : 'text-amber-400' }}">
                                                        {{ ucfirst($payment->payment_status) }}
                                                    </p>
                                                </div>
                                            </div>
                                        @endforeach
                                        @if($balance > 0)
                                            <div class="mt-2 flex items-center gap-2 text-amber-300/70 text-[11px] bg-amber-400/8 border border-amber-400/20 rounded-lg px-3 py-2">
                                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                                ₱{{ number_format($balance, 2) }} balance due
                                            </div>
                                        @endif
                                    @endif
                                </div>

                                {{-- Actions --}}
                                <div class="flex flex-col gap-2 justify-start">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-white/30 mb-1">Actions</p>

                                    @if($businessSlug)
                                        {{-- View Spot → Offerings page --}}
                                        <a href="{{ route('business.offerings', $businessSlug) }}" wire:navigate
                                           class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-white/12 hover:bg-white/[0.06] text-white/60 hover:text-white text-xs font-semibold uppercase tracking-wider transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            View Spot
                                        </a>

                                        {{-- 🆕 Navigate in Map (auto directions) --}}
                                        @if($tenantLat && $tenantLng)
                                            <a href="{{ route('explore.map', ['fly_to' => $property?->tenant?->id, 'directions' => '1']) }}"
                                               wire:navigate
                                               class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-white/12 hover:bg-white/[0.06] text-white/60 hover:text-white text-xs font-semibold uppercase tracking-wider transition">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                Navigate in Map
                                            </a>
                                        @endif

                                        {{-- Get Directions (external Google Maps) --}}
                                        @if($tenantLat && $tenantLng)
                                            <a href="https://www.google.com/maps/dir/?api=1&destination={{ $tenantLat }},{{ $tenantLng }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-white/12 hover:bg-white/[0.06] text-white/60 hover:text-white text-xs font-semibold uppercase tracking-wider transition">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                Get Directions
                                            </a>
                                        @endif

                                        @if($balance > 0 && in_array($booking->status, ['pending','confirmed']))
                                            <a href="{{ route('business.offerings', $businessSlug) }}" wire:navigate
                                               class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold uppercase tracking-wider transition shadow-lg shadow-brand-600/20">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                                Pay Balance
                                            </a>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
    function setupReveal() {
        const obs = new IntersectionObserver(entries => {
            entries.forEach(e => { if(e.isIntersecting){ e.target.classList.add('in'); obs.unobserve(e.target); } });
        }, { threshold: .07 });
        document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
    }

    document.addEventListener('DOMContentLoaded', setupReveal);
    document.addEventListener('livewire:navigated', setupReveal);
</script>
@endpush