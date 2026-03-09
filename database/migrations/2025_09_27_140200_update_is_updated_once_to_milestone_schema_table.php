<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // if Super Admin position exists
        $superAdminPosition = DB::table('positions')->where('position_name', 'Super Admin')->orWhere('id', '4')->first();

        if (! empty($superAdminPosition)) {
            $positionsProductCount = DB::table('position_products')
                ->where('product_id', 1)
                ->whereNot('position_id', $superAdminPosition->id)
                ->count();
        } else {
            $positionsProductCount = DB::table('position_products')->where('product_id', 1)->count();
        }

        if ($positionsProductCount == 0) { // fixed prev condition (Update is_updated_once to 0 when positionsProductCount is 0 (for new server only))
            DB::table('milestone_schemas')->where('id', 1)->update(['is_updated_once' => 0]);
        }
    }
};
