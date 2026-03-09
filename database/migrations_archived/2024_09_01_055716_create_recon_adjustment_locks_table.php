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
        if (! Schema::hasTable('recon_adjustment_locks')) {
            Schema::create('recon_adjustment_locks', function (Blueprint $table) {
                $table->integer('id');
                $table->string('user_id', 200)->nullable();
                $table->string('pid', 200)->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->enum('adjustment_type', ['commission', 'override', 'clawback', 'deductions'])->nullable();
                $table->string('adjustment_override_type')->nullable();
                $table->string('adjustment_amount')->nullable();
                $table->text('adjustment_comment')->nullable();
                $table->string('adjustment_by_user_id')->nullable();
                $table->string('payroll_status')->nullable();
                $table->string('finalize_count')->nullable();
                $table->string('sent_count')->nullable();
                $table->string('payroll_id')->nullable();
                $table->date('pay_period_from')->nullable();
                $table->date('pay_period_to')->nullable();
                $table->string('payroll_execute_status')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('recon_adjustment_locks');
    }
};
