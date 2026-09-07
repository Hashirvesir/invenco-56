<?php

namespace App\Listeners\AccountingV2;

use App\Events\DepositDeleted;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GenerateDepositReversal
{
    use SkipsWhenManual;

    public function handle(DepositDeleted $event): void
    {
        if ($this->shouldSkipForModel($event->deposit)) {
            return;
        }

        AccountingHelper::reverseDeposit($event->deposit);
    }
}
