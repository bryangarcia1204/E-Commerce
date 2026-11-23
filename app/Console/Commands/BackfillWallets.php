<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Console\Command;

class BackfillWallets extends Command
{
    protected $signature = 'wallets:backfill';
    protected $description = 'Crea wallets para usuarios que aún no tienen';

    public function handle(): int
    {
        $created = 0;
        User::doesntHave('wallet')->chunk(200, function ($users) use (&$created) {
            foreach ($users as $u) {
                Wallet::create(['user_id' => $u->id]);
                $created++;
            }
        });

        $this->info("Wallets creadas: {$created}");
        return self::SUCCESS;
    }
}