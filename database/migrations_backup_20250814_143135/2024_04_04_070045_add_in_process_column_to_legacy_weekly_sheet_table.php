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
        Schema::table('legacy_weekly_sheet', function (Blueprint $table) {
            $table->enum('in_process', ['0', '1'])->default('0')->after('log_file_name')->comment('0 = Completed, 1 = In-Process');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('legacy_weekly_sheet', function (Blueprint $table) {
            //
        });
    }
};
