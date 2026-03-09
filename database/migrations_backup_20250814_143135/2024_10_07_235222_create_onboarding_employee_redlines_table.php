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
        Schema::create('onboarding_employee_redlines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->tinyInteger('self_gen_user')->default(0);
            $table->unsignedBigInteger('core_position_id')->nullable();
            $table->unsignedBigInteger('updater_id')->nullable();
            $table->Integer('position_id')->default(0);
            $table->string('redline_amount_type')->nullable();
            $table->decimal('redline', 8, 2)->nullable();
            $table->string('redline_type')->nullable();
            $table->date('redline_effective_date')->nullable();
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
        Schema::dropIfExists('onboarding_employee_redlines');
    }
};
