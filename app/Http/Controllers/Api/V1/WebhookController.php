<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function moneroo(Request $request)
    {
        Log::info('Moneroo Webhook received', $request->all());

        $data = $request->input('data');
        $status = $data['status'] ?? '';
        $orderId = $data['metadata']['order_id'] ?? null;

        if ($status === 'success' && $orderId) {
            $this->markAsPaid($orderId);
        }

        return response()->json(['message' => 'Processed'], 200);
    }

    public function axazara(Request $request)
    {
        Log::info('Axa Zara Webhook received', $request->all());

        $status = $request->input('status');
        $orderId = $request->input('order_id');

        if ($status === 'PAID' && $orderId) {
            $this->markAsPaid($orderId);
        }

        return response()->json(['message' => 'Processed'], 200);
    }

    private function markAsPaid($orderId)
    {
        $order = Order::find($orderId);
        if ($order) {
            $order->update([
                'is_paid' => true,
                'paid_at' => now(),
            ]);
            Log::info("Order {$orderId} marked as paid.");

            // TODO: Trigger MLM commission here
            // $this->triggerMLM($order);
        }
    }
}
