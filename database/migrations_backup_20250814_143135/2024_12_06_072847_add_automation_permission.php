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
        $roleId = 1;
        $policy = 'Automation';

        // Check if the record already exists
        $exists = DB::table('group_policies')
            ->where('role_id', $roleId)
            ->where('policies', $policy)
            ->exists();

        if (! $exists) {

            $insertedId = DB::table('group_policies')->insertGetId([
                'role_id' => 1,
                'policies' => 'Automation',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $policies_tabsID = DB::table('policies_tabs')->insertGetId([
                'policies_id' => $insertedId,
                'tabs' => 'Automation',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Define the permissions to be inserted
            $permissions = [
                'automation-add' => 'Add',
                'automation-edit' => 'Edit',
                'automation-delete' => 'Delete',
                'automation-view' => 'View',
            ];

            foreach ($permissions as $permission => $gaurd) {
                // Check if the permission already exists
                $existingPermission = DB::table('permissions')
                    ->where('policies_tabs_id', $policies_tabsID)
                    ->where('name', $permission)
                    ->where('guard_name', $gaurd)
                    ->exists();

                if (! $existingPermission) {
                    // Insert the permission record
                    DB::table('permissions')->insert([
                        'policies_tabs_id' => $policies_tabsID,
                        'name' => $permission,
                        'guard_name' => $gaurd,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
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
