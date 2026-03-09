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
        Schema::table('crm_sale_info', function (Blueprint $table) {
            //
            $table->date('cancel_date')->nullable()->after('created_id');
            $table->string('status', 20)->nullable()->after('created_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('crm_sale_info', function (Blueprint $table) {
            //
            $table->dropColumn('cancel_date');
            $table->dropColumn('status');
        });
    }
};
