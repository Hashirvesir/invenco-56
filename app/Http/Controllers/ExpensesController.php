<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Models\UserWarehouse;
use App\Models\Warehouse;
use App\utils\helpers;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ExpensesController extends BaseController
{
    // -------------- Show All  Expenses -----------\\

    public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Expense::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers;
        $user = Auth::user();
        $is_all_warehouses = $user->is_all_warehouses;
        // If the user is restricted, fetch their assigned warehouse IDs once and reuse below.
        if (! $is_all_warehouses) {
            $warehouse_ids = UserWarehouse::where('user_id', $user->id)
                ->pluck('warehouse_id')
                ->toArray();
        }
        // New way: Check user's record_view field (user-level boolean)
        // Backward compatibility: If record_view is null, fall back to role permission check
        $view_records = $user->hasRecordView();
        // Filter fields With Params to retrieve
        $columns = [0 => 'Ref', 1 => 'warehouse_id', 2 => 'date', 3 => 'expense_category_id', 4 => 'account_id', 5 => 'payment_method_id'];
        $param = [0 => 'like', 1 => '=', 2 => '=', 3 => '=', 4 => '=', 5 => '='];
        $data = [];

        // Check If User Has Permission View  All Records
        $Expenses = Expense::with('expense_category', 'warehouse', 'account')
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($view_records) {
                if (! $view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            });

            if (! $is_all_warehouses) {
                $Sales->whereIn('warehouse_id', $warehouse_ids);
            }

        // Multiple Filter
        $Filtred = $helpers->filter($Expenses, $columns, $param, $request)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('date', 'LIKE', "%{$request->search}%")
                        ->orWhere('details', 'LIKE', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('expense_category', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('payment_method', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('account', function ($q) use ($request) {
                                $q->where('account_name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });
        $totalRows = $Filtred->count();
        if ($perPage == '-1') {
            $perPage = $totalRows;
        }
        $Expenses = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($Expenses as $Expense) {

            $item['id'] = $Expense->id;
            $item['date'] = $Expense->date;
            $item['Ref'] = $Expense->Ref;
            $item['details'] = $Expense->details;
            $item['amount'] = $Expense->amount;
            $item['payment_method'] = $Expense['payment_method'] ? $Expense['payment_method']->name : '---';
            $item['warehouse_name'] = $Expense['warehouse']->name;
            $item['category_name'] = $Expense['expense_category']->name;
            $item['account_name'] = $Expense['account'] ? $Expense['account']->account_name : 'N/D';

            // Get documents count
            $item['documents_count'] = DB::table('expense_documents')
                ->where('expense_id', $Expense->id)
                ->whereNull('deleted_at')
                ->count();

            $data[] = $item;
        }

        $Expenses_category = ExpenseCategory::with('chartOfAccount:id,code,name')->where('deleted_at', '=', null)->get(['id', 'name', 'coa_id']);
        $accounts = Account::where('deleted_at', '=', null)->get(['id', 'account_name']);
        $payment_methods = PaymentMethod::where('deleted_at', '=', null)->get(['id', 'name']);

        // get warehouses assigned to user
        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        } else {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        return response()->json([
            'expenses' => $data,
            'Expenses_category' => $Expenses_category,
            'warehouses' => $warehouses,
            'accounts' => $accounts,
            'totalRows' => $totalRows,
            'payment_methods' => $payment_methods,
        ]);

    }

    // -------------- Store New Expense -----------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Expense::class);

        \DB::transaction(function () use ($request) {
            request()->validate([
                'expense.date' => 'required',
                'expense.warehouse_id' => 'required',
                'expense.category_id' => 'required',
                'expense.details' => 'required',
                'expense.amount' => 'required',
            ]);

            // Auto-resolve account_id if not provided
            $accountId = $request['expense']['account_id'] ?? null;
            if (! $accountId) {
                $setting = \App\Models\Setting::whereNull('deleted_at')->first();
                $accountId = ($setting && $setting->default_account_id) ? $setting->default_account_id : Account::whereNull('deleted_at')->where('account_name', 'LIKE', '%cash%')->value('id');
            }

            // Auto-resolve payment_method_id from account if not provided
            $paymentMethodId = $request['expense']['payment_method_id'] ?? null;
            if (! $paymentMethodId) {
                $account = Account::find($accountId);
                if ($account && (stripos($account->account_name, 'bank') !== false || stripos($account->account_name, 'ubl') !== false || stripos($account->account_name, 'meezan') !== false)) {
                    $pm = PaymentMethod::whereNull('deleted_at')->where('name', 'LIKE', '%bank%')->first();
                    $paymentMethodId = $pm ? $pm->id : 1;
                } else {
                    $pm = PaymentMethod::whereNull('deleted_at')->where('name', 'LIKE', '%cash%')->first();
                    $paymentMethodId = $pm ? $pm->id : 2;
                }
            }

            Expense::create([
                'user_id' => Auth::user()->id,
                'date' => $request['expense']['date'],
                'Ref' => $this->getNumberOrder(),
                'payment_method_id' => $paymentMethodId,
                'warehouse_id' => $request['expense']['warehouse_id'],
                'expense_category_id' => $request['expense']['category_id'],
                'account_id' => $accountId,
                'details' => $request['expense']['details'],
                'amount' => $request['expense']['amount'],
            ]);

            $account = Account::find($accountId);

            if ($account) {
                $account->update([
                    'balance' => $account->balance - $request['expense']['amount'],
                ]);
            }

        }, 10);

        return response()->json(['success' => true]);
    }

    // ------------- Get Expense Documents ----------\\
    public function getDocuments($expenseId)
    {
        $this->authorizeForUser(request()->user('api'), 'view', Expense::class);

        $expense = Expense::findOrFail($expenseId);

        $documents = DB::table('expense_documents')
            ->where('expense_id', $expenseId)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'documents' => $documents,
            'status' => true,
        ]);
    }

    // ------------- Upload Expense Documents ----------\\
    public function uploadDocuments(Request $request, $expenseId)
    {
        $this->authorizeForUser($request->user('api'), 'update', Expense::class);

        $expense = Expense::findOrFail($expenseId);

        $request->validate([
            'documents.*' => 'required|file|max:10240', // Max 10MB per file
        ]);

        $uploadedDocuments = [];

        if ($request->hasFile('documents')) {
            // Create directory if it doesn't exist
            $uploadPath = public_path('images/expense_documents');
            if (! file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            foreach ($request->file('documents') as $file) {
                // Capture metadata BEFORE moving the file (tmp file is still readable)
                $originalName = $file->getClientOriginalName();
                $size = $file->getSize();
                $mimeType = $file->getMimeType();

                $filename = time() . '_' . Str::random(10) . '_' . $originalName;

                // Move file to public/images/expense_documents
                $file->move($uploadPath, $filename);

                $relativePath = 'images/expense_documents/'.$filename;

                $documentId = DB::table('expense_documents')->insertGetId([
                    'expense_id' => $expenseId,
                    'name' => $originalName,
                    'path' => $relativePath,
                    'size' => $size,
                    'mime_type' => $mimeType,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                $uploadedDocuments[] = $documentId;
            }
        }

        return response()->json([
            'message' => 'Documents uploaded successfully',
            'documents' => $uploadedDocuments,
            'status' => true,
        ]);
    }

    // ------------- Download Expense Document ----------\\
    public function downloadDocument($documentId)
    {
        $this->authorizeForUser(request()->user('api'), 'view', Expense::class);

        $document = DB::table('expense_documents')
            ->where('id', $documentId)
            ->whereNull('deleted_at')
            ->first();

        if (! $document) {
            return response()->json([
                'message' => 'Document not found in database',
                'status' => false,
            ], 404);
        }

        $filePath = public_path($document->path);

        if (! file_exists($filePath)) {
            return response()->json([
                'message' => 'Physical file not found on server',
                'status' => false,
                'path' => $document->path,
            ], 404);
        }

        return response()->download($filePath, $document->name);
    }

    // ------------- Delete Expense Document ----------\\
    public function deleteDocument($documentId)
    {
        $this->authorizeForUser(request()->user('api'), 'delete', Expense::class);

        $document = DB::table('expense_documents')
            ->where('id', $documentId)
            ->whereNull('deleted_at')
            ->first();

        if (! $document) {
            return response()->json([
                'message' => 'Document not found',
                'status' => false,
            ], 404);
        }

        // Soft delete
        DB::table('expense_documents')
            ->where('id', $documentId)
            ->update(['deleted_at' => Carbon::now()]);

        // Optionally delete the physical file
        $filePath = public_path($document->path);
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        return response()->json([
            'message' => 'Document deleted successfully',
            'status' => true,
        ]);
    }

    // ------------ function show -----------\\

    public function show(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', Expense::class);
        $user = Auth::user();
        $view_records = $user->hasRecordView();
        $expense = Expense::with(['warehouse:id,name', 'expense_category:id,name', 'account:id,account_name,account_num', 'payment_method', 'user:id,username'])->whereNull('deleted_at')->findOrFail($id);

        if (! $view_records) {
            $this->authorizeForUser($request->user('api'), 'check_record', $expense);
        }

        $pmName = 'Cash';
        if ($expense->payment_method) {
            $pmName = $expense->payment_method->title ?? $expense->payment_method->name ?? 'Cash';
        }

        return response()->json([
            'expense' => [
                'id' => $expense->id,
                'Ref' => $expense->Ref,
                'date' => $expense->date,
                'amount' => (float) $expense->amount,
                'details' => $expense->details,
                'warehouse_name' => $expense->warehouse ? $expense->warehouse->name : '-',
                'category_name' => $expense->expense_category ? $expense->expense_category->name : '-',
                'account_name' => $expense->account ? $expense->account->account_name : '-',
                'account_num' => $expense->account ? $expense->account->account_num : '-',
                'payment_method' => $pmName,
                'user_name' => $expense->user ? $expense->user->username : '-',
                'created_at' => $expense->created_at ? $expense->created_at->format('Y-m-d H:i') : null,
            ],
        ]);
    }

    // -------------- Update  Expense -----------\\

    public function update(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', Expense::class);
        \DB::transaction(function () use ($request, $id) {
            $user = Auth::user();
            // New way: Check user's record_view field (user-level boolean)
            // Backward compatibility: If record_view is null, fall back to role permission check
            $view_records = $user->hasRecordView();
            $expense = Expense::findOrFail($id);

            // Check If User Has Permission view All Records
            if (! $view_records) {
                // Check If User->id === expense->id
                $this->authorizeForUser($request->user('api'), 'check_record', $expense);
            }

            request()->validate([
                'expense.date' => 'required',
                'expense.warehouse_id' => 'required',
                'expense.category_id' => 'required',
                'expense.details' => 'required',
                'expense.amount' => 'required',
            ]);

            $accountId = $request['expense']['account_id'] ?? null;
            $paymentMethodId = $request['expense']['payment_method_id'] ?? null;
            if (! $paymentMethodId) {
                $acc = Account::find($accountId);
                if ($acc && (stripos($acc->account_name, 'bank') !== false || stripos($acc->account_name, 'ubl') !== false || stripos($acc->account_name, 'meezan') !== false)) {
                    $pm = PaymentMethod::whereNull('deleted_at')->where('name', 'LIKE', '%bank%')->first();
                    $paymentMethodId = $pm ? $pm->id : 1;
                } else {
                    $pm = PaymentMethod::whereNull('deleted_at')->where('name', 'LIKE', '%cash%')->first();
                    $paymentMethodId = $pm ? $pm->id : 2;
                }
            }

            $account = Account::find($expense->account_id);

            if ($account) {
                $account->update([
                    'balance' => $account->balance + $expense->amount,
                ]);
            }

            Expense::whereId($id)->update([
                'date' => $request['expense']['date'],
                'payment_method_id' => $paymentMethodId,
                'warehouse_id' => $request['expense']['warehouse_id'],
                'expense_category_id' => $request['expense']['category_id'],
                'account_id' => $accountId ? $accountId : null,
                'details' => $request['expense']['details'],
                'amount' => $request['expense']['amount'],
            ]);

            $account = Account::find($request['expense']['account_id']);
            if ($account) {
                $account->update([
                    'balance' => $account->balance - $request['expense']['amount'],
                ]);
            }

        }, 10);

        return response()->json(['success' => true]);
    }

    // -------------- Delete Expense -----------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Expense::class);
        $user = Auth::user();
        // New way: Check user's record_view field (user-level boolean)
        // Backward compatibility: If record_view is null, fall back to role permission check
        $view_records = $user->hasRecordView();
        $expense = Expense::findOrFail($id);

        // Check If User Has Permission view All Records
        if (! $view_records) {
            // Check If User->id === expense->id
            $this->authorizeForUser($request->user('api'), 'check_record', $expense);
        }

        $expense->update([
            'deleted_at' => Carbon::now(),
        ]);

        $account = Account::where('id', $expense->account_id)->exists();

        if ($account) {
            // Account exists, perform the update
            $account = Account::find($expense->account_id);
            $account->update([
                'balance' => $account->balance + $expense->amount,
            ]);
        }

        return response()->json(['success' => true]);
    }

    // -------------- Delete by selection  ---------------\\

    public function delete_by_selection(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Expense::class);
        $selectedIds = $request->selectedIds;
        $user = Auth::user();
        // New way: Check user's record_view field (user-level boolean)
        // Backward compatibility: If record_view is null, fall back to role permission check
        $view_records = $user->hasRecordView();

        foreach ($selectedIds as $expense_id) {
            $expense = Expense::findOrFail($expense_id);

            // Check If User Has Permission view All Records
            if (! $view_records) {
                // Check If User->id === expense->id
                $this->authorizeForUser($request->user('api'), 'check_record', $expense);
            }
            $expense->update([
                'deleted_at' => Carbon::now(),
            ]);

            $account = Account::where('id', $expense->account_id)->exists();

            if ($account) {
                // Account exists, perform the update
                $account = Account::find($expense->account_id);
                $account->update([
                    'balance' => $account->balance + $expense->amount,
                ]);
            }
        }

        return response()->json(['success' => true]);
    }

    // --------------- Reference Number of Expense ----------------\\

    public function getNumberOrder()
    {

        $last = DB::table('expenses')->latest('id')->first();

        if ($last) {
            $item = $last->Ref;
            $nwMsg = explode('_', $item);
            $inMsg = $nwMsg[1] + 1;
            $code = $nwMsg[0].'_'.$inMsg;
        } else {
            $code = 'EXP_1111';
        }

        return $code;

    }

    // ---------------- Show Form Create Expense ---------------\\

    public function create(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'create', Expense::class);

        // get warehouses assigned to user
        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        } else {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        $Expenses_category = ExpenseCategory::with('chartOfAccount:id,code,name')->where('deleted_at', '=', null)->get(['id', 'name', 'coa_id']);
        $accounts = Account::where('deleted_at', '=', null)->get(['id', 'account_name']);
        $payment_methods = PaymentMethod::where('deleted_at', '=', null)->get(['id', 'name']);

        $settings = \App\Models\Setting::whereNull('deleted_at')->first();
        $defaultAccount = null;
        if ($settings && $settings->default_account_id) {
            $defaultAccount = Account::whereNull('deleted_at')->find($settings->default_account_id);
        }
        if (! $defaultAccount) {
            $defaultAccount = Account::whereNull('deleted_at')->where('account_name', 'LIKE', '%cash%')->first();
        }
        if (! $defaultAccount) {
            $defaultAccount = Account::whereNull('deleted_at')->first();
        }

        $defaultPaymentMethod = PaymentMethod::whereNull('deleted_at')->where('name', 'LIKE', '%cash%')->first();
        if (! $defaultPaymentMethod) {
            $defaultPaymentMethod = PaymentMethod::whereNull('deleted_at')->first();
        }

        return response()->json([
            'Expenses_category' => $Expenses_category,
            'warehouses' => $warehouses,
            'accounts' => $accounts,
            'payment_methods' => $payment_methods,
            'default_account_id' => $defaultAccount ? $defaultAccount->id : null,
            'default_payment_method_id' => $defaultPaymentMethod ? $defaultPaymentMethod->id : null,
        ]);
    }

    // ------------- Show Form Edit Expense -----------\\

    public function edit(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', Expense::class);
        $user = Auth::user();
        // New way: Check user's record_view field (user-level boolean)
        // Backward compatibility: If record_view is null, fall back to role permission check
        $view_records = $user->hasRecordView();
        $Expense = Expense::where('deleted_at', '=', null)->findOrFail($id);

        // Check If User Has Permission view All Records
        if (! $view_records) {
            // Check If User->id === Expense->id
            $this->authorizeForUser($request->user('api'), 'check_record', $Expense);
        }

        if ($Expense->warehouse_id) {
            if (Warehouse::where('id', $Expense->warehouse_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $data['warehouse_id'] = $Expense->warehouse_id;
            } else {
                $data['warehouse_id'] = '';
            }
        } else {
            $data['warehouse_id'] = '';
        }

        if ($Expense->account_id) {
            if (Account::where('id', $Expense->account_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $data['account_id'] = $Expense->account_id;
            } else {
                $data['account_id'] = '';
            }
        } else {
            $data['account_id'] = '';
        }

        if ($Expense->expense_category_id) {
            if (ExpenseCategory::where('id', $Expense->expense_category_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $data['category_id'] = $Expense->expense_category_id;
            } else {
                $data['category_id'] = '';
            }
        } else {
            $data['category_id'] = '';
        }

        $data['date'] = $Expense->date;
        $data['amount'] = $Expense->amount;
        $data['details'] = $Expense->details;
        $data['payment_method_id'] = $Expense->payment_method_id;

        // get warehouses assigned to user
        $user_auth = auth()->user();
        if ($user_auth->is_all_warehouses) {
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        } else {
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        $Expenses_category = ExpenseCategory::with('chartOfAccount:id,code,name')->where('deleted_at', '=', null)->get(['id', 'name', 'coa_id']);
        $accounts = Account::where('deleted_at', '=', null)->get(['id', 'account_name']);
        $payment_methods = PaymentMethod::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json([
            'expense' => $data,
            'expense_Category' => $Expenses_category,
            'warehouses' => $warehouses,
            'accounts' => $accounts,
            'payment_methods' => $payment_methods,
        ]);
    }
}
