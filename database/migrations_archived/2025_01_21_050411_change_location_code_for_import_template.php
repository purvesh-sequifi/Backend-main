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
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            ImportCategoryDetails::where('name', 'location_code')->update(['is_mandatory' => '0']);
        }
        ImportCategoryDetails::where('name', 'product_id')->update(['is_mandatory' => '0']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
