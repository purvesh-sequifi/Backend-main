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
        Schema::create('marketing_deals_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->date('day_date')->nullable();
            $table->unsignedBigInteger('marketing_setting_id');
            $table->timestamps();

            $table->foreign('marketing_setting_id')->references('id')
                ->on('marketing__deals__settings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Schema::dropIfExists('marketing__deals__reconciliations');
        Schema::dropIfExists('marketing_deals_reconciliations');
    }
};
