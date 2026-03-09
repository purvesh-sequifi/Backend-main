<?php

use App\Models\GroupMaster;
use App\Models\GroupPermissions;
use App\Models\GroupPolicies;
use App\Models\PoliciesTabs;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $policies = GroupPolicies::where(['policies' => 'Setting'])->first();
        $tierPolicy = PoliciesTabs::where('tabs', 'Tiers')->first();
        if ($policies && ! $tierPolicy) {
            DB::statement('insert into `policies_tabs` (`policies_id`, `tabs`, `created_at`, `updated_at`) VALUES ('.$policies->id.", 'Tiers', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ((select `id` from `policies_tabs` order by `id` desc limit 1), 'tiers-add', 'Add', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ((select `id` from `policies_tabs` order by `id` desc limit 1), 'tiers-edit', 'Edit', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ((select `id` from `policies_tabs` order by `id` desc limit 1), 'tiers-delete', 'Delete', NOW(), NOW())");
            DB::statement("insert into `permissions` (`policies_tabs_id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES ((select `id` from `policies_tabs` order by `id` desc limit 1), 'tiers-view', 'View', NOW(), NOW())");

            $groupMaster = GroupMaster::where(['name' => 'Super Admin'])->first();
            if ($groupMaster) {
                $permissions = [
                    'tiers-add',
                    'tiers-edit',
                    'tiers-delete',
                    'tiers-view',
                ];

                foreach ($permissions as $permission) {
                    $tierPolicy = PoliciesTabs::where('tabs', 'Tiers')->first();
                    $tierPermission = Permission::where('name', $permission)->first();
                    $role = Role::where('name', 'administrator')->first();
                    if ($tierPolicy && $tierPermission && $role) {
                        GroupPermissions::create([
                            'group_id' => $groupMaster->id,
                            'role_id' => $role->id,
                            'group_policies_id' => $policies->id,
                            'policies_tabs_id' => $tierPolicy->id,
                            'permissions_id' => $tierPermission->id,
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
        Schema::table('permissions', function (Blueprint $table) {
            //
        });
    }
};
