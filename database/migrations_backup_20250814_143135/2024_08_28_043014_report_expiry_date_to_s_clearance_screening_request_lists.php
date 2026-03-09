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
            $table->date('report_expiry_date')->nullable()->after('report_date');
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
            //
        });
    }
};
