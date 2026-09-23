
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use App\Models\Booking;
use App\Models\User;
use App\Models\Property;
use App\Models\Service;
use App\Models\BookingItem;
use App\Models\BookingService;
use App\Models\ServiceAvailability;
use App\Scopes\TenantScope;
use App\Traits\ChecksTenantPermissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

new
#[Layout('tenant.layouts.app')]
#[Title('Edit Booking')]
class extends Component
{
    use ChecksTenantPermissions;

    /** Bound from route. Auto-locked (Eloquent model). */
    public Booking $booking;

    // ── Guest ──
    public string $customerName    = '';
    public string $customerPhone   = '';
    public string $customerEmail   = '';

    /** Guest user id — set via `selectGuest()` or inherited from the booking. */
    public ?int $user_id = null;

    // ── Booking dates ──
    public string $check_in      = '';
    public string $check_out     = '';
    public string $check_in_time = '14:00';

    #[Locked]
    public string $booking_reference = '';

    // ── Status / type ──
    public string $status       = Booking::STATUS_PENDING;
    public string $booking_type = Booking::TYPE_FULL;

    // ── Selection ──
    /**
     * @var array<int, array{quantity: int, booking_item_id: ?int}>
     *   keyed by property_id
     */
    public array $selectedProperties = [];

    /**
     * @var array<int, array{quantity: int, booking_service_id: ?int}>
     *   keyed by service_id
     */
    public array $selectedServices = [];

    // ── Money (server-computed) ──
    #[Locked] public float $totalAmount      = 0;
    #[Locked] public float $finalTotal       = 0;
    #[Locked] public float $reservationFee   = 0;
    #[Locked] public float $balanceOnArrival = 0;

    /** Public — operator-editable. Capped in calculateTotal(). */
    public float $discountAmount = 0;

    // ── Guest search ──
    public string $guestSearch       = '';
    public array  $guestResults      = [];
    public bool   $showGuestDropdown = false;

    // ─────────────────────────────────────────────────────────
    //  Lifecycle
    // ─────────────────────────────────────────────────────────

    public function mount($booking): void
    {
        if (! $booking instanceof Booking) {
            $booking = Booking::withoutGlobalScope(TenantScope::class)
                ->findOrFail((int) $booking);
        }

        abort_unless($booking->tenant_id === Auth::user()->tenant_id, 403, 'Unauthorized.');

        $booking->load([
            'user',
            'items.property',
            'services.service',
        ]);

        $this->booking            = $booking;
        $this->user_id            = $booking->user_id;
        $this->customerName       = $booking->user->name  ?? '';
        $this->customerPhone      = $booking->user->phone ?? '';
        $this->customerEmail      = $booking->user->email ?? '';
        $this->booking_reference  = $booking->booking_reference;
        $this->status             = $booking->status;
        $this->booking_type       = $booking->booking_type ?? Booking::TYPE_FULL;

        $this->check_in      = $booking->check_in  ? Carbon::parse($booking->check_in)->toDateString()  : now()->toDateString();
        $this->check_out     = $booking->check_out ? Carbon::parse($booking->check_out)->toDateString() : now()->addDay()->toDateString();
        $this->check_in_time = $booking->check_in  ? Carbon::parse($booking->check_in)->format('H:i')   : now()->format('H:i');

        foreach ($booking->items as $item) {
            $this->selectedProperties[$item->property_id] = [
                'quantity'        => (int) $item->quantity,
                'booking_item_id' => (int) $item->id,
            ];
        }

        foreach ($booking->services as $service) {
            $this->selectedServices[$service->service_id] = [
                'quantity'           => (int) $service->quantity,
                'booking_service_id' => (int) $service->id,
            ];
        }

        $baseTotal = $this->calculateBaseTotal();
        $this->discountAmount = $booking->total_amount < $baseTotal
            ? round($baseTotal - (float) $booking->total_amount, 2)
            : 0;

        $this->calculateTotal();
    }

    public function hydrate(): void
    {
        abort_unless(Auth::user()?->tenant_id, 403);

        // Rule 17 — re-verify booking ownership on every update request.
        // Livewire actions bypass route middleware, so this is the safety
        // net against a stale snapshot from a prior tenant session.
        abort_unless(
            $this->booking->tenant_id === Auth::user()->tenant_id,
            403,
            'Unauthorized.'
        );
    }

    public function updated(string $field): void
    {
        $trimFields = ['customerName', 'customerPhone', 'customerEmail'];

        if (in_array($field, $trimFields, true)) {
            $this->$field = trim((string) $this->$field);
        }

        if ($field === 'customerPhone') {
            $this->customerPhone = (string) preg_replace('/[^0-9+]/', '', $this->customerPhone);
        }
    }

