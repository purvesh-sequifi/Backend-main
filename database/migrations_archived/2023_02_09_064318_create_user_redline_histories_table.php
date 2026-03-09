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
        Schema::create('user_redline_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('updater_id')->nullable();
            $table->string('redline')->nullable();
            $table->string('redline_type')->nullable();
            $table->string('redline_amount_type')->nullable();
            $table->string('self_gen_user')->nullable()->default(0);
            $table->string('old_redline')->nullable();
            $table->string('old_redline_type')->nullable();
            $table->decimal('withheld_amount')->nullable();
            $table->enum('withheld_type', ['per sale', 'per KW'])->nullable();
            $table->date('withheld_effective_date')->nullable();
            $table->string('old_redline_amount_type')->nullable();
            $table->string('old_self_gen_user')->nullable();
            $table->date('start_date')->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->unsignedBigInteger('position_type')->nullable();
            $table->unsignedBigInteger('sub_position_type')->nullable();
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
        Schema::dropIfExists('user_redline_histories');
    }
};
