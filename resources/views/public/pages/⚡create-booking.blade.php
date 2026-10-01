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
use App\Services\BookingAvailabilityService;
use App\Services\PayMongoService;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Carbon\Carbon;

new
#[Layout('layouts.app')]
#[Title('Complete Your Booking')]
class extends Component
{
    #[Locked]
    public int $propertyId;

    #[Locked]
    public ?Property $property = null;

    public string $customerName  = '';
    public string $customerEmail = '';
    public string $customerPhone = '';

    public string $check_in     = '';
    public string $check_out    = '';
    public string $checkInTime  = '09:00';

    public bool $dateRangeValid = true;

    public array $selectedServices = [];

    public float $totalAmount      = 0;
    public int   $totalDays        = 1;
    public float $reservationFee   = 0;
    public float $balanceOnArrival = 0;

    public string $bookingMode   = 'full';
    public string $paymentMethod = 'gcash';

    public array $blockedHoursForSelection = [];

    public function mount($publicproperty): void
    {
        $this->propertyId = (int) $publicproperty;
        $this->loadProperty(true);
        $this->assertPropertyIsBookable();

        $account = Auth::user();
        $this->customerName  = (string) ($account?->name  ?? '');
        $this->customerEmail = (string) ($account?->email ?? '');
        $this->customerPhone = (string) ($account?->phone ?? '');

        $firstAvailable = $this->firstAvailableDate;

        $this->check_in    = $firstAvailable;
        $this->check_out   = $firstAvailable;
        $this->checkInTime = now()->format('H:i');

        $this->enforceOperatingHours();
        $this->refreshAvailability();
        $this->enforceMinCheckInTime();
        $this->calculateTotal();
    }

    public function hydrate(): void
    {
        $this->loadProperty(false);
        $this->assertPropertyIsBookable();
    }

    protected function loadProperty(bool $orFail): void
    {
        $query = Property::withoutGlobalScope(TenantScope::class)->with([
            'tenant',
            'images'       => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            'propertyType' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
        ]);

        $this->property = $orFail ? $query->findOrFail($this->propertyId) : $query->find($this->propertyId);
    }

    protected function assertPropertyIsBookable(): void
    {
        if (! $this->property || ! $this->property->tenant_id || ! $this->property->is_active || ! $this->property->tenant) {
            abort(404);
        }
    }

    protected function rules(): array
    {
        return [
            'customerName'  => ['required', 'string', 'max:255'],
            'customerEmail' => ['required', 'email', 'max:255'],
            'customerPhone' => ['required', 'string', 'max:20', 'regex:/^(09|\+639)\d{9}$/'],
            'check_in'      => ['required', 'date', 'after_or_equal:today'],
            'check_out'     => [
                'required',
                'date',
                'after_or_equal:check_in',
                function ($attribute, $value, $fail) {
                    if (! $this->check_in || ! $this->check_out) return;
                    try {
                        $in  = Carbon::parse($this->check_in)->startOfDay();
                        $out = Carbon::parse($this->check_out)->startOfDay();
                    } catch (\Throwable) {
                        return;
                    }

                    if ($out->lt($in)) {
                        $fail('End date must be on or after the start date.');
                        return;
                    }

                    $days   = (int) $in->diffInDays($out) + 1;
                    $limits = $this->durationLimits;

                    if ($days < $limits['min']) {
                        $label = $limits['min'] === 1 ? 'day' : 'days';
                        $fail("Minimum stay for this property is {$limits['min']} {$label}.");
                        return;
                    }

                    if ($limits['max'] !== null && $days > $limits['max']) {
                        $label = $limits['max'] === 1 ? 'day' : 'days';
                        $fail("Maximum stay for this property is {$limits['max']} {$label}.");
                    }
                },
            ],
            'checkInTime'   => [
                'required',
                'date_format:H:i',
                function ($attribute, $value, $fail) {
                    if ($this->check_in !== now()->format('Y-m-d') || ! is_string($value) || $value === '') {
                        return;
                    }
                    if ($value < now()->format('H:i')) {
                        $fail('Start time cannot be earlier than the current time when booking for today.');
                    }
                },
            ],
            'bookingMode'        => ['required', 'in:full,reservation'],
            'paymentMethod'      => ['required', 'in:gcash,paymaya,card'],
            'selectedServices'   => ['array'],
            'selectedServices.*' => ['integer', 'min:1', 'max:100'],
        ];
    }

    protected function enforceMinCheckInTime(): void
    {
        if ($this->check_in !== now()->format('Y-m-d')) return;

        $now = now()->format('H:i');

        if ($this->checkInTime === '' || $this->checkInTime < $now) {
            $this->checkInTime = $now;
        }
    }

    protected function enforceOperatingHours(): void
    {
        if (! $this->property) return;

        $hours = $this->operatingHoursForProperty;
        if ($hours['is_24hr']) return;

        $current = $this->toMinutes($this->checkInTime);
        $opening = $this->toMinutes($hours['opening']);
        $closing = $this->toMinutes($hours['closing']);

        if ($current < $opening || $current > $closing) {
            $this->checkInTime = $hours['opening'];
        }
    }

