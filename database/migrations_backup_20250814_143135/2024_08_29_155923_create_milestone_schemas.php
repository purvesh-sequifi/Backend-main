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
        Schema::create('milestone_schemas', function (Blueprint $table) {
            $table->id();
            // $table->foreignId('milestone_schema_id')->constrained('milestone_schemas')->onDelete('cascade'); // Foreign key to milestone_schemas
            $table->string('prefix')->default('MS');
            $table->string('schema_name'); // Schema Name
            $table->text('schema_description')->nullable(); // Schema Description
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
        Schema::dropIfExists('milestone_schemas');
    }
};
