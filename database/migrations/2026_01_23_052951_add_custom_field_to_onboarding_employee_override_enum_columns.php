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
     * Add 'custom field' as a valid value to override type ENUM columns
     * in onboarding_employee_override table.
     */
    public function up(): void
    {
        // Add 'custom field' to direct_overrides_type ENUM
        DB::statement("ALTER TABLE onboarding_employee_override 
            MODIFY direct_overrides_type ENUM('per sale', 'per kw', 'percent', 'custom field') NULL");

        // Add 'custom field' to indirect_overrides_type ENUM
        DB::statement("ALTER TABLE onboarding_employee_override 
            MODIFY indirect_overrides_type ENUM('per sale', 'per kw', 'percent', 'custom field') NULL");

        // Add 'custom field' to office_overrides_type ENUM
        DB::statement("ALTER TABLE onboarding_employee_override 
            MODIFY office_overrides_type ENUM('per sale', 'per kw', 'percent', 'custom field') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'custom field' from direct_overrides_type ENUM
        DB::statement("ALTER TABLE onboarding_employee_override 
            MODIFY direct_overrides_type ENUM('per sale', 'per kw', 'percent') NULL");

        // Remove 'custom field' from indirect_overrides_type ENUM
        DB::statement("ALTER TABLE onboarding_employee_override 
            MODIFY indirect_overrides_type ENUM('per sale', 'per kw', 'percent') NULL");

        // Remove 'custom field' from office_overrides_type ENUM
        DB::statement("ALTER TABLE onboarding_employee_override 
            MODIFY office_overrides_type ENUM('per sale', 'per kw', 'percent') NULL");
    }
};
