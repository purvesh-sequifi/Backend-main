<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class JiraDataSyncing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jira:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs Status & Estimation Date From Jira!!';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $namespace = app()->getNamespace();
        $this->ticketController = app()->make($namespace.\Http\Controllers\API\TicketSystem\Ticket\TicketController::class);
        $this->ticketController->sync();

        return Command::SUCCESS;
    }
}
