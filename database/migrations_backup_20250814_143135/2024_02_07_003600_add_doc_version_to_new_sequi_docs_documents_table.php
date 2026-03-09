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
        Schema::table('new_sequi_docs_documents', function (Blueprint $table) {
            // Add the new column
            $table->unsignedInteger('doc_version')->default(1)->after('document_inactive_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        Schema::table('new_sequi_docs_documents', function (Blueprint $table) {
            // Drop the column if you want to rollback
            $table->dropColumn('doc_version');
        });
    }
};
