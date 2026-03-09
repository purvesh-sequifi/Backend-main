<?php

namespace App\Console\Commands;

use App\Jobs\AddUpdateLeadsOnBigQueryJob;
use Illuminate\Console\Command;

class SyncLeadsOnBigQueryHourly extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leadssynconbigquery:hourly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will work for sync leads on BigQuery.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        AddUpdateLeadsOnBigQueryJob::dispatch();

        return Command::SUCCESS;
    }
}
