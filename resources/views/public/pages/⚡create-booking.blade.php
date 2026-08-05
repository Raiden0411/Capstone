{{-- resources/views/public/pages/booking/create.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\Property;
use App\Models\Service;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\BookingItem;
use App\Models\BookingService;
use App\Models\Payment;
use App\Services\PayMongoService;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

new
#[Layout('layouts.app')]
#[Title('Complete Your Booking')]
class extends Component
{
    public Property $property;

    #[Validate('required|string|max:255')]
    public $customerName    = '';
    #[Validate('nullable|string|max:20')]
    public $customerPhone   = '';
    #[Validate('nullable|email|max:255')]
    public $customerEmail   = '';
    #[Validate('nullable|string')]
    public $customerAddress = '';

    #[Validate('required|date|after_or_equal:today')]
    public $check_in;
    #[Validate('required|date|after:check_in')]
    public $check_out;

    public $selectedServices = [];
    public $totalAmount      = 0;
    public $totalDays        = 1;

    #[Validate('required|in:cash,card,gcash,paymaya')]
    public $payment_method = 'cash';

    public function mount($publicproperty)
    {
        $this->property = Property::with('tenant')
            ->withoutGlobalScope(TenantScope::class)
            ->findOrFail($publicproperty);

        if (!$this->property->tenant || !$this->property->tenant_id) abort(404);

        if (Auth::check()) {
            $this->customerName  = Auth::user()->name;
            $this->customerEmail = Auth::user()->email;
        }

        $this->check_in  = now()->format('Y-m-d');
        $this->check_out = now()->addDay()->format('Y-m-d');
        $this->calculateTotal();
    }

    public function updatedCheckIn()
    {
        $max = now()->addDays(30)->format('Y-m-d');
        if ($this->check_in > $max) $this->check_in = $max;
        if ($this->check_out && Carbon::parse($this->check_in)->gte(Carbon::parse($this->check_out))) {
            $this->check_out = Carbon::parse($this->check_in)->addDay()->format('Y-m-d');
        }
        $this->calculateTotal();
    }

    public function updatedCheckOut()
    {
        $max = now()->addDays(30)->format('Y-m-d');
        if ($this->check_out > $max) $this->check_out = $max;
        if (Carbon::parse($this->check_out)->lte(Carbon::parse($this->check_in))) {
            $this->check_out = Carbon::parse($this->check_in)->addDay()->format('Y-m-d');
        }
        $this->calculateTotal();
    }

    public function addService($serviceId)
    {
        $this->selectedServices[$serviceId] = ($this->selectedServices[$serviceId] ?? 0) + 1;
        $this->calculateTotal();
    }

    public function removeService($serviceId)
    {
        unset($this->selectedServices[$serviceId]);
        $this->calculateTotal();
    }

    public function calculateTotal()
    {
        $in  = Carbon::parse($this->check_in);
        $out = Carbon::parse($this->check_out);
        $this->totalDays   = max(1, $in->diffInDays($out));
        $this->totalAmount = $this->property->price * $this->totalDays;
        foreach ($this->selectedServices as $serviceId => $qty) {
            $svc = Service::find($serviceId);
            if ($svc) $this->totalAmount += $svc->price * $qty;
        }
    }

    public function getAvailableServicesProperty()
    {
        return Service::where('tenant_id', $this->property->tenant_id)
            ->withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function submit()
    {
        $this->validate();
        $tenantId = $this->property->tenant_id;
        if (!$tenantId) { session()->flash('error', 'Property not linked to a valid business.'); return; }

        $conflict = BookingItem::where('property_id', $this->property->id)
            ->whereHas('booking', fn($q) =>
                $q->whereNotIn('status', ['cancelled','completed'])
                  ->where('check_in', '<', $this->check_out)
                  ->where('check_out', '>', $this->check_in)
            )->exists();

        if ($conflict) { session()->flash('error', 'These dates are not available. Please choose different dates.'); return; }

        $redirect = DB::transaction(function () use ($tenantId) {
            $customer = Customer::firstOrCreate(
                ['email' => $this->customerEmail, 'tenant_id' => $tenantId],
                ['name' => $this->customerName, 'phone' => $this->customerPhone, 'address' => $this->customerAddress]
            );
            $booking = Booking::create([
                'tenant_id'         => $tenantId,
                'customer_id'       => $customer->id,
                'booking_reference' => 'BK-' . strtoupper(Str::random(8)),
                'check_in'          => $this->check_in,
                'check_out'         => $this->check_out,
                'total_amount'      => $this->totalAmount,
                'status'            => 'pending',
            ]);
            BookingItem::create([
                'tenant_id'   => $tenantId,
                'booking_id'  => $booking->id,
                'property_id' => $this->property->id,
                'price'       => $this->property->price,
                'quantity'    => 1,
                'subtotal'    => $this->property->price * $this->totalDays,
            ]);
            foreach ($this->selectedServices as $serviceId => $qty) {
                $svc = Service::find($serviceId);
                if ($svc) BookingService::create([
                    'tenant_id'  => $tenantId,
                    'booking_id' => $booking->id,
                    'service_id' => $serviceId,
                    'quantity'   => $qty,
                    'subtotal'   => $svc->price * $qty,
                ]);
            }

            if ($this->payment_method === 'cash') {
                Payment::create([
                    'tenant_id' => $tenantId, 'booking_id' => $booking->id,
                    'amount' => $this->totalAmount, 'payment_method' => 'cash',
                    'payment_status' => 'paid', 'paid_at' => now(),
                ]);
                return null;
            }

            $payMongo = app(PayMongoService::class);
            $session  = $payMongo->createCheckoutSession([
                'customer_name'        => $customer->name,
                'customer_email'       => $customer->email ?? 'guest@example.com',
                'customer_phone'       => $customer->phone,
                'amount'               => $this->totalAmount,
                'description'          => "Booking #{$booking->booking_reference}",
                'item_name'            => 'Tourism Activity',
                'success_url'          => route('tenant.payments.success', ['booking' => $booking->id]),
                'cancel_url'           => route('tenant.payments.cancel',  ['booking' => $booking->id]),
                'metadata'             => ['booking_id' => $booking->id, 'tenant_id' => $tenantId],
                'payment_method_types' => [$this->payment_method],
            ]);

            if ($session) {
                Payment::create([
                    'tenant_id' => $tenantId, 'booking_id' => $booking->id,
                    'amount' => $this->totalAmount, 'payment_method' => $this->payment_method,
                    'payment_status' => 'unpaid',
                    'paymongo_session_id' => $session['data']['id'],
                ]);
                return redirect()->away($session['data']['attributes']['checkout_url']);
            }

            Payment::create([
                'tenant_id' => $tenantId, 'booking_id' => $booking->id,
                'amount' => $this->totalAmount, 'payment_method' => $this->payment_method,
                'payment_status' => 'unpaid',
            ]);
            return null;
        });

        if ($redirect instanceof \Illuminate\Http\RedirectResponse) return $redirect;

        session()->flash('message', 'Booking confirmed! We\'ll see you soon.');
        return redirect()->route('my-bookings');
    }
};
?>

@push('styles')
<style>
@keyframes fadeUp   { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
@keyframes pricePop { 0%{transform:scale(1)} 40%{transform:scale(1.08)} 100%{transform:scale(1)} }
@keyframes shake    { 0%,100%{transform:translateX(0)} 20%,60%{transform:translateX(-4px)} 40%,80%{transform:translateX(4px)} }
@keyframes checkIn  { 0%{transform:scale(0) rotate(-20deg);opacity:0} 70%{transform:scale(1.2) rotate(4deg)} 100%{transform:scale(1) rotate(0);opacity:1} }

.price-flash   { animation: pricePop .35s cubic-bezier(.34,1.56,.64,1); }
.error-shake   { animation: shake .4s ease; }
.check-appear  { animation: checkIn .35s cubic-bezier(.34,1.56,.64,1) both; }

/* ── Step wizard ── */
.step-dot {
    width:32px; height:32px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    font-size:12px; font-weight:800;
    transition: all .35s cubic-bezier(.34,1.56,.64,1);
    flex-shrink:0;
}
.step-dot.done    { background:#16a34a; color:#fff; box-shadow:0 0 0 4px rgba(22,163,74,.2); }
.step-dot.active  { background:#22c55e; color:#fff; box-shadow:0 0 0 5px rgba(34,197,94,.25); }
.step-dot.pending { background:rgba(255,255,255,.07); color:rgba(255,255,255,.35); border:1px solid rgba(255,255,255,.1); }
.step-line { height:1px; flex:1; background:rgba(255,255,255,.08); margin:0 8px; margin-top:-16px; }
.step-line.done { background:rgba(34,197,94,.4); }

/* ── Input fields ── */
.bk-input {
    width:100%; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.09);
    border-radius:12px; padding:12px 16px; color:#fff; font-size:14px;
    transition:border-color .2s, box-shadow .2s;
    outline:none;
}
.bk-input::placeholder { color:rgba(255,255,255,.25); }
.bk-input:focus { border-color:rgba(34,197,94,.5); box-shadow:0 0 0 3px rgba(34,197,94,.1); }
.bk-input[type="date"]::-webkit-calendar-picker-indicator { filter:invert(.5); cursor:pointer; }

/* ── Payment cards ── */
.pay-card {
    background:rgba(255,255,255,.04); border:1.5px solid rgba(255,255,255,.08);
    border-radius:14px; padding:14px; cursor:pointer; transition:all .25s ease;
    display:flex; align-items:center; gap:10px;
}
.pay-card:hover { border-color:rgba(255,255,255,.2); background:rgba(255,255,255,.06); }
.pay-card.selected {
    border-color:rgba(34,197,94,.55); background:rgba(34,197,94,.08);
    box-shadow:0 0 0 3px rgba(34,197,94,.1);
}

/* ── Service toggle button ── */
.svc-btn {
    display:flex; align-items:center; justify-content:space-between; gap:8px;
    padding:10px 14px; border-radius:12px; font-size:12px; font-weight:600;
    border:1px solid rgba(255,255,255,.1); cursor:pointer;
    background:rgba(255,255,255,.04); color:rgba(255,255,255,.7);
    transition:all .25s ease; text-align:left;
}
.svc-btn:hover { border-color:rgba(255,255,255,.22); background:rgba(255,255,255,.07); color:#fff; }
.svc-btn.added { border-color:rgba(34,197,94,.4); background:rgba(34,197,94,.08); color:#86efac; }

/* ── Summary sidebar ── */
.summary-card {
    background:rgba(0,0,0,.55); backdrop-filter:blur(24px);
    border:1px solid rgba(255,255,255,.09); border-radius:22px;
    overflow:hidden; box-shadow:0 32px 64px rgba(0,0,0,.5);
}

/* ── Section block ── */
.booking-section {
    background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.07);
    border-radius:20px; padding:24px; margin-bottom:16px;
    animation:fadeUp .5s cubic-bezier(.16,1,.3,1) both;
}
</style>
@endpush

<div class="relative z-10 min-h-screen py-8 pb-20"
     x-data="{
         currentStep: 1,
         totalAmount: {{ $totalAmount }},
         priceFlash:  false,

         flashPrice() {
             this.priceFlash = true;
             setTimeout(() => this.priceFlash = false, 400);
         }
     }"
     x-init="
         \$watch('totalAmount', () => flashPrice());
         Livewire.on('total-updated', (val) => { totalAmount = val; });
     ">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Back link --}}
        <div class="mb-8" style="animation:fadeUp .5s .05s both">
            <a href="{{ route('tenant.show', $property->tenant->slug) }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs uppercase tracking-wider text-white/40 hover:text-brand-400 transition-colors group">
                <svg class="w-3.5 h-3.5 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 12H5m7-7l-7 7 7 7"/></svg>
                Back to {{ $property->tenant->name }}
            </a>
        </div>

        {{-- Page heading --}}
        <div class="mb-8" style="animation:fadeUp .5s .1s both">
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-brand-500"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-brand-500 font-bold">Reservation</span>
            </div>
            <h1 class="font-display text-3xl md:text-4xl font-semibold text-white">
                Complete Your <em class="italic text-brand-400">Booking</em>
            </h1>
        </div>

        {{-- Step progress ── --}}
        <div class="flex items-start gap-0 mb-10" style="animation:fadeUp .5s .18s both">
            @php
                $steps = [
                    ['Your Details', 'Guest information'],
                    ['Visit Dates',  'Check-in & out'],
                    ['Payment',      'Secure checkout'],
                ];
            @endphp
            @foreach($steps as $i => [$title, $sub])
                <div class="flex flex-col items-center text-center min-w-0" style="flex:1">
                    <div class="step-dot active pending" id="step-dot-{{ $i+1 }}">
                        <span id="step-inner-{{ $i+1 }}">{{ $i+1 }}</span>
                    </div>
                    <p class="text-white font-semibold text-xs mt-2 leading-tight">{{ $title }}</p>
                    <p class="text-white/30 text-[10px] mt-0.5 hidden sm:block">{{ $sub }}</p>
                </div>
                @if($i < count($steps)-1)
                    <div class="step-line mt-4" id="step-line-{{ $i+1 }}"></div>
                @endif
            @endforeach
        </div>

        {{-- Error / success banners --}}
        @if(session()->has('error'))
            <div class="bg-red-500/10 border border-red-400/30 text-red-300 p-4 rounded-2xl text-sm mb-6 flex items-start gap-3 error-shake"
                 style="animation:fadeUp .4s both">
                <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-8 items-start">

            {{-- ─── FORM COLUMN ─── --}}
            <div class="space-y-4">

                {{-- ── SECTION 1: Guest Details ── --}}
                <div class="booking-section" style="animation-delay:.22s">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="step-dot done" style="width:28px;height:28px;font-size:11px">1</span>
                        <div>
                            <h2 class="font-display text-base font-semibold text-white">Your Details</h2>
                            <p class="text-white/35 text-[11px]">Who is this reservation for?</p>
                        </div>
                    </div>

                    @auth
                        <div class="flex items-center justify-between bg-white/[0.04] border border-white/[0.07] rounded-xl px-4 py-3 mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-brand-600 flex items-center justify-center text-white text-xs font-bold shrink-0">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-white text-sm font-semibold">{{ Auth::user()->name }}</p>
                                    <p class="text-white/40 text-xs">{{ Auth::user()->email }}</p>
                                </div>
                            </div>
                            <button type="button"
                                    onclick="document.getElementById('extra-guest-fields').classList.toggle('hidden')"
                                    class="text-[10px] font-bold uppercase tracking-wider text-brand-400 hover:text-brand-300 transition">
                                Edit
                            </button>
                        </div>
                        <div id="extra-guest-fields" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @else
                        <div id="extra-guest-fields" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @endauth

                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-white/40 mb-1.5">Full Name *</label>
                            <input type="text" wire:model="customerName" placeholder="Your full name"
                                   class="bk-input @error('customerName') border-red-400/50 @enderror">
                            @error('customerName') <p class="text-xs text-red-400 mt-1 flex items-center gap-1"><svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-white/40 mb-1.5">Email</label>
                            <input type="email" wire:model="customerEmail" placeholder="you@example.com"
                                   class="bk-input @error('customerEmail') border-red-400/50 @enderror">
                            @error('customerEmail') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-white/40 mb-1.5">Phone</label>
                            <input type="tel" wire:model="customerPhone" placeholder="+63 9xx xxx xxxx" class="bk-input">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-white/40 mb-1.5">Address</label>
                            <input type="text" wire:model="customerAddress" placeholder="City, Province" class="bk-input">
                        </div>
                    </div>
                </div>

                {{-- ── SECTION 2: Stay Dates ── --}}
                <div class="booking-section" style="animation-delay:.3s">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="step-dot active" style="width:28px;height:28px;font-size:11px">2</span>
                        <div>
                            <h2 class="font-display text-base font-semibold text-white">Visit Dates</h2>
                            <p class="text-white/35 text-[11px]">When are you planning to stay?</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-[1fr_auto_1fr] items-end gap-3">
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-white/40 mb-1.5">Check-in *</label>
                            <input type="date" wire:model.live="check_in"
                                   min="{{ now()->format('Y-m-d') }}"
                                   max="{{ now()->addDays(30)->format('Y-m-d') }}"
                                   class="bk-input @error('check_in') border-red-400/50 @enderror">
                            @error('check_in') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Nights badge --}}
                        <div class="flex flex-col items-center pb-2">
                            <div class="w-10 h-10 rounded-full bg-brand-500/15 border border-brand-500/25 flex flex-col items-center justify-center">
                                <span class="font-display text-sm font-bold text-brand-400 leading-none">{{ $totalDays }}</span>
                                <span class="text-[8px] text-brand-400/60 uppercase tracking-wide">nts</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-white/40 mb-1.5">Check-out *</label>
                            <input type="date" wire:model.live="check_out"
                                   min="{{ now()->addDay()->format('Y-m-d') }}"
                                   max="{{ now()->addDays(30)->format('Y-m-d') }}"
                                   class="bk-input @error('check_out') border-red-400/50 @enderror">
                            @error('check_out') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="mt-4 flex items-center gap-2 text-white/50 text-sm">
                        <svg class="w-4 h-4 text-brand-400/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="text-xs">{{ $totalDays }} night{{ $totalDays > 1 ? 's' : '' }} ·
                            {{ \Carbon\Carbon::parse($check_in)->format('M d') }} →
                            {{ \Carbon\Carbon::parse($check_out)->format('M d, Y') }}
                        </span>
                    </div>
                </div>

                {{-- ── SECTION 3: Extra Services ── --}}
                @if($this->availableServices->isNotEmpty())
                <div class="booking-section" style="animation-delay:.38s">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="step-dot pending" style="width:28px;height:28px;font-size:11px">3</span>
                        <div>
                            <h2 class="font-display text-base font-semibold text-white">Extra Services</h2>
                            <p class="text-white/35 text-[11px]">Optional add-ons for your stay</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-4">
                        @foreach($this->availableServices as $service)
                            @php $isAdded = isset($selectedServices[$service->id]); @endphp
                            <button type="button"
                                    wire:click="{{ $isAdded ? 'removeService' : 'addService' }}({{ $service->id }})"
                                    class="svc-btn {{ $isAdded ? 'added' : '' }}">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        @if($isAdded)
                                            <svg class="w-3.5 h-3.5 check-appear shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        @endif
                                        <span class="truncate text-[13px]">{{ $service->name }}</span>
                                    </div>
                                </div>
                                <span class="{{ $isAdded ? 'text-brand-400' : 'text-white/50' }} font-bold text-xs shrink-0">
                                    {{ $isAdded ? '✓ Added' : '+₱'.number_format($service->price, 0) }}
                                </span>
                            </button>
                        @endforeach
                    </div>

                    @if(count($selectedServices))
                        <div class="rounded-xl bg-white/[0.03] border border-white/[0.06] overflow-hidden">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-white/[0.06]">
                                        <th class="py-2 px-4 text-left text-[10px] uppercase tracking-wider text-white/30 font-bold">Service</th>
                                        <th class="py-2 px-4 text-center text-[10px] uppercase tracking-wider text-white/30 font-bold">Qty</th>
                                        <th class="py-2 px-4 text-right text-[10px] uppercase tracking-wider text-white/30 font-bold">Subtotal</th>
                                        <th class="py-2 px-3 w-8"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($selectedServices as $serviceId => $qty)
                                        @php $svc = App\Models\Service::find($serviceId); @endphp
                                        @if($svc)
                                            <tr class="border-b border-white/[0.04] last:border-0">
                                                <td class="py-2.5 px-4 text-white/75">{{ $svc->name }}</td>
                                                <td class="py-2.5 px-4 text-center text-white/50">{{ $qty }}</td>
                                                <td class="py-2.5 px-4 text-right text-white font-medium">₱{{ number_format($svc->price * $qty, 2) }}</td>
                                                <td class="py-2.5 px-3">
                                                    <button wire:click="removeService({{ $serviceId }})"
                                                            class="w-5 h-5 rounded-full border border-red-400/30 text-red-400/60 hover:bg-red-500 hover:text-white hover:border-transparent inline-flex items-center justify-center transition-all text-[11px]">
                                                        ✕
                                                    </button>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                @endif

                {{-- ── SECTION 4 (or 3): Payment Method ── --}}
                <div class="booking-section" style="animation-delay:.44s">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="step-dot pending" style="width:28px;height:28px;font-size:11px">{{ $this->availableServices->isNotEmpty() ? '4' : '3' }}</span>
                        <div>
                            <h2 class="font-display text-base font-semibold text-white">Payment Method</h2>
                            <p class="text-white/35 text-[11px]">How would you like to pay?</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        @foreach([
                            ['cash',    'Cash',    '💵', 'Pay upon arrival'],
                            ['gcash',   'GCash',   '📱', 'Mobile wallet'],
                            ['paymaya', 'PayMaya', '💳', 'Digital payments'],
                            ['card',    'Card',    '🏦', 'Visa / Mastercard'],
                        ] as [$val, $label, $icon, $desc])
                            <label class="cursor-pointer">
                                <input type="radio" wire:model.live="payment_method" value="{{ $val }}" class="sr-only peer">
                                <div class="pay-card peer-checked:selected">
                                    <span class="text-2xl">{{ $icon }}</span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-white font-semibold text-sm">{{ $label }}</p>
                                        <p class="text-white/35 text-[11px]">{{ $desc }}</p>
                                    </div>
                                    <div class="w-4 h-4 rounded-full border border-white/20 flex items-center justify-center shrink-0 peer-checked:border-brand-500 transition-all">
                                        @if($payment_method === $val)
                                            <div class="w-2 h-2 rounded-full bg-brand-500 check-appear"></div>
                                        @endif
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>

                    @if($payment_method !== 'cash')
                        <div class="mt-4 flex items-start gap-2.5 bg-amber-500/8 border border-amber-400/20 rounded-xl px-4 py-3">
                            <svg class="w-4 h-4 text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p class="text-amber-300/80 text-xs leading-relaxed">
                                You'll be redirected to <strong class="text-amber-300">PayMongo</strong> to complete your payment securely.
                            </p>
                        </div>
                    @endif
                </div>

            </div>

            {{-- ─── SUMMARY SIDEBAR ─── --}}
            <div class="lg:sticky lg:top-24" style="animation:fadeUp .5s .3s both">
                <div class="summary-card">

                    {{-- Property info --}}
                    <div class="p-6 border-b border-white/[0.07]">
                        @if($property->images->isNotEmpty())
                            <div class="w-full h-36 rounded-xl overflow-hidden mb-4">
                                <img src="{{ asset('storage/'.$property->images->first()->image_path) }}"
                                     class="w-full h-full object-cover" alt="{{ $property->name }}">
                            </div>
                        @endif
                        <h3 class="font-display text-xl font-semibold text-white leading-tight">{{ $property->name }}</h3>
                        <p class="text-sm text-white/40 mt-0.5">{{ $property->propertyType->name ?? 'Property' }} · {{ $property->tenant->name }}</p>
                        <div class="flex items-baseline gap-1.5 mt-3">
                            <span class="font-display text-3xl text-brand-400">₱{{ number_format($property->price, 2) }}</span>
                            <span class="text-xs text-white/35">/ night</span>
                        </div>
                    </div>

                    {{-- Line items --}}
                    <div class="p-6 border-b border-white/[0.07] space-y-3">
                        <dl>
                            <div class="flex justify-between items-center text-sm">
                                <dt class="text-white/55">{{ $totalDays }} night{{ $totalDays > 1 ? 's' : '' }} × ₱{{ number_format($property->price, 2) }}</dt>
                                <dd class="font-semibold text-white">₱{{ number_format($property->price * $totalDays, 2) }}</dd>
                            </div>
                            @foreach($selectedServices as $serviceId => $qty)
                                @php $svc = App\Models\Service::find($serviceId); @endphp
                                @if($svc)
                                    <div class="flex justify-between items-center text-sm mt-2">
                                        <dt class="text-white/55 truncate max-w-[160px]">{{ $svc->name }} ×{{ $qty }}</dt>
                                        <dd class="font-semibold text-white shrink-0">₱{{ number_format($svc->price * $qty, 2) }}</dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>
                    </div>

                    {{-- Total --}}
                    <div class="p-6 border-b border-white/[0.07]">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-bold uppercase tracking-widest text-white/35">Total Due</span>
                            <span class="font-display text-3xl font-semibold text-brand-400"
                                  :class="priceFlash ? 'price-flash' : ''">
                                ₱{{ number_format($totalAmount, 2) }}
                            </span>
                        </div>
                        @if($payment_method === 'cash')
                            <p class="text-[10px] text-white/25 mt-2">Payable upon arrival</p>
                        @else
                            <p class="text-[10px] text-white/25 mt-2">Charged now via PayMongo</p>
                        @endif
                    </div>

                    {{-- Submit button --}}
                    <button wire:click="submit"
                            wire:loading.attr="disabled"
                            class="w-full py-4 bg-brand-600 hover:bg-brand-500 text-white font-bold text-sm uppercase tracking-wider transition-all disabled:opacity-60 disabled:cursor-not-allowed hover:shadow-lg hover:shadow-brand-500/25">
                        <span wire:loading.remove class="flex items-center justify-center gap-2">
                            @if($payment_method === 'cash')
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Confirm Booking
                            @else
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                Proceed to Pay
                            @endif
                        </span>
                        <span wire:loading class="flex items-center justify-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Processing…
                        </span>
                    </button>

                    {{-- Trust badges --}}
                    <div class="p-5 flex items-center justify-center gap-6 text-center">
                        <div class="flex flex-col items-center gap-1 text-white/25">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <span class="text-[10px]">Secure</span>
                        </div>
                        <div class="flex flex-col items-center gap-1 text-white/25">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="text-[10px]">24hr cancel</span>
                        </div>
                        <div class="flex flex-col items-center gap-1 text-white/25">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <span class="text-[10px]">Confirmation</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>