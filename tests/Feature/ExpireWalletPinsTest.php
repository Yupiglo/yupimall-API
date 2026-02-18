<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WalletPin;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class ExpireWalletPinsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_expires_stale_pins(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_STOCKIST]);
        $service = new WalletService();
        $wallet = $service->getOrCreateWallet($user);
        $wallet->credit(100, 'recharge');

        Carbon::setTestNow(Carbon::now()->subMinutes(20));
        $pin = $service->generatePin($wallet->fresh(), 40);
        Carbon::setTestNow();

        $this->artisan('wallet:expire-pins')
            ->expectsOutputToContain('Expired 1 PIN(s)')
            ->assertExitCode(0);

        $this->assertEquals('expired', $pin->fresh()->status);
        $this->assertEquals(100.00, (float) $wallet->fresh()->balance);
    }

    public function test_command_handles_no_expired_pins(): void
    {
        $this->artisan('wallet:expire-pins')
            ->expectsOutputToContain('No expired PINs found')
            ->assertExitCode(0);
    }

    public function test_command_does_not_expire_active_pins(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_STOCKIST]);
        $service = new WalletService();
        $wallet = $service->getOrCreateWallet($user);
        $wallet->credit(100, 'recharge');

        $pin = $service->generatePin($wallet->fresh(), 30);

        $this->artisan('wallet:expire-pins')
            ->expectsOutputToContain('No expired PINs found')
            ->assertExitCode(0);

        $this->assertEquals('active', $pin->fresh()->status);
        $this->assertEquals(70.00, (float) $wallet->fresh()->balance);
    }
}
