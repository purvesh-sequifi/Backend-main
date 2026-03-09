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
            $table->tinyInteger('exam_attempts')->default(0)->after('status');
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
            $table->dropColumn('exam_attempts');
        });
    }
};
