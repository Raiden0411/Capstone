{{-- resources/views/public/pages/⚡create-booking.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Locked;
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
    /**
     * The Property row this booking is for.
     *
     * NOT typed as non-nullable `Property` because Livewire v4's
     * typed-model binding re-fetches the row on hydrate WITHOUT
     * `withoutGlobalScope(TenantScope::class)` and WITHOUT the mount()
     * eager loads. For an authenticated tourist (`tenant_id === null`)
     * the TenantScope's `whereRaw('1 = 0')` filters the property out
     * and a non-nullable binding throws ModelNotFoundException before
     * the `hydrate()` hook can intervene.
     *
     * Approach: pin the ID on a Locked scalar (the client can't change
     * it), make the model nullable so Livewire can null it during its
     * own rehydrate pass, and re-fetch explicitly in `hydrate()` with
     * the tenant-scope bypass and the mount-time eager loads.
     */
    #[Locked]
    public int $propertyId;

    public ?Property $property = null;

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

    // ── Totals (display-only; recomputed server-side on submit) ──
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
        $this->propertyId = (int) $publicproperty;

        $this->property = Property::withoutGlobalScope(TenantScope::class)
            ->with(['tenant', 'images', 'propertyType'])
            ->findOrFail($this->propertyId);

        $this->assertPropertyIsBookable();

        $this->customerName  = (string) Auth::user()?->name;
        $this->customerEmail = (string) Auth::user()?->email;
        $this->customerPhone = (string) (Auth::user()?->phone ?? '');

        // Default to the first available day, NOT today. If today is
        // already booked, starting there trapped the user — every
        // attempt to extend produced "includes booked dates" because
        // the start itself was invalid.
        $firstAvailable    = $this->firstAvailableDate;
        $this->check_in    = $firstAvailable;
        $this->check_out   = $firstAvailable;
        $this->checkInTime = now()->format('H:i');

        $this->calculateTotal();
    }

    /**
     * Livewire v4 re-fetches typed model properties on hydrate WITHOUT
     * the mount-time eager loads. Re-fetch here — with the tenant-scope
     * bypass AND the eager loads — so the template and every availability
     * check don't trigger lazy-load queries on each action.
     */
    public function hydrate(): void
    {
        $this->property = Property::withoutGlobalScope(TenantScope::class)
            ->with(['tenant', 'images', 'propertyType'])
            ->find($this->propertyId);

        $this->assertPropertyIsBookable();
    }

    protected function assertPropertyIsBookable(): void
    {
        if (
            !$this->property
            || !$this->property->tenant_id
            || !$this->property->is_active
            || !$this->property->tenant
        ) {
            abort(404);
        }
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
            'selectedServices.*' => ['integer', 'min:1', 'max:100'],
        ];
    }

    // ─────────────────────────────────────────────────────────
    //  Date + service mutations
    // ─────────────────────────────────────────────────────────

    public function setDates($checkIn, $checkOut): void
    {
        if (!is_string($checkIn) || !is_string($checkOut)) {
            return;
        }

        try {
            $in  = Carbon::createFromFormat('Y-m-d', $checkIn);
            $out = Carbon::createFromFormat('Y-m-d', $checkOut);
        } catch (\Throwable) {
            return;
        }

        $this->check_in  = $in->format('Y-m-d');
        $this->check_out = $out->format('Y-m-d');

        $this->validateDateRange();
        $this->calculateTotal();
    }

    public function updatedCheckIn(): void
    {
        if ($this->check_in === '') {
            $this->calculateTotal();
            return;
        }

        try {
            $in = Carbon::parse($this->check_in);
        } catch (\Throwable) {
            return;
        }

        if ($this->check_out === '') {
            $this->check_out = $this->check_in;
        } else {
            try {
                if ($in->gt(Carbon::parse($this->check_out))) {
                    $this->check_out = $this->check_in;
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        $this->validateDateRange();
        $this->calculateTotal();
    }

    public function updatedCheckOut(): void
    {
        if ($this->check_out === '') {
            $this->calculateTotal();
            return;
        }

        try {
            $out = Carbon::parse($this->check_out);
        } catch (\Throwable) {
            return;
        }

        if ($this->check_in === '') {
            $this->check_in = $this->check_out;
        } else {
            try {
                if ($out->lt(Carbon::parse($this->check_in))) {
                    $this->check_out = $this->check_in;
                }
            } catch (\Throwable) {
                // ignore
            }
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

    /**
     * Add one unit of a service. Only services belonging to the
     * property's tenant are accepted — a client-tampered ID from
     * another tenant is silently rejected.
     */
    public function addService(int $serviceId): void
    {
        $exists = Service::withoutGlobalScope(TenantScope::class)
            ->where('id', $serviceId)
            ->where('tenant_id', $this->property->tenant_id)
            ->where('is_active', true)
            ->exists();

        if (!$exists) {
            return;
        }

        $current = (int) ($this->selectedServices[$serviceId] ?? 0);
        $this->selectedServices[$serviceId] = min(100, $current + 1);

        $this->calculateTotal();
    }

    /**
     * Decrement one unit. Removes the entry entirely at qty 1 — there
     * is no "0 quantity but still selected" state.
     */
    public function decrementService(int $serviceId): void
    {
        if (!isset($this->selectedServices[$serviceId])) {
            return;
        }

        $current = (int) $this->selectedServices[$serviceId];

        if ($current <= 1) {
            unset($this->selectedServices[$serviceId]);
        } else {
            $this->selectedServices[$serviceId] = $current - 1;
        }

        $this->calculateTotal();
    }

    public function removeService(int $serviceId): void
    {
        unset($this->selectedServices[$serviceId]);
        $this->calculateTotal();
    }

    /**
     * Reset the range to the first available day — NOT today, which
     * may itself be booked.
     */
    public function clearDates(): void
    {
        $target          = $this->firstAvailableDate;
        $this->check_in  = $target;
        $this->check_out = $target;
        $this->calculateTotal();
    }

    /**
     * Recompute all totals from authoritative DB state.
     * Called on every relevant field change AND again in submit() before
     * any write — never trust the client-dehydrated floats.
     */
    public function calculateTotal(): void
    {
        $price = (float) $this->property->price;

        if (empty($this->check_in) || empty($this->check_out)) {
            $this->totalDays   = 1;
            $this->totalAmount = $price;
        } else {
            try {
                $in  = Carbon::parse($this->check_in);
                $out = Carbon::parse($this->check_out);
                $this->totalDays   = max(1, (int) $in->diffInDays($out));
                $this->totalAmount = $price * $this->totalDays;
            } catch (\Throwable) {
                $this->totalDays   = 1;
                $this->totalAmount = $price;
            }
        }

        foreach ($this->selectedServices as $serviceId => $qty) {
            if ($svc = $this->selectedServiceModels->get($serviceId)) {
                $this->totalAmount += (float) $svc->price * (int) $qty;
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
            ->where('tenant_id', $this->property->tenant_id)
            ->where('is_active', true)
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
     * First day within the 90-day booking window that isn't already
     * booked for this property. Falls back to today if nothing is
     * free (an edge case that only happens when the calendar is
     * fully booked).
     */
    #[Computed]
    public function firstAvailableDate(): string
    {
        $booked = $this->bookedDatesArray;
        $cursor = now()->startOfDay();

        for ($i = 0; $i < 90; $i++) {
            $iso = $cursor->format('Y-m-d');
            if (! in_array($iso, $booked, true)) {
                return $iso;
            }
            $cursor->addDay();
        }

        return now()->format('Y-m-d');
    }

    /**
     * JSON payload for the Alpine date picker. Encoded with JSON_HEX_*
     * flags so it's safe to embed in an HTML data-* attribute.
     */
    #[Computed]
    public function dateSelectorDataJson(): string
    {
        return (string) json_encode([
            'checkIn'        => $this->check_in,
            'checkOut'       => $this->check_out,
            'bookedDates'    => $this->bookedDatesArray,
            'today'          => now()->format('Y-m-d'),
            'maxDate'        => now()->addDays(90)->format('Y-m-d'),
            'firstAvailable' => $this->firstAvailableDate,
        ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG);
    }

    /** @return array<int, array{start: string, end: string}> */
    #[Computed]
    public function bookedDateRanges(): array
    {
        $minDate = now()->format('Y-m-d');
        $maxDate = now()->addDays(90)->format('Y-m-d');

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
     * Explode the booked ranges into individual dates.
     *
     * NOTE ON SEMANTICS — currently INCLUSIVE of `check_out`:
     *   A booking Oct 1 → Oct 3 marks Oct 1, Oct 2, and Oct 3 as taken.
     *   That means no same-day turnover: a new guest cannot check in on
     *   the same day another checks out. The `submit()` conflict check
     *   uses EXCLUSIVE comparisons (`E_out > N_in`), so it would accept
     *   that turnover. The two are inconsistent by design here — the
     *   frontend is stricter, which is the safe side of the mismatch.
     *
     *   If you want standard hotel-style same-day turnover (check_out
     *   morning is free for the next arrival), change the loop below to
     *   include `check_in` only, then walk up to but NOT including
     *   `check_out`:
     *
     *     $dates[] = $start->format('Y-m-d');
     *     for ($d = $start->copy()->addDay(); $d->lt($end); $d->addDay()) {
     *         $dates[] = $d->format('Y-m-d');
     *     }
     *
     *   That also requires matching changes in `validateDateRange()`
     *   below and the `submit()` conflict query. Pick one semantic and
     *   apply it in all three places.
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
        // Livewire actions bypass route middleware. Re-verify the session.
        abort_unless(Auth::check(), 403, 'Your session has expired. Please sign in again.');

        $this->validate();
        $this->validateDateRange();

        // ── Recompute totals from authoritative DB state ──
        $this->calculateTotal();

        $tenantId = $this->property->tenant_id;

        if (!$tenantId) {
            session()->flash('error', 'Property not linked to a valid business.');
            return null;
        }

        if (!$this->property->is_active) {
            session()->flash('error', 'This activity is currently unavailable. Please choose another.');
            return null;
        }

        if ($this->check_out < $this->check_in) {
            session()->flash('error', 'End date must be on or after start date.');
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
            //
            // NOTE: check_in / check_out are MySQL DATE columns — they
            // physically cannot store a time. booking_time preserves
            // the user-picked HH:MM so the receipt can display it.
            $booking = Booking::create([
                'tenant_id'         => $tenantId,
                'user_id'           => Auth::id(),
                'booking_reference' => 'BK-' . strtoupper(Str::random(8)),
                'check_in'          => $checkInDateTime,
                'check_out'         => $checkOutDateTime,
                'booking_time'      => $this->checkInTime,   // ← preserves the selected start time
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

            // 5. Booking services — iterate the tenant-scoped collection.
            foreach ($this->selectedServiceModels as $serviceId => $svc) {
                $qty = (int) ($this->selectedServices[$serviceId] ?? 0);
                if ($qty < 1) {
                    continue;
                }

                BookingService::create([
                    'tenant_id'  => $tenantId,
                    'booking_id' => $booking->id,
                    'service_id' => $serviceId,
                    'quantity'   => $qty,
                    'subtotal'   => (float) $svc->price * $qty,
                ]);
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
                'payment_method_types' => [$this->paymentMethod],
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

            // 8. Record the pending payment.
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
                'booking_id'    => $booking->id,
                'session_id'    => $session['id'],
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

@push('styles')
    @once
        <style>
            .step-dot {
                width: 36px; height: 36px; border-radius: 50%;
                display: flex; align-items: center; justify-content: center;
                font-size: 13px; font-weight: 800;
                transition: all .35s cubic-bezier(.34,1.56,.64,1);
                flex-shrink: 0;
            }
            .step-dot.done    { background: #059669; color: #fff; box-shadow: 0 0 0 4px rgba(5,150,105,.2); }
            .step-dot.active  { background: #10b981; color: #fff; box-shadow: 0 0 0 5px rgba(16,185,129,.25); }
            .step-dot.pending { background: #e5e7eb; color: #6b7280; border: 1px solid #d1d5db; }
            .dark .step-dot.pending { background: #374151; color: #e5e7eb; border-color: #6b7280; }

            .step-panel {
                animation: stepSlideIn .25s cubic-bezier(.16,1,.3,1);
            }
            @keyframes stepSlideIn {
                from { opacity: 0; transform: translateX(16px); }
                to   { opacity: 1; transform: translateX(0); }
            }
            @media (prefers-reduced-motion: reduce) {
                .step-panel { animation: none; }
            }

            /* Calendar day cell — 44px touch target minimum. */
            .cal-day {
                min-height: 44px;
                min-width: 0;
                border-radius: 12px;
                font-weight: 500;
                transition: background-color .15s, color .15s, transform .1s;
            }
            .cal-day:active:not(:disabled) { transform: scale(.92); }
        </style>
    @endonce
@endpush

<div class="relative z-10 min-h-screen text-gray-900 dark:text-gray-100"
     x-data="{
         step: 1,
         maxStep: {{ $this->availableServices->isNotEmpty() ? 4 : 3 }},
         errors: {},
         next() {
             if (this.step === 1) {
                 if (!this.$wire.customerName.trim()) this.errors.name = 'Full name is required.';
                 else delete this.errors.name;
                 if (!this.$wire.customerEmail.trim()) this.errors.email = 'Email is required.';
                 else delete this.errors.email;
                 if (Object.keys(this.errors).length > 0) return;
             }
             if (this.step === 2) {
                 if (!this.$wire.check_in || !this.$wire.check_out) {
                     this.errors.dates = 'Please select both start and end dates.';
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
     }">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-32 lg:pb-12">

        {{-- Back link --}}
        <div class="mb-6">
            <a href="{{ route('tenant.show', $property->tenant->slug) }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-xs uppercase tracking-wider text-gray-600 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors group active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
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
                $steps[2] = ['Visit Dates', 'Start & end'];

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
                        <span x-text="{{ $num }} < step ? '✓' : '{{ $num }}'"></span>
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
            <div x-data="{ show: true }"
                 x-init="setTimeout(() => show = false, 5000)"
                 :class="show ? '' : 'hidden'"
                 role="alert"
                 aria-live="polite"
                 class="bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-400/40 text-rose-700 dark:text-rose-200 p-4 rounded-2xl text-sm mb-6 flex items-start gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <span class="flex-1">{{ session('error') }}</span>
                <button type="button"
                        @click="show = false"
                        class="inline-flex items-center justify-center h-7 w-7 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                               transition-all duration-200 active:scale-95
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 shrink-0"
                        aria-label="Dismiss error">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-8 items-start">

            {{-- ═══════════════════════════════════════════════════
                 Main Form Area
                 ═══════════════════════════════════════════════════ --}}
            <div class="space-y-4">

                {{-- ═══ STEP 1: Guest Details ═══ --}}
                <div :class="step === 1 ? 'step-panel space-y-4' : 'hidden'">

                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
                        <h2 class="font-display text-lg font-semibold text-gray-900 dark:text-white mb-4" x-ref="stepHeading1" tabindex="-1">Your Details</h2>

                        <div x-data="{ showFields: {{ Auth::check() ? 'false' : 'true' }} }">
                            @auth
                                <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 mb-4">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-8 h-8 rounded-full bg-primary-600 flex items-center justify-center text-white text-xs font-bold shrink-0">
                                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-gray-900 dark:text-white text-sm font-semibold truncate">{{ Auth::user()->name }}</p>
                                            <p class="text-gray-500 dark:text-gray-400 text-xs truncate">{{ Auth::user()->email }}</p>
                                        </div>
                                    </div>
                                    <button type="button"
                                            @click="showFields = !showFields"
                                            class="text-[10px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded-md px-2 py-1 shrink-0">
                                        <span x-text="showFields ? 'Done' : 'Edit'"></span>
                                    </button>
                                </div>
                            @endauth

                            <div :class="showFields ? 'grid grid-cols-1 sm:grid-cols-2 gap-3' : 'hidden'">

                                <div>
                                    <label for="customerName" class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Full Name *</label>
                                    <input id="customerName" type="text" wire:model="customerName" placeholder="Your full name"
                                           class="input w-full @error('customerName') border-rose-400/50 @enderror">
                                    @error('customerName') <p class="text-xs text-rose-600 dark:text-rose-300 mt-1">{{ $message }}</p> @enderror
                                    <p x-cloak :class="errors.name ? 'block' : 'hidden'" x-text="errors.name" class="text-xs text-rose-600 dark:text-rose-300 mt-1"></p>
                                </div>

                                <div>
                                    <label for="customerEmail" class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Email *</label>
                                    <input id="customerEmail" type="email" wire:model="customerEmail" placeholder="you@example.com" required
                                           class="input w-full @error('customerEmail') border-rose-400/50 @enderror">
                                    @error('customerEmail') <p class="text-xs text-rose-600 dark:text-rose-300 mt-1">{{ $message }}</p> @enderror
                                    <p x-cloak :class="errors.email ? 'block' : 'hidden'" x-text="errors.email" class="text-xs text-rose-600 dark:text-rose-300 mt-1"></p>
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="customerPhone" class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Phone</label>
                                    <input id="customerPhone"
                                           type="tel"
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
                                           class="input w-full @error('customerPhone') border-rose-400/50 @enderror">
                                    @error('customerPhone') <p class="text-xs text-rose-600 dark:text-rose-300 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="button" @click="next()"
                                class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                       transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                       disabled:opacity-60 disabled:cursor-not-allowed">
                            Continue
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>

                {{-- ═══ STEP 2: Visit Dates ═══ --}}
                <div :class="step === 2 ? 'step-panel space-y-4' : 'hidden'">

                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-4 sm:p-6 shadow-sm">
                        <h2 class="font-display text-lg font-semibold text-gray-900 dark:text-white mb-4" x-ref="stepHeading2" tabindex="-1">Visit Dates</h2>

                        <div x-data="dateSelector()"
                             data-date-data="{{ $this->dateSelectorDataJson }}"
                             class="space-y-5">

                            {{-- Quick-range chips --}}
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Quick pick</p>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" @click="quickSelect('today')"
                                            class="inline-flex items-center gap-1 h-9 px-3 rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs font-semibold text-gray-700 dark:text-gray-300
                                                   hover:border-primary-400 dark:hover:border-primary-500 hover:text-primary-600 dark:hover:text-primary-400
                                                   transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                        Today
                                    </button>
                                    <button type="button" @click="quickSelect('tomorrow')"
                                            class="inline-flex items-center gap-1 h-9 px-3 rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs font-semibold text-gray-700 dark:text-gray-300
                                                   hover:border-primary-400 dark:hover:border-primary-500 hover:text-primary-600 dark:hover:text-primary-400
                                                   transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                        Tomorrow
                                    </button>
                                    <button type="button" @click="quickSelect('three-days')"
                                            class="inline-flex items-center gap-1 h-9 px-3 rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs font-semibold text-gray-700 dark:text-gray-300
                                                   hover:border-primary-400 dark:hover:border-primary-500 hover:text-primary-600 dark:hover:text-primary-400
                                                   transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                        3 days
                                    </button>
                                    <button type="button" @click="quickSelect('weekend')"
                                            class="inline-flex items-center gap-1 h-9 px-3 rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs font-semibold text-gray-700 dark:text-gray-300
                                                   hover:border-primary-400 dark:hover:border-primary-500 hover:text-primary-600 dark:hover:text-primary-400
                                                   transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                        This weekend
                                    </button>
                                    <button type="button" @click="clearSelection()"
                                            class="ml-auto inline-flex items-center gap-1 h-9 px-3 rounded-full text-xs font-semibold text-gray-500 dark:text-gray-400
                                                   hover:text-rose-600 dark:hover:text-rose-400 transition-all duration-200 active:scale-95
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Reset
                                    </button>
                                </div>
                            </div>

                            {{-- Selection summary --}}
                            <div class="grid grid-cols-2 sm:grid-cols-[1fr_1fr_auto] gap-2 sm:gap-3">
                                <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 sm:px-4 sm:py-3">
                                    <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-600 dark:text-primary-400 shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-0.5">Start</p>
                                        <p class="text-xs sm:text-sm font-semibold text-gray-900 dark:text-white truncate"
                                           x-text="checkIn ? formatDate(checkIn) : 'Pick a date'"></p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 sm:px-4 sm:py-3">
                                    <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-gray-500 dark:text-gray-400 shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-0.5">End</p>
                                        <p class="text-xs sm:text-sm font-semibold text-gray-900 dark:text-white truncate"
                                           x-text="checkOut ? formatDate(checkOut) : 'Same day'"></p>
                                    </div>
                                </div>

                                <div x-cloak
                                     :class="hasRange ? 'flex' : 'hidden'"
                                     class="col-span-2 sm:col-span-1 items-center gap-2 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-500/30 rounded-xl px-3 py-2.5 sm:px-4 sm:py-3">
                                    <div class="w-8 h-8 rounded-full bg-primary-600 flex items-center justify-center text-white shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400 mb-0.5">Duration</p>
                                        <p class="text-xs sm:text-sm font-bold text-primary-700 dark:text-primary-300" x-text="durationLabel"></p>
                                    </div>
                                </div>
                            </div>

                            {{-- Start time --}}
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-600 dark:text-primary-400 shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Start Time</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Used for both start and end</p>
                                    </div>
                                </div>
                                <input type="time" wire:model.live="checkInTime" class="input" />
                            </div>

                            {{-- Calendar navigation --}}
                            <div class="flex items-center justify-between mb-1">
                                <button type="button" @click="prevMonth()" :disabled="!canGoPrevMonth"
                                        class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-gray-500 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-gray-100 dark:hover:bg-gray-700
                                               transition-all duration-200 active:scale-95
                                               disabled:opacity-30 disabled:cursor-not-allowed
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                        aria-label="Previous month">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                </button>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white" x-text="currentMonthName + ' ' + currentYear"></span>
                                <button type="button" @click="nextMonth()" :disabled="!canGoNextMonth"
                                        class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-gray-500 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-gray-100 dark:hover:bg-gray-700
                                               transition-all duration-200 active:scale-95
                                               disabled:opacity-30 disabled:cursor-not-allowed
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                        aria-label="Next month">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </button>
                            </div>

                            {{-- Calendar grid --}}
                            <div class="grid grid-cols-7 gap-0.5 sm:gap-1">
                                <template x-for="day in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="day">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 text-center py-1.5" x-text="day"></span>
                                </template>

                                <template x-for="blank in firstDayOffset" :key="'blank-'+blank">
                                    <span></span>
                                </template>

                                <template x-for="day in daysInMonth" :key="day.iso">
                                    <button type="button"
                                            class="cal-day text-sm flex items-center justify-center
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                            :disabled="day.isDisabled || day.isBooked"
                                            :aria-label="day.isBooked ? 'Unavailable' : ''"
                                            :title="day.isBooked ? 'Unavailable' : ''"
                                            :class="{
                                                'bg-rose-50 dark:bg-rose-900/30 text-rose-300 dark:text-rose-500/60 line-through cursor-not-allowed': day.isBooked,
                                                'bg-primary-600 text-white shadow-md font-bold': !day.isBooked && (day.iso === checkIn || day.iso === checkOut),
                                                'bg-primary-100 dark:bg-primary-900/30 text-primary-800 dark:text-primary-200': !day.isBooked && isInRange(day.iso),
                                                'text-gray-300 dark:text-gray-600 cursor-not-allowed': !day.isBooked && day.isDisabled,
                                                'text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer': !day.isBooked && !day.isDisabled && day.iso !== checkIn && day.iso !== checkOut && !isInRange(day.iso)
                                            }"
                                            @click="selectDate(day.iso)">
                                        <span x-text="day.dayNumber"></span>
                                    </button>
                                </template>
                            </div>

                            {{-- Inline error --}}
                            <p x-cloak
                               :class="error ? 'flex' : 'hidden'"
                               class="items-start gap-2 text-xs text-rose-600 dark:text-rose-300 bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-500/30 rounded-lg px-3 py-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                </svg>
                                <span x-text="error"></span>
                            </p>

                            {{-- Legend --}}
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-[10px] text-gray-500 dark:text-gray-400 pt-1">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-3 h-3 rounded-sm bg-primary-600"></span>
                                    Selected
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-3 h-3 rounded-sm bg-primary-100 dark:bg-primary-900/40 border border-primary-300/50"></span>
                                    In range
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-3 h-3 rounded-sm bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-500/30"></span>
                                    Unavailable
                                </span>
                            </div>

                            {{-- Booked ranges list --}}
                            @if(!empty($this->bookedDateRanges))
                                <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-xl p-3 sm:p-4">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
                                        Already booked · {{ count($this->bookedDateRanges) }} {{ count($this->bookedDateRanges) === 1 ? 'range' : 'ranges' }}
                                    </p>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($this->bookedDateRanges as $range)
                                            <span wire:key="range-{{ md5($range['start'] . '|' . $range['end']) }}"
                                                  class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 text-[11px] font-medium">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                                {{ \Carbon\Carbon::parse($range['start'])->format('M d') }} – {{ \Carbon\Carbon::parse($range['end'])->format('M d') }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <p x-cloak
                       :class="errors.dates ? 'block' : 'hidden'"
                       x-text="errors.dates"
                       class="text-xs text-rose-600 dark:text-rose-300"></p>

                    <div class="flex justify-between gap-3">
                        <button type="button" @click="prev()"
                                class="inline-flex items-center justify-center gap-2 h-11 px-4 sm:px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                       transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                       disabled:opacity-60 disabled:cursor-not-allowed">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                            Back
                        </button>
                        <button type="button" @click="next()"
                                class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                       transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                       disabled:opacity-60 disabled:cursor-not-allowed">
                            Continue
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>

                {{-- ═══ STEP 3: Extra Services ═══ --}}
                @if($this->availableServices->isNotEmpty())
                    <div :class="step === 3 ? 'step-panel space-y-4' : 'hidden'">

                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
                            <h2 class="font-display text-lg font-semibold text-gray-900 dark:text-white mb-1" x-ref="stepHeading3" tabindex="-1">Extra Services</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-5">Optional add-ons. Tap to add — adjust quantity with <span class="font-bold">+</span> / <span class="font-bold">−</span>.</p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach($this->availableServices as $service)
                                    @php
                                        $qty = (int) ($selectedServices[$service->id] ?? 0);
                                        $isAdded = $qty > 0;
                                    @endphp
                                    <div wire:key="service-{{ $service->id }}"
                                         class="rounded-xl border {{ $isAdded
                                             ? 'border-primary-500 bg-primary-50/50 dark:bg-primary-900/20 dark:border-primary-500/40'
                                             : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900' }} transition-colors duration-200">

                                        <button type="button"
                                                wire:click="addService({{ $service->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="addService"
                                                class="w-full items-center justify-between gap-3 px-4 py-3 text-left
                                                       transition-all duration-200 active:scale-[0.98]
                                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded-xl
                                                       disabled:opacity-60 disabled:cursor-not-allowed
                                                       {{ $isAdded ? 'hidden' : 'flex' }}">
                                            <span class="min-w-0 flex-1">
                                                <span class="block text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $service->name }}</span>
                                                <span class="block text-xs text-gray-500 dark:text-gray-400 mt-0.5 tabular-nums">₱{{ number_format($service->price, 2) }}</span>
                                            </span>
                                            <span class="shrink-0 inline-flex items-center gap-1 h-8 px-3 rounded-full bg-primary-600 hover:bg-primary-700 text-white text-[10px] font-bold uppercase tracking-wider transition">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
                                                Add
                                            </span>
                                        </button>

                                        <div class="items-center justify-between gap-3 px-4 py-3 {{ $isAdded ? 'flex' : 'hidden' }}">
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $service->name }}</p>
                                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5 tabular-nums">
                                                    ₱{{ number_format($service->price, 2) }} × {{ $qty }} =
                                                    <span class="font-bold text-primary-600 dark:text-primary-400">₱{{ number_format($service->price * $qty, 2) }}</span>
                                                </p>
                                            </div>
                                            <div class="shrink-0 flex items-center gap-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-full p-0.5">
                                                <button type="button"
                                                        wire:click="decrementService({{ $service->id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="decrementService,addService"
                                                        class="inline-flex items-center justify-center w-7 h-7 rounded-full text-gray-600 dark:text-gray-300
                                                               hover:bg-gray-100 dark:hover:bg-gray-700
                                                               transition-all duration-200 active:scale-90
                                                               disabled:opacity-60 disabled:cursor-not-allowed
                                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                                        aria-label="Remove one {{ $service->name }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M20 12H4"/></svg>
                                                </button>
                                                <span class="inline-flex items-center justify-center min-w-[24px] text-sm font-bold tabular-nums text-gray-900 dark:text-white">
                                                    {{ $qty }}
                                                </span>
                                                <button type="button"
                                                        wire:click="addService({{ $service->id }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="addService,decrementService"
                                                        class="inline-flex items-center justify-center w-7 h-7 rounded-full text-white bg-primary-600
                                                               hover:bg-primary-700
                                                               transition-all duration-200 active:scale-90
                                                               disabled:opacity-60 disabled:cursor-not-allowed
                                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                                        aria-label="Add one more {{ $service->name }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex justify-between gap-3">
                            <button type="button" @click="prev()"
                                    class="inline-flex items-center justify-center gap-2 h-11 px-4 sm:px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                           transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                                Back
                            </button>
                            <button type="button" @click="next()"
                                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                           transition-all duration-200 active:scale-95
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                Continue
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                    </div>
                @endif

                {{-- ═══ PAYMENT STEP ═══ --}}
                @php $paymentStep = $this->availableServices->isNotEmpty() ? 4 : 3; @endphp
                <div :class="step === {{ $paymentStep }} ? 'step-panel space-y-4' : 'hidden'">

                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
                        <h2 class="font-display text-lg font-semibold text-gray-900 dark:text-white mb-4"
                            x-ref="stepHeading{{ $paymentStep }}" tabindex="-1">Payment Method</h2>

                        {{-- Booking mode --}}
                        <div class="mb-5">
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Booking Type</label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="cursor-pointer group">
                                    <input type="radio" wire:model.live="bookingMode" value="full" class="sr-only peer">
                                    <div class="flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-center transition-all duration-200 cursor-pointer peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 peer-checked:shadow-lg active:scale-[0.98]">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <p class="text-gray-900 dark:text-white font-semibold text-sm">Book Now</p>
                                        <p class="text-gray-500 dark:text-gray-400 text-[11px]">Pay 100% online</p>
                                    </div>
                                </label>
                                <label class="cursor-pointer group">
                                    <input type="radio" wire:model.live="bookingMode" value="reservation" class="sr-only peer">
                                    <div class="flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-center transition-all duration-200 cursor-pointer peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 peer-checked:shadow-lg active:scale-[0.98]">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v10a2 2 0 002 2h14a2 2 0 002-2V7a2 2 0 00-2-2H5z"/>
                                        </svg>
                                        <p class="text-gray-900 dark:text-white font-semibold text-sm">Reserve</p>
                                        <p class="text-gray-500 dark:text-gray-400 text-[11px]">Pay 20% reservation fee</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        {{-- Payment method --}}
                        <div class="mb-5">
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">Pay with</label>
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
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                            </div>

                                            @if($val === 'gcash')
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" viewBox="0 0 32 32" fill="none" aria-hidden="true">
                                                    <circle cx="16" cy="16" r="16" fill="#007DFE"/>
                                                    <text x="16" y="21" text-anchor="middle" fill="white" font-size="13" font-weight="900" font-family="sans-serif">G</text>
                                                </svg>
                                            @elseif($val === 'paymaya')
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" viewBox="0 0 32 32" fill="none" aria-hidden="true">
                                                    <circle cx="16" cy="16" r="16" fill="#111827"/>
                                                    <text x="16" y="21" text-anchor="middle" fill="#00C6D7" font-size="13" font-weight="900" font-family="sans-serif">M</text>
                                                </svg>
                                            @else
                                                <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-600 dark:text-gray-300">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
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
                        </div>

                        {{-- Secure notice --}}
                        <div class="flex items-start gap-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <div>
                                <p class="text-blue-800 dark:text-blue-200 text-sm font-medium">Secure Checkout via PayMongo</p>
                                <p class="text-blue-600 dark:text-blue-300/80 text-xs mt-0.5 leading-relaxed">
                                    You will be redirected to complete your payment securely.
                                </p>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex flex-col-reverse sm:flex-row justify-between gap-4 mt-8">
                            <button type="button" @click="prev()"
                                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold w-full sm:w-auto
                                           transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                                Back
                            </button>
                            <button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit"
                                    class="inline-flex items-center justify-center gap-2 h-11 px-6 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm w-full sm:w-auto
                                           transition-all duration-200 active:scale-95
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                           disabled:opacity-60 disabled:cursor-not-allowed data-loading:opacity-50">
                                <span wire:loading.remove wire:target="submit">Proceed to Pay</span>
                                <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin w-4 h-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
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
                                 class="w-full h-full object-cover" alt="{{ $property->name }}" loading="lazy" decoding="async">
                        </div>
                    @endif

                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="font-display text-xl font-semibold text-gray-900 dark:text-white leading-tight">{{ $property->name }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ $property->propertyType->name ?? 'Activity' }} · {{ $property->tenant->name }}
                        </p>
                        <div class="flex items-baseline gap-1.5 mt-3">
                            <span class="font-display text-3xl text-primary-600 dark:text-primary-400 tabular-nums">₱{{ number_format($property->price, 2) }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">per unit</span>
                        </div>
                    </div>

                    <div class="p-6 border-b border-gray-200 dark:border-gray-700 space-y-3">
                        @if($check_in && $check_out)
                            <div class="flex justify-between items-center text-xs text-gray-500 dark:text-gray-400">
                                <span>Dates</span>
                                <span class="font-medium text-gray-900 dark:text-white text-right tabular-nums">
                                    {{ \Carbon\Carbon::parse($check_in)->format('M d') }} – {{ \Carbon\Carbon::parse($check_out)->format('M d') }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center text-xs text-gray-500 dark:text-gray-400">
                                <span>Start time</span>
                                <span class="font-medium text-gray-900 dark:text-white text-right tabular-nums">
                                    {{ \Carbon\Carbon::createFromFormat('H:i', $checkInTime)->format('g:i A') }}
                                </span>
                            </div>
                        @endif

                        <dl>
                            <div class="flex justify-between items-center text-sm">
                                <dt class="text-gray-600 dark:text-gray-300">
                                    {{ $totalDays }} day{{ $totalDays > 1 ? 's' : '' }} × ₱{{ number_format($property->price, 2) }}
                                </dt>
                                <dd class="font-semibold text-gray-900 dark:text-white tabular-nums">
                                    ₱{{ number_format($property->price * $totalDays, 2) }}
                                </dd>
                            </div>

                            @foreach($selectedServices as $serviceId => $qty)
                                @php $svc = $this->selectedServiceModels->get($serviceId); @endphp
                                @if($svc)
                                    <div class="flex justify-between items-center text-sm mt-2" wire:key="summary-service-{{ $serviceId }}">
                                        <dt class="text-gray-600 dark:text-gray-300 truncate max-w-[160px]">{{ $svc->name }} ×{{ $qty }}</dt>
                                        <dd class="font-semibold text-gray-900 dark:text-white shrink-0 tabular-nums">₱{{ number_format($svc->price * $qty, 2) }}</dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>
                    </div>

                    <div class="p-6">
                        @if($bookingMode === 'reservation')
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Pay Now (20%)</span>
                                <span class="font-display text-2xl font-semibold text-primary-600 dark:text-primary-400 tabular-nums">
                                    ₱{{ number_format($reservationFee, 2) }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Balance on Arrival</span>
                                <span class="font-display text-lg font-semibold text-gray-900 dark:text-white tabular-nums">
                                    ₱{{ number_format($balanceOnArrival, 2) }}
                                </span>
                            </div>
                        @else
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Total Due</span>
                                <span class="font-display text-3xl font-semibold text-primary-600 dark:text-primary-400 tabular-nums">
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
    <div class="lg:hidden fixed bottom-0 left-0 right-0 z-50 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 shadow-[0_-4px_20px_rgba(0,0,0,0.08)] p-3 pb-safe">
        <div class="flex items-center justify-between gap-3 max-w-7xl mx-auto">
            <div class="flex-1 min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    {{ $bookingMode === 'reservation' ? 'Pay now (20%)' : 'Total due' }}
                </p>
                <p class="font-display text-xl font-bold text-gray-900 dark:text-white leading-tight tabular-nums">
                    ₱{{ number_format($bookingMode === 'reservation' ? $reservationFee : $totalAmount, 2) }}
                </p>
                @if($check_in && $check_out)
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5 truncate tabular-nums">
                        {{ \Carbon\Carbon::parse($check_in)->format('M d') }} → {{ \Carbon\Carbon::parse($check_out)->format('M d') }}
                        · {{ $totalDays }} day{{ $totalDays > 1 ? 's' : '' }}
                    </p>
                @endif
            </div>
            <button type="button"
                    @click="goTo({{ $this->availableServices->isNotEmpty() ? 4 : 3 }})"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm shrink-0
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                           disabled:opacity-60 disabled:cursor-not-allowed">
                Review
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>
</div>