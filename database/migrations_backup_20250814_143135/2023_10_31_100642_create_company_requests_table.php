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
        Schema::create('company_requests', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->nullable();
            $table->unsignedBigInteger('plan_id');
            $table->string('full_name')->nullable();
            $table->string('site_name')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->foreign('plan_id')->references('id')
                ->on('plans')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('company_requests');
    }
};
