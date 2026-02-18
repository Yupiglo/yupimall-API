<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletPin;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WalletService
{
    /**
     * Get or lazily create a wallet for a user.
     */
    public function getOrCreateWallet(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            ['owner_type' => 'user', 'owner_id' => $user->id],
            ['balance' => 0, 'currency' => 'USD']
        );
    }

    /**
     * Generate a 6-char alphanumeric PIN, debiting the seller's wallet.
     * Returns the PIN model with the plaintext code.
     */
    public function generatePin(Wallet $wallet, float $amount): WalletPin
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Le montant doit être supérieur à 0.');
        }

        if (!$wallet->hasSufficientBalance($amount)) {
            throw new \RuntimeException('Solde insuffisant pour générer ce PIN.');
        }

        return DB::transaction(function () use ($wallet, $amount) {
            $code = $this->generateUniqueCode();

            $wallet->debit($amount, 'pin_generation', null, "Génération PIN {$code}");

            $pin = WalletPin::create([
                'code' => $code,
                'seller_wallet_id' => $wallet->id,
                'seller_id' => $wallet->owner_id,
                'amount' => $amount,
                'amount_used' => 0,
                'status' => 'active',
                'expires_at' => Carbon::now()->addMinutes(15),
            ]);

            $pin->getConnection()->table('wallet_transactions')
                ->where('wallet_id', $wallet->id)
                ->where('reference_type', 'pin_generation')
                ->whereNull('reference_id')
                ->latest('id')
                ->limit(1)
                ->update(['reference_id' => $pin->id]);

            Log::info("PIN {$code} generated for wallet #{$wallet->id}, amount: \${$amount}");

            return $pin;
        });
    }

    /**
     * Validate a PIN without consuming it.
     * Returns the PIN if valid, null otherwise.
     */
    public function validatePin(int $sellerId, string $pinCode): ?WalletPin
    {
        $pin = WalletPin::where('code', strtoupper($pinCode))
            ->where('seller_id', $sellerId)
            ->first();

        if (!$pin) {
            return null;
        }

        if ($pin->status !== 'active') {
            return null;
        }

        if ($pin->isExpired()) {
            return null;
        }

        return $pin;
    }

    /**
     * Redeem a PIN to pay for an order.
     * Marks PIN as used, refunds remainder to seller if order total < PIN amount.
     */
    public function redeemPin(int $sellerId, string $pinCode, int $orderId, float $orderTotal): WalletPin
    {
        $pin = $this->validatePin($sellerId, $pinCode);

        if (!$pin) {
            throw new \RuntimeException('PIN invalide, expiré ou déjà utilisé.');
        }

        if ($orderTotal > (float) $pin->amount) {
            throw new \RuntimeException('Le montant de la commande dépasse le montant du PIN.');
        }

        return DB::transaction(function () use ($pin, $orderId, $orderTotal) {
            $amountUsed = $orderTotal;
            $pin->markAsUsed($orderId, $amountUsed);

            $remainder = $pin->getRemainder();
            if ($remainder > 0) {
                $pin->sellerWallet->refund(
                    $remainder,
                    'pin_remainder',
                    $pin->id,
                    "Remboursement reste PIN {$pin->code} (commande #{$orderId})"
                );
            }

            Log::info("PIN {$pin->code} redeemed for order #{$orderId}, used: \${$amountUsed}, refunded: \${$remainder}");

            return $pin->fresh();
        });
    }

    /**
     * Expire stale PINs and refund the full amount to seller wallets.
     * Called by the scheduler every minute.
     */
    public function expireStalePins(): int
    {
        $expiredPins = WalletPin::expired()->get();
        $count = 0;

        foreach ($expiredPins as $pin) {
            DB::transaction(function () use ($pin) {
                $pin->update(['status' => 'expired']);

                $pin->sellerWallet->refund(
                    (float) $pin->amount,
                    'pin_expiry',
                    $pin->id,
                    "Remboursement PIN expiré {$pin->code}"
                );

                Log::info("PIN {$pin->code} expired, refunded \${$pin->amount} to wallet #{$pin->seller_wallet_id}");
            });
            $count++;
        }

        return $count;
    }

    /**
     * Admin recharges a user's wallet.
     */
    public function rechargeWallet(int $walletId, float $amount, int $adminId): Wallet
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Le montant doit être supérieur à 0.');
        }

        $wallet = Wallet::findOrFail($walletId);

        $wallet->credit(
            $amount,
            'recharge',
            null,
            "Recharge par admin #{$adminId}"
        );

        Log::info("Wallet #{$walletId} recharged with \${$amount} by admin #{$adminId}");

        return $wallet->fresh();
    }

    /**
     * Dev generates treasury dollars and credits the admin wallet.
     */
    public function generateTreasury(float $amount, int $devId, int $adminUserId): Wallet
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Le montant doit être supérieur à 0.');
        }

        $adminUser = User::findOrFail($adminUserId);
        $wallet = $this->getOrCreateWallet($adminUser);

        $wallet->credit(
            $amount,
            'treasury',
            null,
            "Trésorerie générée par dev #{$devId}"
        );

        Log::info("Treasury \${$amount} generated by dev #{$devId} for admin #{$adminUserId}");

        return $wallet->fresh();
    }

    /**
     * Check if a user is allowed to make purchases (DEV role is blocked).
     */
    public function checkPurchaseAllowance(User $user): bool
    {
        return $user->role !== User::ROLE_DEV;
    }

    /**
     * Generate a unique 6-character alphanumeric code.
     */
    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (WalletPin::where('code', $code)->exists());

        return $code;
    }
}
