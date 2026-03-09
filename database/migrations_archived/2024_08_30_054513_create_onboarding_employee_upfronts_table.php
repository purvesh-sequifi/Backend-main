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
        Schema::create('onboarding_employee_upfronts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('position_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('milestone_schema_id');
            $table->unsignedBigInteger('milestone_schema_trigger_id');
            $table->tinyInteger('self_gen_user')->comment('0 = Not SelfGen, 1 = SelfGen')->default('0');
            $table->unsignedBigInteger('updater_id');
            $table->string('upfront_pay_amount');
            $table->string('upfront_sale_type')->comment('per sale, per kw');
            $table->date('upfront_effective_date');
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
        Schema::dropIfExists('onboarding_employee_upfronts');
    }
};
