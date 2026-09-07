<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSupplierBillNoToPurchaseReturnsTable extends Migration
{
    public function up()
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->string('supplier_bill_no', 100)->nullable()->after('notes');
        });
    }

    public function down()
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropColumn('supplier_bill_no');
        });
    }
}
