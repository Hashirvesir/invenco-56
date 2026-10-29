<?php

namespace App\Http\Controllers\AccountingV2;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountingV2\ChartOfAccount;
use App\Models\PaymentPurchase;
use App\Models\PaymentSale;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\TransferMoney;
use App\Services\AccountingV2\AccountingSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NEW FEATURE - SAFE ADDITION
 */
class ChartOfAccountsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'chart_of_accounts', Account::class);

        if (! Schema::hasTable('acc_chart_of_accounts')) {
            return response()->json(['data' => [], 'totalRows' => 0]);
        }
        $query = ChartOfAccount::query();
        // Filters
        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }
        if ($request->has('active')) {
            $query->where('is_active', (int) $request->get('active') === 1);
        }
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")->orWhere('name', 'LIKE', "%{$search}%");
            });
        }

        $totalRows = $query->count();
        $perPage = (int) ($request->get('limit') ?: 10);
        if ($perPage === -1) {
            $perPage = $totalRows > 0 ? $totalRows : 10;
        }
        $page = max(1, (int) ($request->get('page') ?: 1));
        $offSet = ($page * $perPage) - $perPage;
        $order = $request->get('SortField', 'code');
        $dir = $request->get('SortType', 'asc');

        $rows = $query->orderBy($order, $dir)->offset($offSet)->limit($perPage)->get();

        // Attach real-time ledger balance to each account
        if (Schema::hasTable('acc_journal_entry_lines') && $rows->isNotEmpty()) {
            $coaIds = $rows->pluck('id')->all();
            $balanceMap = DB::table('acc_journal_entry_lines as l')
                ->join('acc_journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
                ->where('j.status', 'posted')
                ->whereIn('l.coa_id', $coaIds)
                ->select('l.coa_id', DB::raw('SUM(l.debit) as debits'), DB::raw('SUM(l.credit) as credits'))
                ->groupBy('l.coa_id')
                ->get()
                ->keyBy('coa_id');

            foreach ($rows as $row) {
                $totals = $balanceMap->get($row->id);
                $deb = (float) ($totals->debits ?? 0);
                $cred = (float) ($totals->credits ?? 0);
                if (in_array($row->type, ['asset', 'expense'], true)) {
                    $row->balance = round($deb - $cred, 2);
                } else {
                    $row->balance = round($cred - $deb, 2);
                }
            }
        }

        return response()->json([
            'data' => $rows,
            'totalRows' => $totalRows,
        ]);
    }

    /**
     * Account General Ledger - Detailed transaction statement for a specific account.
     */
    public function ledger(Request $request, int $id)
    {
        $this->authorizeForUser($request->user('api'), 'chart_of_accounts', Account::class);

        if (! Schema::hasTable('acc_chart_of_accounts') || ! Schema::hasTable('acc_journal_entry_lines')) {
            return response()->json([
                'account' => null,
                'opening_balance' => 0,
                'closing_balance' => 0,
                'total_debit' => 0,
                'total_credit' => 0,
                'data' => [],
                'totalRows' => 0,
            ]);
        }

        $account = ChartOfAccount::findOrFail($id);
        $from = $request->get('from');
        $to = $request->get('to');
        $search = trim((string) $request->get('search', ''));

        // Collect all related COA IDs (including this ID and any mapped to the same legacy account)
        $matchingCoaIds = [$id];
        if (!empty($account->account_id)) {
            $related = ChartOfAccount::where('account_id', $account->account_id)->pluck('id')->all();
            $matchingCoaIds = array_values(array_unique(array_merge($matchingCoaIds, $related)));
        }

        // 1. Calculate opening balance (all posted entries before $from)
        $openingBalance = 0.0;
        if ($from) {
            $prevTotals = DB::table('acc_journal_entry_lines as l')
                ->join('acc_journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
                ->where('j.status', 'posted')
                ->where(function ($q) use ($matchingCoaIds, $account) {
                    $q->whereIn('l.coa_id', $matchingCoaIds);
                    if (!empty($account->account_id)) {
                        $q->orWhere('l.account_id', $account->account_id);
                    }
                })
                ->where('j.date', '<', $from)
                ->select(DB::raw('SUM(l.debit) as debits'), DB::raw('SUM(l.credit) as credits'))
                ->first();

            $prevDeb = (float) ($prevTotals->debits ?? 0);
            $prevCred = (float) ($prevTotals->credits ?? 0);
            if (in_array($account->type, ['asset', 'expense'], true)) {
                $openingBalance = $prevDeb - $prevCred;
            } else {
                $openingBalance = $prevCred - $prevDeb;
            }
        }

        // 2. Query transactions in date range (including all payments/transfers through this account)
        $query = DB::table('acc_journal_entry_lines as l')
            ->join('acc_journal_entries as j', 'j.id', '=', 'l.journal_entry_id')
            ->where('j.status', 'posted')
            ->where(function ($q) use ($matchingCoaIds, $account) {
                $q->whereIn('l.coa_id', $matchingCoaIds);
                if (!empty($account->account_id)) {
                    $q->orWhere('l.account_id', $account->account_id);
                }
            })
            ->select(
                'l.id as line_id',
                'j.id as journal_entry_id',
                'j.date',
                'j.reference_type',
                'j.reference_id',
                'j.description as journal_description',
                'l.memo',
                'l.debit',
                'l.credit'
            );

        if ($from) {
            $query->where('j.date', '>=', $from);
        }
        if ($to) {
            $query->where('j.date', '<=', $to);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('j.description', 'like', "%{$search}%")
                    ->orWhere('l.memo', 'like', "%{$search}%")
                    ->orWhere('j.reference_type', 'like', "%{$search}%");
            });
        }

        $rawEntries = $query->orderBy('j.date', 'asc')
            ->orderBy('j.id', 'asc')
            ->orderBy('l.id', 'asc')
            ->get();

        // 3. Batch resolve document reference numbers for human readability
        $saleIds = [];
        $purchaseIds = [];
        $paymentSaleIds = [];
        $paymentPurchaseIds = [];
        $transferIds = [];

        foreach ($rawEntries as $entry) {
            if ($entry->reference_type === 'sale' && $entry->reference_id) {
                $saleIds[] = $entry->reference_id;
            } elseif ($entry->reference_type === 'purchase' && $entry->reference_id) {
                $purchaseIds[] = $entry->reference_id;
            } elseif ($entry->reference_type === 'payment_sale' && $entry->reference_id) {
                $paymentSaleIds[] = $entry->reference_id;
            } elseif ($entry->reference_type === 'payment_purchase' && $entry->reference_id) {
                $paymentPurchaseIds[] = $entry->reference_id;
            } elseif ($entry->reference_type === 'transfer' && $entry->reference_id) {
                $transferIds[] = $entry->reference_id;
            }
        }

        $saleRefs = ! empty($saleIds) ? Sale::whereIn('id', array_unique($saleIds))->pluck('Ref', 'id') : collect();
        $purchaseRefs = ! empty($purchaseIds) ? Purchase::whereIn('id', array_unique($purchaseIds))->pluck('Ref', 'id') : collect();
        $paymentSales = ! empty($paymentSaleIds) ? PaymentSale::whereIn('id', array_unique($paymentSaleIds))->get(['id', 'Ref', 'sale_id'])->keyBy('id') : collect();
        $paymentPurchases = ! empty($paymentPurchaseIds) ? PaymentPurchase::whereIn('id', array_unique($paymentPurchaseIds))->get(['id', 'Ref', 'purchase_id'])->keyBy('id') : collect();
        $transfers = ! empty($transferIds) ? TransferMoney::with('from_account', 'to_account')->whereIn('id', array_unique($transferIds))->get()->keyBy('id') : collect();

        // 4. Calculate running balances
        $runningBalance = $openingBalance;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $isAssetOrExpense = in_array($account->type, ['asset', 'expense'], true);

        $transactions = [];
        foreach ($rawEntries as $row) {
            $deb = (float) $row->debit;
            $cred = (float) $row->credit;
            $totalDebit += $deb;
            $totalCredit += $cred;

            if ($isAssetOrExpense) {
                $runningBalance += ($deb - $cred);
            } else {
                $runningBalance += ($cred - $deb);
            }

            // Resolve Ref & Source Document ID for click-through
            $refNumber = $row->journal_description ?? '';
            $sourceId = $row->reference_id;
            $desc = $row->journal_description;
            $memo = $row->memo;

            if ($row->reference_type === 'sale' && isset($saleRefs[$row->reference_id])) {
                $refNumber = $saleRefs[$row->reference_id];
                $sourceId = $row->reference_id;
            } elseif ($row->reference_type === 'purchase' && isset($purchaseRefs[$row->reference_id])) {
                $refNumber = $purchaseRefs[$row->reference_id];
                $sourceId = $row->reference_id;
            } elseif ($row->reference_type === 'payment_sale' && isset($paymentSales[$row->reference_id])) {
                $refNumber = $paymentSales[$row->reference_id]->Ref;
                $sourceId = $paymentSales[$row->reference_id]->sale_id;
            } elseif ($row->reference_type === 'payment_purchase' && isset($paymentPurchases[$row->reference_id])) {
                $refNumber = $paymentPurchases[$row->reference_id]->Ref;
                $sourceId = $paymentPurchases[$row->reference_id]->purchase_id;
            } elseif ($row->reference_type === 'transfer' && isset($transfers[$row->reference_id])) {
                $t = $transfers[$row->reference_id];
                $fromName = $t->from_account->account_name ?? 'Account';
                $toName = $t->to_account->account_name ?? 'Account';
                $refNumber = 'TR-' . $t->id;
                $sourceId = $t->id;
                if ($deb > 0) {
                    $desc = 'Transfer from ' . $fromName;
                    $memo = 'Transfer In from ' . $fromName;
                } else {
                    $desc = 'Transfer to ' . $toName;
                    $memo = 'Transfer Out to ' . $toName;
                }
            }

            $transactions[] = [
                'line_id' => $row->line_id,
                'journal_entry_id' => $row->journal_entry_id,
                'date' => $row->date,
                'reference_type' => $row->reference_type,
                'reference_id' => $row->reference_id,
                'source_id' => $sourceId,
                'reference_no' => $refNumber,
                'description' => $desc,
                'memo' => $memo,
                'debit' => $deb,
                'credit' => $cred,
                'balance' => round($runningBalance, 2),
            ];
        }

        return response()->json([
            'account' => $account,
            'opening_balance' => round($openingBalance, 2),
            'closing_balance' => round($runningBalance, 2),
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'data' => $transactions,
            'totalRows' => count($transactions),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'chart_of_accounts', Account::class);

        $this->validate($request, [
            'code' => 'required|max:64',
            'name' => 'required|max:192',
            'type' => 'required|in:asset,liability,equity,income,expense',
        ]);

        $isActive = true;
        if ($request->has('is_active')) {
            $isActive = filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        }

        $coa = ChartOfAccount::create([
            'account_id' => $request->get('account_id'),
            'code' => $request->get('code'),
            'name' => $request->get('name'),
            'type' => $request->get('type'),
            'parent_id' => $request->get('parent_id'),
            'level' => (int) $request->get('level', 0),
            'is_active' => $isActive,
        ]);

        // Auto-sync into accounts table if it is an asset (Cash/Bank)
        try {
            AccountingSyncService::syncCoaToAccount($coa);
        } catch (\Throwable $e) {
            // best-effort
        }

        return response()->json($coa, 201);
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeForUser($request->user('api'), 'chart_of_accounts', Account::class);

        $coa = ChartOfAccount::findOrFail($id);
        $this->validate($request, [
            'code' => 'sometimes|max:64',
            'name' => 'sometimes|max:192',
            'type' => 'sometimes|in:asset,liability,equity,income,expense',
        ]);

        $data = $request->only(['account_id', 'code', 'name', 'type', 'parent_id', 'level']);
        if ($request->has('is_active')) {
            $data['is_active'] = filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN);
        }
        $coa->update($data);

        // Keep accounts table in sync
        try {
            AccountingSyncService::syncCoaToAccount($coa);
        } catch (\Throwable $e) {
            // best-effort
        }

        return response()->json($coa);
    }

    public function destroy(Request $request, int $id)
    {
        $this->authorizeForUser($request->user('api'), 'chart_of_accounts', Account::class);

        $coa = ChartOfAccount::findOrFail($id);
        if ($coa->account_id) {
            $acc = Account::find($coa->account_id);
            if ($acc) {
                $acc->update(['deleted_at' => \Carbon\Carbon::now()]);
            }
        }
        $coa->delete();

        return response()->json(['success' => true]);
    }
}
