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
        Schema::create('new_sequi_docs_signature_request_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('ApiName')->nullable();
            $table->json('user_array')->nullable();
            $table->json('envelope_data')->nullable();
            $table->json('send_document_final_array')->nullable();
            $table->json('signature_request_response')->nullable();
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
        Schema::dropIfExists('new_sequi_docs_signature_request_logs');
    }
};
