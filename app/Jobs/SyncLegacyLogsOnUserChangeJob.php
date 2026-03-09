<?php

namespace App\Jobs;

use App\Jobs\Sales\SaleMasterJob;
use App\Models\LegacyApiRawDataHistory;
use App\Models\LegacyApiRawDataHistoryLog;
use App\Models\SalesMaster;
use App\Models\User;
use App\Services\LegacyLogsQueryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncLegacyLogsOnUserChangeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 900; // 15 minutes

    protected int $userId;

    protected string $eventType; // created | email_updated | hire_date_updated

    protected array $payload;

    public function __construct(int $userId, string $eventType, array $payload = [])
    {
        $this->userId = $userId;
        $this->eventType = $eventType;
        $this->payload = $payload;
        $this->onQueue(Config::get('legacy_logs_sync.queue', 'default'));
    }

    public function handle(LegacyLogsQueryService $logsService): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            Log::warning('SyncLegacyLogsOnUserChangeJob: user not found', [
                'user_id' => $this->userId,
            ]);

            return;
        }

        // Determine emails to match (include primary and work emails, old/new variants)
        $emails = [];
        foreach ([
            Arr::get($this->payload, 'new_email'),
            Arr::get($this->payload, 'old_email'),
            Arr::get($this->payload, 'new_work_email'),
            Arr::get($this->payload, 'old_work_email'),
            $user->email ?? null,
            $user->work_email ?? null,
        ] as $e) {
            if (! empty($e)) {
                $emails[] = strtolower(trim($e));
            }
        }

        // Include additional emails if model/table exist
        try {
            if (class_exists(\App\Models\UsersAdditionalEmail::class)) {
                $addlEmails = \App\Models\UsersAdditionalEmail::where('user_id', $user->id)
                    ->pluck('email')
                    ->filter()
                    ->map(fn ($e) => strtolower(trim($e)))
                    ->all();
                $emails = array_values(array_unique(array_merge($emails, $addlEmails)));
            }
        } catch (\Throwable $e) {
            // Non-fatal; continue with primary emails
        }

        // Determine date range
        $defaultStart = Config::get('legacy_logs_sync.default_start_date');
        if ($defaultStart) {
            $fromDate = Date::parse($defaultStart);
        } else {
            // Default to March 1 of current year
            $fromDate = Date::create(Date::now()->year, 3, 1, 0, 0, 0);
        }

        // Prefer explicit date from payload (new_hire_at), otherwise user's created_at, otherwise default
        $payloadHire = Arr::get($this->payload, 'new_hire_at');
        if ($payloadHire) {
            $fromDate = Date::parse($payloadHire);
        } elseif (! empty($user->created_at)) {
            $fromDate = Date::parse($user->created_at);
        }

        $toDate = Date::now();

        // Begin backfill operations using direct Eloquent chunking
        $totalProcessed = 0;
        $insertedCount = 0;
        $dataSourceTypesInserted = [];
        $batchSize = (int) Config::get('legacy_logs_sync.chunk', 500);
        $updateByEmail = (bool) Config::get('legacy_logs_sync.update_sales_master_by_email', true);
        $createMissingSM = (bool) Config::get('legacy_logs_sync.create_missing_sales_master', false);

        $lowerEmails = array_values(array_filter(array_unique(array_map('strtolower', $emails))));

        // Preload column list for safe attribute copy when inserting into histories
        $historyTable = (new LegacyApiRawDataHistory)->getTable();
        $historyColumns = Schema::getColumnListing($historyTable);

        // Helper to apply LOWER(email) ORs
        $applyEmailFilter = function ($q) use ($lowerEmails) {
            $q->where(function ($w) use ($lowerEmails) {
                foreach ($lowerEmails as $e) {
                    $w->orWhereRaw('LOWER(sales_rep_email) = ?', [$e]);
                }
            });
        };

        // 1) Update closer1_id in legacy_api_raw_data_histories where missing
        LegacyApiRawDataHistory::query()
            ->when(! empty($lowerEmails), function ($q) use ($applyEmailFilter) {
                $applyEmailFilter($q);
            })
            ->where(function ($w) use ($fromDate, $toDate) {
                $w->whereBetween('created_at', [$fromDate, $toDate])
                    ->orWhereBetween('source_created_at', [$fromDate, $toDate]);
            })
            ->where(function ($w) {
                $w->whereNull('closer1_id')
                    ->orWhere('closer1_id', 0)
                    ->orWhere('closer1_id', '');
            })
            ->orderBy('id')
            ->chunkById($batchSize, function ($rows) use ($user, &$totalProcessed, &$dataSourceTypesInserted) {
                foreach ($rows as $row) {
                    try {
                        $row->closer1_id = $user->id;
                        // Ensure import_to_sales is set to '0' so SaleMasterJob can pick it up
                        $row->import_to_sales = '0';
                        $row->save();
                        if (! empty($row->data_source_type)) {
                            $dataSourceTypesInserted[$row->data_source_type] = true;
                        }
                        $totalProcessed++;
                    } catch (\Throwable $e) {
                        Log::warning('SyncLegacyLogsOnUserChangeJob: update history failed', [
                            'id' => $row->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        // 2) Update closer1_id in legacy_api_raw_data_histories_log where missing
        LegacyApiRawDataHistoryLog::query()
            ->when(! empty($lowerEmails), function ($q) use ($applyEmailFilter) {
                $applyEmailFilter($q);
            })
            ->where(function ($w) use ($fromDate, $toDate) {
                $w->whereBetween('created_at', [$fromDate, $toDate])
                    ->orWhereBetween('source_created_at', [$fromDate, $toDate]);
            })
            ->where(function ($w) {
                $w->whereNull('closer1_id')
                    ->orWhere('closer1_id', 0)
                    ->orWhere('closer1_id', '');
            })
            ->orderBy('id')
            ->chunkById($batchSize, function ($rows) use ($user, &$totalProcessed, &$dataSourceTypesInserted) {
                foreach ($rows as $row) {
                    try {
                        $row->closer1_id = $user->id;
                        $row->save();
                        if (! empty($row->data_source_type)) {
                            $dataSourceTypesInserted[$row->data_source_type] = true;
                        }
                        $totalProcessed++;
                    } catch (\Throwable $e) {
                        Log::warning('SyncLegacyLogsOnUserChangeJob: update log failed', [
                            'id' => $row->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        // 2b) Fallback: if nothing processed within date window but we have emails, try without date filter (email-scoped)
        if ($totalProcessed === 0 && ! empty($lowerEmails)) {
            Log::info('SyncLegacyLogsOnUserChangeJob: no rows matched within date window; running email-scoped fallback without date filter', [
                'emails' => $lowerEmails,
            ]);
            LegacyApiRawDataHistory::query()
                ->when(! empty($lowerEmails), function ($q) use ($applyEmailFilter) {
                    $applyEmailFilter($q);
                })
                ->where(function ($w) {
                    $w->whereNull('closer1_id')
                        ->orWhere('closer1_id', 0)
                        ->orWhere('closer1_id', '');
                })
                ->orderBy('id')
                ->chunkById($batchSize, function ($rows) use ($user, &$totalProcessed, &$dataSourceTypesInserted) {
                    foreach ($rows as $row) {
                        try {
                            $row->closer1_id = $user->id;
                            $row->import_to_sales = '0';
                            $row->save();
                            if (! empty($row->data_source_type)) {
                                $dataSourceTypesInserted[$row->data_source_type] = true;
                            }
                            $totalProcessed++;
                        } catch (\Throwable $e) {
                            Log::warning('SyncLegacyLogsOnUserChangeJob: fallback update history failed', [
                                'id' => $row->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                });

            LegacyApiRawDataHistoryLog::query()
                ->when(! empty($lowerEmails), function ($q) use ($applyEmailFilter) {
                    $applyEmailFilter($q);
                })
                ->where(function ($w) {
                    $w->whereNull('closer1_id')
                        ->orWhere('closer1_id', 0)
                        ->orWhere('closer1_id', '');
                })
                ->orderBy('id')
                ->chunkById($batchSize, function ($rows) use ($user, &$totalProcessed, &$dataSourceTypesInserted) {
                    foreach ($rows as $row) {
                        try {
                            $row->closer1_id = $user->id;
                            $row->save();
                            if (! empty($row->data_source_type)) {
                                $dataSourceTypesInserted[$row->data_source_type] = true;
                            }
                            $totalProcessed++;
                        } catch (\Throwable $e) {
                            Log::warning('SyncLegacyLogsOnUserChangeJob: fallback update log failed', [
                                'id' => $row->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                });
        }

        // 3) Insert missing rows into legacy_api_raw_data_histories from logs (import_to_sales = '0')
        LegacyApiRawDataHistoryLog::query()
            ->when(! empty($lowerEmails), function ($q) use ($applyEmailFilter) {
                $applyEmailFilter($q);
            })
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->orderBy('id')
            ->chunkById($batchSize, function ($logs) use ($user, &$insertedCount, &$dataSourceTypesInserted, $historyColumns) {
                // Determine which PIDs are missing in histories
                $pids = $logs->pluck('pid')->filter()->unique()->values()->all();
                if (empty($pids)) {
                    return;
                }
                $existingPids = LegacyApiRawDataHistory::query()
                    ->whereIn('pid', $pids)
                    ->pluck('pid')
                    ->all();
                $existingPidSet = array_flip($existingPids);

                $toInsert = [];
                foreach ($logs as $logRow) {
                    $pid = $logRow->pid;
                    if (! $pid || isset($existingPidSet[$pid])) {
                        continue; // already exists
                    }

                    $attrs = $logRow->getAttributes();
                    unset($attrs['id'], $attrs['created_at'], $attrs['updated_at']);

                    // Ensure required flags and ownership
                    $attrs['import_to_sales'] = '0';
                    $attrs['closer1_id'] = $user->id;

                    // Track type for downstream SaleMasterJob
                    if (! empty($attrs['data_source_type'])) {
                        $dataSourceTypesInserted[$attrs['data_source_type']] = true;
                    }

                    // Filter attributes to only valid history table columns
                    $filtered = [];
                    foreach ($attrs as $k => $v) {
                        if (in_array($k, $historyColumns, true)) {
                            $filtered[$k] = $v;
                        }
                    }
                    if (! empty($filtered)) {
                        $toInsert[] = $filtered;
                    }
                }

                if (! empty($toInsert)) {
                    try {
                        LegacyApiRawDataHistory::insert($toInsert);
                        $insertedCount += count($toInsert);
                    } catch (\Throwable $e) {
                        Log::error('SyncLegacyLogsOnUserChangeJob: bulk insert into histories failed', [
                            'count' => count($toInsert),
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        // 4) Update or create SalesMaster linkage based on logs in the window (maintain prior behavior)
        LegacyApiRawDataHistoryLog::query()
            ->when(! empty($lowerEmails), function ($q) use ($applyEmailFilter) {
                $applyEmailFilter($q);
            })
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->orderBy('id')
            ->chunkById($batchSize, function ($chunk) use ($user, $lowerEmails, $updateByEmail, $createMissingSM) {
                foreach ($chunk as $log) {
                    try {
                        DB::beginTransaction();

                        // Update existing SalesMaster if present (by pid / homeowner_id / proposal_id)
                        $smQuery = SalesMaster::query();
                        if (! empty($log->pid)) {
                            $smQuery->where('pid', $log->pid);
                        }
                        if (! empty($log->homeowner_id)) {
                            $smQuery->orWhere('homeowner_id', $log->homeowner_id);
                        }
                        if (! empty($log->proposal_id)) {
                            $smQuery->orWhere('proposal_id', $log->proposal_id);
                        }
                        $existing = $smQuery->first();
                        if ($existing) {
                            $existing->closer1_id = $user->id;
                            $existing->save();
                        } else {
                            if ($updateByEmail) {
                                $smByEmail = SalesMaster::query()
                                    ->where(function ($w) use ($lowerEmails) {
                                        foreach ($lowerEmails as $e) {
                                            $w->orWhereRaw('LOWER(sales_rep_email) = ?', [$e]);
                                        }
                                    })
                                    ->whereNull('closer1_id')
                                    ->first();
                                if ($smByEmail) {
                                    $smByEmail->closer1_id = $user->id;
                                    $smByEmail->save();
                                } elseif ($createMissingSM) {
                                    $salesMaster = new SalesMaster;
                                    $fieldsToMap = [
                                        'pid', 'weekly_sheet_id', 'initialStatusText', 'install_partner',
                                        'install_partner_id', 'customer_name', 'customer_address',
                                        'customer_address_2', 'customer_city', 'customer_state',
                                        'location_code', 'customer_zip', 'customer_email', 'customer_phone',
                                        'homeowner_id', 'proposal_id', 'sales_rep_name', 'employee_id',
                                        'sales_rep_email', 'kw', 'date_cancelled', 'customer_signoff',
                                        'm1_date', 'm2_date', 'product_id', 'gross_account_value',
                                    ];
                                    foreach ($fieldsToMap as $field) {
                                        if (isset($log->$field)) {
                                            $salesMaster->$field = $log->$field;
                                        }
                                    }
                                    $salesMaster->closer1_id = $user->id;
                                    $salesMaster->save();
                                }
                            }
                        }

                        DB::commit();
                    } catch (\Throwable $e) {
                        DB::rollBack();
                        Log::warning('SyncLegacyLogsOnUserChangeJob: SalesMaster processing failed', [
                            'log_id' => $log->id ?? null,
                            'pid' => $log->pid ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        // 5) Dispatch SaleMasterJob per data_source_type for affected records (updates or inserts)
        if (! empty($dataSourceTypesInserted)) {
            $queue = Config::get('legacy_logs_sync.sale_master_queue', 'sales-import');
            $chunkSize = (int) Config::get('legacy_logs_sync.sale_master_chunk', 100);
            $types = array_keys($dataSourceTypesInserted);
            foreach ($types as $type) {
                try {
                    dispatch((new SaleMasterJob($type, $chunkSize))->onQueue($queue));
                    Log::info('SyncLegacyLogsOnUserChangeJob: dispatched SaleMasterJob', [
                        'type' => $type,
                        'queue' => $queue,
                        'chunk' => $chunkSize,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('SyncLegacyLogsOnUserChangeJob: failed to dispatch SaleMasterJob', [
                        'type' => $type,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('SyncLegacyLogsOnUserChangeJob completed', [
            'user_id' => $user->id,
            'event' => $this->eventType,
            'emails' => $emails,
            'from' => $fromDate->toDateTimeString(),
            'to' => $toDate->toDateTimeString(),
            'updated_or_checked' => $totalProcessed,
            'inserted_histories' => $insertedCount,
            'dispatched_types' => array_keys($dataSourceTypesInserted),
        ]);
    }
}
