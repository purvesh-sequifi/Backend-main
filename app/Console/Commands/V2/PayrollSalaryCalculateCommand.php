<?php

declare(strict_types=1);

namespace App\Console\Commands\V2;

use App\Models\User;
use App\Models\PositionWage;
use App\Models\FrequencyType;
use Illuminate\Console\Command;
use App\Models\UserWagesHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\PayrollHourlySalary;
use App\Models\PositionPayFrequency;
use App\Traits\EmailNotificationTrait;
use App\Models\UserOrganizationHistory;

class PayrollSalaryCalculateCommand extends Command
{
    use EmailNotificationTrait;

    private const LOCK_STALE_THRESHOLD_SECONDS = 7200;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:salary-calculate {--memory-limit=1024 : Memory limit in MB} {--timeout=3600 : Timeout in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculates payroll salary every day.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $startTime = microtime(true);
        $initialMemory = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');

        // Get memory limit and timeout from command options
        $memoryLimitMB = (int) $this->option('memory-limit');
        $timeoutSeconds = (int) $this->option('timeout');

        // Clear stale scheduler lock if it exists and is older than 2 hours
        // Use only command name in hash to prevent multiple instances with different options
        $lockFile = storage_path('framework/schedule-' . sha1('payroll:salary-calculate'));
        if (file_exists($lockFile)) {
            $lockAge = time() - filemtime($lockFile);
            if ($lockAge > self::LOCK_STALE_THRESHOLD_SECONDS) { // 2 hours
                @unlink($lockFile);
                $this->info("Cleared stale scheduler lock (age: {$lockAge} seconds)");
            }
        }

        // Set memory and timeout from command options
        ini_set('memory_limit', $memoryLimitMB . 'M');
        set_time_limit($timeoutSeconds);

        // Log memory usage
        Log::info('Payroll salary calculation started', [
            'initial_memory_mb' => round($initialMemory / 1024 / 1024, 2),
            'memory_limit' => $memoryLimit
        ]);

        $payFrequencyTypeIds = [
            FrequencyType::WEEKLY_ID => ['1099', 'w2'],
            FrequencyType::MONTHLY_ID => ['1099', 'w2'],
            FrequencyType::BI_WEEKLY_ID => ['1099', 'w2'],
            FrequencyType::SEMI_MONTHLY_ID => ['1099', 'w2']
        ];

        $totalProcessed = 0;
        foreach ($payFrequencyTypeIds as $payFrequencyTypeId => $workerTypes) {
            try {
                foreach ($workerTypes as $workerType) {
                    $frequencyConfig = getFrequencyClassByType($payFrequencyTypeId);
                    $class = $frequencyConfig['class'];
                    $type = $frequencyConfig['type'];

                    if (!isset($class)) {
                        continue; // Skip this frequency type, continue with next
                    }

                    $frequency = $class::query();
                    if ($workerType === '1099') {
                        $frequency = $frequency->where(["closed_status" => 0]);
                    } else if ($workerType === 'w2') {
                        $frequency = $frequency->where(["w2_closed_status" => 0]);
                    }
                    if (($payFrequencyTypeId === FrequencyType::BI_WEEKLY_ID || $payFrequencyTypeId === FrequencyType::SEMI_MONTHLY_ID) && isset($type)) {
                        $frequency = $frequency->where('type', $type);
                    }
                    $frequency = $frequency->orderBy('id', 'ASC')->first();
                    if ($frequency && isset($frequency->pay_period_from) && isset($frequency->pay_period_to)) {
                        $positionIds = [];
                        $startDate = $frequency->pay_period_from;
                        $endDate = $frequency->pay_period_to;
                        $positionPayFrequencies = PositionPayFrequency::select('position_id')->where('frequency_type_id', $payFrequencyTypeId)->pluck('position_id')->toArray();
                        foreach ($positionPayFrequencies as $positionPayFrequency) {
                            $positionWage = PositionWage::where('position_id', $positionPayFrequency)->where('effective_date', '<=', $startDate)->orderBy('effective_date', 'desc')->orderBy('id', 'desc')->first();
                            if (!$positionWage) {
                                // If no position wage with effective_date, try to find one with NULL effective_date
                                $positionWage = PositionWage::where('position_id', $positionPayFrequency)->whereNull('effective_date')->orderBy('id', 'desc')->first();
                            }
                            if ($positionWage && $positionWage->wages_status == 1) {
                                $positionIds[] = $positionWage->position_id;
                            }
                        }

                        // Skip if no valid positions found
                        if (empty($positionIds)) {
                            continue;
                        }

                        $subQuery = UserOrganizationHistory::select(
                            'id',
                            'user_id',
                            'effective_date',
                            DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn')
                        )->where('effective_date', '<=', $startDate);

                        $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                            ->mergeBindings($subQuery->getQuery())
                            ->select('user_id', 'effective_date')
                            ->where('rn', 1)->get();

                        $closestDates = $results->map(function ($result) {
                            return ['user_id' => $result->user_id, 'effective_date' => $result->effective_date];
                        });

                        // Skip if no closest dates found
                        if ($closestDates->isEmpty()) {
                            continue;
                        }

                        $userIdArr = UserOrganizationHistory::where(function ($query) use ($closestDates) {
                            foreach ($closestDates as $closestDate) {
                                $query->orWhere(function ($q) use ($closestDate) {
                                    $q->where('user_id', $closestDate['user_id'])
                                        ->where('effective_date', $closestDate['effective_date']);
                                });
                            }
                        })->whereIn('sub_position_id', $positionIds)->pluck('user_id')->toArray();

                        // Skip if no users found
                        if (empty($userIdArr)) {
                            continue;
                        }

                        $userIds = [];
                        $users = User::select('id')->whereIn('id', $userIdArr)->where('worker_type', $workerType)->get();
                        foreach ($users as $user) {
                            $dismissFlag = checkDismissFlag($user->id, $startDate);
                            if ($dismissFlag && $dismissFlag->dismiss) {
                                continue;
                            }
                            $terminateFlag = checkTerminateFlag($user->id, $startDate);
                            if ($terminateFlag && $terminateFlag->is_terminate) {
                                continue;
                            }
                            $contractEndFlag = checkContractEndFlag($user->id, $startDate);
                            if ($contractEndFlag) {
                                continue;
                            }
                            $userIds[] = $user->id;
                        }

                        // Skip if no users found
                        if (empty($userIds)) {
                            continue;
                        }

                        // Process users in chunks to prevent memory issues
                        $totalUsers = count($userIds);
                        $chunkSize = 100;
                        $processedCount = 0;

                        User::whereIn('id', $userIds)
                            ->select('id', 'sub_position_id', 'stop_payroll', 'worker_type')
                            ->chunk($chunkSize, function ($users) use ($startDate, $endDate, $payFrequencyTypeId, $workerType, &$processedCount, $totalUsers) {
                                DB::beginTransaction();
                                try {
                                    $userIdsChunk = $users->pluck('id')->toArray();

                                    // Get existing payroll salaries for this chunk
                                    $existingPayrollSalaries = PayrollHourlySalary::whereIn('user_id', $userIdsChunk)
                                        ->where([
                                            'pay_period_from' => $startDate,
                                            'pay_period_to' => $endDate,
                                            'pay_frequency' => $payFrequencyTypeId,
                                            'user_worker_type' => $workerType
                                        ])
                                        ->where('status', '!=', 3)
                                        ->get()
                                        ->keyBy('user_id');

                                    foreach ($users as $user) {
                                        $this->processUserSalary($user, $startDate, $endDate, $payFrequencyTypeId, $workerType, $existingPayrollSalaries);
                                        $processedCount++;

                                        // Log progress every 50 users
                                        if ($processedCount % 50 === 0) {
                                            $this->info("Processed {$processedCount} of {$totalUsers} users...");
                                        }
                                    }

                                    // Clear memory after each chunk
                                    unset($existingPayrollSalaries);
                                    DB::commit();
                                } catch (\Throwable $e) {
                                    DB::rollBack();
                                    Log::error('Payroll Salary Calculate Command Failed!! ' . $e->getMessage() . ' Line No. :- ' . $e->getLine() . ' File :- ' . $e->getFile());
                                }
                            });

                        $this->info("Completed processing {$processedCount} users for frequency {$payFrequencyTypeId}, worker type {$workerType}");
                        $totalProcessed += $processedCount;
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Payroll Salary Calculate Command Failed!! ' . $e->getMessage() . ' Line No. :- ' . $e->getLine() . ' File :- ' . $e->getFile());
            }
        }

        $executionTime = microtime(true) - $startTime;
        $finalMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        Log::info('Payroll salary calculation completed', [
            'execution_time_seconds' => round($executionTime, 2),
            'users_processed' => $totalProcessed,
            'initial_memory_mb' => round($initialMemory / 1024 / 1024, 2),
            'peak_memory_mb' => round($peakMemory / 1024 / 1024, 2),
            'final_memory_mb' => round($finalMemory / 1024 / 1024, 2),
        ]);

        return Command::SUCCESS;
    }

    /**
     * Process salary calculation for a single user
     */
    private function processUserSalary(User $user, string $startDate, string $endDate, int $payFrequencyTypeId, string $workerType, $existingPayrollSalaries): void
    {
        $userWage = UserWagesHistory::where('effective_date', '<=', $startDate)
            ->where('user_id', $user->id)
            ->orderBy('effective_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();

        if ($userWage && $userWage->pay_type === 'Salary' && isset($userWage->pay_rate)) {
            $date = date('Y-m-d');
            $subPositionId = $user->sub_position_id;
            $stopPayroll = $user->stop_payroll;
            $totalRate = $userWage->pay_rate ?? 0;

            $payrollHourlySalary = $existingPayrollSalaries[$user->id] ?? null;
            if ($payrollHourlySalary) {
                $payrollHourlySalary->user_id = $user->id;
                $payrollHourlySalary->position_id = $subPositionId;
                $payrollHourlySalary->salary = $totalRate;
                $payrollHourlySalary->total = $totalRate;
                $payrollHourlySalary->pay_period_from = $startDate;
                $payrollHourlySalary->pay_period_to = $endDate;
                $payrollHourlySalary->status = 1;
                $payrollHourlySalary->is_stop_payroll = $stopPayroll;
                $payrollHourlySalary->pay_frequency = $payFrequencyTypeId;
                $payrollHourlySalary->user_worker_type = $user->worker_type;
                $payrollHourlySalary->save();
            } else {
                PayrollHourlySalary::create([
                    'user_id' => $user->id,
                    'position_id' => $subPositionId,
                    'date' => $date,
                    'salary' => $totalRate,
                    'total' => $totalRate,
                    'pay_period_from' => $startDate,
                    'pay_period_to' => $endDate,
                    'status' => 1,
                    'is_stop_payroll' => $stopPayroll,
                    'pay_frequency' => $payFrequencyTypeId,
                    'user_worker_type' => $user->worker_type
                ]);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Payroll Salary Calculate Command Failed!! ' . $e->getMessage() . ' Line No. :- ' . $e->getLine() . ' File :- ' . $e->getFile());
    }
}
