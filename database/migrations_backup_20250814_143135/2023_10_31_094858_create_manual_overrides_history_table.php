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
        Schema::create('manual_overrides_history', function (Blueprint $table) {
            $table->id();
            $table->integer('manual_user_id');
            $table->integer('manual_overrides_id');
            $table->integer('user_id');
            $table->integer('updated_by')->nullable();
            $table->double('old_overrides_amount', 8, 2)->nullable();
            $table->string('old_overrides_type')->nullable();
            $table->double('overrides_amount', 8, 2)->nullable();
            $table->enum('overrides_type', ['per sale', 'per kw']);
            $table->date('effective_date')->nullable();
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
        Schema::dropIfExists('manual_overrides_history');
    }
};
