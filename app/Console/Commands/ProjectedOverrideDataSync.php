<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProjectedOverrideDataSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'projectedOverrideData:sync {pid? : Optional PID of sale_masters table}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'To Sync Projected Overrides Data!! Works Every 6 Hours!!';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $namespace = app()->getNamespace();
        $salesProjectionController = app()->make($namespace.\Http\Controllers\API\V2\Sales\SalesProjectionsController::class);
        $response = $salesProjectionController->syncProjectedOverridesData($this->argument('pid'));
        $this->info($response['message']);

        return Command::SUCCESS;
    }
}
