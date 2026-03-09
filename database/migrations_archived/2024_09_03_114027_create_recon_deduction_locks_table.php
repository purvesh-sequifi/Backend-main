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
        Schema::create('recon_deduction_locks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('cost_center_id');
            $table->decimal('amount', 10, 2)->nullable();
            $table->decimal('limit', 10, 2)->nullable();
            $table->decimal('total', 10, 2)->nullable();
            $table->decimal('outstanding', 10, 2)->nullable();
            $table->decimal('subtotal', 10, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->string('finalize_count')->nullable();
            $table->string('payroll_status')->nullable();
            $table->tinyInteger('status')->nullable()->default(1);
            $table->tinyInteger('is_mark_paid')->nullable()->default(0);
            $table->tinyInteger('is_next_payroll')->nullable()->default(0);
            $table->tinyInteger('is_stop_payroll')->nullable()->default(0);
            $table->integer('ref_id')->nullable()->default(0);
            $table->tinyInteger('is_move_to_recon')->nullable()->default(0);
            $table->tinyInteger('is_move_to_recon_paid')->nullable()->default(0);
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
        Schema::dropIfExists('recon_deduction_locks');
    }
};
