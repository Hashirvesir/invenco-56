<?php

namespace App\Models\AccountingV2;

use Illuminate\Database\Eloquent\Model;

/**
 * NEW FEATURE - SAFE ADDITION
 */
class ChartOfAccount extends Model
{
    protected $table = 'acc_chart_of_accounts';

    protected $fillable = [
        'account_id', 'code', 'name', 'type', 'parent_id', 'level', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'level' => 'integer',
        'account_id' => 'integer',
        'parent_id' => 'integer',
    ];

    protected $attributes = [
        'is_active' => true,
        'level' => 0,
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}





