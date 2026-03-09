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
        Schema::create('w2_user_transfer_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('updater_id')->default(0);
            $table->date('period_of_agreement')->nullable();
            $table->date('employee_transfer_date')->nullable();
            $table->date('contractor_transfer_date')->nullable();
            $table->string('type', 100)->nullable()->comment('1099, w2');
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
        Schema::dropIfExists('w2_user_transfer_histories');
    }
};
