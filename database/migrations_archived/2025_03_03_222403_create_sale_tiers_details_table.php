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
        Schema::dropIfExists('sale_tiers_details');
        Schema::create('sale_tiers_details', function (Blueprint $table) {
            $table->id();
            $table->string('pid');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('user_id');
            $table->string('tier_level')->nullable();
            $table->tinyInteger('is_tiered')->default(0)->comment('0 = Non Tier, 1 = Tiered');
            $table->string('tiers_type')->nullable()->comment('Progressive, Retroactive');
            $table->string('type')->nullable()->comment('Commission, Upfront, Override');
            $table->string('sub_type')->nullable()->comment('Commission = (Commission), Upfront = (Milestone Like m1, m2), Override = (Office, Additional Office, Direct, InDirect)');
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
        Schema::dropIfExists('sale_tiers_details');
    }
};
