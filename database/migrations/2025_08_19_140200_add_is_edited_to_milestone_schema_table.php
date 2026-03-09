<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('milestone_schemas', 'is_updated_once')) {
            Schema::table('milestone_schemas', function (Blueprint $table) {
                $table->tinyInteger('is_updated_once')->default(0)->after('status');
            });
        }

        // Update is_updated_once to 1 where positions count is greater than 0
        $positionsProductCount = DB::table('position_products')->where('product_id', 1)->count();

        if ($positionsProductCount > 0) {
            DB::table('milestone_schemas')->update(['is_updated_once' => 1]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('milestone_schemas', function (Blueprint $table) {
            $table->dropColumn('is_updated_once');
        });
    }
};
