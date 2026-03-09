<?php

namespace App\Console\Commands;

use App\Core\Traits\HubspotTrait;
use Illuminate\Console\Command;

class UpdateStatusForOfferExpiryDate extends Command
{
    use HubspotTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'OfferExpiryDate:StatusUpdate {date? : Optional date based on Offer Expire Date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'auto update user status for offer expiry date ';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $date = $this->argument('date') ? $this->argument('date') : date('Y-m-d');
        $namespace = app()->getNamespace();
        $OnboardingEmployeeController = app()->make($namespace.\Http\Controllers\hiredEmployee_from_call_back::class);
        $response = $OnboardingEmployeeController->updateOfferExpiryDate($date);
        $this->info($response['message']);

        if ($response['message']) {
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
