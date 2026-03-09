<?php

namespace App\Console\Commands;

use App\Models\ImportExpord;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class legacyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'legaces:corn';

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
        return Command::SUCCESS;
    }
}
