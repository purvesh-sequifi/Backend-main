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
        Schema::create('sales_invoice_details', function (Blueprint $table) {
            $table->id();
            $table->integer('sale_master_id');
            $table->string('data_from')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_state')->nullable();
            $table->string('pid')->nullable();
            $table->string('kw')->nullable();
            $table->date('customer_signoff')->nullable();
            $table->date('m1_date')->nullable();
            $table->date('m2_date')->nullable();
            $table->enum('invoice_for', ['unique_pid', 'm2_date'])->nullable()->default('unique_pid');
            $table->integer('billing_history_id')->nullable();
            $table->string('invoice_no')->nullable();
            $table->date('billing_date')->nullable();
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
        Schema::dropIfExists('sales_invoice_details');
    }
};
