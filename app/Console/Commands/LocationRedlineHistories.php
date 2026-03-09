<?php

namespace App\Console\Commands;

use App\Models\LocationRedlineHistory;
use App\Models\Locations;
use Illuminate\Console\Command;

class LocationRedlineHistories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locationEffectiveRedline:update';

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
        $date = date('Y-m-d');
        $redline = LocationRedlineHistory::where('effective_date', $date)->first();
        if ($redline) {
            $redline = Locations::where('id', $redline->location_id)->update([
                'redline_min' => $redline->redline_min,
                'redline_standard' => $redline->redline_standard,
                'redline_max' => $redline->redline_max,
                'date_effective' => $redline->effective_date,
            ]);
        }
    }
}
