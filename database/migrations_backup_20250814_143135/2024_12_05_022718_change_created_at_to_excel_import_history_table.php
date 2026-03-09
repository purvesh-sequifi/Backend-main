<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        DB::statement('ALTER TABLE `excel_import_history` CHANGE `created_at` `created_at` DATETIME NULL DEFAULT NULL;');
        DB::statement('ALTER TABLE `excel_import_history` CHANGE `updated_at` `updated_at` DATETIME NULL DEFAULT NULL;');
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
