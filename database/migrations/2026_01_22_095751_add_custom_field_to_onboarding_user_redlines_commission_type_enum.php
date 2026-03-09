<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add 'custom field' to the commission_type ENUM for Custom Sales Fields feature
     */
    public function up(): void
    {
        // Alter commission_type ENUM to include 'custom field'
        DB::statement("ALTER TABLE onboarding_user_redlines MODIFY commission_type ENUM('percent', 'per kw', 'per sale', 'custom field') NULL");
        
        // Also check and update upfront_sale_type if it's also an ENUM
        DB::statement("ALTER TABLE onboarding_user_redlines MODIFY upfront_sale_type ENUM('percent', 'per kw', 'per sale', 'custom field') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original ENUM values (this will fail if 'custom field' values exist)
        DB::statement("ALTER TABLE onboarding_user_redlines MODIFY commission_type ENUM('percent', 'per kw', 'per sale') NULL");
        DB::statement("ALTER TABLE onboarding_user_redlines MODIFY upfront_sale_type ENUM('percent', 'per kw', 'per sale') NULL");
    }
};
