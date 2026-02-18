<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletPin;
use App\Models\Order;
use App\Models\ExchangeRate;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        return $user;
    }

    private function stockistWithBalance(float $balance = 100): array
    {
        $user = User::factory()->create(['role' => User::ROLE_STOCKIST]);
        $service = new WalletService();
        $wallet = $service->getOrCreateWallet($user);
        $wallet->credit($balance, 'recharge');
        return [$user, $wallet->fresh()];
    }

    // --- GET /wallet/balance ---

    public function test_get_balance_returns_wallet(): void
    {
        [$user, $wallet] = $this->stockistWithBalance(150);

        $response = $this->actingAs($user)->getJson('/api/v1/wallet/balance');

        $response->assertOk()
            ->assertJsonPath('wallet.balance', '150.00')
            ->assertJsonPath('wallet.currency', 'USD');
    }

    public function test_get_balance_requires_auth(): void
    {
        $this->getJson('/api/v1/wallet/balance')->assertUnauthorized();
    }

    // --- GET /wallet/transactions ---

    public function test_get_transactions_paginated(): void
    {
        [$user, $wallet] = $this->stockistWithBalance(100);

        $response = $this->actingAs($user)->getJson('/api/v1/wallet/transactions');

        $response->assertOk()
            ->assertJsonStructure(['transactions' => ['data']]);
    }

    // --- POST /wallet/recharge ---

    public function test_admin_can_recharge_wallet(): void
    {
        $admin = $this->actingAsRole(User::ROLE_ADMIN);
        [$stockist, $wallet] = $this->stockistWithBalance(50);

        $response = $this->actingAs($admin)->postJson('/api/v1/wallet/recharge', [
            'wallet_id' => $wallet->id,
            'amount' => 200,
        ]);

        $response->assertOk()
            ->assertJsonPath('wallet.balance', '250.00');
    }

    public function test_stockist_cannot_recharge_wallet(): void
    {
        [$stockist, $wallet] = $this->stockistWithBalance(50);

        $response = $this->actingAs($stockist)->postJson('/api/v1/wallet/recharge', [
            'wallet_id' => $wallet->id,
            'amount' => 100,
        ]);

        $response->assertForbidden();
    }

    // --- POST /wallet/treasury/generate ---

    public function test_dev_can_generate_treasury(): void
    {
        $dev = $this->actingAsRole(User::ROLE_DEV);
        $admin = $this->actingAsRole(User::ROLE_ADMIN);

        $response = $this->actingAs($dev)->postJson('/api/v1/wallet/treasury/generate', [
            'amount' => 1000,
            'admin_user_id' => $admin->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('wallet.balance', '1000.00');
    }

    public function test_admin_cannot_generate_treasury(): void
    {
        $admin = $this->actingAsRole(User::ROLE_ADMIN);

        $response = $this->actingAs($admin)->postJson('/api/v1/wallet/treasury/generate', [
            'amount' => 1000,
            'admin_user_id' => $admin->id,
        ]);

        $response->assertForbidden();
    }

    // --- POST /wallet/pins/generate ---

    public function test_stockist_can_generate_pin(): void
    {
        [$user, $wallet] = $this->stockistWithBalance(100);

        $response = $this->actingAs($user)->postJson('/api/v1/wallet/pins/generate', [
            'amount' => 50,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['pin' => ['code', 'amount', 'expires_at', 'seller_id']])
            ->assertJsonPath('pin.amount', '50.00');

        $this->assertEquals(6, strlen($response->json('pin.code')));
    }

    public function test_consumer_cannot_generate_pin(): void
    {
        $consumer = $this->actingAsRole(User::ROLE_CONSUMER);

        $response = $this->actingAs($consumer)->postJson('/api/v1/wallet/pins/generate', [
            'amount' => 50,
        ]);

        $response->assertForbidden();
    }

    // --- POST /wallet/pins/validate ---

    public function test_validate_valid_pin(): void
    {
        [$user, $wallet] = $this->stockistWithBalance(100);
        $service = new WalletService();
        $pin = $service->generatePin($wallet, 50);

        $response = $this->actingAs($user)->postJson('/api/v1/wallet/pins/validate', [
            'seller_id' => $user->id,
            'pin_code' => $pin->code,
        ]);

        $response->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('pin.amount', '50.00');
    }

    public function test_validate_invalid_pin(): void
    {
        [$user, $wallet] = $this->stockistWithBalance(100);

        $response = $this->actingAs($user)->postJson('/api/v1/wallet/pins/validate', [
            'seller_id' => $user->id,
            'pin_code' => 'ZZZZZZ',
        ]);

        $response->assertNotFound()
            ->assertJsonPath('valid', false);
    }

    // --- POST /wallet/pins/redeem ---

    public function test_redeem_pin_success(): void
    {
        [$user, $wallet] = $this->stockistWithBalance(100);
        $service = new WalletService();
        $pin = $service->generatePin($wallet, 50);

        $order = Order::create([
            'tracking_code' => Order::generateTrackingCode(),
            'total_order_price' => 40,
            'payment_method' => 'wallet_pin',
            'order_status' => 'pending',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/wallet/pins/redeem', [
            'seller_id' => $user->id,
            'pin_code' => $pin->code,
            'order_id' => $order->id,
            'order_total' => 40,
        ]);

        $response->assertOk()
            ->assertJsonPath('pin.status', 'used')
            ->assertJsonPath('pin.amount_used', '40.00');
    }

    // --- GET /wallet/pins/history ---

    public function test_pin_history_returns_user_pins(): void
    {
        [$user, $wallet] = $this->stockistWithBalance(100);
        $service = new WalletService();
        $service->generatePin($wallet, 10);
        $service->generatePin($wallet, 20);

        $response = $this->actingAs($user)->getJson('/api/v1/wallet/pins/history');

        $response->assertOk()
            ->assertJsonCount(2, 'pins.data');
    }

    // --- GET /wallet/sellers ---

    public function test_sellers_list_public(): void
    {
        User::factory()->create([
            'role' => User::ROLE_STOCKIST,
            'is_wallet_seller' => true,
        ]);
        User::factory()->create([
            'role' => User::ROLE_STOCKIST,
            'is_wallet_seller' => false,
        ]);

        $response = $this->getJson('/api/v1/wallet/sellers');

        $response->assertOk()
            ->assertJsonCount(1, 'sellers');
    }

    // --- GET /wallet/all --- Admin

    public function test_admin_can_list_all_wallets(): void
    {
        $admin = $this->actingAsRole(User::ROLE_ADMIN);
        $this->stockistWithBalance(100);

        $response = $this->actingAs($admin)->getJson('/api/v1/wallet/all');

        $response->assertOk()
            ->assertJsonStructure(['wallets' => ['data']]);
    }

    // --- Exchange Rates ---

    public function test_get_exchange_rates_public(): void
    {
        $admin = $this->actingAsRole(User::ROLE_ADMIN);
        ExchangeRate::create([
            'from_currency' => 'XAF',
            'to_currency' => 'USD',
            'rate' => 0.0016,
            'set_by' => $admin->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/exchange-rates');

        $response->assertOk()
            ->assertJsonCount(1, 'rates');
    }

    public function test_admin_can_set_exchange_rate(): void
    {
        $admin = $this->actingAsRole(User::ROLE_ADMIN);

        $response = $this->actingAs($admin)->postJson('/api/v1/exchange-rates', [
            'from_currency' => 'XAF',
            'rate' => 0.0016,
        ]);

        $response->assertCreated()
            ->assertJsonPath('rate.from_currency', 'XAF')
            ->assertJsonPath('rate.to_currency', 'USD');
    }

    public function test_consumer_cannot_set_exchange_rate(): void
    {
        $consumer = $this->actingAsRole(User::ROLE_CONSUMER);

        $response = $this->actingAs($consumer)->postJson('/api/v1/exchange-rates', [
            'from_currency' => 'XAF',
            'rate' => 0.0016,
        ]);

        $response->assertForbidden();
    }
}
