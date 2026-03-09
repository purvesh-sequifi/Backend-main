<?php

use App\Models\User;
use App\Models\UserDismissHistory;
use App\Models\UserTerminateHistory;
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
        $users = User::where('dismiss', 1)->get();
        foreach ($users as $user) {
            UserDismissHistory::create([
                'user_id' => $user->id,
                'dismiss' => 1,
                'effective_date' => '2025-05-25',
            ]);
        }

        $users = User::where('terminate', 1)->get();
        foreach ($users as $user) {
            $user->disable_login = 1;
            $user->save();
            UserTerminateHistory::create([
                'user_id' => $user->id,
                'is_terminate' => 1,
                'terminate_effective_date' => '2025-05-25',
            ]);
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
