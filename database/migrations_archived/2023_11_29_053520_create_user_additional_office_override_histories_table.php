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
        Schema::create('user_additional_office_override_histories', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('updater_id');
            $table->date('override_effective_date');
            $table->integer('state_id');
            $table->integer('office_id');
            $table->double('office_overrides_amount', 8, 2)->nullable();
            $table->enum('office_overrides_type', ['per sale', 'per kw']);
            $table->double('old_office_overrides_amount', 8, 2)->nullable();
            $table->enum('old_office_overrides_type', ['per sale', 'per kw']);
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
        Schema::dropIfExists('user_additional_office_override_histories');
    }
};
