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
        Schema::create('margin_of_differences', function (Blueprint $table) {
            $table->id();
            $table->float('difference_parcentage');
            $table->enum('applied_to', ['Setter', 'Closer', 'Manager'])->default('Setter');
            $table->unsignedBigInteger('margin_setting_id');
            $table->timestamps();

            $table->foreign('margin_setting_id')->references('id')
                ->on('margin_settings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('migration_of_differences');
    }
};
