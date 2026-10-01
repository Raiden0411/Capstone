<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Transaction;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Luigel\Paymongo\Paymongo;
use Throwable;

class PayMongoService
{
    private const API_BASE      = 'https://api.paymongo.com/v1';
    private const PAID_STATUSES = ['paid', 'succeeded'];

    public const ALLOWED_PAYMENT_METHODS = ['gcash', 'paymaya', 'card'];

    private const DEFAULT_PAYMENT_METHODS = ['gcash', 'paymaya', 'card'];

    public function __construct(
        protected Paymongo $paymongo,
    ) {}

    // ═════════════════════════════════════════════════════════
    //  Hosted Checkout Sessions
    // ═════════════════════════════════════════════════════════

    public function createCheckoutSession(array $data): ?array
    {
        try {
            $checkout = $this->paymongo->checkout()->create([
                'billing' => [
                    'name'  => $data['customer_name'],
                    'email' => $data['customer_email'],
                    'phone' => $this->normalizePhone($data['customer_phone'] ?? null),
                ],
                'line_items' => [[
                    'currency'    => 'PHP',
                    'amount'      => (int) round($data['amount'] * 100),
                    'description' => $data['description'],
                    'name'        => $data['item_name'] ?? 'Booking Payment',
                    'quantity'    => 1,
                ]],
                'payment_method_types' => $this->resolvePaymentMethods(
                    $data['payment_method_types'] ?? null
                ),
                'success_url' => $data['success_url'],
                'cancel_url'  => $data['cancel_url'],
                'metadata'    => $data['metadata'] ?? [],
            ]);

            $checkoutData = $checkout->getData();

            $checkoutId  = data_get($checkoutData, 'id');
            $checkoutUrl = data_get($checkoutData, 'checkout_url');
            $status      = data_get($checkoutData, 'status');

            if (! $checkoutId || ! $checkoutUrl) {
                Log::error('PayMongo Checkout missing ID or URL', ['object' => $checkoutData]);
                return null;
            }

            return [
                'id'           => $checkoutId,
                'checkout_url' => $checkoutUrl,
                'status'       => $status,
            ];
        } catch (Throwable $e) {
            Log::error('PayMongo Checkout Error: ' . $e->getMessage());
            return null;
        }
    }

    private function resolvePaymentMethods(mixed $requested): array
    {
        if (! is_array($requested) || $requested === []) {
            return self::DEFAULT_PAYMENT_METHODS;
        }

        $filtered = array_values(array_intersect(
            array_map('strval', $requested),
            self::ALLOWED_PAYMENT_METHODS,
        ));

        return $filtered !== [] ? $filtered : self::DEFAULT_PAYMENT_METHODS;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! is_string($phone)) {
            return null;
        }

        $phone = trim($phone);
        if ($phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (! is_string($digits) || $digits === '') {
            return null;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return substr($digits, 1);
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '63')) {
            return substr($digits, 2);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            return $digits;
        }

