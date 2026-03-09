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
        Schema::create('sent_offer_letters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_id')->comment('template_id of offer letter');
            $table->unsignedBigInteger('onboarding_employee_id')->comment('offerletter template sent to onboarding_employee');
            $table->softDeletes();
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
        Schema::dropIfExists('sent_offer_letters');
    }
};
