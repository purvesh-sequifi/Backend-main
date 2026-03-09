<?php

use App\Models\CompanyProfile;
use App\Models\ImportCategoryDetails;
use App\Models\ImportTemplateDetail;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            ImportCategoryDetails::where('name', 'location_code')->update(['name' => 'customer_state', 'label' => 'Customer State', 'section_name' => 'Customer Details']);
            ImportTemplateDetail::where('excel_field', 'location_code')->update(['excel_field' => 'customer_state']);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_state_migration', function (Blueprint $table) {
            //
        });
    }
};
