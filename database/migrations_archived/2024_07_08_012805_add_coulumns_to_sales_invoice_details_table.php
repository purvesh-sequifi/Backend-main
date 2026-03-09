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
        Schema::table('sales_invoice_details', function (Blueprint $table) {
            $table->string('updated_kw')->nullable()->after('billing_date');
            $table->date('updated_kw_date')->nullable()->after('updated_kw');
            $table->tinyInteger('is_kw_adjusted_invoice')->nullable()->after('updated_kw_date')->default(0);
            $table->string('invoice_generated_on_kw')->nullable()->after('is_kw_adjusted_invoice');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sales_invoice_details', function (Blueprint $table) {
            $table->dropColumn('updated_kw');
            $table->dropColumn('updated_kw_date');
            $table->dropColumn('is_kw_adjusted_invoice');
            $table->dropColumn('invoice_generated_on_kw');
        });
    }
};
