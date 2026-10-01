{{-- resources/views/tenant/pages/booking/⚡view-booking.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\Property;
use App\Scopes\TenantScope;
use App\Services\PayMongoService;
use App\Services\BookingRefundService;
use App\Traits\ChecksTenantPermissions;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

new
#[Layout('tenant.layouts.app')]
#[Title('Bookings')]
class extends Component {
    use WithPagination;
    use ChecksTenantPermissions;

    #[Url] public string $search = '';
    #[Url] public string $statusFilter = '';
    #[Url] public ?string $fromDate = null;
    #[Url] public ?string $toDate = null;
    #[Url] public ?int $userFilter = null;
    #[Url] public string $sortBy = 'newest';
    #[Url] public string $tab = 'active';

    public ?int $viewingBookingId = null;

    // ── Payment collection state ──
    public ?int $payingBookingId = null;
    public bool $showPaymentModal = false;
    public string $paymentMethod = 'cash';

    public bool $showQrModal = false;
    public ?string $qrImage = null;
    public ?string $qrPaymentIntentId = null;
    public ?string $qrExpiresAt = null;
    public ?string $qrError = null;
    public float $qrAmount = 0.0;

    // ── Admin cancellation state ──
    public ?int $cancellingBookingId = null;
    public bool $showCancelModal = false;
    public string $adminCancelReason = '';
    public ?array $adminCancelPreview = null;

    public function mount(): void
    {
        $this->processMaintenanceTasks();
    }

    public function hydrate(): void
    {
        abort_unless(Auth::user()?->tenant_id, 403);
        $this->processMaintenanceTasks();
    }

    // ─────────────────────────────────────────────────────────
    //  Details modal
    // ─────────────────────────────────────────────────────────

    public function openDetails(int $id): void
    {
        $exists = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereKey($id)
            ->exists();

        if ($exists) {
            $this->viewingBookingId = $id;
        }
    }

    public function closeDetails(): void
    {
        $this->viewingBookingId = null;
    }

    #[Computed]
    public function viewingBooking(): ?Booking
    {
        if (! $this->viewingBookingId) {
            return null;
        }

        return Booking::withoutGlobalScope(TenantScope::class)
            ->with([
                'user:id,name,email,phone',
                'items'                 => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                'items.property'        => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->select('id', 'name', 'tenant_id'),
                'services'              => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                'services.service'      => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->select('id', 'name'),
                'payments'              => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            ])
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereKey($this->viewingBookingId)
            ->first();
    }

    // ─────────────────────────────────────────────────────────
    //  Payment collection
    // ─────────────────────────────────────────────────────────

    public function openPaymentModal(int $id): void
    {
        $this->requirePermission('manage payments');

        $booking = Booking::withoutGlobalScope(TenantScope::class)
            ->with(['payments' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereKey($id)
            ->first();

        if (! $booking || ! $this->canCollectPayment($booking)) {
            return;
        }

        $this->payingBookingId  = $id;
        $this->paymentMethod    = 'cash';
        $this->showPaymentModal = true;
        $this->qrError          = null;
    }

    public function closePaymentModal(): void
    {
        $this->payingBookingId  = null;
        $this->showPaymentModal = false;
        $this->qrError          = null;
    }

    public function submitCashPayment(): void
    {
        $this->requirePermission('manage payments');

        $booking = $this->payingBooking();
        if (! $booking) {
            $this->closePaymentModal();
            return;
        }

        $amount = $this->amountDueFor($booking);
        if ($amount <= 0) {
            session()->flash('error', 'Nothing left to collect on this booking.');
            $this->closePaymentModal();
            return;
        }

        DB::transaction(function () use ($booking, $amount): void {
            Payment::create([
                'tenant_id'      => $booking->tenant_id,
                'booking_id'     => $booking->id,
                'amount'         => $amount,
                'payment_method' => 'cash',
                'payment_type'   => $this->paymentTypeFor($booking),
                'payment_status' => 'paid',
                'paid_at'        => now(),
            ]);

            $this->applyPaymentTransition($booking);
        });

        unset($this->bookings);
        unset($this->stats);

        $this->closePaymentModal();
        session()->flash('message', 'Cash payment of ₱' . number_format($amount, 2) . ' recorded.');
    }

    public function submitQrPayment(): void
    {
        $this->requirePermission('manage payments');

        $booking = $this->payingBooking();
        if (! $booking) {
            $this->closePaymentModal();
            return;
        }

        $amount = $this->amountDueFor($booking);
        if ($amount <= 0) {
            session()->flash('error', 'Nothing left to collect on this booking.');
            $this->closePaymentModal();
            return;
        }

        $payMongo = app(PayMongoService::class);

        $intent = $payMongo->createQrPhPaymentIntent(
            $amount,
            'Booking ' . $booking->booking_reference,
            [
                'booking_id' => (string) $booking->id,
                'tenant_id'  => (string) $booking->tenant_id,
            ],
        );

        if (! $intent) {
            Log::error('[view-booking] QR intent creation returned null', ['booking_id' => $booking->id]);
            $this->qrError = 'PayMongo rejected the payment intent. Check storage/logs/laravel.log.';
            return;
        }

        $qr = $payMongo->attachQrPhPaymentMethod($intent['id'], $intent['client_key']);

        if (! $qr) {
            Log::error('[view-booking] QR attach returned null', [
                'booking_id' => $booking->id,
                'intent_id'  => $intent['id'],
            ]);
            $this->qrError = 'PayMongo rejected the QR attach call. Check storage/logs/laravel.log.';
            return;
        }

        try {
            Payment::create([
                'tenant_id'        => $booking->tenant_id,
                'booking_id'       => $booking->id,
                'amount'           => $amount,
                'payment_method'   => 'qr',
                'payment_type'     => $this->paymentTypeFor($booking),
                'payment_status'   => 'pending',
                'reference_number' => $qr['payment_intent_id'],
            ]);
        } catch (\Throwable $e) {
            Log::error('[view-booking] QR payment row creation failed', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
            $this->qrError = 'Could not record the payment locally. Try again.';
            return;
        }

        $this->qrPaymentIntentId = $qr['payment_intent_id'];
        $this->qrImage           = $qr['qr_image'];
        $this->qrExpiresAt       = $qr['expires_at'];
        $this->qrAmount          = $amount;
        $this->showQrModal       = true;

        $this->showPaymentModal = false;
        $this->payingBookingId  = null;
        $this->qrError          = null;
    }

    public function checkQrPayment(): void
    {
        $this->requirePermission('manage payments');

        if (! $this->qrPaymentIntentId) {
            return;
        }

        $payMongo = app(PayMongoService::class);

        if (! $payMongo->finalizeQrPayment($this->qrPaymentIntentId)) {
            return;
        }

        $this->closeQrModal();

        unset($this->bookings);
        unset($this->stats);

        session()->flash('message', 'QR payment received and recorded.');
    }

    public function cancelQrPayment(): void
    {
        $this->closeQrModal();
    }

    protected function closeQrModal(): void
    {
        $this->showQrModal       = false;
        $this->qrImage           = null;
        $this->qrPaymentIntentId = null;
        $this->qrExpiresAt       = null;
        $this->qrAmount          = 0.0;
        $this->qrError           = null;
    }

    #[Computed]
    public function payingBooking(): ?Booking
    {
        if (! $this->payingBookingId) {
            return null;
        }

        return Booking::withoutGlobalScope(TenantScope::class)
            ->with(['payments' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereKey($this->payingBookingId)
            ->first();
    }

    public function amountDueFor(Booking $booking): float
    {
        $paid  = $this->paidAmount($booking);
        $total = (float) $booking->total_amount;

        if (
            $booking->booking_type === Booking::TYPE_RESERVATION
            && $booking->status === Booking::STATUS_PENDING
        ) {
            $fee = round($total * 0.20, 2);
            return max(0.0, $fee - $paid);
        }

        return max(0.0, $total - $paid);
    }

    public function canCollectPayment(Booking $booking): bool
    {
        if (in_array($booking->status, [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED], true)) {
            return false;
        }

        if ($booking->status === Booking::STATUS_CHECKED_IN) {
            return false;
        }

        return $this->amountDueFor($booking) > 0;
    }

    protected function paymentTypeFor(Booking $booking): string
    {
        if (
            $booking->booking_type === Booking::TYPE_RESERVATION
            && $booking->status === Booking::STATUS_PENDING
        ) {
            return Payment::TYPE_RESERVATION;
        }

        return Payment::TYPE_FULL;
    }

    protected function applyPaymentTransition(Booking $booking): void
    {
        $total = (float) $booking->total_amount;

        $paid = (float) Payment::withoutGlobalScope(TenantScope::class)
            ->where('booking_id', $booking->id)
            ->where('payment_status', 'paid')
            ->sum('amount');

        if ($paid >= $total) {
            $booking->update(['status' => Booking::STATUS_CONFIRMED]);
            return;
        }

        if (
            $booking->booking_type === Booking::TYPE_RESERVATION
            && $booking->status === Booking::STATUS_PENDING
        ) {
            $fee = round($total * 0.20, 2);
            if ($paid >= $fee) {
                $booking->update(['status' => Booking::STATUS_RESERVED]);
            }
        }
    }

    // ─────────────────────────────────────────────────────────
    //  Maintenance — auto-complete + auto-cancel + auto-confirm
    // ─────────────────────────────────────────────────────────

    protected function processMaintenanceTasks(): void
    {
        try {
            $tenantId = Auth::user()->tenant_id;
            $cacheKey = "booking_maint:{$tenantId}";

            if (! Cache::add($cacheKey, true, now()->addMinute())) {
                return;
            }

            DB::transaction(function () use ($tenantId): void {
                $this->autoCompletePastBookings($tenantId);
                $this->cancelOverduePendingBookings($tenantId);
                $this->confirmFullyPaidBookings($tenantId);
            });
        } catch (\Throwable $e) {
            Log::warning('Booking maintenance task failed', [
                'tenant_id' => Auth::user()?->tenant_id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    private function autoCompletePastBookings(int $tenantId): void
    {
        $candidates = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_RESERVED,
                Booking::STATUS_CHECKED_IN,
            ])
            ->get();

        foreach ($candidates as $booking) {
            if (! $booking->check_out) {
                continue;
            }

            // Booking completes only after the entire check_out day has
            // passed. `booking_time` is the START of the stay, not its
            // end — for a same-day booking (check_in == check_out) it is
            // identical to the check-in clock, so using it here flipped
            // the booking to COMPLETED the moment the clock ticked past
            // the user's own start time.
            $end = $booking->check_out->copy()->endOfDay();

            if ($end->isPast()) {
                $booking->update(['status' => Booking::STATUS_COMPLETED]);
            }
        }
    }

    private function cancelOverduePendingBookings(int $tenantId): void
    {
        $deadline = now()->subMinutes(Booking::PAYMENT_DEADLINE_MINUTES);

        $overdue = Booking::withoutGlobalScope(TenantScope::class)
            ->with([
                'items'    => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
                'payments' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            ])
            ->where('tenant_id', $tenantId)
            ->where('status', Booking::STATUS_PENDING)
            ->where('created_at', '<=', $deadline)
            ->lockForUpdate()
            ->get()
            ->filter(function (Booking $b): bool {
                $total = (float) $b->total_amount;
                $paid  = (float) $b->payments->where('payment_status', 'paid')->sum('amount');

                if ($b->booking_type === Booking::TYPE_RESERVATION) {
                    $fee = round($total * 0.20, 2);
                    return $paid < $fee;
                }

                return $paid < $total;
            });

        if ($overdue->isEmpty()) {
            return;
        }

        $propertyIds = $overdue
            ->flatMap->items
            ->pluck('property_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (! empty($propertyIds)) {
            Property::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $propertyIds)
                ->update(['status' => 'available']);
        }

        Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $overdue->pluck('id'))
            ->update(['status' => Booking::STATUS_CANCELLED]);
    }

    private function confirmFullyPaidBookings(int $tenantId): void
    {
        $pending = Booking::withoutGlobalScope(TenantScope::class)
            ->with(['payments' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_RESERVED])
            ->get();

        $confirmIds = $pending
            ->filter(fn (Booking $b): bool =>
                (float) $b->payments->where('payment_status', 'paid')->sum('amount')
                >= (float) $b->total_amount
            )
            ->pluck('id')
            ->all();

        if (! empty($confirmIds)) {
            Booking::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $confirmIds)
                ->update(['status' => Booking::STATUS_CONFIRMED]);
        }
    }

    // ─────────────────────────────────────────────────────────
    //  Filter / URL hooks
    // ─────────────────────────────────────────────────────────

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingFromDate(): void     { $this->resetPage(); }
    public function updatingToDate(): void       { $this->resetPage(); }
    public function updatingUserFilter(): void   { $this->resetPage(); }
    public function updatingSortBy(): void       { $this->resetPage(); }

    public function updatingTab(): void
    {
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['active', 'archive'], true)) {
            return;
        }
        if ($this->tab === $tab) {
            return;
        }
        $this->tab = $tab;
        $this->statusFilter = '';
        $this->resetPage();
    }

    // ─────────────────────────────────────────────────────────
    //  Admin cancellation — always 100% refund
    // ─────────────────────────────────────────────────────────

    public function openCancelModal(int $id): void
    {
        $this->requirePermission('edit bookings');

        $booking = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereKey($id)
            ->first();

        if (! $booking || ! $booking->canBeCancelled()) {
            session()->flash('error', 'This booking cannot be cancelled.');
            return;
        }

        $this->viewingBookingId    = null;
        $this->cancellingBookingId = $id;
        $this->adminCancelReason   = '';
        $this->adminCancelPreview  = app(BookingRefundService::class)
            ->previewRefund($booking, Booking::CANCELLED_BY_ADMIN);
        $this->showCancelModal     = true;
    }

    public function closeCancelModal(): void
    {
        $this->showCancelModal     = false;
        $this->cancellingBookingId = null;
        $this->adminCancelReason   = '';
        $this->adminCancelPreview  = null;
    }

    public function confirmCancellation(): void
    {
        $this->requirePermission('edit bookings');

        if (! $this->cancellingBookingId) {
            $this->closeCancelModal();
            return;
        }

        $booking = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereKey($this->cancellingBookingId)
            ->first();

        if (! $booking) {
            session()->flash('error', 'Booking not found.');
            $this->closeCancelModal();
            return;
        }

        try {
            $cancelled = app(BookingRefundService::class)->cancel(
                $booking,
                Booking::CANCELLED_BY_ADMIN,
                $this->adminCancelReason !== '' ? $this->adminCancelReason : null,
            );

            unset($this->bookings);
            unset($this->stats);

            $refund = (float) $cancelled->refund_amount;

            if ($refund > 0) {
                session()->flash(
                    'message',
                    "Booking #{$cancelled->booking_reference} cancelled. Full refund of ₱"
                    . number_format($refund, 2) . " will be processed."
                );
            } else {
                session()->flash(
                    'message',
                    "Booking #{$cancelled->booking_reference} cancelled. No payment was collected — no refund due."
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Admin cancellation failed', [
                'booking_id' => $this->cancellingBookingId,
                'user_id'    => Auth::id(),
                'error'      => $e->getMessage(),
            ]);
            session()->flash('error', 'Could not cancel this booking. Please try again.');
        }

        $this->closeCancelModal();
    }

    #[Computed]
    public function cancellingBooking(): ?Booking
    {
        if (! $this->cancellingBookingId) {
            return null;
        }

        return Booking::withoutGlobalScope(TenantScope::class)
            ->with([
                'user:id,name,email',
                'items.property' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->select('id', 'name'),
            ])
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereKey($this->cancellingBookingId)
            ->first();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'fromDate', 'toDate', 'userFilter', 'sortBy']);
        $this->resetPage();
    }

    // ─────────────────────────────────────────────────────────
    //  Display helpers — shared by card grid + modals
    // ─────────────────────────────────────────────────────────

    public function statusConfigFor(string $status): array
    {
        return match ($status) {
            Booking::STATUS_PENDING    => ['bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/40', 'Pending'],
            Booking::STATUS_RESERVED   => ['bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-500/40', 'Reserved'],
            Booking::STATUS_CONFIRMED  => ['bg-indigo-100 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-500/40', 'Confirmed'],
            Booking::STATUS_CHECKED_IN => ['bg-purple-100 dark:bg-purple-500/15 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-500/40', 'Checked In'],
            Booking::STATUS_COMPLETED  => ['bg-slate-100 dark:bg-slate-500/15 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-500/40', 'Completed'],
            Booking::STATUS_CANCELLED  => ['bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-500/40', 'Cancelled'],
            default                    => ['bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-600', ucfirst($status)],
        };
    }

    public function formatTime(?string $time): string
    {
        if (! $time || ! preg_match('/^\d{2}:\d{2}$/', $time)) {
            return '';
        }

        try {
            return Carbon::createFromFormat('H:i', $time)->format('g:i A');
        } catch (\Throwable) {
            return $time;
        }
    }

    public function formatStayLine(Booking $booking): string
    {
        $start = $booking->check_in?->format('M j') ?? '—';
        $end   = $booking->check_out?->format('M j') ?? '—';
        $time  = $this->formatTime($booking->booking_time);

        if ($time !== '') {
            return "{$start}, {$time} — {$end}, {$time}";
        }

        return "{$start} — {$end}";
    }

    public function paidAmount(Booking $booking): float
    {
        return (float) $booking->payments
            ->where('payment_status', 'paid')
            ->sum('amount');
    }

    public function balanceAmount(Booking $booking): float
    {
        return max(0.0, (float) $booking->total_amount - $this->paidAmount($booking));
    }

    public function isOverdueBooking(Booking $booking): bool
    {
        if ($booking->status !== Booking::STATUS_PENDING) {
            return false;
        }

        if ($this->amountDueFor($booking) <= 0) {
            return false;
        }

        if (! $booking->created_at) {
            return false;
        }

        return $booking->created_at
            ->copy()
            ->addMinutes(Booking::PAYMENT_DEADLINE_MINUTES)
            ->isPast();
    }

    public function durationDays(Booking $booking): int
    {
        if (! $booking->check_in || ! $booking->check_out) {
            return 0;
        }

        return max(1, (int) $booking->check_in->diffInDays($booking->check_out) + 1);
    }

    public function minutesLeft(Booking $booking): int
    {
        if (! $booking->created_at) {
            return 0;
        }

        return max(
            0,
            Booking::PAYMENT_DEADLINE_MINUTES - (int) $booking->created_at->diffInMinutes(now())
        );
    }

    public function isArrivingToday(Booking $booking): bool
    {
        if (! $booking->check_in || ! $booking->check_in->isToday()) {
            return false;
        }

        return in_array($booking->status, [
            Booking::STATUS_PENDING,
            Booking::STATUS_RESERVED,
            Booking::STATUS_CONFIRMED,
        ], true);
    }

    public function dueLabelFor(Booking $booking): string
    {
        if ($booking->booking_type === Booking::TYPE_RESERVATION
            && $booking->status === Booking::STATUS_PENDING) {
            return 'Reservation fee due';
        }

        return 'Balance due';
    }

    // ─────────────────────────────────────────────────────────
    //  Queries
    // ─────────────────────────────────────────────────────────

    #[Computed]
    public function users()
    {
        return User::query()
            ->whereHas('bookings', fn ($q) => $q->where('tenant_id', Auth::user()->tenant_id))
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    private function query()
    {
        return Booking::withoutGlobalScope(TenantScope::class)
            ->with([
                'user:id,name,email,phone',
                'items.property:id,name',
                'services.service:id,name',
                'payments' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)
                    ->select('id', 'booking_id', 'payment_status', 'amount'),
            ])
            ->where('tenant_id', Auth::user()->tenant_id)
            ->when(
                $this->tab === 'archive',
                fn ($q) => $q->whereIn('status', [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED]),
                fn ($q) => $q->whereNotIn('status', [Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED]),
            )
            ->when($this->search, fn ($q) => $q->where(fn ($q2) =>
                $q2->where('booking_reference', 'like', '%' . $this->search . '%')
                   ->orWhereHas('user', fn ($c) => $c->where('name', 'like', '%' . $this->search . '%'))
            ))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->userFilter, fn ($q) => $q->where('user_id', $this->userFilter))
            ->when($this->fromDate && $this->toDate, function ($q) {
                $q->whereBetween('check_in', [
                    Carbon::parse($this->fromDate)->startOfDay(),
                    Carbon::parse($this->toDate)->endOfDay(),
                ]);
            })
            ->when($this->sortBy, function ($q) {
                match ($this->sortBy) {
                    'check_in_asc'  => $q->orderBy('check_in', 'asc'),
                    'check_in_desc' => $q->orderBy('check_in', 'desc'),
                    'amount_high'   => $q->orderByDesc('total_amount'),
                    'amount_low'    => $q->orderBy('total_amount'),
                    default         => $q->latest(),
                };
            });
    }

    #[Computed]
    public function bookings()
    {
        return $this->query()->paginate(12);
    }

    #[Computed]
    public function stats()
    {
        $tid      = Auth::user()->tenant_id;
        $deadline = now()->subMinutes(Booking::PAYMENT_DEADLINE_MINUTES)->toDateTimeString();
        $today    = today()->toDateString();

        $agg = Booking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tid)
            ->selectRaw("
                SUM(CASE WHEN status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as total,
                SUM(CASE WHEN status IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as archived,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) as checked_in,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'pending' AND created_at <= ? THEN 1 ELSE 0 END) as overdue,
                SUM(CASE WHEN status IN ('confirmed', 'checked_in', 'completed') THEN total_amount ELSE 0 END) as revenue,
                SUM(CASE WHEN DATE(check_in) = ? AND status != 'cancelled' THEN 1 ELSE 0 END) as today_arrivals,
                SUM(CASE WHEN DATE(check_out) = ? AND status != 'cancelled' THEN 1 ELSE 0 END) as today_departures
            ", [$deadline, $today, $today])
            ->first();

        $availableCount = Property::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tid)
            ->where('is_active', true)
            ->where('status', 'available')
            ->count();

        return [
            'total'            => $agg->total            ?? 0,
            'archived'         => $agg->archived         ?? 0,
            'pending'          => $agg->pending          ?? 0,
            'reserved'         => $agg->reserved         ?? 0,
            'confirmed'        => $agg->confirmed        ?? 0,
            'checked_in'       => $agg->checked_in       ?? 0,
            'completed'        => $agg->completed        ?? 0,
            'cancelled'        => $agg->cancelled        ?? 0,
            'overdue'          => $agg->overdue          ?? 0,
            'revenue'          => $agg->revenue          ?? 0,
            'today_arrivals'   => $agg->today_arrivals   ?? 0,
            'today_departures' => $agg->today_departures ?? 0,
            'available'        => $availableCount,
        ];
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->statusFilter !== ''
            || ! empty($this->fromDate)
            || ! empty($this->toDate)
            || ! empty($this->userFilter);
    }
};
?>

