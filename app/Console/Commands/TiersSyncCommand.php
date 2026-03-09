<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TiersSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tier:sync {user_id? : Optional user id Comma Separated To Sync history!!}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Users Current Tier!!';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $namespace = app()->getNamespace();
        $resetTiers = app()->make($namespace.\Http\Controllers\API\V2\Tiers\TierResetController::class);
        $response = $resetTiers->tiersSync($this->argument('user_id'));

        if ($response['success']) {
            $this->info($response['message']);

            return Command::SUCCESS;
        } else {
            $this->error($response['message']);

            return Command::FAILURE;
        }
    }
}
