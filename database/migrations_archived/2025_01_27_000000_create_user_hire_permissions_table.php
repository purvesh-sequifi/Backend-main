<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('position_hire_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('position_id');
            $table->unsignedBigInteger('granted_by');
            $table->timestamps();

            $table->foreign('position_id')->references('id')->on('positions');
            $table->foreign('granted_by')->references('id')->on('users');

            $table->unique('position_id'); // One row per position
            $table->index('position_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('position_hire_permissions');
    }
};
