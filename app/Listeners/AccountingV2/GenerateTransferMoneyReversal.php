<?php

namespace App\Listeners\AccountingV2;

use App\Events\TransferMoneyDeleted;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GenerateTransferMoneyReversal
{
    use SkipsWhenManual;

    public function handle(TransferMoneyDeleted $event): void
    {
        if ($this->shouldSkipForModel($event->transfer)) {
            return;
        }

        AccountingHelper::reverseTransfer($event->transfer);
    }
}
