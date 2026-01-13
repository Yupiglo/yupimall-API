<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MlmService
{
    /**
     * Notify the external MLM system that an order has been paid.
     */
    public function notifyOrderPaid(Order $order)
    {
        Log::info("Notifying MLM system for order {$order->id}");

        $payload = [
            'event' => 'order.paid',
            'order_id' => $order->id,
            'total_amount' => $order->total_order_price,
            'distributor_id' => $order->distributor_id ?? 'direct_yupi', // Plan requirement: default attribution
            'customer_email' => $order->shipping_email,
            'timestamp' => now()->toIso8601String(),
        ];

        // In a real scenario, we would send this to the MLM API
        // For V1, we log it and prepare the structure for the bridge
        Log::info("MLM Payload:", $payload);

        // Example of what the future request would look like:
        /*
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.mlm.token'),
            ])->post(config('services.mlm.api_url') . '/webhooks/order-paid', $payload);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Failed to notify MLM system: " . $e->getMessage());
            return false;
        }
        */

        return true;
    }
}
