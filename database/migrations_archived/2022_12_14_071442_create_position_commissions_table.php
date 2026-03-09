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
        Schema::create('position_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('position_id');
            $table->float('commission_parentage')->nullValue();
            $table->enum('commission_amount_type', ['percent', 'per kw'])->nullable();
            $table->integer('commission_parentag_hiring_locked')->nullable();
            $table->integer('commission_amount_type_locked')->nullable();
            $table->enum('commission_structure_type', ['Tiered', 'Fixed'])->nullable();
            $table->integer('commission_parentag_type_hiring_locked')->nullable();
            $table->timestamps();

            $table->foreign('position_id')->references('id')
                ->on('positions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('compenstion_plan_commissions');
    }
};
