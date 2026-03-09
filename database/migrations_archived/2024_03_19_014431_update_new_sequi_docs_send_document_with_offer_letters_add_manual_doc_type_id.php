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
        Schema::table('new_sequi_docs_send_document_with_offer_letters', function (Blueprint $table) {
            $table->integer('manual_doc_type_id')->nullable()->after('is_document_for_upload');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('new_sequi_docs_send_document_with_offer_letters', function (Blueprint $table) {
            $table->dropColumn('manual_doc_type_id');
        });
    }
};
