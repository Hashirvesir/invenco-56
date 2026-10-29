<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Setting;
use App\Services\AccountingV2\AccountingHelper;
use App\Services\AccountingV2\AccountingSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountingApplyFixes extends Command
{
    protected $signature = 'accounting:apply-fixes';

    protected $description = 'One-click full sync, COA mapping, expense classification, and COGS backfill for production';

    public function handle(): int
    {
        $this->info('========================================================');
        $this->info('Starting Accounting Apply Fixes (Production / Local Sync)');
        $this->info('========================================================');

        // 1. Sync accounts & chart of accounts + missing entities
        $this->info('1/7: Running AccountingSyncService::syncAll()...');
        $syncResult = AccountingSyncService::syncAll();
        $this->info('Sync result: ' . json_encode($syncResult));

        // Clean up any duplicate COA entries sharing the same account_id
        $accountsWithDuplicates = DB::table('acc_chart_of_accounts')
            ->whereNotNull('account_id')
            ->select('account_id', DB::raw('COUNT(*) as c'))
            ->groupBy('account_id')
            ->having('c', '>', 1)
            ->get();

        foreach ($accountsWithDuplicates as $dup) {
            $records = DB::table('acc_chart_of_accounts')
                ->where('account_id', $dup->account_id)
                ->orderBy('id', 'asc')
                ->get();
            $primary = $records->first();
            $others = $records->slice(1);
            foreach ($others as $other) {
                DB::table('acc_journal_entry_lines')->where('coa_id', $other->id)->update(['coa_id' => $primary->id]);
                DB::table('acc_chart_of_accounts')->where('id', $other->id)->delete();
                $this->warn("Merged duplicate COA #{$other->id} ({$other->code}) into primary #{$primary->id} ({$primary->code})");
            }
        }

        // Backfill account initial balances if missing
        $initialAccounts = DB::table('accounts')
            ->whereNull('deleted_at')
            ->where('initial_balance', '>', 0)
            ->get();
        foreach ($initialAccounts as $iAcc) {
            AccountingHelper::fromAccountOpeningBalance($iAcc);
        }

        // Backfill unposted transfers
        $unpostedTransfers = DB::table('transfer_money')
            ->whereNull('deleted_at')
            ->whereNotIn('id', function ($q) {
                $q->select('reference_id')->from('acc_journal_entries')->where('reference_type', 'transfer');
            })
            ->get();
        foreach ($unpostedTransfers as $tr) {
            AccountingHelper::fromTransferMoney($tr);
        }

        // 2. Fix null account_ids in payment_sales & payment_purchases
        $this->info('2/6: Backfilling missing account_ids in payment tables...');
        $cashAccId = DB::table('accounts')->whereNull('deleted_at')->where('account_name', 'LIKE', '%cash%')->value('id');
        if (! $cashAccId) {
            $cashAccId = DB::table('accounts')->whereNull('deleted_at')->value('id');
        }

        if ($cashAccId) {
            $updatedPs = DB::table('payment_sales')->where(function ($q) {
                $q->whereNull('account_id')->orWhere('account_id', 0);
            })->update(['account_id' => $cashAccId]);

            $updatedPp = DB::table('payment_purchases')->where(function ($q) {
                $q->whereNull('account_id')->orWhere('account_id', 0);
            })->update(['account_id' => $cashAccId]);

            $this->info("Updated {$updatedPs} payment_sales and {$updatedPp} payment_purchases to account_id: {$cashAccId}");
        }

        // 3. Map all expense_categories to COA accounts
        $this->info('3/6: Mapping expense_categories to Chart of Accounts...');
        $rentCoa = AccountingHelper::getOrCreateCoa('6010-RENT', 'Rent Expense', 'expense');
        $utilCoa = AccountingHelper::getOrCreateCoa('6020-UTIL', 'Utilities Expense', 'expense');
        $salCoa  = AccountingHelper::getOrCreateCoa('6030-SAL', 'Salaries & Wages Expense', 'expense');
        $repCoa  = AccountingHelper::getOrCreateCoa('6050-REP', 'Repair & Maintenance Expense', 'expense');
        $mktCoa  = AccountingHelper::getOrCreateCoa('6060-MKT', 'Marketing & Advertising Expense', 'expense');
        $genCoa  = AccountingHelper::getOrCreateCoa('6040-GEN', 'General & Administrative Expense', 'expense');

        $categories = DB::table('expense_categories')->get();
        foreach ($categories as $cat) {
            $name = strtolower(trim($cat->name));
            $targetCoa = $genCoa;

            if (str_contains($name, 'rent')) {
                $targetCoa = $rentCoa;
            } elseif (str_contains($name, 'bill') || str_contains($name, 'electric') || str_contains($name, 'water') || str_contains($name, 'util')) {
                $targetCoa = $utilCoa;
            } elseif (str_contains($name, 'salry') || str_contains($name, 'salary') || str_contains($name, 'wage')) {
                $targetCoa = $salCoa;
            } elseif (str_contains($name, 'repair') || str_contains($name, 'kiosk') || str_contains($name, 'maint')) {
                $targetCoa = $repCoa;
            } elseif (str_contains($name, 'marketing') || str_contains($name, 'advert') || preg_match('/\bad\b/', $name)) {
                $targetCoa = $mktCoa;
            }

            DB::table('expense_categories')->where('id', $cat->id)->update(['coa_id' => $targetCoa]);
        }
        $this->info("Mapped " . count($categories) . " expense categories to COA accounts.");

        // 4. Reclassify all expense journals lines
        $this->info('4/6: Classifying expense journal entries to proper COA...');
        $expenseJournals = DB::table('acc_journal_entries')->where('reference_type', 'expense')->get();
        $expReclassified = 0;
        foreach ($expenseJournals as $ej) {
            $expense = DB::table('expenses')->where('id', $ej->reference_id)->first();
            if (! $expense) {
                continue;
            }
            $targetCoaId = AccountingHelper::resolveExpenseCoaId($expense);
            $lines = DB::table('acc_journal_entry_lines')->where('journal_entry_id', $ej->id)->where('debit', '>', 0)->get();
            foreach ($lines as $line) {
                if ($line->coa_id != $targetCoaId) {
                    DB::table('acc_journal_entry_lines')->where('id', $line->id)->update(['coa_id' => $targetCoaId]);
                    $expReclassified++;
                }
            }
        }
        $this->info("Reclassified {$expReclassified} expense lines.");

        // 5. Backfill COGS lines for sales and returns
        $this->info('5/6: Backfilling Cost of Goods Sold (COGS) lines for Sales & Returns...');
        $cogsCoaId = AccountingHelper::getOrCreateCoa('5000-COGS', 'Cost of Goods Sold', 'expense');
        $invCoaId  = AccountingHelper::getOrCreateCoa('1200-INV', 'Inventory', 'asset');

        $saleJournals = DB::table('acc_journal_entries')->where('reference_type', 'sale')->get();
        $salesCogsAdded = 0;
        $now = Carbon::now();

        foreach ($saleJournals as $sj) {
            $hasCogs = DB::table('acc_journal_entry_lines')->where('journal_entry_id', $sj->id)->where('coa_id', $cogsCoaId)->exists();
            if ($hasCogs) {
                continue;
            }

            $cogsAmount = (float) DB::table('sale_details as sd')
                ->leftJoin('products as p', 'p.id', '=', 'sd.product_id')
                ->leftJoin('product_variants as pv', 'pv.id', '=', 'sd.product_variant_id')
                ->where('sd.sale_id', $sj->reference_id)
                ->selectRaw('SUM(sd.quantity * COALESCE(NULLIF(pv.cost, 0), p.cost, 0)) as cost')
                ->value('cost');

            if ($cogsAmount > 0) {
                DB::table('acc_journal_entry_lines')->insert([
                    [
                        'journal_entry_id' => $sj->id,
                        'coa_id' => $cogsCoaId,
                        'account_id' => null,
                        'debit' => $cogsAmount,
                        'credit' => 0,
                        'memo' => 'Cost of Goods Sold',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    [
                        'journal_entry_id' => $sj->id,
                        'coa_id' => $invCoaId,
                        'account_id' => null,
                        'debit' => 0,
                        'credit' => $cogsAmount,
                        'memo' => 'Inventory Relieved',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                ]);
                $salesCogsAdded++;
            }
        }
        $this->info("Added COGS lines to {$salesCogsAdded} sales.");

        // Returns COGS
        $returnJournals = DB::table('acc_journal_entries')->where('reference_type', 'sale_return')->get();
        $returnsCogsAdded = 0;
        foreach ($returnJournals as $rj) {
            $hasCogs = DB::table('acc_journal_entry_lines')->where('journal_entry_id', $rj->id)->where('coa_id', $cogsCoaId)->exists();
            if ($hasCogs) {
                continue;
            }

            $returnCost = (float) DB::table('sale_return_details as srd')
                ->leftJoin('products as p', 'p.id', '=', 'srd.product_id')
                ->leftJoin('product_variants as pv', 'pv.id', '=', 'srd.product_variant_id')
                ->where('srd.sale_return_id', $rj->reference_id)
                ->selectRaw('SUM(srd.quantity * COALESCE(NULLIF(pv.cost, 0), p.cost, 0)) as cost')
                ->value('cost');

            if ($returnCost > 0) {
                DB::table('acc_journal_entry_lines')->insert([
                    [
                        'journal_entry_id' => $rj->id,
                        'coa_id' => $invCoaId,
                        'account_id' => null,
                        'debit' => $returnCost,
                        'credit' => 0,
                        'memo' => 'Restock Returned Inventory',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    [
                        'journal_entry_id' => $rj->id,
                        'coa_id' => $cogsCoaId,
                        'account_id' => null,
                        'debit' => 0,
                        'credit' => $returnCost,
                        'memo' => 'Reverse Cost of Goods Sold',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                ]);
                $returnsCogsAdded++;
            }
        }
        $this->info("Added COGS lines to {$returnsCogsAdded} sale returns.");

        // 6. Fix legacy orphan lines (e.g. manual entries #3 with deleted/legacy coa_id)
        $this->info('6/6: Checking orphan journal lines...');
        $validCoaIds = DB::table('acc_chart_of_accounts')->pluck('id')->toArray();
        $orphanLines = DB::table('acc_journal_entry_lines')->whereNotIn('coa_id', $validCoaIds)->get();
        foreach ($orphanLines as $ol) {
            // Remap to Cash or Retained Earnings if unknown
            $fallbackCoa = AccountingHelper::getOrCreateCoa('1000-CASH', 'Cash in Hand', 'asset');
            DB::table('acc_journal_entry_lines')->where('id', $ol->id)->update(['coa_id' => $fallbackCoa]);
            $this->warn("Remapped orphan line #{$ol->id} to COA #{$fallbackCoa}");
        }

        // Final Trial Balance verification
        $totalDebit = (float) DB::table('acc_journal_entry_lines as l')
            ->join('acc_journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->where('j.status', 'posted')
            ->sum('l.debit');

        $totalCredit = (float) DB::table('acc_journal_entry_lines as l')
            ->join('acc_journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->where('j.status', 'posted')
            ->sum('l.credit');

        $diff = round(abs($totalDebit - $totalCredit), 2);

        $this->info('========================================================');
        $this->info('VERIFICATION REPORT:');
        $this->info("Grand Debit : Rs. " . number_format($totalDebit, 2));
        $this->info("Grand Credit: Rs. " . number_format($totalCredit, 2));
        $this->info("Difference  : Rs. " . number_format($diff, 2));
        if ($diff === 0.00) {
            $this->info('STATUS: PERFECTLY BALANCED (100% SUCCESS) ✔');
        } else {
            $this->warn('STATUS: Difference detected: ' . $diff);
        }
        $this->info('========================================================');

        return 0;
    }
}
