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
        Schema::table('imported_files', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('filename')->comment('Type of import source (e.g., clark_excel, json_sftp)');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('imported_files', function (Blueprint $table) {
            $table->dropColumn('source_type');
        });
    }
};
