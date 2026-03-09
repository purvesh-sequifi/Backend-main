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
        // Drop existing columns
        // Schema::table('visible_signatures', function (Blueprint $table) {
        //     $table->dropColumn(['signer_id', 'envelope_id']);
        // });

        // // add form attr
        // Schema::table('visible_signatures', function (Blueprint $table) {
        //     $table->jsonb('form_data_attributes');
        // });

        // Schema::table('visible_signatures', function (Blueprint $table) {
        //     $table->unsignedInteger('document_signer_id');
        // });

        // Add new column document_signer_id and make it a foreign key
        // Schema::table('visible_signatures', function (Blueprint $table) {
        //     $table->foreignId('document_signer_id')->constrained('document_signers')->onDelete('cascade');
        // });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Schema::table('visible_signatures', function (Blueprint $table) {
        //     $table->dropConstrainedForeignId('document_signer_id');
        // });

        // Add back the removed columns
        Schema::table('visible_signatures', function (Blueprint $table) {
            $table->unsignedInteger('signer_id');
            $table->unsignedInteger('envelope_id');
        });
        Schema::table('visible_signatures', function (Blueprint $table) {
            $table->dropColumn(['form_data_attributes', 'document_signer_id']);
        });
    }
};
