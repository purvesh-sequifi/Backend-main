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
        try {

            $position = DB::table('positions')->where('position_name', 'Super Admin')->first();
            if ($position) {
                $userData = DB::table('users')->where('is_super_admin', 1)->get();
                if (count($userData) > 0) {
                    foreach ($userData as $key => $user) {
                        $organization = DB::table('user_organization_history')->where('user_id', $user->id)->where('sub_position_id', '!=', $position->id)->where('effective_date', '<=', date('Y-m-d'))->whereNotNull('sub_position_id')->orderBy('effective_date', 'DESC')->first();
                        if ($organization) {

                            $positionData = DB::table('positions')->where('id', $organization->sub_position_id)->first();
                            if ($positionData) {
                                $upadte = DB::table('users')->where('id', $user->id)->update([
                                    'position_id' => isset($positionData->parent_id) ? $positionData->parent_id : 0,
                                    'sub_position_id' => isset($positionData->id) ? $positionData->id : 0,
                                    'department_id' => isset($positionData->department_id) ? $positionData->department_id : null,
                                ]);
                            }

                            $positionId = DB::table('user_organization_history')->where(['user_id' => $user->id, 'sub_position_id' => $position->id])->delete();

                        }
                    }
                }
            }

        } catch (\Exception $e) {
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
