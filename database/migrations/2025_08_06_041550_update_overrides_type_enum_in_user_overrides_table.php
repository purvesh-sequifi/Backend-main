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
        DB::statement("ALTER TABLE `user_overrides` 
            MODIFY `overrides_type` ENUM('per sale', 'per kw', 'percent') NULL;");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `user_overrides` 
            MODIFY `overrides_type` ENUM('per sale', 'per kw') NULL;");
    }
};
