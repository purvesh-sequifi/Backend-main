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

        $policyTabId = DB::table('policies_tabs')->where('policies_id', '16')->where('tabs', 'Scheduling')->value('id');
        if (! $policyTabId) {
            $policies_tabs_id = DB::table('policies_tabs')->insertGetId([
                'policies_id' => '16',
                'tabs' => 'Scheduling',
                'created_at' => NOW(),
                'updated_at' => NOW(),
            ]);

            DB::statement("insert into `permissions` ( `policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'scheduling-add','Add', NOW(), NOW())");
            DB::statement("insert into `permissions` ( `policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'scheduling-edit','Edit', NOW(), NOW())");
            DB::statement("insert into `permissions` ( `policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'scheduling-delete','Delete', NOW(), NOW())");
            DB::statement("insert into `permissions` ( `policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'scheduling-view','View', NOW(), NOW())");
            DB::statement("insert into `permissions` ( `policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'scheduling-timesheet-approval','Timesheet Approval', NOW(), NOW())");
        }

        $policyTabId = DB::table('policies_tabs')->where('policies_id', '13')->where('tabs', 'My Wages')->value('id');
        if (! $policyTabId) {
            $policies_tabs_id = DB::table('policies_tabs')->insertGetId([
                'policies_id' => '13',
                'tabs' => 'My Wages',
                'created_at' => NOW(),
                'updated_at' => NOW(),
            ]);

            DB::statement("insert into `permissions` ( `policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'wages-add','Add', NOW(), NOW())");
            DB::statement("insert into `permissions` ( `policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'wages-edit','Edit', NOW(), NOW())");
            DB::statement("insert into `permissions` ( `policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'wages-delete','Delete', NOW(), NOW())");
            DB::statement("insert into `permissions` ( `policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ($policies_tabs_id, 'wages-view','View', NOW(), NOW())");
        }

        $groupPolicyId = DB::table('group_policies')->where('role_id', '2')->where('policies', 'Arena')->value('id');
        if (! $groupPolicyId) {
            $group_policies_id = DB::table('group_policies')->insertGetId([
                'role_id' => '2',
                'policies' => 'Arena',
                'created_at' => NOW(),
                'updated_at' => NOW(),
            ]);

            $policies_tabs_id = DB::table('policies_tabs')->insertGetId([
                'policies_id' => $group_policies_id,
                'tabs' => 'Event Page',
                'created_at' => NOW(),
                'updated_at' => NOW(),
            ]);
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'Arena-events-page-add','Add', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'Arena-events-page-edit','Edit', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'Arena-events-page-delete','Delete', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'Arena-events-page-view','View', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'Arena-events-page-create-an-event','Create an event', NOW(), NOW())");

            $policies_tabs_id1 = DB::table('policies_tabs')->insertGetId([
                'policies_id' => $group_policies_id,
                'tabs' => 'All Events',
                'created_at' => NOW(),
                'updated_at' => NOW(),
            ]);
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id1, 'Arena-events-all-add','Add', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id1, 'Arena-events-all-edit','Edit', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id1, 'Arena-events-all-delete','Delete', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id1, 'Arena-events-all-view','View', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id1, 'Arena-events-all-finalize','Finalize', NOW(), NOW())");
        }

        $policyTabId = DB::table('policies_tabs')->where('policies_id', '2')->where('tabs', 'Products')->value('id');
        if (! $policyTabId) {
            $policies_tabs_id = DB::table('policies_tabs')->insertGetId([
                'policies_id' => '2',
                'tabs' => 'Products',
                'created_at' => NOW(),
                'updated_at' => NOW(),
            ]);

            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'products-add','Add', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'products-edit','Edit', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'products-delete','Delete', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'products-view','View', NOW(), NOW())");
        }

        $policyTabId = DB::table('policies_tabs')->where('policies_id', '2')->where('tabs', 'Milestones')->value('id');
        if (! $policyTabId) {
            $policies_tabs_id = DB::table('policies_tabs')->insertGetId([
                'policies_id' => '2',
                'tabs' => 'Milestones',
                'created_at' => NOW(),
                'updated_at' => NOW(),
            ]);

            DB::statement("insert into `permissions` ( `policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'milestones-add','Add', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'milestones-edit','Edit', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'milestones-delete','Delete', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'milestones-view','View', NOW(), NOW())");
        }

        $policyTabId = DB::table('policies_tabs')->where('policies_id', '14')->where('tabs', 'Hiring Permissions')->value('id');
        if (! $policyTabId) {
            $policies_tabs_id = DB::table('policies_tabs')->insertGetId([
                'policies_id' => '14',
                'tabs' => 'Hiring Permissions',
                'created_at' => NOW(),
                'updated_at' => NOW(),
            ]);

            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'hiring-hire','Hire', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'hiring-offer-reviewer','Offer Reviewer', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'hiring-special-reviewer','Special Reviewer', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'hiring-document-reviewer','Document Reviewer', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'hiring-hire-directly','Hire Directly', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ( $policies_tabs_id, 'hiring-offer-bonus','Offer Bonus', NOW(), NOW())");

            $policyTabIds = DB::table('permissions')->where('policies_tabs_id', '33')->whereIn('guard_name', ['Hire Now', 'Hire Directly', 'Offer Bonus'])->get();
            foreach ($policyTabIds as $snglPermissionId) {

                if ($snglPermissionId->guard_name == 'Hire Now') {
                    // return $snglPermissionId;
                    $policyTabIds1 = DB::table('permissions')->where('policies_tabs_id', $policies_tabs_id)->where('guard_name', 'Document Reviewer')->first();
                    if ($policyTabIds1) {
                        $groupPermissionData = DB::table('group_permissions')->where('policies_tabs_id', '33')->where('permissions_id', $snglPermissionId->id)->first();
                        if ($groupPermissionData) {
                            DB::table('group_permissions')->where(['policies_tabs_id' => '33', 'permissions_id' => $snglPermissionId->id])->update(['policies_tabs_id' => $policyTabIds1->policies_tabs_id, 'permissions_id' => $policyTabIds1->id]);
                        }
                    }
                }

                if ($snglPermissionId->guard_name == 'Hire Directly') {
                    $policyTabIds1 = DB::table('permissions')->where('policies_tabs_id', $policies_tabs_id)->where('guard_name', 'Hire Directly')->first();
                    if ($policyTabIds1) {
                        $groupPermissionData = DB::table('group_permissions')->where('policies_tabs_id', '33')->where('permissions_id', $snglPermissionId->id)->first();
                        if ($groupPermissionData) {
                            DB::table('group_permissions')->where(['policies_tabs_id' => '33', 'permissions_id' => $snglPermissionId->id])->update(['policies_tabs_id' => $policyTabIds1->policies_tabs_id, 'permissions_id' => $policyTabIds1->id]);
                        }
                    }
                }

                if ($snglPermissionId->guard_name == 'Offer Bonus') {
                    $policyTabIds1 = DB::table('permissions')->where('policies_tabs_id', $policies_tabs_id)->where('guard_name', 'Offer Bonus')->first();
                    if ($policyTabIds1) {
                        $groupPermissionData = DB::table('group_permissions')->where('policies_tabs_id', '33')->where('permissions_id', $snglPermissionId->id)->first();
                        if ($groupPermissionData) {
                            DB::table('group_permissions')->where(['policies_tabs_id' => '33', 'permissions_id' => $snglPermissionId->id])->update(['policies_tabs_id' => $policyTabIds1->policies_tabs_id, 'permissions_id' => $policyTabIds1->id]);
                        }
                    }
                }

            }

            DB::table('permissions')->where('policies_tabs_id', '33')->whereIn('guard_name', ['Hire Now', 'Hire Directly', 'Offer Bonus'])->delete();

        }

        $hiringStatusId = DB::table('hiring_status')->where('status', 'Offer Review')->where('display_order', '1')->where('hide_status', '0')->where('colour_code', '#E4E9FFF')->where('show_on_card', '1')->value('id');
        if (! $hiringStatusId) {
            DB::statement("insert into `hiring_status` (`status`, `display_order`, `hide_status`, `colour_code`, `show_on_card`, `created_at`, `updated_at`) VALUES ('Offer Review', '1', '0', '#E4E9FFF','1', NOW(), NOW())");
        }

        $hiringStatusId = DB::table('hiring_status')->where('status', 'Special Review')->where('display_order', '1')->where('hide_status', '0')->where('colour_code', '#E4E9FFF')->where('show_on_card', '1')->value('id');
        if (! $hiringStatusId) {
            DB::statement("insert into `hiring_status` (`status`, `display_order`, `hide_status`, `colour_code`, `show_on_card`, `created_at`, `updated_at`) VALUES ('Special Review', '1', '0', '#E4E9FFF','1', NOW(), NOW())");
        }

        $hiringStatusId = DB::table('hiring_status')->where('status', 'Manager Rejected')->where('display_order', '10')->where('hide_status', '0')->where('colour_code', '#E4E9FFF')->where('show_on_card', '1')->value('id');
        if (! $hiringStatusId) {
            DB::statement("insert into `hiring_status` (`status`, `display_order`, `hide_status`, `colour_code`, `show_on_card`, `created_at`, `updated_at`) VALUES ('Manager Rejected', '10', '0', '#E4E9FFF','1', NOW(), NOW())");
        }

        $hiringStatusId = DB::table('hiring_status')->where('status', 'Conditions Rejected')->where('display_order', '12')->where('hide_status', '0')->where('colour_code', '#E4E9FFF')->where('show_on_card', '1')->value('id');
        if (! $hiringStatusId) {
            DB::statement("insert into `hiring_status` (`status`, `display_order`, `hide_status`, `colour_code`, `show_on_card`, `created_at`, `updated_at`) VALUES ('Conditions Rejected', '12', '0', '#E4E9FFF','1', NOW(), NOW())");
        }

        DB::table('hiring_status')->where(['id' => '16', 'status' => 'Hire Now'])->update(['status' => 'Document Review']);

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
