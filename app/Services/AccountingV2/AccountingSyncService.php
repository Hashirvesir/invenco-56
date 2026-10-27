<?php

namespace App\Services\AccountingV2;

use App\Models\Account;
use App\Models\AccountingV2\ChartOfAccount;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountingSyncService
{
    /**
     * Synchronize all existing legacy accounts into Chart of Accounts.
     */
    public static function syncAll(): array
    {
        if (! Schema::hasTable('acc_chart_of_accounts') || ! Schema::hasTable('accounts')) {
            return ['status' => 'skipped', 'message' => 'Required tables not found'];
        }

        // 1. Fix misclassified 'cash' account if type was set to 'income'
        DB::table('acc_chart_of_accounts')
            ->where('code', '12121')
            ->where('name', 'cash')
            ->where('type', 'income')
            ->update(['type' => 'asset', 'updated_at' => Carbon::now()]);

        // 2. Ensure general cash account exists in legacy accounts and acc_chart_of_accounts
        $cashAccount = Account::whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('account_name', 'like', '%cash%')
                  ->orWhere('account_num', '1000-CASH');
            })->first();

        if (! $cashAccount) {
            $cashAccount = Account::create([
                'account_num' => '1000-CASH',
                'account_name' => 'Cash in Hand',
                'initial_balance' => 0,
                'balance' => 0,
                'note' => 'Default Cash Account',
            ]);
        }

        $generalCash = DB::table('acc_chart_of_accounts')->where('code', '1000-CASH')->first();
        if (! $generalCash) {
            DB::table('acc_chart_of_accounts')->insert([
                'account_id' => $cashAccount->id,
                'code' => '1000-CASH',
                'name' => 'Cash in Hand',
                'type' => 'asset',
                'parent_id' => null,
                'level' => 0,
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        } else {
            DB::table('acc_chart_of_accounts')
                ->where('id', $generalCash->id)
                ->update([
                    'account_id' => $cashAccount->id,
                    'type' => 'asset',
                    'is_active' => true,
                    'updated_at' => Carbon::now(),
                ]);
        }

        // 3. Clean up any accidental duplicate COA entries for cash
        DB::table('acc_chart_of_accounts')
            ->where('code', 'like', '1010-%')
            ->where('name', 'like', '%Cash%')
            ->delete();

        // 4. Sync each legacy account into acc_chart_of_accounts
        $accounts = Account::whereNull('deleted_at')->get();
        $synced = 0;

        foreach ($accounts as $acc) {
            // If it's the cash account, skip because it's already mapped to 1000-CASH
            if ($acc->id == $cashAccount->id) {
                continue;
            }

            $coa = ChartOfAccount::where('account_id', $acc->id)->first();
            if (! $coa) {
                // Check if an existing COA has matching name or code
                $existingByName = ChartOfAccount::where('name', $acc->account_name)->whereNull('account_id')->first();
                if ($existingByName) {
                    $existingByName->update([
                        'account_id' => $acc->id,
                        'type' => 'asset',
                        'is_active' => true,
                    ]);
                    $synced++;
                } else {
                    ChartOfAccount::create([
                        'account_id' => $acc->id,
                        'code' => '1010-'.$acc->id,
                        'name' => $acc->account_name,
                        'type' => 'asset',
                        'parent_id' => null,
                        'level' => 1,
                        'is_active' => true,
                    ]);
                    $synced++;
                }
            } else {
                $coa->update([
                    'name' => $acc->account_name,
                    'type' => 'asset',
                    'is_active' => true,
                ]);
                $synced++;
            }
        }

        // 5. Ensure standard default Chart of Accounts exist (Assets, Liabilities, Equity, Income, Expenses)
        $defaultAccounts = [
            // Assets
            ['code' => '1000-CASH', 'name' => 'Cash in Hand', 'type' => 'asset', 'account_id' => $cashAccount->id],
            ['code' => '1020-PETTY', 'name' => 'Petty Cash', 'type' => 'asset'],
            ['code' => '1100-AR', 'name' => 'Accounts Receivable', 'type' => 'asset'],
            ['code' => '1200-INV', 'name' => 'Inventory', 'type' => 'asset'],
            // Liabilities
            ['code' => '2100-AP', 'name' => 'Accounts Payable', 'type' => 'liability'],
            ['code' => '2200-TAX', 'name' => 'Sales Tax Payable', 'type' => 'liability'],
            ['code' => '2300-SAL', 'name' => 'Salaries Payable', 'type' => 'liability'],
            ['code' => '2400-ACC', 'name' => 'Accrued Liabilities', 'type' => 'liability'],
            // Equity
            ['code' => '3000-CAP', 'name' => 'Owner Capital', 'type' => 'equity'],
            ['code' => '3100-RE', 'name' => 'Retained Earnings', 'type' => 'equity'],
            // Revenue / Income
            ['code' => '4000-REV', 'name' => 'Sales Revenue', 'type' => 'income'],
            ['code' => '4010-RET', 'name' => 'Sales Returns', 'type' => 'income'],
            ['code' => '4100-DISC', 'name' => 'Discounts Allowed', 'type' => 'income'],
            ['code' => '4200-OTH', 'name' => 'Other Income', 'type' => 'income'],
            // Cost & Expenses
            ['code' => '5000-COGS', 'name' => 'Cost of Goods Sold', 'type' => 'expense'],
            ['code' => '5010-PRET', 'name' => 'Purchase Returns', 'type' => 'expense'],
            ['code' => '6010-RENT', 'name' => 'Rent Expense', 'type' => 'expense'],
            ['code' => '6020-UTIL', 'name' => 'Utilities Expense', 'type' => 'expense'],
            ['code' => '6030-SAL', 'name' => 'Salaries & Wages Expense', 'type' => 'expense'],
            ['code' => '6040-GEN', 'name' => 'General & Administrative Expense', 'type' => 'expense'],
            ['code' => '6050-REP', 'name' => 'Repair & Maintenance Expense', 'type' => 'expense'],
            ['code' => '6060-MKT', 'name' => 'Marketing & Advertising Expense', 'type' => 'expense'],
        ];

        foreach ($defaultAccounts as $item) {
            $existing = DB::table('acc_chart_of_accounts')
                ->where('code', $item['code'])
                ->orWhere(function ($q) use ($item) {
                    $q->where('name', $item['name'])->where('type', $item['type']);
                })->first();

            if (! $existing) {
                DB::table('acc_chart_of_accounts')->insert([
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'account_id' => $item['account_id'] ?? null,
                    'parent_id' => null,
                    'level' => 0,
                    'is_active' => true,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            } else {
                $update = [
                    'code' => $item['code'],
                    'type' => $item['type'],
                    'is_active' => true,
                    'updated_at' => Carbon::now(),
                ];
                if (!empty($item['account_id'])) {
                    $update['account_id'] = $item['account_id'];
                }
                DB::table('acc_chart_of_accounts')->where('id', $existing->id)->update($update);
            }
        }

        // 6. Ensure setting has a valid default account configured
        $setting = Setting::first();
        if ($setting) {
            $currentDefaultExists = Account::whereNull('deleted_at')->where('id', $setting->default_account_id)->exists();
            if (! $currentDefaultExists || empty($setting->default_account_id)) {
                $setting->default_account_id = $cashAccount->id;
                $setting->save();
            }
        }

        // 7. Backfill any unlinked sales, purchases, payments, expenses
        $existingSaleIds = DB::table('acc_journal_entries')->where('reference_type', 'sale')->pluck('reference_id')->toArray();
        $missingSales = \App\Models\Sale::whereNull('deleted_at')
            ->where('GrandTotal', '>', 0)
            ->whereNotIn('id', $existingSaleIds)
            ->get();
        foreach ($missingSales as $s) {
            AccountingHelper::fromSale($s);
        }

        $existingPaymentSaleIds = DB::table('acc_journal_entries')->where('reference_type', 'payment_sale')->pluck('reference_id')->toArray();
        $missingPaymentSales = \App\Models\PaymentSale::whereNull('deleted_at')
            ->where('montant', '>', 0)
            ->whereNotIn('id', $existingPaymentSaleIds)
            ->get();
        foreach ($missingPaymentSales as $ps) {
            AccountingHelper::fromPaymentSale($ps);
        }

        $existingPurchaseIds = DB::table('acc_journal_entries')->where('reference_type', 'purchase')->pluck('reference_id')->toArray();
        $missingPurchases = \App\Models\Purchase::whereNull('deleted_at')
            ->where('GrandTotal', '>', 0)
            ->whereNotIn('id', $existingPurchaseIds)
            ->get();
        foreach ($missingPurchases as $p) {
            AccountingHelper::fromPurchase($p);
        }

        $existingPaymentPurchaseIds = DB::table('acc_journal_entries')->where('reference_type', 'payment_purchase')->pluck('reference_id')->toArray();
        $missingPaymentPurchases = \App\Models\PaymentPurchase::whereNull('deleted_at')
            ->where('montant', '>', 0)
            ->whereNotIn('id', $existingPaymentPurchaseIds)
            ->get();
        foreach ($missingPaymentPurchases as $pp) {
            AccountingHelper::fromPaymentPurchase($pp);
        }

        $existingExpenseIds = DB::table('acc_journal_entries')->where('reference_type', 'expense')->pluck('reference_id')->toArray();
        $missingExpenses = \App\Models\Expense::whereNull('deleted_at')
            ->where('amount', '>', 0)
            ->whereNotIn('id', $existingExpenseIds)
            ->get();
        foreach ($missingExpenses as $e) {
            AccountingHelper::fromExpense($e);
        }

        return ['status' => 'success', 'synced_accounts' => $synced];
    }

    /**
     * Sync a single Account into Chart of Accounts.
     */
    public static function syncAccount(Account $account): ChartOfAccount
    {
        $coa = ChartOfAccount::where('account_id', $account->id)->first();
        if (! $coa) {
            $coa = ChartOfAccount::create([
                'account_id' => $account->id,
                'code' => '1010-'.$account->id,
                'name' => $account->account_name,
                'type' => 'asset',
                'parent_id' => null,
                'level' => 1,
                'is_active' => true,
            ]);
        } else {
            $coa->update([
                'name' => $account->account_name,
                'type' => 'asset',
                'is_active' => true,
            ]);
        }

        return $coa;
    }

    /**
     * Sync when an Account in Chart of Accounts is created/edited.
     * If it is an asset (Cash/Bank), reflect or link it in accounts table.
     */
    public static function syncCoaToAccount(ChartOfAccount $coa): ?Account
    {
        if ($coa->type !== 'asset') {
            return null;
        }

        if ($coa->account_id) {
            $account = Account::find($coa->account_id);
            if ($account) {
                $account->update([
                    'account_name' => $coa->name,
                ]);

                return $account;
            }
        }

        // Only create legacy account for asset accounts that represent payment accounts (code starting with 10 or 12)
        if (str_starts_with($coa->code, '10') || str_starts_with($coa->code, '12') || str_contains(strtolower($coa->name), 'bank') || str_contains(strtolower($coa->name), 'cash')) {
            $account = Account::create([
                'account_num' => $coa->code,
                'account_name' => $coa->name,
                'initial_balance' => 0,
                'balance' => 0,
                'note' => 'Created via Chart of Accounts',
            ]);

            $coa->update(['account_id' => $account->id]);

            return $account;
        }

        return null;
    }
}
