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
        Schema::create('s_clearance_transunion_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('screening_request_applicant_id')->nullable();
            $table->string('status')->nullable();
            $table->tinyInteger('is_manual_verification')->nullable();
            $table->longText('response')->nullable();
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
        Schema::dropIfExists('s_clearance_transunion_responses');
    }
};
