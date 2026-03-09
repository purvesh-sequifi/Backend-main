<?php

use Database\Seeders\StateMVRCostSeeder;
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
        if (! Schema::hasTable('state_mvr_costs')) {
            Schema::create('state_mvr_costs', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('state_code')->nullable();
                $table->string('cost')->nullable();
                $table->timestamps();
            });
        }
        // Run the StateMVRCostSeeder after creating the table
        $this->seed();
    }

    /**
     * Seed the table after migration.
     */
    private function seed()
    {
        (new StateMVRCostSeeder)->run();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('state_mvr_costs');
    }
};
