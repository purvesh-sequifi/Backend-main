<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Insert QuickBooks record into the crms table
     *
     * @return void
     */
    public function up()
    {
        // Check if QuickBooks already exists in the crms table
        $exists = DB::table('crms')
            ->where('name', 'QuickBooks')
            ->exists();

        if (! $exists) {
            DB::table('crms')->insert([
                'name' => 'QuickBooks',
                'logo' => null, // Default logo - update if needed
                'status' => 0, // Assuming 1 means active
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove QuickBooks entry from crms table
        DB::table('crms')
            ->where('name', 'QuickBooks')
            ->delete();
    }
};
