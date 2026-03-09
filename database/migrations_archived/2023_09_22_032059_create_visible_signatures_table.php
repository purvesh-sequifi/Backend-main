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
        Schema::create('visible_signatures', function (Blueprint $table) {
            $table->id();
            $table->json('signature_attributes')->nullable(); // can be user id, employee id , manager id
            $table->json('form_data_attributes')->nullable();
            $table->integer('document_signer_id')->nullable();
            $table->timestamps();
            $table->softDeletes($column = 'deleted_at', $precision = 0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('visible_signatures');
    }
};
