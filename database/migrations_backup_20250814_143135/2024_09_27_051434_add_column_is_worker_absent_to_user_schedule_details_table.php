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
        Schema::table('user_schedule_details', function (Blueprint $table) {
            $table->integer('is_worker_absent')->nullable()->default(0)->after('is_flexible');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_schedule_details', function (Blueprint $table) {
            $table->dropColumn('is_worker_absent');
        });
    }
};
