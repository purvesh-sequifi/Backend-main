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
        Schema::create('recon_override_history', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->nullable();
            $table->string('pid')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('overrider')->nullable();
            $table->string('type')->nullable();
            $table->double('ke', 8, 2)->nullable();
            $table->double('override_amount', 8, 2)->nullable();
            $table->double('total_amount', 8, 2)->nullable();
            $table->double('paid', 8, 2)->nullable();
            $table->double('percentage', 8, 2)->nullable();
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
        Schema::dropIfExists('recon_override_history');
    }
};
