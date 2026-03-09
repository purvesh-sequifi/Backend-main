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
        Schema::create('document_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->string('signature_request_id')->nullable();
            $table->string('signed_document_id')->nullable();
            $table->tinyInteger('signed_status')->comment('0=not signed,1=signed');
            $table->text('document')->nullable()->comment('doc before sign');
            $table->text('signed_document')->nullable()->comment('doc before sign');
            $table->string('signature_request_id_for_callback')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('document_files');
    }
};
