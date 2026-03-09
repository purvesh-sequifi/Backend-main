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
        if (! Schema::hasTable('s_clearance_turn_responses')) {
            Schema::create('s_clearance_turn_responses', function (Blueprint $table) {
                $table->id();
                $table->string('turn_id')->nullable();
                $table->string('worker_id')->nullable();
                $table->string('webhook_type')->nullable();
                $table->string('status')->nullable();
                $table->longText('response')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('s_clearance_turn_responses');
    }
};