    public function updatedGuestSearch(): void
    {
        $this->searchGuests();
    }

    // ─────────────────────────────────────────────────────────
    //  Validation
    // ─────────────────────────────────────────────────────────

    protected function rules(): array
    {
        $tenantId = Auth::user()->tenant_id;

        return [
            'customerName'   => ['required', 'string', 'max:255'],
            'customerPhone'  => ['required', 'string', 'max:20', 'regex:/^(09|\+639)\d{9}$/'],
            'customerEmail'  => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->user_id),
            ],
            'check_in'       => ['required', 'date'],
            'check_out'      => ['required', 'date', 'after_or_equal:check_in'],
            'check_in_time'  => ['required', 'date_format:H:i'],
            'status'         => ['required', 'in:pending,reserved,confirmed,checked_in,completed,cancelled'],
            'booking_type'   => ['required', 'in:full,reservation'],
            'discountAmount' => ['nullable', 'numeric', 'min:0'],

            'user_id' => [
                'nullable', 'integer',
                function ($attribute, $value, $fail) use ($tenantId): void {
                    if (! $value) return;
                    $user = User::find($value);
                    if (! $user) {
                        $fail('Selected guest does not exist.');
                        return;
                    }
                    if ($user->tenant_id !== null && $user->tenant_id !== $tenantId) {
                        $fail('Selected guest does not belong to your business.');
                    }
                },
            ],

            'selectedProperties' => [
                'required', 'array', 'min:1',
                function ($attribute, $value, $fail) use ($tenantId): void {
                    $ids = array_map('intval', array_keys((array) $value));
                    if (empty($ids)) {
                        $fail('Please select at least one activity.');
                        return;
                    }
                    $valid = Property::withoutGlobalScope(TenantScope::class)
                        ->where('tenant_id', $tenantId)
                        ->whereIn('id', $ids)
                        ->count();
                    if ($valid !== count($ids)) {
                        $fail('One or more selected properties are invalid.');
                    }
                },
            ],
            'selectedProperties.*.quantity' => ['integer', 'min:1'],

