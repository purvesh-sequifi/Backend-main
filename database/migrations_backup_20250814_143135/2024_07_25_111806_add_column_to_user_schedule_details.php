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
            $table->integer('user_attendance_id')->nullable()->default(null)->after('updated_type');
            $table->integer('attendance_status')->default(0)->after('user_attendance_id');
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
            $table->dropColumn('user_attendance_id');
            $table->dropColumn('attendance_status');
        });
    }
};
