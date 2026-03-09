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
        Schema::create('sub_commissions', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->nullable();
            $table->unsignedBigInteger('commissions_id');
            $table->timestamps();

            $table->foreign('commissions_id')->references('id')
                ->on('commissions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sub_commissions');
    }
};
