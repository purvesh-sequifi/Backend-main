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
        // id, envelope_id, signer_id/user_id/onboarding_employee_id, concent
        Schema::create('document_signers', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('envelope_document_id')->nullable();
            $table->tinyInteger('consent')->default(0);
            $table->tinyInteger('signer_type')->default(0);
            $table->integer('signer_sequence')->nullable();
            $table->string('signer_email')->nullable();
            $table->string('signer_name')->nullable();
            $table->string('signer_role')->nullable();
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
        Schema::dropIfExists('document_signers');
    }
};
