<?php

namespace App\Console\Commands;

use App\Services\AccountingV2\AccountingHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NEW FEATURE - SAFE ADDITION
 *
 * One-time backfill: generates journal entries for existing transactions
 * (e.g. bulk-imported/legacy data) that were never created through the app
 * and therefore never triggered the AccountingV2 model observers.
 * Idempotent: records that already have a journal entry are skipped.
 */
class AccountingBackfill extends Command
{
    protected $signature = 'accounting:backfill {--dry-run : Report counts without writing anything} {--only= : Comma-separated reference types to process, e.g. sale,purchase}';

    protected $description = 'Backfill journal entries for existing sales/purchases/returns/payments/expenses/deposits/transfers/cash-register-openings missing a journal entry';

    protected array $sources = [
        'sale' => ['table' => 'sales', 'method' => 'fromSale'],
        'purchase' => ['table' => 'purchases', 'method' => 'fromPurchase'],
        'sale_return' => ['table' => 'sale_returns', 'method' => 'fromSaleReturn'],
        'purchase_return' => ['table' => 'purchase_returns', 'method' => 'fromPurchaseReturn'],
        'payment_sale' => ['table' => 'payment_sales', 'method' => 'fromPaymentSale'],
        'payment_purchase' => ['table' => 'payment_purchases', 'method' => 'fromPaymentPurchase'],
        'payment_sale_return' => ['table' => 'payment_sale_returns', 'method' => 'fromPaymentSaleReturn'],
        'payment_purchase_return' => ['table' => 'payment_purchase_returns', 'method' => 'fromPaymentPurchaseReturn'],
        'expense' => ['table' => 'expenses', 'method' => 'fromExpense'],
        'deposit' => ['table' => 'deposits', 'method' => 'fromDeposit'],
        'transfer' => ['table' => 'transfer_money', 'method' => 'fromTransferMoney'],
        'cash_register_open' => ['table' => 'cash_registers', 'method' => 'fromCashRegisterOpening'],
    ];

    public function handle(): int
    {
        if (! config('accounting_v2.enabled', true)) {
            $this->error('Accounting V2 is disabled (ACCOUNTING_V2_ENABLED=false). Nothing to do.');

            return 1;
        }
        if (! Schema::hasTable('acc_journal_entries') || ! Schema::hasTable('acc_journal_entry_lines')) {
            $this->error('Accounting tables not installed. Run migrations first.');

            return 1;
        }

        $dryRun = (bool) $this->option('dry-run');
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));

        foreach ($this->sources as $referenceType => $cfg) {
            if (! empty($only) && ! in_array($referenceType, $only, true)) {
                continue;
            }
            if (! Schema::hasTable($cfg['table'])) {
                continue;
            }

            $existing = DB::table('acc_journal_entries')
                ->where('reference_type', $referenceType)
                ->pluck('reference_id')
                ->flip();

            $query = DB::table($cfg['table']);
            if (Schema::hasColumn($cfg['table'], 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            $created = 0;
            $skipped = 0;
            $method = $cfg['method'];

            $query->orderBy('id')->chunkById(500, function ($rows) use (&$created, &$skipped, $existing, $method, $dryRun) {
                foreach ($rows as $row) {
                    if (isset($existing[$row->id])) {
                        $skipped++;

                        continue;
                    }
                    if (! $dryRun) {
                        AccountingHelper::{$method}($row);
                    }
                    $created++;
                }
            });

            $this->info(sprintf('%-24s processed=%-6d skipped(existing)=%d', $referenceType, $created, $skipped));
        }

        $this->info($dryRun ? 'Dry run complete. No entries written.' : 'Backfill complete.');

        return 0;
    }
}
