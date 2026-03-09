<?php

namespace App\Console\Commands;

use App\Core\Traits\PayFrequencyTrait;
use App\Core\Traits\PermissionCheckTrait;
use App\Core\Traits\ReconciliationPeriodTrait;
use App\Models\AdditionalLocations;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\LocationRedlineHistory;
use App\Models\Locations;
use App\Models\ManualOverrides;
use App\Models\ManualOverridesHistory;
use App\Models\OverrideStatus;
use App\Models\overrideSystemSetting;
use App\Models\PositionOverride;
use App\Models\PositionReconciliations;
use App\Models\ProjectionUserOverrides;
use App\Models\SaleMasterProjections;
use App\Models\SalesMaster;
use App\Models\State;
use App\Models\User;
use App\Models\UserAdditionalOfficeOverrideHistory;
use App\Models\UserCommissionHistory;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserOverrides;
use App\Models\UserRedlines;
use App\Models\UserSelfGenCommmissionHistory;
use App\Models\UserTransferHistory;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalculateOverrideProjections extends Command
{
    use PayFrequencyTrait;
    use PermissionCheckTrait;
    use ReconciliationPeriodTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */ // Artisan::call('calculateoverrideprojections:create');
    protected $signature = 'calculateoverrideprojections:create';

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
            $sales = SalesMaster::with('salesMasterProcess:sale_master_id,closer1_id,closer2_id,setter1_id,setter2_id')
                ->whereNotNull('customer_signoff')
                ->whereNull('m2_date')
                ->whereNull('date_cancelled')
                // ->where('pid', 'TestProjection')
                ->orderBy('customer_signoff', 'ASC')
                ->get();
            if (! empty($sales)) {
                $companyProfile = CompanyProfile::first();
                foreach ($sales as $sale) {
                    // $this->calculateoverride($sale);
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $this->pestCalculateoverride($sale);
                    } else {
                        $this->calculateoverride($sale);
                    }
                }
            }
        } catch (Exception $e) {
            Log::info('Exception error '.$e->getMessage());
        }
    }

    public function calculateoverride($checked)
    {
        ProjectionUserOverrides::where('pid', $checked->salesMasterProcess->pid)->delete();

        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;
        $m2date = $checked->m2_date;
        $kw = $checked->kw;
        $netEpc = $checked->net_epc;
        $approvedDate = $checked->customer_signoff;

        $overrideSetting = CompanySetting::where('type', 'overrides')->first();
        $companyMargin = CompanyProfile::where('id', 1)->first();
        // Get Pull user Redlines from subroutineSix
        $redline = $this->subroutineSix($checked);

        // Calculate setter & closer commission
        $setter_commission = 0;
        if ($setterId != null && $setter2Id != null) {
            $setter = User::where('id', $setterId)->first();
            $organizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $setter = $organizationHistory;
            }
            if ($setter->self_gen_accounts == 1 && $setter->position_id == 2) {
                $commission_percentage = 0;
                $commission_type = null;
                // $positionId = ($setter->position_id==2)? '3':$setter->position_id;
                $commissionHistory = UserCommissionHistory::where('user_id', $setterId)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionHistory) {
                    $commission_percentage = $commissionHistory->commission;
                    $commission_type = $commissionHistory->commission_type;
                }
            } else {
                $commission_percentage = 0;
                $commission_type = null;
                // $positionId = $setter->position_id;
                $commissionHistory = UserCommissionHistory::where('user_id', $setterId)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionHistory) {
                    $commission_percentage = $commissionHistory->commission;
                    $commission_type = $commissionHistory->commission_type;
                }
            }

            $setter2 = User::where('id', $setter2Id)->first();
            $organizationHistory2 = UserOrganizationHistory::where('user_id', $setter2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory2) {
                $setter2 = $organizationHistory2;
            }
            if ($setter2->self_gen_accounts == 1 && $setter2->position_id == 2) {
                $commission_percentage2 = 0;
                $commission_type2 = null;
                // $positionId = ($setter2->position_id==2)? '3':$setter2->position_id;
                $commission2History = UserCommissionHistory::where('user_id', $setter2Id)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commission2History) {
                    $commission_percentage2 = $commission2History->commission;
                    $commission_type2 = $commission2History->commission_type;
                }
            } else {
                $commission_percentage2 = 0;
                $commission_type2 = null;
                // $positionId = $setter2->position_id;
                $commission2History = UserCommissionHistory::where('user_id', $setter2Id)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commission2History) {
                    $commission_percentage2 = $commission2History->commission;
                    $commission_type2 = $commission2History->commission_type;
                }
            }

            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $margin_percentage = $companyMargin->company_margin;
                $x = ((100 - $margin_percentage) / 100);

                if ($commission_type == 'per kw') {
                    $setter1_commission = ($kw * $commission_percentage * $x * 0.5);
                } else {
                    $setter1_commission = ((($netEpc - $redline['setter1_redline']) * $x) * $kw * 1000 * $commission_percentage / 100) * 0.5;
                }

                if ($commission_type2 == 'per kw') {
                    $setter2_commission = ($kw * $commission_percentage2 * $x * 0.5);
                } else {
                    $setter2_commission = ((($netEpc - $redline['setter2_redline']) * $x) * $kw * 1000 * $commission_percentage2 / 100) * 0.5;
                }
            } else {
                if ($commission_type == 'per kw') {
                    $setter1_commission = ($kw * $commission_percentage * 0.5);
                } else {
                    $setter1_commission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage / 100) * 0.5;
                }

                if ($commission_type2 == 'per kw') {
                    $setter2_commission = ($kw * $commission_percentage2 * 0.5);
                } else {
                    $setter2_commission = (($netEpc - $redline['setter2_redline']) * $kw * 1000 * $commission_percentage2 / 100) * 0.5;
                }
            }
            $setter_commission = ($setter1_commission + $setter2_commission);
            $where1 = [
                'pid' => $checked->pid,
            ];

            $update1 = [
                'setter1_id' => $setterId,
                'setter2_id' => $setter2Id,
                'setter1_commission' => $setter1_commission,
                'setter2_commission' => $setter2_commission,
            ];
            SaleMasterProjections::updateOrCreate(
                $where1,
                $update1
            );
            $this->UserOverride($setterId, $checked->pid, $kw, $m2date, $redline['setter1_redline']);
            $this->UserOverride($setter2Id, $checked->pid, $kw, $m2date, $redline['setter2_redline']);
        } elseif ($setterId) {
            if ($closerId != $setterId) {
                $setter = User::where('id', $setterId)->first();
                $organizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $setter = $organizationHistory;
                }

                if ($setter->self_gen_accounts == 1 && $setter->position_id == 2) {
                    $commission_percentage = 0; // percenge
                    $commission_type = null;
                    // $positionId = ($setter->position_id==2)? '3':$setter->position_id;
                    $commissionHistory = UserCommissionHistory::where('user_id', $setterId)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                } else {
                    $commission_percentage = 0; // percenge
                    $commission_type = null;
                    // $positionId = $setter->position_id;
                    $commissionHistory = UserCommissionHistory::where('user_id', $setterId)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                }

                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                    $margin_percentage = $companyMargin->company_margin;
                    $x = ((100 - $margin_percentage) / 100);
                    if ($commission_type == 'per kw') {
                        $setter_commission = (($kw * $commission_percentage) * $x);
                    } else {
                        $setter_commission = ((($netEpc - $redline['setter1_redline']) * $x) * $kw * 1000 * $commission_percentage / 100);
                    }
                } else {
                    if ($commission_type == 'per kw') {
                        $setter_commission = ($kw * $commission_percentage);
                    } else {
                        $setter_commission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage / 100);
                    }
                }

                $where1 = [
                    'pid' => $checked->pid,
                ];

                $update1 = [
                    'setter1_id' => $setterId,
                    'setter1_commission' => $setter_commission,
                ];

                SaleMasterProjections::updateOrCreate(
                    $where1,
                    $update1
                );
                $this->UserOverride($setterId, $checked->pid, $kw, $m2date, $redline['setter1_redline']);
            }
        }

        $closer_commission = 0;
        if ($closerId != null && $closer2Id != null) {

            $closer = User::where('id', $closerId)->first();
            $organizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $closer = $organizationHistory;
            }

            if ($closer->self_gen_accounts == 1 && $closer->position_id == 3) {
                $commission_percentage = 0;
                $commission_type = null;
                // $positionId = ($closer->position_id==3)? '2':$closer->position_id;
                $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionHistory) {
                    $commission_percentage = $commissionHistory->commission;
                    $commission_type = $commissionHistory->commission_type;
                }
            } else {
                $commission_percentage = 0; // percenge
                $commission_type = null;
                // $positionId = $closer->position_id;
                $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionHistory) {
                    $commission_percentage = $commissionHistory->commission;
                    $commission_type = $commissionHistory->commission_type;
                }
            }

            $closer2 = User::where('id', $closer2Id)->first();
            $organizationHistory2 = UserOrganizationHistory::where('user_id', $closer2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory2) {
                $closer2 = $organizationHistory2;
            }
            if ($closer2->self_gen_accounts == 1 && $closer2->position_id == 3) {
                $commission_percentage2 = 0;
                $commission_type2 = null;
                // $positionId = ($closer2->position_id==3)? '2':$closer2->position_id;
                $commission2History = UserCommissionHistory::where('user_id', $closer2Id)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commission2History) {
                    $commission_percentage2 = $commission2History->commission;
                    $commission_type2 = $commission2History->commission_type;
                }
            } else {
                $commission_percentage2 = 0; // percenge
                $commission_type2 = null;
                // $positionId = $closer2->position_id;
                $commission2History = UserCommissionHistory::where('user_id', $closer2Id)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commission2History) {
                    $commission_percentage2 = $commission2History->commission;
                    $commission_type2 = $commission2History->commission_type;
                }
            }

            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $margin_percentage = $companyMargin->company_margin;
                $x = ((100 - $margin_percentage) / 100);
                if ($commission_type == 'per kw') {
                    $closer1_commission = ($kw * $commission_percentage * $x * 0.5);
                } else {
                    $closer1_commission = (((($netEpc - $redline['closer1_redline']) * $x) * $kw * 1000) * ($commission_percentage / 100)) * 0.5;
                }

                if ($commission_type2 == 'per kw') {
                    $closer2_commission = ($kw * $commission_percentage2 * $x * 0.5);
                } else {
                    $closer2_commission = (((($netEpc - $redline['closer2_redline']) * $x) * $kw * 1000) * ($commission_percentage2 / 100)) * 0.5;
                }
            } else {
                if ($commission_type == 'per kw') {
                    $closer1_commission = ($kw * $commission_percentage * 0.5);
                } else {
                    $closer1_commission = ((($netEpc - $redline['closer1_redline']) * $kw * 1000) * ($commission_percentage / 100)) * 0.5;
                }

                if ($commission_type2 == 'per kw') {
                    $closer2_commission = ($kw * $commission_percentage2 * 0.5);
                } else {
                    $closer2_commission = ((($netEpc - $redline['closer2_redline']) * $kw * 1000) * ($commission_percentage2 / 100)) * 0.5;
                }
            }

            $where1 = [
                'pid' => $checked->pid,
            ];

            $update1 = [
                'closer1_id' => $closerId,
                'closer2_id' => $closer2Id,
                'closer1_commission' => $closer1_commission,
                'closer2_commission' => $closer2_commission,
            ];
            SaleMasterProjections::updateOrCreate(
                $where1,
                $update1
            );
            if ($overrideSetting->status == '1') {
                $this->UserOverride($closerId, $checked->pid, $kw, $m2date, $redline['closer1_redline']);
                $this->UserOverride($closer2Id, $checked->pid, $kw, $m2date, $redline['closer2_redline']);
            }
        } elseif ($closerId) {
            $closer = User::where('id', $closerId)->first();
            $organizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $closer = $organizationHistory;
            }

            if ($closerId == $setterId && $closer->self_gen_accounts == 1) {
                $commission_percentage = 100;
                $commission_type = null;
            } else {
                if ($closer->self_gen_accounts == 1 && $closer->position_id == 3) {
                    $commission_percentage = 0;
                    $commission_type = null;
                    // $positionId = ($closer->position_id == 3) ? '2' : $closer->position_id;
                    $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('self_gen_user', 1)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                } else {
                    $commission_percentage = 0; // percenge
                    $commission_type = null;
                    // $positionId = $closer->position_id;
                    $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                        $commission_type = $commissionHistory->commission_type;
                    }
                }
            }

            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $margin_percentage = $companyMargin->company_margin;
                $x = ((100 - $margin_percentage) / 100);
                if ($commission_type == 'per kw') {
                    $closer_commission = (($kw * $commission_percentage) * $x);
                } else {
                    $closer_commission = ((($netEpc - $redline['closer1_redline']) * $x) * $kw * 1000 * $commission_percentage / 100);
                }
            } else {
                if ($commission_type == 'per kw') {
                    $closer_commission = ($kw * $commission_percentage);
                } else {
                    $closer_commission = (($netEpc - $redline['closer1_redline']) * $kw * 1000 * $commission_percentage / 100);
                }
            }

            // $closer_commission = (($netEpc - $redline['closer1_redline']) * $kw * 1000 * $commission_percentage/100);
            if ($closerId == $setterId && $closer->self_gen_accounts == 1) {
                $commissionSelfgen = UserSelfGenCommmissionHistory::where('user_id', $closerId)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionSelfgen && $commissionSelfgen->commission > 0) {
                    $selfgen_percentage = $commissionSelfgen->commission;
                    if ($commissionSelfgen->commission_type == 'per kw') {
                        $x = isset($x) && ! empty($x) ? $x : 1;
                        $closer_commission = ($kw * $selfgen_percentage * $x);
                    } else {
                        $closer_commission = ($closer_commission * $selfgen_percentage / 100);
                    }
                }
            }

            $where1 = [
                'pid' => $checked->pid,
            ];

            $update1 = [
                'closer1_id' => $closerId,
                'closer1_commission' => $closer_commission,
            ];
            SaleMasterProjections::updateOrCreate(
                $where1,
                $update1
            );
            $this->UserOverride($closerId, $checked->pid, $kw, $m2date, $redline['closer1_redline']);
        }

        $this->StackUserOverride($closerId, $checked->pid, $kw, $m2date);

        // $this->StackUserOverride($closer2Id, $checked->pid, $kw, $m2date);
        return true;
    }

    private function userRedline($userdata, $saleState, $approvedDate)
    {
        $generalCode = Locations::where('general_code', $saleState)->first();
        if ($generalCode) {
            $locationRedlines = LocationRedlineHistory::where('location_id', $generalCode->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($locationRedlines) {
                $saleStandardRedline = $locationRedlines->redline_standard;
            } else {
                $saleStandardRedline = $generalCode->redline_standard;
            }

        } else {
            // customer state Id..................................................
            $state = State::where('state_code', $saleState)->first();
            $saleStateId = isset($state->id) ? $state->id : 0;
            $location = Locations::where('state_id', $saleStateId)->first();
            $locationId = isset($location->id) ? $location->id : 0;
            $locationRedlines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($locationRedlines) {
                $saleStandardRedline = $locationRedlines->redline_standard;
            } else {
                $saleStandardRedline = isset($location->redline_standard) ? $location->redline_standard : 0;
            }

        }

        if ($userdata) {

            $organizationHistory = UserOrganizationHistory::where('user_id', $userdata->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $userredlinesdata = $organizationHistory;
            } else {
                $userredlinesdata = $userdata;
            }

            if ($userredlinesdata->self_gen_accounts == 1 && $userredlinesdata->position_id == 3) {
                $positionId = ($userredlinesdata->position_id == 3) ? '2' : $userredlinesdata->position_id;
                // $userRedlines = UserRedlines::where('user_id',$userdata->id)->where('start_date', '<=', $approvedDate)->where('position_type', $positionId)->where('self_gen_user',1)->orderBy('start_date', 'DESC')->first();
                $userRedlines = UserRedlines::where('user_id', $userdata->id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
            } else {
                $positionId = $userredlinesdata->position_id;
                // $userRedlines = UserRedlines::where('user_id',$userdata->id)->where('start_date', '<=', $approvedDate)->where('position_type', $positionId)->where('self_gen_user',0)->orderBy('start_date', 'DESC')->first();
                $userRedlines = UserRedlines::where('user_id', $userdata->id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
            }
            // $userRedlines = UserRedlines::where('user_id',$userdata->id)->where('start_date', '<=', $approvedDate)->where('position_type',2)->where('self_gen_user',0)->orderBy('start_date', 'DESC')->first();
            if ($userRedlines) {
                $closer_redline = $userRedlines->redline;
                $redline_amount_type = $userRedlines->redline_amount_type;
            } else {
                $closer_redline = $userdata->redline;
                $redline_amount_type = $userdata->redline_amount_type;
            }

            $closerOfficeId = $userdata->office_id;

            if ($redline_amount_type == 'Fixed') {

                $closer1_redline = $closer_redline;

            } else {
                $closerLocation = Locations::where('id', $closerOfficeId)->first();
                $location_id = isset($closerLocation->id) ? $closerLocation->id : 0;
                $locationRedlines = LocationRedlineHistory::where('location_id', $location_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($locationRedlines) {
                    $closerStateRedline = $locationRedlines->redline_standard;
                } else {
                    $closerStateRedline = isset($closerLocation->redline_standard) ? $closerLocation->redline_standard : 0;
                }

                // closer_redline
                $redline = $saleStandardRedline + ($closer_redline - $closerStateRedline);
                $closer1_redline = $redline;
            }

        }

        return $closer1_redline;

    }

    public function subroutineSix($checked)
    {
        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;

        // $customerRedline = $checked->redline;
        $approvedDate = $checked->customer_signoff;
        // $saleState = $checked->customer_state;

        if (config('app.domain_name') == 'flex') {
            $saleState = $checked->customer_state;
        } else {
            $saleState = $checked->location_code;
        }

        $generalCode = Locations::where('general_code', $saleState)->first();
        if ($generalCode) {
            $locationRedlines = LocationRedlineHistory::where('location_id', $generalCode->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($locationRedlines) {
                $saleStandardRedline = $locationRedlines->redline_standard;
            } else {
                $saleStandardRedline = $generalCode->redline_standard;
            }
        } else {
            // customer state Id..................................................
            $state = State::where('state_code', $saleState)->first();
            $saleStateId = isset($state->id) ? $state->id : 0;
            $location = Locations::where('state_id', $saleStateId)->first();
            $locationId = isset($location->id) ? $location->id : 0;
            $locationRedlines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($locationRedlines) {
                $saleStandardRedline = $locationRedlines->redline_standard;
            } else {
                $saleStandardRedline = isset($location->redline_standard) ? $location->redline_standard : 0;
            }
        }

        if ($approvedDate != null) {
            $data['closer1_redline'] = '0';
            $data['closer2_redline'] = '0';
            $data['setter1_redline'] = '0';
            $data['setter2_redline'] = '0';

            if ($setterId && $setter2Id) {
                $setter1 = User::where('id', $setterId)->first();
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if (@$userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory['position_id'] == 2) {
                    $userRedLines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                } else {
                    $userRedLines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                }
                if ($userRedLines) {
                    $setterRedLine = $userRedLines->redline;
                    $redLineAmountType = $userRedLines->redline_amount_type;
                } else {
                    $setterRedLine = $setter1->redline;
                    $redLineAmountType = $setter1->redline_amount_type;
                }

                $setterOfficeId = $setter1->office_id;
                if ($redLineAmountType == 'Fixed') {
                    $data['setter1_redline'] = $setterRedLine;
                } else {
                    $setterLocation = Locations::where('id', $setterOfficeId)->first();
                    $locationId = isset($setterLocation->id) ? $setterLocation->id : 0;
                    $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedLines) {
                        $setterStateRedLine = $locationRedLines->redline_standard;
                    } else {
                        $setterStateRedLine = isset($setterLocation->redline_standard) ? $setterLocation->redline_standard : 0;
                    }
                    $redLine = $saleStandardRedline + ($setterRedLine - $setterStateRedLine);
                    $data['setter1_redline'] = $redLine;
                }

                $setter2 = User::where('id', $setter2Id)->first();
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setter2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if (@$userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory['position_id'] == 2) {
                    $userRedLines = UserRedlines::where('user_id', $setter2Id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                } else {
                    $userRedLines = UserRedlines::where('user_id', $setter2Id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                }
                if ($userRedLines) {
                    $setterRedLine = $userRedLines->redline;
                    $redLineAmountType = $userRedLines->redline_amount_type;
                } else {
                    $setterRedLine = $setter2->redline;
                    $redLineAmountType = $setter2->redline_amount_type;
                }

                $setterOfficeId = $setter2->office_id;
                if ($redLineAmountType == 'Fixed') {
                    $data['setter2_redline'] = $setterRedLine;
                } else {
                    $setterLocation = Locations::where('id', $setterOfficeId)->first();
                    $locationId = isset($setterLocation->id) ? $setterLocation->id : 0;
                    $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedLines) {
                        $setterStateRedLine = $locationRedLines->redline_standard;
                    } else {
                        $setterStateRedLine = isset($setterLocation->redline_standard) ? $setterLocation->redline_standard : 0;
                    }
                    $redLine = $saleStandardRedline + ($setterRedLine - $setterStateRedLine);
                    $data['setter2_redline'] = $redLine;
                }
            } elseif ($setterId) {
                $setter = User::where('id', $setterId)->first();
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $setterId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($closerId == $setterId && @$userOrganizationHistory->self_gen_accounts == 1) {
                    if ($userOrganizationHistory->position_id == '3') {
                        $userRedLines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                    } else {
                        $userRedLines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                    }
                    if ($userRedLines) {
                        $setterRedLine = $userRedLines->redline;
                        $redLineAmountType = $userRedLines->redline_amount_type;
                    } else {
                        $setterRedLine = $setter->redline;
                        $redLineAmountType = $setter->redline_amount_type;
                    }
                } else {
                    if (@$userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory['position_id'] == 2) {
                        $userRedLines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                    } else {
                        $userRedLines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                    }
                    if ($userRedLines) {
                        $setterRedLine = $userRedLines->redline;
                        $redLineAmountType = $userRedLines->redline_amount_type;
                    } else {
                        $setterRedLine = $setter->redline;
                        $redLineAmountType = $setter->redline_amount_type;
                    }
                }

                $setterOfficeId = $setter->office_id;
                if ($redLineAmountType == 'Fixed') {
                    $data['setter1_redline'] = $setterRedLine;
                } else {
                    $setterLocation = Locations::where('id', $setterOfficeId)->first();
                    $locationId = isset($setterLocation->id) ? $setterLocation->id : 0;
                    $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedLines) {
                        $setterStateRedLine = $locationRedLines->redline_standard;
                    } else {
                        $setterStateRedLine = isset($setterLocation->redline_standard) ? $setterLocation->redline_standard : 0;
                    }

                    $redLine = $saleStandardRedline + ($setterRedLine - $setterStateRedLine);
                    $data['setter1_redline'] = $redLine;
                }
            }

            if ($closerId && $closer2Id) {
                $closer1 = User::where('id', $closerId)->first();
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if (@$userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory['position_id'] == 3) {
                    $userRedLines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                } else {
                    $userRedLines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                }
                if ($userRedLines) {
                    $closerRedLine = $userRedLines->redline;
                    $redLineAmountType = $userRedLines->redline_amount_type;
                } else {
                    $closerRedLine = $closer1->redline;
                    $redLineAmountType = $closer1->redline_amount_type;
                }

                $closerOfficeId = $closer1->office_id;
                if ($redLineAmountType == 'Fixed') {
                    $data['closer1_redline'] = $closerRedLine;
                } else {
                    $closerLocation = Locations::where('id', $closerOfficeId)->first();
                    $locationId = isset($closerLocation->id) ? $closerLocation->id : 0;
                    $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedLines) {
                        $closerStateRedLine = $locationRedLines->redline_standard;
                    } else {
                        $closerStateRedLine = isset($closerLocation->redline_standard) ? $closerLocation->redline_standard : 0;
                    }
                    $redLine = $saleStandardRedline + ($closerRedLine - $closerStateRedLine);
                    $data['closer1_redline'] = $redLine;
                }

                $closer2 = User::where('id', $closer2Id)->first();
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closer2Id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if (@$userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory['position_id'] == 3) {
                    $userRedLines = UserRedlines::where('user_id', $closer2Id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                } else {
                    $userRedLines = UserRedlines::where('user_id', $closer2Id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                }
                if ($userRedLines) {
                    $closerRedLine = $userRedLines->redline;
                    $redLineAmountType = $userRedLines->redline_amount_type;
                } else {
                    $closerRedLine = $closer2->redline;
                    $redLineAmountType = $closer2->redline_amount_type;
                }

                $closerOfficeId = $closer2->office_id;
                if ($redLineAmountType == 'Fixed') {
                    $data['closer2_redline'] = $closerRedLine;
                } else {
                    $closerLocation = Locations::where('id', $closerOfficeId)->first();
                    $locationId = isset($closerLocation->id) ? $closerLocation->id : 0;
                    $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedLines) {
                        $closerStateRedLine = $locationRedLines->redline_standard;
                    } else {
                        $closerStateRedLine = isset($closerLocation->redline_standard) ? $closerLocation->redline_standard : 0;
                    }
                    $redLine = $saleStandardRedline + ($closerRedLine - $closerStateRedLine);
                    $data['closer2_redline'] = $redLine;
                }
            } elseif ($closerId) {
                $closer = User::where('id', $closerId)->first();
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $closerId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($closerId == $setterId && @$userOrganizationHistory['self_gen_accounts'] == 1) {
                    if ($userOrganizationHistory->position_id == '3') {
                        $userRedLines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                    } else {
                        $userRedLines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                    }
                    if ($userRedLines) {
                        $closerRedLine = $userRedLines->redline;
                        $redLineAmountType = $userRedLines->redline_amount_type;
                    } else {
                        $closerRedLine = $closer->redline;
                        $redLineAmountType = $closer->redline_amount_type;
                    }
                } else {
                    if (@$userOrganizationHistory->self_gen_accounts == 1 && $userOrganizationHistory['position_id'] == 3) {
                        $userRedLines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                    } else {
                        $userRedLines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                    }
                    if ($userRedLines) {
                        $closerRedLine = $userRedLines->redline;
                        $redLineAmountType = $userRedLines->redline_amount_type;
                    } else {
                        $closerRedLine = $closer->redline;
                        $redLineAmountType = $closer->redline_amount_type;
                    }
                }

                $closerOfficeId = $closer->office_id;
                if ($redLineAmountType == 'Fixed') {
                    $data['closer1_redline'] = $closerRedLine;
                } else {
                    $closerLocation = Locations::where('id', $closerOfficeId)->first();
                    $locationId = isset($closerLocation->id) ? $closerLocation->id : 0;
                    $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedLines) {
                        $closerStateRedLine = $locationRedLines->redline_standard;
                    } else {
                        $closerStateRedLine = isset($closerLocation->redline_standard) ? $closerLocation->redline_standard : 0;
                    }
                    $redLine = $saleStandardRedline + ($closerRedLine - $closerStateRedLine);
                    $data['closer1_redline'] = $redLine;
                }

                if ($closerId == $setterId && @$userOrganizationHistory['self_gen_accounts'] == 1) {
                    $redLine1 = $data['setter1_redline'];
                    $redLine2 = $data['closer1_redline'];
                    if ($redLine1 > $redLine2) {
                        $data['closer1_redline'] = $redLine2;
                    } else {
                        $data['closer1_redline'] = $redLine1;
                    }
                }
            }

            return $data;
        }
    }

    public function UserOverride($sale_user_id, $pid, $kw, $date, $redline)
    {
        $saleMaster = SalesMaster::where('pid', $pid)->first();
        $netEpc = $saleMaster->net_epc;
        $approvedDate = isset($saleMaster->customer_signoff) ? $saleMaster->customer_signoff : null;
        $recruiter_id_data = User::where('id', $sale_user_id)->where('dismiss', 0)->first();
        $companyMargin = CompanyProfile::where('id', 1)->first();
        if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
            $margin_percentage = $companyMargin->company_margin;
            $x = ((100 - $margin_percentage) / 100);
        } else {
            $x = 1;
        }

        // OFFICE OVERRIDES CODE
        if ($recruiter_id_data && $recruiter_id_data->office_id) {
            $office_id = $recruiter_id_data->office_id;
            $userTransferHistory = UserTransferHistory::where('user_id', $sale_user_id)->where('transfer_effective_date', '<=', $approvedDate)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $office_id = $userTransferHistory->office_id;
            }
            $userIdArr1 = User::select('id', 'stop_payroll', 'sub_position_id', 'dismiss', 'office_overrides_amount', 'office_overrides_type')
                ->where(['office_id' => $office_id, 'dismiss' => '0'])->whereNotIn('id', ['1'])->get();

            foreach ($userIdArr1 as $userData) {
                $stopPayroll = ($userData->stop_payroll == 1) ? 1 : 0;

                $organizationHistory = UserOrganizationHistory::where('user_id', $userData->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $positionId = $organizationHistory->sub_position_id;
                } else {
                    $positionId = $userData->sub_position_id;
                }

                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'override_settlement' => 'Reconciliation', 'status' => '1'])->first();
                if ($positionReconciliation) {
                    $settlementType = 'reconciliation';
                } else {
                    $settlementType = 'during_m2';
                }

                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'override_id' => '3', 'status' => '1'])->first();
                if ($positionOverride) {
                    $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $userData->id, 'type' => 'Office', 'status' => 1])->first();
                    if (! $overrideStatus && $userData) {
                        $userData->office_overrides_amount = 0;
                        $userData->office_overrides_type = '';

                        $overrideHistory = UserOverrideHistory::where('user_id', $userData->id)->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->first();
                        if ($overrideHistory) {
                            $userData->office_overrides_amount = $overrideHistory->office_overrides_amount;
                            $userData->office_overrides_type = $overrideHistory->office_overrides_type;
                        }

                        if ($userData->office_overrides_amount) {
                            if ($userData->office_overrides_type == 'per kw') {
                                $amount = $userData->office_overrides_amount * $kw;
                            } elseif ($userData->office_overrides_type == 'percent') {
                                $commissionHistory = UserCommissionHistory::where(['user_id' => $userData->id, 'self_gen_user' => '0'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                                if ($commissionHistory && $commissionHistory->commission_type == 'per kw') {
                                    $commission_percentage = $commissionHistory->commission;
                                    $amount = ($kw * $commission_percentage * $x * ($userData->office_overrides_amount / 100));
                                } else {
                                    $amount = ((($netEpc - $redline) * $x) * $kw * 1000 * ($userData->office_overrides_amount / 100));
                                }
                            } else {
                                $amount = $userData->office_overrides_amount;
                            }

                            $where = [
                                'user_id' => $userData->id,
                                'type' => 'Office',
                                'pid' => $pid,
                                'sale_user_id' => $sale_user_id,
                            ];

                            $update = [
                                'customer_name' => $saleMaster->customer_name,
                                'kw' => $kw,
                                'total_override' => $amount,
                                'overrides_amount' => $userData->office_overrides_amount,
                                'overrides_type' => $userData->office_overrides_type,
                                'overrides_settlement_type' => $settlementType,
                                'status' => 1,
                                'is_stop_payroll' => $stopPayroll,
                                'office_id' => $office_id,
                            ];

                            $officeOverrides = ProjectionUserOverrides::where(['user_id' => $userData->id, 'type' => 'Office', 'pid' => $pid])->first();
                            if ($officeOverrides) {
                                if ($amount > $officeOverrides->total_override) {
                                    ProjectionUserOverrides::where('id', $officeOverrides->id)->where('status', 1)->delete();
                                    if ($userData->office_overrides_type) {
                                        ProjectionUserOverrides::updateOrCreate($where, $update);
                                    }
                                }
                            } else {
                                ProjectionUserOverrides::where(['user_id' => $userData->id, 'pid' => $pid, 'type' => 'Office', 'status' => 1])->delete();
                                if ($userData->office_overrides_type) {
                                    ProjectionUserOverrides::updateOrCreate($where, $update);
                                }
                            }
                        }
                    }
                }
            }

            $userIdArr2 = AdditionalLocations::whereHas('user')->with('user:id,stop_payroll,sub_position_id,dismiss,office_overrides_amount,office_overrides_type')
                ->where(['office_id' => $office_id])->whereNotIn('user_id', ['1', $sale_user_id])->get();
            foreach ($userIdArr2 as $userData) {
                $userData = $userData->user;
                $stopPayroll = ($userData->stop_payroll == 1) ? 1 : 0;
                $organizationHistory = UserOrganizationHistory::where('user_id', $userData->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $positionId = $organizationHistory->sub_position_id;
                } else {
                    $positionId = $userData->sub_position_id;
                }

                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
                if ($positionReconciliation) {
                    $settlementType = 'reconciliation';
                } else {
                    $settlementType = 'during_m2';
                }

                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'override_id' => '3', 'status' => '1'])->first();
                if ($positionOverride) {
                    $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $userData->id, 'type' => 'Office', 'status' => '1'])->first();
                    if (! $overrideStatus && $userData->dismiss == '0') {
                        $userData->office_overrides_amount = 0;
                        $userData->office_overrides_type = '';

                        $overrideHistory = UserAdditionalOfficeOverrideHistory::where(['user_id' => $userData->id, 'office_id' => $office_id])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->first();
                        if ($overrideHistory) {
                            $userData->office_overrides_amount = $overrideHistory->office_overrides_amount;
                            $userData->office_overrides_type = $overrideHistory->office_overrides_type;
                        }

                        if ($userData->office_overrides_amount) {
                            if ($userData->office_overrides_type == 'per kw') {
                                $amount = $userData->office_overrides_amount * $kw;
                            } elseif ($userData->office_overrides_type == 'percent') {
                                $commissionHistory = UserCommissionHistory::where(['user_id' => $userData->id, 'self_gen_user' => '0'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                                if ($commissionHistory && $commissionHistory->commission_type == 'per kw') {
                                    $commission_percentage = $commissionHistory->commission;
                                    $amount = ($kw * $commission_percentage * $x * ($userData->office_overrides_amount / 100));
                                } else {
                                    $amount = ((($netEpc - $redline) * $x) * $kw * 1000 * ($userData->office_overrides_amount / 100));
                                }
                            } else {
                                $amount = $userData->office_overrides_amount;
                            }

                            $where = [
                                'user_id' => $userData->id,
                                'type' => 'Office',
                                'pid' => $pid,
                                'sale_user_id' => $sale_user_id,
                            ];

                            $update = [
                                'customer_name' => $saleMaster->customer_name,
                                'kw' => $kw,
                                'total_override' => $amount,
                                'overrides_amount' => $userData->office_overrides_amount,
                                'overrides_type' => $userData->office_overrides_type,
                                'overrides_settlement_type' => $settlementType,
                                'status' => 1,
                                'is_stop_payroll' => $stopPayroll,
                                'office_id' => $office_id,
                            ];

                            $officeOverrides = ProjectionUserOverrides::where(['user_id' => $userData->id, 'type' => 'Office', 'pid' => $pid])->first();
                            if ($officeOverrides) {
                                if ($amount > $officeOverrides->total_override) {
                                    ProjectionUserOverrides::where('id', $officeOverrides->id)->where('status', 1)->delete();
                                    if ($userData->office_overrides_type) {
                                        ProjectionUserOverrides::updateOrCreate($where, $update);
                                    }
                                }
                            } else {
                                ProjectionUserOverrides::where(['user_id' => $userData->id, 'pid' => $pid, 'type' => 'Office', 'status' => 1])->delete();
                                if ($userData->office_overrides_type) {
                                    ProjectionUserOverrides::updateOrCreate($where, $update);
                                }
                            }
                        }
                    }
                }
            }
        }
        // END OFFICE OVERRIDES CODE

        // DIRECT & INDIRECT OVERRIDES CODE
        if ($recruiter_id_data && $recruiter_id_data->recruiter_id) {
            $recruiter_ids = $recruiter_id_data->recruiter_id;
            if (! empty($recruiter_id_data->additional_recruiter_id1)) {
                $recruiter_ids .= ','.$recruiter_id_data->additional_recruiter_id1;
            }
            if (! empty($recruiter_id_data->additional_recruiter_id2)) {
                $recruiter_ids .= ','.$recruiter_id_data->additional_recruiter_id2;
            }

            $idsArr = explode(',', $recruiter_ids);
            $directs = User::whereIn('id', $idsArr)->where('id', '!=', '1')->where('dismiss', 0)->get();

            foreach ($directs as $value) {
                $stopPayroll = ($value->stop_payroll == 1) ? 1 : 0;

                $organizationHistory = UserOrganizationHistory::where('user_id', $value->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $positionId = $organizationHistory->sub_position_id;
                } else {
                    $positionId = $value->sub_position_id;
                }

                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
                if ($positionReconciliation) {
                    $settlementType = 'reconciliation';
                } else {
                    $settlementType = 'during_m2';
                }

                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'status' => '1', 'override_id' => '1'])->first();
                $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $value->id, 'type' => 'Direct', 'status' => 1])->first();
                if ($positionOverride && ! $overrideStatus) {
                    $value->direct_overrides_amount = 0;
                    $value->direct_overrides_type = '';

                    $overrideHistory = UserOverrideHistory::where('user_id', $value->id)->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->first();
                    if ($overrideHistory) {
                        $value->direct_overrides_amount = $overrideHistory->direct_overrides_amount;
                        $value->direct_overrides_type = $overrideHistory->direct_overrides_type;
                    }

                    if ($value->direct_overrides_amount) {
                        if ($value->direct_overrides_type == 'per kw') {
                            $amount = $value->direct_overrides_amount * $kw;
                        } elseif ($value->direct_overrides_type == 'percent') {
                            $commissionHistory = UserCommissionHistory::where(['user_id' => $value->id, 'self_gen_user' => '0'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                            if ($commissionHistory && $commissionHistory->commission_type == 'per kw') {
                                $commission_percentage = $commissionHistory->commission;
                                $amount = ($kw * $commission_percentage * $x * ($value->direct_overrides_amount / 100));
                            } else {
                                $amount = ((($netEpc - $redline) * $x) * $kw * 1000 * ($value->direct_overrides_amount / 100));
                            }
                        } else {
                            $amount = $value->direct_overrides_amount;
                        }

                        $where = [
                            'user_id' => $value->id,
                            'type' => 'Direct',
                            'pid' => $pid,
                            'sale_user_id' => $sale_user_id,
                        ];

                        $update = [
                            'customer_name' => $saleMaster->customer_name,
                            'kw' => $kw,
                            'total_override' => $amount,
                            'overrides_amount' => $value->direct_overrides_amount,
                            'overrides_type' => $value->direct_overrides_type,
                            'overrides_settlement_type' => $settlementType,
                            'status' => 1,
                            'is_stop_payroll' => $stopPayroll,
                        ];

                        $overrideSystemSetting = overrideSystemSetting::where('pay_type', 2)->first();
                        if ($overrideSystemSetting) {
                            $userOverrides = ProjectionUserOverrides::where(['user_id' => $value->id, 'pid' => $pid])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->orderByDesc('total_override')->first();
                            if ($userOverrides) {
                                if ($amount > $userOverrides->total_override) {
                                    ProjectionUserOverrides::where(['id' => $userOverrides->id, 'status' => '1'])->delete();
                                    if ($value->direct_overrides_type) {
                                        ProjectionUserOverrides::updateOrCreate($where, $update);
                                    }
                                }
                            } else {
                                ProjectionUserOverrides::where(['user_id' => $value->id, 'pid' => $pid, 'type' => 'Direct', 'status' => 1])->delete();
                                if ($value->direct_overrides_type) {
                                    ProjectionUserOverrides::updateOrCreate($where, $update);
                                }
                            }
                        } else {
                            if ($value->direct_overrides_type) {
                                ProjectionUserOverrides::updateOrCreate($where, $update);
                            }
                        }
                    }
                }

                // INDIRECT
                if ($value->recruiter_id) {
                    $recruiter_ids = $value->recruiter_id;
                    if (! empty($value->additional_recruiter_id1)) {
                        $recruiter_ids .= ','.$value->additional_recruiter_id1;
                    }
                    if (! empty($value->additional_recruiter_id2)) {
                        $recruiter_ids .= ','.$value->additional_recruiter_id2;
                    }
                    $idsArr = explode(',', $recruiter_ids);

                    $additional = User::whereIn('id', $idsArr)->where('id', '!=', '1')->where('dismiss', 0)->get();
                    foreach ($additional as $val) {
                        $stopPayroll = ($val->stop_payroll == 1) ? 1 : 0;

                        $organizationHistory = UserOrganizationHistory::where('user_id', $val->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                        if ($organizationHistory) {
                            $positionId = $organizationHistory->sub_position_id;
                        } else {
                            $positionId = $val->sub_position_id;
                        }

                        $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
                        if ($positionReconciliation) {
                            $settlementType = 'reconciliation';
                        } else {
                            $settlementType = 'during_m2';
                        }

                        $positionOverride = PositionOverride::where(['position_id' => $positionId, 'status' => '1', 'override_id' => '1'])->first();
                        $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $val->id, 'type' => 'Direct', 'status' => 1])->first();
                        if ($positionOverride && ! $overrideStatus) {
                            $val->indirect_overrides_amount = 0;
                            $val->indirect_overrides_type = '';

                            $overrideHistory = UserOverrideHistory::where('user_id', $val->id)->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->first();
                            if ($overrideHistory) {
                                $val->indirect_overrides_amount = $overrideHistory->indirect_overrides_amount;
                                $val->indirect_overrides_type = $overrideHistory->indirect_overrides_type;
                            }

                            if ($val->indirect_overrides_amount) {
                                if ($val->indirect_overrides_type == 'per kw') {
                                    $amount = $val->indirect_overrides_amount * $kw;
                                } elseif ($val->indirect_overrides_type == 'percent') {
                                    $commissionHistory = UserCommissionHistory::where(['user_id' => $val->id, 'self_gen_user' => '0'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                                    if ($commissionHistory && $commissionHistory->commission_type == 'per kw') {
                                        $commission_percentage = $commissionHistory->commission;
                                        $amount = ($kw * $commission_percentage * $x * ($val->indirect_overrides_amount / 100));
                                    } else {
                                        $amount = ((($netEpc - $redline) * $x) * $kw * 1000 * ($val->indirect_overrides_amount / 100));
                                    }
                                } else {
                                    $amount = $val->indirect_overrides_amount;
                                }

                                $where = [
                                    'user_id' => $val->id,
                                    'type' => 'Indirect',
                                    'pid' => $pid,
                                    'sale_user_id' => $sale_user_id,
                                ];

                                $update = [
                                    'customer_name' => $saleMaster->customer_name,
                                    'kw' => $kw,
                                    'total_override' => $amount,
                                    'overrides_amount' => $val->indirect_overrides_amount,
                                    'overrides_type' => $val->indirect_overrides_type,
                                    'overrides_settlement_type' => $settlementType,
                                    'status' => 1,
                                    'is_stop_payroll' => $stopPayroll,
                                ];

                                $overrideSystemSetting = overrideSystemSetting::where('pay_type', 2)->first();
                                if ($overrideSystemSetting) {
                                    $userOverrides = ProjectionUserOverrides::where(['user_id' => $val->id, 'pid' => $pid])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->orderByDesc('total_override')->first();
                                    if ($userOverrides) {
                                        if ($amount > $userOverrides->total_override) {
                                            ProjectionUserOverrides::where('id', $userOverrides->id)->where('status', 1)->delete();
                                            if ($val->indirect_overrides_type) {
                                                ProjectionUserOverrides::updateOrCreate($where, $update);
                                            }
                                        }
                                    } else {
                                        ProjectionUserOverrides::where(['user_id' => $val->id, 'pid' => $pid, 'type' => 'Indirect', 'status' => 1])->delete();
                                        if ($val->indirect_overrides_type) {
                                            ProjectionUserOverrides::updateOrCreate($where, $update);
                                        }
                                    }
                                } else {
                                    if ($val->indirect_overrides_type) {
                                        ProjectionUserOverrides::updateOrCreate($where, $update);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        // END DIRECT & INDIRECT OVERRIDES CODE

        // MANUAL OVERRIDES CODE
        if ($sale_user_id) {
            if (overrideSystemSetting::where('allow_manual_override_status', 1)->first()) {
                $manualOverrides = ManualOverrides::where('manual_user_id', $sale_user_id)->whereHas('manualUser', function ($q) {
                    $q->where('id', '!=', '1')->where('dismiss', '0');
                })->pluck('user_id');
                $users = User::whereIn('id', $manualOverrides)->get();

                foreach ($users as $value) {
                    $stopPayroll = ($value->stop_payroll == 1) ? 1 : 0;

                    $organizationHistory = UserOrganizationHistory::where('user_id', $value->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($organizationHistory) {
                        $positionId = $organizationHistory->sub_position_id;
                    } else {
                        $positionId = $value->sub_position_id;
                    }

                    if (PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first()) {
                        $settlementType = 'reconciliation';
                    } else {
                        $settlementType = 'during_m2';
                    }

                    $value->overrides_amount = 0;
                    $value->overrides_type = '';
                    $overrideHistory = ManualOverridesHistory::where(['user_id' => $value->id, 'manual_user_id' => $sale_user_id])
                        ->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($overrideHistory) {
                        $value->overrides_amount = $overrideHistory->overrides_amount;
                        $value->overrides_type = $overrideHistory->overrides_type;
                    }

                    if ($value->overrides_amount) {
                        if ($value->overrides_type == 'per kw') {
                            $amount = $value->overrides_amount * $kw;
                        } elseif ($value->overrides_type == 'percent') {
                            $commissionHistory = UserCommissionHistory::where(['user_id' => $value->id, 'self_gen_user' => '0'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                            if ($commissionHistory && $commissionHistory->commission_type == 'per kw') {
                                $commission_percentage = $commissionHistory->commission;
                                $amount = ($kw * $commission_percentage * $x * ($value->overrides_amount / 100));
                            } else {
                                $amount = ((($netEpc - $redline) * $x) * $kw * 1000 * ($value->overrides_amount / 100));
                            }
                        } else {
                            $amount = $value->overrides_amount;
                        }

                        $manOverride = UserOverrides::where(['user_id' => $value->id, 'pid' => $pid, 'type' => 'Manual'])->first();
                        $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $value->id, 'type' => 'Manual', 'status' => 1])->first();
                        if (! $manOverride && ! $overrideStatus) {
                            $where = [
                                'user_id' => $value->id,
                                'type' => 'Manual',
                                'pid' => $pid,
                                'sale_user_id' => $sale_user_id,
                            ];

                            $update = [
                                'customer_name' => $saleMaster->customer_name,
                                'kw' => $kw,
                                'total_override' => $amount,
                                'overrides_amount' => $value->overrides_amount,
                                'overrides_type' => $value->overrides_type,
                                'overrides_settlement_type' => $settlementType,
                                'status' => 1,
                                'is_stop_payroll' => $stopPayroll,
                            ];

                            $overrideSystemSetting = overrideSystemSetting::where('pay_type', 2)->first();
                            if ($overrideSystemSetting) {
                                $userOverrides = ProjectionUserOverrides::where(['user_id' => $value->id, 'pid' => $pid])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->orderByDesc('total_override')->first();
                                if ($userOverrides) {
                                    if ($amount > $userOverrides->total_override) {
                                        ProjectionUserOverrides::where('id', $userOverrides->id)->where('status', 1)->delete();
                                        if ($value->overrides_type) {
                                            ProjectionUserOverrides::updateOrCreate($where, $update);
                                        }
                                    }
                                } else {
                                    ProjectionUserOverrides::where(['user_id' => $value->id, 'pid' => $pid, 'type' => 'Manual', 'status' => 1])->delete();
                                    if ($value->overrides_type) {
                                        ProjectionUserOverrides::updateOrCreate($where, $update);
                                    }
                                }
                            } else {
                                if ($value->overrides_type) {
                                    ProjectionUserOverrides::updateOrCreate($where, $update);
                                }
                            }
                        }
                    }
                }
            }
        }
        // END MANUAL OVERRIDES CODE
    }

    public function StackUserOverride($sale_user_id, $pid, $kw, $date)
    {
        $stackSystemSetting = overrideSystemSetting::where('allow_office_stack_override_status', 1)->first();
        $user_data = User::where('id', $sale_user_id)->first();

        if ($user_data && $user_data->office_id && $stackSystemSetting) {
            $office_id = $user_data->office_id;
            $saleData = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
            $sale = SaleMasterProjections::where('pid', $pid)->first();
            $closer1_id = $sale->closer1_id;
            $setter1_id = $sale->setter1_id;
            if (config('app.domain_name') == 'flex') {
                $saleState = $saleData->customer_state;
            } else {
                $saleState = $saleData->location_code;
            }
            $saleUsers = [$closer1_id, $setter1_id];

            $approvedDate = $saleData->customer_signoff;
            $netEpc = $saleData->net_epc;
            $finalCommission = $sale->closer1_commission + $sale->closer2_commission + $sale->setter1_commission + $sale->setter2_commission;

            $finalOverride = ProjectionUserOverrides::where(['pid' => $pid])->where('type', '!=', 'Stack')->sum('total_override');
            $userTransferHistory = UserTransferHistory::where('user_id', $sale_user_id)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $office_id = $userTransferHistory->office_id;
            }

            // Subquery to get the row number for each user_id partitioned and ordered by override_effective_date and id
            $subQuery = UserOverrideHistory::select(
                'id',
                'user_id',
                'override_effective_date',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY override_effective_date DESC, id DESC) as rn')
            )->where('override_effective_date', '<=', $approvedDate);

            // Main query to get the IDs where rn = 1
            $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                ->mergeBindings($subQuery->getQuery())
                ->select('id')
                ->where('rn', 1);

            // Final query to get the user_override_history records with the selected IDs
            $userIdArr = UserOverrideHistory::whereIn('id', $results->pluck('id'))->whereHas('user', function ($q) use ($office_id, $closer1_id, $setter1_id, $sale_user_id) {
                $q->where(['office_id' => $office_id, 'dismiss' => '0'])->when(($closer1_id == $setter1_id), function ($q) use ($sale_user_id) {
                    $q->where('id', '!=', $sale_user_id);
                });
            })->whereNotNull('office_stack_overrides_amount')->pluck('user_id')->toArray();

            $userIds = AdditionalLocations::where('office_id', $office_id)->where('user_id', '!=', $sale_user_id)->pluck('user_id')->toArray();
            // Subquery to get the row number for each user_id partitioned and ordered by override_effective_date and id
            $subQuery = UserOverrideHistory::select(
                'id',
                'user_id',
                'override_effective_date',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY override_effective_date DESC, id DESC) as rn')
            )->where('override_effective_date', '<=', $approvedDate);

            // Main query to get the IDs where rn = 1
            $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                ->mergeBindings($subQuery->getQuery())
                ->select('id')
                ->where('rn', 1);

            // Final query to get the user_override_history records with the selected IDs
            $additionalUserIdArr = UserOverrideHistory::whereHas('user', function ($q) {
                $q->where('dismiss', '0');
            })->whereIn('id', $results->pluck('id'))->whereIn('user_id', $userIds)->whereNotNull('office_stack_overrides_amount')->pluck('user_id')->toArray();
            $userIdArr = array_unique(array_merge($userIdArr, $additionalUserIdArr));

            $redArray = [];
            foreach ($userIdArr as $userId) {
                $userData = User::where(['id' => $userId])->first();
                $redline = $this->userRedline($userData, $saleState, $approvedDate);
                $redArray[$userId] = $redline;
            }
            arsort($redArray);

            $closerRedline = $this->userRedline($user_data, $saleState, $approvedDate);
            $previousValue = 0;
            $lowerStackPay = 0;
            $userIds = [];
            $i = 0;

            foreach ($redArray as $key => $value) {
                if ($value <= $closerRedline) {
                    $overrideStatus = OverrideStatus::where(['user_id' => $sale_user_id, 'recruiter_id' => $key, 'type' => 'Stack', 'status' => 1])->first();
                    if (! $overrideStatus) {
                        $userData = User::where(['id' => $key])->first();
                        $organizationHistory = UserOrganizationHistory::where('user_id', $key)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                        if ($organizationHistory) {
                            $positionId = $organizationHistory->sub_position_id;
                        } else {
                            $positionId = $userData->sub_position_id;
                        }

                        $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => 1, 'stack_settlement' => 'Reconciliation'])->first();
                        if ($positionReconciliation) {
                            $settlementType = 'reconciliation';
                        } else {
                            $settlementType = 'during_m2';
                        }

                        $positionOverride = PositionOverride::where(['position_id' => $positionId, 'override_id' => '4', 'status' => '1'])->first();
                        if ($positionOverride) {
                            $overrideHistory = UserOverrideHistory::where('user_id', $userData->id)->where('override_effective_date', '<=', $approvedDate)->whereNotNull('office_stack_overrides_amount')->orderBy('override_effective_date', 'DESC')->first();
                            if ($overrideHistory) {
                                $userData->office_stack_overrides_amount = $overrideHistory->office_stack_overrides_amount;
                            }

                            if ($userData->office_stack_overrides_amount) {
                                $stackShare = $userData->office_stack_overrides_amount;
                                $redline = $value;

                                if ($i == 0) {
                                    $lowerStackPay = 0;
                                } else {
                                    if ($previousValue == $value) {
                                        $lowerStackPay = $lowerStackPay;
                                    } else {
                                        $lowerStackPay = ProjectionUserOverrides::where(['type' => 'Stack', 'pid' => $pid])->whereIn('user_id', $userIds)->whereIn('sale_user_id', $saleUsers)->sum('total_override');
                                    }
                                }

                                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                                    $margin_percentage = $companyMargin->company_margin;
                                    $x = ((100 - $margin_percentage) / 100);
                                    $amount = ((($netEpc - $redline) * $x) * $kw * 1000 - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                } else {
                                    $amount = (($netEpc - $redline) * $kw * 1000 - $finalCommission - $finalOverride - $lowerStackPay) * ($stackShare / 100);
                                }

                                $where = [
                                    'user_id' => $userData->id,
                                    'type' => 'Stack',
                                    'pid' => $pid,
                                    'sale_user_id' => $sale_user_id,
                                ];

                                $update = [
                                    'customer_name' => $saleData->customer_name,
                                    'kw' => $kw,
                                    'total_override' => $amount,
                                    'overrides_amount' => $userData->office_stack_overrides_amount,
                                    'overrides_type' => 'per sale',
                                    'overrides_settlement_type' => $settlementType,
                                    'status' => 1,
                                    'is_stop_payroll' => 0,
                                ];
                                ProjectionUserOverrides::updateOrCreate($where, $update);

                                $i++;
                                $userIds[] = $userData->id;
                                $previousValue = $value;
                            }
                        }
                    }
                }
            }
        }
    }

    public function pestCalculateoverride($checked)
    {
        ProjectionUserOverrides::where('pid', $checked->salesMasterProcess->pid)->delete();

        $pid = $checked->pid;
        $closer1 = $checked->salesMasterProcess->closer1_id;
        $closer2 = $checked->salesMasterProcess->closer2_id;
        $grossAmountValue = $checked->gross_account_value;
        $approvedDate = $checked->customer_signoff;
        $companyMargin = CompanyProfile::where('id', 1)->first();
        $overrideSetting = CompanySetting::where('type', 'overrides')->first();

        $closerCommission = 0;
        if ($closer1 && $closer2) {
            $commissionPercentage = 0;
            $commissionHistory = UserCommissionHistory::where('user_id', $closer1)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commissionHistory) {
                $commissionPercentage = $commissionHistory->commission;
            }

            $commissionPercentage2 = 0;
            $commission2History = UserCommissionHistory::where('user_id', $closer2)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commission2History) {
                $commissionPercentage2 = $commission2History->commission;
            }

            $closer1Commission = 0;
            $closer2Commission = 0;
            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $marginPercentage = $companyMargin->company_margin;
                $x = ((100 - $marginPercentage) / 100);
                if ($commissionPercentage && $commissionPercentage2) {
                    $closer1Commission = ((($grossAmountValue * $commissionPercentage * $x) / 100) / 2);
                    $closer2Commission = ((($grossAmountValue * $commissionPercentage2 * $x) / 100) / 2);
                } elseif ($commissionPercentage) {
                    $closer1Commission = (($grossAmountValue * $commissionPercentage * $x) / 100);
                } elseif ($commissionPercentage2) {
                    $closer2Commission = (($grossAmountValue * $commissionPercentage2 * $x) / 100);
                }
            } else {
                if ($commissionPercentage && $commissionPercentage2) {
                    $closer1Commission = ((($grossAmountValue * $commissionPercentage) / 100) / 2);
                    $closer2Commission = ((($grossAmountValue * $commissionPercentage2) / 100) / 2);
                } elseif ($commissionPercentage) {
                    $closer1Commission = (($grossAmountValue * $commissionPercentage) / 100);
                } elseif ($commissionPercentage2) {
                    $closer2Commission = (($grossAmountValue * $commissionPercentage2) / 100);
                }
            }

            $where1 = [
                'pid' => $checked->pid,
            ];

            $update1 = [
                'closer1_id' => $closer1,
                'closer2_id' => $closer2,
                'closer1_commission' => $closer1Commission,
                'closer2_commission' => $closer2Commission,
            ];
            SaleMasterProjections::updateOrCreate($where1, $update1);

            if ($overrideSetting->status == '1') {
                $this->pestUserOverride($closer1, $pid);
                $this->pestUserOverride($closer2, $pid);
            }
        } elseif ($closer1) {
            $commissionPercentage = 0;
            $commissionHistory = UserCommissionHistory::where('user_id', $closer1)->where('self_gen_user', 0)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commissionHistory) {
                $commissionPercentage = $commissionHistory->commission;
            }

            if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                $marginPercentage = $companyMargin->company_margin;
                $x = ((100 - $marginPercentage) / 100);
                $closerCommission = (($grossAmountValue * $commissionPercentage * $x) / 100);
            } else {
                $closerCommission = (($grossAmountValue * $commissionPercentage) / 100);
            }

            $where1 = [
                'pid' => $checked->pid,
            ];

            $update1 = [
                'closer1_id' => $closer1,
                'closer1_commission' => $closerCommission,
            ];
            SaleMasterProjections::updateOrCreate($where1, $update1);

            if ($overrideSetting->status == '1') {
                $this->pestUserOverride($closer1, $pid);
            }
        }

        if (! empty($closer1)) {
            $this->pestStackUserOverride($closer1, $pid);
        }

        return true;
    }

    // GENERATE USER OVERRIDES PEST
    public function pestUserOverride($saleUserId, $pid)
    {
        UserOverrides::where(['sale_user_id' => $saleUserId, 'pid' => $pid, 'status' => 1, 'is_displayed' => '1'])->delete();
        $saleMaster = SalesMaster::where('pid', $pid)->first();
        $approvedDate = isset($saleMaster->customer_signoff) ? $saleMaster->customer_signoff : null;
        $recruiter_id_data = User::where('id', $saleUserId)->where('dismiss', 0)->first();
        $companyMargin = CompanyProfile::where('id', 1)->first();
        if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
            $margin_percentage = $companyMargin->company_margin;
            $x = ((100 - $margin_percentage) / 100);
        } else {
            $x = 1;
        }
        $kw = $saleMaster->gross_account_value;

        // OFFICE OVERRIDES CODE
        if ($recruiter_id_data && $recruiter_id_data->office_id) {
            $office_id = $recruiter_id_data->office_id;
            $userTransferHistory = UserTransferHistory::where('user_id', $saleUserId)->where('transfer_effective_date', '<=', $approvedDate)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $office_id = $userTransferHistory->office_id;
            }
            $userIdArr1 = User::select('id', 'stop_payroll', 'sub_position_id', 'dismiss', 'office_overrides_amount', 'office_overrides_type')
                ->where(['office_id' => $office_id, 'dismiss' => '0'])->whereNotIn('id', ['1'])->get();

            foreach ($userIdArr1 as $userData) {
                $stopPayroll = ($userData->stop_payroll == 1) ? 1 : 0;

                $organizationHistory = UserOrganizationHistory::where('user_id', $userData->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $positionId = $organizationHistory->sub_position_id;
                } else {
                    $positionId = $userData->sub_position_id;
                }

                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'override_settlement' => 'Reconciliation', 'status' => '1'])->first();
                if ($positionReconciliation) {
                    $settlementType = 'reconciliation';
                } else {
                    $settlementType = 'during_m2';
                }

                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'override_id' => '3', 'status' => '1'])->first();
                if ($positionOverride) {
                    $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $userData->id, 'type' => 'Office', 'status' => 1])->first();
                    if (! $overrideStatus && $userData) {
                        $userData->office_overrides_amount = 0;
                        $userData->office_overrides_type = '';

                        $overrideHistory = UserOverrideHistory::where('user_id', $userData->id)->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->first();
                        if ($overrideHistory) {
                            $userData->office_overrides_amount = $overrideHistory->office_overrides_amount;
                            $userData->office_overrides_type = $overrideHistory->office_overrides_type;
                        }

                        if ($userData->office_overrides_amount) {
                            if ($userData->office_overrides_type == 'percent') {
                                $amount = (($kw * $userData->office_overrides_amount * $x) / 100);
                            } else {
                                $amount = $userData->office_overrides_amount;
                            }

                            $where = [
                                'user_id' => $userData->id,
                                'type' => 'Office',
                                'pid' => $pid,
                                'sale_user_id' => $saleUserId,
                            ];

                            $update = [
                                'customer_name' => $saleMaster->customer_name,
                                'kw' => $kw,
                                'total_override' => $amount,
                                'overrides_amount' => $userData->office_overrides_amount,
                                'overrides_type' => $userData->office_overrides_type,
                                'overrides_settlement_type' => $settlementType,
                                'status' => 1,
                                'is_stop_payroll' => $stopPayroll,
                                'office_id' => $office_id,
                            ];

                            $officeOverrides = ProjectionUserOverrides::where(['user_id' => $userData->id, 'type' => 'Office', 'pid' => $pid])->first();
                            if ($officeOverrides) {
                                if ($amount > $officeOverrides->total_override) {
                                    ProjectionUserOverrides::where('id', $officeOverrides->id)->where('status', 1)->delete();
                                    if ($userData->office_overrides_type) {
                                        ProjectionUserOverrides::updateOrCreate($where, $update);
                                    }
                                }
                            } else {
                                ProjectionUserOverrides::where(['user_id' => $userData->id, 'pid' => $pid, 'type' => 'Office', 'status' => 1])->delete();
                                if ($userData->office_overrides_type) {
                                    ProjectionUserOverrides::updateOrCreate($where, $update);
                                }
                            }
                        }
                    }
                }
            }

            $userIdArr2 = AdditionalLocations::whereHas('user')->with('user:id,stop_payroll,sub_position_id,dismiss,office_overrides_amount,office_overrides_type')
                ->where(['office_id' => $office_id])->whereNotIn('user_id', ['1', $saleUserId])->get();
            foreach ($userIdArr2 as $userData) {
                $userData = $userData->user;
                $stopPayroll = ($userData->stop_payroll == 1) ? 1 : 0;
                $organizationHistory = UserOrganizationHistory::where('user_id', $userData->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $positionId = $organizationHistory->sub_position_id;
                } else {
                    $positionId = $userData->sub_position_id;
                }

                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
                if ($positionReconciliation) {
                    $settlementType = 'reconciliation';
                } else {
                    $settlementType = 'during_m2';
                }

                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'override_id' => '3', 'status' => '1'])->first();
                if ($positionOverride) {
                    $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $userData->id, 'type' => 'Office', 'status' => '1'])->first();
                    if (! $overrideStatus && $userData->dismiss == '0') {
                        $userData->office_overrides_amount = 0;
                        $userData->office_overrides_type = '';

                        $overrideHistory = UserAdditionalOfficeOverrideHistory::where(['user_id' => $userData->id, 'office_id' => $office_id])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->first();
                        if ($overrideHistory) {
                            $userData->office_overrides_amount = $overrideHistory->office_overrides_amount;
                            $userData->office_overrides_type = $overrideHistory->office_overrides_type;
                        }

                        if ($userData->office_overrides_amount) {
                            if ($userData->office_overrides_type == 'percent') {
                                $amount = (($kw * $userData->office_overrides_amount * $x) / 100);
                            } else {
                                $amount = $userData->office_overrides_amount;
                            }

                            $where = [
                                'user_id' => $userData->id,
                                'type' => 'Office',
                                'pid' => $pid,
                                'sale_user_id' => $saleUserId,
                            ];

                            $update = [
                                'customer_name' => $saleMaster->customer_name,
                                'kw' => $kw,
                                'total_override' => $amount,
                                'overrides_amount' => $userData->office_overrides_amount,
                                'overrides_type' => $userData->office_overrides_type,
                                'overrides_settlement_type' => $settlementType,
                                'status' => 1,
                                'is_stop_payroll' => $stopPayroll,
                                'office_id' => $office_id,
                            ];

                            $officeOverrides = ProjectionUserOverrides::where(['user_id' => $userData->id, 'type' => 'Office', 'pid' => $pid])->first();
                            if ($officeOverrides) {
                                if ($amount > $officeOverrides->total_override) {
                                    ProjectionUserOverrides::where('id', $officeOverrides->id)->where('status', 1)->delete();
                                    if ($userData->office_overrides_type) {
                                        ProjectionUserOverrides::updateOrCreate($where, $update);
                                    }
                                }
                            } else {
                                ProjectionUserOverrides::where(['user_id' => $userData->id, 'pid' => $pid, 'type' => 'Office', 'status' => 1])->delete();
                                if ($userData->office_overrides_type) {
                                    ProjectionUserOverrides::updateOrCreate($where, $update);
                                }
                            }
                        }
                    }
                }
            }
        }
        // END OFFICE OVERRIDES CODE

        // DIRECT & INDIRECT OVERRIDES CODE
        if ($recruiter_id_data && $recruiter_id_data->recruiter_id) {
            $recruiter_ids = $recruiter_id_data->recruiter_id;
            if (! empty($recruiter_id_data->additional_recruiter_id1)) {
                $recruiter_ids .= ','.$recruiter_id_data->additional_recruiter_id1;
            }
            if (! empty($recruiter_id_data->additional_recruiter_id2)) {
                $recruiter_ids .= ','.$recruiter_id_data->additional_recruiter_id2;
            }

            $idsArr = explode(',', $recruiter_ids);
            $directs = User::whereIn('id', $idsArr)->where('id', '!=', '1')->where('dismiss', 0)->get();

            foreach ($directs as $value) {
                $stopPayroll = ($value->stop_payroll == 1) ? 1 : 0;

                $organizationHistory = UserOrganizationHistory::where('user_id', $value->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                if ($organizationHistory) {
                    $positionId = $organizationHistory->sub_position_id;
                } else {
                    $positionId = $value->sub_position_id;
                }

                $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
                if ($positionReconciliation) {
                    $settlementType = 'reconciliation';
                } else {
                    $settlementType = 'during_m2';
                }

                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'status' => '1', 'override_id' => '1'])->first();
                $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $value->id, 'type' => 'Direct', 'status' => 1])->first();
                if ($positionOverride && ! $overrideStatus) {
                    $value->direct_overrides_amount = 0;
                    $value->direct_overrides_type = '';

                    $overrideHistory = UserOverrideHistory::where('user_id', $value->id)->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->first();
                    if ($overrideHistory) {
                        $value->direct_overrides_amount = $overrideHistory->direct_overrides_amount;
                        $value->direct_overrides_type = $overrideHistory->direct_overrides_type;
                    }

                    if ($value->direct_overrides_amount) {
                        if ($value->direct_overrides_type == 'percent') {
                            $amount = (($kw * $value->direct_overrides_amount * $x) / 100);
                        } else {
                            $amount = $value->direct_overrides_amount;
                        }

                        $where = [
                            'user_id' => $value->id,
                            'type' => 'Direct',
                            'pid' => $pid,
                            'sale_user_id' => $saleUserId,
                        ];

                        $update = [
                            'customer_name' => $saleMaster->customer_name,
                            'kw' => $kw,
                            'total_override' => $amount,
                            'overrides_amount' => $value->direct_overrides_amount,
                            'overrides_type' => $value->direct_overrides_type,
                            'overrides_settlement_type' => $settlementType,
                            'status' => 1,
                            'is_stop_payroll' => $stopPayroll,
                        ];

                        $overrideSystemSetting = overrideSystemSetting::where('pay_type', 2)->first();
                        if ($overrideSystemSetting) {
                            $userOverrides = ProjectionUserOverrides::where(['user_id' => $value->id, 'pid' => $pid])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->orderByDesc('total_override')->first();
                            if ($userOverrides) {
                                if ($amount > $userOverrides->total_override) {
                                    ProjectionUserOverrides::where(['id' => $userOverrides->id, 'status' => '1'])->delete();
                                    if ($value->direct_overrides_type) {
                                        ProjectionUserOverrides::updateOrCreate($where, $update);
                                    }
                                }
                            } else {
                                ProjectionUserOverrides::where(['user_id' => $value->id, 'pid' => $pid, 'type' => 'Direct', 'status' => 1])->delete();
                                if ($value->direct_overrides_type) {
                                    ProjectionUserOverrides::updateOrCreate($where, $update);
                                }
                            }
                        } else {
                            if ($value->direct_overrides_type) {
                                ProjectionUserOverrides::updateOrCreate($where, $update);
                            }
                        }
                    }
                }

                // INDIRECT
                if ($value->recruiter_id) {
                    $recruiter_ids = $value->recruiter_id;
                    if (! empty($value->additional_recruiter_id1)) {
                        $recruiter_ids .= ','.$value->additional_recruiter_id1;
                    }
                    if (! empty($value->additional_recruiter_id2)) {
                        $recruiter_ids .= ','.$value->additional_recruiter_id2;
                    }
                    $idsArr = explode(',', $recruiter_ids);

                    $additional = User::whereIn('id', $idsArr)->where('id', '!=', '1')->where('dismiss', 0)->get();
                    foreach ($additional as $val) {
                        $stopPayroll = ($val->stop_payroll == 1) ? 1 : 0;

                        $organizationHistory = UserOrganizationHistory::where('user_id', $val->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                        if ($organizationHistory) {
                            $positionId = $organizationHistory->sub_position_id;
                        } else {
                            $positionId = $val->sub_position_id;
                        }

                        $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first();
                        if ($positionReconciliation) {
                            $settlementType = 'reconciliation';
                        } else {
                            $settlementType = 'during_m2';
                        }

                        $positionOverride = PositionOverride::where(['position_id' => $positionId, 'status' => '1', 'override_id' => '1'])->first();
                        $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $val->id, 'type' => 'Direct', 'status' => 1])->first();
                        if ($positionOverride && ! $overrideStatus) {
                            $val->indirect_overrides_amount = 0;
                            $val->indirect_overrides_type = '';

                            $overrideHistory = UserOverrideHistory::where('user_id', $val->id)->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->first();
                            if ($overrideHistory) {
                                $val->indirect_overrides_amount = $overrideHistory->indirect_overrides_amount;
                                $val->indirect_overrides_type = $overrideHistory->indirect_overrides_type;
                            }

                            if ($val->indirect_overrides_amount) {
                                if ($val->indirect_overrides_type == 'percent') {
                                    $amount = (($kw * $val->indirect_overrides_amount * $x) / 100);
                                } else {
                                    $amount = $val->indirect_overrides_amount;
                                }

                                $where = [
                                    'user_id' => $val->id,
                                    'type' => 'Indirect',
                                    'pid' => $pid,
                                    'sale_user_id' => $saleUserId,
                                ];

                                $update = [
                                    'customer_name' => $saleMaster->customer_name,
                                    'kw' => $kw,
                                    'total_override' => $amount,
                                    'overrides_amount' => $val->indirect_overrides_amount,
                                    'overrides_type' => $val->indirect_overrides_type,
                                    'overrides_settlement_type' => $settlementType,
                                    'status' => 1,
                                    'is_stop_payroll' => $stopPayroll,
                                ];

                                $overrideSystemSetting = overrideSystemSetting::where('pay_type', 2)->first();
                                if ($overrideSystemSetting) {
                                    $userOverrides = ProjectionUserOverrides::where(['user_id' => $val->id, 'pid' => $pid])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->orderByDesc('total_override')->first();
                                    if ($userOverrides) {
                                        if ($amount > $userOverrides->total_override) {
                                            ProjectionUserOverrides::where('id', $userOverrides->id)->where('status', 1)->delete();
                                            if ($val->indirect_overrides_type) {
                                                ProjectionUserOverrides::updateOrCreate($where, $update);
                                            }
                                        }
                                    } else {
                                        ProjectionUserOverrides::where(['user_id' => $val->id, 'pid' => $pid, 'type' => 'Indirect', 'status' => 1])->delete();
                                        if ($val->indirect_overrides_type) {
                                            ProjectionUserOverrides::updateOrCreate($where, $update);
                                        }
                                    }
                                } else {
                                    if ($val->indirect_overrides_type) {
                                        ProjectionUserOverrides::updateOrCreate($where, $update);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        // END DIRECT & INDIRECT OVERRIDES CODE

        // MANUAL OVERRIDES CODE
        if ($saleUserId) {
            if (overrideSystemSetting::where('allow_manual_override_status', 1)->first()) {
                $manualOverrides = ManualOverrides::where('manual_user_id', $saleUserId)->whereHas('manualUser', function ($q) {
                    $q->where('id', '!=', '1')->where('dismiss', '0');
                })->pluck('user_id');
                $users = User::whereIn('id', $manualOverrides)->get();

                foreach ($users as $value) {
                    $stopPayroll = ($value->stop_payroll == 1) ? 1 : 0;

                    $organizationHistory = UserOrganizationHistory::where('user_id', $value->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($organizationHistory) {
                        $positionId = $organizationHistory->sub_position_id;
                    } else {
                        $positionId = $value->sub_position_id;
                    }

                    if (PositionReconciliations::where(['position_id' => $positionId, 'status' => '1', 'override_settlement' => 'Reconciliation'])->first()) {
                        $settlementType = 'reconciliation';
                    } else {
                        $settlementType = 'during_m2';
                    }

                    $value->overrides_amount = 0;
                    $value->overrides_type = '';
                    $overrideHistory = ManualOverridesHistory::where(['user_id' => $value->id, 'manual_user_id' => $saleUserId])
                        ->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($overrideHistory) {
                        $value->overrides_amount = $overrideHistory->overrides_amount;
                        $value->overrides_type = $overrideHistory->overrides_type;
                    }

                    if ($value->overrides_amount) {
                        if ($value->overrides_type == 'percent') {
                            $amount = (($kw * $value->overrides_amount * $x) / 100);
                        } else {
                            $amount = $value->overrides_amount;
                        }

                        $manOverride = UserOverrides::where(['user_id' => $value->id, 'pid' => $pid, 'type' => 'Manual'])->first();
                        $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $value->id, 'type' => 'Manual', 'status' => 1])->first();
                        if (! $manOverride && ! $overrideStatus) {
                            $where = [
                                'user_id' => $value->id,
                                'type' => 'Manual',
                                'pid' => $pid,
                                'sale_user_id' => $saleUserId,
                            ];

                            $update = [
                                'customer_name' => $saleMaster->customer_name,
                                'kw' => $kw,
                                'total_override' => $amount,
                                'overrides_amount' => $value->overrides_amount,
                                'overrides_type' => $value->overrides_type,
                                'overrides_settlement_type' => $settlementType,
                                'status' => 1,
                                'is_stop_payroll' => $stopPayroll,
                            ];

                            $overrideSystemSetting = overrideSystemSetting::where('pay_type', 2)->first();
                            if ($overrideSystemSetting) {
                                $userOverrides = ProjectionUserOverrides::where(['user_id' => $value->id, 'pid' => $pid])->whereIn('type', ['Direct', 'Indirect', 'Manual'])->orderByDesc('total_override')->first();
                                if ($userOverrides) {
                                    if ($amount > $userOverrides->total_override) {
                                        ProjectionUserOverrides::where('id', $userOverrides->id)->where('status', 1)->delete();
                                        if ($value->overrides_type) {
                                            ProjectionUserOverrides::updateOrCreate($where, $update);
                                        }
                                    }
                                } else {
                                    ProjectionUserOverrides::where(['user_id' => $value->id, 'pid' => $pid, 'type' => 'Manual', 'status' => 1])->delete();
                                    if ($value->overrides_type) {
                                        ProjectionUserOverrides::updateOrCreate($where, $update);
                                    }
                                }
                            } else {
                                if ($value->overrides_type) {
                                    ProjectionUserOverrides::updateOrCreate($where, $update);
                                }
                            }
                        }
                    }
                }
            }
        }
        // END MANUAL OVERRIDES CODE
    }

    // GENERATE STACK FOR USERS PEST
    public function pestStackUserOverride($saleUserId, $pid)
    {
        $stackSystemSetting = overrideSystemSetting::where('allow_office_stack_override_status', 1)->first();
        $user_data = User::where('id', $saleUserId)->first();

        if ($user_data && $user_data->office_id && $stackSystemSetting) {
            $office_id = $user_data->office_id;
            $saleData = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
            $sale = SaleMasterProjections::where('pid', $pid)->first();
            $saleUsers = [];
            if ($sale->closer1_id) {
                $saleUsers[] = $sale->closer1_id;
            }
            if ($sale->closer2_id) {
                $saleUsers[] = $saleData->salesMasterProcess->closer2_id;
            }
            $grossAmountValue = $saleData->gross_account_value;
            $approvedDate = $saleData->customer_signoff;
            $finalCommission = $sale->closer1_commission + $sale->closer2_commission;

            $finalOverride = ProjectionUserOverrides::where(['pid' => $pid])->where('type', '!=', 'Stack')->sum('total_override');
            $userTransferHistory = UserTransferHistory::where('user_id', $saleUserId)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $office_id = $userTransferHistory->office_id;
            }

            // Subquery to get the row number for each user_id partitioned and ordered by override_effective_date and id
            $subQuery = UserOverrideHistory::select(
                'id',
                'user_id',
                'override_effective_date',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY override_effective_date DESC, id DESC) as rn')
            )->where('override_effective_date', '<=', $approvedDate);

            // Main query to get the IDs where rn = 1
            $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                ->mergeBindings($subQuery->getQuery())
                ->select('id')
                ->where('rn', 1);

            // Final query to get the user_override_history records with the selected IDs
            $userIdArr = UserOverrideHistory::whereIn('id', $results->pluck('id'))->whereHas('user', function ($q) use ($office_id) {
                $q->where(['office_id' => $office_id, 'dismiss' => '0']);
            })->whereNotNull('office_stack_overrides_amount')->pluck('user_id')->toArray();

            $userIds = AdditionalLocations::where('office_id', $office_id)->where('user_id', '!=', $saleUserId)->pluck('user_id')->toArray();
            // Subquery to get the row number for each user_id partitioned and ordered by override_effective_date and id
            $subQuery = UserOverrideHistory::select(
                'id',
                'user_id',
                'override_effective_date',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY override_effective_date DESC, id DESC) as rn')
            )->where('override_effective_date', '<=', $approvedDate);

            // Main query to get the IDs where rn = 1
            $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                ->mergeBindings($subQuery->getQuery())
                ->select('id')
                ->where('rn', 1);

            // Final query to get the user_override_history records with the selected IDs
            $additionalUserIdArr = UserOverrideHistory::whereHas('user', function ($q) {
                $q->where('dismiss', '0');
            })->whereIn('id', $results->pluck('id'))->whereIn('user_id', $userIds)->whereNotNull('office_stack_overrides_amount')->pluck('user_id')->toArray();
            $userIdArr = array_unique(array_merge($userIdArr, $additionalUserIdArr));

            $commissionArray = [];
            foreach ($userIdArr as $userId) {
                $userdata = User::where(['id' => $userId])->first();
                $commission = $this->userStackCommission($userdata, $approvedDate);
                if ($commission) {
                    $commissionArray[$userId] = $commission;
                }
            }
            krsort($commissionArray);

            $closerCommission = 0;
            if ($user_data) {
                $closerCommission = $this->userStackCommission($user_data, $approvedDate);
            }

            $previousValue = 0;
            $lowerStackPay = 0;
            $userIds = [];
            $i = 0;
            foreach ($commissionArray as $key => $value) {
                if ($value >= $closerCommission) {
                    $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $key, 'type' => 'Stack', 'status' => 1])->first();
                    if (! $overrideStatus) {
                        $userData = User::where(['id' => $key])->first();
                        $organizationHistory = UserOrganizationHistory::where('user_id', $key)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                        if ($organizationHistory) {
                            $positionId = $organizationHistory->sub_position_id;
                        } else {
                            $positionId = $userData->sub_position_id;
                        }

                        $positionReconciliation = PositionReconciliations::where(['position_id' => $positionId, 'status' => 1, 'stack_settlement' => 'Reconciliation'])->first();
                        if ($positionReconciliation) {
                            $settlementType = 'reconciliation';
                        } else {
                            $settlementType = 'during_m2';
                        }

                        $positionOverride = PositionOverride::where(['position_id' => $positionId, 'override_id' => '4', 'status' => '1'])->first();
                        if ($positionOverride) {
                            $overrideHistory = UserOverrideHistory::where('user_id', $userData->id)->where('override_effective_date', '<=', $approvedDate)->whereNotNull('office_stack_overrides_amount')->orderBy('override_effective_date', 'DESC')->first();
                            if ($overrideHistory) {
                                $userData->office_stack_overrides_amount = $overrideHistory->office_stack_overrides_amount;
                            }

                            if ($userData->office_stack_overrides_amount) {
                                $stackShare = $userData->office_stack_overrides_amount;
                                $commission = $value;

                                if ($i == 0) {
                                    $lowerStackPay = 0;
                                } else {
                                    if ($previousValue == $value) {
                                        $lowerStackPay = $lowerStackPay;
                                    } else {
                                        $lowerStackPay = ProjectionUserOverrides::where(['type' => 'Stack', 'pid' => $pid])->whereIn('user_id', $userIds)->whereIn('sale_user_id', $saleUsers)->sum('total_override');
                                    }
                                }

                                if (isset($companyMargin->company_margin) && $companyMargin->company_margin > 0) {
                                    $marginPercentage = $companyMargin->company_margin;
                                    $x = ((100 - $marginPercentage) / 100);
                                    $amount = (((($value / 100) * $grossAmountValue) * $x) - ($finalCommission + $finalOverride + $lowerStackPay)) * ($stackShare / 100);
                                } else {
                                    $amount = (((($value / 100) * $grossAmountValue)) - ($finalCommission + $finalOverride + $lowerStackPay)) * ($stackShare / 100);
                                }

                                $where = [
                                    'user_id' => $userData->id,
                                    'type' => 'Stack',
                                    'pid' => $pid,
                                    'sale_user_id' => $saleUserId,
                                ];

                                $update = [
                                    'customer_name' => $saleData->customer_name,
                                    'kw' => $grossAmountValue,
                                    'total_override' => $amount,
                                    'overrides_amount' => $userData->office_stack_overrides_amount,
                                    'overrides_type' => 'per sale',
                                    'overrides_settlement_type' => $settlementType,
                                    'status' => 1,
                                    'is_stop_payroll' => 0,
                                ];
                                ProjectionUserOverrides::updateOrCreate($where, $update);

                                $i++;
                                $userIds[] = $userData->id;
                                $previousValue = $value;
                            }
                        }
                    }
                }
            }
        }
    }

    public function userStackCommission($userdata, $approvedDate)
    {
        return @UserCommissionHistory::where('user_id', $userdata->id)->where('commission_effective_date', '<=', $approvedDate)->first()->commission;
    }
}
