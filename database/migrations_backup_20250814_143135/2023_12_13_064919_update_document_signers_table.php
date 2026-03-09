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
        if (Schema::hasColumn('envelope_id', 'envelope_document_id')) {
            Schema::table('document_signers', function (Blueprint $table) {
                $table->dropColumn('envelope_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('document_signers', function (Blueprint $table) {
            // Reverse changes: remove foreign key and rename document_id back to envelope_id
            // $table->dropForeign(['envelope_document_id']);

            $table->renameColumn('envelope_document_id', 'envelope_id')->integer('envelope_id')->change();

            // // Reverse change to signer_type
            $table->string('signer_type')->change();
            $table->bigInteger('signer_id');
            $table->dropColumn(['signer_name', 'signer_role', 'signer_email']);
        });
    }
};
