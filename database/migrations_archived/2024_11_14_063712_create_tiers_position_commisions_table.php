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
        Schema::create('tiers_position_commisions', function (Blueprint $table) {
            $table->id();
            $table->Integer('position_id')->nullable();
            $table->Integer('position_commission_id')->nullable();
            $table->Integer('product_id')->nullable();
            $table->Integer('tiers_schema_id')->nullable();
            $table->string('tiers_advancement')->nullable();
            $table->decimal('to_dealer_fee', 8, 2)->nullable();
            $table->decimal('from_dealer_fee', 8, 2)->nullable();
            $table->string('commission_value')->nullable();
            $table->date('effective_date')->nullable();
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
        Schema::dropIfExists('tiers_position_commisions');
    }
};
