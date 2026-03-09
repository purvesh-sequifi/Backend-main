<?php

namespace App\Console\Commands;

use App\Jobs\AddUpdateAdditionalInfoForEmployeeGetStartedOnBigQueryJob;
use Illuminate\Console\Command;

class SyncAdditionalInfoForEmployeeGetStartedOnBigQueryHourly extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'syncadditionalinfoforemployeeonbigquery:hourly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will work for sync additional info for employee on BigQuery.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        AddUpdateAdditionalInfoForEmployeeGetStartedOnBigQueryJob::dispatch();

        return Command::SUCCESS;
    }
}
