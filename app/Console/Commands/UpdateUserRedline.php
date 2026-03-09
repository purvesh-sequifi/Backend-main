<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserRedlines;
use Illuminate\Console\Command;

class UpdateUserRedline extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'userEffectiveRedline:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updateeffective redline for user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // return Command::SUCCESS;

        $user = UserRedlines::get();
        $date = date('Y-m-d');

        foreach ($user as $users) {
            if ($users->start_date == $date) {
                $userRedline = User::where('id', $users->user_id)->update(['redline_amount_type' => $users->redline_amount_type, 'redline' => $users->redline, 'redline_type' => $users->redline_type]);
            }

        }

        return Command::SUCCESS;
    }
}
