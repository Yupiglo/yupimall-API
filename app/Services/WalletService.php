<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Registration;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletPin;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WalletService
{
    public function getOrCreateWallet(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            ['owner_type' => 'user', 'owner_id' => $user->id],
            ['balance' => 0, 'currency' => 'USD']
        );
    }

    /**
     * Generate a 6-char PIN, debiting the seller's wallet atomically.
     */
    public function generatePin(Wallet $wallet, float $amount): WalletPin
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Le montant doit être supérieur à 0.');
        }

        return DB::transaction(function () use ($wallet, $amount) {
            $locked = Wallet::lockForUpdate()->find($wallet->id);

            if ($locked->balance < $amount) {
                throw new \RuntimeException('Solde insuffisant pour générer ce PIN.');
            }

            $code = $this->generateUniqueCode();

            $locked->debit($amount, 'pin_generation', null, "Génération PIN {$code}");

            $pin = WalletPin::create([
                'code' => $code,
                'seller_wallet_id' => $locked->id,
                'seller_id' => $locked->owner_id,
                'amount' => $amount,
                'amount_used' => 0,
                'status' => 'active',
                'expires_at' => Carbon::now()->addMinutes(15),
            ]);

            $pin->getConnection()->table('wallet_transactions')
                ->where('wallet_id', $locked->id)
                ->where('reference_type', 'pin_generation')
                ->whereNull('reference_id')
                ->latest('id')
                ->limit(1)
                ->update(['reference_id' => $pin->id]);

            Log::info("PIN {$code} generated for wallet #{$locked->id}, amount: \${$amount}");

            return $pin;
        });
    }

    /**
     * Validate a PIN without consuming it (read-only check).
     */
    public function validatePin(int $sellerId, string $pinCode): ?WalletPin
    {
        $pin = WalletPin::where('code', strtoupper($pinCode))
            ->where('seller_id', $sellerId)
            ->first();

        if (!$pin || $pin->status !== 'active' || $pin->isExpired()) {
            return null;
        }

        return $pin;
    }

    /**
     * Redeem a PIN atomically: lock PIN row, mark used, update order, refund remainder.
     */
    public function redeemPin(int $sellerId, string $pinCode, int $orderId, float $orderTotal): WalletPin
    {
        return DB::transaction(function () use ($sellerId, $pinCode, $orderId, $orderTotal) {
            $pin = WalletPin::where('code', strtoupper($pinCode))
                ->where('seller_id', $sellerId)
                ->lockForUpdate()
                ->first();

            if (!$pin || $pin->status !== 'active' || $pin->isExpired()) {
                throw new \RuntimeException('PIN invalide, expiré ou déjà utilisé.');
            }

            if ($orderTotal > (float) $pin->amount) {
                throw new \RuntimeException('Le montant de la commande dépasse le montant du PIN.');
            }

            $amountUsed = $orderTotal;
            $pin->markAsUsed($orderId, $amountUsed);

            Order::where('id', $orderId)->update(['wallet_pin_id' => $pin->id]);

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
     * Redeem a PIN for a registration atomically: lock PIN row, mark used, update registration, refund remainder.
     */
    public function redeemPinForRegistration(int $sellerId, string $pinCode, int $registrationId, float $registrationTotal): WalletPin
    {
        return DB::transaction(function () use ($sellerId, $pinCode, $registrationId, $registrationTotal) {
            $pin = WalletPin::where('code', strtoupper($pinCode))
                ->where('seller_id', $sellerId)
                ->lockForUpdate()
                ->first();

            if (!$pin || $pin->status !== 'active' || $pin->isExpired()) {
                throw new \RuntimeException('PIN invalide, expiré ou déjà utilisé.');
            }

            if ($registrationTotal > (float) $pin->amount) {
                throw new \RuntimeException('Le montant de l\'inscription dépasse le montant du PIN.');
            }

            $amountUsed = $registrationTotal;
            $pin->markAsUsedForRegistration($registrationId, $amountUsed);

            Registration::where('id', $registrationId)->update([
                'wallet_pin_id' => $pin->id,
                'payment_status' => 'paid'
            ]);

            $remainder = $pin->getRemainder();
            if ($remainder > 0) {
                $pin->sellerWallet->refund(
                    $remainder,
                    'pin_remainder',
                    $pin->id,
                    "Remboursement reste PIN {$pin->code} (inscription #{$registrationId})"
                );
            }

            Log::info("PIN {$pin->code} redeemed for registration #{$registrationId}, used: \${$amountUsed}, refunded: \${$remainder}");

            return $pin->fresh();
        });
    }

    /**
     * Manually refund a PIN that couldn't be used (admin action).
     * Returns the amount to the seller's wallet so they can generate a new PIN.
     * Only works for PINs that are active or expired (never used).
     */
    public function refundPin(WalletPin $pin, int $adminId, string $reason = ''): WalletPin
    {
        if ($pin->status === 'refunded') {
            throw new \RuntimeException('Ce PIN a déjà été remboursé.');
        }
        if ($pin->status === 'used') {
            throw new \RuntimeException('Ce PIN a déjà été utilisé. Le reste éventuel a été remboursé automatiquement.');
        }
        if ($pin->status !== 'active' && $pin->status !== 'expired') {
            throw new \RuntimeException('Ce PIN n\'est pas éligible au remboursement manuel.');
        }

        $amountToRefund = (float) $pin->amount;
        if ($amountToRefund <= 0) {
            throw new \RuntimeException('Montant du PIN invalide.');
        }

        return DB::transaction(function () use ($pin, $amountToRefund, $adminId, $reason) {
            $locked = WalletPin::lockForUpdate()->find($pin->id);

            if ($locked->status === 'refunded') {
                throw new \RuntimeException('Ce PIN a déjà été remboursé.');
            }

            $locked->sellerWallet->refund(
                $amountToRefund,
                'pin_manual_refund',
                $locked->id,
                "Remboursement manuel PIN {$locked->code} (admin #{$adminId})" . ($reason ? " - {$reason}" : '')
            );

            $locked->update(['status' => 'refunded']);

            Log::info("PIN {$locked->code} manually refunded \${$amountToRefund} to wallet #{$locked->seller_wallet_id} by admin #{$adminId}");

            return $locked->fresh();
        });
    }

    /**
     * Expire stale PINs atomically and refund full amount to sellers.
     */
    public function expireStalePins(): int
    {
        $count = 0;

        $expiredPinIds = WalletPin::expired()->pluck('id');

        foreach ($expiredPinIds as $pinId) {
            try {
                DB::transaction(function () use ($pinId) {
                    $pin = WalletPin::lockForUpdate()->find($pinId);

                    if (!$pin || $pin->status !== 'active') {
                        return;
                    }

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
            } catch (\Throwable $e) {
                Log::error("Failed to expire PIN #{$pinId}: {$e->getMessage()}");
            }
        }

        return $count;
    }

    /**
     * Admin recharges a wallet.
     */
    public function rechargeWallet(int $walletId, float $amount, int $adminId): Wallet
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Le montant doit être supérieur à 0.');
        }

        $wallet = Wallet::findOrFail($walletId);

        $wallet->credit($amount, 'recharge', null, "Recharge par admin #{$adminId}");

        Log::info("Wallet #{$walletId} recharged with \${$amount} by admin #{$adminId}");

        return $wallet->fresh();
    }

    /**
     * Dev generates treasury dollars for an admin wallet.
     */
    public function generateTreasury(float $amount, int $devId, int $adminUserId): Wallet
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Le montant doit être supérieur à 0.');
        }

        $adminUser = User::findOrFail($adminUserId);
        $wallet = $this->getOrCreateWallet($adminUser);

        $wallet->credit($amount, 'treasury', null, "Trésorerie générée par dev #{$devId}");

        Log::info("Treasury \${$amount} generated by dev #{$devId} for admin #{$adminUserId}");

        return $wallet->fresh();
    }

    public function checkPurchaseAllowance(User $user): bool
    {
        return $user->role !== User::ROLE_DEV;
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (WalletPin::where('code', $code)->exists());

        return $code;
    }
}
