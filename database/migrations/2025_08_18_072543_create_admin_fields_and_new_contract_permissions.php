<?php

use App\Models\GroupPermissions;
use App\Models\Permissions;
use App\Models\PoliciesTabs;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create Admin Fields policy tab and permissions
        $adminFieldsPolicy = PoliciesTabs::where('tabs', 'Admin Fields')->first();
        if (! $adminFieldsPolicy) {
            // Create Admin Fields policy tab
            DB::statement("insert into `policies_tabs` (`policies_id`, `tabs`, `created_at`, `updated_at`) VALUES (25, 'Admin Fields', NOW(), NOW())");

            // Create Admin Fields permissions
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ((select `id` from `policies_tabs` where `tabs` = 'Admin Fields'), 'admin-fields-add', 'Add', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ((select `id` from `policies_tabs` where `tabs` = 'Admin Fields'), 'admin-fields-edit', 'Edit', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ((select `id` from `policies_tabs` where `tabs` = 'Admin Fields'), 'admin-fields-view', 'View', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ((select `id` from `policies_tabs` where `tabs` = 'Admin Fields'), 'admin-fields-delete', 'Delete', NOW(), NOW())");
        }

        // Create New Contract policy tab and permissions
        $newContractPolicy = PoliciesTabs::where('tabs', 'New Contract')->first();
        if (! $newContractPolicy) {
            // Create New Contract policy tab
            DB::statement("insert into `policies_tabs` (`policies_id`, `tabs`, `created_at`, `updated_at`) VALUES (25, 'New Contract', NOW(), NOW())");

            // Create New Contract permissions (only view)
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ((select `id` from `policies_tabs` where `tabs` = 'New Contract'), 'new-contract-view', 'View', NOW(), NOW())");
        }

        // Optional: Copy permissions from Organisation to Admin Fields (if needed)
        $adminFieldsPolicy = PoliciesTabs::where('tabs', 'Admin Fields')->first();
        $organisationPolicy = PoliciesTabs::where('tabs', 'Organisation')->first();

        if ($adminFieldsPolicy && $organisationPolicy) {
            $groups = GroupPermissions::where('policies_tabs_id', $organisationPolicy->id)->groupBy('group_id')->get();

            foreach ($groups as $group) {
                $permissions = [
                    'organisation-add' => 'admin-fields-add',
                    'organisation-edit' => 'admin-fields-edit',
                    'organisation-delete' => 'admin-fields-delete',
                    'organisation-view' => 'admin-fields-view',
                ];

                foreach ($permissions as $orgPermission => $adminPermission) {
                    $perm = Permissions::where('name', $orgPermission)->first();
                    if ($groupPermission = GroupPermissions::where(['group_id' => $group->group_id, 'permissions_id' => $perm->id])->first()) {
                        $adminFieldsPermission = Permissions::where('name', $adminPermission)->first();
                        if ($adminFieldsPermission) {
                            GroupPermissions::create([
                                'group_id' => $groupPermission->group_id,
                                'role_id' => $groupPermission->role_id,
                                'group_policies_id' => $groupPermission->group_policies_id,
                                'policies_tabs_id' => $adminFieldsPolicy->id,
                                'permissions_id' => $adminFieldsPermission->id,
                            ]);
                        }
                    }
                }
            }
        }

        // Optional: Copy view permission from Organisation to New Contract (if needed)
        $newContractPolicy = PoliciesTabs::where('tabs', 'New Contract')->first();

        if ($newContractPolicy && $organisationPolicy) {
            $groups = GroupPermissions::where('policies_tabs_id', $organisationPolicy->id)->groupBy('group_id')->get();

            foreach ($groups as $group) {
                $perm = Permissions::where('name', 'organisation-view')->first();
                if ($groupPermission = GroupPermissions::where(['group_id' => $group->group_id, 'permissions_id' => $perm->id])->first()) {
                    $newContractPermission = Permissions::where('name', 'new-contract-view')->first();
                    if ($newContractPermission) {
                        GroupPermissions::create([
                            'group_id' => $groupPermission->group_id,
                            'role_id' => $groupPermission->role_id,
                            'group_policies_id' => $groupPermission->group_policies_id,
                            'policies_tabs_id' => $newContractPolicy->id,
                            'permissions_id' => $newContractPermission->id,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove Admin Fields permissions and policy tab
        $adminFieldsPolicy = PoliciesTabs::where('tabs', 'Admin Fields')->first();
        if ($adminFieldsPolicy) {
            GroupPermissions::where('policies_tabs_id', $adminFieldsPolicy->id)->delete();
            Permissions::where('policies_tabs_id', $adminFieldsPolicy->id)->delete();
            $adminFieldsPolicy->delete();
        }

        // Remove New Contract permissions and policy tab
        $newContractPolicy = PoliciesTabs::where('tabs', 'New Contract')->first();
        if ($newContractPolicy) {
            GroupPermissions::where('policies_tabs_id', $newContractPolicy->id)->delete();
            Permissions::where('policies_tabs_id', $newContractPolicy->id)->delete();
            $newContractPolicy->delete();
        }
    }
};
