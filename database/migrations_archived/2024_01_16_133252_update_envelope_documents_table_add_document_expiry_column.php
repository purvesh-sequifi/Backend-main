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
        Schema::table('envelope_documents', function (Blueprint $table) {
            $table->datetime('document_expiry')->nullable();
        });

        Schema::table('document_signers', function (Blueprint $table) {
            $table->string('signer_plain_password')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('envelope_documents', function (Blueprint $table) {
            $table->dropColumn('document_expiry');
        });

        Schema::table('document_signers', function (Blueprint $table) {
            $table->dropColumn('signer_plain_password');
        });
    }
};
