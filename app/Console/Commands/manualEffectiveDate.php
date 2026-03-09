<?php

namespace App\Console\Commands;

use App\Models\ManualOverrides;
use App\Models\ManualOverridesHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class manualEffectiveDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'manualEffectiveDate:update';

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

        $date = date('Y-m-d');
        $data = ManualOverrides::get();
        foreach ($data as $datas) {
            $currentDate = Carbon::now()->format('Y-m-d');
            $datas = ManualOverridesHistory::where('manual_user_id', $datas->manual_user_id)->where('effective_date', $currentDate)->first();
            if ($datas) {
                $data = ManualOverrides::where('manual_user_id', $datas->manual_user_id)->update(['effective_date' => $datas->effective_date, 'overrides_amount' => $datas->overrides_amount]);
            }

        }

        return Command::SUCCESS;
    }
}
