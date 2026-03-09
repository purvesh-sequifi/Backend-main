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
        Schema::create('onboarding_employee_withhelds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->Integer('product_id')->default(0);
            $table->unsignedBigInteger('updater_id')->nullable();
            $table->Integer('position_id')->default(0);
            $table->decimal('withheld_amount', 8, 2)->nullable();
            $table->string('withheld_type')->nullable();
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
        Schema::dropIfExists('onboarding_employee_withhelds');
    }
};
