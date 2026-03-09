<?php

namespace App\Console\Commands;

use App\Models\TestData;
use Illuminate\Console\Command;

class TestUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:data';

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
        // return Command::SUCCESS;

        // for($i=1; $i<=4; $i++)
        // {
        //     $data = TestData::create(['first_name'=>'test'.$i,'last_name'=>'demo'.$i,'email'=>'test'.$i.'@gmail.com']);
        // }
    }
}
