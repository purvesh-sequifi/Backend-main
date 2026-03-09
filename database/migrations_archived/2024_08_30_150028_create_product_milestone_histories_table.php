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
        Schema::create('product_milestone_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products');
            $table->foreignId('milestone_schema_id')->constrained('milestone_schemas'); // Milestone Schema
            $table->foreignId('clawback_exempt_on_ms_trigger_id')
                ->constrained('milestone_schema_trigger', 'id')
                ->name('pmh_clawback_fk'); // Shortened constraint name

            $table->date('effective_date')->nullable(); // Effective date

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
        Schema::dropIfExists('product_milestone_histories');
    }
};
