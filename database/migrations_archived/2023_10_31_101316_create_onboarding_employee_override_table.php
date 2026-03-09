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
        Schema::create('onboarding_employee_override', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('updater_id');
            $table->date('override_effective_date')->nullable();
            $table->double('direct_overrides_amount', 8, 2)->nullable();
            $table->enum('direct_overrides_type', ['per sale', 'per kw'])->nullable();
            $table->double('indirect_overrides_amount', 8, 2)->nullable();
            $table->enum('indirect_overrides_type', ['per sale', 'per kw'])->nullable();
            $table->double('office_overrides_amount', 8, 2)->nullable();
            $table->enum('office_overrides_type', ['per sale', 'per kw'])->nullable();
            $table->double('office_stack_overrides_amount', 8, 2)->nullable();
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
        Schema::dropIfExists('onboarding_employee_override');
    }
};
