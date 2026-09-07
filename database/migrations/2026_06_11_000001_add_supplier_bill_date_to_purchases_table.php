<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSupplierBillDateToPurchasesTable extends Migration
{
    public function up()
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->date('supplier_bill_date')->nullable()->after('supplier_bill_no');
        });

        \DB::table('translations')->updateOrInsert(
            ['locale' => 'en', 'key' => 'Supplier_Bill_Date'],
            ['value' => 'Supplier Bill Date', 'is_default' => 1, 'updated_at' => now(), 'created_at' => now()]
        );
        \DB::table('translations')->updateOrInsert(
            ['locale' => 'en', 'key' => 'Supplier_Bill_No'],
            ['value' => 'Supplier Bill No', 'is_default' => 1, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down()
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('supplier_bill_date');
        });

        \DB::table('translations')->where('locale', 'en')->where('key', 'Supplier_Bill_Date')->delete();
    }
}
