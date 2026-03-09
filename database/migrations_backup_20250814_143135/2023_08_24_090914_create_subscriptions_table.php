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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->integer('plan_type_id');
            $table->integer('plan_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('status')->comment('0 active 1 cancel')->default(0);
            $table->integer('paid_status')->comment('0 unpaid 1 paid')->default(0);
            $table->integer('total_pid')->default(0);
            $table->integer('total_m2')->default(0);
            $table->decimal('sales_tax_per', 8, 2)->nullable()->default(0);
            $table->decimal('sales_tax_amount', 8, 2)->nullable()->default(0);
            $table->double('amount')->default(0);
            $table->decimal('credit_amount', 8, 2)->nullable()->default(0);
            $table->decimal('used_credit', 8, 2)->nullable()->default(0);
            $table->decimal('balance_credit', 8, 2)->nullable()->default(0);
            $table->decimal('taxable_amount', 8, 2)->nullable()->default(0);
            $table->double('grand_total')->default(0);
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
        Schema::dropIfExists('subscriptions');
    }
};
