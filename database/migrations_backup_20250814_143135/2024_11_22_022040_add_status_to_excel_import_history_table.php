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
        Schema::table('excel_import_history', function (Blueprint $table) {
            $table->tinyInteger('status')->default(0)->comment('0 = Success, 1 = In-Progress, 2 = Failed')->after('total_records');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('excel_import_history', function (Blueprint $table) {
            //
        });
    }
};