    protected function refreshAvailability(): void
    {
        if (! $this->property || $this->check_in === '' || $this->check_out === '') {
            $this->blockedHoursForSelection = [];
            return;
        }

        try {
            $blocked = app(BookingAvailabilityService::class)
                ->blockedHoursForSelection($this->property, $this->check_in, $this->check_out);

            $this->blockedHoursForSelection = $blocked;

            $pickedHour = (int) explode(':', $this->checkInTime)[0];

            if (in_array($pickedHour, $blocked, true)) {
                $hours       = $this->operatingHoursForProperty;
                $openingHour = $hours['is_24hr'] ? 0 : (int) explode(':', $hours['opening'])[0];
                $closingHour = $hours['is_24hr'] ? 23 : (int) explode(':', $hours['closing'])[0];

                for ($h = $pickedHour; $h <= $closingHour; $h++) {
                    if (! in_array($h, $blocked, true)) {
                        $this->checkInTime = sprintf('%02d:00', $h);
                        return;
                    }
                }

                for ($h = $openingHour; $h <= $closingHour; $h++) {
                    if (! in_array($h, $blocked, true)) {
                        $this->checkInTime = sprintf('%02d:00', $h);
                        return;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Availability refresh failed', [
                'property_id' => $this->property->id,
                'check_in'    => $this->check_in,
                'check_out'   => $this->check_out,
                'error'       => $e->getMessage(),
            ]);
            $this->blockedHoursForSelection = [];
        }
    }

    protected function toMinutes(?string $time): int
    {
        if (! is_string($time) || $time === '') return 0;
        [$h, $m] = array_pad(explode(':', $time, 2), 2, '0');
        return ((int) $h) * 60 + ((int) $m);
    }

    protected function formatHourForDisplay(int $h): string
    {
        if ($h === 0)  return '12 AM';
        if ($h < 12)   return $h . ' AM';
        if ($h === 12) return '12 PM';
        return ($h - 12) . ' PM';
    }

    public function selectTime(string $time): void
    {
        if (! preg_match('/^\d{2}:\d{2}$/', $time)) return;
        if ($this->check_in === '' || $this->check_out === '') return;
        if (! $this->property) return;

        try {
            $available = app(BookingAvailabilityService::class)
                ->isSlotAvailable($this->property, $this->check_in, $this->check_out, $time);
        } catch (\Throwable $e) {
            Log::warning('selectTime availability check threw', [
                'property_id' => $this->property->id,
                'time'        => $time,
                'error'       => $e->getMessage(),
            ]);
            $available = false;
        }

        if (! $available) {
            session()->flash('error', 'That time slot is no longer available. Please pick another.');
            return;
        }

        $this->checkInTime = $time;
        $this->validateDateRange();
        $this->calculateTotal();
    }

    #[Computed]
    public function durationLimits(): array
    {
        if (! $this->property) {
            return ['min' => 1, 'max' => null];
        }

        return app(BookingAvailabilityService::class)->durationLimits($this->property);
    }

    #[Computed]
    public function operatingHoursForProperty(): array
    {
        if (! $this->property) {
            return ['opening' => '06:00', 'closing' => '20:00', 'is_24hr' => false];
        }

        return app(BookingAvailabilityService::class)->operatingHours($this->property);
    }

    #[Computed]
    public function calendarStatus(): array
    {
        if (! $this->property) return [];

        return app(BookingAvailabilityService::class)->calendarAvailability(
            $this->property,
            now()->format('Y-m-d'),
            now()->addDays(90)->format('Y-m-d'),
        );
    }

    #[Computed]
    public function timeSlots(): array
    {
        $blocked = $this->blockedHoursForSelection;
        $slots   = [];

        for ($h = 0; $h < 24; $h++) {
            $slots[] = [
                'h24'     => sprintf('%02d:00', $h),
                'hour'    => $h,
                'display' => $this->formatHourForDisplay($h),
                'period'  => $h < 12 ? 'AM' : 'PM',
                'blocked' => in_array($h, $blocked, true),
            ];
        }

        return $slots;
    }

    public function setDates($checkIn, $checkOut): void
    {
        if (! is_string($checkIn) || ! is_string($checkOut)) return;

        try {
            $in  = Carbon::createFromFormat('Y-m-d', $checkIn);
            $out = Carbon::createFromFormat('Y-m-d', $checkOut);
        } catch (\Throwable) {
            return;
        }

        if ($out->lt($in)) {
            $out = $in->copy();
        }

        $limits = $this->durationLimits;
        $days   = (int) $in->copy()->startOfDay()->diffInDays($out->copy()->startOfDay()) + 1;

        if ($days < $limits['min']) {
            $out = $in->copy()->addDays($limits['min'] - 1);
        } elseif ($limits['max'] !== null && $days > $limits['max']) {
            $out = $in->copy()->addDays($limits['max'] - 1);
        }

        $this->check_in  = $in->format('Y-m-d');
        $this->check_out = $out->format('Y-m-d');

        $this->enforceMinCheckInTime();
        $this->refreshAvailability();
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

        $limits = $this->durationLimits;

        if ($this->check_out === '') {
            $this->check_out = $in->format('Y-m-d');
        } else {
            try {
                $out = Carbon::parse($this->check_out);
            } catch (\Throwable) {
                $out = $in->copy();
            }

            if ($out->lt($in)) {
                $out = $in->copy();
            }

            $minOut = $in->copy()->addDays($limits['min'] - 1);
            $maxOut = $limits['max'] !== null ? $in->copy()->addDays($limits['max'] - 1) : null;

            if ($out->lt($minOut)) {
                $out = $minOut;
            } elseif ($maxOut !== null && $out->gt($maxOut)) {
                $out = $maxOut;
            }

            $this->check_out = $out->format('Y-m-d');
        }

        $this->enforceMinCheckInTime();
        $this->refreshAvailability();
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
            $this->check_in = $out->format('Y-m-d');
        }

        try {
            $in = Carbon::parse($this->check_in);
        } catch (\Throwable) {
            return;
        }

        if ($out->lt($in)) {
            $this->check_out = $in->format('Y-m-d');
        }

        $limits = $this->durationLimits;
        $days   = (int) $in->copy()->startOfDay()->diffInDays(Carbon::parse($this->check_out)->startOfDay()) + 1;

        if ($days < $limits['min']) {
            $this->check_out = $in->copy()->addDays($limits['min'] - 1)->format('Y-m-d');
        } elseif ($limits['max'] !== null && $days > $limits['max']) {
            $this->check_out = $in->copy()->addDays($limits['max'] - 1)->format('Y-m-d');
        }

        $this->refreshAvailability();
        $this->validateDateRange();
        $this->calculateTotal();
    }

    public function updatedCheckInTime(): void
    {
        $this->enforceMinCheckInTime();
        $this->calculateTotal();
    }

    public function updatedBookingMode(): void
    {
        $this->calculateTotal();
    }

    public function addService(int $serviceId): void
    {
        $exists = Service::withoutGlobalScope(TenantScope::class)
            ->where('id', $serviceId)
            ->where('tenant_id', $this->property->tenant_id)
            ->where('is_active', true)
            ->exists();

        if (! $exists) return;

        $this->selectedServices[$serviceId] = min(100, (int) ($this->selectedServices[$serviceId] ?? 0) + 1);
        $this->calculateTotal();
    }

