<?php

namespace App\Console\Commands;

use App\Core\Traits\PayFrequencyTrait;
use App\Core\Traits\PermissionCheckTrait;
use App\Events\PusherProcessingCompleteEvent;
use App\Models\CompanyProfile;
use App\Models\ProjectionUserOverrides;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserOverrideQueue;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CalculateUserOverrideProjections extends Command
{
    use PayFrequencyTrait;
    use PermissionCheckTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */ // Artisan::call('calculateuseroverrideprojections:create');
    protected $signature = 'calculateuseroverrideprojections:create {user_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'command for creating user wise override projection values';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        set_time_limit(0);
        try {
            // Log::info('command called');
            $user_id = $this->argument('user_id');
            if (! $user_id) {
                $user_id = Auth::user()->id;
            }
            $id = $user_id;
            // $override_users = User::where('dismiss',0)
            // ->select('id','first_name','last_name', 'redline', 'office_id', 'recruiter_id', 'stop_payroll', 'sub_position_id', 'dismiss')
            // ->where(function($query) use($user_id){
            //     return $query->where('recruiter_id',$user_id)
            //     ->orWhere('additional_recruiter_id1',$user_id)
            //     ->orWhere('additional_recruiter_id2',$user_id);
            // })
            // ->get();

            $override_users = User::select('id')->with('childs', 'childs1', 'childs2')
                ->where('id', $id)
                ->orWhere('recruiter_id', $id)
                ->orWhere('additional_recruiter_id1', $id)
                ->orWhere('additional_recruiter_id2', $id)->get();

            ProjectionUserOverrides::where('sale_user_id', $id)->delete();

            $all_childs = [];
            foreach ($override_users as $overide_user) {
                $all_childs = array_merge($all_childs, $this->extractUniqueIds($overide_user));
            }
            foreach ($all_childs as $all_child) {
                $this->get_user_sales($all_child);
            }
            UserOverrideQueue::updateOrInsert(['user_id' => $user_id], ['processing' => '0']);
        } catch (Exception $e) {
            Log::info('Exception error '.$e->getMessage());
        }
    }

    public function overrideProcessingPusherNotification($user, $message, $status)
    {

        $response = [
            'event' => 'Projection',
            'userid' => $user,
            'status' => $status,
            'message' => $message,
        ];

        // Dispatch the event with the modified message
        event(new PusherProcessingCompleteEvent($response));

        return true;
    }

    public function get_user_sales($userid)
    {
        $calculator = new CalculateOverrideProjections;
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $sales = SalesMaster::with('salesMasterProcess:sale_master_id,closer1_id,closer2_id,setter1_id,setter2_id')
                ->whereHas('salesMasterProcess', function ($q) use ($userid) {
                    $q->where(function ($sub) {
                        $sub->whereNotNull('closer1_id')
                            ->orWhereNotNull('closer2_id');
                    })->where(function ($query) use ($userid) {
                        $query->where('closer1_id', $userid)
                            ->orWhere('closer2_id', $userid);
                    });
                })
                ->whereNotNull('customer_signoff')->whereNull('m2_date')
                ->whereNull('date_cancelled')
                ->orderBy('customer_signoff', 'ASC')
                ->get();
        } else {
            $sales = SalesMaster::with('salesMasterProcess:sale_master_id,closer1_id,closer2_id,setter1_id,setter2_id')
                ->whereHas('salesMasterProcess', function ($q) use ($userid) {
                    $q->whereNotNull('closer1_id')
                        ->whereNotNull('setter1_id')
                        ->where(function ($query) use ($userid) {
                            $query->where('closer1_id', $userid)
                                ->orWhere('setter1_id', $userid);
                        });
                })
                ->whereNotNull('customer_signoff')->whereNull('m2_date')
                ->whereNull('date_cancelled')
                ->orderBy('customer_signoff', 'ASC')
                ->get();
        }

        foreach ($sales as $sale) {
            // $calculator->calculateoverride($sale);
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $calculator->pestCalculateoverride($sale);
            } else {
                $calculator->calculateoverride($sale);
            }
        }
    }

    public function extractUniqueIds($data, &$ids = [])
    {
        if (! empty($data->id)) {
            $ids[] = $data->id;
        }

        if (! empty($data->childs)) {
            foreach ($data->childs as $child) {
                $this->extractUniqueIds($child, $ids);
            }
        }
        if (! empty($data->childs1)) {
            foreach ($data->childs1 as $child1) {
                $this->extractUniqueIds($child1, $ids);
            }
        }
        if (! empty($data->childs2)) {
            foreach ($data->childs2 as $child2) {
                $this->extractUniqueIds($child2, $ids);
            }
        }

        return array_unique($ids);
    }
}
