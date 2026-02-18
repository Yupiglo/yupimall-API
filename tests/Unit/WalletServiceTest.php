<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletPin;
use App\Models\Order;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WalletService();
    }

    private function createStockist(float $balance = 100): array
    {
        $user = User::factory()->create(['role' => User::ROLE_STOCKIST]);
        $wallet = $this->service->getOrCreateWallet($user);
        if ($balance > 0) {
            $wallet->credit($balance, 'recharge', null, 'Initial balance');
        }
        return [$user, $wallet->fresh()];
    }

    // --- getOrCreateWallet ---

    public function test_get_or_create_wallet_creates_new_wallet(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_STOCKIST]);

        $wallet = $this->service->getOrCreateWallet($user);

        $this->assertNotNull($wallet);
        $this->assertEquals(0, $wallet->balance);
        $this->assertEquals('USD', $wallet->currency);
        $this->assertEquals($user->id, $wallet->owner_id);
    }

    public function test_get_or_create_wallet_returns_existing(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_STOCKIST]);

        $wallet1 = $this->service->getOrCreateWallet($user);
        $wallet2 = $this->service->getOrCreateWallet($user);

        $this->assertEquals($wallet1->id, $wallet2->id);
    }

    // --- generatePin ---

    public function test_generate_pin_debits_wallet(): void
    {
        [$user, $wallet] = $this->createStockist(100);

        $pin = $this->service->generatePin($wallet, 30);

        $this->assertNotNull($pin);
        $this->assertEquals(6, strlen($pin->code));
        $this->assertEquals(30, $pin->amount);
        $this->assertEquals('active', $pin->status);
        $this->assertNotNull($pin->expires_at);
        $this->assertEquals(70.00, (float) $wallet->fresh()->balance);
    }

    public function test_generate_pin_creates_transaction(): void
    {
        [$user, $wallet] = $this->createStockist(100);

        $pin = $this->service->generatePin($wallet, 25);

        $transaction = $wallet->transactions()->where('reference_type', 'pin_generation')->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('debit', $transaction->type);
        $this->assertEquals(25, (float) $transaction->amount);
        $this->assertEquals(100, (float) $transaction->balance_before);
        $this->assertEquals(75, (float) $transaction->balance_after);
    }

    public function test_generate_pin_fails_insufficient_balance(): void
    {
        [$user, $wallet] = $this->createStockist(10);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Solde insuffisant');

        $this->service->generatePin($wallet, 50);
    }

    public function test_generate_pin_fails_zero_amount(): void
    {
        [$user, $wallet] = $this->createStockist(100);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->generatePin($wallet, 0);
    }

    public function test_generate_pin_sets_15_minute_expiry(): void
    {
        [$user, $wallet] = $this->createStockist(100);

        Carbon::setTestNow(Carbon::create(2026, 2, 18, 12, 0, 0));
        $pin = $this->service->generatePin($wallet, 10);

        $this->assertTrue($pin->expires_at->equalTo(Carbon::create(2026, 2, 18, 12, 15, 0)));

        Carbon::setTestNow();
    }

    // --- validatePin ---

    public function test_validate_pin_returns_valid_pin(): void
    {
        [$user, $wallet] = $this->createStockist(100);
        $pin = $this->service->generatePin($wallet, 50);

        $result = $this->service->validatePin($user->id, $pin->code);

        $this->assertNotNull($result);
        $this->assertEquals($pin->id, $result->id);
    }

    public function test_validate_pin_returns_null_for_wrong_code(): void
    {
        [$user, $wallet] = $this->createStockist(100);

        $result = $this->service->validatePin($user->id, 'XXXXXX');

        $this->assertNull($result);
    }

    public function test_validate_pin_returns_null_for_expired(): void
    {
        [$user, $wallet] = $this->createStockist(100);

        Carbon::setTestNow(Carbon::now()->subMinutes(20));
        $pin = $this->service->generatePin($wallet, 20);
        Carbon::setTestNow();

        $result = $this->service->validatePin($user->id, $pin->code);

        $this->assertNull($result);
    }

    public function test_validate_pin_returns_null_for_used(): void
    {
        [$user, $wallet] = $this->createStockist(100);
        $pin = $this->service->generatePin($wallet, 50);

        $order = Order::create([
            'tracking_code' => Order::generateTrackingCode(),
            'total_order_price' => 40,
            'payment_method' => 'wallet_pin',
            'order_status' => 'pending',
        ]);
        $this->service->redeemPin($user->id, $pin->code, $order->id, 40);

        $result = $this->service->validatePin($user->id, $pin->code);

        $this->assertNull($result);
    }

    // --- redeemPin ---

    public function test_redeem_pin_marks_as_used(): void
    {
        [$user, $wallet] = $this->createStockist(100);
        $pin = $this->service->generatePin($wallet, 50);

        $order = Order::create([
            'tracking_code' => Order::generateTrackingCode(),
            'total_order_price' => 40,
            'payment_method' => 'wallet_pin',
            'order_status' => 'pending',
        ]);

        $redeemed = $this->service->redeemPin($user->id, $pin->code, $order->id, 40);

        $this->assertEquals('used', $redeemed->status);
        $this->assertEquals(40, (float) $redeemed->amount_used);
        $this->assertEquals($order->id, $redeemed->used_by_order_id);
        $this->assertNotNull($redeemed->used_at);
    }

    public function test_redeem_pin_refunds_remainder(): void
    {
        [$user, $wallet] = $this->createStockist(100);
        $pin = $this->service->generatePin($wallet, 50);

        $order = Order::create([
            'tracking_code' => Order::generateTrackingCode(),
            'total_order_price' => 30,
            'payment_method' => 'wallet_pin',
            'order_status' => 'pending',
        ]);

        $this->service->redeemPin($user->id, $pin->code, $order->id, 30);

        // Started 100, debited 50 (=50), refunded 20 remainder (=70)
        $this->assertEquals(70.00, (float) $wallet->fresh()->balance);
    }

    public function test_redeem_pin_no_refund_exact_amount(): void
    {
        [$user, $wallet] = $this->createStockist(100);
        $pin = $this->service->generatePin($wallet, 50);

        $order = Order::create([
            'tracking_code' => Order::generateTrackingCode(),
            'total_order_price' => 50,
            'payment_method' => 'wallet_pin',
            'order_status' => 'pending',
        ]);

        $this->service->redeemPin($user->id, $pin->code, $order->id, 50);

        // Started 100, debited 50 (=50), no remainder
        $this->assertEquals(50.00, (float) $wallet->fresh()->balance);
    }

    public function test_redeem_pin_fails_order_exceeds_amount(): void
    {
        [$user, $wallet] = $this->createStockist(100);
        $pin = $this->service->generatePin($wallet, 30);

        $order = Order::create([
            'tracking_code' => Order::generateTrackingCode(),
            'total_order_price' => 50,
            'payment_method' => 'wallet_pin',
            'order_status' => 'pending',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('montant de la commande dépasse');

        $this->service->redeemPin($user->id, $pin->code, $order->id, 50);
    }

    // --- expireStalePins ---

    public function test_expire_stale_pins_refunds_wallet(): void
    {
        [$user, $wallet] = $this->createStockist(100);

        Carbon::setTestNow(Carbon::now()->subMinutes(20));
        $pin = $this->service->generatePin($wallet, 40);
        Carbon::setTestNow();

        $count = $this->service->expireStalePins();

        $this->assertEquals(1, $count);
        $this->assertEquals('expired', $pin->fresh()->status);
        // Started 100, debited 40 (=60), refunded 40 (=100)
        $this->assertEquals(100.00, (float) $wallet->fresh()->balance);
    }

    public function test_expire_stale_pins_skips_active(): void
    {
        [$user, $wallet] = $this->createStockist(100);
        $this->service->generatePin($wallet, 20);

        $count = $this->service->expireStalePins();

        $this->assertEquals(0, $count);
    }

    // --- rechargeWallet ---

    public function test_recharge_wallet_credits_balance(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_STOCKIST]);
        $wallet = $this->service->getOrCreateWallet($user);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $recharged = $this->service->rechargeWallet($wallet->id, 200, $admin->id);

        $this->assertEquals(200.00, (float) $recharged->balance);
    }

    public function test_recharge_fails_zero_amount(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_STOCKIST]);
        $wallet = $this->service->getOrCreateWallet($user);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->rechargeWallet($wallet->id, 0, $admin->id);
    }

    // --- generateTreasury ---

    public function test_generate_treasury_credits_admin_wallet(): void
    {
        $dev = User::factory()->create(['role' => User::ROLE_DEV]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $wallet = $this->service->generateTreasury(500, $dev->id, $admin->id);

        $this->assertEquals(500.00, (float) $wallet->balance);
        $this->assertEquals($admin->id, $wallet->owner_id);
    }

    // --- checkPurchaseAllowance ---

    public function test_dev_cannot_purchase(): void
    {
        $dev = User::factory()->create(['role' => User::ROLE_DEV]);

        $this->assertFalse($this->service->checkPurchaseAllowance($dev));
    }

    public function test_stockist_can_purchase(): void
    {
        $stockist = User::factory()->create(['role' => User::ROLE_STOCKIST]);

        $this->assertTrue($this->service->checkPurchaseAllowance($stockist));
    }

    public function test_member_can_purchase(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);

        $this->assertTrue($this->service->checkPurchaseAllowance($member));
    }
}
