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
        Schema::create('onboarding_user_redlines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('updater_id')->nullable();
            $table->string('redline_amount_type')->nullable();
            $table->string('redline')->nullable();
            $table->string('redline_type')->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->date('start_date')->nullable();
            $table->integer('commission')->nullable();
            $table->enum('commission_type', ['percent', 'per kw'])->nullable();
            $table->date('commission_effective_date')->nullable();
            $table->string('upfront_pay_amount')->nullable();
            $table->string('upfront_sale_type')->nullable();
            $table->date('upfront_effective_date')->nullable();
            $table->integer('position_id')->nullable();
            $table->decimal('withheld_amount', 8, 2)->nullable();
            $table->enum('withheld_type', ['per sale', 'per KW'])->nullable();
            $table->date('withheld_effective_date')->nullable();
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
        Schema::dropIfExists('onboarding_user_redlines');
    }
};
