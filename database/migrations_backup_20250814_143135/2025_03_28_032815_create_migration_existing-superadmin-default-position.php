<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        try {

            $position = DB::table('positions')->where('position_name', 'Super Admin')->first();
            if ($position) {
                $userData = DB::table('users')->where('is_super_admin', 1)->get();
                if (count($userData) > 0) {
                    foreach ($userData as $key => $user) {
                        $upadte = DB::table('users')->where('id', $user->id)->update([
                            'position_id' => isset($position->parent_id) ? $position->parent_id : 0,
                            'sub_position_id' => isset($position->id) ? $position->id : 0,
                            'department_id' => isset($position->department_id) ? $position->department_id : null,
                        ]);

                        $positionId = DB::table('user_organization_history')->where(['user_id' => $user->id, 'sub_position_id' => $position->id])->value('id');
                        if (! $positionId) {

                            $insertId = DB::table('user_organization_history')->insertGetId([
                                'user_id' => $user->id,
                                'updater_id' => 0,
                                'product_id' => 1,
                                'effective_date' => isset($user->period_of_agreement_start_date) ? $user->period_of_agreement_start_date : date('Y-m-d'),
                                'position_id' => isset($position->parent_id) ? $position->parent_id : null,
                                'sub_position_id' => isset($position->id) ? $position->id : null,
                                'is_manager' => 0,
                                'deleted_at' => null,
                            ]);
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('Migration error: '.$e->getMessage());
            throw $e;
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
