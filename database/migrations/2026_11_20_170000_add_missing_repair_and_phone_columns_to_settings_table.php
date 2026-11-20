<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'company_logo')) {
                $table->string('company_logo')->nullable();
            }
            if (! Schema::hasColumn('settings', 'company_phone_2')) {
                $table->string('company_phone_2')->nullable();
            }
            if (! Schema::hasColumn('settings', 'company_phone_3')) {
                $table->string('company_phone_3')->nullable();
            }
            if (! Schema::hasColumn('settings', 'whatsapp_sale_template')) {
                $table->text('whatsapp_sale_template')->nullable();
            }
            if (! Schema::hasColumn('settings', 'whatsapp_purchase_template')) {
                $table->text('whatsapp_purchase_template')->nullable();
            }
            if (! Schema::hasColumn('settings', 'whatsapp_ledger_template')) {
                $table->text('whatsapp_ledger_template')->nullable();
            }
            if (! Schema::hasColumn('settings', 'repair_invoice_terms')) {
                $table->text('repair_invoice_terms')->nullable();
            }
            if (! Schema::hasColumn('settings', 'repair_receipt_terms')) {
                $table->text('repair_receipt_terms')->nullable();
            }
            if (! Schema::hasColumn('settings', 'repair_custom_note')) {
                $table->text('repair_custom_note')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $columns = [
                'company_logo',
                'company_phone_2',
                'company_phone_3',
                'whatsapp_sale_template',
                'whatsapp_purchase_template',
                'whatsapp_ledger_template',
                'repair_invoice_terms',
                'repair_receipt_terms',
                'repair_custom_note',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
