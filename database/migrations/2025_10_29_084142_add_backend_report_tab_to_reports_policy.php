<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Only run for specific domains
        $allowedDomains = ['whiteknight', 'evomarketing', 'momentumv2', 'homeguard'];
        $currentDomain = env('DOMAIN_NAME');
        
        if (!in_array($currentDomain, $allowedDomains)) {
            echo "Skipping Backend Report migration - Domain '{$currentDomain}' is not in allowed list.\n";
            echo "Allowed domains: " . implode(', ', $allowedDomains) . "\n";
            return;
        }
        
        echo "Running Backend Report migration for domain: {$currentDomain}\n\n";
        
        $now = Carbon::now();

        // Step 1: Get role IDs dynamically by role names
        $administratorRoleId = DB::table('roles')->where('name', 'administrator')->value('id');
        $standardRoleId = DB::table('roles')->where('name', 'standard')->value('id');

        if (!$administratorRoleId || !$standardRoleId) {
            throw new \Exception('Administrator or Standard role not found');
        }

        $roleIds = [$administratorRoleId, $standardRoleId];

        // Step 2: Find all Reports policies dynamically for both administrator and standard roles
        $reportsPolicies = DB::table('group_policies')
            ->where('policies', 'Reports')
            ->whereIn('role_id', $roleIds)
            ->get();

        if ($reportsPolicies->isEmpty()) {
            throw new \Exception('No Reports policies found for administrator or standard roles');
        }

        // Step 3: Loop through each Reports policy (one for administrator, one for standard)
        foreach ($reportsPolicies as $reportsPolicy) {
            $policyId = $reportsPolicy->id;
            $roleId = $reportsPolicy->role_id;
            $roleName = DB::table('roles')->where('id', $roleId)->value('name');

            echo "Processing Backend Report tab for {$roleName} role (policy_id: {$policyId})\n";

            // Check if the tab already exists for this policy
            $existingTab = DB::table('policies_tabs')
                ->where('tabs', 'Backend Report')
                ->where('policies_id', $policyId)
                ->first();

            if ($existingTab) {
                echo "  - Backend Report tab already exists for {$roleName} role (tab_id: {$existingTab->id})\n";
                continue;
            }

            // Step 4: Insert the new "Backend Report" tab under this Reports policy
            $tabId = DB::table('policies_tabs')->insertGetId([
                'policies_id' => $policyId,
                'tabs' => 'Backend Report',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            echo "  - Created Backend Report tab (tab_id: {$tabId})\n";

            // Step 5: Create role-specific permissions for Backend Report tab
            // Note: Using role prefix to avoid unique constraint conflicts
            // since both administrator and standard roles have the same "Backend Report" tab
            $rolePrefix = $roleId == $administratorRoleId ? 'admin' : 'standard';
            $permissions = [
                ['name' => "{$rolePrefix}-backend-report-add", 'guard_name' => 'Add'],
                ['name' => "{$rolePrefix}-backend-report-edit", 'guard_name' => 'Edit'],
                ['name' => "{$rolePrefix}-backend-report-delete", 'guard_name' => 'Delete'],
                ['name' => "{$rolePrefix}-backend-report-view", 'guard_name' => 'View'],
            ];

            $permissionIds = [];
            foreach ($permissions as $permission) {
                // Check if permission already exists
                $existingPermission = DB::table('permissions')
                    ->where('name', $permission['name'])
                    ->where('guard_name', $permission['guard_name'])
                    ->first();

                if (!$existingPermission) {
                    $permissionId = DB::table('permissions')->insertGetId([
                        'policies_tabs_id' => $tabId,
                        'name' => $permission['name'],
                        'guard_name' => $permission['guard_name'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $permissionIds[] = $permissionId;
                    echo "  - Created permission: {$permission['name']} (id: {$permissionId})\n";
                } else {
                    $permissionIds[] = $existingPermission->id;
                    echo "  - Reusing existing permission: {$permission['name']} (id: {$existingPermission->id})\n";
                }
            }

            // Step 6: Assign permissions to existing groups that have access to this Reports policy
            $groupsWithReportsAccess = DB::table('group_permissions')
                ->where('group_policies_id', $policyId)
                ->select('group_id', 'role_id', 'group_policies_id')
                ->distinct()
                ->get();

            $groupCount = 0;
            foreach ($groupsWithReportsAccess as $group) {
                foreach ($permissionIds as $permissionId) {
                    // Insert only if not already exists
                    $exists = DB::table('group_permissions')
                        ->where('group_id', $group->group_id)
                        ->where('role_id', $group->role_id)
                        ->where('group_policies_id', $group->group_policies_id)
                        ->where('policies_tabs_id', $tabId)
                        ->where('permissions_id', $permissionId)
                        ->exists();

                    if (!$exists) {
                        DB::table('group_permissions')->insert([
                            'group_id' => $group->group_id,
                            'role_id' => $group->role_id,
                            'group_policies_id' => $group->group_policies_id,
                            'policies_tabs_id' => $tabId,
                            'permissions_id' => $permissionId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $groupCount++;
                    }
                }
            }

            echo "  - Assigned permissions to {$groupsWithReportsAccess->count()} group(s) ({$groupCount} total assignments)\n";
            echo "\n";
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Only run for specific domains
        $allowedDomains = ['whiteknight', 'evomarketing', 'momentumv2', 'homeguard'];
        $currentDomain = env('DOMAIN_NAME');
        
        if (!in_array($currentDomain, $allowedDomains)) {
            echo "Skipping Backend Report rollback - Domain '{$currentDomain}' is not in allowed list.\n";
            return;
        }
        
        echo "Rolling back Backend Report migration for domain: {$currentDomain}\n\n";
        
        // Find all Backend Report tabs (for both administrator and standard roles)
        $tabs = DB::table('policies_tabs')
            ->where('tabs', 'Backend Report')
            ->get();

        if ($tabs->isEmpty()) {
            echo "No Backend Report tabs found to delete.\n";
            return;
        }

        foreach ($tabs as $tab) {
            $policy = DB::table('group_policies')->where('id', $tab->policies_id)->first();
            $roleName = $policy ? DB::table('roles')->where('id', $policy->role_id)->value('name') : 'Unknown';
            
            echo "Removing Backend Report tab for {$roleName} role (tab_id: {$tab->id})\n";

            // Delete all group permissions for this tab
            $deletedGroupPerms = DB::table('group_permissions')
                ->where('policies_tabs_id', $tab->id)
                ->delete();
            echo "  - Deleted {$deletedGroupPerms} group permission(s)\n";

            // Delete all permissions for this tab
            $deletedPerms = DB::table('permissions')
                ->where('policies_tabs_id', $tab->id)
                ->delete();
            echo "  - Deleted {$deletedPerms} permission(s)\n";

            // Delete the tab itself
            DB::table('policies_tabs')
                ->where('id', $tab->id)
                ->delete();
            echo "  - Deleted Backend Report tab\n\n";
        }
    }
};
