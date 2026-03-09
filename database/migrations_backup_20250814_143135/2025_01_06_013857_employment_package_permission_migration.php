<?php

use App\Models\GroupPermissions;
use App\Models\PoliciesTabs;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $commissionPolicy = PoliciesTabs::where('tabs', 'Commissions')->first();
        if (! $commissionPolicy) {
            DB::statement("insert into `policies_tabs` (`policies_id`, `tabs`, `created_at`, `updated_at`) VALUES (25, 'Commissions', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ((select `id` from `policies_tabs` order by `id` desc limit 1), 'commissions-add', 'Add', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ((select `id` from `policies_tabs` order by `id` desc limit 1), 'commissions-edit', 'Edit', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ((select `id` from `policies_tabs` order by `id` desc limit 1), 'commissions-delete', 'Delete', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ((select `id` from `policies_tabs` order by `id` desc limit 1), 'commissions-view', 'View', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ((select `id` from `policies_tabs` order by `id` desc limit 1), 'commissions-update-package', 'Update Package', NOW(), NOW())");

            DB::statement("update `policies_tabs` set `tabs` = 'Organisation' WHERE `tabs` = 'Employment Package'");
            DB::statement("update `permissions` set `name` = 'organisation-add' WHERE `name` = 'employment-package-add'");
            DB::statement("update `permissions` set `name` = 'organisation-edit' WHERE `name` = 'employment-package-edit'");
            DB::statement("update `permissions` set `name` = 'organisation-delete' WHERE `name` = 'employment-package-delete'");
            DB::statement("update `permissions` set `name` = 'organisation-view' WHERE `name` = 'employment-package-view'");
            DB::statement("update `permissions` set `name` = 'organisation-transfer-employee' WHERE `name` = 'employment-package-transfer-employee'");
            DB::statement("update `permissions` set `name` = 'organisation-dismiss-users' WHERE `name` = 'employment-package-dismiss-users'");

            $commissionPolicy = PoliciesTabs::where('tabs', 'Commissions')->first();
            $policy = PoliciesTabs::where('tabs', 'Organisation')->first();
            $groups = GroupPermissions::where('policies_tabs_id', $policy->id)->groupBy('group_id')->get();
            foreach ($groups as $group) {
                $permissions = [
                    'organisation-add' => 'commissions-add',
                    'organisation-edit' => 'commissions-edit',
                    'organisation-delete' => 'commissions-delete',
                    'organisation-view' => 'commissions-view',
                    'organisation-transfer-employee' => 'commissions-update-package',
                ];

                foreach ($permissions as $key => $permission) {
                    $perm = Permission::where('name', $key)->first();
                    if ($groupPermission = GroupPermissions::where(['group_id' => $group->group_id, 'permissions_id' => $perm->id])->first()) {
                        $commissionPermission = Permission::where('name', $permission)->first();
                        GroupPermissions::create([
                            'group_id' => $groupPermission->group_id,
                            'role_id' => $groupPermission->role_id,
                            'group_policies_id' => $groupPermission->group_policies_id,
                            'policies_tabs_id' => $commissionPolicy->id,
                            'permissions_id' => $commissionPermission->id,
                        ]);
                    }
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
