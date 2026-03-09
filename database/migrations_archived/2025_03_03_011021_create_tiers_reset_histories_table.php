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
        Schema::table('users_tiers_histories', function (Blueprint $table) {
            $table->removeColumn('tiers_type');
            $table->unsignedBigInteger('tiers_history_id')->after('product_id');
        });

        Schema::create('tiers_reset_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('updater_id')->nullable();
            $table->unsignedBigInteger('tier_schema_id')->nullable();
            $table->string('tiers_type')->nullable()->comment('Progressive, Retroactive');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->dateTime('reset_date_time')->nullable();
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
        Schema::dropIfExists('tiers_reset_histories');
    }
};