        return $digits;
    }

    public function fetchCheckoutSessionRaw(string $sessionId): ?array
    {
        $secret = (string) config('paymongo.secret_key');

        if ($secret === '') {
            Log::error('PayMongo secret key is not configured.');
            return null;
        }

        try {
            $response = Http::withBasicAuth($secret, '')
                ->acceptJson()
                ->timeout(10)
                ->get(self::API_BASE . "/checkout_sessions/{$sessionId}");

            if ($response->failed()) {
                Log::warning('PayMongo REST checkout lookup failed', [
                    'session_id' => $sessionId,
                    'http'       => $response->status(),
                    'body'       => $response->json() ?? $response->body(),
                ]);
                return null;
            }

            return [
                'attributes' => (array) ($response->json('data.attributes') ?? []),
                'id'         => $response->json('data.id'),
            ];
        } catch (Throwable $e) {
            Log::error('PayMongo REST checkout lookup threw', [
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Resolve the successful PayMongo payment ID (pay_xxx) for a
     * checkout session, fetching from the REST API if necessary.
     *
     * Called by the refund job when a booking's payment row has a
     * session ID but no payment ID (webhook arrived before the
     * payment array was populated). Returns null if PayMongo has no
     * successful payment attached to the session.
     */
    public function resolvePaymentIdFromSession(string $sessionId): ?string
    {
        if ($sessionId === '') {
            return null;
        }

        $raw = $this->fetchCheckoutSessionRaw($sessionId);

        if (! $raw) {
            return null;
        }

        $payments = $raw['attributes']['payments'] ?? [];

        return $this->extractPaymongoPaymentId(is_array($payments) ? $payments : []);
    }

    // ═════════════════════════════════════════════════════════
    //  Payment lookup
    // ═════════════════════════════════════════════════════════

    public function findPaymentForBooking(int $bookingId): ?Payment
    {
        return Payment::withoutGlobalScope(TenantScope::class)
            ->where('booking_id', $bookingId)
            ->whereIn('payment_status', ['pending', 'paid'])
            ->latest('id')
            ->first();
    }

    public function findPaymentBySession(string $sessionId): ?Payment
    {
        return Payment::withoutGlobalScope(TenantScope::class)
            ->where(function ($q) use ($sessionId): void {
                $q->where('paymongo_session_id', $sessionId)
                  ->orWhere('reference_number', $sessionId);
            })
            ->latest('id')
            ->first();
    }

    // ═════════════════════════════════════════════════════════
    //  Refunds
    // ═════════════════════════════════════════════════════════

    public function refundPayment(
        string $paymentId,
        float $amount,
        string $notes,
        string $bookingId,
    ): ?array {
        $secret = (string) config('paymongo.secret_key');

        if ($secret === '') {
            Log::error('PayMongo refund: secret key not configured.');
            return null;
        }

        if ($paymentId === '' || $amount <= 0) {
            Log::error('PayMongo refund: invalid arguments', [
                'payment_id' => $paymentId,
                'amount'     => $amount,
            ]);
            return null;
        }

        try {
            $response = Http::withBasicAuth($secret, '')
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->post(self::API_BASE . '/refunds', [
                    'data' => [
                        'attributes' => [
                            'amount'     => (int) round($amount * 100),
                            'payment_id' => $paymentId,
                            'reason'     => 'requested_by_customer',
                            'notes'      => $notes,
                            'metadata'   => ['booking_id' => $bookingId],
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::error('PayMongo refund API rejected request', [
                    'payment_id' => $paymentId,
                    'http'       => $response->status(),
                    'body'       => $response->json() ?? $response->body(),
                ]);
                return null;
            }

            $data   = $response->json('data');
            $refId  = $data['id'] ?? null;
            $status = $data['attributes']['status'] ?? null;

            if (! $refId) {
                Log::error('PayMongo refund response missing refund ID', ['response' => $data]);
                return null;
            }

            return [
                'id'     => (string) $refId,
                'status' => (string) ($status ?? 'pending'),
            ];
        } catch (Throwable $e) {
            Log::error('PayMongo refund threw', [
                'payment_id' => $paymentId,
                'error'      => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function findRefund(string $refundId): ?array
    {
        $secret = (string) config('paymongo.secret_key');

        if ($secret === '') {
            return null;
        }

        try {
            $response = Http::withBasicAuth($secret, '')
                ->acceptJson()
                ->timeout(10)
                ->get(self::API_BASE . "/refunds/{$refundId}");

            if ($response->failed()) {
                return null;
            }

            $data = $response->json('data');

            return [
                'id'     => $data['id'] ?? null,
                'status' => $data['attributes']['status'] ?? null,
            ];
        } catch (Throwable $e) {
            Log::warning('PayMongo refund lookup threw', [
                'refund_id' => $refundId,
                'error'     => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ═════════════════════════════════════════════════════════
    //  QR Ph (deprecated, retained)
    // ═════════════════════════════════════════════════════════

    public function createQrPhPaymentIntent(
        float $amount,
        string $description,
        array $metadata = [],
    ): ?array {
        try {
            $intent = $this->paymongo->paymentIntent()->create([
                'amount'                 => (int) round($amount),
                'currency'               => 'PHP',
                'payment_method_allowed' => ['qrph'],
                'description'            => $description,
                'statement_descriptor'   => 'Victorias Tourism',
                'metadata'               => $metadata,
            ]);

            $data = $intent->getData();

            $id        = data_get($data, 'id');
            $clientKey = data_get($data, 'client_key');

            if (! $id || ! $clientKey) {
                Log::error('PayMongo Payment Intent missing ID or client_key', [
                    'object' => $data,
                ]);
                return null;
            }

            return [
                'id'         => $id,
                'client_key' => $clientKey,
                'status'     => data_get($data, 'status'),
            ];
        } catch (Throwable $e) {
            Log::error('PayMongo QR Ph intent creation failed: ' . $e->getMessage());
            return null;
        }
    }

    public function attachQrPhPaymentMethod(
        string $paymentIntentId,
        string $clientKey,
        ?string $returnUrl = null,
        int $expirySeconds = 1800,
    ): ?array {
        try {
            $returnUrl = $returnUrl ?: route('tenant.bookings.index');

            $method = $this->paymongo->paymentMethod()->create([
                'type'           => 'qrph',
                'expiry_seconds' => $expirySeconds,
            ]);

            $methodId = data_get($method->getData(), 'id');

            if (! $methodId) {
                Log::error('PayMongo: QR Ph payment method creation returned no ID');
                return null;
            }

            $secretKey = (string) config('paymongo.secret_key');

            if ($secretKey === '') {
                Log::error('PayMongo: secret key is not configured. Check PAYMONGO_SECRET_KEY in .env');
                return null;
            }

            $response = Http::withBasicAuth($secretKey, '')
                ->acceptJson()
                ->asJson()
                ->post(self::API_BASE . "/payment_intents/{$paymentIntentId}/attach", [
                    'data' => [
                        'attributes' => [
                            'payment_method' => $methodId,
                            'client_key'     => $clientKey,
                            'return_url'     => $returnUrl,
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::error('PayMongo QR Ph attach failed (HTTP ' . $response->status() . ')', [
                    'intent_id'  => $paymentIntentId,
                    'return_url' => $returnUrl,
                    'body'       => $response->json() ?? $response->body(),
                ]);
                return null;
            }

            $attachedData = $response->json('data.attributes') ?? [];
            $qrImage      = data_get($attachedData, 'next_action.code.image_url')
                ?? data_get($attachedData, 'next_action.qr_code.image_url')
                ?? data_get($attachedData, 'next_action.code.data');

            if (! $qrImage) {
                Log::error('PayMongo: no QR image in attach response', [
                    'intent_id' => $paymentIntentId,
                    'response'  => $attachedData,
                ]);
                return null;
            }

            if (! str_starts_with($qrImage, 'data:')) {
                $qrImage = 'data:image/png;base64,' . $qrImage;
            }

            return [
                'payment_intent_id' => $paymentIntentId,
                'qr_image'          => $qrImage,
                'expires_at'        => now()->addSeconds($expirySeconds)->toIso8601String(),
            ];
        } catch (Throwable $e) {
            Log::error('PayMongo QR Ph attach failed: ' . $e->getMessage(), [
                'intent_id' => $paymentIntentId,
            ]);
            return null;
        }
    }

    public function getPaymentIntentStatus(string $paymentIntentId): ?string
    {
        try {
            $intent = $this->paymongo->paymentIntent()->find($paymentIntentId);

            return (string) data_get($intent->getData(), 'status');
        } catch (Throwable $e) {
            Log::error('PayMongo Payment Intent lookup failed: ' . $e->getMessage(), [
                'intent_id' => $paymentIntentId,
            ]);
            return null;
        }
    }

    // ═════════════════════════════════════════════════════════
    //  Payment finalization
    // ═════════════════════════════════════════════════════════

    public function processPayment(string $sessionId): void
    {
        try {
            $checkout = $this->paymongo->checkout()->find($sessionId);
        } catch (Throwable $e) {
            Log::error('PayMongo session not found: ' . $e->getMessage(), [
                'session_id' => $sessionId,
            ]);
            return;
        }

        $checkoutData   = $checkout->getData();
        $checkoutId     = data_get($checkoutData, 'id');
        $checkoutStatus = data_get($checkoutData, 'status');
        $payments       = data_get($checkoutData, 'payments', []);

        if (empty($payments)) {
            $raw = $this->fetchCheckoutSessionRaw($sessionId);
            if ($raw) {
                $checkoutStatus = $raw['attributes']['status']   ?? $checkoutStatus;
                $payments       = $raw['attributes']['payments'] ?? [];
            }
        }

        if ($this->checkoutSessionIsPaid($checkoutStatus, $payments)) {
            Log::info('PayMongo session confirmed paid — finalising', [
                'session_id'    => $sessionId,
                'status'        => $checkoutStatus,
                'payment_count' => is_array($payments) ? count($payments) : 0,
            ]);

            $paymongoPaymentId = $this->extractPaymongoPaymentId($payments);

            $this->finalizePayment($sessionId, $checkoutId, $paymongoPaymentId);
            return;
        }

        Log::info('PayMongo session not paid yet', [
            'session_id'       => $sessionId,
            'status'           => $checkoutStatus,
            'payment_count'    => is_array($payments) ? count($payments) : 0,
            'payment_statuses' => $this->extractPaymentStatuses($payments),
        ]);
    }

    public function finalizeCheckoutSession(string $sessionId): bool
    {
        if ($sessionId === '') {
            return false;
        }

        try {
            $payment = $this->findPaymentBySession($sessionId);

            if (! $payment) {
                Log::warning('finalizeCheckoutSession: no Payment row for session', [
                    'session_id' => $sessionId,
                ]);
                return false;
            }

            if ($payment->payment_status === 'paid') {
                return true;
            }

            // Resolve the payment ID from PayMongo before finalizing.
            // Without this, the payment row's paymongo_payment_id stays
            // null and refunds cannot be issued against it.
            $paymongoPaymentId = $payment->paymongo_payment_id
                ?: $this->resolvePaymentIdFromSession($sessionId);

            $this->finalizePayment($sessionId, $sessionId, $paymongoPaymentId);

            return true;
        } catch (Throwable $e) {
            Log::error('finalizeCheckoutSession failed', [
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function finalizeQrPayment(string $paymentIntentId): bool
    {
        $status = $this->getPaymentIntentStatus($paymentIntentId);

        if ($status !== 'succeeded') {
            return false;
        }

        $this->finalizePayment($paymentIntentId, $paymentIntentId);

        return true;
    }

    protected function finalizePayment(
        string $reference,
        ?string $externalId = null,
        ?string $paymongoPaymentId = null,
    ): void {
        // Backfill the payment ID if the caller didn't provide one.
        // The webhook fires before the payments array is always
        // populated; this closes that gap.
        if ($paymongoPaymentId === null && str_starts_with($reference, 'cs_')) {
            $paymongoPaymentId = $this->resolvePaymentIdFromSession($reference);
        }

        DB::transaction(function () use ($reference, $externalId, $paymongoPaymentId): void {
            $payment = Payment::withoutGlobalScope(TenantScope::class)
                ->where(function ($q) use ($reference): void {
                    $q->where('paymongo_session_id', $reference)
                      ->orWhere('reference_number', $reference);
                })
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                Log::warning('Payment record not found for reference', [
                    'reference' => $reference,
                ]);
                return;
            }

            if ($payment->payment_status === 'paid') {
                // Still backfill the payment ID if it's missing
                if ($paymongoPaymentId !== null && $payment->paymongo_payment_id === null) {
                    $payment->update(['paymongo_payment_id' => $paymongoPaymentId]);
                }
                return;
            }

            $payment->update([
                'payment_status'      => 'paid',
                'paid_at'             => now(),
                'reference_number'    => $externalId ?? $payment->reference_number,
                'paymongo_payment_id' => $paymongoPaymentId ?? $payment->paymongo_payment_id,
            ]);

            Transaction::create([
                'tenant_id'   => $payment->tenant_id,
                'booking_id'  => $payment->booking_id,
                'type'        => 'income',
                'amount'      => $payment->amount,
                'description' => 'PayMongo payment: ' . ($externalId ?? $reference),
            ]);

            /** @var Booking|null $booking */
            $booking = Booking::withoutGlobalScope(TenantScope::class)
                ->whereKey($payment->booking_id)
                ->lockForUpdate()
                ->first();

            if (! $booking) {
                return;
            }

            $totalPaid = (float) Payment::withoutGlobalScope(TenantScope::class)
                ->where('booking_id', $booking->id)
                ->where('payment_status', 'paid')
                ->sum('amount');

            if ($payment->payment_type === Payment::TYPE_RESERVATION) {
                $booking->update(['status' => Booking::STATUS_RESERVED]);
            } elseif ($totalPaid >= (float) $booking->total_amount) {
                $booking->update(['status' => Booking::STATUS_CONFIRMED]);
            }
        });
    }

    // ═════════════════════════════════════════════════════════
    //  Helpers
    // ═════════════════════════════════════════════════════════

    protected function extractPaymongoPaymentId(array $payments): ?string
    {
        foreach ($payments as $payment) {
            if (! is_array($payment)) {
                continue;
            }

            $status = $payment['attributes']['status'] ?? $payment['status'] ?? null;

            if (! is_string($status) || ! in_array($status, self::PAID_STATUSES, true)) {
                continue;
            }

            $id = $payment['id'] ?? null;

            if (is_string($id) && $id !== '') {
                return $id;
            }
        }

        return null;
    }

    protected function checkoutSessionIsPaid(?string $status, array $payments): bool
    {
        if (in_array($status, self::PAID_STATUSES, true)) {
            return true;
        }

        foreach ($this->extractPaymentStatuses($payments) as $ps) {
            if (in_array($ps, self::PAID_STATUSES, true)) {
                return true;
            }
        }

        return false;
    }

    protected function extractPaymentStatuses(array $payments): array
    {
        $out = [];

        foreach ($payments as $payment) {
            if (is_array($payment)) {
                $status = $payment['attributes']['status']
                    ?? $payment['status']
                    ?? null;

                if (is_string($status) && $status !== '') {
                    $out[] = $status;
                }
            }
        }

        return $out;
    }

    public function handlePaymentPaid(
        string $sessionId,
        int $retries = 3,
        int $delaySeconds = 2,
    ): bool {
        if ($sessionId === '') {
            Log::error('handlePaymentPaid called with empty session ID');
            return false;
        }

        for ($i = 0; $i < $retries; $i++) {
            try {
                $this->processPayment($sessionId);

                return true;
            } catch (Throwable $e) {
                if ($i < $retries - 1) {
                    sleep($delaySeconds);
                }
            }
        }

        return false;
    }

    public function checkPaymentStatus(string $sessionId): bool
    {
        try {
            $this->processPayment($sessionId);

            return true;
        } catch (Throwable $e) {
            Log::warning('PayMongo payment status check failed', [
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);
            return false;
        }
    }
}