{{-- resources/views/livewire/booking/⚡create.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
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

    // ── Guest ──
    public string $customerName  = '';
    public string $customerEmail = '';
    public string $customerPhone = '';

    // ── Stay ──
    public string $check_in     = '';
    public string $check_out    = '';
    public string $checkInTime  = '14:00';

    // ── Selection ──
    /** @var array<int, int> service_id => quantity */
    public array $selectedServices = [];

    // ── Totals ──
    public float $totalAmount      = 0;
    public int   $totalDays        = 1;
    public float $reservationFee   = 0;
    public float $balanceOnArrival = 0;

    // ── Payment ──
    public string $bookingMode   = 'full';
    public string $paymentMethod = 'gcash';

    // ─────────────────────────────────────────────────────────
    //  Lifecycle
    // ─────────────────────────────────────────────────────────

    public function mount($publicproperty): void
    {
        $this->property = Property::with(['tenant', 'images', 'propertyType'])
            ->withoutGlobalScope(TenantScope::class)
            ->findOrFail($publicproperty);

        if (!$this->property->tenant || !$this->property->tenant_id || !$this->property->is_active) {
            abort(404);
        }

        $this->customerName  = (string) Auth::user()->name;
        $this->customerEmail = (string) Auth::user()->email;
        $this->customerPhone = (string) (Auth::user()->phone ?? '');

        $this->check_in    = now()->format('Y-m-d');
        $this->check_out   = $this->check_in;
        $this->checkInTime = now()->format('H:i');

        $this->calculateTotal();
    }

    // ─────────────────────────────────────────────────────────
    //  Validation
    // ─────────────────────────────────────────────────────────

    protected function rules(): array
    {
        return [
            'customerName'  => ['required', 'string', 'max:255'],
            'customerEmail' => ['required', 'email', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:20', 'regex:/^(09|\+639)\d{9}$/'],
            'check_in'      => ['required', 'date', 'after_or_equal:today'],
            'check_out'     => ['required', 'date', 'after_or_equal:check_in'],
            'checkInTime'   => ['required', 'date_format:H:i'],
            'bookingMode'   => ['required', 'in:full,reservation'],
            'paymentMethod' => ['required', 'in:gcash,paymaya,card'],
            'selectedServices'   => ['array'],
            'selectedServices.*' => ['integer', 'min:1'],
        ];
    }

    // ─────────────────────────────────────────────────────────
    //  Date + service mutations
    // ─────────────────────────────────────────────────────────

    /**
     * Called from Alpine after the user taps a date on the calendar.
     * Values arrive as validated Y-m-d strings.
     */
    public function setDates($checkIn, $checkOut): void
    {
        if (!is_string($checkIn) || !is_string($checkOut)) {
            return;
        }

        try {
            $in  = Carbon::createFromFormat('Y-m-d', $checkIn);
            $out = Carbon::createFromFormat('Y-m-d', $checkOut);
        } catch (\Throwable $e) {
            return;
        }

        $this->check_in  = $in->format('Y-m-d');
        $this->check_out = $out->format('Y-m-d');

        $this->validateDateRange();
        $this->calculateTotal();
    }

    public function updatedCheckIn(): void
    {
        if (empty($this->check_out) || Carbon::parse($this->check_in)->gt(Carbon::parse($this->check_out))) {
            $this->check_out = $this->check_in;
        }

        $this->validateDateRange();
        $this->calculateTotal();
    }

    public function updatedCheckOut(): void
    {
        if (empty($this->check_in) || Carbon::parse($this->check_out)->lt(Carbon::parse($this->check_in))) {
            $this->check_out = $this->check_in;
        }

        $this->validateDateRange();
        $this->calculateTotal();
    }

    public function updatedCheckInTime(): void
    {
        $this->calculateTotal();
    }

    public function updatedBookingMode(): void
    {
        $this->calculateTotal();
    }

    public function addService(int $serviceId): void
    {
        $this->selectedServices[$serviceId] = ($this->selectedServices[$serviceId] ?? 0) + 1;
        $this->calculateTotal();
    }

    public function removeService(int $serviceId): void
    {
        unset($this->selectedServices[$serviceId]);
        $this->calculateTotal();
    }

    public function calculateTotal(): void
    {
        $price = (float) $this->property->price;

        if (empty($this->check_in) || empty($this->check_out)) {
            $this->totalDays   = 1;
            $this->totalAmount = $price;
        } else {
            $in  = Carbon::parse($this->check_in);
            $out = Carbon::parse($this->check_out);
            $this->totalDays   = max(1, (int) $in->diffInDays($out));
            $this->totalAmount = $price * $this->totalDays;
        }

        foreach ($this->selectedServices as $serviceId => $qty) {
            if ($svc = $this->selectedServiceModels->get($serviceId)) {
                $this->totalAmount += (float) $svc->price * $qty;
            }
        }

        $this->reservationFee   = round($this->totalAmount * 0.20, 2);
        $this->balanceOnArrival = round($this->totalAmount - $this->reservationFee, 2);
    }

    // ─────────────────────────────────────────────────────────
    //  Computed
    // ─────────────────────────────────────────────────────────

    #[Computed]
    public function selectedServiceModels()
    {
        $serviceIds = array_keys($this->selectedServices);

        if (empty($serviceIds)) {
            return collect();
        }

        return Service::withoutGlobalScope(TenantScope::class)
            ->whereIn('id', $serviceIds)
            ->get(['id', 'name', 'price'])
            ->keyBy('id');
    }

    #[Computed]
    public function availableServices()
    {
        return Service::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $this->property->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'price']);
    }

    /**
     * @return array<int, array{start: string, end: string}>
     */
    #[Computed]
    public function bookedDateRanges(): array
    {
        $minDate = now()->format('Y-m-d');
        $maxDate = now()->addDays(30)->format('Y-m-d');

        return BookingItem::withoutGlobalScope(TenantScope::class)
            ->where('property_id', $this->property->id)
            ->whereHas('booking', function ($q) use ($minDate, $maxDate) {
                $q->withoutGlobalScope(TenantScope::class)
                  ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
                  ->where('check_in', '<=', $maxDate)
                  ->where('check_out', '>=', $minDate);
            })
            ->with(['booking' => fn ($q) => $q
                ->withoutGlobalScope(TenantScope::class)
                ->select('id', 'check_in', 'check_out')
            ])
            ->get(['id', 'booking_id', 'property_id'])
            ->map(function ($item) {
                if (!$item->booking) {
                    return null;
                }

                return [
                    'start' => $item->booking->check_in->format('Y-m-d'),
                    'end'   => $item->booking->check_out->format('Y-m-d'),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function bookedDatesArray(): array
    {
        $dates = [];

        foreach ($this->bookedDateRanges as $range) {
            $start = Carbon::parse($range['start']);
            $end   = Carbon::parse($range['end']);

            while ($start->lte($end)) {
                $dates[] = $start->format('Y-m-d');
                $start->addDay();
            }
        }

        return array_values(array_unique($dates));
    }

    // ─────────────────────────────────────────────────────────
    //  Date range validation
    // ─────────────────────────────────────────────────────────

    protected function validateDateRange(): void
    {
        if (empty($this->check_in) || empty($this->check_out)) {
            return;
        }

        $start       = Carbon::parse($this->check_in);
        $end         = Carbon::parse($this->check_out);
        $bookedDates = $this->bookedDatesArray;

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if (in_array($d->format('Y-m-d'), $bookedDates, true)) {
                session()->flash('error', 'Selected date range includes unavailable dates. Please choose different dates.');
                return;
            }
        }

        session()->forget('error');
    }

    // ─────────────────────────────────────────────────────────
    //  Submit → create booking + PayMongo checkout session
    // ─────────────────────────────────────────────────────────

    public function submit()
    {
        $this->validate();
        $this->validateDateRange();

        $tenantId = $this->property->tenant_id;

        if (!$tenantId) {
            session()->flash('error', 'Property not linked to a valid business.');
            return null;
        }

        if (!$this->property->is_active) {
            session()->flash('error', 'This activity is currently unavailable. Please choose another.');
            return null;
        }

        DB::beginTransaction();

        try {
            // 1. Lock the property row to prevent concurrent double-booking.
            Property::withoutGlobalScope(TenantScope::class)
                ->whereKey($this->property->id)
                ->lockForUpdate()
                ->first();

            // 2. Re-check availability after locking.
            $conflict = BookingItem::withoutGlobalScope(TenantScope::class)
                ->where('property_id', $this->property->id)
                ->whereHas('booking', function ($q) {
                    $q->withoutGlobalScope(TenantScope::class)
                      ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
                      ->where('check_in', '<', $this->check_out . ' 23:59:59')
                      ->where('check_out', '>', $this->check_in . ' 00:00:00');
                })
                ->exists();

            if ($conflict) {
                DB::rollBack();
                session()->flash('error', 'Selected dates are not available. Please choose different dates.');
                return null;
            }

            $checkInDateTime  = $this->check_in . ' ' . $this->checkInTime . ':00';
            $checkOutDateTime = $this->check_out . ' ' . $this->checkInTime . ':00';

            // 3. Create the booking header.
            $booking = Booking::create([
                'tenant_id'         => $tenantId,
                'user_id'           => Auth::id(),
                'booking_reference' => 'BK-' . strtoupper(Str::random(8)),
                'check_in'          => $checkInDateTime,
                'check_out'         => $checkOutDateTime,
                'total_amount'      => $this->totalAmount,
                'status'            => Booking::STATUS_PENDING,
                'booking_type'      => $this->bookingMode,
            ]);

            // 4. Booking item (the property itself).
            BookingItem::create([
                'tenant_id'   => $tenantId,
                'booking_id'  => $booking->id,
                'property_id' => $this->property->id,
                'price'       => $this->property->price,
                'quantity'    => $this->totalDays,
                'subtotal'    => (float) $this->property->price * $this->totalDays,
            ]);

            // 5. Booking services (add-ons).
            foreach ($this->selectedServices as $serviceId => $qty) {
                if ($svc = $this->selectedServiceModels->get($serviceId)) {
                    BookingService::create([
                        'tenant_id'  => $tenantId,
                        'booking_id' => $booking->id,
                        'service_id' => $serviceId,
                        'quantity'   => $qty,
                        'subtotal'   => (float) $svc->price * $qty,
                    ]);
                }
            }

            // 6. Determine what to charge now.
            $chargeAmount = $this->bookingMode === Booking::TYPE_RESERVATION
                ? $this->reservationFee
                : $this->totalAmount;

            // 7. Create the PayMongo checkout session.
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
                'success_url'          => route('booking.payment.processing', ['bookingId' => $booking->id]),
                'cancel_url'           => route('booking.payment.cancel', ['booking' => $booking->id]),
                'metadata'             => [
                    'booking_id' => (string) $booking->id,
                    'tenant_id'  => (string) $tenantId,
                ],
                'payment_method_types' => ['gcash', 'paymaya', 'card'],
            ]);

            if (!$session || empty($session['id']) || empty($session['checkout_url'])) {
                DB::rollBack();

                Log::error('PayMongo checkout session creation failed', [
                    'booking_id' => $booking->id ?? null,
                    'session'    => $session,
                ]);

                session()->flash('error', 'Unable to initiate payment. Please try again.');
                return null;
            }

            // 8. Record the pending payment so the processing page can find it
            //    via PayMongoService::findPaymentForBooking().
            Payment::create([
                'tenant_id'           => $tenantId,
                'booking_id'          => $booking->id,
                'amount'              => $chargeAmount,
                'payment_method'      => $this->paymentMethod,
                'payment_status'      => 'pending',
                'payment_type'        => $this->bookingMode,
                'paymongo_session_id' => $session['id'],
            ]);

            DB::commit();

            Log::info('Booking created, redirecting to PayMongo', [
                'booking_id'   => $booking->id,
                'session_id'   => $session['id'],
                'charge_amount' => $chargeAmount,
            ]);

            return redirect()->away($session['checkout_url']);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Booking creation error: ' . $e->getMessage(), [
                'property_id' => $this->property->id ?? null,
                'user_id'     => Auth::id(),
                'trace'       => $e->getTraceAsString(),
            ]);

            session()->flash('error', 'Something went wrong. Please try again.');
            return null;
        }
    }
};
?>

