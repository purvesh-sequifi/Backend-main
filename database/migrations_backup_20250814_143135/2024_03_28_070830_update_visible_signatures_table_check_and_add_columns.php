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

        if (Schema::hasColumn('visible_signatures', 'signer_id')) {
            Schema::table('visible_signatures', function (Blueprint $table) {
                $table->dropColumn('signer_id');
            });
        }

        if (Schema::hasColumn('visible_signatures', 'envelope_id')) {
            Schema::table('visible_signatures', function (Blueprint $table) {
                $table->dropColumn('envelope_id');
            });
        }

        if (! Schema::hasColumn('visible_signatures', 'form_data_attributes')) {
            Schema::table('visible_signatures', function (Blueprint $table) {
                // form_data_attributes
                $table->jsonb('form_data_attributes')->nullable();
            });
        }

        if (! Schema::hasColumn('visible_signatures', 'document_signer_id')) {
            Schema::table('visible_signatures', function (Blueprint $table) {
                // document_signer_id
                $table->unsignedInteger('document_signer_id')->nullable();
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
        //
    }
};
