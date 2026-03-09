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
        if (! Schema::hasTable('position_tiers')) {
            Schema::create('position_tiers', function (Blueprint $table) {
                $table->id(); // id bigint(20)
                $table->bigInteger('position_id')->index();
                $table->bigInteger('tiers_schema_id')->index();
                $table->string('tier_advancement', 255);
                $table->enum('type', ['commission', 'upfront', 'override']); // enum for type
                $table->tinyInteger('status');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('position_tiers');
    }
};
