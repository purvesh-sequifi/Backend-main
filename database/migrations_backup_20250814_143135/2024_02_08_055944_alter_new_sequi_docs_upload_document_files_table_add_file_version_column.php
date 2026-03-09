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
        Schema::table('new_sequi_docs_upload_document_files', function (Blueprint $table) {
            $table->unsignedInteger('file_version')->default(1)->after('s3_document_file_path');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('new_sequi_docs_upload_document_files', function (Blueprint $table) {
            $table->dropColumn('file_version');
        });
    }
};
