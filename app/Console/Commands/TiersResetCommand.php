<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TiersResetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tiers:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resets Tiers!!';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $namespace = app()->getNamespace();
        $resetTiers = app()->make($namespace.\Http\Controllers\API\V2\Tiers\TierResetController::class);
        $response = $resetTiers->resetTiers();

        if ($response['success']) {
            $this->info($response['message']);

            return Command::SUCCESS;
        } else {
            $this->error($response['message']);

            return Command::FAILURE;
        }
    }
}
