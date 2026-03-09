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
            $sale = [
                [
                    'category_id' => 1,
                    'name' => 'closing_date',
                    'label' => 'Closing Date',
                    'sequence' => 38,
                    'is_mandatory' => 0,
                    'is_custom' => 0,
                    'section_name' => 'Dates',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'category_id' => 1,
                    'name' => 'funding_date',
                    'label' => 'Funding Date',
                    'sequence' => 39,
                    'is_mandatory' => 0,
                    'is_custom' => 0,
                    'section_name' => 'Dates',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'category_id' => 1,
                    'name' => 'gross_revenue',
                    'label' => 'Gross Revenue',
                    'sequence' => 40,
                    'is_mandatory' => 0,
                    'is_custom' => 0,
                    'section_name' => 'Job Details',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'category_id' => 1,
                    'name' => 'fee_percentage',
                    'label' => 'Fee Percentage',
                    'sequence' => 41,
                    'is_mandatory' => 0,
                    'is_custom' => 0,
                    'section_name' => 'Job Details',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ];
            $i = 42;
            $triggerDate = getTriggerDatesForSample();
            foreach ($triggerDate as $date) {
                $isCustom = 1;
                if (isset($date['custom'])) {
                    $isCustom = $date['custom'];
                }
                $sale[] = [
                    'category_id' => 1,
                    'name' => $date['name'],
                    'label' => $date['name'],
                    'sequence' => $i,
                    'is_mandatory' => 0,
                    'is_custom' => $isCustom,
                    'section_name' => 'Dates',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $i++;
            }
            foreach ($sale as $sale) {
                ImportCategoryDetails::updateOrCreate(
                    ['name' => $sale['name']],
                    $sale
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $fields = [
            'closing_date',
            'funding_date',
            'gross_revenue',
            'fee_percentage',
        ];

        $triggerDate = getTriggerDatesForSample();
        foreach ($triggerDate as $date) {
            $fields[] = $date['name'];
        }

        ImportCategoryDetails::whereIn('name', $fields)->delete();
    }
};
