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
        Schema::create('user_wages', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('updater_id');

            $table->enum('pay_type', ['Hourly', 'Salary'])->default('Salary');

            $table->decimal('pay_rate', 10, 2);

            $table->decimal('pto_hours', 10, 2)->nullable();

            $table->enum('unused_pto', ['Expires Monthly', 'Expires Annually', 'Accrues Continuously'])->nullable();

            $table->decimal('expected_weekly_hours', 10, 2)->default(40.00);

            $table->decimal('overtime_rate', 10, 2)->default(1.50);
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
        Schema::dropIfExists('user_wages');
    }
};
