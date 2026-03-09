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
        Schema::create('employee_id_setting', function (Blueprint $table) {
            $table->id();
            $table->string('prefix')->nullable();
            $table->string('id_code')->nullable();
            $table->string('onbording_prefix')->nullable();
            $table->string('id_code_no_to_start_from')->nullable();
            $table->string('onbording_id_code')->nullable();
            $table->string('onbording_id_code_no_to_start_from')->nullable();
            $table->string('select_offer_letter_to_send')->nullable();
            $table->string('select_agreement_to_sign')->nullable();
            $table->tinyInteger('automatic_hiring_status')->default('0');
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
        Schema::dropIfExists('employee_id_setting');
    }
};