            'selectedServices' => [
                'array',
                function ($attribute, $value, $fail) use ($tenantId): void {
                    $ids = array_map('intval', array_keys((array) $value));
                    if (empty($ids)) return;
                    $valid = Service::withoutGlobalScope(TenantScope::class)
                        ->where('tenant_id', $tenantId)
                        ->whereIn('id', $ids)
                        ->count();
                    if ($valid !== count($ids)) {
                        $fail('One or more selected services are invalid.');
                    }
                },
            ],
            'selectedServices.*.quantity' => ['integer', 'min:1'],
        ];
    }

    // ─────────────────────────────────────────────────────────
    //  Computed
    // ─────────────────────────────────────────────────────────

    /**
     * Rule 24 — clear memoized computed values after mutations. Called at
     * the top of calculateTotal(), which is the post-mutation funnel.
     */
    protected function forgetComputed(): void
    {
        unset(
            $this->numberOfDays,
            $this->selectedPropertyModels,
            $this->selectedServiceModels,
            $this->availableProperties,
            $this->availableServices,
        );
    }

    #[Computed]
    public function numberOfDays(): int
    {
        if (! $this->check_in || ! $this->check_out) {
            return 1;
        }

        $days = Carbon::parse($this->check_in)->diffInDays(Carbon::parse($this->check_out));

        return max(1, (int) $days);
    }

    #[Computed]
    public function selectedPropertyModels()
    {
        if (empty($this->selectedProperties)) {
            return collect();
        }

        return Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereIn('id', array_keys($this->selectedProperties))
            ->with(['images' => fn ($q) => $q->select('id', 'property_id', 'image_path')])
            ->get(['id', 'name', 'price', 'capacity'])
            ->keyBy('id');
    }

    #[Computed]
    public function selectedServiceModels()
    {
        if (empty($this->selectedServices)) {
            return collect();
        }

        return Service::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereIn('id', array_keys($this->selectedServices))
            ->get(['id', 'name', 'price'])
            ->keyBy('id');
    }

    #[Computed]
    public function availableProperties()
    {
        if (! $this->check_in || ! $this->check_out) {
            return collect();
        }

        $checkInDateTime  = $this->check_in  . ' ' . $this->check_in_time . ':00';
        $checkOutDateTime = $this->check_out . ' ' . $this->check_in_time . ':00';
        $bookingId        = $this->booking->id;
        $tenantId         = Auth::user()->tenant_id;

        $conflictingPropertyIds = BookingItem::withoutGlobalScope(TenantScope::class)
            ->whereHas('booking', fn ($q) => $q
                ->withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
                ->where('id', '!=', $bookingId)
                ->where('check_in', '<', $checkOutDateTime)
                ->where('check_out', '>', $checkInDateTime)
            )
            ->pluck('property_id')
            ->unique()
            ->all();

        return Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with(['images' => fn ($q) => $q->select('id', 'property_id', 'image_path')])
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'capacity'])
            ->reject(fn ($property) => in_array($property->id, $conflictingPropertyIds))
            ->values();
    }

    #[Computed]
    public function availableServices()
    {
        if (! $this->check_in || ! $this->check_out) {
            return collect();
        }

        $days  = $this->numberOfDays;
        $dates = [];
        for ($i = 0; $i < $days; $i++) {
            $dates[] = Carbon::parse($this->check_in)->addDays($i)->toDateString();
        }

        $unavailableServiceIds = ServiceAvailability::withoutGlobalScope(TenantScope::class)
            ->whereIn('date', $dates)
            ->where('is_available', false)
            ->pluck('service_id')
            ->unique()
            ->all();

        return Service::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'price'])
            ->reject(fn ($service) => in_array($service->id, $unavailableServiceIds))
            ->values();
    }

    #[Computed]
    public function allowedStatuses(): array
    {
        $current = $this->booking->status;

        if (in_array($current, [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED], true)) {
            return [$current => $this->statusLabel($current)];
        }

        $transitions = match ($current) {
            Booking::STATUS_PENDING    => [Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED],
            Booking::STATUS_RESERVED   => [Booking::STATUS_CONFIRMED, Booking::STATUS_CANCELLED],
            Booking::STATUS_CONFIRMED  => [Booking::STATUS_CHECKED_IN, Booking::STATUS_CANCELLED],
            Booking::STATUS_CHECKED_IN => [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED],
            default                    => [],
        };

        $options = [$current => $this->statusLabel($current)];
        foreach ($transitions as $s) {
            $options[$s] = $this->statusLabel($s);
        }

        return $options;
    }

    #[Computed]
    public function paidAmount(): float
    {
        return (float) $this->booking->payments()
            ->withoutGlobalScope(TenantScope::class)
            ->where('payment_status', 'paid')
            ->sum('amount');
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            Booking::STATUS_PENDING    => 'Pending',
            Booking::STATUS_RESERVED   => 'Reserved',
            Booking::STATUS_CONFIRMED  => 'Confirmed',
            Booking::STATUS_CHECKED_IN => 'Checked In',
            Booking::STATUS_COMPLETED  => 'Completed',
            Booking::STATUS_CANCELLED  => 'Cancelled',
            default                    => ucfirst($status),
        };
    }

    // ─────────────────────────────────────────────────────────
    //  Guest search
    // ─────────────────────────────────────────────────────────

    public function searchGuests(): void
    {
        $this->requirePermission('edit bookings');

        if (strlen($this->guestSearch) < 2) {
            $this->guestResults      = [];
            $this->showGuestDropdown = false;
            return;
        }

        $tenantId = Auth::user()->tenant_id;

        $this->guestResults = User::query()
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                  ->orWhereNull('tenant_id');
            })
            ->where(function ($q) {
                $q->where('name',  'like', '%' . $this->guestSearch . '%')
                  ->orWhere('email', 'like', '%' . $this->guestSearch . '%')
                  ->orWhere('phone', 'like', '%' . $this->guestSearch . '%');
            })
            ->orderBy('name')
            ->limit(6)
            ->get(['id', 'name', 'email', 'phone', 'tenant_id']);

        $this->showGuestDropdown = $this->guestResults->isNotEmpty();
    }

    public function selectGuest(int $userId): void
    {
        $this->requirePermission('edit bookings');

        $tenantId = Auth::user()->tenant_id;

        $user = User::query()
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                  ->orWhereNull('tenant_id');
            })
            ->whereKey($userId)
            ->first();

        if (! $user) {
            return;
        }

        $this->user_id       = $user->id;
        $this->customerName  = $user->name  ?? '';
        $this->customerPhone = $user->phone ?? '';
        $this->customerEmail = $user->email ?? '';
        $this->guestSearch   = $user->name ?? '';

        $this->showGuestDropdown = false;
        $this->guestResults      = [];
    }

    public function closeGuestDropdown(): void
    {
        $this->showGuestDropdown = false;
    }

    // ─────────────────────────────────────────────────────────
    //  Date / total recalculation hooks
    // ─────────────────────────────────────────────────────────

    public function updatedCheckIn(): void      { $this->recalculateAndWarn(); }
    public function updatedCheckOut(): void     { $this->recalculateAndWarn(); }
    public function updatedCheckInTime(): void  { $this->recalculateAndWarn(); }
    public function updatedBookingType(): void  { $this->calculateTotal(); }

    public function updatedDiscountAmount(): void
    {
        $base = $this->calculateBaseTotal();
        $val  = is_numeric($this->discountAmount) ? (float) $this->discountAmount : 0;

        if ($val > $base) {
            $this->discountAmount = round($base, 2);
        }
        if ($val < 0) {
            $this->discountAmount = 0;
        }

        $this->calculateTotal();
    }

    public function recalculateAndWarn(): void
    {
        $available = $this->availableProperties;
        $removed   = false;

        foreach ($this->selectedProperties as $id => $item) {
            if (! $available->contains('id', $id)) {
                unset($this->selectedProperties[$id]);
                $removed = true;
            }
        }

        $availableServiceIds = $this->availableServices->pluck('id')->all();
        foreach ($this->selectedServices as $id => $item) {
            if (! in_array($id, $availableServiceIds, true)) {
                unset($this->selectedServices[$id]);
                $removed = true;
            }
        }

        if ($removed) {
            session()->flash('error', 'One or more selected activities or services were removed — they are not available for the new dates.');
        }

        $this->calculateTotal();
    }

    // ─────────────────────────────────────────────────────────
    //  Selection toggles (server-side pricing only)
    // ─────────────────────────────────────────────────────────

    public function toggleProperty(int $propertyId): void
    {
        $this->requirePermission('edit bookings');

        if (isset($this->selectedProperties[$propertyId])) {
            unset($this->selectedProperties[$propertyId]);
            $this->calculateTotal();
            return;
        }

        $tenantId = Auth::user()->tenant_id;

        $exists = Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereKey($propertyId)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            return;
        }

        if (! $this->availableProperties->contains('id', $propertyId)) {
            session()->flash('error', 'That activity is not available for the selected dates.');
            return;
        }

        $this->selectedProperties[$propertyId] = [
            'quantity'        => 1,
            'booking_item_id' => null,
        ];

        $this->calculateTotal();
    }

    public function toggleService(int $serviceId): void
    {
        $this->requirePermission('edit bookings');

        if (isset($this->selectedServices[$serviceId])) {
            unset($this->selectedServices[$serviceId]);
            $this->calculateTotal();
            return;
        }

        $tenantId = Auth::user()->tenant_id;

        $exists = Service::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereKey($serviceId)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            return;
        }

        $this->selectedServices[$serviceId] = [
            'quantity'           => 1,
            'booking_service_id' => null,
        ];

        $this->calculateTotal();
    }

    // ─────────────────────────────────────────────────────────
    //  Total calculation — all prices come from the DB
    // ─────────────────────────────────────────────────────────

    protected function calculateBaseTotal(): float
    {
        $total = 0.0;
        $days  = $this->numberOfDays;

        foreach ($this->selectedProperties as $id => $item) {
            $property = $this->selectedPropertyModels->get($id);
            if (! $property) continue;
            $qty    = (int) ($item['quantity'] ?? 1);
            $total += (float) $property->price * $qty * $days;
        }

        foreach ($this->selectedServices as $id => $item) {
            $service = $this->selectedServiceModels->get($id);
            if (! $service) continue;
            $qty    = (int) ($item['quantity'] ?? 1);
            $total += (float) $service->price * $qty;
        }

        return round($total, 2);
    }

    public function calculateTotal(): void
    {
        $this->forgetComputed();

        $this->totalAmount = $this->calculateBaseTotal();

        $discount = is_numeric($this->discountAmount) ? (float) $this->discountAmount : 0;
        $discount = max(0, min($discount, $this->totalAmount));

        $this->finalTotal       = round($this->totalAmount - $discount, 2);
        $this->reservationFee   = round($this->finalTotal * 0.20, 2);
        $this->balanceOnArrival = round($this->finalTotal - $this->reservationFee, 2);
    }

    // ─────────────────────────────────────────────────────────
    //  Save
    // ─────────────────────────────────────────────────────────

    public function update()
    {
        // Livewire actions bypass route middleware. Enforce the same
        // permission the `/admin/bookings/{id}/edit` route requires.
        $this->requirePermission('edit bookings');

        $this->calculateTotal();

        $this->validate();

        $allowed = $this->allowedStatuses;
        if (! array_key_exists($this->status, $allowed)) {
            session()->flash('error', "Cannot change status from '{$this->booking->status}' to '{$this->status}'.");
            return null;
        }

        $tenantId         = Auth::user()->tenant_id;
        $checkInDateTime  = $this->check_in . ' ' . $this->check_in_time . ':00';
        $checkOutDateTime = $this->check_out . ' ' . $this->check_in_time . ':00';

        try {
            DB::transaction(function () use ($tenantId, $checkInDateTime, $checkOutDateTime): void {
                $booking = Booking::withoutGlobalScope(TenantScope::class)
                    ->where('tenant_id', $tenantId)
                    ->whereKey($this->booking->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedProperties = Property::withoutGlobalScope(TenantScope::class)
                    ->where('tenant_id', $tenantId)
                    ->whereIn('id', array_keys($this->selectedProperties))
                    ->lockForUpdate()
                    ->get(['id', 'price'])
                    ->keyBy('id');

                foreach (array_keys($this->selectedProperties) as $propertyId) {
                    $conflict = BookingItem::withoutGlobalScope(TenantScope::class)
                        ->where('property_id', $propertyId)
                        ->whereHas('booking', fn ($q) => $q
                            ->withoutGlobalScope(TenantScope::class)
                            ->where('tenant_id', $tenantId)
                            ->where('id', '!=', $booking->id)
                            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
                            ->where('check_in', '<', $checkOutDateTime)
                            ->where('check_out', '>', $checkInDateTime)
                        )
                        ->exists();

                    if ($conflict) {
                        $name = $lockedProperties[$propertyId]->name ?? 'Property';
                        throw new \DomainException("'{$name}' is no longer available for the new dates — another booking took it.");
                    }
                }

                /*
                 * SECURITY: only update the linked user's profile when the
                 * booking's assigned user is the one being edited. Reassigning
                 * a booking to a *different* user must NOT overwrite that
                 * user's name/phone/email.
                 */
                if ($this->user_id && $this->user_id === $this->booking->user_id) {
                    $guest = User::find($this->user_id);
                    if ($guest) {
                        $guest->update([
                            'name'  => $this->customerName,
                            'phone' => $this->customerPhone,
                            'email' => $this->customerEmail,
                        ]);
                    }
                }

                $days = $this->numberOfDays;

                $booking->update([
                    'user_id'      => $this->user_id,
                    'check_in'     => $checkInDateTime,
                    'check_out'    => $checkOutDateTime,
                    'status'       => $this->status,
                    'booking_type' => $this->booking_type,
                    'total_amount' => $this->finalTotal,
                ]);

                // ── Sync items ──
                $existingItemIds = $booking->items()->pluck('id')->all();

                foreach ($this->selectedProperties as $propertyId => $item) {
                    $property = $lockedProperties->get($propertyId);
                    if (! $property) continue;

                    $qty      = (int) ($item['quantity'] ?? 1);
                    $subtotal = (float) $property->price * $qty * $days;

                    $existingId = $item['booking_item_id'] ?? null;

                    if ($existingId) {
                        $affected = BookingItem::withoutGlobalScope(TenantScope::class)
                            ->where('tenant_id', $tenantId)
                            ->where('id', $existingId)
                            ->where('booking_id', $booking->id)
                            ->update([
                                'property_id' => $property->id,
                                'price'       => $property->price,
                                'quantity'    => $qty,
                                'subtotal'    => $subtotal,
                            ]);

                        if ($affected) {
                            $existingItemIds = array_diff($existingItemIds, [$existingId]);
                            continue;
                        }
                    }

                    BookingItem::create([
                        'tenant_id'   => $tenantId,
                        'booking_id'  => $booking->id,
                        'property_id' => $property->id,
                        'price'       => $property->price,
                        'quantity'    => $qty,
                        'subtotal'    => $subtotal,
                    ]);
                }

                if (! empty($existingItemIds)) {
                    BookingItem::withoutGlobalScope(TenantScope::class)
                        ->where('tenant_id', $tenantId)
                        ->whereIn('id', $existingItemIds)
                        ->where('booking_id', $booking->id)
                        ->delete();
                }

                // ── Sync services ──
                $existingServiceIds = $booking->services()->pluck('id')->all();

                $lockedServices = Service::withoutGlobalScope(TenantScope::class)
                    ->where('tenant_id', $tenantId)
                    ->whereIn('id', array_keys($this->selectedServices))
                    ->get(['id', 'price'])
                    ->keyBy('id');

                foreach ($this->selectedServices as $serviceId => $item) {
                    $service = $lockedServices->get($serviceId);
                    if (! $service) continue;

                    $qty      = (int) ($item['quantity'] ?? 1);
                    $subtotal = (float) $service->price * $qty;

                    $existingId = $item['booking_service_id'] ?? null;

                    if ($existingId) {
                        $affected = BookingService::withoutGlobalScope(TenantScope::class)
                            ->where('tenant_id', $tenantId)
                            ->where('id', $existingId)
                            ->where('booking_id', $booking->id)
                            ->update([
                                'service_id' => $service->id,
                                'quantity'   => $qty,
                                'subtotal'   => $subtotal,
                            ]);

                        if ($affected) {
                            $existingServiceIds = array_diff($existingServiceIds, [$existingId]);
                            continue;
                        }
                    }

                    BookingService::create([
                        'tenant_id'  => $tenantId,
                        'booking_id' => $booking->id,
                        'service_id' => $service->id,
                        'quantity'   => $qty,
                        'subtotal'   => $subtotal,
                    ]);
                }

                if (! empty($existingServiceIds)) {
                    BookingService::withoutGlobalScope(TenantScope::class)
                        ->where('tenant_id', $tenantId)
                        ->whereIn('id', $existingServiceIds)
                        ->where('booking_id', $booking->id)
                        ->delete();
                }
            });
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
            return null;
        } catch (\Throwable $e) {
            Log::error('Booking update failed', [
                'tenant_id'  => $tenantId,
                'booking_id' => $this->booking->id,
                'error'      => $e->getMessage(),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
            ]);
            session()->flash('error', 'An error occurred while updating the booking. Please try again.');
            return null;
        }

        session()->flash('message', 'Booking updated successfully.');
        return $this->redirectRoute('tenant.bookings.show', ['booking' => $this->booking->id], navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 pb-24 lg:pb-8 max-w-7xl mx-auto space-y-6">

    
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Bookings</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                Edit Booking
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                Ref <span class="font-mono font-semibold text-gray-700 dark:text-gray-300"><?php echo e($booking->booking_reference); ?></span>
                · Update guest details, dates, and items.
            </p>
        </div>
        <a href="<?php echo e(route('tenant.bookings.show', $booking->id)); ?>" wire:navigate
           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                  transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span>Back to Booking</span>
        </a>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('message')): ?>
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 4000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><?php echo e(session('message')); ?></span>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-7 w-7 rounded-md text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/10
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('error')): ?>
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 5000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span><?php echo e(session('error')); ?></span>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-7 w-7 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 6000)"
             :class="show ? '' : 'hidden'"
             class="flex items-start justify-between gap-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl">
            <div class="flex items-start gap-2.5 min-w-0">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div class="text-xs sm:text-sm text-rose-800 dark:text-rose-300 min-w-0">
                    <p class="font-semibold mb-1">Please fix the following:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $err): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <li <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'err-'.e($loop->index).''; ?>wire:key="err-<?php echo e($loop->index); ?>"><?php echo e($err); ?></li>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </ul>
                </div>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-7 w-7 shrink-0 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <form wire:submit="update" class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6 items-start">

        <div class="space-y-6">

            
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Guest Information</h2>
                </div>

                <div class="relative" wire:click.outside="closeGuestDropdown">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Search existing guest
                    </label>
                    <input type="text" wire:model.live.debounce.300ms="guestSearch"
                           placeholder="Type name, email or phone…"
                           class="input w-full">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showGuestDropdown): ?>
                        <div class="absolute z-20 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl max-h-48 overflow-y-auto">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $guestResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $guest): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <button type="button"
                                        <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'guest-'.e($guest->id).''; ?>wire:key="guest-<?php echo e($guest->id); ?>"
                                        wire:click="selectGuest(<?php echo e($guest->id); ?>)"
                                        class="w-full text-left px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 transition active:scale-[0.99] focus-visible:outline-none focus-visible:bg-gray-100 dark:focus-visible:bg-gray-700">
                                    <p class="text-sm text-gray-900 dark:text-white"><?php echo e($guest->name); ?></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($guest->email ?? $guest->phone); ?></p>
                                </button>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <div class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">No matching guests found.</div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="customerName" class="input w-full">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['customerName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone <span class="text-rose-500">*</span></label>
                        <input type="tel" wire:model="customerPhone" inputmode="numeric" maxlength="13" class="input w-full">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['customerPhone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                        <input type="email" wire:model="customerEmail" class="input w-full">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['customerEmail'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>

            
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Booking Details</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date <span class="text-rose-500">*</span></label>
                        <input type="date" wire:model.live.debounce.300ms="check_in" class="input w-full">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['check_in'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Time <span class="text-rose-500">*</span></label>
                        <input type="time" wire:model.live.debounce.300ms="check_in_time" class="input w-full">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['check_in_time'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Date <span class="text-rose-500">*</span></label>
                        <input type="date" wire:model.live.debounce.300ms="check_out" class="input w-full">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['check_out'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Time</label>
                        <input type="text" value="<?php echo e($check_in_time); ?>" readonly
                               class="input w-full bg-gray-100 dark:bg-gray-900 cursor-not-allowed text-gray-500 dark:text-gray-400">
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">Mirrors start time.</p>
                    </div>
                </div>

                <div class="pt-5 border-t border-gray-100 dark:border-gray-700/60 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status <span class="text-rose-500">*</span></label>
                        <select wire:model="status" class="input w-full">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->allowedStatuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <option value="<?php echo e($value); ?>" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'status-opt-'.e($value).''; ?>wire:key="status-opt-<?php echo e($value); ?>"><?php echo e($label); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['status'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Booking Type</label>
                        <select wire:model.live="booking_type" class="input w-full">
                            <option value="full">Book Now (Full Payment)</option>
                            <option value="reservation">Reserve (20% Fee)</option>
                        </select>
                    </div>
                </div>
            </div>

            
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Select Activities</h2>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->availableProperties->isNotEmpty()): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->availableProperties; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $property): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                $isSelected = isset($selectedProperties[$property->id]);
                                $firstImg   = $property->images->first();
                            ?>
                            <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'prop-'.e($property->id).''; ?>wire:key="prop-<?php echo e($property->id); ?>"
                                 role="button"
                                 tabindex="0"
                                 x-on:keydown.enter.prevent="$wire.toggleProperty(<?php echo e($property->id); ?>)"
                                 x-on:keydown.space.prevent="$wire.toggleProperty(<?php echo e($property->id); ?>)"
                                 wire:click="toggleProperty(<?php echo e($property->id); ?>)"
                                 class="relative rounded-xl border-2 transition-all duration-200 cursor-pointer overflow-hidden
                                        focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                        <?php echo e($isSelected ? 'border-primary-600 ring-2 ring-primary-500/30' : 'border-gray-200 dark:border-gray-700 hover:border-primary-400/50'); ?>">
                                <div class="aspect-[4/3] overflow-hidden">
                                    <img class="w-full h-full object-cover"
                                         src="<?php echo e($firstImg ? asset('storage/' . $firstImg->image_path) : asset('images/placeholder-room.jpg')); ?>"
                                         alt="<?php echo e($property->name); ?>"
                                         loading="lazy"
                                         decoding="async">
                                </div>
                                <div class="p-4 bg-white dark:bg-gray-800">
                                    <h3 class="font-medium text-gray-900 dark:text-white truncate"><?php echo e($property->name); ?></h3>
                                    <p class="mt-1 text-sm text-primary-600 dark:text-primary-400 font-semibold tabular-nums">
                                        ₱<?php echo e(number_format($property->price, 2)); ?> <span class="text-gray-500 font-normal">/ day</span>
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Capacity: <?php echo e($property->capacity); ?> persons</p>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isSelected): ?>
                                    <div class="absolute top-3 right-3 bg-primary-600 text-white rounded-full p-1 shadow-md">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-10 bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
                        <svg class="mx-auto w-8 h-8 text-gray-400 dark:text-gray-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                        <p class="text-sm text-gray-500 dark:text-gray-400">No activities available for the selected dates.</p>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($selectedProperties) > 0): ?>
                    <div class="pt-5 border-t border-gray-100 dark:border-gray-700/60 space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="w-5 h-px bg-primary-600"></span>
                            <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Selected Activities</h3>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $selectedProperties; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $id => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                $prop      = $this->selectedPropertyModels->get($id);
                                $days      = $this->numberOfDays;
                                $roomTotal = ($prop->price ?? 0) * $item['quantity'] * $days;
                            ?>
                            <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'sel-prop-'.e($id).''; ?>wire:key="sel-prop-<?php echo e($id); ?>"
                                 class="flex items-center gap-4 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700">
                                <div class="w-14 h-14 rounded-lg overflow-hidden bg-gray-200 dark:bg-gray-600 shrink-0 shadow-sm">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($prop && $prop->images->isNotEmpty()): ?>
                                        <img src="<?php echo e(asset('storage/' . $prop->images->first()->image_path)); ?>"
                                             class="w-full h-full object-cover"
                                             alt="<?php echo e($prop->name); ?>"
                                             loading="lazy"
                                             decoding="async">
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-gray-900 dark:text-white truncate"><?php echo e($prop->name ?? 'Unknown'); ?></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 tabular-nums">
                                        ₱<?php echo e(number_format($prop->price ?? 0, 2)); ?> / day · <?php echo e($days); ?> day<?php echo e($days > 1 ? 's' : ''); ?>

                                    </p>
                                </div>
                                <p class="text-sm font-bold text-gray-900 dark:text-white tabular-nums">₱<?php echo e(number_format($roomTotal, 2)); ?></p>
                                <button type="button" wire:click="toggleProperty(<?php echo e($id); ?>)"
                                        aria-label="Remove <?php echo e($prop->name ?? 'activity'); ?>"
                                        class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-gray-500 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50
                                               transition-all duration-200 active:scale-95
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Add-On Services <span class="text-gray-400 dark:text-gray-500 font-medium normal-case tracking-normal">(optional)</span>
                    </h2>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->availableServices->isNotEmpty()): ?>
                    <div class="flex flex-wrap gap-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->availableServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php $isServiceSelected = isset($selectedServices[$service->id]); ?>
                            <button type="button"
                                    <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'svc-btn-'.e($service->id).''; ?>wire:key="svc-btn-<?php echo e($service->id); ?>"
                                    wire:click="toggleService(<?php echo e($service->id); ?>)"
                                    class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg border text-xs font-semibold
                                           transition-all duration-200 active:scale-95
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                           <?php echo e($isServiceSelected
                                              ? 'bg-primary-50 dark:bg-primary-500/15 border-primary-300 dark:border-primary-500/40 text-primary-700 dark:text-primary-300'
                                              : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700'); ?>">
                                <?php echo e($service->name); ?> <span class="text-gray-500 dark:text-gray-400 font-normal">(+₱<?php echo e(number_format($service->price, 2)); ?>)</span>
                            </button>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400">No services available for the selected dates.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($selectedServices) > 0): ?>
                    <div class="pt-5 border-t border-gray-100 dark:border-gray-700/60 space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="w-5 h-px bg-primary-600"></span>
                            <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Selected Services</h3>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $selectedServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $id => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php $service = $this->selectedServiceModels->get($id); ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($service): ?>
                                <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'sel-svc-'.e($id).''; ?>wire:key="sel-svc-<?php echo e($id); ?>"
                                     class="flex items-center justify-between gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700">
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900 dark:text-white truncate"><?php echo e($service->name); ?></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Fixed rate per booking</p>
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        <p class="text-sm font-bold text-gray-900 dark:text-white tabular-nums">₱<?php echo e(number_format($service->price, 2)); ?></p>
                                        <button type="button" wire:click="toggleService(<?php echo e($id); ?>)"
                                                aria-label="Remove <?php echo e($service->name); ?>"
                                                class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-gray-500 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50
                                                       transition-all duration-200 active:scale-95
                                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        
        <div class="space-y-4 lg:sticky lg:top-24">
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 space-y-4">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Payment Summary</h2>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Discount (₱)</label>
                    <input type="number" min="0" step="0.01"
                           wire:model.live.debounce.500ms="discountAmount"
                           class="input w-full">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['discountAmount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-300">Base Total</span>
                        <span class="font-semibold text-gray-900 dark:text-white tabular-nums">₱<?php echo e(number_format($totalAmount, 2)); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-300">Discount</span>
                        <span class="font-semibold text-gray-900 dark:text-white tabular-nums">−₱<?php echo e(number_format(min((float) $discountAmount, $totalAmount), 2)); ?></span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                        <span class="text-gray-700 dark:text-gray-200 font-semibold">Final Total</span>
                        <span class="font-bold text-emerald-600 dark:text-emerald-400 tabular-nums">₱<?php echo e(number_format($finalTotal, 2)); ?></span>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-gray-100 dark:border-gray-700/60">
                        <span class="text-gray-600 dark:text-gray-300">Already Paid</span>
                        <span class="font-semibold text-gray-900 dark:text-white tabular-nums">₱<?php echo e(number_format($this->paidAmount, 2)); ?></span>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($booking_type === 'reservation'): ?>
                        <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                            <span class="text-gray-600 dark:text-gray-300">Reservation Fee (20%)</span>
                            <span class="font-semibold text-primary-600 dark:text-primary-400 tabular-nums">₱<?php echo e(number_format($reservationFee, 2)); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-300">Balance on Arrival</span>
                            <span class="font-semibold text-amber-600 dark:text-amber-500 tabular-nums">₱<?php echo e(number_format($balanceOnArrival, 2)); ?></span>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="pt-4 border-t border-gray-200 dark:border-gray-700 space-y-3">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="update"
                            class="w-full inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                   transition-all duration-200 active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="update">Save Changes</span>
                        <span wire:loading wire:target="update" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Saving…
                        </span>
                    </button>
                    <a href="<?php echo e(route('tenant.bookings.show', $booking->id)); ?>" wire:navigate
                       class="w-full inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                              transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <span>Cancel</span>
                    </a>
                </div>
            </div>
        </div>
    </form>

    
    <div class="lg:hidden fixed bottom-0 left-0 right-0 z-50 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 shadow-lg p-4">
        <div class="flex items-center justify-between gap-4 max-w-7xl mx-auto">
            <div class="flex-1 min-w-0">
                <p class="text-xs text-gray-500 dark:text-gray-400">Final Total</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white tabular-nums">
                    ₱<?php echo e(number_format($finalTotal, 2)); ?>

                </p>
            </div>
            <button type="button" wire:click="update"
                    wire:loading.attr="disabled"
                    wire:target="update"
                    class="shrink-0 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                           disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="update">Save</span>
                <span wire:loading wire:target="update" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Saving…
                </span>
            </button>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views\tenant\pages\booking\⚡edit-booking.blade.php ENDPATH**/ ?>