{{-- resources/views/tenant/pages/booking/⚡create-booking.blade.php --}}
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
use App\Models\Payment;
use App\Scopes\TenantScope;
use App\Services\PayMongoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

new
#[Layout('tenant.layouts.app')]
#[Title('Walk-In Booking')]
class extends Component {

    // ── Guest ──
    public string $customerName    = '';
    public string $customerPhone   = '';
    public string $customerEmail   = '';
    public string $customerAddress = '';

    // ── Stay ──
    public string $check_in      = '';
    public string $check_out     = '';
    public string $check_in_time = '';

    #[Locked]
    public string $booking_reference = '';

    /** @var array<int, int> property_id => quantity */
    public array $selectedProperties = [];
    /** @var array<int, int> service_id => quantity */
    public array $selectedServices = [];

    #[Locked]
    public float $totalAmount = 0;

    #[Locked]
    public ?int $calendarPropertyId = null;

    public string $payment_method = 'cash';

    // ── QR Ph state (all server-set) ──
    #[Locked] public ?string $qrPaymentIntentId = null;
    #[Locked] public ?string $qrImage           = null;
    #[Locked] public ?string $qrExpiresAt       = null;
    #[Locked] public ?int    $pendingBookingId  = null;
    #[Locked] public ?int    $createdBookingId  = null;

    public bool    $showQrModal = false;
    public ?string $qrError     = null;

    // ─────────────────────────────────────────────────────────
    //  Lifecycle
    // ─────────────────────────────────────────────────────────

    public function mount(): void
    {
        abort_unless(Auth::user()?->tenant_id, 403);

        $this->check_in      = now()->format('Y-m-d');
        $this->check_out     = now()->addDay()->format('Y-m-d');
        $this->check_in_time = now()->format('H:i');

        $this->generateBookingReference();
    }

    public function updated(string $field): void
    {
        $trimFields = ['customerName', 'customerPhone', 'customerEmail', 'customerAddress'];

        if (in_array($field, $trimFields, true)) {
            $this->$field = trim((string) $this->$field);
        }

        if ($field === 'customerPhone') {
            $this->customerPhone = (string) preg_replace('/[^0-9+]/', '', $this->customerPhone);
        }
    }

    // ─────────────────────────────────────────────────────────
    //  Validation
    // ─────────────────────────────────────────────────────────

