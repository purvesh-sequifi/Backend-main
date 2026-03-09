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
        Schema::create('ticket_faqs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('faq_category_id');
            $table->tinyText('question')->nullable();
            $table->longText('answer')->nullable();
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
        Schema::dropIfExists('ticket_faqs');
    }
};
