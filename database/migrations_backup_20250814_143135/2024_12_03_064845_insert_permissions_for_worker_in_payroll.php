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
        $policyId = DB::table('group_policies')->where('policies', 'PayRoll')->value('id');
        if ($policyId) {

            $policiesTabId = DB::table('policies_tabs')
                ->where('tabs', 'Run Payroll & Approvals')
                ->value('id');

            if ($policiesTabId) {
                // Define the permissions to be inserted
                $permissions = [
                    'run-payroll-admin-request' => 'Admin Request',
                    'run-payroll-worker' => 'Add Worker',
                    'run-payroll-pay-now' => 'Pay Now',
                ];

                foreach ($permissions as $permission => $gaurd) {
                    // Check if the permission already exists
                    $existingPermission = DB::table('permissions')
                        ->where('policies_tabs_id', $policiesTabId)
                        ->where('name', $permission)
                        ->where('guard_name', $gaurd)
                        ->exists();

                    if (! $existingPermission) {
                        // Insert the permission record
                        DB::table('permissions')->insert([
                            'policies_tabs_id' => $policiesTabId,
                            'name' => $permission,
                            'guard_name' => $gaurd,
                            'created_at' => now(),
                            'updated_at' => now(),
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
