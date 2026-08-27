{{-- resources/views/public/pages/⚡create-booking.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Computed;
use App\Models\Property;
use App\Models\Service;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingService;
use App\Models\Payment;
use App\Services\PayMongoService;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

new
#[Layout('layouts.app')]
#[Title('Complete Your Booking')]
class extends Component
{
    public Property $property;

    #[Validate('required|string|max:255')]
    public $customerName = '';
    #[Validate('required|email|max:255')]
    public $customerEmail = '';
    #[Validate('nullable|string|max:20')]
    public $customerPhone = '';

    #[Validate('required|date|after_or_equal:today')]
    public $check_in;
    #[Validate('required|date|after:check_in')]
    public $check_out;

    public $selectedServices = [];
    public $totalAmount = 0;
    public $totalDays = 1;

    public $bookingMode = 'full';
    #[Validate('required|in:gcash,paymaya,card')]
    public $paymentMethod = 'gcash';

    public $reservationFee = 0;
    public $balanceOnArrival = 0;

    public function mount($publicproperty)
    {
        $this->property = Property::with('tenant')
            ->withoutGlobalScope(TenantScope::class)
            ->findOrFail($publicproperty);

        if (!$this->property->tenant || !$this->property->tenant_id) abort(404);

        $this->customerName = Auth::user()->name;
        $this->customerEmail = Auth::user()->email;
        $this->customerPhone = Auth::user()->phone ?? '';

        $this->check_in = now()->format('Y-m-d');
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

    public function updatedBookingMode()
    {
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
        $this->totalDays = max(1, $in->diffInDays($out));
        $this->totalAmount = $this->property->price * $this->totalDays;

        foreach ($this->selectedServices as $serviceId => $qty) {
            $svc = Service::withoutGlobalScope(TenantScope::class)->find($serviceId);
            if ($svc) {
                $this->totalAmount += $svc->price * $qty;
            }
        }

        $this->reservationFee = round($this->totalAmount * 0.20, 2);
        $this->balanceOnArrival = round($this->totalAmount - $this->reservationFee, 2);
    }

    #[Computed]
    public function availableServices()
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
        if (!$tenantId) {
            session()->flash('error', 'Property not linked to a valid business.');
            return;
        }

        $conflict = BookingItem::where('property_id', $this->property->id)
            ->whereHas('booking', fn($q) =>
                $q->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
                  ->where('check_in', '<', $this->check_out)
                  ->where('check_out', '>', $this->check_in)
            )->exists();

        if ($conflict) {
            session()->flash('error', 'These dates are not available. Please choose different dates.');
            return;
        }

        DB::beginTransaction();
        try {
            $booking = Booking::create([
                'tenant_id'         => $tenantId,
                'user_id'           => Auth::id(),
                'booking_reference' => 'BK-' . strtoupper(Str::random(8)),
                'check_in'          => $this->check_in,
                'check_out'         => $this->check_out,
                'total_amount'      => $this->totalAmount,
                'status'            => Booking::STATUS_PENDING,
                'booking_type'      => $this->bookingMode,
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
                $svc = Service::withoutGlobalScope(TenantScope::class)->find($serviceId);
                if ($svc) {
                    BookingService::create([
                        'tenant_id'  => $tenantId,
                        'booking_id' => $booking->id,
                        'service_id' => $serviceId,
                        'quantity'   => $qty,
                        'subtotal'   => $svc->price * $qty,
                    ]);
                }
            }

            $chargeAmount = $this->bookingMode === Booking::TYPE_RESERVATION
                ? $this->reservationFee
                : $this->totalAmount;

            $payMongo = app(PayMongoService::class);
            $session = $payMongo->createCheckoutSession([
                'customer_name'        => $this->customerName,
                'customer_email'       => $this->customerEmail,
                'customer_phone'       => $this->customerPhone,
                'amount'               => $chargeAmount,
                'description'          => $this->bookingMode === Booking::TYPE_RESERVATION
                                            ? "Reservation fee for Booking #{$booking->booking_reference}"
                                            : "Full payment for Booking #{$booking->booking_reference}",
                'item_name'            => $this->bookingMode === Booking::TYPE_RESERVATION
                                            ? 'Reservation Fee'
                                            : 'Activity Booking',
                'success_url'          => route('booking.payment.success', ['booking' => $booking->id]),
                'cancel_url'           => route('booking.payment.cancel', ['booking' => $booking->id]),
                'metadata'             => ['booking_id' => $booking->id, 'tenant_id' => $tenantId],
                'payment_method_types' => ['gcash', 'paymaya', 'card'],
            ]);

            if (!$session) {
                DB::rollBack();
                session()->flash('error', 'Unable to initiate payment. Please try again.');
                return;
            }

            Payment::create([
                'tenant_id'            => $tenantId,
                'booking_id'           => $booking->id,
                'amount'               => $chargeAmount,
                'payment_method'       => $this->paymentMethod,
                'payment_status'       => 'unpaid',
                'payment_type'         => $this->bookingMode,
                'paymongo_session_id'  => $session['id'],
            ]);

            DB::commit();
            return redirect()->away($session['checkout_url']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Booking creation error: ' . $e->getMessage());
            session()->flash('error', 'Something went wrong. Please try again.');
            return;
        }
    }
};
?>

<div class="relative z-10 min-h-screen bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100"
     x-data="{
         step: 1,
         maxStep: {{ $this->availableServices->isNotEmpty() ? 4 : 3 }},
         next() {
             if (this.step < this.maxStep) {
                 this.step++;
                 this.$nextTick(() => this.$refs['stepHeading' + this.step]?.focus());
             }
         },
         prev() {
             if (this.step > 1) {
                 this.step--;
                 this.$nextTick(() => this.$refs['stepHeading' + this.step]?.focus());
             }
         },
         goTo(s) {
             if (s <= this.maxStep && s !== this.step) {
                 this.step = s;
                 this.$nextTick(() => this.$refs['stepHeading' + this.step]?.focus());
             }
         }
     }"
     @keydown.arrow-right.window="next()"
     @keydown.arrow-left.window="prev()">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-32 lg:pb-12">

        {{-- Back link --}}
        <div class="mb-6">
            <a href="{{ route('tenant.show', $property->tenant->slug) }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs uppercase tracking-wider text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors group">
                <svg class="w-3.5 h-3.5 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 12H5m7-7l-7 7 7 7"/></svg>
                Back to {{ $property->tenant->name }}
            </a>
        </div>

        {{-- Header --}}
        <div class="mb-8">
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Reservation</span>
            </div>
            <h1 class="font-display text-3xl md:text-4xl font-semibold text-gray-900 dark:text-white">
                Complete Your <em class="italic text-primary-600 dark:text-primary-400">Booking</em>
            </h1>
        </div>

        {{-- Step Progress --}}
        <div class="flex items-center mb-10">
            @php
                $steps = [];
                $steps[1] = ['Your Details', 'Guest information'];
                $steps[2] = ['Visit Dates', 'Check-in & out'];
                if ($this->availableServices->isNotEmpty()) {
                    $steps[3] = ['Extras', 'Optional services'];
                    $steps[4] = ['Payment', 'Secure checkout'];
                } else {
                    $steps[3] = ['Payment', 'Secure checkout'];
                }
            @endphp
            @foreach($steps as $num => [$title, $sub])
                <button type="button"
                        @click="goTo({{ $num }})"
                        :disabled="{{ $num }} > maxStep"
                        class="flex flex-col items-center min-w-0 flex-1 focus:outline-none group"
                        :class="{{ $num }} <= maxStep ? 'cursor-pointer' : 'cursor-not-allowed opacity-60'">
                    <span class="step-dot"
                          :class="{
                            'done': {{ $num }} < step,
                            'active': {{ $num }} === step,
                            'pending': {{ $num }} > step
                          }">
                        <template x-if="{{ $num }} < step">✓</template>
                        <template x-if="{{ $num }} >= step">{{ $num }}</template>
                    </span>
                    <span class="text-xs font-semibold mt-2 text-center"
                          :class="{
                            'text-gray-900 dark:text-white': {{ $num }} <= step,
                            'text-gray-500 dark:text-gray-400': {{ $num }} > step
                          }">
                        {{ $title }}
                    </span>
                    <span class="hidden sm:block text-[10px] text-gray-400 dark:text-gray-500">{{ $sub }}</span>
                </button>
                @if($num < count($steps))
                    <div class="h-px flex-1 bg-gray-200 dark:bg-gray-700 mx-2 mt-4"></div>
                @endif
            @endforeach
        </div>

        {{-- Error Flash --}}
        @if(session()->has('error'))
            <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-400/40 text-red-700 dark:text-red-200 p-4 rounded-2xl text-sm mb-6 flex items-start gap-3">
                <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-8 items-start">

            {{-- Main Form Area --}}
            <div class="space-y-4">

                {{-- Step 1: Guest Details --}}
                <div x-show="step === 1" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" class="space-y-4">
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
                        <h2 class="font-display text-lg font-semibold text-gray-900 dark:text-white mb-4" x-ref="stepHeading1" tabindex="-1">Your Details</h2>

                        @auth
                            <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-primary-600 flex items-center justify-center text-white text-xs font-bold shrink-0">
                                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="text-gray-900 dark:text-white text-sm font-semibold">{{ Auth::user()->name }}</p>
                                        <p class="text-gray-500 dark:text-gray-400 text-xs">{{ Auth::user()->email }}</p>
                                    </div>
                                </div>
                                <button type="button"
                                        onclick="document.getElementById('extra-guest-fields').classList.toggle('hidden')"
                                        class="text-[10px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 transition">
                                    Edit
                                </button>
                            </div>
                            <div id="extra-guest-fields" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @else
                            <div id="extra-guest-fields" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @endauth

                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Full Name *</label>
                                <input type="text" wire:model="customerName" placeholder="Your full name"
                                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-600/50 focus:border-primary-600 transition-colors duration-200 @error('customerName') border-red-400/50 @enderror">
                                @error('customerName') <p class="text-xs text-red-600 dark:text-red-300 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Email *</label>
                                <input type="email" wire:model="customerEmail" placeholder="you@example.com" required
                                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-600/50 focus:border-primary-600 transition-colors duration-200 @error('customerEmail') border-red-400/50 @enderror">
                                @error('customerEmail') <p class="text-xs text-red-600 dark:text-red-300 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Phone</label>
                                <input type="tel" wire:model="customerPhone" placeholder="+63 9xx xxx xxxx"
                                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-600/50 focus:border-primary-600 transition-colors duration-200">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="button" @click="next()" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-full text-sm font-bold uppercase tracking-widest transition">Continue →</button>
                    </div>
                </div>

                {{-- Step 2: Visit Dates --}}
                <div x-show="step === 2" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" class="space-y-4">
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
                        <h2 class="font-display text-lg font-semibold text-gray-900 dark:text-white mb-4" x-ref="stepHeading2" tabindex="-1">Visit Dates</h2>

                        <div class="grid grid-cols-[1fr_auto_1fr] items-end gap-3">
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Check-in *</label>
                                <input type="date" wire:model.live="check_in"
                                       min="{{ now()->format('Y-m-d') }}"
                                       max="{{ now()->addDays(30)->format('Y-m-d') }}"
                                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-600/50 focus:border-primary-600 transition-colors duration-200 @error('check_in') border-red-400/50 @enderror">
                                @error('check_in') <p class="text-xs text-red-600 dark:text-red-300 mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div class="flex flex-col items-center pb-2">
                                <div class="w-10 h-10 rounded-full bg-primary-500/15 border border-primary-500/25 flex flex-col items-center justify-center">
                                    <span class="font-display text-sm font-bold text-primary-600 dark:text-primary-400 leading-none">{{ $totalDays }}</span>
                                    <span class="text-[8px] text-primary-600/60 dark:text-primary-300/70 uppercase tracking-wide">days</span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Check-out *</label>
                                <input type="date" wire:model.live="check_out"
                                       min="{{ now()->addDay()->format('Y-m-d') }}"
                                       max="{{ now()->addDays(30)->format('Y-m-d') }}"
                                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-600/50 focus:border-primary-600 transition-colors duration-200 @error('check_out') border-red-400/50 @enderror">
                                @error('check_out') <p class="text-xs text-red-600 dark:text-red-300 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="mt-4 flex items-center gap-2 text-gray-500 dark:text-gray-400 text-sm">
                            <svg class="w-4 h-4 text-primary-600/60 dark:text-primary-400/70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="text-xs">{{ $totalDays }} day{{ $totalDays > 1 ? 's' : '' }} ·
                                {{ \Carbon\Carbon::parse($check_in)->format('M d') }} →
                                {{ \Carbon\Carbon::parse($check_out)->format('M d, Y') }}
                            </span>
                        </div>
                    </div>

                    <div class="flex justify-between">
                        <button type="button" @click="prev()" class="px-6 py-3 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-full text-sm font-bold uppercase tracking-widest transition">← Back</button>
                        <button type="button" @click="next()" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-full text-sm font-bold uppercase tracking-widest transition">Continue →</button>
                    </div>
                </div>

                {{-- Step 3: Extra Services (conditional) --}}
                @if($this->availableServices->isNotEmpty())
                <div x-show="step === 3" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" class="space-y-4">
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
                        <h2 class="font-display text-lg font-semibold text-gray-900 dark:text-white mb-4" x-ref="stepHeading3" tabindex="-1">Extra Services</h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-4">
                            @foreach($this->availableServices as $service)
                                @php $isAdded = isset($selectedServices[$service->id]); @endphp
                                <button type="button"
                                        wire:click="{{ $isAdded ? 'removeService' : 'addService' }}({{ $service->id }})"
                                        class="flex items-center justify-between gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-200 {{ $isAdded ? 'border-primary-600 bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300' : '' }}">
                                    <span>{{ $service->name }}</span>
                                    <span class="font-bold text-xs">{{ $isAdded ? '✓ Added' : '+₱'.number_format($service->price, 0) }}</span>
                                </button>
                            @endforeach
                        </div>

                        @if(count($selectedServices))
                            <div class="rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <th class="py-2 px-4 text-left text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-bold">Service</th>
                                            <th class="py-2 px-4 text-center text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-bold">Qty</th>
                                            <th class="py-2 px-4 text-right text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-bold">Subtotal</th>
                                            <th class="py-2 px-3 w-8"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($selectedServices as $serviceId => $qty)
                                            @php $svc = App\Models\Service::withoutGlobalScope(TenantScope::class)->find($serviceId); @endphp
                                            @if($svc)
                                                <tr class="border-b border-gray-100 dark:border-gray-700 last:border-0">
                                                    <td class="py-2.5 px-4 text-gray-700 dark:text-gray-200">{{ $svc->name }}</td>
                                                    <td class="py-2.5 px-4 text-center text-gray-500 dark:text-gray-400">{{ $qty }}</td>
                                                    <td class="py-2.5 px-4 text-right text-gray-900 dark:text-white font-medium">₱{{ number_format($svc->price * $qty, 2) }}</td>
                                                    <td class="py-2.5 px-3">
                                                        <button type="button" wire:click="removeService({{ $serviceId }})"
                                                                class="w-5 h-5 rounded-full border border-red-300 dark:border-red-500/40 text-red-500 dark:text-red-300 hover:bg-red-500 hover:text-white hover:border-transparent inline-flex items-center justify-center transition-all text-[11px]">
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

                    <div class="flex justify-between">
                        <button type="button" @click="prev()" class="px-6 py-3 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-full text-sm font-bold uppercase tracking-widest transition">← Back</button>
                        <button type="button" @click="next()" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-full text-sm font-bold uppercase tracking-widest transition">Continue →</button>
                    </div>
                </div>
                @endif

                {{-- Step 4: Payment --}}
                <div x-show="step === {{ $this->availableServices->isNotEmpty() ? 4 : 3 }}" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" class="space-y-4">
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
                        <h2 class="font-display text-lg font-semibold text-gray-900 dark:text-white mb-4" x-ref="{{ $this->availableServices->isNotEmpty() ? 'stepHeading4' : 'stepHeading3' }}" tabindex="-1">Payment Method</h2>

                        {{-- Booking Mode --}}
                        <div class="mb-4">
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Booking Type</label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="cursor-pointer">
                                    <input type="radio" wire:model.live="bookingMode" value="full" class="sr-only peer">
                                    <div class="flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-center transition-all duration-200 cursor-pointer peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 peer-checked:shadow-lg">
                                        <span class="text-2xl">📅</span>
                                        <p class="text-gray-900 dark:text-white font-semibold text-sm">Book Now</p>
                                        <p class="text-gray-500 dark:text-gray-400 text-[11px]">Pay 100% online</p>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" wire:model.live="bookingMode" value="reservation" class="sr-only peer">
                                    <div class="flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-center transition-all duration-200 cursor-pointer peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 peer-checked:shadow-lg">
                                        <span class="text-2xl">🪙</span>
                                        <p class="text-gray-900 dark:text-white font-semibold text-sm">Reserve</p>
                                        <p class="text-gray-500 dark:text-gray-400 text-[11px]">Pay 20% reservation fee</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        {{-- Payment Methods --}}
                        <div class="grid grid-cols-3 gap-3">
                            @foreach([
                                ['gcash', 'GCash'],
                                ['paymaya', 'Maya'],
                                ['card', 'Card'],
                            ] as [$val, $label])
                                <label class="cursor-pointer">
                                    <input type="radio" wire:model.live="paymentMethod" value="{{ $val }}" class="sr-only peer">
                                    <div class="flex flex-col items-center justify-center gap-1 p-3 rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-center transition-all duration-200 cursor-pointer peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 peer-checked:shadow-lg">
                                        @if($val === 'gcash')
                                            <svg class="w-10 h-10" viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="16" fill="#007DFE"/><text x="16" y="21" text-anchor="middle" fill="white" font-size="12" font-weight="bold">G</text></svg>
                                        @elseif($val === 'paymaya')
                                            <svg class="w-10 h-10" viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="16" fill="#00C6D7"/><text x="16" y="21" text-anchor="middle" fill="white" font-size="12" font-weight="bold">M</text></svg>
                                        @else
                                            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2" stroke="currentColor" stroke-width="2"/><line x1="2" y1="10" x2="22" y2="10" stroke="currentColor" stroke-width="2"/></svg>
                                        @endif
                                        <p class="text-gray-900 dark:text-white font-semibold text-xs mt-1">{{ $label }}</p>
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        <div class="mt-4 flex items-start gap-2.5 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-400/30 rounded-xl px-4 py-3">
                            <svg class="w-4 h-4 text-amber-500 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p class="text-amber-700 dark:text-amber-200 text-xs leading-relaxed">
                                You'll be redirected to <strong class="text-amber-800 dark:text-amber-100">PayMongo</strong> to complete your payment securely.
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-between">
                        <button type="button" @click="prev()" class="px-6 py-3 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-full text-sm font-bold uppercase tracking-widest transition">← Back</button>
                        <button wire:click="submit" wire:loading.attr="disabled"
                                class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-full text-sm font-bold uppercase tracking-widest transition disabled:opacity-60 disabled:cursor-not-allowed">
                            <span wire:loading.remove>Proceed to Pay</span>
                            <span wire:loading>Processing…</span>
                        </button>
                    </div>
                </div>

            </div>

            {{-- Summary Sidebar --}}
            <div class="hidden lg:block lg:sticky lg:top-24">
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-3xl overflow-hidden shadow-lg">
                    @if($property->images->isNotEmpty())
                        <div class="w-full h-36 rounded-t-3xl overflow-hidden">
                            <img src="{{ asset('storage/'.$property->images->first()->image_path) }}"
                                 class="w-full h-full object-cover" alt="{{ $property->name }}">
                        </div>
                    @endif
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-display text-xl font-semibold text-gray-900 dark:text-white leading-tight">{{ $property->name }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $property->propertyType->name ?? 'Activity' }} · {{ $property->tenant->name }}</p>
                        <div class="flex items-baseline gap-1.5 mt-3">
                            <span class="font-display text-3xl text-primary-600 dark:text-primary-400">₱{{ number_format($property->price, 2) }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">per unit</span>
                        </div>
                    </div>

                    <div class="p-6 border-b border-gray-200 dark:border-gray-700 space-y-3">
                        <dl>
                            <div class="flex justify-between items-center text-sm">
                                <dt class="text-gray-600 dark:text-gray-300">{{ $totalDays }} day{{ $totalDays > 1 ? 's' : '' }} × ₱{{ number_format($property->price, 2) }}</dt>
                                <dd class="font-semibold text-gray-900 dark:text-white">₱{{ number_format($property->price * $totalDays, 2) }}</dd>
                            </div>
                            @foreach($selectedServices as $serviceId => $qty)
                                @php $svc = App\Models\Service::withoutGlobalScope(TenantScope::class)->find($serviceId); @endphp
                                @if($svc)
                                    <div class="flex justify-between items-center text-sm mt-2">
                                        <dt class="text-gray-600 dark:text-gray-300 truncate max-w-[160px]">{{ $svc->name }} ×{{ $qty }}</dt>
                                        <dd class="font-semibold text-gray-900 dark:text-white shrink-0">₱{{ number_format($svc->price * $qty, 2) }}</dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>
                    </div>

                    <div class="p-6">
                        @if($bookingMode === 'reservation')
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Pay Now (20%)</span>
                                <span class="font-display text-2xl font-semibold text-primary-600 dark:text-primary-400">₱{{ number_format($reservationFee, 2) }}</span>
                            </div>
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Balance on Arrival</span>
                                <span class="font-display text-lg font-semibold text-gray-900 dark:text-white">₱{{ number_format($balanceOnArrival, 2) }}</span>
                            </div>
                        @else
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Total Due</span>
                                <span class="font-display text-3xl font-semibold text-primary-600 dark:text-primary-400">₱{{ number_format($totalAmount, 2) }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Mobile Sticky Summary --}}
    <div class="lg:hidden fixed bottom-0 left-0 right-0 z-50 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 shadow-lg p-4">
        <div class="flex items-center justify-between gap-4 max-w-7xl mx-auto">
            <div class="flex-1 min-w-0">
                <p class="text-xs text-gray-500 dark:text-gray-400">Total due</p>
                <p class="font-display text-xl font-bold text-gray-900 dark:text-white">
                    ₱{{ number_format($bookingMode === 'reservation' ? $reservationFee : $totalAmount, 2) }}
                </p>
            </div>
            <button type="button" @click="goTo({{ $this->availableServices->isNotEmpty() ? 4 : 3 }})"
                    class="shrink-0 px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-full text-sm font-bold uppercase tracking-widest transition shadow-lg shadow-primary-500/30">
                Review
            </button>
        </div>
    </div>

    <style>
        .step-dot {
            width: 32px; height: 32px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 800;
            transition: all .35s cubic-bezier(.34,1.56,.64,1);
            flex-shrink: 0;
        }
        .step-dot.done { background: #16a34a; color: #fff; box-shadow: 0 0 0 4px rgba(22,163,74,.2); }
        .step-dot.active { background: #22c55e; color: #fff; box-shadow: 0 0 0 5px rgba(34,197,94,.25); }
        .step-dot.pending { background: #e5e7eb; color: #6b7280; border: 1px solid #d1d5db; }
        .dark .step-dot.pending { background: #374151; color: #e5e7eb; border-color: #6b7280; }
    </style>
</div>