<?php

namespace App\Listeners\AccountingV2;

use App\Events\DepositCreated;
use App\Listeners\AccountingV2\Concerns\SkipsWhenManual;
use App\Services\AccountingV2\AccountingHelper;

class GenerateDepositJournal
{
    use SkipsWhenManual;

    public function handle(DepositCreated $event): void
    {
        if ($this->shouldSkipForModel($event->deposit)) {
            return;
        }

        AccountingHelper::fromDeposit($event->deposit);
    }
}
