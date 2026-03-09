<?php

use App\Models\CompanyProfile;
use App\Models\ImportCategoryDetails;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
        if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
            ImportCategoryDetails::where('label', 'Adders Description')->update(['label' => 'Notes', 'section_name' => 'Notes', 'name' => 'notes']);
        } elseif (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            if (! ImportCategoryDetails::where('label', 'Notes')->exists()) {
                ImportCategoryDetails::where('sequence', '>=', 25)->update(['sequence' => DB::raw('sequence + 1')]);
                ImportCategoryDetails::create([
                    'category_id' => 1,
                    'name' => 'notes',
                    'label' => 'Notes',
                    'sequence' => 25,
                    'is_mandatory' => 0,
                    'is_custom' => 0,
                    'section_name' => 'Notes',
                ]);
            }
        } else {
            ImportCategoryDetails::where('label', 'Adders Description')->update(['label' => 'Notes', 'section_name' => 'Notes', 'name' => 'notes']);
        }
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
