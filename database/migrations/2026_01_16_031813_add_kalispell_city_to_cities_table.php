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
        // Check if Kalispell doesn't already exist for Montana (state_id = 46)
        $exists = DB::table('cities')
            ->where('name', 'Kalispell')
            ->where('state_id', 46)
            ->exists();

        if (!$exists) {
            DB::table('cities')->insert([
                'name' => 'Kalispell',
                'state_id' => 46,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove Kalispell city from Montana
        DB::table('cities')
            ->where('name', 'Kalispell')
            ->where('state_id', 46)
            ->delete();
    }
};
