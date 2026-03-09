<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionsFinalizeAndExecuteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                'policies_tabs_id' => 10,
                'name' => 'finalize-payroll-view',
                'guard_name' => 'Finalize Payroll',
            ],
            [
                'policies_tabs_id' => 10,
                'name' => 'execute-payroll-view',
                'guard_name' => 'Execute Payroll',
            ],
        ];

        foreach ($permissions as $permission) {
            $exists = DB::table('permissions')
                ->where('name', $permission['name'])
                ->where('guard_name', $permission['guard_name'])
                ->exists();

            if (! $exists) {
                DB::table('permissions')->insert([
                    'policies_tabs_id' => $permission['policies_tabs_id'],
                    'name' => $permission['name'],
                    'guard_name' => $permission['guard_name'],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }
    }
}
