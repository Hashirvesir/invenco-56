<?php

namespace App\Events;

use App\Models\TransferMoney;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransferMoneyDeleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public TransferMoney $transfer;

    public function __construct(TransferMoney $transfer)
    {
        $this->transfer = $transfer;
    }
}
