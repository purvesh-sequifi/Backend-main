<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasColumn('company_profiles', 'deduct_any_available_reconciliation_upfront')) {
            Schema::table('company_profiles', function (Blueprint $table) {
                $table->boolean('deduct_any_available_reconciliation_upfront')->default(0);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('company_profiles', 'deduct_any_available_reconciliation_upfront')) {
            Schema::table('company_profiles', function (Blueprint $table) {
                $table->dropColumn('deduct_any_available_reconciliation_upfront');
            });
        }
    }
};