<div class="relative z-10 min-h-screen bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100"
     x-data="{
         step: 1,
         maxStep: {{ $this->availableServices->isNotEmpty() ? 4 : 3 }},
         errors: {},
         next() {
             if (this.step === 1) {
                 if (!this.$wire.customerName.trim()) {
                     this.errors.name = 'Full name is required.';
                 } else {
                     delete this.errors.name;
                 }
                 if (!this.$wire.customerEmail.trim()) {
                     this.errors.email = 'Email is required.';
                 } else {
                     delete this.errors.email;
                 }
                 if (Object.keys(this.errors).length > 0) return;
             }
             if (this.step === 2) {
                 if (!this.$wire.check_in || !this.$wire.check_out) {
                     this.errors.dates = 'Please select both check-in and check-out dates.';
                     return;
                 } else {
                     delete this.errors.dates;
                 }
             }
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
               class="inline-flex items-center gap-1.5 text-xs uppercase tracking-wider text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors group active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                <svg class="w-3.5 h-3.5 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 12H5m7-7l-7 7 7 7"/>
                </svg>
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
                        class="flex flex-col items-center min-w-0 flex-1 focus:outline-none group active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded-lg transition-all duration-200"
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
                <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-8 items-start">

            {{-- ═══════════════════════════════════════════════════
                 Main Form Area
                 ═══════════════════════════════════════════════════ --}}
            <div class="space-y-4">

                {{-- Step 1: Guest Details --}}
                <div x-show="step === 1"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-x-4"
                     x-transition:enter-end="opacity-100 translate-x-0"
                     class="space-y-4">

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
                                        class="text-[10px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded-md px-2 py-1">
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
                                <p x-show="errors.name" x-text="errors.name" class="text-xs text-red-600 dark:text-red-300 mt-1"></p>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Email *</label>
                                <input type="email" wire:model="customerEmail" placeholder="you@example.com" required
                                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-600/50 focus:border-primary-600 transition-colors duration-200 @error('customerEmail') border-red-400/50 @enderror">
                                @error('customerEmail') <p class="text-xs text-red-600 dark:text-red-300 mt-1">{{ $message }}</p> @enderror
                                <p x-show="errors.email" x-text="errors.email" class="text-xs text-red-600 dark:text-red-300 mt-1"></p>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Phone</label>
                                <input type="tel"
                                       inputmode="numeric"
                                       pattern="[0-9+]*"
                                       maxlength="13"
                                       wire:model.live.debounce.500ms="customerPhone"
                                       x-on:input="
                                           const cleaned = $event.target.value.replace(/[^0-9+]/g, '');
                                           if (cleaned !== $event.target.value) {
                                               $event.target.value = cleaned;
                                               $event.target.dispatchEvent(new Event('input', { bubbles: true }));
                                           }
                                       "
                                       placeholder="09xxxxxxxxx"
                                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-600/50 focus:border-primary-600 transition-colors duration-200 @error('customerPhone') border-red-400/50 @enderror">
                                @error('customerPhone') <p class="text-xs text-red-600 dark:text-red-300 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="button" @click="next()" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-full text-sm font-bold uppercase tracking-widest transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            Continue →
                        </button>
                    </div>
                </div>

                {{-- Step 2: Visit Dates --}}
                <div x-show="step === 2"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-x-4"
                     x-transition:enter-end="opacity-100 translate-x-0"
                     class="space-y-4">

                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
                        <h2 class="font-display text-lg font-semibold text-gray-900 dark:text-white mb-4" x-ref="stepHeading2" tabindex="-1">Visit Dates</h2>

                        <div x-data="dateSelector()" x-init="init()" class="space-y-4">

                            {{-- Selected Dates Display --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 cursor-pointer"
                                     :class="selecting === 'checkin' ? 'ring-2 ring-primary-500' : ''"
                                     @click="selecting = 'checkin'">
                                    <div class="w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-600 dark:text-primary-400 shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-0.5">Check-in</p>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white" x-text="checkIn ? formatDate(checkIn) : 'Select date'"></p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 cursor-pointer"
                                     :class="selecting === 'checkout' ? 'ring-2 ring-primary-500' : ''"
                                     @click="selecting = 'checkout'">
                                    <div class="w-9 h-9 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-gray-500 dark:text-gray-400 shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-0.5">Check-out</p>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white" x-text="checkOut ? formatDate(checkOut) : 'Same day'"></p>
                                    </div>
                                </div>
                            </div>

                            {{-- Check-in time (Wire-bound, browser time default) --}}
                            <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-600 dark:text-primary-400 shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Check-in Time</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Used for both check-in and check-out</p>
                                    </div>
                                </div>
                                <input type="time" wire:model.live="checkInTime"
                                       class="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500" />
                            </div>

                            {{-- Calendar navigation --}}
                            <div class="flex items-center justify-between mb-2">
                                <button type="button" @click="prevMonth()"
                                        class="flex items-center justify-center w-8 h-8 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                </button>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white" x-text="currentMonthName + ' ' + currentYear"></span>
                                <button type="button" @click="nextMonth()"
                                        class="flex items-center justify-center w-8 h-8 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </button>
                            </div>

                            {{-- Calendar grid --}}
                            <div class="grid grid-cols-7 gap-1 text-center">
                                <template x-for="day in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="day">
                                    <span class="text-[10px] font-bold uppercase text-gray-400 dark:text-gray-500 py-1" x-text="day"></span>
                                </template>
                                <template x-for="blank in firstDayOffset" :key="'blank-'+blank">
                                    <span></span>
                                </template>
                                <template x-for="day in daysInMonth" :key="day.date">
                                    <button type="button"
                                            @click="selectDate(day.date)"
                                            :disabled="day.isDisabled"
                                            :class="{
                                                'bg-primary-600 text-white shadow-md': day.date === checkIn || day.date === checkOut,
                                                'bg-primary-100 dark:bg-primary-900/30 text-primary-800 dark:text-primary-200': isInRange(day.date) && day.date !== checkIn && day.date !== checkOut,
                                                'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-300 cursor-not-allowed': day.isBooked,
                                                'hover:bg-gray-100 dark:hover:bg-gray-700': !day.isDisabled && !day.isBooked && day.date !== checkIn && day.date !== checkOut,
                                                'text-gray-300 dark:text-gray-600 cursor-not-allowed': day.isDisabled,
                                                'text-gray-900 dark:text-white': !day.isDisabled && !day.isBooked && day.date !== checkIn && day.date !== checkOut
                                            }"
                                            class="h-9 rounded-xl text-sm font-medium transition-all duration-200 active:scale-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                            :title="day.isBooked ? 'Unavailable' : ''">
                                        <span x-text="day.dayNumber"></span>
                                    </button>
                                </template>
                            </div>

                            <p x-show="error" x-text="error" class="text-xs text-red-500 mt-2"></p>

                            {{-- Booked dates list --}}
                            @if(!empty($this->bookedDateRanges))
                                <div class="mt-5 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-400/30 rounded-xl p-4">
                                    <h4 class="text-[10px] font-bold uppercase tracking-wider text-red-700 dark:text-red-300 mb-2 flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        Already Booked Dates
                                    </h4>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($this->bookedDateRanges as $range)
                                            <span wire:key="range-{{ md5($range['start'] . '|' . $range['end']) }}"
                                                  class="inline-flex items-center px-3 py-1 rounded-full bg-white dark:bg-gray-800 border border-red-200 dark:border-red-400/30 text-red-700 dark:text-red-300 text-xs font-medium">
                                                {{ \Carbon\Carbon::parse($range['start'])->format('M d') }} – {{ \Carbon\Carbon::parse($range['end'])->format('M d') }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="mt-5 text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    All dates are currently open for booking.
                                </div>
                            @endif
                        </div>
                    </div>

                    <p x-show="errors.dates" x-text="errors.dates" class="text-xs text-red-600 dark:text-red-300"></p>

                    <div class="flex justify-between">
                        <button type="button" @click="prev()" class="px-6 py-3 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-full text-sm font-bold uppercase tracking-widest transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-500/50">
                            ← Back
                        </button>
                        <button type="button" @click="next()" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-full text-sm font-bold uppercase tracking-widest transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            Continue →
                        </button>
                    </div>
                </div>

                {{-- Step 3: Extra Services (conditional) --}}
                @if($this->availableServices->isNotEmpty())
                    <div x-show="step === 3"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-x-4"
                         x-transition:enter-end="opacity-100 translate-x-0"
                         class="space-y-4">

                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
                            <h2 class="font-display text-lg font-semibold text-gray-900 dark:text-white mb-4" x-ref="stepHeading3" tabindex="-1">Extra Services</h2>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-4">
                                @foreach($this->availableServices as $service)
                                    @php $isAdded = isset($selectedServices[$service->id]); @endphp
                                    <button type="button"
                                            wire:key="service-{{ $service->id }}"
                                            wire:click="{{ $isAdded ? 'removeService' : 'addService' }}({{ $service->id }})"
                                            class="flex items-center justify-between gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 {{ $isAdded ? 'border-primary-600 bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300' : '' }}">
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
                                                @php $svc = $this->selectedServiceModels->get($serviceId); @endphp
                                                @if($svc)
                                                    <tr class="border-b border-gray-100 dark:border-gray-700 last:border-0" wire:key="selected-service-{{ $serviceId }}">
                                                        <td class="py-2.5 px-4 text-gray-700 dark:text-gray-200">{{ $svc->name }}</td>
                                                        <td class="py-2.5 px-4 text-center text-gray-500 dark:text-gray-400">{{ $qty }}</td>
                                                        <td class="py-2.5 px-4 text-right text-gray-900 dark:text-white font-medium">₱{{ number_format($svc->price * $qty, 2) }}</td>
                                                        <td class="py-2.5 px-3">
                                                            <button type="button" wire:click="removeService({{ $serviceId }})"
                                                                    class="w-5 h-5 rounded-full border border-red-300 dark:border-red-500/40 text-red-500 dark:text-red-300 hover:bg-red-500 hover:text-white hover:border-transparent inline-flex items-center justify-center transition-all text-[11px] active:scale-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/50">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
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
                            <button type="button" @click="prev()" class="px-6 py-3 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-full text-sm font-bold uppercase tracking-widest transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-500/50">
                                ← Back
                            </button>
                            <button type="button" @click="next()" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-full text-sm font-bold uppercase tracking-widest transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                Continue →
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Payment Step (Step 3 or 4 depending on services) --}}
                @php $paymentStep = $this->availableServices->isNotEmpty() ? 4 : 3; @endphp
                <div x-show="step === {{ $paymentStep }}"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-x-4"
                     x-transition:enter-end="opacity-100 translate-x-0"
                     class="space-y-4">

                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
                        <h2 class="font-display text-lg font-semibold text-gray-900 dark:text-white mb-4"
                            x-ref="stepHeading{{ $paymentStep }}" tabindex="-1">Payment Method</h2>

                        {{-- Booking mode --}}
                        <div class="mb-4">
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Booking Type</label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="cursor-pointer group">
                                    <input type="radio" wire:model.live="bookingMode" value="full" class="sr-only peer">
                                    <div class="flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-center transition-all duration-200 cursor-pointer peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 peer-checked:shadow-lg active:scale-[0.98]">
                                        <svg class="w-8 h-8 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <p class="text-gray-900 dark:text-white font-semibold text-sm">Book Now</p>
                                        <p class="text-gray-500 dark:text-gray-400 text-[11px]">Pay 100% online</p>
                                    </div>
                                </label>
                                <label class="cursor-pointer group">
                                    <input type="radio" wire:model.live="bookingMode" value="reservation" class="sr-only peer">
                                    <div class="flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-center transition-all duration-200 cursor-pointer peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 peer-checked:shadow-lg active:scale-[0.98]">
                                        <svg class="w-8 h-8 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v10a2 2 0 002 2h14a2 2 0 002-2V7a2 2 0 00-2-2H5z"/>
                                        </svg>
                                        <p class="text-gray-900 dark:text-white font-semibold text-sm">Reserve</p>
                                        <p class="text-gray-500 dark:text-gray-400 text-[11px]">Pay 20% reservation fee</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        {{-- Payment method --}}
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            @foreach([
                                ['gcash',   'GCash'],
                                ['paymaya', 'Maya'],
                                ['card',    'Credit / Debit'],
                            ] as [$val, $label])
                                <label class="relative cursor-pointer group" wire:key="payment-method-{{ $val }}">
                                    <input type="radio" wire:model.live="paymentMethod" value="{{ $val }}" class="sr-only peer">
                                    <div class="flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-center transition-all duration-200 peer-hover:border-gray-300 dark:peer-hover:border-gray-600 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500 peer-focus-visible:ring-offset-2 peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/20 peer-checked:shadow-md active:scale-[0.98]">
                                        <div class="absolute top-3 right-3 opacity-0 peer-checked:opacity-100 text-primary-600 dark:text-primary-400 transition-opacity duration-200">
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>

                                        @if($val === 'gcash')
                                            <svg class="w-10 h-10" viewBox="0 0 32 32" fill="none">
                                                <circle cx="16" cy="16" r="16" fill="#007DFE"/>
                                                <text x="16" y="21" text-anchor="middle" fill="white" font-size="13" font-weight="900" font-family="sans-serif">G</text>
                                            </svg>
                                        @elseif($val === 'paymaya')
                                            <svg class="w-10 h-10" viewBox="0 0 32 32" fill="none">
                                                <circle cx="16" cy="16" r="16" fill="#111827"/>
                                                <text x="16" y="21" text-anchor="middle" fill="#00C6D7" font-size="13" font-weight="900" font-family="sans-serif">M</text>
                                            </svg>
                                        @else
                                            <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-600 dark:text-gray-300">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <rect x="2" y="5" width="20" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
                                                    <line x1="2" y1="10" x2="22" y2="10" stroke="currentColor" stroke-width="2"/>
                                                </svg>
                                            </div>
                                        @endif

                                        <p class="text-gray-900 dark:text-white font-semibold text-sm">{{ $label }}</p>
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        {{-- Secure payment notice --}}
                        <div class="mt-5 flex items-start gap-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4">
                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <div>
                                <p class="text-blue-800 dark:text-blue-200 text-sm font-medium">Secure Checkout via PayMongo</p>
                                <p class="text-blue-600 dark:text-blue-300/80 text-xs mt-0.5 leading-relaxed">
                                    You will be redirected to complete your payment securely.
                                </p>
                            </div>
                        </div>

                        {{-- Action buttons --}}
                        <div class="flex flex-col-reverse sm:flex-row justify-between gap-4 mt-8">
                            <button type="button" @click="prev()" class="w-full sm:w-auto px-6 py-3 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-full text-sm font-bold uppercase tracking-widest transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-500/50 active:scale-95">
                                ← Back
                            </button>
                            <button wire:click="submit" wire:loading.attr="disabled"
                                    class="relative w-full sm:w-auto px-8 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-full text-sm font-bold uppercase tracking-widest transition-all disabled:opacity-70 disabled:cursor-not-allowed shadow-lg shadow-primary-500/30 hover:shadow-primary-500/50 hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98] flex items-center justify-center min-w-[200px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 data-loading:opacity-50">
                                <span wire:loading.remove>Proceed to Pay</span>
                                <span wire:loading class="flex items-center gap-2">
                                    <svg class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Processing…
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            {{-- ═══════════════════════════════════════════════════
                 Summary Sidebar
                 ═══════════════════════════════════════════════════ --}}
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
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ $property->propertyType->name ?? 'Activity' }} · {{ $property->tenant->name }}
                        </p>
                        <div class="flex items-baseline gap-1.5 mt-3">
                            <span class="font-display text-3xl text-primary-600 dark:text-primary-400">₱{{ number_format($property->price, 2) }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">per unit</span>
                        </div>
                    </div>

                    <div class="p-6 border-b border-gray-200 dark:border-gray-700 space-y-3">
                        <dl>
                            <div class="flex justify-between items-center text-sm">
                                <dt class="text-gray-600 dark:text-gray-300">
                                    {{ $totalDays }} day{{ $totalDays > 1 ? 's' : '' }} × ₱{{ number_format($property->price, 2) }}
                                </dt>
                                <dd class="font-semibold text-gray-900 dark:text-white">
                                    ₱{{ number_format($property->price * $totalDays, 2) }}
                                </dd>
                            </div>

                            @foreach($selectedServices as $serviceId => $qty)
                                @php $svc = $this->selectedServiceModels->get($serviceId); @endphp
                                @if($svc)
                                    <div class="flex justify-between items-center text-sm mt-2" wire:key="summary-service-{{ $serviceId }}">
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
                                <span class="font-display text-2xl font-semibold text-primary-600 dark:text-primary-400">
                                    ₱{{ number_format($reservationFee, 2) }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Balance on Arrival</span>
                                <span class="font-display text-lg font-semibold text-gray-900 dark:text-white">
                                    ₱{{ number_format($balanceOnArrival, 2) }}
                                </span>
                            </div>
                        @else
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Total Due</span>
                                <span class="font-display text-3xl font-semibold text-primary-600 dark:text-primary-400">
                                    ₱{{ number_format($totalAmount, 2) }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         Mobile Sticky Summary
         ═══════════════════════════════════════════════════════ --}}
    <div class="lg:hidden fixed bottom-0 left-0 right-0 z-50 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 shadow-lg p-4">
        <div class="flex items-center justify-between gap-4 max-w-7xl mx-auto">
            <div class="flex-1 min-w-0">
                <p class="text-xs text-gray-500 dark:text-gray-400">Total due</p>
                <p class="font-display text-xl font-bold text-gray-900 dark:text-white">
                    ₱{{ number_format($bookingMode === 'reservation' ? $reservationFee : $totalAmount, 2) }}
                </p>
            </div>
            <button type="button"
                    @click="goTo({{ $this->availableServices->isNotEmpty() ? 4 : 3 }})"
                    class="shrink-0 px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-full text-sm font-bold uppercase tracking-widest transition shadow-lg shadow-primary-500/30 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
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

    <script>
        function dateSelector() {
            return {
                checkIn: @json($check_in),
                checkOut: @json($check_out),
                bookedDates: @json($this->bookedDatesArray),
                today: @json(now()->format('Y-m-d')),
                maxDate: @json(now()->addDays(30)->format('Y-m-d')),
                currentMonth: new Date().getMonth(),
                currentYear: new Date().getFullYear(),
                selecting: 'checkin',
                error: '',

                init() {},

                formatDate(dateStr) {
                    if (!dateStr) return '';
                    const d = new Date(dateStr + 'T00:00:00');
                    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                },

                isBooked(dateStr) {
                    return this.bookedDates.includes(dateStr);
                },

                isPast(dateStr) {
                    return dateStr < this.today;
                },

                isBeyondMax(dateStr) {
                    return dateStr > this.maxDate;
                },

                isInRange(dateStr) {
                    if (!this.checkIn || !this.checkOut) return false;
                    return dateStr > this.checkIn && dateStr < this.checkOut;
                },

                get daysInMonth() {
                    const year  = this.currentYear;
                    const month = this.currentMonth;
                    const days  = [];
                    const totalDays = new Date(year, month + 1, 0).getDate();

                    for (let day = 1; day <= totalDays; day++) {
                        const dateObj = new Date(year, month, day);
                        const dateStr = dateObj.getFullYear() + '-'
                            + String(dateObj.getMonth() + 1).padStart(2, '0') + '-'
                            + String(dateObj.getDate()).padStart(2, '0');

                        days.push({
                            date: dateStr,
                            dayNumber: day,
                            isBooked: this.isBooked(dateStr),
                            isDisabled: this.isPast(dateStr) || this.isBeyondMax(dateStr),
                        });
                    }
                    return days;
                },

                get firstDayOffset() {
                    return new Date(this.currentYear, this.currentMonth, 1).getDay();
                },

                get currentMonthName() {
                    return new Date(this.currentYear, this.currentMonth)
                        .toLocaleDateString('en-US', { month: 'long' });
                },

                prevMonth() {
                    this.currentMonth--;
                    if (this.currentMonth < 0) {
                        this.currentMonth = 11;
                        this.currentYear--;
                    }
                },

                nextMonth() {
                    this.currentMonth++;
                    if (this.currentMonth > 11) {
                        this.currentMonth = 0;
                        this.currentYear++;
                    }
                },

                selectDate(dateStr) {
                    if (this.isBooked(dateStr) || this.isPast(dateStr) || this.isBeyondMax(dateStr)) return;

                    if (this.selecting === 'checkin') {
                        this.checkIn  = dateStr;
                        this.checkOut = dateStr;
                        this.selecting = 'checkout';
                        this.error = '';
                    } else {
                        if (!this.checkIn || dateStr < this.checkIn) {
                            this.checkIn  = dateStr;
                            this.checkOut = dateStr;
                            this.selecting = 'checkout';
                            this.error = '';
                        } else {
                            // Verify the entire range is free of booked dates.
                            let start = new Date(this.checkIn + 'T00:00:00');
                            let end   = new Date(dateStr + 'T00:00:00');

                            for (let d = start; d <= end; d.setDate(d.getDate() + 1)) {
                                const iso = d.getFullYear() + '-'
                                    + String(d.getMonth() + 1).padStart(2, '0') + '-'
                                    + String(d.getDate()).padStart(2, '0');

                                if (this.isBooked(iso)) {
                                    this.error = 'Selected range includes booked dates. Please choose different dates.';
                                    return;
                                }
                            }

                            this.checkOut = dateStr;
                            this.error    = '';
                            this.selecting = 'checkin';
                        }
                    }

                    this.$wire.setDates(this.checkIn, this.checkOut);
                },
            };
        }
    </script>
</div>