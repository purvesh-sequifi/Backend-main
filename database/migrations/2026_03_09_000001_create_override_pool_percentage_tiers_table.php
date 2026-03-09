<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('override_pool_percentage_tiers', function (Blueprint $table) {
            $table->id();
            $table->integer('sales_from')->comment('Inclusive lower bound of downline sales range');
            $table->integer('sales_to')->nullable()->comment('Inclusive upper bound; NULL = open-ended (no ceiling)');
            $table->decimal('pool_percentage', 5, 2)->comment('Pool percentage applied at this tier, e.g. 12.00 = 12%');
            $table->tinyInteger('is_active')->default(1)->comment('1 = active, 0 = inactive');
            $table->timestamps();

            $table->index(['sales_from', 'sales_to'], 'idx_pool_tier_range');
        });

        // Seed initial tiers from PRD
        DB::table('override_pool_percentage_tiers')->insert([
            ['sales_from' => 0,   'sales_to' => 400,  'pool_percentage' => 12.00, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['sales_from' => 401, 'sales_to' => 600,  'pool_percentage' => 16.00, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['sales_from' => 601, 'sales_to' => 1400, 'pool_percentage' => 20.00, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('override_pool_percentage_tiers');
    }
};
