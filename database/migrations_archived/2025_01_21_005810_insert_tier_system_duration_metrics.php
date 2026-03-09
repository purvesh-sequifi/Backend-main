<?php

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

        $tableName = 'tier_durations';
        $tierDurationValues = ['Per Pay Period', 'Weekly', 'Monthly', 'Quarterly', 'Semi-Annually', 'Annually', 'Per Recon Period', 'On Demand'];
        foreach ($tierDurationValues as $snglDurationVal) {
            $tierId = DB::table($tableName)->where('value', $snglDurationVal)->value('id');
            if (! $tierId) {
                DB::statement('insert into `'.$tableName.'` ( `value`, `created_at`, `updated_at`) VALUES ( "'.$snglDurationVal.'", NOW(), NOW())');
            }
        }

        $tableName = 'tier_systems';
        $tierSystemValues = ['Tiered based on Individual performance', 'Tiered based on Office Performance', 'Tiered based on hiring/ recruitment performance'];
        foreach ($tierSystemValues as $snglSystemVal) {
            $tierId = DB::table($tableName)->where('value', $snglSystemVal)->value('id');
            if (! $tierId) {
                DB::statement('insert into `'.$tableName.'` ( `value`, `created_at`, `updated_at`) VALUES ( "'.$snglSystemVal.'", NOW(), NOW())');
            }
        }

        $tableName = 'tier_metrics';
        $tierMetricValues = ['Accounts Sold', 'Account Serviced', 'Revenue Sold', 'Revenue Serviced', 'Average Contract Value', 'Service Rate', 'Cancellation Rate', 'Autopay Enrolment Rate', 'Accounts Sold', 'Account Serviced', 'Revenue Sold', 'Revenue Serviced', 'Average Contract Value', 'Service Rate', 'Cancellation Rate', 'Autopay Enrolment Rate', 'Workers Hired', 'Leads Generated', 'Workers Managed'];
        $tierMetricSystemIds = ['1', '1', '1', '1', '1', '1', '1', '1', '2', '2', '2', '2', '2', '2', '2', '2', '3', '3', '3'];
        foreach ($tierMetricValues as $key => $snglMetricVal) {
            $tierId = DB::table($tableName)->where('value', $snglMetricVal)->where('tier_system_id', $tierMetricSystemIds[$key])->value('id');
            if (! $tierId) {
                DB::statement('insert into `'.$tableName.'` ( `value`, `tier_system_id`, `created_at`, `updated_at`) VALUES ( "'.$snglMetricVal.'", "'.$tierMetricSystemIds[$key].'", NOW(), NOW())');
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
        //
    }
};
