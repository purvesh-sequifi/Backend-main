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

        if (Schema::hasColumn('document_signers', 'envelope_id')) {
            Schema::table('document_signers', function (Blueprint $table) {
                // Rename column and change type
                $table->renameColumn('envelope_id', 'envelope_document_id');
            });
        }

        if (Schema::hasColumn('document_signers', 'envelope_document_id')) {
            Schema::table('document_signers', function (Blueprint $table) {
                $table->bigInteger('envelope_document_id')->nullable()->change();
            });

        }

        if (! Schema::hasColumn('document_signers', 'signer_role')) {
            Schema::table('document_signers', function (Blueprint $table) {
                $table->string('signer_role')->nullable();
            });
        }

        if (Schema::hasColumn('document_signers', 'signer_id')) {
            Schema::table('document_signers', function (Blueprint $table) {
                $table->dropColumn('signer_id');
            });
        }

        //

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
