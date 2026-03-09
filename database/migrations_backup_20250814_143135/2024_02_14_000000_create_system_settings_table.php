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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            // Index for faster lookups
            $table->index(['key', 'group']);
        });

        // Insert the initial setting for FieldRoutes sync
        DB::table('system_settings')->insert([
            'key' => 'fieldroutes_sync_last_run',
            'value' => null,
            'group' => 'fieldroutes',
            'description' => 'Last successful run timestamp for FieldRoutes data synchronization',
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
        Schema::dropIfExists('system_settings');
    }
};
