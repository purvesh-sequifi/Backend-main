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
        Schema::create('override_pool_calculations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('Agent for whom the calculation was run');
            $table->smallInteger('year')->comment('Calculation year, e.g. 2025');

            // Step-by-step audit trail
            $table->integer('downline_count')->default(0)->comment('Total number of users in recruitment downline');
            $table->integer('downline_sales')->default(0)->comment('Total sales attributed to downline (excluding user\'s own)');
            $table->decimal('pool_percentage', 5, 2)->nullable()->comment('Pool % assigned from tier lookup');
            $table->decimal('gross_pool_value', 12, 2)->nullable()->comment('downline_sales × pool_rate (informational)');

            // Core calculation outputs
            $table->decimal('part1', 12, 2)->default(0)->comment('Override earned on direct recruits\' personal sales');
            $table->decimal('part2_total', 12, 2)->default(0)->comment('Sum of Part 2 overrides across all direct recruits');
            $table->decimal('total_pool_payment', 12, 2)->default(0)->comment('Part 1 + Part 2 total = annual pool payment');
            $table->decimal('pool_rate', 10, 2)->default(50.00)->comment('Dollar amount per sale used in calculation');

            // Full Part 2 breakdown stored as JSON for auditability
            $table->json('part2_breakdown')->nullable()->comment('Per-direct-recruit Part 2 detail: [{user_id, sales, downline_sales, pool_pct, part2}]');

            // Q4 reconciliation
            $table->decimal('q4_trueup', 12, 2)->nullable()->comment('total_pool_payment minus Q1+Q2+Q3 advances; NULL until advances entered');

            // Error tracking
            $table->string('calculation_error')->nullable()->comment('Error message if calculation could not complete for this user');

            $table->timestamps();

            $table->unique(['user_id', 'year'], 'uq_pool_calc_user_year');
            $table->index('year', 'idx_pool_calc_year');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('override_pool_calculations');
    }
};
