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
        Schema::create('user_transfer_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->date('transfer_effective_date')->nullable();
            $table->integer('updater_id')->nullable();
            $table->integer('state_id')->nullable();
            $table->integer('old_state_id')->nullable();
            $table->integer('office_id')->nullable();
            $table->integer('old_office_id')->nullable();
            $table->integer('department_id')->nullable();
            $table->integer('old_department_id')->nullable();
            $table->integer('position_id')->nullable();
            $table->integer('old_position_id')->nullable();
            $table->integer('sub_position_id')->nullable();
            $table->integer('old_sub_position_id')->nullable();
            $table->integer('is_manager')->nullable();
            $table->integer('old_is_manager')->nullable();
            $table->integer('self_gen_accounts')->nullable();
            $table->integer('old_self_gen_accounts')->nullable();
            $table->integer('manager_id')->nullable();
            $table->integer('old_manager_id')->nullable();
            $table->integer('team_id')->nullable();
            $table->integer('old_team_id')->nullable();
            $table->string('redline_amount_type')->nullable();
            $table->string('old_redline_amount_type')->nullable();
            $table->integer('redline')->nullable();
            $table->integer('old_redline')->nullable();
            $table->string('redline_type')->nullable();
            $table->string('old_redline_type')->nullable();
            $table->string('self_gen_redline_amount_type')->nullable();
            $table->string('old_self_gen_redline_amount_type')->nullable();
            $table->integer('self_gen_redline')->nullable();
            $table->integer('old_self_gen_redline')->nullable();
            $table->string('self_gen_redline_type')->nullable();
            $table->string('old_self_gen_redline_type')->nullable();
            $table->integer('existing_employee_new_manager_id')->nullable();
            $table->integer('existing_employee_old_manager_id')->nullable();

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
        Schema::dropIfExists('user_transfer_history');
    }
};
