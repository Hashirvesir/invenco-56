<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    protected $fillable = [
        'user_id', 'description', 'name', 'coa_id', 'created_at', 'updated_at', 'deleted_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'coa_id' => 'integer',
    ];

    public function chartOfAccount()
    {
        return $this->belongsTo('App\Models\AccountingV2\ChartOfAccount', 'coa_id');
    }

    public function expense()
    {
        return $this->belongsTo('App\Models\Expense');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }
}
