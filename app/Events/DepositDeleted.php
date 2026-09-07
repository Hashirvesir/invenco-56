<?php

namespace App\Events;

use App\Models\Deposit;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DepositDeleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Deposit $deposit;

    public function __construct(Deposit $deposit)
    {
        $this->deposit = $deposit;
    }
}
