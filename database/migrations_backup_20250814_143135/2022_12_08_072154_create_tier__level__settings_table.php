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
        Schema::create('tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tier_type_id');
            $table->unsignedBigInteger('tier_setting_id');
            $table->enum('scale_based_on', ['Monthly', 'Bi-Monthly', 'Quaterly', 'Semi-Annually', 'Annually'])->nullable();
            $table->enum('shifts_on', ['Monthly', 'Bi-Monthly', 'Quaterly', 'Semi-Annually', 'Annually'])->nullable();
            $table->enum('rest', ['Monthly', 'Bi-Monthly', 'Quaterly', 'Semi-Annually', 'Annually'])->nullable();
            $table->timestamps();

            $table->foreign('tier_type_id')->references('id')
                ->on('tiers_type')->onDelete('cascade');
            $table->foreign('tier_setting_id')->references('id')
                ->on('tier_settings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tier__level__settings');
    }
};
