<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\GroupMaster;
use App\Models\GroupPermissions;
use App\Models\GroupPolicies;
use App\Models\PoliciesTabs;
use App\Models\Permissions;
use App\Models\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdditionalGroupPermissionsSeeder extends Seeder
{
    /**
     * Add Admin, Standard, and Manager groups with their permissions
     */
    public function run(): void
    {
        // Define additional groups
        $groups = [
            ['id' => 5, 'name' => 'Admin'],
            ['id' => 6, 'name' => 'Standard'],
            ['id' => 7, 'name' => 'Manager'],
        ];

        // Create groups
        foreach ($groups as $groupData) {
            GroupMaster::updateOrCreate(
                ['id' => $groupData['id']],
                ['name' => $groupData['name']]
            );
        }

        // Clear existing permissions for these groups
        DB::table('group_permissions')->whereIn('group_id', [5, 6, 7])->delete();

        // Define permissions structure
        $groupPermissions = [
            // Group 5: Admin
            5 => [
                'administrator' => [
                    'Dashboard', 'Integrations', 'SequiDocs', 'Payroll',
                    'Reports', 'Alerts Center', 'Support'
                ],
                'standard' => [
                    'Dashboard', 'My Earnings', 'Hiring', 'Calendar',
                    'Management', 'Reports', 'Requests & Approvals',
                    'Support', 'SequiDocs', 'Referrals'
                ],
                'profile' => ['Profile']
            ],

            // Group 6: Standard
            6 => [
                'standard' => [
                    'Dashboard', 'My Earnings', 'Hiring', 'Calendar',
                    'Reports', 'Requests & Approvals', 'Support'
                ]
            ],

            // Group 7: Manager
            7 => [
                'standard' => [
                    'Dashboard', 'My Earnings', 'Hiring', 'Calendar',
                    'Management', 'Reports', 'Requests & Approvals', 'Support'
                ],
                'profile' => ['Profile']
            ],
        ];

        // Process each group
        foreach ($groupPermissions as $groupId => $roles) {
            foreach ($roles as $roleName => $policyNames) {
                // Get role
                $role = Roles::where('name', $roleName)->first();
                if (!$role) {
                    continue;
                }

                foreach ($policyNames as $policyName) {
                    // Get policy
                    $policy = GroupPolicies::where('policies', $policyName)
                        ->where('role_id', $role->id)
                        ->first();

                    if (!$policy) {
                        continue;
                    }

                    // Get all tabs for this policy
                    $tabs = PoliciesTabs::where('policies_id', $policy->id)->get();

                    if ($tabs->isEmpty()) {
                        continue;
                    }

                    foreach ($tabs as $tab) {
                        // Get all permissions for this tab
                        $permissions = Permissions::where('policies_tabs_id', $tab->id)->get();

                        if ($permissions->isEmpty()) {
                            continue;
                        }

                        foreach ($permissions as $permission) {
                            // Skip Sales Export permission for Manager group (group_id = 7)
                            // Path: Manager > Standard Policies > Reports > Sales > Export
                            if ($groupId === 7 && $permission->name === 'report-sales-export') {
                                continue;
                            }

                            // Create group permission entry
                            GroupPermissions::create([
                                'group_id' => $groupId,
                                'role_id' => $role->id,
                                'group_policies_id' => $policy->id,
                                'policies_tabs_id' => $tab->id,
                                'permissions_id' => $permission->id,
                            ]);
                        }
                    }
                }
            }
        }
    }
}

