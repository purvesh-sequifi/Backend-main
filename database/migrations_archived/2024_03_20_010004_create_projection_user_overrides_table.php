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
        Schema::create('projection_user_overrides', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('customer_name')->nullable();
            $table->string('type')->nullable();
            $table->integer('sale_user_id')->nullable();
            $table->string('pid')->nullable();
            $table->string('kw')->nullable();
            $table->double('total_override', 8, 2)->nullable();
            ('');
            $table->double('overrides_amount', 8, 2)->nullable();
            $table->string('overrides_type')->nullable();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->string('overrides_settlement_type')->nullable();
            $table->string('status')->nullable();
            $table->tinyInteger('is_stop_payroll')->nullable();
            $table->integer('office_id')->nullable();
            $table->date('date')->nullable();
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
        Schema::dropIfExists('projection_user_overrides');
    }
};
