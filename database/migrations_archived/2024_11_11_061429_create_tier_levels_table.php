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
        Schema::create('tiers_levels', function (Blueprint $table) {
            $table->id();
            $table->Integer('tiers_schema_id');
            $table->decimal('to_dealer_fee', 8, 2)->nullable();
            $table->decimal('from_dealer_fee', 8, 2)->nullable();
            $table->date('effective_date')->nullable(); // Effective date
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
        Schema::dropIfExists('tiers_levels');
    }
};
