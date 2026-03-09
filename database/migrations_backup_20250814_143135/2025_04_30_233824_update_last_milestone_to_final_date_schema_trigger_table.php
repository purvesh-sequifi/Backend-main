<?php

use App\Models\CompanyProfile;
use App\Models\MilestoneSchemaTrigger;
use App\Models\SchemaTriggerDate;
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

        if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE) {
            try {

                if (env('DOMAIN_NAME') == 'kinhome') {
                    $finalPayment = 'M3 Date';
                } else {
                    $finalPayment = 'M2 Date';
                }

                $latestTriggerDate = SchemaTriggerDate::where('name', $finalPayment)->first();

                if ($latestTriggerDate) {
                    $latestTriggerDate->update([
                        'name' => 'Final Payment',
                    ]);
                }

                $lastMilestoneTriggers = MilestoneSchemaTrigger::where('on_trigger', $finalPayment)->get()->toArray();

                if (! empty($lastMilestoneTriggers)) {
                    foreach ($lastMilestoneTriggers as $milestone) {
                        MilestoneSchemaTrigger::where('id', $milestone['id'])->update([
                            'on_trigger' => 'Final Payment',
                        ]);
                    }
                }

            } catch (\Exception $e) {
                throw $e;
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
        Schema::table('schema_trigger_dates', function (Blueprint $table) {
            //
        });
    }
};
