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
        Schema::table('s_clearance_screening_request_lists', function (Blueprint $table) {
            $table->Integer('plan_id')->default(0)->after('exam_attempts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('s_clearance_screening_request_lists', function (Blueprint $table) {
            $table->dropColumn('plan_id');
        });
    }
};
