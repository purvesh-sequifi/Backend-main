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
        if (! Schema::hasTable('recon_override_history_locks')) {
            Schema::create('recon_override_history_locks', function (Blueprint $table) {
                $table->integer('id')->nullable();
                $table->bigInteger('user_id')->nullable();
                $table->string('pid')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('status')->nullable();
                $table->bigInteger('sent_count')->nullable();
                $table->string('customer_name')->nullable();
                $table->string('overrider')->nullable();
                $table->string('type')->nullable();
                $table->double('kw', 8, 2)->nullable();
                $table->double('override_amount', 8, 2)->nullable();
                $table->double('total_amount', 8, 2)->nullable();
                $table->double('paid', 8, 2)->nullable();
                $table->double('percentage', 8, 2)->nullable();
                $table->string('payroll_execute_status', 10)->default(0);
                $table->date('pay_period_from')->nullable();
                $table->date('pay_period_to')->nullable();
                $table->bigInteger('payroll_id')->nullable();
                $table->tinyInteger('is_next_payroll')->nullable();
                $table->tinyInteger('is_mark_paid')->nullable();
                $table->enum('is_displayed', ['0', '1'])->default('1')->comment('0 = Old, 1 = In Display');
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
        Schema::dropIfExists('recon_override_history_locks');
    }
};