    public function decrementService(int $serviceId): void
    {
        if (! isset($this->selectedServices[$serviceId])) return;

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

    public function clearDates(): void
    {
        $target = $this->firstAvailableDate;

        $this->check_in  = $target;
        $this->check_out = $target;
        $this->enforceMinCheckInTime();
        $this->refreshAvailability();
        $this->calculateTotal();
    }

    public function calculateTotal(): void
    {
        $price = (float) $this->property->price;

        $this->totalDays   = 1;
        $this->totalAmount = $price;

        if ($this->check_in !== '' && $this->check_out !== '') {
            try {
                $in  = Carbon::parse($this->check_in)->startOfDay();
                $out = Carbon::parse($this->check_out)->startOfDay();

                $this->totalDays   = max(1, (int) $in->diffInDays($out) + 1);
                $this->totalAmount = $price * $this->totalDays;
            } catch (\Throwable) {
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

    #[Computed]
    public function minCheckInTime(): string
    {
        return $this->check_in === now()->format('Y-m-d') ? now()->format('H:i') : '';
    }

    #[Computed]
    public function selectedServiceModels()
    {
        $ids = array_keys($this->selectedServices);
        if (empty($ids)) return collect();

        return Service::withoutGlobalScope(TenantScope::class)
            ->whereIn('id', $ids)
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

    #[Computed]
    public function firstAvailableDate(): string
    {
        $calendar = $this->calendarStatus;
        $cursor   = now()->startOfDay();

        for ($i = 0; $i < 90; $i++) {
            $iso    = $cursor->format('Y-m-d');
            $status = $calendar[$iso] ?? null;

            if (! $status || ! $status['fully_blocked']) {
                return $iso;
            }
            $cursor->addDay();
        }

        return now()->format('Y-m-d');
    }

    #[Computed]
    public function dateSelectorDataJson(): string
    {
        return (string) json_encode([
            'checkIn'          => $this->check_in,
            'checkOut'         => $this->check_out,
            'checkInTime'      => $this->checkInTime,
            'bookedDates'      => $this->bookedDatesArray,
            'today'            => now()->format('Y-m-d'),
            'maxDate'          => now()->addDays(90)->format('Y-m-d'),
            'firstAvailable'   => $this->firstAvailableDate,
            'serverNowEpochMs' => now()->getTimestamp() * 1000,
            'timezone'         => (string) config('app.timezone', 'Asia/Manila'),
            'operatingHours'   => $this->operatingHoursForProperty,
            'blockedHours'     => $this->blockedHoursForSelection,
            'calendarStatus'   => $this->calendarStatus,
            'durationLimits'   => $this->durationLimits,
        ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG);
    }

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
            ->with(['booking' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->select('id', 'check_in', 'check_out')])
            ->get(['id', 'booking_id', 'property_id'])
            ->map(fn ($item) => $item->booking ? [
                'start' => $item->booking->check_in->format('Y-m-d'),
                'end'   => $item->booking->check_out->format('Y-m-d'),
            ] : null)
            ->filter()
            ->values()
            ->all();
    }

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

    protected function validateDateRange(): void
    {
        if (empty($this->check_in) || empty($this->check_out)) {
            $this->dateRangeValid = true;
            return;
        }

        if ($this->check_out < $this->check_in) {
            $this->dateRangeValid = false;
            session()->flash('error', 'End date must be on or after the start date.');
            return;
        }

        try {
            $in  = Carbon::parse($this->check_in)->startOfDay();
            $out = Carbon::parse($this->check_out)->startOfDay();
            $days = (int) $in->diffInDays($out) + 1;
            $limits = $this->durationLimits;

            if ($days < $limits['min']) {
                $this->dateRangeValid = false;
                $label = $limits['min'] === 1 ? 'day' : 'days';
                session()->flash('error', "Minimum stay for this property is {$limits['min']} {$label}.");
                return;
            }

            if ($limits['max'] !== null && $days > $limits['max']) {
                $this->dateRangeValid = false;
                $label = $limits['max'] === 1 ? 'day' : 'days';
                session()->flash('error', "Maximum stay for this property is {$limits['max']} {$label}.");
                return;
            }
        } catch (\Throwable) {
            // fall through
        }

        try {
            $available = app(BookingAvailabilityService::class)
                ->isSlotAvailable($this->property, $this->check_in, $this->check_out, $this->checkInTime);
        } catch (\Throwable) {
            $available = true;
        }

        $this->dateRangeValid = $available;

        if (! $available) {
            session()->flash('error', 'That time slot is not available. Please pick another start time or shift the dates.');
        } else {
            session()->forget('error');
        }
    }

    public function submit()
    {
        $account = Auth::user();
        abort_unless($account, 403, 'Your session has expired. Please sign in again.');

        $throttleKey = 'booking-submit:' . $account->id;
        if (! RateLimiter::attempt($throttleKey, 5, fn () => true, 60)) {
            session()->flash('error', 'Too many booking attempts in a short time. Please wait a minute and try again.');
            return null;
        }

        $this->customerName  = (string) $account->name;
        $this->customerEmail = (string) $account->email;
        $this->customerPhone = (string) ($account->phone ?? '');

        if ($this->customerPhone === '') {
            session()->flash('error', 'Add a phone number to your account before placing a booking. Update your profile to continue.');
            return null;
        }

        $this->enforceMinCheckInTime();
        $this->enforceOperatingHours();
        $this->validate();
        $this->validateDateRange();
        $this->calculateTotal();

        $tenantId = $this->property->tenant_id;

        if (! $tenantId) {
            session()->flash('error', 'Property not linked to a valid business.');
            return null;
        }

        if (! $this->property->is_active) {
            session()->flash('error', 'This activity is currently unavailable. Please choose another.');
            return null;
        }

        if ($this->check_out < $this->check_in) {
            session()->flash('error', 'End date must be on or after the start date.');
            return null;
        }

        $limits = $this->durationLimits;
        if (! $this->property->isDurationWithinLimits($this->totalDays)) {
            $min = $limits['min'];
            $max = $limits['max'];

            if ($max === null) {
                $msg = "Minimum stay for this property is {$min} " . ($min === 1 ? 'day' : 'days') . '.';
            } else {
                $msg = "This property accepts stays between {$min} and {$max} days.";
            }

            session()->flash('error', $msg);
            return null;
        }

        DB::beginTransaction();

        try {
            Property::withoutGlobalScope(TenantScope::class)->whereKey($this->property->id)->lockForUpdate()->first();

            $available = app(BookingAvailabilityService::class)
                ->isSlotAvailable($this->property, $this->check_in, $this->check_out, $this->checkInTime);

            if (! $available) {
                DB::rollBack();
                session()->flash('error', 'That time slot is not available. Please pick a different date or start time.');
                return null;
            }

            $booking = Booking::create([
                'tenant_id'         => $tenantId,
                'user_id'           => Auth::id(),
                'booking_reference' => 'BK-' . strtoupper(Str::random(8)),
                'check_in'          => $this->check_in,
                'check_out'         => $this->check_out,
                'booking_time'      => $this->checkInTime,
                'total_amount'      => $this->totalAmount,
                'status'            => Booking::STATUS_PENDING,
                'booking_type'      => $this->bookingMode,
            ]);

            BookingItem::create([
                'tenant_id'   => $tenantId,
                'booking_id'  => $booking->id,
                'property_id' => $this->property->id,
                'price'       => $this->property->price,
                'quantity'    => $this->totalDays,
                'subtotal'    => (float) $this->property->price * $this->totalDays,
            ]);

            foreach ($this->selectedServiceModels as $serviceId => $svc) {
                $qty = (int) ($this->selectedServices[$serviceId] ?? 0);
                if ($qty < 1) continue;

                BookingService::create([
                    'tenant_id'  => $tenantId,
                    'booking_id' => $booking->id,
                    'service_id' => $serviceId,
                    'quantity'   => $qty,
                    'subtotal'   => (float) $svc->price * $qty,
                ]);
            }

            $isReservation = $this->bookingMode === Booking::TYPE_RESERVATION;
            $chargeAmount  = $isReservation ? $this->reservationFee : $this->totalAmount;

            $session = app(PayMongoService::class)->createCheckoutSession([
                'customer_name'        => $this->customerName,
                'customer_email'       => $this->customerEmail,
                'customer_phone'       => $this->customerPhone,
                'amount'               => $chargeAmount,
                'description'          => ($isReservation ? 'Reservation fee' : 'Full payment') . " for Booking #{$booking->booking_reference}",
                'item_name'            => $isReservation ? 'Reservation Fee' : 'Activity Booking',
                'success_url'          => route('booking.payment.processing', ['bookingId' => $booking->id]),
                'cancel_url'           => route('booking.payment.cancel', ['booking' => $booking->id]),
                'metadata'             => ['booking_id' => (string) $booking->id, 'tenant_id' => (string) $tenantId],
                'payment_method_types' => [$this->paymentMethod],
            ]);

            if (! $session || empty($session['id']) || empty($session['checkout_url'])) {
                DB::rollBack();
                Log::error('PayMongo checkout session creation failed', ['booking_id' => $booking->id ?? null, 'session' => $session]);
                session()->flash('error', 'Unable to initiate payment. Please try again.');
                return null;
            }

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

@php
    $services    = $this->availableServices;
    $hasServices = $services->isNotEmpty();
    $paymentStep = $hasServices ? 4 : 3;
    $account     = Auth::user();
    $hasPhone    = filled($account?->phone);
    $image       = $property->images->first();
    $imageUrl    = $image ? '/storage/' . ltrim($image->image_path, '/') : null;
    $isReserve   = $bookingMode === 'reservation';

    $slots    = $this->timeSlots;
    $amSlots  = array_values(array_filter($slots, fn ($s) => $s['period'] === 'AM'));
    $pmSlots  = array_values(array_filter($slots, fn ($s) => $s['period'] === 'PM'));

    $limits = $this->durationLimits;

    $hours = $this->operatingHoursForProperty;
    $hoursLabel = $hours['is_24hr']
        ? 'Open 24 hours'
        : 'Open ' . \Carbon\Carbon::createFromFormat('H:i', $hours['opening'])->format('g:i A')
          . ' – ' . \Carbon\Carbon::createFromFormat('H:i', $hours['closing'])->format('g:i A');

    $isToday   = $check_in === now()->format('Y-m-d');
    $nowHM     = now()->format('H:i');
    $nowLabel  = now()->format('g:i A');

    // Only surface the "past hours" hint when the current time has
    // actually cut off some of today's opening window.
    $pastHoursExistToday = $isToday
        && ! $hours['is_24hr']
        && $nowHM > $hours['opening'];

    $steps = [1 => ['Your details', 'Confirm contact'], 2 => ['Visit dates', 'Start & end']];
    if ($hasServices) {
        $steps[3] = ['Extras', 'Optional services'];
        $steps[4] = ['Payment', 'Secure checkout'];
    } else {
        $steps[3] = ['Payment', 'Secure checkout'];
    }

    $ring      = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50';
    $label     = 'mb-2 block text-sm font-semibold text-gray-500 dark:text-gray-400';
    $h2        = 'mb-4 font-display text-lg font-semibold tracking-tight text-gray-900 dark:text-white focus:outline-none';
    $chevR     = 'M9 5l7 7-7 7';
    $chevL     = 'M15 19l-7-7 7-7';
    $warn      = 'M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z';
    $pill      = "inline-flex min-h-[44px] items-center rounded-full border border-gray-200 bg-white px-4 text-sm font-semibold text-gray-700 transition hover:border-primary-400 hover:text-primary-600 active:scale-95 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 {$ring}";
    $radioCard = 'flex flex-col items-center justify-center gap-2 rounded-2xl border-2 border-gray-200 bg-white p-4 text-center transition active:scale-[0.98] peer-checked:border-primary-600 peer-checked:bg-primary-50 peer-checked:shadow-md peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500 peer-focus-visible:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:peer-checked:bg-primary-900/30';
    $fmtMoney  = fn ($n) => '₱' . number_format((float) $n, 2);

    $days = fn (int $n): string => $n . ' ' . ($n === 1 ? 'day' : 'days');
    $isSameDay = $check_in !== '' && $check_in === $check_out;
@endphp

<div class="relative z-10 min-h-screen text-gray-900 dark:text-gray-100"
     x-data="{
         step: 1,
         maxStep: {{ $hasServices ? 4 : 3 }},
         errors: {},
         get nextLabel() {
             if (this.step >= this.maxStep) return 'Continue';
             const target = this.step + 1;
             if (target === 2) return 'Continue to dates';
             if (target === 3) return this.maxStep === 4 ? 'Continue to extras' : 'Continue to payment';
             return 'Continue to payment';
         },
         focusHeading() { this.$nextTick(() => this.$refs['stepHeading' + this.step]?.focus()); },
         next() {
             if (this.step === 1) {
                 if (!this.$wire.customerPhone || !this.$wire.customerPhone.trim()) {
                     this.errors.phone = 'Add a phone number to your account before continuing.';
                     return;
                 }
                 delete this.errors.phone;
             }
             if (this.step === 2) {
                 if (!this.$wire.check_in || !this.$wire.check_out) {
                     this.errors.dates = 'Please select both start and end dates.';
                     return;
                 }
                 if (this.$wire.dateRangeValid === false) {
                     this.errors.dates = 'That time slot is not available. Try another start time or shift the dates.';
                     return;
                 }
                 delete this.errors.dates;
             }
             if (this.step < this.maxStep) { this.step++; this.focusHeading(); }
         },
         prev() { if (this.step > 1) { this.step--; this.focusHeading(); } },
         goTo(s) { if (s < this.step && s >= 1) { this.step = s; this.focusHeading(); } }
     }">

    <div class="mx-auto max-w-7xl px-4 pb-32 pt-6 sm:px-6 sm:pt-8 lg:px-8 lg:pb-12">

        <a href="{{ route('business.offerings', $property->tenant->slug) }}" wire:navigate
           class="group mb-6 inline-flex min-h-[44px] items-center gap-1.5 rounded-full text-sm font-medium text-gray-600 transition hover:text-primary-600 active:scale-95 dark:text-gray-400 dark:hover:text-primary-400 {{ $ring }}">
            <svg class="size-4 transition-transform group-hover:-translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m7-7l-7 7 7 7"/></svg>
            Back to {{ $property->tenant->name }}
        </a>

        <h1 class="mb-6 font-display text-3xl font-bold tracking-tight text-gray-900 dark:text-white sm:mb-8 md:text-4xl">Complete your booking</h1>

        <div class="card mb-6 flex items-center gap-4 p-4 sm:mb-8 sm:p-5">
            <div class="size-16 shrink-0 overflow-hidden rounded-xl bg-gray-100 ring-1 ring-gray-200/70 dark:bg-gray-700 dark:ring-gray-700/70 sm:size-20">
                @if($imageUrl)
                    <img src="{{ $imageUrl }}" alt="{{ $property->name }}" width="80" height="80" loading="eager" decoding="async" class="size-full object-cover">
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <h2 class="truncate font-display text-base font-semibold leading-tight text-gray-900 dark:text-white sm:text-lg">{{ $property->name }}</h2>
                <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{{ $property->propertyType->name ?? 'Activity' }} · {{ $property->tenant->name }}</p>
            </div>
            <div class="shrink-0 border-l border-gray-200 pl-3 text-right dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400">From</p>
                <p class="font-display text-lg font-semibold leading-none tabular-nums text-primary-600 dark:text-primary-400 sm:text-xl">₱{{ number_format($property->price, 0) }}</p>
                <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">/ unit</p>
            </div>
        </div>

        <ol class="mb-8 flex items-start sm:mb-10">
            @foreach($steps as $num => [$title, $sub])
                <li class="flex min-w-0 flex-1 flex-col items-center">
                    <button type="button" @click.stop="goTo({{ $num }})" :disabled="{{ $num }} > step"
                            :aria-current="{{ $num }} === step ? 'step' : 'false'"
                            class="group flex min-h-[44px] flex-col items-center rounded-xl transition active:scale-95 {{ $ring }}"
                            :class="{{ $num }} > step ? 'cursor-not-allowed opacity-60' : ({{ $num }} < step ? 'cursor-pointer' : 'cursor-default')">
                        <span class="grid size-10 place-items-center rounded-full text-sm font-bold transition-all duration-300"
                              :class="{{ $num }} < step ? 'bg-primary-600 text-white ring-4 ring-primary-600/15'
                                     : ({{ $num }} === step ? 'bg-primary-600 text-white ring-4 ring-primary-600/25'
                                     : 'border border-gray-300 bg-gray-100 text-gray-400 dark:border-gray-700 dark:bg-gray-800')"
                              aria-hidden="true">
                            <span x-text="{{ $num }} < step ? '✓' : '{{ $num }}'"></span>
                        </span>
                        <span class="mt-2 text-center text-xs font-semibold" :class="{{ $num }} <= step ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400'">{{ $title }}</span>
                        <span class="mt-0.5 hidden text-xs text-gray-400 dark:text-gray-500 sm:block">{{ $sub }}</span>
                    </button>
                </li>
                @if($num < count($steps))
                    <li class="mx-2 mt-5 h-0.5 flex-1 rounded-full transition-colors duration-500" :class="{{ $num }} < step ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-700'" aria-hidden="true"></li>
                @endif
            @endforeach
        </ol>

        @if(session()->has('error'))
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 6000)" x-show="show" x-transition.opacity
                 role="alert" aria-live="polite"
                 class="mb-6 flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 shadow-sm dark:border-rose-400/40 dark:bg-rose-900/30 dark:text-rose-200">
                <svg class="mt-0.5 size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $warn }}"/></svg>
                <span class="flex-1">{{ session('error') }}</span>
                <button type="button" @click.stop="show = false" aria-label="Dismiss error"
                        class="-my-2 -mr-2 grid size-11 shrink-0 place-items-center rounded-full text-rose-500 transition hover:bg-rose-100 active:scale-95 dark:hover:bg-rose-500/10 {{ $ring }}">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[1fr_380px] lg:gap-8">

            <div class="space-y-4">

                <section x-cloak x-show="step === 1" x-transition.opacity.duration.200ms class="space-y-4">
                    <div class="card p-5 sm:p-6">
                        <h2 class="{{ $h2 }}" x-ref="stepHeading1" tabindex="-1">Your details</h2>

                        @if($account)
                            <div class="mb-4 flex items-start gap-3 rounded-2xl border px-4 py-3.5 {{ $hasPhone ? 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900' : 'border-rose-200 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-900/20' }}">
                                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-primary-600 text-sm font-bold text-white">{{ strtoupper(substr($account->name, 0, 1)) }}</span>
                                <div class="min-w-0 flex-1 space-y-0.5">
                                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $account->name }}</p>
                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $account->email }}</p>
                                    <p class="truncate text-xs tabular-nums {{ $hasPhone ? 'text-gray-500 dark:text-gray-400' : 'font-semibold text-rose-700 dark:text-rose-300' }}">
                                        {{ $hasPhone ? $account->phone : 'No phone number on your account.' }}
                                    </p>
                                </div>
                                <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500">From account</span>
                            </div>

                            <p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                @if($hasPhone)
                                    Your contact details come from your account and are sent to PayMongo with the booking. To change them, update your
                                @else
                                    PayMongo requires a contact number for every booking. Add one in your
                                @endif
                                <a href="{{ route('profile') }}" wire:navigate class="font-semibold text-primary-600 hover:underline dark:text-primary-400">profile</a>{{ $hasPhone ? '.' : ' and return here to continue.' }}
                            </p>

                            <p x-cloak x-show="errors.phone" class="mt-3 flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-600 dark:border-rose-500/30 dark:bg-rose-900/20 dark:text-rose-300">
                                <svg class="mt-0.5 size-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $warn }}"/></svg>
                                <span x-text="errors.phone"></span>
                            </p>
                            @error('customerPhone')<p class="mt-3 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
                        @endif
                    </div>

                    <div class="flex justify-end">
                        <button type="button" @click.stop="next()" :disabled="!$wire.customerPhone || !$wire.customerPhone.trim()"
                                class="btn-primary min-h-[44px] disabled:cursor-not-allowed disabled:opacity-50 disabled:active:scale-100">
                            <span x-text="nextLabel"></span>
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $chevR }}"/></svg>
                        </button>
                    </div>
                </section>

                <section x-cloak x-show="step === 2" x-transition.opacity.duration.200ms class="space-y-4">
                    <div class="card p-4 sm:p-6">
                        <h2 class="{{ $h2 }}" x-ref="stepHeading2" tabindex="-1">Visit dates</h2>

                        <div x-data="dateSelector()" data-date-data="{{ $this->dateSelectorDataJson }}" class="space-y-5">

                            <div>
                                <p class="{{ $label }}">Quick pick</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach(['today' => 'Today', 'tomorrow' => 'Tomorrow', 'three-days' => '3 days', 'weekend' => 'This weekend'] as $kind => $text)
                                        <button type="button" @click.stop="quickSelect('{{ $kind }}')" class="{{ $pill }}">{{ $text }}</button>
                                    @endforeach
                                    <button type="button" @click.stop="clearSelection()"
                                            class="ml-auto inline-flex min-h-[44px] items-center gap-1 rounded-full px-4 text-sm font-semibold text-gray-500 transition hover:text-rose-600 active:scale-95 dark:text-gray-400 dark:hover:text-rose-400 {{ $ring }}">
                                        <svg class="size-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        Reset
                                    </button>
                                </div>

                                @if($limits['min'] > 1 || $limits['max'] !== null)
                                    <p class="mt-3 flex items-start gap-2 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-medium text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-300">
                                        <svg class="mt-0.5 size-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span>
                                            @if($limits['max'] === null)
                                                Minimum stay is {{ $days($limits['min']) }}.
                                            @elseif($limits['min'] === $limits['max'])
                                                This property only accepts {{ $days($limits['min']) }} bookings.
                                            @else
                                                Stays between {{ $days($limits['min']) }} and {{ $days($limits['max']) }} only.
                                            @endif
                                        </span>
                                    </p>
                                @endif
                            </div>

                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-[1fr_1fr_auto] sm:gap-3">
                                @foreach([['Start', "checkIn ? formatDate(checkIn) : 'Pick a date'", true], ['End', "checkOut ? formatDate(checkOut) : 'Pick a date'", false]] as [$cap, $expr, $accent])
                                    <div class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-900 sm:px-4 sm:py-3">
                                        <span class="grid size-8 shrink-0 place-items-center rounded-full {{ $accent ? 'bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400' : 'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }}">
                                            <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $cap }}</p>
                                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-white" x-text="{{ $expr }}"></p>
                                        </div>
                                    </div>
                                @endforeach

                                <div x-cloak x-show="hasRange" class="col-span-2 flex items-center gap-2 rounded-2xl border border-primary-200 bg-primary-50 px-3 py-2.5 dark:border-primary-500/30 dark:bg-primary-900/20 sm:col-span-1 sm:px-4 sm:py-3">
                                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-primary-600 text-white">
                                        <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </span>
                                    <div>
                                        <p class="text-xs text-primary-600 dark:text-primary-400">Duration</p>
                                        <p class="text-sm font-bold text-primary-700 dark:text-primary-300" x-text="durationLabel"></p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-700 dark:bg-gray-900">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Start time</p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        <span class="font-semibold">{{ $hoursLabel }}</span>
                                        @if($check_in)
                                            · arrival on <span class="font-semibold">{{ \Carbon\Carbon::parse($check_in)->format('M j') }}</span>
                                        @endif
                                    </p>
                                </div>

                                @if($pastHoursExistToday)
                                    <p class="mt-3 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                                        <svg class="mt-0.5 size-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $warn }}"/></svg>
                                        <span>
                                            It's currently {{ $nowLabel }} — today's start times before
                                            {{ \Carbon\Carbon::createFromFormat('H:i', $nowHM)->format('g:i A') }}
                                            have already passed. Pick a future date to see the full
                                            {{ \Carbon\Carbon::createFromFormat('H:i', $hours['opening'])->format('g:i A') }}–{{ \Carbon\Carbon::createFromFormat('H:i', $hours['closing'])->format('g:i A') }} window.
                                        </span>
                                    </p>
                                @endif

                                @if(empty($this->blockedHoursForSelection))
                                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Select a date to see available times.</p>
                                @else
                                    @foreach(['AM' => $amSlots, 'PM' => $pmSlots] as $period => $group)
                                        @php $hasAnyFree = collect($group)->contains(fn ($s) => ! $s['blocked']); @endphp
                                        @if(! empty($group))
                                            <div class="mt-4">
                                                <div class="mb-2 flex items-center justify-between">
                                                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ $period }}</p>
                                                    @if(! $hasAnyFree)
                                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">No availability</p>
                                                    @endif
                                                </div>
                                                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
                                                    @foreach($group as $slot)
                                                        @php
                                                            $isSelected = $checkInTime === $slot['h24'];
                                                            $isBlocked  = $slot['blocked'];
                                                        @endphp
                                                        <button type="button"
                                                                wire:click="selectTime('{{ $slot['h24'] }}')"
                                                                wire:loading.attr="disabled"
                                                                wire:target="selectTime"
                                                                @disabled($isBlocked)
                                                                aria-label="Start time {{ $slot['display'] }}{{ $isBlocked ? ' (unavailable)' : '' }}"
                                                                aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                                                                class="flex min-h-[44px] items-center justify-center rounded-xl border text-sm font-semibold tabular-nums transition active:scale-95 disabled:active:scale-100 {{ $ring }}
                                                                    {{ $isSelected
                                                                        ? 'border-primary-600 bg-primary-600 text-white shadow-md'
                                                                        : ($isBlocked
                                                                            ? 'cursor-not-allowed border-gray-200 bg-gray-100 text-gray-300 line-through dark:border-gray-700 dark:bg-gray-800 dark:text-gray-600'
                                                                            : 'border-gray-200 bg-white text-gray-700 hover:border-primary-400 hover:text-primary-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-primary-500 dark:hover:text-primary-400') }}">
                                                            {{ $slot['display'] }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                @endif

                                @error('checkInTime')<p class="mt-2 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
                            </div>

                            @foreach(['check_in', 'check_out'] as $field)
                                @error($field)<p class="text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>@enderror
                            @endforeach

                            <div class="flex items-center justify-between">
                                <button type="button" @click.stop="prevMonth()" :disabled="!canGoPrevMonth" aria-label="Previous month"
                                        class="btn-ghost size-11 !p-0 disabled:cursor-not-allowed disabled:opacity-30">
                                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $chevL }}"/></svg>
                                </button>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white" x-text="currentMonthName + ' ' + currentYear"></span>
                                <button type="button" @click.stop="nextMonth()" :disabled="!canGoNextMonth" aria-label="Next month"
                                        class="btn-ghost size-11 !p-0 disabled:cursor-not-allowed disabled:opacity-30">
                                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $chevR }}"/></svg>
                                </button>
                            </div>

                            <div class="grid grid-cols-7 gap-0 sm:gap-1">
                                <template x-for="day in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="day">
                                    <span class="py-1.5 text-center text-xs font-semibold text-gray-400 dark:text-gray-500" x-text="day"></span>
                                </template>
                                <template x-for="blank in firstDayOffset" :key="'blank-'+blank"><span></span></template>
                                <template x-for="day in daysInMonth" :key="day.iso">
                                    <button type="button" @click="selectDate(day.iso)"
                                            :disabled="day.isDisabled || day.isBooked || day.isBeyondStayLimit"
                                            :aria-label="day.isBooked ? 'Unavailable' : (day.isBeyondStayLimit ? 'Beyond maximum stay' : day.iso)"
                                            :title="day.isBooked ? 'Unavailable' : (day.isBeyondStayLimit ? 'Beyond the property maximum stay' : '')"
                                            class="flex min-h-[44px] min-w-0 items-center justify-center rounded-xl text-sm font-medium transition active:scale-90 {{ $ring }}"
                                            :class="{
                                                'cursor-not-allowed bg-rose-50 text-rose-300 line-through dark:bg-rose-900/30 dark:text-rose-500/60': day.isBooked,
                                                'cursor-not-allowed border border-dashed border-gray-300 bg-gray-100 text-gray-400 opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-500': !day.isBooked && day.isBeyondStayLimit,
                                                'bg-primary-600 font-bold text-white shadow-md': !day.isBooked && !day.isBeyondStayLimit && (day.iso === checkIn || day.iso === checkOut),
                                                'bg-primary-100 text-primary-800 dark:bg-primary-900/30 dark:text-primary-200': !day.isBooked && !day.isBeyondStayLimit && isInRange(day.iso),
                                                'cursor-not-allowed text-gray-300 dark:text-gray-600': !day.isBooked && !day.isBeyondStayLimit && day.isDisabled,
                                                'cursor-pointer text-gray-900 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700': !day.isBooked && !day.isBeyondStayLimit && !day.isDisabled && day.iso !== checkIn && day.iso !== checkOut && !isInRange(day.iso)
                                            }">
                                        <span x-text="day.dayNumber"></span>
                                    </button>
                                </template>
                            </div>

                            <p x-cloak x-show="error" class="flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-600 dark:border-rose-500/30 dark:bg-rose-900/20 dark:text-rose-300">
                                <svg class="mt-0.5 size-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $warn }}"/></svg>
                                <span x-text="error"></span>
                            </p>

                            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-gray-500 dark:text-gray-400">
                                <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-sm bg-primary-600"></span>Selected</span>
                                <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-sm border border-primary-300/50 bg-primary-100 dark:bg-primary-900/40"></span>In range</span>
                                @if($limits['max'] !== null)
                                    <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-sm border border-dashed border-gray-300 bg-gray-100 dark:border-gray-700 dark:bg-gray-800"></span>Beyond max stay</span>
                                @endif
                                <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-sm border border-rose-200 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-900/30"></span>Unavailable</span>
                            </div>

                            @if(! empty($this->bookedDateRanges))
                                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/50 sm:p-4">
                                    <p class="mb-2 text-sm font-semibold text-gray-500 dark:text-gray-400">
                                        Already booked · {{ count($this->bookedDateRanges) }} {{ count($this->bookedDateRanges) === 1 ? 'range' : 'ranges' }}
                                    </p>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($this->bookedDateRanges as $range)
                                            <span wire:key="range-{{ md5($range['start'] . '|' . $range['end']) }}"
                                                  class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-medium tabular-nums text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                                <span class="size-1.5 rounded-full bg-rose-400" aria-hidden="true"></span>
                                                @if($range['start'] === $range['end'])
                                                    {{ \Carbon\Carbon::parse($range['start'])->format('M d') }}
                                                @else
                                                    {{ \Carbon\Carbon::parse($range['start'])->format('M d') }} – {{ \Carbon\Carbon::parse($range['end'])->format('M d') }}
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <p x-cloak x-show="errors.dates" x-text="errors.dates" class="text-xs text-rose-600 dark:text-rose-300"></p>

                    <div class="flex justify-between gap-3">
                        <button type="button" @click.stop="prev()" class="btn-secondary min-h-[44px]">
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $chevL }}"/></svg>
                            Back
                        </button>
                        <button type="button" @click.stop="next()" class="btn-primary min-h-[44px]">
                            <span x-text="nextLabel"></span>
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $chevR }}"/></svg>
                        </button>
                    </div>
                </section>

                @if($hasServices)
                    <section x-cloak x-show="step === 3" x-transition.opacity.duration.200ms class="space-y-4">
                        <div class="card p-5 sm:p-6">
                            <h2 class="{{ $h2 }} !mb-1" x-ref="stepHeading3" tabindex="-1">Extra services</h2>
                            <p class="mb-5 text-xs text-gray-500 dark:text-gray-400">Optional add-ons. Tap to add, then adjust the quantity.</p>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                @foreach($services as $service)
                                    @php $qty = (int) ($selectedServices[$service->id] ?? 0); @endphp
                                    <div wire:key="service-{{ $service->id }}"
                                         class="rounded-2xl border transition-colors duration-200 {{ $qty > 0 ? 'border-primary-500 bg-primary-50/50 dark:border-primary-500/40 dark:bg-primary-900/20' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900' }}">

                                        @if($qty === 0)
                                            <button type="button" wire:click.stop="addService({{ $service->id }})" @click.stop
                                                    wire:loading.attr="disabled" wire:target="addService"
                                                    class="flex min-h-[64px] w-full items-center justify-between gap-3 rounded-2xl px-4 py-3 text-left transition active:scale-[0.98] disabled:opacity-60 {{ $ring }}">
                                                <span class="min-w-0 flex-1">
                                                    <span class="block truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $service->name }}</span>
                                                    <span class="mt-0.5 block text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ $fmtMoney($service->price) }}</span>
                                                </span>
                                                <span class="inline-flex h-11 shrink-0 items-center gap-1 rounded-full bg-primary-600 px-4 text-sm font-semibold text-white">
                                                    <svg class="size-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                                    Add
                                                </span>
                                            </button>
                                        @else
                                            <div class="flex min-h-[64px] items-center justify-between gap-3 px-4 py-3">
                                                <div class="min-w-0 flex-1">
                                                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $service->name }}</p>
                                                    <p class="mt-0.5 text-xs tabular-nums text-gray-600 dark:text-gray-400">
                                                        {{ $fmtMoney($service->price) }} × {{ $qty }} = <span class="font-bold text-primary-600 dark:text-primary-400">{{ $fmtMoney($service->price * $qty) }}</span>
                                                    </p>
                                                </div>
                                                <div class="flex shrink-0 items-center rounded-full border border-gray-200 bg-white p-0.5 dark:border-gray-700 dark:bg-gray-800">
                                                    <button type="button" wire:click.stop="decrementService({{ $service->id }})" @click.stop
                                                            wire:loading.attr="disabled" wire:target="decrementService,addService"
                                                            aria-label="Remove one {{ $service->name }}"
                                                            class="grid size-11 place-items-center rounded-full text-gray-600 transition hover:bg-gray-100 active:scale-90 disabled:opacity-60 dark:text-gray-300 dark:hover:bg-gray-700 {{ $ring }}">
                                                        <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/></svg>
                                                    </button>
                                                    <span class="min-w-[28px] text-center text-sm font-bold tabular-nums text-gray-900 dark:text-white">{{ $qty }}</span>
                                                    <button type="button" wire:click.stop="addService({{ $service->id }})" @click.stop
                                                            wire:loading.attr="disabled" wire:target="addService,decrementService"
                                                            aria-label="Add one more {{ $service->name }}"
                                                            class="grid size-11 place-items-center rounded-full bg-primary-600 text-white transition hover:bg-primary-700 active:scale-90 disabled:opacity-60 {{ $ring }}">
                                                        <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                                    </button>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex justify-between gap-3">
                            <button type="button" @click.stop="prev()" class="btn-secondary min-h-[44px]">
                                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $chevL }}"/></svg>
                                Back
                            </button>
                            <button type="button" @click.stop="next()" class="btn-primary min-h-[44px]">
                                <span x-text="nextLabel"></span>
                                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $chevR }}"/></svg>
                            </button>
                        </div>
                    </section>
                @endif

                <section x-cloak x-show="step === {{ $paymentStep }}" x-transition.opacity.duration.200ms class="space-y-4">
                    <div class="card p-5 sm:p-6">
                        <h2 class="{{ $h2 }}" x-ref="stepHeading{{ $paymentStep }}" tabindex="-1">Payment method</h2>

                        <fieldset class="mb-5">
                            <legend class="{{ $label }}">Booking type</legend>
                            <div class="grid grid-cols-2 gap-3">
                                @foreach([
                                    ['full', 'Pay in full', '100% online now', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                                    ['reservation', 'Reserve', '20% now · rest on arrival', 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v10a2 2 0 002 2h14a2 2 0 002-2V7a2 2 0 00-2-2H5z'],
                                ] as [$val, $title, $sub, $icon])
                                    <label class="relative block min-h-[44px] cursor-pointer" wire:key="mode-{{ $val }}">
                                        <input type="radio" wire:model.live="bookingMode" value="{{ $val }}" class="peer sr-only">
                                        <div class="{{ $radioCard }}">
                                            <svg class="size-8 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $title }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $sub }}</p>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <fieldset class="mb-5">
                            <legend class="{{ $label }}">Pay with</legend>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                @foreach([['gcash', 'GCash'], ['paymaya', 'Maya'], ['card', 'Credit / Debit']] as [$val, $text])
                                    <label class="relative block min-h-[44px] cursor-pointer" wire:key="payment-method-{{ $val }}">
                                        <input type="radio" wire:model.live="paymentMethod" value="{{ $val }}" class="peer sr-only">
                                        <div class="{{ $radioCard }}">
                                            @if($val === 'gcash')
                                                <svg class="size-11" viewBox="0 0 32 32" aria-hidden="true"><circle cx="16" cy="16" r="16" fill="#007DFE"/><text x="16" y="22" text-anchor="middle" fill="white" font-size="14" font-weight="900" font-family="system-ui,sans-serif">G</text></svg>
                                            @elseif($val === 'paymaya')
                                                <svg class="size-11" viewBox="0 0 32 32" aria-hidden="true"><circle cx="16" cy="16" r="16" fill="#111827"/><text x="16" y="22" text-anchor="middle" fill="#00C6D7" font-size="14" font-weight="900" font-family="system-ui,sans-serif">M</text></svg>
                                            @else
                                                <span class="grid size-11 place-items-center rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                    <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                                                </span>
                                            @endif
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $text }}</p>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <div class="flex items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                            <svg class="mt-0.5 size-5 shrink-0 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">Secure checkout via PayMongo</p>
                                <p class="mt-0.5 text-xs leading-relaxed text-gray-500 dark:text-gray-400">You'll go straight to the method you picked, with no second selection.</p>
                            </div>
                        </div>

                        <div class="mt-8 flex flex-col-reverse justify-between gap-3 sm:flex-row">
                            <button type="button" @click.stop="prev()" class="btn-secondary min-h-[44px] w-full sm:w-auto">
                                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $chevL }}"/></svg>
                                Back
                            </button>
                            <button type="button" wire:click="submit" @click.stop wire:loading.attr="disabled" wire:target="submit"
                                    class="btn-primary min-h-[44px] w-full px-8 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">
                                <span wire:loading.remove wire:target="submit">Proceed to pay</span>
                                <span wire:loading wire:target="submit">Processing…</span>
                            </button>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="hidden lg:sticky lg:top-24 lg:block" aria-label="Booking summary">
                <div class="card max-h-[calc(100dvh-7rem)] overflow-y-auto !rounded-3xl shadow-lg">
                    <div class="aspect-[4/3] w-full overflow-hidden bg-gray-100 dark:bg-gray-700">
                        @if($imageUrl)
                            <img src="{{ $imageUrl }}" alt="{{ $property->name }}" width="380" height="285" loading="lazy" decoding="async" class="size-full object-cover">
                        @endif
                    </div>

                    <div class="border-b border-gray-200 p-6 dark:border-gray-700">
                        <h3 class="font-display text-xl font-semibold leading-tight tracking-tight text-gray-900 dark:text-white">{{ $property->name }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $property->propertyType->name ?? 'Activity' }} · {{ $property->tenant->name }}</p>
                        <p class="mt-3 flex items-baseline gap-1.5">
                            <span class="font-display text-3xl tabular-nums text-primary-600 dark:text-primary-400">{{ $fmtMoney($property->price) }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">per unit</span>
                        </p>
                    </div>

                    <div class="space-y-3 border-b border-gray-200 p-6 dark:border-gray-700">
                        @if($check_in && $check_out)
                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                <span>Date{{ $isSameDay ? '' : 's' }}</span>
                                <span class="font-medium tabular-nums text-gray-900 dark:text-white">
                                    @if($isSameDay)
                                        {{ \Carbon\Carbon::parse($check_in)->format('M d, Y') }}
                                    @else
                                        {{ \Carbon\Carbon::parse($check_in)->format('M d') }} – {{ \Carbon\Carbon::parse($check_out)->format('M d') }}
                                    @endif
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                <span>Start time</span>
                                <span class="font-medium tabular-nums text-gray-900 dark:text-white">{{ \Carbon\Carbon::createFromFormat('H:i', $checkInTime)->format('g:i A') }}</span>
                            </div>
                        @endif

                        <dl class="space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <dt class="text-gray-600 dark:text-gray-300">{{ $totalDays }} day{{ $totalDays > 1 ? 's' : '' }} × {{ $fmtMoney($property->price) }}</dt>
                                <dd class="font-semibold tabular-nums text-gray-900 dark:text-white">{{ $fmtMoney($property->price * $totalDays) }}</dd>
                            </div>
                            @foreach($selectedServices as $serviceId => $qty)
                                @php $svc = $this->selectedServiceModels->get($serviceId); @endphp
                                @if($svc)
                                    <div class="flex items-center justify-between" wire:key="summary-service-{{ $serviceId }}">
                                        <dt class="max-w-[160px] truncate text-gray-600 dark:text-gray-300">{{ $svc->name }} ×{{ $qty }}</dt>
                                        <dd class="shrink-0 font-semibold tabular-nums text-gray-900 dark:text-white">{{ $fmtMoney($svc->price * $qty) }}</dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>
                    </div>

                    <div class="space-y-2 p-6">
                        @if($isReserve)
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold text-gray-500 dark:text-gray-400">Pay now (20%)</span>
                                <span class="font-display text-2xl font-semibold tabular-nums text-primary-600 dark:text-primary-400">{{ $fmtMoney($reservationFee) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold text-gray-500 dark:text-gray-400">Balance on arrival</span>
                                <span class="font-display text-lg font-semibold tabular-nums text-gray-900 dark:text-white">{{ $fmtMoney($balanceOnArrival) }}</span>
                            </div>
                        @else
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold text-gray-500 dark:text-gray-400">Total due</span>
                                <span class="font-display text-3xl font-semibold tabular-nums text-primary-600 dark:text-primary-400">{{ $fmtMoney($totalAmount) }}</span>
                            </div>
                        @endif
                    </div>

                    <p class="flex items-center justify-center gap-2 px-6 pb-5 text-xs text-gray-400 dark:text-gray-500">
                        <svg class="size-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Secured by PayMongo
                    </p>
                </div>
            </aside>
        </div>
    </div>

    <div class="glass fixed inset-x-0 bottom-0 z-50 border-x-0 border-b-0 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-4px_20px_rgba(0,0,0,0.08)] lg:hidden">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $isReserve ? 'Pay now (20%)' : 'Total due' }}</p>
                <p class="font-display text-xl font-bold leading-tight tabular-nums text-gray-900 dark:text-white">{{ $fmtMoney($isReserve ? $reservationFee : $totalAmount) }}</p>
                @if($check_in && $check_out)
                    <p class="mt-0.5 truncate text-xs tabular-nums text-gray-500 dark:text-gray-400">
                        @if($isSameDay)
                            {{ \Carbon\Carbon::parse($check_in)->format('M d') }}
                        @else
                            {{ \Carbon\Carbon::parse($check_in)->format('M d') }} → {{ \Carbon\Carbon::parse($check_out)->format('M d') }}
                        @endif
                        · {{ $totalDays }} day{{ $totalDays > 1 ? 's' : '' }}
                    </p>
                @endif
            </div>

            <button x-cloak x-show="step < maxStep" type="button" @click.stop="next()"
                    :disabled="step === 1 && (!$wire.customerPhone || !$wire.customerPhone.trim())"
                    class="btn-primary min-h-[44px] shrink-0 disabled:cursor-not-allowed disabled:opacity-50 disabled:active:scale-100">
                Continue
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $chevR }}"/></svg>
            </button>

            <button x-cloak x-show="step === maxStep" type="button" wire:click="submit" @click.stop wire:loading.attr="disabled" wire:target="submit"
                    class="btn-primary min-h-[44px] shrink-0 disabled:cursor-not-allowed disabled:opacity-60">
                <span wire:loading.remove wire:target="submit">Pay now</span>
                <span wire:loading wire:target="submit">Processing…</span>
            </button>
        </div>
    </div>
</div>