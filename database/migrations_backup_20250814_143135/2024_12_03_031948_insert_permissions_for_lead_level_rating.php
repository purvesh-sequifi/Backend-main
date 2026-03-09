<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // $policiesTabId = DB::table('policies_tabs')
        //     ->where('tabs', 'Lead Level Rating')
        //     ->value('id');

        // if ($policiesTabId) {
        //     // Define the permissions to be inserted
        //     $permissions = [
        //         'lead-level-rating-add' => 'Add',
        //         'lead-level-rating-edit' => 'Edit',
        //         'lead-level-rating-delete' => 'Delete',
        //         'lead-level-rating-view' => 'View',
        //     ];

        //     foreach ($permissions as $permission => $gaurd) {
        //         // Check if the permission already exists
        //         $existingPermission = DB::table('permissions')
        //             ->where('policies_tabs_id', $policiesTabId)
        //             ->where('name', $permission)
        //             ->where('guard_name', $gaurd)
        //             ->exists();

        //         if (!$existingPermission) {
        //             // Insert the permission record
        //             DB::table('permissions')->insert([
        //                 'policies_tabs_id' => $policiesTabId,
        //                 'name' => $permission,
        //                 'guard_name' => $gaurd,
        //                 'created_at' => now(),
        //                 'updated_at' => now(),
        //             ]);
        //         }
        //     }
        // }
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
