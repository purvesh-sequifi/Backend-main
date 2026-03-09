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
        Schema::create('milestone_schema_trigger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milestone_schema_id')->constrained('milestone_schemas')->onDelete('cascade'); // Foreign key to milestone_schemas
            $table->string('name'); // Milestone Name
            $table->string('on_trigger'); // Trigger Date
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
        Schema::dropIfExists('milestone_schema_trigger');
    }
};
