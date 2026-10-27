<?php

namespace App\Console\Commands;

use App\Services\AccountingV2\AccountingSyncService;
use Illuminate\Console\Command;

class AccountingSyncAccounts extends Command
{
    protected $signature = 'accounting:sync-accounts';
    protected $description = 'Sync legacy accounts into Chart of Accounts';

    public function handle()
    {
        $this->info('Starting Accounting sync...');
        $result = AccountingSyncService::syncAll();
        $this->info('Result: ' . json_encode($result));
        return 0;
    }
}
