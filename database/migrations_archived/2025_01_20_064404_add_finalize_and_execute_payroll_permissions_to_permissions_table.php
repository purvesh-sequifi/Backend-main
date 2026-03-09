<?php

use Carbon\Carbon;
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
        Schema::table('permissions', function (Blueprint $table) {
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
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('permissions', function (Blueprint $table) {
            $permissions = [
                'finalize-payroll-view',
                'execute-payroll-view',
            ];

            DB::table('permissions')
                ->whereIn('name', $permissions)
                ->delete();
        });
    }
};
