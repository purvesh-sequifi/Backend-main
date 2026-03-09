<?php

namespace App\Console\Commands;

use App\Models\SalesMaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncProjectionCommission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'syncSalesProjectionData:sync 
                            {pid? : Optional PID of sale_masters table}
                            {--chunk-size=100 : Number of records to process per chunk}
                            {--memory-limit=2048 : Memory limit in MB}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'To Sync Projection Commissions Every 4 Hours!!';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Set memory limit based on command option
        $memoryLimit = $this->option('memory-limit');
        ini_set('memory_limit', $memoryLimit.'M');

        // Set maximum execution time to 1 hour (3600 seconds)
        ini_set('max_execution_time', 3600);

        $chunkSize = (int) $this->option('chunk-size');
        $pid = $this->argument('pid');

        $namespace = app()->getNamespace();
        $salesProjectionController = app()->make($namespace.\Http\Controllers\API\V2\Sales\SalesProjectionsController::class);

        // Show starting message
        $this->info('Starting projection data sync...');

        // If specific PID is provided, process just that record
        if (! empty($pid)) {
            $response = $salesProjectionController->syncSubroutineProcessData($pid);
            $this->info($response['message']);

            return Command::SUCCESS;
        }

        // Otherwise process in chunks
        $this->info('Setting up chunked processing with chunk size: '.$chunkSize);

        // Start query builder for eligible sales
        $query = SalesMaster::with([
            'salesMasterProcess.closer1Detail',
            'salesMasterProcess.closer2Detail',
            'salesMasterProcess.setter1Detail',
            'salesMasterProcess.setter2Detail',
            'salesProductMaster',
        ])
            ->whereHas('salesProductMaster', function ($q) {
                $q->whereNull('milestone_date')->where('is_last_date', '1');
            })
            ->whereNotNull('customer_signoff')
            ->whereNull('date_cancelled')
            ->orderBy('customer_signoff', 'ASC');

        // Get total count for progress indicator
        $totalCount = $query->count();
        $this->info('Total records to process: '.$totalCount);

        if ($totalCount == 0) {
            $this->info('No records found to process.');

            return Command::SUCCESS;
        }

        $processedCount = 0;
        $errors = [];

        // Process in chunks with progress bar
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $processedPID = [];
        $query->chunk($chunkSize, function ($sales) use ($salesProjectionController, &$processedCount, &$errors, $bar, &$processedPID) {
            foreach ($sales as $sale) {
                $pid = $sale->pid;
                $processedPID[] = $pid;

                // Process each record in its own transaction for better error isolation
                DB::beginTransaction();
                try {
                    // Clean up existing projection data for this specific PID
                    DB::table('projection_user_commissions')->where('pid', $pid)->delete();
                    DB::table('projection_user_overrides')->where('pid', $pid)->delete();

                    // Process individual record
                    $salesProjectionController->syncIndividualSaleProjection($sale);

                    DB::commit();
                    $processedCount++;
                    $bar->advance();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $errors[] = [
                        'pid' => $pid,
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ];
                    Log::error('Error processing sales projection for PID: '.$pid, [
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $bar->advance();
                }
            }

            // Force garbage collection after each chunk to free memory
            gc_collect_cycles();

            // Only reconnect every 10 chunks to avoid excessive reconnections
            static $chunkCounter = 0;
            $chunkCounter++;
            if ($chunkCounter % 10 === 0) {
                DB::disconnect('mysql');
                DB::reconnect('mysql');
            }
        });

        DB::table('projection_user_commissions')->whereNotIn('pid', $processedPID)->delete();
        DB::table('projection_user_overrides')->whereNotIn('pid', $processedPID)->delete();

        $bar->finish();
        $this->newLine(2);

        $this->info('Processed '.$processedCount.' of '.$totalCount.' records.');

        if (count($errors) > 0) {
            $this->error('Encountered '.count($errors).' errors during processing.');
            Log::error('Sales projection sync errors', ['errors' => $errors]);
        }

        return Command::SUCCESS;
    }
}
