<?php

use App\Http\Controllers\TestDataController;
use App\Models\CompanyProfile;
use App\Models\ImportCategoryDetails;
use App\Models\ImportTemplate;
use App\Models\ImportTemplateDetail;
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
        if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            $dataArray = [];
            $importCategoryDetails = ImportCategoryDetails::where('category_id', '1')->get();
            foreach ($importCategoryDetails as $importCategoryDetail) {
                $dataArray[$importCategoryDetail->name] = $importCategoryDetail->id;
            }

            ImportCategoryDetails::where('category_id', '1')->delete();
            (new TestDataController)->salesTemplate();

            $templates = ImportTemplate::where('category_id', '1')->get();
            $importCategoryDetails = ImportCategoryDetails::where('category_id', '1')->get();
            foreach ($templates as $template) {
                foreach ($importCategoryDetails as $importCategoryDetail) {
                    if (isset($dataArray[$importCategoryDetail->name])) {
                        ImportTemplateDetail::where(['template_id' => $template->id, 'category_detail_id' => $dataArray[$importCategoryDetail->name]])->update(['category_detail_id' => $importCategoryDetail->id]);
                    } else {
                        ImportTemplateDetail::create([
                            'template_id' => $template->id,
                            'category_detail_id' => $importCategoryDetail->id,
                            'excel_field' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            ImportTemplateDetail::whereIn('template_id', $templates->pluck('id'))->whereNotIn('category_detail_id', $importCategoryDetails->pluck('id'))->delete();
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