    protected function rules(): array
    {
        $tenantId = Auth::user()?->tenant_id;

        return [
            'customerName'    => ['required', 'string', 'max:255'],
            'customerPhone'   => ['required', 'string', 'max:20', 'regex:/^(09|\+639)\d{9}$/'],
            'customerEmail'   => ['nullable', 'email', 'max:255'],
            'customerAddress' => ['nullable', 'string', 'max:255'],
            'check_in'        => ['required', 'date', 'after_or_equal:today'],
            'check_out'       => ['required', 'date', 'after_or_equal:check_in'],
            'check_in_time'   => ['required', 'date_format:H:i'],
            'payment_method'  => ['required', 'in:cash,qr'],

            // Keys of the arrays are the IDs — validate them with a closure
            // because Rule::exists() on `.*` walks the *values* (quantities).
            'selectedProperties' => [
                'required', 'array', 'min:1',
                function ($attribute, $value, $fail) use ($tenantId): void {
                    $ids = array_map('intval', array_keys((array) $value));
                    if (empty($ids)) {
                        $fail('Please select at least one property.');
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
            'selectedProperties.*' => ['integer', 'min:1'],

            'selectedServices' => [
                'array',
                function ($attribute, $value, $fail) use ($tenantId): void {
                    $ids = array_map('intval', array_keys((array) $value));
                    if (empty($ids)) {
                        return;
                    }
                    $valid = Service::withoutGlobalScope(TenantScope::class)
                        ->where('tenant_id', $tenantId)
                        ->whereIn('id', $ids)
                        ->count();
                    if ($valid !== count($ids)) {
                        $fail('One or more selected services are invalid.');
                    }
                },
            ],
            'selectedServices.*' => ['integer', 'min:1'],
        ];
    }

    // ─────────────────────────────────────────────────────────
    //  Booking reference
    // ─────────────────────────────────────────────────────────

    public function generateBookingReference(): void
    {
        $this->booking_reference = 'BK-' . strtoupper(Str::random(8));
    }

    protected function ensureUniqueReference(): void
    {
        $exists = Booking::withoutGlobalScope(TenantScope::class)
            ->where('booking_reference', $this->booking_reference)
            ->exists();

        if ($exists) {
            $this->generateBookingReference();
        }
    }

    // ─────────────────────────────────────────────────────────
    //  Date updates
    // ─────────────────────────────────────────────────────────

    public function updatedCheckIn(): void
    {
        $maxDate = now()->addDays(30)->format('Y-m-d');
        if ($this->check_in > $maxDate) {
            $this->check_in = $maxDate;
        }

        if ($this->check_out && Carbon::parse($this->check_in)->gte(Carbon::parse($this->check_out))) {
            $this->check_out = Carbon::parse($this->check_in)->addDay()->format('Y-m-d');
        }

        $this->calculateTotal();
    }

    public function updatedCheckOut(): void
    {
        $maxDate = now()->addDays(30)->format('Y-m-d');
        if ($this->check_out > $maxDate) {
            $this->check_out = $maxDate;
        }

        if ($this->check_in && Carbon::parse($this->check_out)->lte(Carbon::parse($this->check_in))) {
            $this->check_out = Carbon::parse($this->check_in)->addDay()->format('Y-m-d');
        }

        $this->calculateTotal();
    }

    public function updatedCheckInTime(): void
    {
        $this->calculateTotal();
    }

    // ─────────────────────────────────────────────────────────
    //  Calendar modal
    // ─────────────────────────────────────────────────────────

    public function openCalendar(int $propertyId): void
    {
        $this->calendarPropertyId = $propertyId;
    }

    public function closeCalendar(): void
    {
        $this->calendarPropertyId = null;
    }

    // ─────────────────────────────────────────────────────────
    //  Selection toggles
    // ─────────────────────────────────────────────────────────

    public function toggleProperty(int $propertyId): void
    {
        $tenantId = Auth::user()->tenant_id;

        $property = Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->find($propertyId);

        if (!$property) {
            return;
        }

        if (!isset($this->selectedProperties[$propertyId])) {
            $checkInDateTime  = $this->check_in . ' ' . $this->check_in_time . ':00';
            $checkOutDateTime = $this->check_out . ' ' . $this->check_in_time . ':00';

            if ($this->hasConflict($propertyId, $checkInDateTime, $checkOutDateTime)) {
                session()->flash('error', "Property '{$property->name}' is not available for these dates.");
                return;
            }
        }

        if (isset($this->selectedProperties[$propertyId])) {
            unset($this->selectedProperties[$propertyId]);
        } else {
            $this->selectedProperties[$propertyId] = 1;
        }

        $this->calculateTotal();
    }

    public function toggleService(int $serviceId): void
    {
        $tenantId = Auth::user()->tenant_id;

        $exists = Service::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereKey($serviceId)
            ->exists();

        if (!$exists) {
            return;
        }

        if (isset($this->selectedServices[$serviceId])) {
            unset($this->selectedServices[$serviceId]);
        } else {
            $this->selectedServices[$serviceId] = 1;
        }

        $this->calculateTotal();
    }

    // ─────────────────────────────────────────────────────────
    //  Total calculation
    // ─────────────────────────────────────────────────────────

    public function calculateTotal(): void
    {
        $total = 0.0;
        $days  = $this->numberOfDays;

        foreach ($this->selectedPropertyModels as $property) {
            $quantity = $this->selectedProperties[$property->id] ?? 1;
            $total   += (float) $property->price * $quantity * $days;
        }

        foreach ($this->selectedServiceModels as $service) {
            $quantity = $this->selectedServices[$service->id] ?? 1;
            $total   += (float) $service->price * $quantity;
        }

        $this->totalAmount = $total;
    }

    #[Computed]
    public function numberOfDays(): int
    {
        if ($this->check_in && $this->check_out) {
            $days = Carbon::parse($this->check_in)->diffInDays(Carbon::parse($this->check_out));

            return max(1, (int) $days);
        }

        return 1;
    }

    // ─────────────────────────────────────────────────────────
    //  Computed
    // ─────────────────────────────────────────────────────────

    #[Computed]
    public function selectedPropertyModels()
    {
        if (empty($this->selectedProperties)) {
            return collect();
        }

        return Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereIn('id', array_keys($this->selectedProperties))
            ->get(['id', 'name', 'price'])
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
    public function properties()
    {
        $tenantId         = Auth::user()->tenant_id;
        $checkInDateTime  = $this->check_in . ' ' . $this->check_in_time . ':00';
        $checkOutDateTime = $this->check_out . ' ' . $this->check_in_time . ':00';

        $properties = Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with(['images' => fn ($q) => $q->select('id', 'property_id', 'image_path')])
            ->orderBy('name')
            ->get(['id', 'name', 'price']);

        $overlapping = BookingItem::withoutGlobalScope(TenantScope::class)
            ->whereIn('property_id', $properties->pluck('id'))
            ->whereHas('booking', fn ($q) => $q
                ->withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
                ->where('check_in', '<', $checkOutDateTime)
                ->where('check_out', '>', $checkInDateTime)
            )
            ->with(['booking' => fn ($q) => $q
                ->withoutGlobalScope(TenantScope::class)
                ->select('id', 'check_in', 'check_out', 'tenant_id')
            ])
            ->get(['id', 'booking_id', 'property_id'])
            ->groupBy('property_id');

        return $properties->each(function ($property) use ($overlapping): void {
            $ranges = $overlapping->get($property->id, collect())->map(fn ($item) => [
                'start_date' => $item->booking->check_in->format('Y-m-d'),
                'end_date'   => $item->booking->check_out->format('Y-m-d'),
                'display'    => $item->booking->check_in->format('M d, Y h:i A')
                    . ' → '
                    . $item->booking->check_out->format('M d, Y h:i A'),
            ])->values()->all();

            $dates = [];
            foreach ($ranges as $range) {
                $start = Carbon::parse($range['start_date']);
                $end   = Carbon::parse($range['end_date']);
                while ($start->lte($end)) {
                    $dates[] = $start->format('Y-m-d');
                    $start->addDay();
                }
            }

            $property->booked_ranges = $ranges;
            $property->booked_dates  = array_values(array_unique($dates));
            $property->is_available  = empty($ranges);
        });
    }

    #[Computed]
    public function availableServices()
    {
        return Service::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'price']);
    }

    // ─────────────────────────────────────────────────────────
    //  Submit
    // ─────────────────────────────────────────────────────────

    public function submit()
    {
        $this->validate();

        if (empty($this->selectedProperties)) {
            session()->flash('error', 'Please select at least one property.');
            return null;
        }

        $this->calculateTotal();
        $this->ensureUniqueReference();

        $checkInDateTime  = $this->check_in . ' ' . $this->check_in_time . ':00';
        $checkOutDateTime = $this->check_out . ' ' . $this->check_in_time . ':00';

        $isCash = $this->payment_method === 'cash';

        try {
            $booking = DB::transaction(function () use ($checkInDateTime, $checkOutDateTime, $isCash) {
                $tenantId    = Auth::user()->tenant_id;
                $propertyIds = array_keys($this->selectedProperties);

                Property::withoutGlobalScope(TenantScope::class)
                    ->where('tenant_id', $tenantId)
                    ->whereIn('id', $propertyIds)
                    ->lockForUpdate()
                    ->get();

                foreach ($propertyIds as $propertyId) {
                    if ($this->hasConflict($propertyId, $checkInDateTime, $checkOutDateTime)) {
                        $property = Property::withoutGlobalScope(TenantScope::class)
                            ->where('tenant_id', $tenantId)
                            ->find($propertyId);

                        throw new \DomainException(
                            "The property '{$property?->name}' is no longer available for the selected dates."
                        );
                    }
                }

                $user = $this->resolveGuestUser();

                $booking = Booking::create([
                    'tenant_id'         => $tenantId,
                    'user_id'           => $user->id,
                    'booking_reference' => $this->booking_reference,
                    'check_in'          => $checkInDateTime,
                    'check_out'         => $checkOutDateTime,
                    'total_amount'      => $this->totalAmount,
                    'status'            => $isCash ? Booking::STATUS_CONFIRMED : Booking::STATUS_PENDING,
                    'booking_type'      => 'full',
                ]);

                $days = $this->numberOfDays;

                foreach ($this->selectedPropertyModels as $property) {
                    $quantity = $this->selectedProperties[$property->id] ?? 1;
                    BookingItem::create([
                        'tenant_id'   => $tenantId,
                        'booking_id'  => $booking->id,
                        'property_id' => $property->id,
                        'price'       => $property->price,
                        'quantity'    => $quantity,
                        'subtotal'    => (float) $property->price * $quantity * $days,
                    ]);
                }

                foreach ($this->selectedServiceModels as $service) {
                    $quantity = $this->selectedServices[$service->id] ?? 1;
                    BookingService::create([
                        'tenant_id'  => $tenantId,
                        'booking_id' => $booking->id,
                        'service_id' => $service->id,
                        'quantity'   => $quantity,
                        'subtotal'   => (float) $service->price * $quantity,
                    ]);
                }

                if ($isCash) {
                    Payment::create([
                        'tenant_id'      => $tenantId,
                        'booking_id'     => $booking->id,
                        'amount'         => $this->totalAmount,
                        'payment_method' => 'cash',
                        'payment_type'   => 'full',
                        'payment_status' => 'paid',
                        'paid_at'        => now(),
                    ]);
                }

                return $booking;
            });
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
            return null;
        } catch (\Throwable $e) {
            Log::error('Walk-in booking creation failed', [
                'tenant_id' => Auth::user()->tenant_id,
                'error'     => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);
            session()->flash('error', 'Could not create the booking. Please try again.');
            return null;
        }

        $this->createdBookingId = $booking->id;

        if (!$isCash) {
            return $this->startQrPayment($booking);
        }

        session()->flash('message', 'Booking created and confirmed successfully.');
        return $this->redirectRoute('tenant.bookings.show', ['booking' => $booking->id], navigate: true);
    }

    // ─────────────────────────────────────────────────────────
    //  Guest resolution
    // ─────────────────────────────────────────────────────────

    protected function resolveGuestUser(): User
    {
        $email = trim($this->customerEmail);

        if ($email !== '') {
            $existing = User::where('email', $email)->first();

            if ($existing && $this->isSafeGuestAccount($existing)) {
                return $existing;
            }
        }

        return User::create([
            'name'      => $this->customerName,
            'email'     => 'walkin_' . Str::random(12) . '@walkin.local',
            'password'  => Hash::make(Str::random(32)),
            'tenant_id' => null,
            'is_active' => true,
        ]);
    }

    protected function isSafeGuestAccount(User $user): bool
    {
        if ($user->tenant_id !== null) {
            return false;
        }

        if ($user->hasRole('admin') || $user->hasRole('super-admin')) {
            return false;
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────
    //  QR Ph (PayMongo)
    // ─────────────────────────────────────────────────────────

    protected function startQrPayment(Booking $booking)
    {
        $this->qrError = null;

        /** @var PayMongoService $payMongo */
        $payMongo = app(PayMongoService::class);

        $intent = $payMongo->createQrPhPaymentIntent(
            (float) $booking->total_amount,
            'Walk-in booking ' . $booking->booking_reference,
            [
                'booking_id'        => (string) $booking->id,
                'booking_reference' => $booking->booking_reference,
                'tenant_id'         => (string) $booking->tenant_id,
            ],
        );

        if (!$intent) {
            Log::error('[walkin] QR payment failed: intent creation returned null', [
                'booking_id' => $booking->id,
            ]);
            $this->cancelOrphanedBooking($booking, 'intent_creation_failed');
            $this->qrError = 'PayMongo rejected the payment intent. Check storage/logs/laravel.log for the exact reason.';
            return null;
        }

        $qr = $payMongo->attachQrPhPaymentMethod($intent['id'], $intent['client_key']);

        if (!$qr) {
            Log::error('[walkin] QR payment failed: attach returned null', [
                'booking_id' => $booking->id,
                'intent_id'  => $intent['id'],
            ]);
            $this->cancelOrphanedBooking($booking, 'attach_failed');
            $this->qrError = 'PayMongo rejected the QR attach call. Check storage/logs/laravel.log for the exact reason.';
            return null;
        }

        try {
            Payment::create([
                'tenant_id'        => $booking->tenant_id,
                'booking_id'       => $booking->id,
                'amount'           => $booking->total_amount,
                'payment_method'   => 'qr',
                'payment_type'     => 'full',
                'payment_status'   => 'pending',
                'reference_number' => $qr['payment_intent_id'],
            ]);
        } catch (\Throwable $e) {
            Log::error('[walkin] QR payment row creation failed', [
                'booking_id' => $booking->id,
                'intent_id'  => $qr['payment_intent_id'],
                'error'      => $e->getMessage(),
            ]);
            $this->cancelOrphanedBooking($booking, 'payment_row_failed');
            $this->qrError = 'Could not record the payment locally. Please try again or use cash.';
            return null;
        }

        $this->qrPaymentIntentId = $qr['payment_intent_id'];
        $this->qrImage           = $qr['qr_image'];
        $this->qrExpiresAt       = $qr['expires_at'];
        $this->pendingBookingId  = $booking->id;
        $this->showQrModal       = true;

        return null;
    }

    protected function cancelOrphanedBooking(Booking $booking, string $reason): void
    {
        DB::transaction(function () use ($booking, $reason): void {
            $locked = Booking::withoutGlobalScope(TenantScope::class)
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->first();

            if ($locked && $locked->status === Booking::STATUS_PENDING) {
                $locked->update(['status' => Booking::STATUS_CANCELLED]);

                Log::warning('Orphaned pending booking cancelled', [
                    'booking_id' => $booking->id,
                    'reason'     => $reason,
                ]);
            }
        });
    }

    public function checkQrPayment(): void
    {
        if (!$this->qrPaymentIntentId || !$this->pendingBookingId) {
            return;
        }

        /** @var PayMongoService $payMongo */
        $payMongo = app(PayMongoService::class);

        if (!$payMongo->finalizeQrPayment($this->qrPaymentIntentId)) {
            return;
        }

        $bookingId = $this->pendingBookingId;

        $this->closeQrModal();

        session()->flash('message', 'QR payment received. Booking confirmed successfully.');

        $this->redirectRoute('tenant.bookings.show', ['booking' => $bookingId], navigate: true);
    }

    public function closeQrModal(): void
    {
        $this->showQrModal       = false;
        $this->qrPaymentIntentId = null;
        $this->qrImage           = null;
        $this->qrExpiresAt       = null;
        $this->pendingBookingId  = null;
        $this->qrError           = null;
    }

    public function cancelQrPayment(): void
    {
        $bookingId = $this->pendingBookingId;

        $this->closeQrModal();

        if ($bookingId) {
            session()->flash('message', 'Booking created as pending. You can collect payment later from the booking page.');
            $this->redirectRoute('tenant.bookings.show', ['booking' => $bookingId], navigate: true);
        }
    }

    // ─────────────────────────────────────────────────────────
    //  Conflict detection
    // ─────────────────────────────────────────────────────────

    protected function hasConflict(int $propertyId, string $checkInDateTime, string $checkOutDateTime): bool
    {
        return BookingItem::withoutGlobalScope(TenantScope::class)
            ->where('property_id', $propertyId)
            ->whereHas('booking', fn ($q) => $q
                ->withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', Auth::user()->tenant_id)
                ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED])
                ->where('check_in', '<', $checkOutDateTime)
                ->where('check_out', '>', $checkInDateTime)
            )
            ->exists();
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1600px] mx-auto space-y-6"
     x-data="{ qrPolling: false }"
     x-init="$watch('$wire.showQrModal', v => { qrPolling = v; })"
     x-effect="if (qrPolling) { const t = setInterval(() => $wire.checkQrPayment(), 5000); return () => clearInterval(t); }">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                Bookings
            </p>
            <h1 class="mt-1 text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
                Walk-In Booking
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Create a manual reservation with instant confirmation.
            </p>
        </div>
        <a href="{{ route('tenant.bookings.index') }}" wire:navigate
           class="btn-secondary active:scale-95 transition-transform
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                  inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Bookings
        </a>
    </div>

    @if (session()->has('error'))
        <div class="flex items-start gap-3 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md">
            <svg class="w-5 h-5 text-red-600 dark:text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-red-700 dark:text-red-300 font-medium">{{ session('error') }}</p>
        </div>
    @endif

    @if ($qrError)
        <div class="flex items-start gap-3 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md">
            <svg class="w-5 h-5 text-red-600 dark:text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-red-700 dark:text-red-300 font-medium">{{ $qrError }}</p>
        </div>
    @endif

    {{-- Global validation errors (catches wildcard keys the per-field @error misses) --}}
    @if($errors->any())
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-red-600 dark:text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="text-sm text-red-700 dark:text-red-300">
                    <p class="font-semibold mb-1">Please fix the following:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <form wire:submit="submit" class="relative grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-6 items-start">

        <div class="space-y-6">

            {{-- Guest & Stay Details --}}
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-5">Guest &amp; Stay Details</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name *</label>
                        <input type="text" wire:model="customerName" class="input w-full" placeholder="Guest name">
                        @error('customerName') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone *</label>
                        <input type="tel" inputmode="numeric" pattern="[0-9+]*" maxlength="13"
                               wire:model.live.debounce.500ms="customerPhone"
                               x-on:input="
                                   const cleaned = $event.target.value.replace(/[^0-9+]/g, '');
                                   if (cleaned !== $event.target.value) {
                                       $event.target.value = cleaned;
                                       $event.target.dispatchEvent(new Event('input', { bubbles: true }));
                                   }
                               "
                               class="input w-full" placeholder="09xxxxxxxxx" autocomplete="tel">
                        @error('customerPhone') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">Format: 09xxxxxxxxx or +639xxxxxxxxx</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email (Optional)</label>
                        <input type="email" wire:model="customerEmail" class="input w-full" placeholder="guest@example.com">
                        @error('customerEmail') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address (Optional)</label>
                        <input type="text" wire:model="customerAddress" class="input w-full" placeholder="Complete address">
                        @error('customerAddress') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Check‑in Date</label>
                        <input type="date" wire:model.live.debounce.300ms="check_in"
                               min="{{ now()->format('Y-m-d') }}"
                               max="{{ now()->addDays(30)->format('Y-m-d') }}"
                               class="input w-full">
                    </div>

                    <div wire:ignore>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Check‑in Time
                            <span class="text-[10px] font-normal text-gray-400 dark:text-gray-500 ml-1">
                                (live — set automatically)
                            </span>
                        </label>
                        <div x-data="{
                                time: '{{ $check_in_time }}',
                                init() {
                                    this.tick();
                                    setInterval(() => this.tick(), 10000);
                                },
                                tick() {
                                    const now = new Date();
                                    const hh = String(now.getHours()).padStart(2, '0');
                                    const mm = String(now.getMinutes()).padStart(2, '0');
                                    const next = hh + ':' + mm;
                                    if (this.time !== next) {
                                        this.time = next;
                                        try { this.$wire.set('check_in_time', next, false); } catch (e) {}
                                    }
                                }
                             }"
                             class="w-full flex items-center justify-between px-3 py-2 rounded-xl
                                    bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600">
                            <span x-text="time" class="font-mono tabular-nums text-sm text-gray-900 dark:text-white">--:--</span>
                            <span class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 motion-reduce:animate-none"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                </span>
                                Live
                            </span>
                        </div>
                        @error('check_in_time') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Check‑out Date</label>
                        <input type="date" wire:model.live.debounce.300ms="check_out"
                               min="{{ now()->addDay()->format('Y-m-d') }}"
                               max="{{ now()->addDays(30)->format('Y-m-d') }}"
                               class="input w-full">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Booking Reference</label>
                        <div class="flex gap-2">
                            <input type="text" wire:model="booking_reference" class="input flex-1 bg-gray-100 dark:bg-gray-900 cursor-not-allowed" readonly>
                            <button type="button" wire:click="generateBookingReference"
                                    class="shrink-0 px-4 py-2 rounded-xl bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-sm font-semibold text-primary-600 dark:text-primary-400 hover:bg-gray-50 dark:hover:bg-gray-700 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                Generate
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Property Selection --}}
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-5">Select Property</h2>

