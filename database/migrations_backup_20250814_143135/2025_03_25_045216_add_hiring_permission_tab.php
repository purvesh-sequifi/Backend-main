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
        $groupPolicyId = DB::table('group_policies')
            ->where('role_id', 1)
            ->where('policies', 'Setting')
            ->value('id');

        if ($groupPolicyId) {
            // Start Transaction
            DB::beginTransaction();
            try {
                // Insert policy tab and get the ID
                $policyTabId = DB::table('policies_tabs')->insertGetId([
                    'policies_id' => 2,
                    'tabs' => 'Hiring',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Insert permissions for the new policy tab
                DB::table('permissions')->insert([
                    [
                        'policies_tabs_id' => $policyTabId,
                        'name' => 'Hiring-add',
                        'guard_name' => 'Add', // Corrected guard_name
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'policies_tabs_id' => $policyTabId,
                        'name' => 'Hiring-edit',
                        'guard_name' => 'Edit',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'policies_tabs_id' => $policyTabId,
                        'name' => 'Hiring-delete',
                        'guard_name' => 'Delete',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'policies_tabs_id' => $policyTabId,
                        'name' => 'Hiring-view',
                        'guard_name' => 'View',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);

                // Commit transaction
                DB::commit();
            } catch (\Exception $e) {
                // Rollback in case of error
                DB::rollBack();
                throw $e;
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
