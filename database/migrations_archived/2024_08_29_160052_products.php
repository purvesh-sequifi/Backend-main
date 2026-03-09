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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Name
            $table->string('product_id'); // Product Id
            $table->text('description')->nullable(); // Description
            $table->foreignId('milestone_schema_id')->constrained('milestone_schemas'); // Milestone Schema
            $table->foreignId('clawback_exempt_on_ms_trigger_id')->constrained('milestone_schema_trigger');
            $table->date('effective_date')->nullable(); // Effective date
            $table->enum('status', ['1', '0'])->default('1'); // Status: active or inactive
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
        Schema::dropIfExists('products');
    }
};
