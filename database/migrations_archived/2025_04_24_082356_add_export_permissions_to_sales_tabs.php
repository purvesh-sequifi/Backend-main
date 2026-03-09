<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AddExportPermissionsToSalesTabs extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();

        $tabsPermissions = [
            [
                'tab' => 'My Sales',
                'policies_id' => 13,
                'view' => 'my-sales-view',
                'export' => ['name' => 'my-sales-export', 'guard_name' => 'Export'],
            ],
            [
                'tab' => 'My Overrides',
                'policies_id' => 13,
                'view' => 'my-overrides-view',
                'export' => ['name' => 'my-overrides-export', 'guard_name' => 'Export'],
            ],
            [
                'tab' => 'Sales',
                'policies_id' => 19,
                'view' => 'report-sales-view',
                'export' => ['name' => 'report-sales-export', 'guard_name' => 'Export'],
            ],
        ];

        foreach ($tabsPermissions as $tabPermission) {
            $tab = DB::table('policies_tabs')->where(['tabs' => $tabPermission['tab'], 'policies_id' => $tabPermission['policies_id']])->first();

            if ($tab) {
                // Insert the export permission if it doesn't exist
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => $tabPermission['export']['name'],
                    'guard_name' => $tabPermission['export']['guard_name'],
                    'policies_tabs_id' => $tab->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Get all group permissions with the view permission
                $viewPermission = DB::table('permissions')->where('name', $tabPermission['view'])->first();

                if ($viewPermission) {
                    $groupPermissions = DB::table('group_permissions')->where('permissions_id', $viewPermission->id)->get();

                    foreach ($groupPermissions as $gp) {
                        DB::table('group_permissions')->insertOrIgnore([
                            'group_id' => $gp->group_id,
                            'role_id' => $gp->role_id,
                            'group_policies_id' => $gp->group_policies_id,
                            'policies_tabs_id' => $tab->id,
                            'permissions_id' => $permissionId,
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $permissionNames = [
            'my-sales-export',
            'my-overrides-export',
            'report-sales-export',
        ];

        $permissions = DB::table('permissions')->whereIn('name', $permissionNames)->get();

        foreach ($permissions as $permission) {
            DB::table('group_permissions')->where('permissions_id', $permission->id)->delete();
            DB::table('permissions')->where('id', $permission->id)->delete();
        }
    }
}