@php
    $s = $this->stats;
    $isArchive = $tab === 'archive';
    $canManagePayments = $this->tenantCan('manage payments');
    $canEditBookings   = $this->tenantCan('edit bookings');
    $canCancelBookings = $canEditBookings;
@endphp

<div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6"
     x-data="{
         qrPolling: false,
         qrPollTimer: null,
         init() {
             this.$watch('$wire.showQrModal', (v) => {
                 this.qrPolling = v;
                 if (v) {
                     this.qrPollTimer = setInterval(() => this.$wire.checkQrPayment(), 5000);
                 } else if (this.qrPollTimer) {
                     clearInterval(this.qrPollTimer);
                     this.qrPollTimer = null;
                 }
             });
         },
         destroy() {
             if (this.qrPollTimer) clearInterval(this.qrPollTimer);
         }
     }">

    {{-- ═══ Page header ═══ --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">
                    {{ $isArchive ? 'Archive' : 'Bookings' }}
                </span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                {{ $isArchive ? 'Archived Bookings' : 'Active Bookings' }}
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ $isArchive
                    ? 'Completed and cancelled bookings. Read-only history.'
                    : 'Manage current reservations, arrivals, and payments.' }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="$refresh"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                           transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M4 9a9 9 0 0014.5 4.5M20 20v-5h-5M20 15a9 9 0 00-14.5-4.5"/>
                </svg>
                <span>Refresh</span>
            </button>

            @unless($isArchive)
                @if($this->tenantCan('create bookings'))
                    <a href="{{ route('tenant.bookings.create') }}" wire:navigate
                       class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                              transition-all duration-200 active:scale-95
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span>New Reservation</span>
                    </a>
                @endif
            @endunless
        </div>
    </div>

    {{-- ═══ Flash messages ═══ --}}
    @if(session()->has('message'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 4000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>{{ session('message') }}</span>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 rounded-md text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/10
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    @if(session()->has('error'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 5000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    {{-- ═══ Tab switcher ═══ --}}
    <div class="inline-flex items-center gap-1 p-1 bg-gray-100 dark:bg-gray-800 rounded-xl"
         role="tablist"
         aria-label="Booking scope">
        <button type="button" wire:click="setTab('active')" role="tab"
                aria-selected="{{ ! $isArchive ? 'true' : 'false' }}"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 h-10 px-4 rounded-lg text-xs font-bold uppercase tracking-wider
                       transition-all duration-200
                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                       {{ ! $isArchive
                          ? 'bg-white dark:bg-gray-700 text-primary-600 dark:text-primary-400 shadow-sm'
                          : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100' }}">
            <span>Active</span>
            <span class="inline-flex items-center justify-center min-w-[22px] h-5 px-1.5 rounded-full text-[10px] font-bold tabular-nums
                         {{ ! $isArchive
                            ? 'bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-300'
                            : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400' }}">
                {{ $s['total'] }}
            </span>
        </button>
        <button type="button" wire:click="setTab('archive')" role="tab"
                aria-selected="{{ $isArchive ? 'true' : 'false' }}"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 h-10 px-4 rounded-lg text-xs font-bold uppercase tracking-wider
                       transition-all duration-200
                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                       {{ $isArchive
                          ? 'bg-white dark:bg-gray-700 text-primary-600 dark:text-primary-400 shadow-sm'
                          : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100' }}">
            <span>Archive</span>
            <span class="inline-flex items-center justify-center min-w-[22px] h-5 px-1.5 rounded-full text-[10px] font-bold tabular-nums
                         {{ $isArchive
                            ? 'bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-300'
                            : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400' }}">
                {{ $s['archived'] }}
            </span>
        </button>
    </div>

    {{-- ═══ KPI strip ═══ --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @php
            $kpis = $isArchive
                ? [
                    ['label' => 'Completed', 'value' => $s['completed'], 'dot' => 'bg-slate-500'],
                    ['label' => 'Cancelled', 'value' => $s['cancelled'], 'dot' => 'bg-rose-500'],
                    ['label' => 'Total Archived', 'value' => $s['archived'], 'dot' => 'bg-gray-400'],
                    ['label' => 'Revenue', 'value' => '₱' . number_format((float) $s['revenue'], 0), 'dot' => 'bg-emerald-500'],
                ]
                : [
                    ['label' => 'Arrivals Today',   'value' => $s['today_arrivals'],   'dot' => 'bg-emerald-500'],
                    ['label' => 'Departures Today', 'value' => $s['today_departures'], 'dot' => 'bg-rose-500'],
                    [
                        'label' => 'Pending',
                        'value' => $s['pending'],
                        'dot'   => 'bg-amber-500',
                        'sub'   => $s['overdue'] > 0 ? "{$s['overdue']} overdue" : null,
                    ],
                    ['label' => 'Revenue', 'value' => '₱' . number_format((float) $s['revenue'], 0), 'dot' => 'bg-emerald-500'],
                ];
        @endphp
        @foreach($kpis as $kpi)
            <div wire:key="kpi-{{ $tab }}-{{ $loop->index }}"
                 class="bg-white dark:bg-gray-800/90 rounded-xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-3.5">
                <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full {{ $kpi['dot'] }}"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</span>
                </div>
                <p class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white tabular-nums">{{ $kpi['value'] }}</p>
                @if(! empty($kpi['sub']))
                    <p class="text-[10px] text-rose-600 dark:text-rose-400 font-bold uppercase tracking-wider mt-0.5">{{ $kpi['sub'] }}</p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ═══ Filters ═══ --}}
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-4 space-y-4">
        <div class="relative">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   class="input w-full"
                   style="padding-left: 2.5rem;"
                   placeholder="Search reference or guest…">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <select wire:model.live="userFilter" class="input w-full">
                <option value="">All Guests</option>
                @foreach($this->users as $user)
                    <option value="{{ $user->id }}" wire:key="guest-opt-{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="sortBy" class="input w-full">
                <option value="newest">Newest Created</option>
                <option value="check_in_asc">Start (Earliest)</option>
                <option value="check_in_desc">Start (Latest)</option>
                <option value="amount_high">Amount (High → Low)</option>
                <option value="amount_low">Amount (Low → High)</option>
            </select>
        </div>

        <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Date Range</p>
            <div class="grid grid-cols-2 gap-2">
                <input type="date" wire:model.live="fromDate" class="input w-full" aria-label="From date">
                <input type="date" wire:model.live="toDate"   class="input w-full" aria-label="To date">
            </div>
        </div>

        @php
            $pills = $isArchive
                ? [
                    ['value' => '',          'label' => 'All',       'count' => $s['archived']],
                    ['value' => 'completed', 'label' => 'Completed', 'count' => $s['completed']],
                    ['value' => 'cancelled', 'label' => 'Cancelled', 'count' => $s['cancelled']],
                ]
                : [
                    ['value' => '',           'label' => 'All',        'count' => $s['total']],
                    ['value' => 'pending',    'label' => 'Pending',    'count' => $s['pending']],
                    ['value' => 'reserved',   'label' => 'Reserved',   'count' => $s['reserved']],
                    ['value' => 'confirmed',  'label' => 'Confirmed',  'count' => $s['confirmed']],
                    ['value' => 'checked_in', 'label' => 'Checked In', 'count' => $s['checked_in']],
                ];
        @endphp
        <div class="flex flex-wrap gap-2 items-center pt-1">
            @foreach($pills as $pill)
                @php $isActive = $statusFilter === $pill['value']; @endphp
                <button type="button"
                        wire:click="$set('statusFilter', '{{ $pill['value'] }}')"
                        wire:key="pill-{{ $tab }}-{{ $pill['value'] !== '' ? $pill['value'] : 'all' }}"
                        aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                        class="inline-flex items-center gap-2 h-11 sm:h-9 pl-3.5 pr-1.5 rounded-full text-xs font-semibold uppercase tracking-wide border
                               transition-all duration-200 active:scale-95 shrink-0
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                               {{ $isActive
                                  ? 'bg-primary-600 border-primary-600 text-white shadow-sm'
                                  : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-primary-400 hover:text-primary-600 dark:hover:text-primary-400' }}">
                    <span>{{ $pill['label'] }}</span>
                    <span class="inline-flex items-center justify-center min-w-[22px] h-5 px-1.5 rounded-full text-[10px] font-bold tabular-nums
                                 {{ $isActive
                                    ? 'bg-white/20 text-white'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                        {{ $pill['count'] }}
                    </span>
                </button>
            @endforeach

            @if(! $isArchive && $s['overdue'] > 0)
                <span class="inline-flex items-center gap-1.5 h-11 sm:h-9 px-3 rounded-full text-[10px] font-bold uppercase tracking-wider
                             bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    {{ $s['overdue'] }} Overdue
                </span>
            @endif

            @if($this->hasActiveFilters)
                <button type="button" wire:click="clearFilters"
                        class="inline-flex items-center gap-1 h-11 sm:h-9 px-3.5 rounded-full text-xs font-semibold uppercase tracking-wide
                               border border-rose-300 dark:border-rose-500/40
                               bg-white dark:bg-gray-800 text-rose-700 dark:text-rose-300
                               transition-all duration-200 active:scale-95
                               hover:bg-rose-50 dark:hover:bg-rose-500/10
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Clear
                </button>
            @endif
        </div>
    </div>

    {{-- ═══ Card grid ═══ --}}
    @if($this->bookings->isEmpty())
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-12 text-center">
            <div class="flex flex-col items-center max-w-md mx-auto">
                <svg class="w-14 h-14 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                <p class="mt-4 text-base font-semibold text-gray-900 dark:text-white">
                    No {{ $isArchive ? 'archived' : 'active' }} bookings
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if($this->hasActiveFilters)
                        No bookings match your current filters. Try adjusting or clearing them.
                    @elseif($isArchive)
                        Completed and past bookings will appear here automatically.
                    @else
                        You don't have any active bookings yet.
                    @endif
                </p>
            </div>
        </div>
    @else
        <div wire:loading.class="opacity-40 pointer-events-none"
             wire:target="search,statusFilter,fromDate,toDate,userFilter,sortBy,clearFilters,gotoPage,nextPage,previousPage,setTab,openCancelModal,confirmCancellation,openPaymentModal,submitCashPayment,submitQrPayment"
             class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 transition-opacity duration-200">
            @foreach($this->bookings as $booking)
                @php
                    $paid      = $this->paidAmount($booking);
                    $balance   = $this->balanceAmount($booking);
                    $dueNow    = $this->amountDueFor($booking);
                    $isOverdue = $this->isOverdueBooking($booking);
                    $days      = $this->durationDays($booking);
                    $minsLeft  = $this->minutesLeft($booking);
                    $statusCfg = $this->statusConfigFor($booking->status);
                    $arriving  = $this->isArrivingToday($booking);
                    $stay      = $this->formatStayLine($booking);
                    $canPay    = $canManagePayments && $this->canCollectPayment($booking);
                    $isReservationPending = $booking->booking_type === 'reservation' && $booking->status === 'pending';
                @endphp

                <article wire:key="booking-{{ $booking->id }}"
                         wire:click="openDetails({{ $booking->id }})"
                         wire:keydown.enter="openDetails({{ $booking->id }})"
                         wire:keydown.space.prevent="openDetails({{ $booking->id }})"
                         role="button"
                         tabindex="0"
                         aria-label="Open details for booking {{ $booking->booking_reference }}"
                         class="group relative bg-white dark:bg-gray-800/90 rounded-2xl border shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 flex flex-col overflow-hidden cursor-pointer
                                focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/60 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                {{ $isOverdue ? 'border-rose-300 dark:border-rose-500/40' : 'border-gray-200/80 dark:border-gray-700/80' }}">

                    <div class="p-4 pb-3">
                        <div class="flex items-start justify-between gap-3 mb-1">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-11 h-11 rounded-full bg-primary-50 dark:bg-primary-500/15 flex items-center justify-center text-primary-600 dark:text-primary-400 font-bold text-sm shrink-0">
                                    {{ strtoupper(substr($booking->user->name ?? 'G', 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 dark:text-white truncate leading-tight">
                                        {{ $booking->user->name ?? 'Walk-in Guest' }}
                                    </p>
                                    <p class="text-[11px] font-mono text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                        {{ $booking->booking_reference }}
                                    </p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border shrink-0
                                         {{ $statusCfg[0] }}">
                                <span class="w-1 h-1 rounded-full bg-current"></span>
                                {{ $statusCfg[1] }}
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5 mt-1">
                            @if($arriving)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                             bg-primary-50 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300
                                             text-[10px] font-bold uppercase tracking-wider">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Arriving Today
                                </span>
                            @endif
                            @if($booking->booking_type === 'reservation')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                             bg-blue-50 dark:bg-blue-500/15 text-blue-700 dark:text-blue-300
                                             text-[10px] font-bold uppercase tracking-wider">
                                    Reserved
                                </span>
                            @endif
                            @if($booking->status === 'cancelled' && $booking->refund_status !== 'none' && $booking->refund_status !== null)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                                    {{ $booking->refund_status === 'processed'
                                        ? 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                        : ($booking->refund_status === 'rejected'
                                            ? 'bg-rose-50 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300'
                                            : 'bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300') }}">
                                    Refund {{ $booking->refund_status }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700/60 space-y-1">
                        <div class="flex items-center gap-2 text-xs">
                            <svg class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-gray-700 dark:text-gray-300 tabular-nums truncate">
                                {{ $stay }}
                            </span>
                        </div>
                        @if($days > 0)
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 pl-5.5">
                                {{ $days }} day{{ $days != 1 ? 's' : '' }}
                            </p>
                        @endif
                    </div>

                    <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700/60 space-y-1 text-xs">
                        @foreach($booking->items->take(2) as $item)
                            <div class="flex justify-between gap-2" wire:key="card-item-{{ $item->id }}">
                                <span class="text-gray-600 dark:text-gray-400 truncate">
                                    {{ $item->property->name ?? 'Unknown' }} <span class="text-gray-400 dark:text-gray-500">×{{ $item->quantity }}</span>
                                </span>
                                <span class="text-gray-900 dark:text-white tabular-nums shrink-0">₱{{ number_format((float) $item->subtotal, 0) }}</span>
                            </div>
                        @endforeach
                        @if($booking->items->count() > 2)
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 pt-0.5">
                                +{{ $booking->items->count() - 2 }} more item{{ $booking->items->count() - 2 != 1 ? 's' : '' }}
                            </p>
                        @endif
                        @if($booking->services->isNotEmpty())
                            <div class="pt-1.5 mt-1.5 border-t border-dashed border-gray-200 dark:border-gray-700 flex justify-between text-[11px] text-gray-500 dark:text-gray-400">
                                <span>{{ $booking->services->count() }} add-on service{{ $booking->services->count() != 1 ? 's' : '' }}</span>
                                <span class="tabular-nums">₱{{ number_format((float) $booking->services->sum('subtotal'), 0) }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700/60 flex items-end justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-lg font-bold text-gray-900 dark:text-white tabular-nums leading-none">
                                ₱{{ number_format((float) $booking->total_amount, 0) }}
                            </p>
                            @if($dueNow > 0)
                                <p class="text-[11px] {{ $isOverdue ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400' }} tabular-nums mt-1">
                                    ₱{{ number_format($dueNow, 0) }} due
                                    @if($isReservationPending)
                                        <span class="text-gray-400 dark:text-gray-500 font-normal">(20% fee)</span>
                                    @endif
                                </p>
                                @if($balance > $dueNow)
                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 tabular-nums">
                                        ₱{{ number_format($balance - $dueNow, 0) }} on arrival
                                    </p>
                                @endif
                            @else
                                <p class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-1 inline-flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Paid in full
                                </p>
                            @endif
                        </div>

                        @if($booking->status === 'pending' && $dueNow > 0)
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider tabular-nums shrink-0
                                         {{ $isOverdue ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400' }}">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                {{ $isOverdue ? 'Overdue' : $minsLeft . 'm left' }}
                            </span>
                        @endif
                    </div>

                    <div class="mt-auto px-3 py-2.5 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-end gap-1">
                        @if($canPay)
                            <button type="button"
                                    wire:click.stop="openPaymentModal({{ $booking->id }})"
                                    aria-label="Collect payment for {{ $booking->booking_reference }}"
                                    title="Collect payment"
                                    class="inline-flex items-center justify-center h-11 w-11 sm:h-9 sm:w-9 rounded-lg text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10
                                           transition-all duration-200 active:scale-95
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <rect x="2" y="6" width="20" height="12" rx="2" stroke="currentColor" stroke-width="2"/>
                                    <circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="2"/>
                                </svg>
                            </button>
                        @endif

                        @if($canEditBookings)
                            <a href="{{ route('tenant.bookings.edit', $booking->id) }}"
                               wire:navigate
                               @click.stop
                               aria-label="Edit booking {{ $booking->booking_reference }}"
                               title="Edit"
                               class="inline-flex items-center justify-center h-11 w-11 sm:h-9 sm:w-9 rounded-lg text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-500/10
                                      transition-all duration-200 active:scale-95
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>
                        @endif

                        @if($canCancelBookings && in_array($booking->status, ['pending', 'confirmed', 'reserved', 'checked_in'], true))
                            <button type="button"
                                    wire:click.stop="openCancelModal({{ $booking->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="openCancelModal"
                                    aria-label="Cancel booking {{ $booking->booking_reference }}"
                                    title="Cancel booking"
                                    class="inline-flex items-center justify-center h-11 w-11 sm:h-9 sm:w-9 rounded-lg text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10
                                           transition-all duration-200 active:scale-95
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        @if($this->bookings->hasPages())
            <div class="pt-2">
                {{ $this->bookings->links() }}
            </div>
        @endif
    @endif

    {{-- ═══ Details modal ═══ --}}
    @php $b = $this->viewingBooking; @endphp
    @if($b)
        @php
            $modalStatus    = $this->statusConfigFor($b->status);
            $modalPaid      = $this->paidAmount($b);
            $modalBalance   = $this->balanceAmount($b);
            $modalDueNow    = $this->amountDueFor($b);
            $modalOverdue   = $this->isOverdueBooking($b);
            $modalDays      = $this->durationDays($b);
            $modalStay      = $this->formatStayLine($b);
            $itemsSubtotal  = (float) $b->items->sum('subtotal');
            $servicesTotal  = (float) $b->services->sum('subtotal');
            $modalCanPay    = $canManagePayments && $this->canCollectPayment($b);
        @endphp

        <div wire:key="booking-modal-{{ $b->id }}"
             wire:keydown.escape.window="closeDetails"
             role="dialog"
             aria-modal="true"
             aria-labelledby="booking-modal-title"
             x-data="{
                 init() { document.body.classList.add('overflow-hidden'); },
                 destroy() { document.body.classList.remove('overflow-hidden'); }
             }"
             class="booking-details-modal fixed inset-0 z-50 overflow-y-auto">

            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm"
                 wire:click="closeDetails"
                 aria-hidden="true"></div>

            <div class="relative flex min-h-full items-center justify-center p-2 sm:p-4">
                <div @click.stop
                     class="relative w-full max-w-3xl max-h-[calc(100dvh-1rem)] sm:max-h-[85dvh] bg-white dark:bg-gray-800 rounded-2xl shadow-2xl flex flex-col overflow-hidden">

                    <header class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border {{ $modalStatus[0] }}">
                                <span class="w-1 h-1 rounded-full bg-current"></span>
                                {{ $modalStatus[1] }}
                            </span>
                            <h2 id="booking-modal-title"
                                class="font-mono text-sm sm:text-base font-semibold text-gray-900 dark:text-white truncate">
                                {{ $b->booking_reference }}
                            </h2>
                        </div>
                        <button type="button"
                                wire:click="closeDetails"
                                class="inline-flex items-center justify-center h-11 w-11 rounded-lg text-gray-500 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700
                                       transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                aria-label="Close details">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </header>

                    <div class="flex-1 overflow-y-auto px-5 py-5 space-y-6">

                        <section>
                            <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">Customer</h3>
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-full bg-primary-50 dark:bg-primary-500/15 flex items-center justify-center text-primary-600 dark:text-primary-400 font-bold text-base shrink-0">
                                    {{ strtoupper(substr($b->user->name ?? 'G', 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 dark:text-white truncate">
                                        {{ $b->user->name ?? 'Walk-in Guest' }}
                                    </p>
                                    @if($b->user?->email)
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $b->user->email }}</p>
                                    @endif
                                    @if($b->user?->phone)
                                        <p class="text-xs text-gray-500 dark:text-gray-400 tabular-nums">{{ $b->user->phone }}</p>
                                    @endif
                                </div>
                            </div>
                        </section>

                        <section>
                            <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">Stay</h3>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-3">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Check-in</p>
                                    <p class="text-gray-900 dark:text-white font-semibold tabular-nums mt-1">
                                        {{ $b->check_in?->format('M j, Y') ?? '—' }}
                                    </p>
                                    @if($this->formatTime($b->booking_time))
                                        <p class="text-xs text-gray-500 dark:text-gray-400 tabular-nums">{{ $this->formatTime($b->booking_time) }}</p>
                                    @endif
                                </div>
                                <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-3">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Check-out</p>
                                    <p class="text-gray-900 dark:text-white font-semibold tabular-nums mt-1">
                                        {{ $b->check_out?->format('M j, Y') ?? '—' }}
                                    </p>
                                    @if($this->formatTime($b->booking_time))
                                        <p class="text-xs text-gray-500 dark:text-gray-400 tabular-nums">{{ $this->formatTime($b->booking_time) }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mt-3 pl-1">
                                <span>{{ $modalDays }} day{{ $modalDays != 1 ? 's' : '' }} · {{ ucfirst($b->booking_type) }} booking</span>
                                <span class="tabular-nums">{{ $modalStay }}</span>
                            </div>
                        </section>

                        @if($b->items->isNotEmpty())
                            <section>
                                <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">Items</h3>
                                <div class="rounded-xl border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700/60">
                                    @foreach($b->items as $item)
                                        <div class="flex items-center justify-between gap-3 px-3 py-2.5 text-sm">
                                            <div class="min-w-0">
                                                <p class="text-gray-900 dark:text-white truncate">{{ $item->property->name ?? 'Unknown' }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 tabular-nums">
                                                    ₱{{ number_format((float) $item->price, 0) }} × {{ $item->quantity }}
                                                </p>
                                            </div>
                                            <span class="text-gray-900 dark:text-white tabular-nums font-medium shrink-0">
                                                ₱{{ number_format((float) $item->subtotal, 0) }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        @if($b->services->isNotEmpty())
                            <section>
                                <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">Add-on Services</h3>
                                <div class="rounded-xl border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700/60">
                                    @foreach($b->services as $svc)
                                        <div class="flex items-center justify-between gap-3 px-3 py-2.5 text-sm">
                                            <div class="min-w-0">
                                                <p class="text-gray-900 dark:text-white truncate">{{ $svc->service->name ?? 'Service' }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 tabular-nums">×{{ $svc->quantity }}</p>
                                            </div>
                                            <span class="text-gray-900 dark:text-white tabular-nums font-medium shrink-0">
                                                ₱{{ number_format((float) $svc->subtotal, 0) }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        <section>
                            <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">Receipt</h3>
                            <div class="rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 p-4 space-y-2 text-sm">
                                @if($itemsSubtotal > 0)
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Items subtotal</span>
                                        <span class="text-gray-900 dark:text-white tabular-nums">₱{{ number_format($itemsSubtotal, 2) }}</span>
                                    </div>
                                @endif
                                @if($servicesTotal > 0)
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Services subtotal</span>
                                        <span class="text-gray-900 dark:text-white tabular-nums">₱{{ number_format($servicesTotal, 2) }}</span>
                                    </div>
                                @endif
                                <div class="flex items-center justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                                    <span class="text-gray-900 dark:text-white font-semibold">Total</span>
                                    <span class="text-gray-900 dark:text-white font-bold tabular-nums">₱{{ number_format((float) $b->total_amount, 2) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Paid</span>
                                    <span class="text-emerald-600 dark:text-emerald-400 tabular-nums">₱{{ number_format($modalPaid, 2) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Balance</span>
                                    <span class="text-gray-900 dark:text-white tabular-nums">₱{{ number_format($modalBalance, 2) }}</span>
                                </div>
                                @if($modalDueNow > 0 && $modalDueNow < $modalBalance)
                                    <div class="flex items-center justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                                        <span class="text-[11px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300">
                                            {{ $this->dueLabelFor($b) }}
                                        </span>
                                        <span class="tabular-nums font-bold text-amber-700 dark:text-amber-300">
                                            ₱{{ number_format($modalDueNow, 2) }}
                                        </span>
                                    </div>
                                @endif
                                @if($modalOverdue)
                                    <p class="text-[11px] text-rose-600 dark:text-rose-400 font-bold uppercase tracking-wider pt-2 border-t border-gray-200 dark:border-gray-700">
                                        Payment window closed
                                    </p>
                                @endif
                            </div>
                        </section>
                    </div>

                    <footer class="flex flex-wrap items-center justify-end gap-2 px-5 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                        <button type="button"
                                onclick="window.print()"
                                class="mr-auto inline-flex items-center gap-2 h-11 px-4 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                       transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            <span>Print</span>
                        </button>

                        @if($modalCanPay)
                            <button type="button"
                                    wire:click="openPaymentModal({{ $b->id }})"
                                    class="inline-flex items-center gap-2 h-11 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-sm
                                           transition-all duration-200 active:scale-95
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <rect x="2" y="6" width="20" height="12" rx="2" stroke="currentColor" stroke-width="2"/>
                                    <circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                <span>Collect Payment</span>
                            </button>
                        @endif

                        @if($canEditBookings)
                            <a href="{{ route('tenant.bookings.edit', $b->id) }}"
                               wire:navigate
                               class="inline-flex items-center gap-2 h-11 px-4 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                      transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                <span>Edit</span>
                            </a>
                        @endif

                        @if($canCancelBookings && in_array($b->status, ['pending', 'confirmed', 'reserved', 'checked_in'], true))
                            <button type="button"
                                    wire:click="openCancelModal({{ $b->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="openCancelModal"
                                    class="inline-flex items-center gap-2 h-11 px-4 rounded-xl border border-rose-300 dark:border-rose-500/40 bg-white dark:bg-gray-800 text-rose-700 dark:text-rose-300 text-sm font-semibold
                                           transition-all duration-200 active:scale-95 hover:bg-rose-50 dark:hover:bg-rose-500/10
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                <span>Cancel Booking</span>
                            </button>
                        @endif
                    </footer>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══ Payment method modal ═══ --}}
    @php $pb = $this->payingBooking; @endphp
    @if($showPaymentModal && $pb)
        @php $payDue = $this->amountDueFor($pb); @endphp
        <div wire:key="payment-modal-{{ $pb->id }}"
             wire:keydown.escape.window="closePaymentModal"
             role="dialog"
             aria-modal="true"
             aria-labelledby="payment-modal-title"
             class="fixed inset-0 z-[90] overflow-y-auto">

            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm"
                 wire:click="closePaymentModal"
                 aria-hidden="true"></div>

            <div class="relative flex min-h-full items-center justify-center p-2 sm:p-4">
                <div @click.stop
                     class="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-2xl flex flex-col overflow-hidden">

                    <header class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="min-w-0">
                            <h2 id="payment-modal-title"
                                class="text-base font-semibold text-gray-900 dark:text-white">
                                Collect Payment
                            </h2>
                            <p class="text-xs font-mono text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                {{ $pb->booking_reference }}
                            </p>
                        </div>
                        <button type="button"
                                wire:click="closePaymentModal"
                                class="inline-flex items-center justify-center h-11 w-11 rounded-lg text-gray-500 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700
                                       transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                aria-label="Close">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </header>

                    <div class="px-5 py-5 space-y-5">

                        <div class="rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Amount Due Now
                            </p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white tabular-nums mt-1">
                                ₱{{ number_format($payDue, 2) }}
                            </p>
                            @if($pb->booking_type === 'reservation' && $pb->status === 'pending')
                                <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">
                                    20% reservation fee — booking becomes Reserved once paid.
                                </p>
                            @elseif($pb->booking_type === 'reservation' && $pb->status === 'reserved')
                                <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">
                                    Balance owed on arrival — booking becomes Confirmed once paid.
                                </p>
                            @endif
                        </div>

                        @if($qrError)
                            <div class="rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 p-3 text-xs text-rose-700 dark:text-rose-300">
                                {{ $qrError }}
                            </div>
                        @endif

                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
                                Payment Method
                            </p>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="cursor-pointer relative
                                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                                    <input type="radio" wire:model.live="paymentMethod" value="cash" class="sr-only peer">
                                    <div class="flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-center transition-all duration-200 cursor-pointer
                                                peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 peer-checked:shadow-lg active:scale-[0.98]">
                                        <svg class="w-7 h-7 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <p class="text-gray-900 dark:text-white font-semibold text-sm">Cash</p>
                                        <p class="text-gray-500 dark:text-gray-400 text-[10px]">Recorded now</p>
                                    </div>
                                </label>

                                <label class="cursor-pointer relative
                                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                                    <input type="radio" wire:model.live="paymentMethod" value="qr" class="sr-only peer">
                                    <div class="flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-center transition-all duration-200 cursor-pointer
                                                peer-checked:border-primary-600 peer-checked:bg-primary-50 dark:peer-checked:bg-primary-900/30 peer-checked:shadow-lg active:scale-[0.98]">
                                        <svg class="w-7 h-7 text-gray-700 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <rect x="4" y="4" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.6"/>
                                            <rect x="14" y="4" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.6"/>
                                            <rect x="4" y="14" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.6"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M14 14h2v2h-2zM20 14v2M14 20h2M18 20h2v-2"/>
                                        </svg>
                                        <p class="text-gray-900 dark:text-white font-semibold text-sm">QR Code</p>
                                        <p class="text-gray-500 dark:text-gray-400 text-[10px]">Scan via PayMongo</p>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <footer class="flex items-center justify-end gap-2 px-5 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                        <button type="button"
                                wire:click="closePaymentModal"
                                class="inline-flex items-center gap-2 h-11 px-4 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                       transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            Cancel
                        </button>

                        @if($paymentMethod === 'cash')
                            <button type="button"
                                    wire:click="submitCashPayment"
                                    wire:loading.attr="disabled"
                                    wire:target="submitCashPayment"
                                    class="inline-flex items-center gap-2 h-11 px-5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-sm
                                           transition-all duration-200 active:scale-95
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                <span wire:loading.remove wire:target="submitCashPayment">Record Cash</span>
                                <span wire:loading wire:target="submitCashPayment" class="inline-flex items-center gap-2">
                                    <svg class="animate-spin w-4 h-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    Processing…
                                </span>
                            </button>
                        @else
                            <button type="button"
                                    wire:click="submitQrPayment"
                                    wire:loading.attr="disabled"
                                    wire:target="submitQrPayment"
                                    class="inline-flex items-center gap-2 h-11 px-5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold shadow-sm
                                           transition-all duration-200 active:scale-95
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                <span wire:loading.remove wire:target="submitQrPayment">Generate QR</span>
                                <span wire:loading wire:target="submitQrPayment" class="inline-flex items-center gap-2">
                                    <svg class="animate-spin w-4 h-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    Processing…
                                </span>
                            </button>
                        @endif
                    </footer>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══ QR modal ═══ --}}
    @if($showQrModal && $qrImage)
        <div wire:key="qr-modal-active"
             wire:keydown.escape.window="cancelQrPayment"
             role="dialog"
             aria-modal="true"
             aria-labelledby="qr-modal-title"
             class="fixed inset-0 z-[95] overflow-y-auto">

            <div class="fixed inset-0 bg-black/70 backdrop-blur-sm"
                 wire:click="cancelQrPayment"
                 aria-hidden="true"></div>

            <div class="relative flex min-h-full items-center justify-center p-2 sm:p-4">
                <div @click.stop
                     class="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-2xl flex flex-col overflow-hidden">

                    <header class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="min-w-0">
                            <h2 id="qr-modal-title"
                                class="text-base font-semibold text-gray-900 dark:text-white">
                                Scan to Pay
                            </h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Open any e-wallet or bank app to scan
                            </p>
                        </div>
                        <button type="button"
                                wire:click="cancelQrPayment"
                                class="inline-flex items-center justify-center h-11 w-11 rounded-lg text-gray-500 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700
                                       transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                aria-label="Close">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </header>

                    <div class="px-5 py-5 space-y-4">
                        <div class="bg-white rounded-2xl p-4 flex items-center justify-center border-2 border-gray-200 dark:border-gray-700">
                            <img src="{{ $qrImage }}" alt="PayMongo QR Code" class="w-64 h-64 object-contain">
                        </div>

                        <div class="text-center">
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount due</p>
                            <p class="text-2xl font-bold text-primary-600 dark:text-primary-400 mt-1 tabular-nums">
                                ₱{{ number_format($qrAmount, 2) }}
                            </p>
                            @if($qrExpiresAt)
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Expires {{ \Carbon\Carbon::parse($qrExpiresAt)->diffForHumans() }}
                                </p>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 p-3 rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20">
                            <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0 animate-pulse motion-reduce:animate-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-xs text-blue-800 dark:text-blue-300 font-medium">
                                Waiting for payment confirmation…
                            </p>
                        </div>
                    </div>

                    <footer class="flex items-center gap-2 px-5 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                        <button type="button"
                                wire:click="checkQrPayment"
                                wire:loading.attr="disabled"
                                wire:target="checkQrPayment"
                                class="flex-1 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                       transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                       disabled:opacity-60 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="checkQrPayment">Check Now</span>
                            <span wire:loading wire:target="checkQrPayment" class="inline-flex items-center gap-2">
                                <svg class="animate-spin w-4 h-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Checking…
                            </span>
                        </button>
                        <button type="button"
                                wire:click="cancelQrPayment"
                                class="flex-1 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                       transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            Close
                        </button>
                    </footer>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══ Admin cancellation modal — always 100% refund ═══ --}}
    @php $acb = $this->cancellingBooking; @endphp
    @if($showCancelModal && $acb && $adminCancelPreview)
        @php
            $acRefundAmt = (float) $adminCancelPreview['refund_amount'];
            $acPaidAmt   = (float) $adminCancelPreview['paid_amount'];
        @endphp

        <div wire:key="admin-cancel-modal-{{ $acb->id }}"
             wire:keydown.escape.window="closeCancelModal"
             role="dialog"
             aria-modal="true"
             aria-labelledby="admin-cancel-modal-title"
             x-data="{
                 init() { document.body.classList.add('overflow-hidden'); },
                 destroy() { document.body.classList.remove('overflow-hidden'); }
             }"
             class="fixed inset-0 z-[100] overflow-y-auto">

            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm"
                 wire:click="closeCancelModal"
                 aria-hidden="true"></div>

            <div class="relative flex min-h-full items-center justify-center p-3 sm:p-4">
                <div @click.stop
                     class="relative w-full max-w-lg max-h-[calc(100dvh-1.5rem)] bg-white dark:bg-gray-800 rounded-2xl shadow-2xl flex flex-col overflow-hidden">

                    <header class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="min-w-0">
                            <h2 id="admin-cancel-modal-title"
                                class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white">
                                Cancel this booking?
                            </h2>
                            <p class="text-xs font-mono text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                #{{ $acb->booking_reference }} · {{ $acb->user?->name ?? 'Guest' }}
                            </p>
                        </div>
                        <button type="button"
                                wire:click="closeCancelModal"
                                class="inline-flex items-center justify-center h-11 w-11 rounded-lg text-gray-500 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700
                                       transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                aria-label="Close">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </header>

                    <div class="flex-1 overflow-y-auto px-5 py-5 space-y-4">

                        <div class="rounded-2xl border p-4
                                    bg-amber-50 dark:bg-amber-500/10
                                    border-amber-200 dark:border-amber-500/30
                                    text-amber-900 dark:text-amber-200">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-500 text-white">
                                    Business cancel
                                </span>
                                <span class="text-xs font-semibold uppercase tracking-wider">
                                    Full refund
                                </span>
                            </div>
                            <p class="text-sm leading-relaxed">
                                Cancelling on behalf of the business issues a
                                <strong class="font-bold">100% refund</strong> to the guest,
                                regardless of how far away the check-in date is.
                            </p>
                        </div>

                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-3">
                                <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Guest paid
                                </dt>
                                <dd class="mt-1 font-semibold text-gray-900 dark:text-white tabular-nums">
                                    ₱{{ number_format($acPaidAmt, 2) }}
                                </dd>
                            </div>
                            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-3">
                                <dt class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Refund to issue
                                </dt>
                                <dd class="mt-1 font-semibold text-gray-900 dark:text-white tabular-nums">
                                    ₱{{ number_format($acRefundAmt, 2) }}
                                </dd>
                            </div>
                        </dl>

                        <div>
                            <label for="admin-cancel-reason"
                                   class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-1.5">
                                Reason for cancellation
                                <span class="text-gray-400 dark:text-gray-500 normal-case font-normal">(optional)</span>
                            </label>
                            <textarea id="admin-cancel-reason"
                                      wire:model="adminCancelReason"
                                      rows="3"
                                      maxlength="500"
                                      placeholder="e.g. Bad weather, emergency closure, double-booking. This is shown to the guest in their cancellation email."
                                      class="textarea"></textarea>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1.5 leading-relaxed">
                                The guest is notified by email that the business cancelled. Include enough detail so they understand why.
                            </p>
                        </div>

                        <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                            Refunds return to the guest's original payment method.
                            @if($acRefundAmt > 0)
                                They will receive a separate email once the refund is processed.
                            @else
                                No payment was collected on this booking — no refund is due.
                            @endif
                        </p>
                    </div>

                    <footer class="flex items-center justify-end gap-2 px-5 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30">
                        <button type="button"
                                wire:click="closeCancelModal"
                                class="inline-flex items-center justify-center gap-2 h-11 px-4 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                       transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            Never mind
                        </button>

                        <button type="button"
                                wire:click="confirmCancellation"
                                wire:loading.attr="disabled"
                                wire:target="confirmCancellation"
                                class="btn-danger min-h-[44px] px-5 disabled:opacity-60 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="confirmCancellation">Cancel &amp; Refund</span>
                            <span wire:loading wire:target="confirmCancellation" class="inline-flex items-center gap-2">
                                <svg class="animate-spin w-4 h-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Processing…
                            </span>
                        </button>
                    </footer>
                </div>
            </div>
        </div>
    @endif
</div>

{{-- Print-only style — hides everything except the details modal when printing. --}}
<script>
    (function () {
        if (document.getElementById('booking-print-styles')) return;
        var style = document.createElement('style');
        style.id = 'booking-print-styles';
        style.textContent = '@media print {' +
            'body *{visibility:hidden !important;}' +
            '.booking-details-modal,.booking-details-modal *{visibility:visible !important;}' +
            '.booking-details-modal{position:absolute !important;inset:0 !important;background:#fff !important;}' +
            '.booking-details-modal [x-show]{display:revert !important;}' +
            '.booking-details-modal footer{display:none !important;}' +
            '}';
        document.head.appendChild(style);
    })();
</script>