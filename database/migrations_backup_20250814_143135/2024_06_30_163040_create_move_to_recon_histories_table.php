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
        if (! Schema::hasTable('move_to_recon_histories')) {
            Schema::create('move_to_recon_histories', function (Blueprint $table) {
                $table->id();
                $table->enum('type', ['commission', 'overrides', 'clawback', 'adjustments', 'deductions', 'hourlysalary', 'overtime'])->nullable();
                $table->string('type_id')->description('this means row id')->nullable();
                $table->integer('pid')->nullable();
                $table->integer('user_id')->nullable();
                $table->date('pay_period_from')->nullable();
                $table->date('pay_period_to')->nullable();
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
        Schema::dropIfExists('move_to_recon_histories');
    }
};
