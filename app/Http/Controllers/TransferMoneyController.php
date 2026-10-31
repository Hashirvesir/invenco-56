<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\TransferMoney;
use App\Services\AccountingV2\AccountingHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransferMoneyController extends BaseController
{
    // -------------- Get All Transfer Money ---------------\\

    public function index(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', TransferMoney::class);

        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField ?? 'id';
        $dir = $request->SortType ?? 'desc';

        $transfers = TransferMoney::with('from_account', 'to_account')->whereNull('deleted_at')
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where(function ($query) use ($request) {
                        return $query->whereHas('from_account', function ($q) use ($request) {
                            $q->where('account_name', 'LIKE', "%{$request->search}%");
                        });
                    })
                    ->orWhere(function ($query) use ($request) {
                        return $query->whereHas('to_account', function ($q) use ($request) {
                            $q->where('account_name', 'LIKE', "%{$request->search}%");
                        });
                    });
                });
            });

        $totalRows = $transfers->count();
        if ($perPage == '-1') {
            $perPage = $totalRows;
        }

        $transfer_data = $transfers->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        $data = [];
        foreach ($transfer_data as $transfer) {
            $data[] = [
                'id' => $transfer->id,
                'from_account' => $transfer->from_account->account_name ?? 'N/A',
                'to_account' => $transfer->to_account->account_name ?? 'N/A',
                'from_account_id' => $transfer->from_account_id,
                'to_account_id' => $transfer->to_account_id,
                'amount' => $transfer->amount,
                'date' => $transfer->date,
            ];
        }

        $accounts = Account::whereNull('deleted_at')->get(['id', 'account_name']);

        return response()->json([
            'transfers' => $data,
            'accounts' => $accounts,
            'totalRows' => $totalRows,
        ]);
    }

    // -------------- Store New TransferMoney ---------------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', TransferMoney::class);

        $request->validate([
            'from_account_id' => 'required|different:to_account_id',
            'to_account_id' => 'required|different:from_account_id',
            'amount' => 'required|numeric|gt:0',
            'date' => 'required',
        ], [
            'from_account_id.different' => 'From and To accounts cannot be the same.',
            'to_account_id.different' => 'To and From accounts cannot be the same.',
            'amount.gt' => 'Transfer amount must be greater than 0.',
        ]);

        if ($request->from_account_id == $request->to_account_id) {
            return response()->json(['error' => 'From and To accounts cannot be the same.'], 422);
        }

        $amount = (float) $request->amount;

        $transfer = DB::transaction(function () use ($request, $amount) {
            $from_account = Account::findOrFail($request->from_account_id);
            $to_account = Account::findOrFail($request->to_account_id);

            $from_account->balance = (float) $from_account->balance - $amount;
            $from_account->saveQuietly();

            $to_account->balance = (float) $to_account->balance + $amount;
            $to_account->saveQuietly();

            $createdTransfer = TransferMoney::create([
                'from_account_id' => $request->from_account_id,
                'to_account_id' => $request->to_account_id,
                'amount' => $amount,
                'date' => $request->date,
            ]);

            try {
                AccountingHelper::fromTransferMoney($createdTransfer);
            } catch (\Throwable $e) {
                Log::warning('[AccountingV2] Direct transfer journal failed: ' . $e->getMessage());
            }

            return $createdTransfer;
        }, 10);

        return response()->json(['success' => true, 'transfer' => $transfer], 200);
    }

    // ------------ function show -----------\\

    public function show($id)
    {
        //
    }

    // -------------- Update TransferMoney ---------------\\

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', TransferMoney::class);

        $transfer = TransferMoney::findOrFail($id);

        $request->validate([
            'amount' => 'required|numeric|gt:0',
            'date' => 'required',
        ], [
            'amount.gt' => 'Transfer amount must be greater than 0.',
        ]);

        $newFromId = $request->get('from_account_id', $transfer->from_account_id);
        $newToId = $request->get('to_account_id', $transfer->to_account_id);

        if ($newFromId == $newToId) {
            return response()->json(['error' => 'From and To accounts cannot be the same.'], 422);
        }

        $newAmount = (float) $request->amount;
        $oldAmount = (float) $transfer->amount;

        DB::transaction(function () use ($transfer, $newFromId, $newToId, $newAmount, $oldAmount, $request) {
            // 1. Revert old transfer impact on old accounts
            $oldFrom = Account::find($transfer->from_account_id);
            if ($oldFrom) {
                $oldFrom->balance = (float) $oldFrom->balance + $oldAmount;
                $oldFrom->saveQuietly();
            }

            $oldTo = Account::find($transfer->to_account_id);
            if ($oldTo) {
                $oldTo->balance = (float) $oldTo->balance - $oldAmount;
                $oldTo->saveQuietly();
            }

            // 2. Apply new transfer impact on new accounts
            $newFrom = Account::findOrFail($newFromId);
            $newFrom->balance = (float) $newFrom->balance - $newAmount;
            $newFrom->saveQuietly();

            $newTo = Account::findOrFail($newToId);
            $newTo->balance = (float) $newTo->balance + $newAmount;
            $newTo->saveQuietly();

            // 3. Update transfer record
            $transfer->update([
                'from_account_id' => $newFromId,
                'to_account_id' => $newToId,
                'amount' => $newAmount,
                'date' => $request->date,
            ]);

            // 4. Update journal entry
            try {
                AccountingHelper::reverseTransfer($transfer);
                AccountingHelper::fromTransferMoney($transfer);
            } catch (\Throwable $e) {
                Log::warning('[AccountingV2] Transfer update journal failed: ' . $e->getMessage());
            }
        }, 10);

        return response()->json(['success' => true], 200);
    }

    // -------------- Delete TransferMoney ---------------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', TransferMoney::class);

        DB::transaction(function () use ($id) {
            $transfer = TransferMoney::findOrFail($id);
            $amount = (float) $transfer->amount;

            $from_account = Account::find($transfer->from_account_id);
            if ($from_account) {
                $from_account->balance = (float) $from_account->balance + $amount;
                $from_account->saveQuietly();
            }

            $to_account = Account::find($transfer->to_account_id);
            if ($to_account) {
                $to_account->balance = (float) $to_account->balance - $amount;
                $to_account->saveQuietly();
            }

            $transfer->deleted_at = Carbon::now();
            $transfer->saveQuietly();

            try {
                AccountingHelper::reverseTransfer($transfer);
            } catch (\Throwable $e) {
                Log::warning('[AccountingV2] Transfer delete journal failed: ' . $e->getMessage());
            }
        }, 10);

        return response()->json(['success' => true], 200);
    }
}
