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
        Schema::create('ticket_faq_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->tinyInteger('order')->nullable();
            $table->enum('status', ['0', '1'])->default('1')->comment('0 = InActive, 1 = Active');
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
        Schema::dropIfExists('ticket_faq_categories');
    }
};
