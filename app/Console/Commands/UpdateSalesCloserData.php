<?php

namespace App\Console\Commands;

use App\Models\LegacyApiRawDataHistory;
use App\Models\LegacyApiRawDataHistoryLog;
use App\Models\SalesMaster;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateSalesCloserData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sales:update-closer-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check legacy records with null closer1_id, find users by sales_rep_email, and update SalesMaster data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting to process legacy data...');

        // Process both tables
        $this->processLegacyHistories();
        $this->processLegacyHistoryLogs();

        $this->info('Data processing completed successfully!');

        return 0;
    }

    /**
     * Process records from legacy_api_raw_data_histories table
     */
    protected function processLegacyHistories()
    {
        $this->info('Processing legacy_api_raw_data_histories table...');

        // Get records with null closer1_id and valid sales_rep_email
        $records = LegacyApiRawDataHistory::whereNull('closer1_id')
            ->whereNotNull('sales_rep_email')
            ->where('sales_rep_email', '!=', '')
            ->chunk(100, function ($records) {
                $this->processRecordChunk($records, 'history');
            });

        $this->info('Completed processing legacy_api_raw_data_histories table.');
    }

    /**
     * Process records from legacy_api_raw_data_histories_log table
     */
    protected function processLegacyHistoryLogs()
    {
        $this->info('Processing legacy_api_raw_data_histories_log table...');

        // Get records with null closer1_id and valid sales_rep_email
        $records = LegacyApiRawDataHistoryLog::whereNull('closer1_id')
            ->whereNotNull('sales_rep_email')
            ->where('sales_rep_email', '!=', '')
            ->chunk(100, function ($records) {
                $this->processRecordChunk($records, 'log');
            });

        $this->info('Completed processing legacy_api_raw_data_histories_log table.');
    }

    /**
     * Process a chunk of records
     *
     * @param  string  $type  Either 'history' or 'log'
     */
    protected function processRecordChunk(Collection $records, string $type)
    {
        $this->info("Processing chunk of {$records->count()} records...");
        $processedCount = 0;
        $errorCount = 0;

        foreach ($records as $record) {
            try {
                DB::beginTransaction();

                // Find user by sales rep email (case-insensitive, checks email and work_email)
                $email = strtolower((string) $record->sales_rep_email);
                $user = User::whereRaw('LOWER(email) = ?', [$email])
                    ->orWhereRaw('LOWER(work_email) = ?', [$email])
                    ->first();

                if ($user) {
                    // Found a matching user
                    $this->info("Found matching user for email: {$record->sales_rep_email}");

                    // Check if record already exists in sales_masters
                    $existingSalesMaster = SalesMaster::where('pid', $record->pid)
                        ->orWhere(function ($query) use ($record) {
                            if (! empty($record->homeowner_id)) {
                                $query->where('homeowner_id', $record->homeowner_id);
                            }
                        })
                        ->first();

                    if (! $existingSalesMaster) {
                        // Create new sales master record
                        $salesMaster = new SalesMaster;

                        // Map fields from legacy record to sales master
                        $fieldsToMap = [
                            'pid', 'weekly_sheet_id', 'initialStatusText', 'install_partner',
                            'install_partner_id', 'customer_name', 'customer_address',
                            'customer_address_2', 'customer_city', 'customer_state',
                            'location_code', 'customer_zip', 'customer_email',
                            'customer_phone', 'homeowner_id', 'proposal_id',
                            'sales_rep_name', 'employee_id', 'sales_rep_email',
                            'kw', 'date_cancelled', 'customer_signoff', 'm1_date',
                            'm2_date', 'product_id', 'gross_account_value',
                        ];

                        foreach ($fieldsToMap as $field) {
                            if (isset($record->$field)) {
                                $salesMaster->$field = $record->$field;
                            }
                        }

                        // Set the closer1_id to the found user id
                        $salesMaster->closer1_id = $user->id;

                        $salesMaster->save();

                        // Update the original record with the closer1_id
                        $record->closer1_id = $user->id;
                        $record->save();

                        $processedCount++;
                    } else {
                        // Update existing sales master record
                        $existingSalesMaster->closer1_id = $user->id;
                        $existingSalesMaster->save();

                        // Update the original record
                        $record->closer1_id = $user->id;
                        $record->save();

                        $processedCount++;
                    }
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $errorCount++;
                Log::error("Error processing record ID: {$record->id}, Error: ".$e->getMessage());
                $this->error("Failed to process record ID: {$record->id} - {$e->getMessage()}");
            }
        }

        $this->info("Processed {$processedCount} records successfully with {$errorCount} errors.");
    }
}
