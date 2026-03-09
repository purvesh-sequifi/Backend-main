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
        Schema::table('crm_setting', function (Blueprint $table) {
            //
            $table->decimal('amount_per_job', 8, 2)->after('status');
            $table->string('plan_name', 50)->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('crm_setting', function (Blueprint $table) {
            //
            $table->dropColumn('amount_per_job');
            $table->dropColumn('plan_name');
        });
    }
};
