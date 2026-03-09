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
        Schema::create('override_pool_quarterly_advances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('Agent receiving the advance');
            $table->smallInteger('year')->comment('Calculation year, e.g. 2025');
            $table->decimal('q1_advance', 12, 2)->default(0)->comment('Q1 advance payment made');
            $table->decimal('q2_advance', 12, 2)->default(0)->comment('Q2 advance payment made');
            $table->decimal('q3_advance', 12, 2)->default(0)->comment('Q3 advance payment made');
            $table->timestamps();

            $table->unique(['user_id', 'year'], 'uq_pool_advances_user_year');
            $table->index('year', 'idx_pool_advances_year');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('override_pool_quarterly_advances');
    }
};
