<?php

use App\Models\CompanyProfile;
use Carbon\Carbon;
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
        if (! DB::table('milestone_schemas')->exists() && ! DB::table('products')->exists()) {
            $milestoneId = DB::table('milestone_schemas')->insertGetId([
                'schema_name' => 'Default',
                'schema_description' => 'Default Milestone',
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            $companyProfile = DB::table('company_profiles')->first();
            $companyType = $companyProfile->company_type ?? null;

            $milestoneTriggers = in_array($companyType, CompanyProfile::PEST_COMPANY_TYPE)
                ? [
                    ['name' => 'Initial Service Date', 'on_trigger' => 'Initial Service Date'],
                    ['name' => 'Service Completion Date', 'on_trigger' => 'Service Completion Date'],
                ]
                : [
                    ['name' => 'M1 Date', 'on_trigger' => 'M1 Date'],
                    ['name' => 'M2 Date', 'on_trigger' => 'M2 Date'],
                ];

            foreach ($milestoneTriggers as $trigger) {
                $triggerExists = DB::table('schema_trigger_dates')
                    ->where('name', $trigger['name'])
                    ->exists();

                if (! $triggerExists) {
                    $triggerId = DB::table('schema_trigger_dates')->insertGetId([
                        'name' => $trigger['name'],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);

                    $schemaTriggerExists = DB::table('milestone_schema_trigger')
                        ->where('name', $trigger['name'])
                        ->where('milestone_schema_id', $milestoneId)
                        ->exists();

                    if (! $schemaTriggerExists) {
                        DB::table('milestone_schema_trigger')->insert([
                            'name' => $trigger['name'],
                            'on_trigger' => $trigger['on_trigger'] ?? null,
                            'milestone_schema_id' => $milestoneId,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ]);
                    }
                }
            }
            $productId = DB::table('products')->insertGetId([
                'name' => 'Default',
                'product_id' => 'DBP',
                'description' => 'Default Product',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            $historyExists = DB::table('product_milestone_histories')
                ->where('product_id', $productId)
                ->where('milestone_schema_id', 1)
                ->exists();

            if (! $historyExists) {
                DB::table('product_milestone_histories')->updateOrInsert(
                    [
                        'product_id' => $productId,
                        'milestone_schema_id' => 1,
                        'effective_date' => '2020-01-01',
                    ],
                    [
                        'clawback_exempt_on_ms_trigger_id' => 0,
                        'override_on_ms_trigger_id' => 2,
                        'product_redline' => in_array($companyType, CompanyProfile::PEST_COMPANY_TYPE) ? null : '0',
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {}
};
