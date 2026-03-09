<?php

namespace Database\Seeders;

use App\Models\CompanyProfile;
use App\Models\MilestoneSchemaTrigger;
use Illuminate\Database\Seeder;

class MilestoneSchemaTriggerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        //         $companyProfile =CompanyProfile::first();
        //         //  CompanyProfile::PEST_COMPANY_TYPE;
        //         if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
        //         MilestoneSchemaTrigger::create([
        //             'milestone_schema_id' => 1,
        //             'name' => 'Pyament-1',
        //             'on_trigger'=>'Initial Service Date',
        //         ]);
        //         MilestoneSchemaTrigger::create([
        //             'milestone_schema_id' => 1,
        //             'name' => 'Pyament-2',
        //             'on_trigger'=>'Service Completion Date',
        //         ]);
        //         MilestoneSchemaTrigger::create([
        //             'milestone_schema_id' => 2,
        //             'name' => 'Final Payment',
        //             'on_trigger'=>'Initial Service Date',
        //         ]);
        //         MilestoneSchemaTrigger::create([
        //             'milestone_schema_id' => 3,
        //             'name' => 'Monday Payment',
        //             'on_trigger'=>'Service Completion Date',
        //         ]);
        //     }else if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE  || $companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE){
        //         MilestoneSchemaTrigger::create([
        //             'milestone_schema_id' => 1,
        //             'name' => 'Pyament-1',
        //             'on_trigger'=>'m1_date',
        //         ]);
        //         MilestoneSchemaTrigger::create([
        //             'milestone_schema_id' => 1,
        //             'name' => 'Pyament-2',
        //             'on_trigger'=>'m2_date',
        //         ]);
        //         MilestoneSchemaTrigger::create([
        //             'milestone_schema_id' => 2,
        //             'name' => 'Final Payment',
        //             'on_trigger'=>'m1_date',
        //         ]);
        //         MilestoneSchemaTrigger::create([
        //             'milestone_schema_id' => 3,
        //             'name' => 'Monday Payment',
        //             'on_trigger'=>'m2_date',
        //         ]);
        //     }
    }
}
