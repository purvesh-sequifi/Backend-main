<?php

use App\Models\CompanyProfile;
use App\Models\ImportCategoryDetails;
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
        Schema::table('sale_masters', function (Blueprint $table) {
            $table->string('sale_product_name')->nullable()->after('product_code');
        });
        Schema::table('legacy_api_data_null', function (Blueprint $table) {
            $table->string('sale_product_name')->nullable()->after('product_code');
        });
        Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
            $table->string('sale_product_name')->nullable()->after('product_code');
        });

        $companyProfile = CompanyProfile::first();
        if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
            ImportCategoryDetails::where('sequence', '>=', 31)->update(['sequence' => DB::raw('sequence + 1')]);
            ImportCategoryDetails::create([
                'category_id' => 1,
                'name' => 'sale_product_name',
                'label' => 'Product Name',
                'sequence' => 31,
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'PID Details',
            ]);
        } elseif (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            ImportCategoryDetails::where('sequence', '>=', 14)->update(['sequence' => DB::raw('sequence + 1')]);
            ImportCategoryDetails::create([
                'category_id' => 1,
                'name' => 'sale_product_name',
                'label' => 'Product Name',
                'sequence' => 14,
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'PID Details',
            ]);
        } elseif ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            ImportCategoryDetails::where('sequence', '>=', 30)->update(['sequence' => DB::raw('sequence + 1')]);
            ImportCategoryDetails::create([
                'category_id' => 1,
                'name' => 'sale_product_name',
                'label' => 'Product Name',
                'sequence' => 30,
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'PID Details',
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
        Schema::table('sale_masters', function (Blueprint $table) {
            //
        });
    }
};
