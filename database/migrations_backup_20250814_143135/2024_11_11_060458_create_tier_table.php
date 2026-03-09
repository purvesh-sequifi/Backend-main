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
        Schema::create('tiers_schema', function (Blueprint $table) {
            $table->id();
            $table->string('prefix')->default('TR');
            $table->string('schema_name')->nullable();
            $table->string('schema_description')->nullable();
            $table->integer('tier_system_id')->default(0);
            $table->integer('tier_metrics_id')->default(0);
            $table->string('tier_metrics_type')->nullable();
            $table->string('tier_type')->nullable();
            $table->integer('tier_duration_id')->default(0);
            $table->string('start_day')->nullable();
            $table->string('end_day')->nullable();
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
        Schema::dropIfExists('tiers_schema');
    }
};
