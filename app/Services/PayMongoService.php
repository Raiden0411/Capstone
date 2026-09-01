<?php

namespace App\Services;

use Luigel\Paymongo\Facades\Paymongo;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessPayMongoPayment;

class PayMongoService
{
    /**
     * Create a checkout session.
     */
    public function createCheckoutSession(array $data): ?array
    {
        try {
            $checkout = Paymongo::checkout()->create([
                'billing' => [
                    'name'  => $data['customer_name'],
                    'email' => $data['customer_email'],
                    'phone' => $data['customer_phone'] ?? null,
                ],
                'line_items' => [[
                    'currency'    => 'PHP',
                    'amount'      => (int) ($data['amount'] * 100), // centavos
                    'description' => $data['description'],
                    'name'        => $data['item_name'] ?? 'Booking Payment',
                    'quantity'    => 1,
                ]],
                'payment_method_types' => $data['payment_method_types'] ?? ['card', 'gcash', 'paymaya'],
                'success_url'          => $data['success_url'],
                'cancel_url'           => $data['cancel_url'],
                'metadata'             => $data['metadata'] ?? [],
            ]);

            // Extract ID and checkout URL using multiple fallbacks
            $checkoutId = $checkout->id
                ?? (method_exists($checkout, 'getId') ? $checkout->getId() : null)
                ?? data_get($checkout, 'id')
                ?? (method_exists($checkout, 'getData') ? data_get($checkout->getData(), 'id') : null);

            $checkoutUrl = $checkout->checkout_url
                ?? (method_exists($checkout, 'getCheckoutUrl') ? $checkout->getCheckoutUrl() : null)
                ?? data_get($checkout, 'checkout_url')
                ?? (method_exists($checkout, 'getData') ? data_get($checkout->getData(), 'checkout_url') : null);

            if (!$checkoutId || !$checkoutUrl) {
                Log::error('PayMongo Checkout missing ID or URL', [
                    'object' => method_exists($checkout, 'getData') ? $checkout->getData() : get_object_vars($checkout),
                ]);
                return null;
            }

            return [
                'id'           => $checkoutId,
                'checkout_url' => $checkoutUrl,
                'status'       => $checkout->status ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('PayMongo Checkout Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Process payment with retry for active sessions.
     */
    public function handlePaymentPaid(string $sessionId, int $retries = 5): bool
    {
        if (!$sessionId) {
            Log::error('handlePaymentPaid called with empty session ID');
            return false;
        }

        for ($i = 0; $i < $retries; $i++) {
            $job = new ProcessPayMongoPayment($sessionId);
            $result = $job->handle();

            if ($result) {
                return true;
            }

            if ($i < $retries - 1) {
                sleep(3); // wait before retrying
            }
        }

        return false;
    }

    /**
     * Check payment status once (used by processing page polling).
     */
    public function checkPaymentStatus(string $sessionId): bool
    {
        $job = new ProcessPayMongoPayment($sessionId);
        return $job->handle();
    }
}