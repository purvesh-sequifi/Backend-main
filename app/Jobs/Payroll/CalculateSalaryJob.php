<?php

declare(strict_types=1);

namespace App\Jobs\Payroll;

use App\Models\User;
use App\Models\PositionWage;
use Illuminate\Bus\Queueable;
use App\Models\FrequencyType;
use Illuminate\Support\Facades\Log;
use App\Models\PositionPayFrequency;
use Illuminate\Queue\SerializesModels;
use App\Models\UserOrganizationHistory;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Calculate salary for a user on clock-in.
 * 
 * This job runs asynchronously after a clock-in request to calculate
 * the user's salary for the current pay period based on their wage
 * history and position settings.
 *
 * @property int $userId The user to calculate salary for
 * @property int $authUserId The authenticated user who triggered the calculation
 */
class CalculateSalaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 600;
    public int $userId;
    public int $authUserId;

    public function __construct(int $userId, int $authUserId)
    {
        $this->userId = $userId;
        $this->authUserId = $authUserId;
    }

    public function handle(): void
    {
        $userOrganizationHistory = UserOrganizationHistory::where('user_id', $this->userId)->where('effective_date', '<=', NOW())->orderBy('effective_date', 'desc')->orderBy('id', 'desc')->first();
        if (!$userOrganizationHistory) {
            Log::warning('CalculateSalaryJob: No organization history', ['user_id' => $this->userId]);
            return;
        }

        $subPositionId = $userOrganizationHistory->sub_position_id;
        if (!$subPositionId) {
            Log::warning('CalculateSalaryJob: No sub position id', ['user_id' => $this->userId]);
            return;
        }

        $positionWage = PositionWage::where('position_id', $subPositionId)->where('effective_date', '<=', NOW())->orderBy('effective_date', 'desc')->orderBy('id', 'desc')->first();
        if (!$positionWage) {
            $positionWage = PositionWage::where('position_id', $subPositionId)->whereNull('effective_date')->orderBy('id', 'desc')->first();
        }

        if (!$positionWage) {
            Log::warning('CalculateSalaryJob: No position wage', ['user_id' => $this->userId]);
            return;
        }

        if ($positionWage->wages_status != 1) {
            Log::warning('CalculateSalaryJob: Position wage status is not 1', ['user_id' => $this->userId]);
            return;
        }

        $positionPayFrequency = PositionPayFrequency::where('position_id', $subPositionId)->first();
        if (!$positionPayFrequency) {
            Log::warning('CalculateSalaryJob: No position pay frequency', ['user_id' => $this->userId]);
            return;
        }

        $payFrequencyTypeId = $positionPayFrequency->frequency_type_id;
        $frequencyConfig = getFrequencyClassByType($payFrequencyTypeId);
        $class = $frequencyConfig['class'];
        $type = $frequencyConfig['type'];

        if (!isset($class)) {
            Log::warning('CalculateSalaryJob: Unknown pay frequency', ['user_id' => $this->userId]);
            return;
        }

        $frequency = $class::query();
        $today = NOW()->format('Y-m-d');
        $frequency = $frequency->where('pay_period_from', '<=', $today)->where('pay_period_to', '>=', $today);
        if ($payFrequencyTypeId == FrequencyType::BI_WEEKLY_ID || $payFrequencyTypeId == FrequencyType::SEMI_MONTHLY_ID) {
            $frequency = $frequency->where('type', $type);
        }
        $frequency = $frequency->first();
        if (!$frequency) {
            Log::warning('CalculateSalaryJob: No frequency', ['user_id' => $this->userId]);
            return;
        }

        $authUser = User::find($this->authUserId);
        if (!$authUser) {
            Log::warning('CalculateSalaryJob: No auth user', ['user_id' => $this->userId]);
            return;
        }

        if (($frequency->closed_status == 0 && $authUser->worker_type === '1099') || ($frequency->w2_closed_status == 0 && $authUser->worker_type === 'w2')) {
            $param = [
                'pay_period_from' => $frequency->pay_period_from,
                'pay_period_to' => $frequency->pay_period_to,
                'pay_frequency' => $payFrequencyTypeId,
                'worker_type' => $authUser->worker_type
            ];
            calculateSalary($authUser, $param);
        }
    }
}
