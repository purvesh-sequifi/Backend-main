<?php

namespace App\Console\Commands;

use App\Models\AdditionalLocations;
use App\Models\ManualOverrides;
use App\Models\OverrideStatus;
use App\Models\overrideSystemSetting;
use App\Models\User;
use App\Models\UserOverrideHistory;
use App\Models\UserTransferHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class createOverrideStatusHistoryForOldRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'store:OverrideStatusHistoryForOldRecords';

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
        $ids = User::orderBy('id', 'ASC')->pluck('id')->toArray();
        foreach ($ids as $id) {
            $user = User::where('id', $id)->first();
            $date = date('Y-m-d');
            $subQuery = UserOverrideHistory::select(
                'id',
                'user_id',
                'override_effective_date',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY override_effective_date DESC, id DESC) as rn')
            )->where('override_effective_date', '<=', $date);

            // Main query to get the IDs where rn = 1
            $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                ->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);

            // Final query to get the user_override_history records with the selected IDs
            $directs = UserOverrideHistory::select(
                'users.id',
                'user_override_history.product_id',
                'user_override_history.override_effective_date'
            )
                ->whereIn('user_override_history.id', $results->pluck('id'))
                ->join('users', 'users.id', 'user_override_history.user_id')
                ->where(function ($query) use ($user) {
                    $query->where('users.recruiter_id', $user->id)
                        ->orWhere('users.additional_recruiter_id1', $user->id)
                        ->orWhere('users.additional_recruiter_id2', $user->id);
                })->where('users.dismiss', 0)->get();

            // DIRECT OVERRIDES START
            foreach ($directs as $value) {

                $direct_override_status = OverrideStatus::create([
                    'user_id' => $value->id,
                    'recruiter_id' => $id,
                    'type' => 'Direct',
                    'status' => 0,
                    'effective_date' => $value->override_effective_date,
                    'product_id' => isset($value->product_id) ? $value->product_id : 0,
                    'updated_by' => 1,
                ]);

                $subQuery = UserOverrideHistory::select(
                    'id',
                    'user_id',
                    'override_effective_date',
                    DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY override_effective_date DESC, id DESC) as rn')
                )->where('override_effective_date', '<=', $date);

                // Main query to get the IDs where rn = 1
                $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                    ->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);

                // Final query to get the user_override_history records with the selected IDs
                $inDirects = UserOverrideHistory::select(
                    'users.id',
                    'user_override_history.product_id',
                    'user_override_history.override_effective_date'
                )
                    ->whereIn('user_override_history.id', $results->pluck('id'))
                    ->join('users', 'users.id', 'user_override_history.user_id')
                    ->where(function ($query) use ($value) {
                        $query->where('users.recruiter_id', $value->id)
                            ->orWhere('users.additional_recruiter_id1', $value->id)
                            ->orWhere('users.additional_recruiter_id2', $value->id);
                    })->where('users.dismiss', 0)->get();

                foreach ($inDirects as $indirect) {
                    $direct_override_status = OverrideStatus::create([
                        'user_id' => $indirect->id,
                        'recruiter_id' => $id,
                        'type' => 'Indirect',
                        'status' => 0,
                        'effective_date' => $indirect->override_effective_date,
                        'product_id' => isset($indirect->product_id) ? $indirect->product_id : 0,
                        'updated_by' => 1,
                    ]);
                }

                // INDIRECT OVERRIDES END
            }

            $office_id = $user->office_id;
            $userTransferHistory = UserTransferHistory::where('user_id', $user->id)->where('transfer_effective_date', '<=', $date)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $office_id = $userTransferHistory->office_id;
            }
            $userIdArr1 = [$office_id];
            $userIdArr2 = AdditionalLocations::where('user_id', $user->id)->pluck('office_id')->toArray();

            if (count($userIdArr1) > 0) {
                $userIdArr = $userIdArr1;

                $subQuery = UserOverrideHistory::select(
                    'id',
                    'user_id',
                    'override_effective_date',
                    DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY override_effective_date DESC, id DESC) as rn')
                )->where('override_effective_date', '<=', $date);

                // Main query to get the IDs where rn = 1
                $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                    ->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);

                // Final query to get the user_override_history records with the selected IDs
                $offices = UserOverrideHistory::select(
                    'users.id',
                    'user_override_history.product_id',
                    'user_override_history.override_effective_date'
                )
                    ->whereIn('user_override_history.id', $results->pluck('id'))
                    ->join('users', 'users.id', 'user_override_history.user_id')
                    ->whereIn('users.office_id', $userIdArr)->where('users.dismiss', 0)->get();

                $userIds = $offices->pluck('id');
                foreach ($offices as $office) {
                    $direct_override_status = OverrideStatus::create([
                        'user_id' => $office->id,
                        'recruiter_id' => $id,
                        'type' => 'Office',
                        'status' => 0,
                        'effective_date' => $office->override_effective_date,
                        'product_id' => isset($office->product_id) ? $office->product_id : 0,
                        'updated_by' => 1,
                    ]);
                }

            }
            if (count($userIdArr2) > 0) {
                $userIdArr = $userIdArr2;

                $subQuery = UserOverrideHistory::select(
                    'id',
                    'user_id',
                    'override_effective_date',
                    DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY override_effective_date DESC, id DESC) as rn')
                )->where('override_effective_date', '<=', $date);

                // Main query to get the IDs where rn = 1
                $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                    ->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);

                // Final query to get the user_override_history records with the selected IDs
                $offices = UserOverrideHistory::select(
                    'users.id',
                    'user_override_history.product_id',
                    'user_override_history.override_effective_date'
                )
                    ->whereIn('user_override_history.id', $results->pluck('id'))
                    ->join('users', 'users.id', 'user_override_history.user_id')
                    ->whereIn('users.office_id', $userIdArr)->where('users.dismiss', 0)->get();

                $userIds = $offices->pluck('id');
                foreach ($offices as $office) {
                    $direct_override_status = OverrideStatus::create([
                        'user_id' => $office->id,
                        'recruiter_id' => $id,
                        'type' => 'Office',
                        'status' => 0,
                        'effective_date' => $office->override_effective_date,
                        'product_id' => isset($office->product_id) ? $office->product_id : 0,
                        'updated_by' => 1,
                    ]);
                }
            }
            // OFFICE OVERRIDES END

            // MANUAL OVERRIDES START
            $manualSystemSetting = overrideSystemSetting::where('allow_manual_override_status', 1)->first();
            if ($manualSystemSetting) {
                $manualData = ManualOverrides::where('user_id', $user->id)->with('ManualOverridesHistory')->get();
                foreach ($manualData as $manual) {
                    $direct_override_status = OverrideStatus::create([
                        'user_id' => $manual->manual_user_id,
                        'recruiter_id' => $manual->user_id,
                        'type' => 'manual',
                        'status' => 0,
                        'effective_date' => $manual->effective_date,
                        'product_id' => isset($manual->product_id) ? $manual->product_id : 0,
                        'updated_by' => 1,
                    ]);
                }
            }
            // MANUAL OVERRIDES END

            // STACK OVERRIDES START
            $stackSystemSetting = overrideSystemSetting::where('allow_office_stack_override_status', 1)->first();
            if ($stackSystemSetting) {
                $office_id = $user->office_id;
                $userTransferHistory = UserTransferHistory::where('user_id', $user->id)->where('transfer_effective_date', '<=', $date)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
                if ($userTransferHistory) {
                    $office_id = $userTransferHistory->office_id;
                }
                $userIdArr1 = [$office_id];
                $userIdArr2 = AdditionalLocations::where('user_id', $user->id)->pluck('office_id')->toArray();
                $userIdArr = array_unique(array_merge($userIdArr1, $userIdArr2));

                $subQuery = UserOverrideHistory::select(
                    'id',
                    'user_id',
                    'override_effective_date',
                    DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY override_effective_date DESC, id DESC) as rn')
                )->where('override_effective_date', '<=', $date);

                // Main query to get the IDs where rn = 1
                $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                    ->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);

                // Final query to get the user_override_history records with the selected IDs
                $stacks = UserOverrideHistory::select(
                    'users.id',
                    'users.recruiter_id',
                    'user_override_history.product_id',
                    'user_override_history.override_effective_date'
                )
                    ->whereIn('user_override_history.id', $results->pluck('id'))
                    ->join('users', 'users.id', 'user_override_history.user_id')
                    ->whereIn('users.office_id', $userIdArr)->where('users.dismiss', 0)->get();
                foreach ($stacks as $stack) {
                    $direct_override_status = OverrideStatus::create([
                        'user_id' => $stack->id,
                        'recruiter_id' => $id,
                        'type' => 'Stack',
                        'status' => 0,
                        'effective_date' => $stack->override_effective_date,
                        'product_id' => isset($stack->product_id) ? $stack->product_id : 0,
                        'updated_by' => 1,
                    ]);
                }
            }

            echo 'process completed for userID '.$id."\n";
        }
        echo 'process completed for all users \\n';
    }
}
