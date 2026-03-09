<?php

namespace App\Console\Commands;

use App\Jobs\AddUpdateNewSequiDocsDocumentOnBigQueryJob;
use Illuminate\Console\Command;

class SyncNewSequiDocsDocumentOnBigQueryHourly extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'syncnewsequidocsdocumentonbigquery:hourly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will work for sync new sequi docs document on BigQuery.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {

        $this->info('Starting SequiDocs to BigQuery sync...');

        // Dispatch the job with monitoring
        $job = AddUpdateNewSequiDocsDocumentOnBigQueryJob::dispatch()
            ->onQueue('bigquery')
            ->delay(now()->addSeconds(10)); // Small delay to prevent overlap

        $this->info('Job dispatched to bigquery queue with 10-second delay');

        return Command::SUCCESS;
    }
}
