<?php

namespace App\Console\Commands;

use App\Services\WalletService;
use Illuminate\Console\Command;

class ExpireWalletPins extends Command
{
    protected $signature = 'wallet:expire-pins';
    protected $description = 'Expire stale wallet PINs and refund seller wallets';

    public function handle(WalletService $walletService): int
    {
        $count = $walletService->expireStalePins();

        if ($count > 0) {
            $this->info("Expired {$count} PIN(s) and refunded seller wallets.");
        } else {
            $this->info('No expired PINs found.');
        }

        return 0;
    }
}
