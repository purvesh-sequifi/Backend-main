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
        Schema::create('positions_duduction_limits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deduction_setting_id')->nullable();
            $table->unsignedBigInteger('position_id')->nullable();
            $table->integer('status')->nullable()->default(0);
            $table->enum('limit_type', ['$', '%'])->nullable();
            $table->float('limit_ammount')->nullable();
            $table->enum('limit', ['per period'])->nullable();
            $table->timestamps();

            $table->foreign('position_id')->references('id')
                ->on('positions')->onDelete('cascade');
            $table->foreign('deduction_setting_id')->references('id')
                ->on('position_commission_deduction_settings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('positions_duduction_limits');
    }
};
