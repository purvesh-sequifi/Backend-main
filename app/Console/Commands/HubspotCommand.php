<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class HubspotCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hubspots:corn';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // return Excel::download(new ImportExpord, 'legacyData.xlsx');
        // return Command::SUCCESS;
    }
}
