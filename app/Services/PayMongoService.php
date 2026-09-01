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

            $checkoutData = $checkout->getData();

            $checkoutId  = data_get($checkoutData, 'id');
            $checkoutUrl = data_get($checkoutData, 'checkout_url');
            $status      = data_get($checkoutData, 'status');

            if (!$checkoutId || !$checkoutUrl) {
                Log::error('PayMongo Checkout missing ID or URL', [
                    'object' => $checkoutData,
                ]);
                return null;
            }

            return [
                'id'           => $checkoutId,
                'checkout_url' => $checkoutUrl,
                'status'       => $status,
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