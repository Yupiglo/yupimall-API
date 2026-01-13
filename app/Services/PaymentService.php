<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /**
     * Initialize a payment transaction for external gateways.
     */
    public function initializePayment(Order $order)
    {
        $method = $order->payment_method;

        Log::info("Initializing payment for order {$order->id} via method: {$method}");

        switch ($method) {
            case 'card':
            case 'stripe':
                return $this->initStripe($order);
            case 'paypal':
                return $this->initPayPal($order);
            case 'yupi_wallet':
                return $this->initWallet($order);
            case 'mobile_money':
                return $this->initMobileMoney($order);
            default:
                return null; // For manual methods
        }
    }

    private function initStripe(Order $order)
    {
        // Mocking Stripe Checkout session creation
        $checkoutUrl = "https://checkout.stripe.com/pay/" . bin2hex(random_bytes(10)) . "?order_id=" . $order->id;
        Log::info("Stripe checkout link generated: {$checkoutUrl}");
        return $checkoutUrl;
    }

    private function initPayPal(Order $order)
    {
        // Mocking PayPal checkout URL
        $checkoutUrl = "https://www.paypal.com/checkoutnow?token=" . bin2hex(random_bytes(10));
        Log::info("PayPal link generated: {$checkoutUrl}");
        return $checkoutUrl;
    }

    private function initMobileMoney(Order $order)
    {
        // Mobile Money redirection (often via a gateway like Moneroo or internal aggregator)
        $checkoutUrl = "https://checkout.moneroo.io/pay/mobile/" . bin2hex(random_bytes(10));
        Log::info("Mobile Money Gateway link: {$checkoutUrl}");
        return $checkoutUrl;
    }

    private function initWallet(Order $order)
    {
        // Simulation de redirection vers le panel Yupi Wallet pour confirmation
        Log::info("Wallet payment initiated for order {$order->id}");
        return "/payment/wallet-confirm?order_id=" . $order->id;
    }
}
