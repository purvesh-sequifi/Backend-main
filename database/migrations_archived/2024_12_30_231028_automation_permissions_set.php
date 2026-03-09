<?php

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
        $policiesTabsId = DB::table('permissions')->where('guard_name', 'Rating View')->value('policies_tabs_id');
        $permissionsId = DB::table('permissions')->where('guard_name', 'Rating View')->value('id');
        $groupPoliciesId = DB::table('policies_tabs')->where('tabs', 'like', '%leads%')->value('policies_id');

        if ($policiesTabsId && $permissionsId && $groupPoliciesId) {
            DB::table('group_permissions')->updateOrInsert(
                [
                    'group_id' => 1,
                    'role_id' => 2,
                    'group_policies_id' => $groupPoliciesId,
                    'policies_tabs_id' => $policiesTabsId,
                    'permissions_id' => $permissionsId,
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // Step 2: Add permissions for 'Automation'
        $automationActions = ['automation-add' => 'Add', 'automation-edit' => 'Edit', 'automation-delete' => 'Delete', 'automation-view' => 'View'];

        foreach ($automationActions as $name => $guardName) {
            $policiesTabsId = DB::table('permissions')->where('name', 'like', '%automation%')->value('policies_tabs_id');
            $permissionsId = DB::table('permissions')->where(['name' => $name, 'guard_name' => $guardName])->value('id');
            $groupPoliciesId = DB::table('policies_tabs')->where('tabs', 'Automation')->value('policies_id');

            if ($policiesTabsId && $permissionsId && $groupPoliciesId) {
                DB::table('group_permissions')->updateOrInsert(
                    [
                        'group_id' => 1,
                        'role_id' => 1,
                        'group_policies_id' => $groupPoliciesId,
                        'policies_tabs_id' => $policiesTabsId,
                        'permissions_id' => $permissionsId,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
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
    public function down()
    {
        //
    }
};
