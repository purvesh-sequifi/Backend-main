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
            if (Schema::hasColumn('excel_import_history', 'id')) {
                $table->dropColumn('id');
            }
        });
        Schema::table('excel_import_history', function (Blueprint $table) {
            $table->bigIncrements('id')->first();
            $table->integer('new_records')->after('uploaded_file');
            $table->integer('updated_records')->after('new_records');
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
