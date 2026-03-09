<?php

use App\Models\OnboardingEmployeeRedline;
use App\Models\OnboardingEmployees;
use App\Models\OnboardingUserRedline;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserCommissionLock;
use App\Models\UserOverrides;
use App\Models\UserOverridesLock;
use App\Models\UserRedlines;
use App\Models\UserTransferHistory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        UserCommission::where(['redline_type' => 'Shift based on Location'])->update(['redline_type' => 'Shift Based on Location']);
        UserCommissionLock::where(['redline_type' => 'Shift based on Location'])->update(['redline_type' => 'Shift Based on Location']);
        UserOverrides::where(['calculated_redline_type' => 'Shift based on Location'])->update(['calculated_redline_type' => 'Shift Based on Location']);
        UserOverridesLock::where(['calculated_redline_type' => 'Shift based on Location'])->update(['calculated_redline_type' => 'Shift Based on Location']);
        UserRedlines::where(['redline_amount_type' => 'Shift based on Location'])->update(['redline_amount_type' => 'Shift Based on Location']);
        UserRedlines::where(['old_redline_amount_type' => 'Shift based on Location'])->update(['old_redline_amount_type' => 'Shift Based on Location']);
        User::where(['redline_amount_type' => 'Shift based on Location'])->update(['redline_amount_type' => 'Shift Based on Location']);
        User::where(['self_gen_redline_amount_type' => 'Shift based on Location'])->update(['self_gen_redline_amount_type' => 'Shift Based on Location']);
        OnboardingEmployees::where(['redline_amount_type' => 'Shift based on Location'])->update(['redline_amount_type' => 'Shift Based on Location']);
        OnboardingEmployees::where(['self_gen_redline_amount_type' => 'Shift based on Location'])->update(['self_gen_redline_amount_type' => 'Shift Based on Location']);
        OnboardingEmployeeRedline::where(['redline_amount_type' => 'Shift based on Location'])->update(['redline_amount_type' => 'Shift Based on Location']);
        OnboardingUserRedline::where(['redline_amount_type' => 'Shift based on Location'])->update(['redline_amount_type' => 'Shift Based on Location']);
        UserTransferHistory::where(['redline_amount_type' => 'Shift based on Location'])->update(['redline_amount_type' => 'Shift Based on Location']);
        UserTransferHistory::where(['old_redline_amount_type' => 'Shift based on Location'])->update(['old_redline_amount_type' => 'Shift Based on Location']);
        UserTransferHistory::where(['self_gen_redline_amount_type' => 'Shift based on Location'])->update(['self_gen_redline_amount_type' => 'Shift Based on Location']);
        UserTransferHistory::where(['old_self_gen_redline_amount_type' => 'Shift based on Location'])->update(['old_self_gen_redline_amount_type' => 'Shift Based on Location']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_redline_histories', function (Blueprint $table) {
            //
        });
    }
};