                @if($this->properties->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($this->properties as $property)
                            @php
                                $isSelected  = isset($selectedProperties[$property->id]);
                                $isAvailable = $property->is_available;
                                $firstImg    = $property->images->first();
                            @endphp
                            <div wire:key="prop-{{ $property->id }}"
                                 @if($isAvailable)
                                     role="button"
                                     tabindex="0"
                                     x-on:keydown.enter.prevent="$wire.toggleProperty({{ $property->id }})"
                                     x-on:keydown.space.prevent="$wire.toggleProperty({{ $property->id }})"
                                     wire:click="toggleProperty({{ $property->id }})"
                                 @endif
                                 class="relative rounded-xl border-2 transition-all duration-200
                                        focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                        {{ $isSelected ? 'border-primary-600 ring-2 ring-primary-500/30' : '' }}
                                        {{ $isAvailable ? 'cursor-pointer' : 'cursor-not-allowed opacity-70' }}">

                                <div class="aspect-[4/3] overflow-hidden relative rounded-t-xl">
                                    <img class="w-full h-full object-cover"
                                         src="{{ $firstImg ? asset('storage/'. $firstImg->image_path) : asset('images/placeholder-room.jpg') }}"
                                         alt="{{ $property->name }}">
                                    @if(!$isAvailable)
                                        <div class="absolute inset-0 bg-black/50 flex items-center justify-center">
                                            <span class="bg-red-600 text-white px-3 py-1 rounded-full text-xs font-bold uppercase">Booked</span>
                                        </div>
                                    @elseif($isSelected)
                                        <div class="absolute top-2 right-2 bg-primary-600 text-white rounded-full p-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </div>
                                    @endif
                                </div>

                                <div class="p-3 flex justify-between items-center">
                                    <div class="min-w-0">
                                        <h3 class="font-medium text-gray-900 dark:text-white truncate">{{ $property->name }}</h3>
                                        <p class="text-sm text-primary-600 dark:text-primary-400 font-semibold">₱{{ number_format($property->price, 2) }} / day</p>
                                    </div>
                                    @if(!$isAvailable)
                                        <button type="button"
                                                wire:click.stop="openCalendar({{ $property->id }})"
                                                class="text-xs text-primary-600 dark:text-primary-400 hover:underline rounded transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                            View Calendar
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-10 bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">No properties available.</p>
                    </div>
                @endif
            </div>

            {{-- Add-On Services --}}
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-5">Add-On Services</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @forelse($this->availableServices as $service)
                        @php $isSelected = isset($selectedServices[$service->id]); @endphp
                        <button type="button" wire:click="toggleService({{ $service->id }})"
                                wire:key="service-{{ $service->id }}"
                                class="group relative flex items-center justify-between gap-3 rounded-xl border px-4 py-3 text-sm font-medium transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                       {{ $isSelected
                                          ? 'bg-primary-50 border-primary-300 text-primary-700 dark:bg-primary-900/30 dark:border-primary-500/50 dark:text-primary-200 shadow-sm'
                                          : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700/50' }}">
                            <span class="truncate">{{ $service->name }}</span>
                            <span class="text-sm font-semibold shrink-0 {{ $isSelected ? 'text-primary-600 dark:text-primary-300' : 'text-gray-500 dark:text-gray-400' }}">
                                {{ $isSelected ? 'Added ✓' : '+₱' . number_format($service->price, 2) }}
                            </span>
                        </button>
                    @empty
                        <p class="col-span-full text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                            No services available for this business.
                        </p>
                    @endforelse
                </div>

                @if(count($selectedServices) > 0)
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 space-y-2">
                        @foreach($selectedServices as $id => $qty)
                            @php $service = $this->selectedServiceModels->get($id); @endphp
                            @if($service)
                                <div class="flex items-center justify-between gap-3 p-3 bg-gray-50 dark:bg-gray-800/70 rounded-lg"
                                     wire:key="selected-service-{{ $id }}">
                                    <span class="text-sm text-gray-700 dark:text-gray-200">{{ $service->name }}</span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white">₱{{ number_format($service->price * $qty, 2) }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Payment Method --}}
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-6">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-5">Payment Method</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="relative cursor-pointer group">
                        <input type="radio" wire:model.live="payment_method" value="cash" class="sr-only peer">
                        <div class="flex items-center gap-4 p-4 rounded-xl border-2 transition-all duration-200
                                    bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600
                                    peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 dark:peer-checked:border-primary-500/50
                                    hover:border-primary-400 dark:hover:border-primary-500/50
                                    peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500/50">
                            <div class="p-3 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Cash</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Instant confirm</p>
                            </div>
                            <div class="ml-auto opacity-0 peer-checked:opacity-100 transition-opacity">
                                <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                        </div>
                    </label>

                    <label class="relative cursor-pointer group">
                        <input type="radio" wire:model.live="payment_method" value="qr" class="sr-only peer">
                        <div class="flex items-center gap-4 p-4 rounded-xl border-2 transition-all duration-200
                                    bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600
                                    peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 dark:peer-checked:border-primary-500/50
                                    hover:border-primary-400 dark:hover:border-primary-500/50
                                    peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500/50">
                            <div class="p-3 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2m4 0v-1M5 7h6m6 0h2M5 11h6m6 0h2M5 15h6m6 0h2M5 19h6m6 0h2"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">QR Code (PayMongo)</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Scan with any e-wallet or bank</p>
                            </div>
                            <div class="ml-auto opacity-0 peer-checked:opacity-100 transition-opacity">
                                <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                        </div>
                    </label>
                </div>

                @if($payment_method === 'qr')
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        A dynamic QR code will be generated via PayMongo. The booking stays
                        <strong>pending</strong> until the customer scans and pays.
                    </p>
                @endif
            </div>
        </div>

        {{-- Summary --}}
        <div class="space-y-4 lg:sticky lg:top-24">
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5">
                <h3 class="font-bold text-gray-900 dark:text-white mb-3">Selected Items</h3>
                <div class="space-y-2 max-h-64 overflow-y-auto">
                    @forelse($selectedProperties as $id => $qty)
                        @php
                            $prop      = $this->selectedPropertyModels->get($id);
                            $days      = $this->numberOfDays;
                            $roomTotal = ($prop->price ?? 0) * $qty * $days;
                        @endphp
                        <div class="flex justify-between items-center text-sm" wire:key="summary-property-{{ $id }}">
                            <span class="text-gray-700 dark:text-gray-300 truncate">{{ $prop->name ?? 'Property' }} ×{{ $qty }}</span>
                            <span class="font-semibold text-gray-900 dark:text-white">₱{{ number_format($roomTotal, 0) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No property selected.</p>
                    @endforelse

                    @foreach($selectedServices as $id => $qty)
                        @php $service = $this->selectedServiceModels->get($id); @endphp
                        @if($service)
                            <div class="flex justify-between items-center text-sm" wire:key="summary-service-{{ $id }}">
                                <span class="text-gray-700 dark:text-gray-300 truncate">{{ $service->name }}</span>
                                <span class="font-semibold text-gray-900 dark:text-white">₱{{ number_format($service->price * $qty, 0) }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>

                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500 dark:text-gray-400">Grand Total</span>
                        <span class="text-2xl font-black text-primary-600 dark:text-primary-400">₱{{ number_format($totalAmount, 2) }}</span>
                    </div>
                </div>

                <button type="submit" wire:loading.attr="disabled" wire:target="submit"
                        class="mt-4 w-full btn-primary py-3 disabled:opacity-50 disabled:cursor-not-allowed
                               flex justify-center items-center active:scale-95 transition-transform
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <span wire:loading.remove wire:target="submit">
                        {{ $payment_method === 'qr' ? 'Generate QR & Create Booking' : 'Complete Checkout' }}
                    </span>
                    <span wire:loading wire:target="submit" class="flex items-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-white motion-reduce:animate-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Processing...
                    </span>
                </button>
                <a href="{{ route('tenant.bookings.index') }}" wire:navigate class="mt-2 w-full btn-secondary text-center py-2.5">Cancel</a>
            </div>
        </div>
    </form>

    {{-- Calendar modal --}}
    @php
        $calendarProperty = $calendarPropertyId
            ? $this->properties->firstWhere('id', $calendarPropertyId)
            : null;
    @endphp

    @if($calendarProperty)
        <div class="fixed inset-0 z-[150] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
             wire:key="calendar-modal-{{ $calendarProperty->id }}"
             x-on:keydown.escape.window="$wire.closeCalendar()"
             x-data="propertyCalendar(@js($calendarProperty->booked_dates))">

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto"
                 @click.outside="$wire.closeCalendar()">

                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ $calendarProperty->name }} — Availability
                    </h3>
                    <button type="button" wire:click="closeCalendar"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">Booked dates:</p>
                    @if(!empty($calendarProperty->booked_ranges))
                        <div class="flex flex-wrap gap-2">
                            @foreach($calendarProperty->booked_ranges as $range)
                                <span wire:key="cal-range-{{ md5($range['display']) }}"
                                      class="inline-block bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-300 rounded px-3 py-1 text-xs">
                                    {{ $range['display'] }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-gray-500 dark:text-gray-400">No booked dates in this range.</p>
                    @endif
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <button type="button" @click="prevMonth()"
                                class="w-8 h-8 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center justify-center text-gray-600 dark:text-gray-300 active:scale-95 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <span class="text-sm font-semibold text-gray-900 dark:text-white"
                              x-text="currentMonthName + ' ' + currentYear"></span>
                        <button type="button" @click="nextMonth()"
                                class="w-8 h-8 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center justify-center text-gray-600 dark:text-gray-300 active:scale-95 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-7 gap-1 text-center">
                        <template x-for="day in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="day">
                            <span class="text-[10px] font-bold uppercase text-gray-500 dark:text-gray-400 py-1" x-text="day"></span>
                        </template>
                        <template x-for="blank in firstDayOffset" :key="'blank-'+blank"><span></span></template>
                        <template x-for="day in daysInMonth" :key="day.date">
                            <div class="h-9 flex items-center justify-center text-sm font-medium rounded-lg"
                                 :class="{
                                     'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300': day.isBooked,
                                     'bg-gray-100 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300': !day.isBooked
                                 }">
                                <span x-text="day.dayNumber"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <button type="button" wire:click="closeCalendar"
                        class="mt-4 w-full btn-primary py-2 rounded-lg active:scale-95 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    Close
                </button>
            </div>
        </div>
    @endif

    {{-- QR Payment Modal --}}
    @if($showQrModal && $qrImage)
        <div class="fixed inset-0 z-[200] flex items-center justify-center bg-black/70 backdrop-blur-sm p-4"
             x-on:keydown.escape.window="$wire.cancelQrPayment()">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full p-6"
                 @click.outside="$wire.closeQrModal()">

                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Scan to Pay</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Open any e-wallet or bank app to scan
                        </p>
                    </div>
                    <button type="button" wire:click="closeQrModal"
                            class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="bg-white rounded-2xl p-4 flex items-center justify-center border-2 border-gray-200 dark:border-gray-700 mb-4">
                    <img src="{{ $qrImage }}" alt="PayMongo QR Code" class="w-64 h-64 object-contain">
                </div>

                <div class="text-center mb-5">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount due</p>
                    <p class="text-2xl font-black text-primary-600 dark:text-primary-400 mt-1">
                        ₱{{ number_format($totalAmount, 2) }}
                    </p>
                    @if($qrExpiresAt)
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Expires {{ \Carbon\Carbon::parse($qrExpiresAt)->diffForHumans() }}
                        </p>
                    @endif
                </div>

                <div class="flex items-center gap-2 p-3 rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 mb-4">
                    <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0 animate-pulse motion-reduce:animate-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-xs text-blue-800 dark:text-blue-300 font-medium">
                        Waiting for payment confirmation…
                    </p>
                </div>

                <div class="flex gap-2">
                    <button type="button" wire:click="checkQrPayment"
                            wire:loading.attr="disabled"
                            wire:target="checkQrPayment"
                            class="flex-1 btn-secondary py-2.5 text-sm active:scale-95 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-60">
                        <span wire:loading.remove wire:target="checkQrPayment">Check Now</span>
                        <span wire:loading wire:target="checkQrPayment">Checking…</span>
                    </button>
                    <button type="button" wire:click="cancelQrPayment"
                            class="flex-1 btn-secondary py-2.5 text-sm active:scale-95 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        Pay Later
                    </button>
                </div>

                <p class="mt-3 text-[11px] text-center text-gray-400 dark:text-gray-500">
                    The booking will be created as <strong>pending</strong> if you close this dialog.
                </p>
            </div>
        </div>
    @endif

</div>

<script>
    function propertyCalendar(bookedDates) {
        return {
            bookedDates: bookedDates || [],
            currentMonth: new Date().getMonth(),
            currentYear: new Date().getFullYear(),

            get daysInMonth() {
                const year = this.currentYear;
                const month = this.currentMonth;
                const days = [];
                const totalDays = new Date(year, month + 1, 0).getDate();

                for (let day = 1; day <= totalDays; day++) {
                    const dateObj = new Date(year, month, day);
                    const dateStr = dateObj.getFullYear() + '-'
                        + String(dateObj.getMonth() + 1).padStart(2, '0') + '-'
                        + String(dateObj.getDate()).padStart(2, '0');

                    days.push({
                        date: dateStr,
                        dayNumber: day,
                        isBooked: this.bookedDates.includes(dateStr),
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
            }
        };
    }
</script>