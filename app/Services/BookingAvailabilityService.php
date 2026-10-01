<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Property;
use App\Models\TenantSetting;
use App\Scopes\TenantScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BookingAvailabilityService
{
    public const BUFFER_MINUTES   = 60;
    public const DEFAULT_OPENING  = '06:00';
    public const DEFAULT_CLOSING  = '20:00';

    /**
     * Operating hours come from the tenant-level business_info JSON.
     *
     * ── WHY withoutGlobalScope HERE ─────────────────────────────────
     *
     *   TenantSetting uses BelongsToTenant, which registers TenantScope.
     *   Under an authenticated tourist (the booking-create page),
     *   Auth::check() is true and tenant_id is null, so TenantScope
     *   reduces the query to `WHERE 1 = 0` — the service then falls
     *   through to the DEFAULT_OPENING/DEFAULT_CLOSING fallback, and
     *   the tourist sees hours the tenant never set.
     *
     *   The property's tenant_id is a trusted attribute.
     *
     * @return array{opening: string, closing: string, is_24hr: bool}
     */
    public function operatingHours(Property $property): array
    {
        $setting = TenantSetting::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $property->tenant_id)
            ->where('key', 'business_info')
            ->first();

        $hours = is_array($setting?->value) ? ($setting->value['opening_hours'] ?? []) : [];

        if ((bool) ($hours['is_24hr'] ?? false)) {
            return ['opening' => '00:00', 'closing' => '23:59', 'is_24hr' => true];
        }

        return [
            'opening' => $this->normalizeTime($hours['opening'] ?? null) ?? self::DEFAULT_OPENING,
            'closing' => $this->normalizeTime($hours['closing'] ?? null) ?? self::DEFAULT_CLOSING,
            'is_24hr' => false,
        ];
    }

    /**
     * Per-property duration limits, with the effective clamp already
     * applied. `max = null` means no upper bound. Values are in
     * INCLUSIVE calendar days: a same-day booking is 1 day.
     *
     * @return array{min: int, max: ?int}
     */
    public function durationLimits(Property $property): array
    {
        return [
            'min' => $property->effectiveMinStayDays(),
            'max' => $property->effectiveMaxStayDays(),
        ];
    }

    /**
     * Integer hours (0..23) that cannot be picked as the check-in hour
     * for the given selection.
     *
     * @return array<int>
     */
    public function blockedHoursForSelection(Property $property, string $checkIn, string $checkOut): array
    {
        $hours    = $this->operatingHours($property);
        $bookings = $this->fetchBookingsInRange($property, $checkIn, $checkOut);

        $blocked    = [];
        $today      = now()->format('Y-m-d');
        $nowMinutes = now()->hour * 60 + now()->minute;

        for ($h = 0; $h < 24; $h++) {
            $T = sprintf('%02d:00', $h);

            if (! $this->slotAvailableWith($bookings, $checkIn, $checkOut, $T, $hours)) {
                $blocked[] = $h;
                continue;
            }

            if ($checkIn === $today && ($h * 60) < $nowMinutes) {
                $blocked[] = $h;
            }
        }

        return $blocked;
    }

    public function isSlotAvailable(Property $property, string $checkIn, string $checkOut, string $time): bool
    {
        $hours    = $this->operatingHours($property);
        $bookings = $this->fetchBookingsInRange($property, $checkIn, $checkOut);

        if (! $this->slotAvailableWith($bookings, $checkIn, $checkOut, $time, $hours)) {
            return false;
        }

        if ($checkIn === now()->format('Y-m-d')) {
            $nowMinutes = now()->hour * 60 + now()->minute;
            if ($this->toMinutes($time) < $nowMinutes) {
                return false;
            }
        }

        return true;
    }

    /**
     * Per-date map for the calendar grid.
     *
     * ── RULES ───────────────────────────────────────────────────────
     *
     *   FULLY BLOCKED. A date is fully blocked when:
     *     (a) it is occupied by a live booking — any date from
     *         check_in through check_out (inclusive);
     *     (b) no valid check-in hour exists for a 1-day booking that
     *         starts and ends on that date.
     *
     *   FREE. Everything else.
     *
     *   NOTE: with day-use semantics, there is no "partial" checkout
     *   day. Every booked date is fully blocked.
     *
     * @return array<string, array{fully_blocked: bool, has_booking: bool}>
     */
    public function calendarAvailability(Property $property, string $fromDate, string $toDate): array
    {
        $hours    = $this->operatingHours($property);
        $bookings = $this->fetchBookingsInRange($property, $fromDate, $toDate);

        $touched       = [];
        $fullyOccupied = [];

        foreach ($bookings as $b) {
            $start = Carbon::parse($b->check_in)->startOfDay();
            $end   = Carbon::parse($b->check_out)->startOfDay();

            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $touched[$cursor->format('Y-m-d')]       = true;
                $fullyOccupied[$cursor->format('Y-m-d')] = true;
                $cursor->addDay();
            }
        }

        $result = [];
        $cursor = Carbon::createFromFormat('Y-m-d', $fromDate);
        $end    = Carbon::createFromFormat('Y-m-d', $toDate);

        while ($cursor->lte($end)) {
            $date = $cursor->format('Y-m-d');

            if (isset($fullyOccupied[$date])) {
                $result[$date] = [
                    'fully_blocked' => true,
                    'has_booking'   => true,
                ];
                $cursor->addDay();
                continue;
            }

            // Same-day probe: "Can a 1-day booking fit on this date?"
            $hasAny = false;
            for ($h = 0; $h < 24; $h++) {
                $T = sprintf('%02d:00', $h);
                if ($this->slotAvailableWith($bookings, $date, $date, $T, $hours)) {
                    $hasAny = true;
                    break;
                }
            }

            $result[$date] = [
                'fully_blocked' => ! $hasAny,
                'has_booking'   => isset($touched[$date]),
            ];

            $cursor->addDay();
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────
    //  Internals
    // ─────────────────────────────────────────────────────────

    /**
     * @param Collection<int, Booking> $bookings
     */
    protected function slotAvailableWith(
        Collection $bookings,
        string $checkIn,
        string $checkOut,
        string $time,
        array $hours,
    ): bool {
        // checkIn must be <= checkOut. Same-day is legal under day-use.
        if ($checkOut < $checkIn) {
            return false;
        }

        $tMin = $this->toMinutes($time);

        // Start time must sit inside the property's opening window.
        if (! $hours['is_24hr']) {
            if ($tMin < $this->toMinutes($hours['opening'])) return false;
            if ($tMin > $this->toMinutes($hours['closing'])) return false;
        }

        try {
            $newStart = Carbon::createFromFormat('Y-m-d H:i', $checkIn . ' ' . $time);
            // End of the stay is the CLOSING time on the check-out date.
            $newEnd   = Carbon::createFromFormat('Y-m-d H:i', $checkOut . ' ' . $hours['closing']);
        } catch (\Throwable) {
            return false;
        }

        if ($newEnd->lt($newStart)) {
            return false;
        }

        $newOccEnd = $newEnd->copy()->addMinutes(self::BUFFER_MINUTES);

        foreach ($bookings as $b) {
            $bTime  = $this->normalizeTime($b->booking_time) ?? $hours['opening'];
            $eStart = Carbon::parse($b->check_in)->startOfDay()->addMinutes($this->toMinutes($bTime));
            $eEnd   = Carbon::parse($b->check_out)->startOfDay()
                        ->addMinutes($this->toMinutes($hours['closing']))
                        ->addMinutes(self::BUFFER_MINUTES);

            if ($newOccEnd->lte($eStart) || $eEnd->lte($newStart)) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * @return Collection<int, Booking>
     */
    protected function fetchBookingsInRange(Property $property, string $checkIn, string $checkOut): Collection
    {
        $from = Carbon::createFromFormat('Y-m-d', $checkIn)->subDays(2)->format('Y-m-d');
        $to   = Carbon::createFromFormat('Y-m-d', $checkOut)->addDays(2)->format('Y-m-d');

        return BookingItem::withoutGlobalScope(TenantScope::class)
            ->where('property_id', $property->id)
            ->whereHas('booking', function ($q) use ($from, $to) {
                $q->withoutGlobalScope(TenantScope::class)
                  ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
                  ->where('check_in', '<=', $to)
                  ->where('check_out', '>=', $from);
            })
            ->with(['booking' => function ($q) {
                $q->withoutGlobalScope(TenantScope::class)
                  ->select('id', 'check_in', 'check_out', 'booking_time', 'status');
            }])
            ->get()
            ->pluck('booking')
            ->filter()
            ->values();
    }

    protected function toMinutes(?string $time): int
    {
        if (! is_string($time) || $time === '') {
            return 0;
        }
        [$h, $m] = array_pad(explode(':', $time, 2), 2, '0');
        return ((int) $h) * 60 + ((int) $m);
    }

    protected function normalizeTime(?string $time): ?string
    {
        if (! is_string($time) || $time === '') {
            return null;
        }
        if (! preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
            return null;
        }
        $h  = (int) $m[1];
        $mm = (int) $m[2];
        if ($h < 0 || $h > 23 || $mm < 0 || $mm > 59) {
            return null;
        }
        return sprintf('%02d:%02d', $h, $mm);
    }
}