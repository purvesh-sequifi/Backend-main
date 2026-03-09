<?php

namespace App\Jobs;

use App\Events\SendSalesExportToPusher;
use App\Events\sendEventToPusher;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\JobNotification;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserCommission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class SalesExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200; // 20 minutes
    public $memory = 512; // 512MB memory limit

    // Export configuration constants
    protected const MAX_EXPORT_RECORDS = 10000; // Maximum records per export
    protected const BASE_MEMORY_MB = 512; // Base memory allocation
    protected const MEMORY_PER_500_RECORDS = 128; // Additional memory per 500 records
    protected const MAX_MEMORY_MB = 2048; // Maximum memory limit
    protected const CHUNK_SIZE = 1000; // Records to process per chunk
    protected const MEMORY_WARNING_THRESHOLD = 0.85; // Warn at 85% memory usage

    public $data;

    // Progress tracking properties
    protected int $progress = 0;
    protected ?string $jobId = null;
    protected ?string $sessionKey = null;
    protected ?int $estimatedRecordCount = null;
    protected int $actualRecordCount = 0;
    protected ?float $estimatedDuration = null;
    protected bool $pusherEnabled = true;

    // Dynamic stage progress boundaries
    protected array $stageBoundaries = [
        'initialize'  => ['start' => 0,  'end' => 5],
        'filter'      => ['start' => 5,  'end' => 10],
        'fetch'       => ['start' => 10, 'end' => 40],   // Largest range for data fetching
        'transform'   => ['start' => 40, 'end' => 70],   // Second largest for processing
        'generate'    => ['start' => 70, 'end' => 95],   // Excel generation
        'finalize'    => ['start' => 95, 'end' => 100],
    ];

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
        $this->sessionKey = $data['session_key'] ?? null;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Initialize job tracking
        $this->jobId = (string) ($this->job?->uuid() ?? uniqid('sales_export_', true));
        $startTime = now();

        $request = $this->data;
        $domainName = config('app.domain_name');

        try {
            // ============ STAGE 1: INITIALIZE (0-5%) ============
            $this->updateProgress(2, 'initialize', 'Starting sales data export...', [
                'job_id' => $this->jobId,
                'session_key' => $this->sessionKey,
                'initiated_at' => $startTime->toISOString(),
            ]);

        $result = [];
        $endDate = '';
        $startDate = '';
        $companyProfile = CompanyProfile::first();
        [$startDate, $endDate] = $this->getDateFromFilter($request);

            // ============ STAGE 2: FILTER (5-10%) ============
            $this->updateProgress(7, 'filter', 'Applying filters and building query...', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'filters_applied' => count(array_filter($request)),
            ]);

        $result = SalesMaster::with([
            'productInfo' => function ($q) {
                $q->withTrashed();
            },
            'salesMasterProcess.closer1Detail' => function ($q) {
                $q->select('id', 'first_name', 'last_name', 'email')->withoutGlobalScopes();
            },
            'salesMasterProcess.closer2Detail' => function ($q) {
                $q->select('id', 'first_name', 'last_name', 'email')->withoutGlobalScopes();
            },
            'salesMasterProcess.setter1Detail' => function ($q) {
                $q->select('id', 'first_name', 'last_name', 'email')->withoutGlobalScopes();
            },
            'salesMasterProcess.setter2Detail' => function ($q) {
                $q->select('id', 'first_name', 'last_name', 'email')->withoutGlobalScopes();
            },
            'salesMasterProcessInfo.status',
            'salesProductMaster' => function ($q) {
                $q->selectRaw('pid, type, SUM(amount) as value, milestone_date, is_projected, milestone_schema_id')->groupBy('pid', 'type');
            },
            'lastMilestone.milestoneSchemaTrigger',
            'salesProductMaster.milestoneSchemaTrigger',
            'legacyAPINull' => function ($q) {
                $q->whereNotNull('data_source_type');
            },
        ])->when(! empty($startDate), function ($q) use ($startDate, $endDate) {
            $q->whereBetween('customer_signoff', [$startDate, $endDate]);
        })->when((isset($request['office_id']) && ! empty($request['office_id'])), function ($q) use ($request) {
            $officeId = $request['office_id'];
            if ($officeId != 'all') {
                $userId = User::withoutGlobalScopes()->where('office_id', $officeId)->pluck('id');
                $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
                $q->whereIn('pid', $salesPid);
            }
        })->when((isset($request['search']) && ! empty($request['search'])), function ($q) use ($request) {
            $search = $request['search'];
            $q->where(function ($query) use ($search) {
                $query->where('pid', 'LIKE', '%'.$search.'%')->orWhere('customer_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('closer1_name', 'LIKE', '%'.$search.'%')->orWhere('setter1_name', 'LIKE', '%'.$search.'%');
            });
        })->when((isset($request['filter_product']) && ! empty($request['filter_product'])), function ($q) use ($request) {
            $q->where('product_id', $request['filter_product']);
        })->when((isset($request['location']) && ! empty($request['location'])), function ($q) use ($request) {
            $q->where('customer_state', $request['location']);
        })->when((isset($request['filter_install']) && ! empty($request['filter_install'])), function ($q) use ($request) {
            $q->where('install_partner', $request['filter_install']);
        })->when((isset($request['filter_status']) && ! empty($request['filter_status'])), function ($q) use ($request) {
            $q->where('job_status', $request['filter_status']);
        })->when((isset($request['date_filter']) && ! empty($request['date_filter'])), function ($q) use ($request) {
            if ($request['date_filter'] == 'Cancel Date') {
                $q->whereNotNull('date_cancelled');
            } else {
                $q->whereHas('salesProductMaster', function ($q) use ($request) {
                    $date_filter = $request['date_filter'];
                    $q->whereNotNull('milestone_date')->whereHas('milestoneSchemaTrigger', function ($q) use ($date_filter) {
                        $q->where('name', $date_filter);
                    });
                });
            }
        });

        // ============ STAGE 3: FETCH (10-40%) - DYNAMIC ============
        $this->updateProgress(10, 'fetch', 'Executing database query...', [
            'query_complexity' => 'high_with_joins',
        ]);

        // First, get estimated count for progress calculation
        $this->estimatedRecordCount = $result->count();
        
        // Validate record count against maximum limit
        if ($this->estimatedRecordCount > self::MAX_EXPORT_RECORDS) {
            throw new \RuntimeException(
                "Export limit exceeded: {$this->estimatedRecordCount} records found (maximum: " . 
                self::MAX_EXPORT_RECORDS . " records). Please refine your filters to reduce the dataset."
            );
        }

        // Calculate and set dynamic memory limit based on record count
        $requiredMemoryMB = $this->calculateRequiredMemory($this->estimatedRecordCount);
        $this->setDynamicMemoryLimit($requiredMemoryMB);

        $this->estimatedDuration = $this->calculateEstimatedDuration($this->estimatedRecordCount);

        $this->updateProgress(15, 'fetch', "Estimated {$this->estimatedRecordCount} records. Fetching data...", [
            'estimated_records' => $this->estimatedRecordCount,
            'estimated_duration_seconds' => round($this->estimatedDuration, 2),
            'allocated_memory_mb' => $requiredMemoryMB,
        ]);

        if (isset($request['sort_val']) && ! empty($request['sort_val'])) {
            $orderBy = $request['sort_val'];
        } else {
            $orderBy = 'DESC';
        }

        if (isset($request['sort']) && $request['sort'] == 'state') {
            $result->orderBy('customer_state', $orderBy);
        } elseif (isset($request['sort']) && $request['sort'] == 'kw') {
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $result->orderBy('gross_account_value', $orderBy);
            } else {
                $result->orderBy(DB::raw('CAST(kw AS UNSIGNED)'), $orderBy);
            }
        } elseif (isset($request['sort']) && $request['sort'] == 'epc') {
            $result->orderBy('epc', $orderBy);
        } elseif (isset($request['sort']) && $request['sort'] == 'net_epc') {
            $result->orderBy('net_epc', $orderBy);
        } elseif (isset($request['sort']) && $request['sort'] == 'adders') {
            $result->orderBy(DB::raw('CAST(adders AS UNSIGNED)'), $orderBy);
        } elseif (isset($request['sort']) && $request['sort'] == 't_Commission') {
            $result->orderBy('total_commission', $orderBy);
        } elseif (isset($request['sort']) && $request['sort'] == 't_Overrides') {
            $result->orderBy('total_override', $orderBy);
        } else {
            $result->orderBy('id', $orderBy);
        }

        $this->updateProgress(30, 'fetch', 'Sorting and preparing to fetch records...');

        // Fetch all filtered records (no limit - memory managed dynamically)
        $data = $result->get();
        $this->actualRecordCount = $data->count();

        // Monitor memory usage after fetching data
        $currentMemoryMB = memory_get_usage(true) / 1024 / 1024;
        $memoryLimit = $this->getMemoryLimitMB();
        $memoryUsagePercent = ($currentMemoryMB / $memoryLimit) * 100;

        // Warning if approaching memory limit
        if ($memoryUsagePercent > (self::MEMORY_WARNING_THRESHOLD * 100)) {
            Log::warning('High memory usage detected during export', [
                'job_id' => $this->jobId,
                'memory_used_mb' => round($currentMemoryMB, 2),
                'memory_limit_mb' => $memoryLimit,
                'usage_percent' => round($memoryUsagePercent, 2),
                'records_fetched' => $this->actualRecordCount,
            ]);
        }

        $this->updateProgress(40, 'fetch', "Successfully fetched {$this->actualRecordCount} records", [
            'records_fetched' => $this->actualRecordCount,
            'memory_usage_mb' => round($currentMemoryMB, 2),
            'memory_usage_percent' => round($memoryUsagePercent, 1),
            'memory_limit_mb' => $memoryLimit,
            'accuracy' => $this->estimatedRecordCount > 0 
                ? round(($this->actualRecordCount / $this->estimatedRecordCount) * 100, 2) . '%'
                : 'N/A',
        ]);

        // ============ STAGE 4: TRANSFORM (40-70%) - DYNAMIC ============
        $this->updateProgress(42, 'transform', 'Starting data transformation...');

        $reconciliationSetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();

        // Transform with dynamic progress updates
        $chunkSize = 100;
        $processedRecords = 0;
        $transformStageStart = 40;
        $transformStageEnd = 70;
        $transformStageRange = $transformStageEnd - $transformStageStart;

        $data->transform(function ($data) use ($reconciliationSetting, &$processedRecords, $chunkSize, $transformStageStart, $transformStageRange) {
            $processedRecords++;

            // Update progress every chunk
            if ($processedRecords % $chunkSize === 0 || $processedRecords === $this->actualRecordCount) {
                // Check memory usage during transformation
                $this->checkMemoryUsage($processedRecords);
                
                $progressPercentage = min(100, ($processedRecords / max(1, $this->actualRecordCount)) * 100);
                $currentProgress = $transformStageStart + (int) (($progressPercentage / 100) * $transformStageRange);

                $this->updateProgress(
                    $currentProgress,
                    'transform',
                    "Processing records: {$processedRecords}/{$this->actualRecordCount}",
                    [
                        'records_processed' => $processedRecords,
                        'total_records' => $this->actualRecordCount,
                        'progress_percentage' => round($progressPercentage, 1),
                    ],
                    true // Silent mode for frequent updates
                );
            }

            // Actual data transformation logic
            $commissionData = UserCommission::where(['pid' => $data->pid, 'status' => 3])->first();
            if (! in_array($data->salesMasterProcessInfo->mark_account_status_id, [1, 6]) && $commissionData) {
                $paymentStatus = ($commissionData) ? 'Paid' : null;
            } else {
                $paymentStatus = isset($data->salesMasterProcessInfo->status->account_status) ? $data->salesMasterProcessInfo->status->account_status : null;
            }
            $compRate = $commissionData?->comp_rate;

            $allMileStones = [];
            foreach ($data->salesProductMaster as $mileStone) {
                $allMileStones[] = [
                    'name' => $mileStone?->milestoneSchemaTrigger?->name,
                    'trigger' => $mileStone?->milestoneSchemaTrigger?->on_trigger,
                    'value' => $mileStone->value,
                    'date' => $mileStone->milestone_date,
                    'is_projected' => $mileStone->is_projected,
                ];
            }

            $reconAmount = 0;
            if ($reconciliationSetting) {
                $reconCommission = UserCommission::selectRaw('SUM(amount) as amount, pid, user_id, date')->where(['pid' => $data->pid, 'settlement_type' => 'reconciliation'])->first();
                if ($reconCommission) {
                    $reconAmount = $reconCommission->amount;
                }
            }

            $firstMilestoneDate = reset($allMileStones)['date'] ?? null;
            $firstDate = $firstMilestoneDate && ! $data->date_cancelled;
            $clawBackPids = ClawbackSettlement::whereNotNull('pid')->groupBy('pid')->pluck('pid')->toArray();
            $jobStatus = match (true) {
                $firstDate => 'Serviced',
                $data->date_cancelled && in_array($data->pid, $clawBackPids) => 'Clawback',
                $data->date_cancelled && ! in_array($data->pid, $clawBackPids) => 'Cancelled',
                default => 'Pending',
            };

            $productId = $data?->productInfo?->product_id;

            return [
                'pid' => $data->pid,
                'source' => $data->data_source_type,
                'customer_name' => $data->customer_name,
                // 'job_status' => $data->job_status,
                'job_status' => $jobStatus,
                'product' => $data->product,
                'product_id' => $productId,
                'state' => $data->customer_state,
                'location_code' => $data->location_code,
                'closer_1' => isset($data->salesMasterProcess->closer1Detail->first_name) ? $data->salesMasterProcess->closer1Detail->first_name.' '.$data->salesMasterProcess->closer1Detail->last_name : null,
                'closer_2' => isset($data->salesMasterProcess->closer2Detail->first_name) ? $data->salesMasterProcess->closer2Detail->first_name.' '.$data->salesMasterProcess->closer2Detail->last_name : null,
                'setter_1' => isset($data->salesMasterProcess->setter1Detail->first_name) ? $data->salesMasterProcess->setter1Detail->first_name.' '.$data->salesMasterProcess->setter1Detail->last_name : null,
                'setter_2' => isset($data->salesMasterProcess->setter2Detail->first_name) ? $data->salesMasterProcess->setter2Detail->first_name.' '.$data->salesMasterProcess->setter2Detail->last_name : null,
                'closer_1_email' => isset($data->salesMasterProcess->closer1Detail->email) ? $data->salesMasterProcess->closer1Detail->email : null,
                'closer_2_email' => isset($data->salesMasterProcess->closer2Detail->email) ? $data->salesMasterProcess->closer2Detail->email : null,
                'setter_1_email' => isset($data->salesMasterProcess->setter1Detail->email) ? $data->salesMasterProcess->setter1Detail->email : null,
                'setter_2_email' => isset($data->salesMasterProcess->setter2Detail->email) ? $data->salesMasterProcess->setter2Detail->email : null,
                'customer_signoff' => $data->customer_signoff,
                'gross_account_value' => $data->gross_account_value,
                'adders' => $data->adders,
                'comp_rate' => $compRate,
                'total_commission' => $data->total_commission + $reconAmount,
                'total_recon' => $reconAmount,
                'total_override' => $data->total_override,
                'mark_account_status_name' => $paymentStatus,
                'kw' => $data->kw,
                'epc' => $data->epc,
                'net_epc' => $data->net_epc,
                'customer_address' => $data->customer_address,
                'homeowner_id' => $data->homeowner_id,
                'customer_address_2' => $data->customer_address_2,
                'proposal_id' => $data->proposal_id,
                'installer' => $data->installer,
                'customer_city' => $data->customer_city,
                'customer_zip' => $data->customer_zip,
                'customer_email' => $data->customer_email,
                'customer_phone' => $data->customer_phone,
                'dealer_fee_percentage' => $data->dealer_fee_percentage,
                'dealer_fee_amount' => $data->dealer_fee_amount,
                'show' => $data->show,
                'date_cancelled' => $data->date_cancelled,
                'all_milestone' => $allMileStones,
            ];
        });

        $this->updateProgress(70, 'transform', "Transformation completed! {$this->actualRecordCount} records processed", [
            'records_transformed' => $this->actualRecordCount,
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);

        // ============ STAGE 5: GENERATE EXCEL (70-95%) - DYNAMIC ============
        $this->updateProgress(72, 'generate', 'Preparing Excel export...', [
            'records_to_export' => $data->count(),
        ]);

        $file_name = 'sales_export_'.date('Y-m-d').'.xlsx';

        $this->updateProgress(80, 'generate', 'Generating Excel file (this may take a moment)...');

        Excel::store(new \App\Exports\ExportReports\Sales\SalesReportExport($data, $reconciliationSetting), 'exports/reports/sales/'.$file_name, 'public', \Maatwebsite\Excel\Excel::XLSX);

        $this->updateProgress(92, 'generate', 'Excel file created successfully');

        $url = getStoragePath('exports/reports/sales/'.$file_name);

        // Calculate final metrics
        $duration = now()->diffInSeconds($startTime);
        $filePath = storage_path('app/public/exports/reports/sales/'.$file_name);
        $fileSize = file_exists($filePath) ? filesize($filePath) : 0;
        $recordsPerSecond = $duration > 0 ? round($this->actualRecordCount / $duration, 2) : 0;

        $this->updateProgress(95, 'generate', 'Excel file ready for download', [
            'file_name' => $file_name,
            'file_size_kb' => round($fileSize / 1024, 2),
            'file_size_mb' => round($fileSize / 1024 / 1024, 2),
        ]);

        // ============ STAGE 6: FINALIZE (95-100%) ============
        $this->updateProgress(97, 'finalize', 'Preparing download link...');

        $domainName = config('app.domain_name');
        $pusherEvent = 'sale-export-excel';
        $pusherMsg = 'Sales exported successfully';
        $pusherUniqueKey = $request['session_key'] ?? null;

        event(new SendSalesExportToPusher($domainName, $pusherEvent, $pusherMsg, $url, $pusherUniqueKey));

        // ============ COMPLETED (100%) ============
        $this->updateProgress(100, 'completed', "Export completed! {$this->actualRecordCount} records exported in {$duration}s", [
            'file_name' => $file_name,
            'file_url' => $url,
            'records_exported' => $this->actualRecordCount,
            'file_size_kb' => round($fileSize / 1024, 2),
            'processing_time_seconds' => $duration,
            'records_per_second' => $recordsPerSecond,
            'estimated_duration' => round($this->estimatedDuration, 2),
            'actual_duration' => $duration,
            'time_accuracy' => $this->estimatedDuration > 0 
                ? round((min($duration, $this->estimatedDuration) / max($duration, $this->estimatedDuration)) * 100, 2) . '%'
                : 'N/A',
            'completed_at' => now()->toISOString(),
        ]);

        Log::info('SalesExportJob completed successfully', [
            'job_id' => $this->jobId,
            'records' => $this->actualRecordCount,
            'file' => $file_name,
            'duration' => $duration,
            'estimated_duration' => $this->estimatedDuration,
            'records_per_second' => $recordsPerSecond,
            'session_key' => $this->sessionKey,
        ]);

        } catch (\Exception $e) {
            Log::error('SalesExportJob failed', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'progress_at_failure' => $this->progress,
                'session_key' => $this->sessionKey,
            ]);

            // Notify failure with current progress
            $this->updateProgress(
                $this->progress,
                'failed',
                'Export failed: ' . $e->getMessage(),
                [
                    'error' => $e->getMessage(),
                    'error_line' => $e->getLine(),
                    'error_file' => basename($e->getFile()),
                    'records_processed' => $this->actualRecordCount,
                ]
            );

            throw $e;
        }
    }

    /**
     * Calculate estimated duration based on record count
     */
    protected function calculateEstimatedDuration(int $recordCount): float
    {
        // Benchmarks (adjust based on your server performance)
        $baseTime = 2.0; // Base overhead in seconds
        $timePerRecord = 0.01; // Seconds per record (average)
        $timePerHundred = 0.5; // Additional time per 100 records for Excel generation

        $estimatedTime = $baseTime + ($recordCount * $timePerRecord) + (($recordCount / 100) * $timePerHundred);

        return $estimatedTime;
    }

    /**
     * Calculate required memory based on record count
     */
    protected function calculateRequiredMemory(int $recordCount): int
    {
        // Base memory + additional memory per 500 records
        $additionalMemory = ceil($recordCount / 500) * self::MEMORY_PER_500_RECORDS;
        $requiredMemory = self::BASE_MEMORY_MB + $additionalMemory;
        
        // Cap at maximum memory limit
        return min($requiredMemory, self::MAX_MEMORY_MB);
    }

    /**
     * Set dynamic memory limit based on requirements
     */
    protected function setDynamicMemoryLimit(int $memoryMB): void
    {
        $newLimit = $memoryMB . 'M';
        ini_set('memory_limit', $newLimit);
        
        Log::info('Dynamic memory limit set for export', [
            'job_id' => $this->jobId,
            'memory_limit_mb' => $memoryMB,
            'estimated_records' => $this->estimatedRecordCount,
        ]);
    }

    /**
     * Get current memory limit in MB
     */
    protected function getMemoryLimitMB(): int
    {
        $memoryLimit = ini_get('memory_limit');
        
        if (preg_match('/^(\d+)(.)$/', $memoryLimit, $matches)) {
            $value = (int) $matches[1];
            $unit = strtoupper($matches[2]);
            
            switch ($unit) {
                case 'G':
                    return $value * 1024;
                case 'M':
                    return $value;
                case 'K':
                    return (int) ceil($value / 1024);
            }
        }
        
        return 512; // Default fallback
    }

    /**
     * Check memory usage and throw exception if approaching limit
     */
    protected function checkMemoryUsage(int $recordsProcessed): void
    {
        $currentMemoryMB = memory_get_usage(true) / 1024 / 1024;
        $memoryLimit = $this->getMemoryLimitMB();
        $memoryUsagePercent = ($currentMemoryMB / $memoryLimit);
        
        // Throw exception if memory usage exceeds threshold
        if ($memoryUsagePercent > self::MEMORY_WARNING_THRESHOLD) {
            $memoryUsagePercentDisplay = round($memoryUsagePercent * 100, 1);
            
            Log::error('Memory limit approaching during export', [
                'job_id' => $this->jobId,
                'memory_used_mb' => round($currentMemoryMB, 2),
                'memory_limit_mb' => $memoryLimit,
                'usage_percent' => $memoryUsagePercentDisplay,
                'records_processed' => $recordsProcessed,
                'total_records' => $this->actualRecordCount,
            ]);
            
            throw new \RuntimeException(
                "Memory limit approaching: {$memoryUsagePercentDisplay}% used " .
                "({$currentMemoryMB}MB / {$memoryLimit}MB). " .
                "Export stopped at {$recordsProcessed}/{$this->actualRecordCount} records."
            );
        }
    }

    /**
     * Update progress with stage-aware boundaries
     */
    protected function updateProgress(
        int $progress,
        string $stage,
        string $message,
        array $metadata = [],
        bool $silent = false
    ): void {
        // Validate progress is within stage boundaries
        if (isset($this->stageBoundaries[$stage])) {
            $min = $this->stageBoundaries[$stage]['start'];
            $max = $this->stageBoundaries[$stage]['end'];
            $progress = max($min, min($max, $progress));
        }

        // Update internal progress
        $this->progress = max($this->progress, $progress);

        // Determine status
        $status = match($stage) {
            'initialize' => 'started',
            'completed' => 'completed',
            'failed' => 'failed',
            default => 'processing'
        };

        // Send notification (with fallback)
        if (!$silent) {
            $this->notifyProgress($status, $this->progress, $message, array_merge($metadata, [
                'stage' => $stage,
            ]));
        }
    }

    /**
     * Send progress notification with fallback mechanism
     */
    protected function notifyProgress(
        string $status,
        int $progress,
        string $message,
        array $metadata = []
    ): void {
        // Validation
        if ($progress < 0 || $progress > 100) {
            Log::warning("Invalid progress value: {$progress}");
            return;
        }

        // Prepare complete metadata
        $metadata['session_key'] = $this->sessionKey;
        $metadata['job_id'] = $this->jobId;
        $metadata['job_type'] = 'sales';
        $metadata['job_name'] = 'SalesExportJob';
        $metadata['status'] = $status;
        $metadata['progress'] = $progress;
        $metadata['timestamp'] = now()->toISOString();

        // STEP 1: Persist to database (ALWAYS - this is the fallback)
        $this->persistNotification($status, $progress, $message, $metadata);

        // STEP 2: Try to send via Pusher (optional)
        if ($this->pusherEnabled) {
            $this->sendPusherNotification($status, $progress, $message, $metadata);
        }
    }

    /**
     * Persist notification to database (PRIMARY - always works)
     */
    protected function persistNotification(
        string $status,
        int $progress,
        string $message,
        array $metadata
    ): void {
        try {
            $companyProfileId = config('app.company_profile_id');
            $domainName = config('app.domain_name');
            $userId = $this->data['user_id'] ?? auth()->id();

            JobNotification::updateOrCreate(
                ['job_id' => $this->jobId],
                [
                    'job_type' => 'sales',
                    'job_name' => 'SalesExportJob',
                    'status' => $status,
                    'progress' => $progress,
                    'message' => $message,
                    'metadata' => $metadata,
                    'company_profile_id' => $companyProfileId,
                    'domain_name' => $domainName,
                    'user_id' => $userId,
                    'session_key' => $this->sessionKey,
                    'initiated_at' => $metadata['initiated_at'] ?? null,
                    'completed_at' => $status === 'completed' ? now() : null,
                    'duration_seconds' => $metadata['processing_time_seconds'] ?? null,
                    'estimated_duration_seconds' => $metadata['estimated_duration'] ?? null,
                    'records_processed' => $metadata['records_processed'] ?? $this->actualRecordCount,
                    'records_per_second' => $metadata['records_per_second'] ?? null,
                    'memory_peak_mb' => $metadata['memory_peak_mb'] ?? null,
                    'file_url' => $metadata['file_url'] ?? null,
                    'file_size_kb' => $metadata['file_size_kb'] ?? null,
                    'error_message' => $metadata['error'] ?? null,
                    'error_file' => $metadata['error_file'] ?? null,
                    'error_line' => $metadata['error_line'] ?? null,
                ]
            );

            Log::debug('Job notification persisted to database', [
                'job_id' => $this->jobId,
                'status' => $status,
                'progress' => $progress,
            ]);

        } catch (\Exception $e) {
            // Even database persistence can fail, but we never stop the job
            Log::error('Failed to persist job notification', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send notification via Pusher (SECONDARY - optional)
     */
    protected function sendPusherNotification(
        string $status,
        int $progress,
        string $message,
        array $metadata
    ): void {
        // Skip if Pusher is disabled
        if (!$this->pusherEnabled) {
            return;
        }

        try {
            $domainName = config('app.domain_name');

            // Check if Pusher is configured
            if (empty(config('broadcasting.connections.pusher.key'))) {
                Log::warning('Pusher not configured, disabling notifications');
                $this->pusherEnabled = false;
                return;
            }

            // Prepare complete event data that frontend expects
            $eventData = array_merge([
                'status' => $status,
                'progress' => $progress,
                'message' => $message,
                'session_key' => $this->sessionKey,
                'job_id' => $this->jobId,
            ], $metadata);

            // Broadcast using existing sendEventToPusher event
            event(new sendEventToPusher(
                $domainName,
                'sale-export-progress',
                $message,
                $eventData
            ));

            Log::debug('Pusher notification sent', [
                'job_id' => $this->jobId,
                'status' => $status,
                'progress' => $progress,
                'session_key' => $this->sessionKey,
            ]);

        } catch (\Exception $e) {
            // FALLBACK: Disable Pusher for this job instance
            Log::error('Pusher notification failed - disabling for this job', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'progress' => $progress,
            ]);

            $this->pusherEnabled = false;

            // Job continues! Database persistence already succeeded
        }
    }

    public function getDateFromFilter($request)
    {
        $startDate = null;
        $endDate = null;
        if ($request['filter'] && ! empty($request['filter'])) {
            $filterDataDateWise = $request['filter'];
            if ($filterDataDateWise == 'this_week') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
                $endDate = date('Y-m-d', strtotime(now()));
            } elseif ($filterDataDateWise == 'last_week') {
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                $endDate = date('Y-m-d', strtotime($endOfLastWeek));
            } elseif ($filterDataDateWise == 'this_month') {
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate = date('Y-m-d', strtotime($startOfMonth));
                $endDate = date('Y-m-d', strtotime($endOfMonth));
            } elseif ($filterDataDateWise == 'this_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q1: January 1 - March 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(2)->endOfMonth()));
                } elseif ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q2: April 1 - June 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(3)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(5)->endOfMonth()));
                } elseif ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q3: July 1 - September 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(6)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(8)->endOfMonth()));
                } elseif ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q4: October 1 - December 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(9)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(11)->endOfMonth()));
                }
            } elseif ($filterDataDateWise == 'last_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q4 of last year: October 1 - December 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(9)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(11)->endOfMonth()));
                } elseif ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q1 of current year: January 1 - March 31
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(2)->endOfMonth()));
                } elseif ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q2 of current year: April 1 - June 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(3)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(5)->endOfMonth()));
                } elseif ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q3 of current year: July 1 - September 30
                    $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(6)));
                    $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(8)->endOfMonth()));
                }
            } elseif ($filterDataDateWise == 'this_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));
            } elseif ($filterDataDateWise == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
            } elseif ($filterDataDateWise == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonth(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
            } elseif ($filterDataDateWise == 'custom') {
                $startDate = date('Y-m-d', strtotime($request['start_date']));
                $endDate = date('Y-m-d', strtotime($request['end_date']));
            }
        }

        return [$startDate, $endDate];
    }
}
