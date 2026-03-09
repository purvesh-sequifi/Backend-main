<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('user_transfer_history')->orderBy('transfer_effective_date')->chunk(100, function ($transfers) {
            foreach ($transfers as $transfer) {
                DB::table('user_department_histories')->insert([
                    'user_id' => $transfer->user_id,
                    'updater_id' => $transfer->updater_id,
                    'department_id' => $transfer->department_id,
                    'old_department_id' => $transfer->old_department_id,
                    'effective_date' => $transfer->transfer_effective_date,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('user_department_histories')->whereIn('id', function ($query) {
            $query->select('id')->from('user_department_histories')
                ->join('user_transfer_history', function ($join) {
                    $join->on('user_department_histories.user_id', '=', 'user_transfer_history.user_id')
                        ->on('user_department_histories.updater_id', '=', 'user_transfer_history.updater_id')
                        ->on('user_department_histories.department_id', '=', 'user_transfer_history.department_id')
                        ->on('user_department_histories.old_department_id', '=', 'user_transfer_history.old_department_id')
                        ->on('user_department_histories.effective_date', '=', 'user_transfer_history.transfer_effective_date');
                });
        })->delete();
    }
};
