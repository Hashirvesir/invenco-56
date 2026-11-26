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
            if (! Schema::hasColumn('settings', 'gemini_api_key')) {
                $table->string('gemini_api_key', 255)->nullable()->after('CompanyPhone');
            }
            if (! Schema::hasColumn('settings', 'openai_api_key')) {
                $table->string('openai_api_key', 255)->nullable()->after('gemini_api_key');
            }
            if (! Schema::hasColumn('settings', 'ai_provider')) {
                $table->string('ai_provider', 50)->default('gemini')->after('openai_api_key');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'gemini_api_key')) {
                $table->dropColumn('gemini_api_key');
            }
            if (Schema::hasColumn('settings', 'openai_api_key')) {
                $table->dropColumn('openai_api_key');
            }
            if (Schema::hasColumn('settings', 'ai_provider')) {
                $table->dropColumn('ai_provider');
            }
        });
    }
};
