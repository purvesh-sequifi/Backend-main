<?php

use App\Models\CompanyProfile;
use App\Models\ImportCategoryDetails;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $companyProfile = CompanyProfile::first();

        if (
            $companyProfile &&
            $companyProfile->company_type === CompanyProfile::MORTGAGE_COMPANY_TYPE
        ) {
            // First, update sequences for all entries after customer_state (sequence 7) by incrementing by 1
            ImportCategoryDetails::where('category_id', 1)
                ->where('sequence', '>', 8)
                ->increment('sequence', 1);

            // Insert the location_code entry at sequence 8 (after customer_state)
            ImportCategoryDetails::create([
                'category_id' => 1,
                'name' => 'location_code',
                'label' => 'Location Code',
                'sequence' => 9,
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'created_at' => now(),
                'updated_at' => now(),
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
        $companyProfile = CompanyProfile::first();

        if (
            $companyProfile &&
            $companyProfile->company_type === CompanyProfile::MORTGAGE_COMPANY_TYPE
        ) {
            // Remove the location_code entry
            ImportCategoryDetails::where('category_id', 1)
                ->where('name', 'location_code')
                ->delete();

            // Restore sequences by decrementing all entries after sequence 8
            ImportCategoryDetails::where('category_id', 1)
                ->where('sequence', '>', 8)
                ->decrement('sequence', 1);
        }
    }
};
