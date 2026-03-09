<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::table('buckets', function (Blueprint $table) {
            //
        });
        DB::table('buckets')->insert([
            'bucket_type' => 'CRM',
            'name' => 'New Jobs',
            'display_order' => 1,
            'colour_code' => '#FFFFF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('buckets')->insert([
            'bucket_type' => 'CRM',
            'name' => 'Cancelled',
            'display_order' => 2,
            'colour_code' => '#FFFFF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('buckets', function (Blueprint $table) {
            //
        });
    }
};
