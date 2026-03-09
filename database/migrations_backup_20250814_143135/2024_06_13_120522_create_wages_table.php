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
        Schema::create('wages', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('position_id');

            $table->boolean('enabled')->default(1)->comment('1 for enabled, 0 for disabled');

            $table->enum('pay_type', ['Hourly', 'Salary'])->nullable();
            $table->boolean('pay_type_lock_for_hire')->default(0)->comment('1 for lock, 0 for unlock');

            $table->decimal('pay_rate', 10, 2);
            $table->boolean('pay_rate_lock_for_hire')->default(0)->comment('1 for lock, 0 for unlock');

            $table->decimal('pto_hours', 10, 2)->nullable();
            $table->boolean('pto_hours_lock_for_hire')->default(0)->comment('1 for lock, 0 for unlock');

            $table->enum('unused_pto', ['Expires Monthly', 'Expires Annually', 'Accrues Continuously'])->nullable();
            $table->boolean('unused_pto_lock_for_hire')->default(0)->comment('1 for lock, 0 for unlock');

            $table->decimal('expected_weekly_hours', 10, 2)->default(40.00);
            $table->boolean('ewh_lock_for_hire')->default(0)->comment('ewh = expected_weekly_hours; 1 for lock, 0 for unlock');

            $table->decimal('overtime_rate', 10, 2)->default(1.50);
            $table->boolean('ot_rate_lock_for_hire')->default(0)->comment('ot = overtime; 1 for lock, 0 for unlock');

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
        Schema::dropIfExists('wages');
    }
};
