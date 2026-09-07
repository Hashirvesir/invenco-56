<?php

namespace App\Listeners\AccountingV2;

use App\Events\TransferMoneyCreated;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GenerateTransferMoneyJournal
{
    use SkipsWhenManual;

    public function handle(TransferMoneyCreated $event): void
    {
        if ($this->shouldSkipForModel($event->transfer)) {
            return;
        }

        AccountingHelper::fromTransferMoney($event->transfer);
    }
}
