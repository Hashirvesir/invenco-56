<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddSupplierBillNoTranslation extends Migration
{
    public function up()
    {
        DB::table('translations')->updateOrInsert(
            ['locale' => 'en', 'key' => 'SupplierBillNo'],
            ['value' => 'Supplier Bill No', 'is_default' => 1, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down()
    {
        DB::table('translations')->where('locale', 'en')->where('key', 'SupplierBillNo')->delete();
    }
}
