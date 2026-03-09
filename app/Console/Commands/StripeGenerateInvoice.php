<?php

namespace App\Console\Commands;

use App\Http\Controllers\API\StripeBillingController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class StripeGenerateInvoice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripegenerateinvoice:all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Function for Genrate Stripe invoice all';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {

        try {
            StripeBillingController::updateinvoice();
        } catch (\Exception $e) {
            log::info([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

    }
}
