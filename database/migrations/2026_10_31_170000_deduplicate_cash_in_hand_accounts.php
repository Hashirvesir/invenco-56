<?php

use App\Services\AccountingV2\AccountingSyncService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            AccountingSyncService::deduplicateCashAccounts();
            AccountingSyncService::syncAll();
        } catch (\Throwable $e) {
            // best-effort
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
