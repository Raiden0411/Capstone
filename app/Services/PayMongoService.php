<?php

namespace App\Services;

use Luigel\Paymongo\Facades\Paymongo;
use Illuminate\Support\Facades\Log;

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
                    'name' => $data['customer_name'],
                    'email' => $data['customer_email'],
                    'phone' => $data['customer_phone'] ?? null,
                ],
                'line_items' => [[
                    'currency' => 'PHP',
                    'amount' => (int) ($data['amount'] * 100), // centavos
                    'description' => $data['description'],
                    'name' => $data['item_name'] ?? 'Booking Payment',
                    'quantity' => 1,
                ]],
                'payment_method_types' => $data['payment_method_types'] ?? ['card', 'gcash', 'paymaya'],
                'success_url' => $data['success_url'],
                'cancel_url' => $data['cancel_url'],
                'metadata' => $data['metadata'] ?? [],
            ]);

            $checkoutData = method_exists($checkout, 'getData')
                ? $checkout->getData()
                : [];

            return [
                'id'           => $checkoutData['id'] ?? null,
                'checkout_url' => $checkoutData['checkout_url'] ?? null,
                'status'       => $checkoutData['status'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('PayMongo Checkout Error: ' . $e->getMessage());
            return null;
        }
    }
}