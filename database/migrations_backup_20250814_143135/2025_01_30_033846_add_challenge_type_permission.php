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
        $groupPolicyId = DB::table('group_policies')->where('role_id', '2')->where('policies', 'Arena')->value('id');
        if ($groupPolicyId) {
            $policyTabId = DB::table('policies_tabs')->where('policies_id', $groupPolicyId)->where('tabs', 'Event Page')->value('id');
            if ($policyTabId) {
                $arenaChallengeId = DB::table('permissions')->where('policies_tabs_id', $policyTabId)->where('name', 'Arena-events-page-challenge')->where('guard_name', 'Challenge')->value('id');
                if (! $arenaChallengeId) {
                    DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policyTabId, 'Arena-events-page-challenge','Challenge', NOW(), NOW())");
                }
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
