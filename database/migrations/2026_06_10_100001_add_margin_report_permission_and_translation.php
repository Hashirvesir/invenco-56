<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddMarginReportPermissionAndTranslation extends Migration
{
    public function up()
    {
        // Insert permission (safe — skip if already exists)
        DB::table('permissions')->insertOrIgnore([
            ['name' => 'margin_report'],
        ]);

        // Assign permission to ALL existing roles so every role can see it
        $permissionId = DB::table('permissions')->where('name', 'margin_report')->value('id');
        if ($permissionId) {
            $roleIds = DB::table('roles')->pluck('id');
            foreach ($roleIds as $roleId) {
                DB::table('permission_role')->insertOrIgnore([
                    ['permission_id' => $permissionId, 'role_id' => $roleId],
                ]);
            }
        }

        // Insert translations (upsert — safe to re-run)
        $translations = [
            ['locale' => 'en', 'key' => 'MarginReport',    'value' => 'Margin Report'],
            ['locale' => 'en', 'key' => 'QuantitySold',    'value' => 'Qty Sold'],
            ['locale' => 'en', 'key' => 'CostPerUnit',     'value' => 'Cost/Unit'],
            ['locale' => 'en', 'key' => 'TotalCostAmount', 'value' => 'Total Cost'],
            ['locale' => 'en', 'key' => 'TotalSaleAmount', 'value' => 'Total Sale'],
            ['locale' => 'en', 'key' => 'GrossProfit',     'value' => 'Gross Profit'],
            ['locale' => 'en', 'key' => 'MarginPercent',   'value' => 'Margin %'],
            ['locale' => 'en', 'key' => 'TotalSales',      'value' => 'Total Sales'],
            ['locale' => 'en', 'key' => 'TotalCost',       'value' => 'Total Cost'],
            ['locale' => 'en', 'key' => 'AverageMargin',   'value' => 'Avg Margin'],
            ['locale' => 'en', 'key' => 'Search_Product',  'value' => 'Search product...'],
            ['locale' => 'en', 'key' => 'SupplierBillNo',  'value' => 'Supplier Bill No'],
        ];

        foreach ($translations as $t) {
            DB::table('translations')->updateOrInsert(
                ['locale' => $t['locale'], 'key' => $t['key']],
                ['value' => $t['value'], 'is_default' => 1, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down()
    {
        $permissionId = DB::table('permissions')->where('name', 'margin_report')->value('id');
        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        }
        DB::table('permissions')->where('name', 'margin_report')->delete();

        $keys = ['MarginReport','QuantitySold','CostPerUnit','TotalCostAmount','TotalSaleAmount',
                 'GrossProfit','MarginPercent','TotalSales','TotalCost','AverageMargin','Search_Product',
                 'SupplierBillNo'];
        DB::table('translations')->whereIn('key', $keys)->where('locale', 'en')->delete();
    }
}
