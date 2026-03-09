<?php

namespace App\Console\Commands;

use App\Models\CompanyProfile;
use App\Models\LegacyApiNullData;
use App\Models\LocationRedlineHistory;
use App\Models\Locations;
use App\Models\User;
use App\Models\UserOrganizationHistory;
use App\Models\UserRedlines;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateAlertsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:alert {pid? : Optional PID Comma Separated To Generate Alert!!}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Alert Data';

    // CHANGING ON THIS FUNCTION WOULD REQUIRE CHANGE ON ApiMissingDataController -> alert_center_details() & ReportsAdminController -> global_search() FUNCTION
    public function handle(): int
    {
        if (config('app.domain_name') == 'flex') {
            $salesKeys = ['pid', 'customer_signoff', 'epc', 'net_epc', 'customer_name', 'customer_state', 'kw'];
        } else {
            $salesKeys = ['pid', 'customer_signoff', 'epc', 'net_epc', 'customer_name', 'customer_state', 'location_code', 'kw'];
        }

        $userEmailData = [];
        $userData = DB::Select('select * from (SELECT uae.user_id, uae.email FROM `users_additional_emails` uae join users u on u.id = uae.user_id union select id, email from users) as tbl');
        foreach ($userData as $ud) {
            $userEmailData[] = [
                'id' => $ud->user_id,
                'email' => $ud->email,
            ];
        }
        $userEmailArray = array_column($userEmailData, 'email');

        $data = LegacyApiNullData::when($this->argument('pid') && ! empty($this->argument('pid')), function ($q) {
            $pid = explode(',', $this->argument('pid'));
            $q->whereIn('pid', $pid);
        })->get();
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || $companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            $salesKeys = ['pid', 'customer_signoff', 'customer_name', 'gross_account_value'];
            foreach ($data as $row) {
                $salesAlertArray = [];
                $missingRepArray = [];

                /******** SALES ALERT  ********/
                foreach ($salesKeys as $salesKey) {
                    if ($row->$salesKey === null || $row->$salesKey === '') {
                        $salesAlertArray[] = $salesKey;
                    }
                }
                $salesAlert = (count($salesAlertArray) > 0) ? implode(',', array_unique($salesAlertArray)) : null;
                /******** SALES ALERT  ********/

                /******** MISSING REP ALERT  ********/
                if (empty($row->sales_rep_email)) {
                    $missingRepArray[] = 'sales_rep_email';
                } else {
                    $checkUser = User::where('email', $row->sales_rep_email)->first();
                    $proceedToApprovalCheck = false;
                    $checkUser = User::where('email', $row->sales_rep_email)->first();
                    if ($checkUser) {
                        if ($row->customer_signoff) {
                            if ($checkUser->isTerminatedOn($row->customer_signoff)) {
                                $missingRepArray[] = 'sales_rep_terminated';
                            } elseif ($checkUser->contract_ended) {
                                $missingRepArray[] = 'sales_rep_contract_ended';
                            } elseif (isUserDismisedOn($checkUser->id, $row->customer_signoff)) {
                                $missingRepArray[] = 'sales_rep_dismissed';
                            } else {
                                $proceedToApprovalCheck = true;
                            }
                        }
                    } else {
                        $proceedToApprovalCheck = true;
                    }

                    if ($proceedToApprovalCheck ?? false) {
                        if (! in_array($row->sales_rep_email, $userEmailArray)) {
                            $missingRepArray[] = 'new_sales_rep|'.$row->sales_rep_email;
                        } else {
                            $closerIdIndex = array_search($row->sales_rep_email, $userEmailArray);
                            if (! empty($closerIdIndex)) {
                                $closerData = $userEmailData[$closerIdIndex];
                                $closerData['sales_data'] = $row;
                                if (@$closerData['sales_data']['customer_signoff']) {
                                    $userOrg = UserOrganizationHistory::where('user_id', $closerData['id'])->where('effective_date', '<=', $closerData['sales_data']['customer_signoff'])->orderBy('effective_date', 'DESC')->first();
                                    if ($userOrg) {
                                        $userOrg = UserOrganizationHistory::where(['user_id' => $closerData['id'], 'effective_date' => $userOrg->effective_date])->first();
                                        if (! $userOrg) {
                                            $missingRepArray[] = 'sales_rep_email_saleapproval';
                                        }
                                    } else {
                                        $missingRepArray[] = 'sales_rep_email_saleapproval';
                                    }
                                }
                            }
                        }
                    }
                }
                $missingRepAlert = (count($missingRepArray) > 0) ? implode(',', array_unique($missingRepArray)) : null;
                /******** MISSING REP ALERT  ********/

                LegacyApiNullData::where('id', $row->id)->update([
                    'sales_alert' => $salesAlert,
                    'missingrep_alert' => $missingRepAlert,
                    'closedpayroll_alert' => null,
                    'locationredline_alert' => null,
                    'repredline_alert' => null,
                ]);
            }
        } else {
            foreach ($data as $row) {
                $location = [];
                $repRedlineArray = [];
                $salesAlertArray = [];
                $missingRepArray = [];
                $locationRedlineArray = [];

                /******** SALES ALERT  ********/
                foreach ($salesKeys as $salesKey) {
                    if ($row->$salesKey === null || $row->$salesKey === '') {
                        $salesAlertArray[] = $salesKey;
                    }
                }
                $salesAlert = (count($salesAlertArray) > 0) ? implode(',', $salesAlertArray) : null;
                /******** SALES ALERT  ********/

                /******** MISSING REP ALERT  ********/
                if (empty($row->sales_rep_email)) {
                    $missingRepArray[] = 'sales_rep_email';
                } else {
                    $proceedToApprovalCheck = false;
                    $checkUser = User::where(['email' => $row->sales_rep_email])->first();
                    if ($checkUser) {
                        if ($row->customer_signoff) {
                            if ($checkUser->isTerminatedOn($row->customer_signoff)) {
                                $missingRepArray[] = 'sales_rep_terminated';
                            } elseif ($checkUser->contract_ended) {
                                $missingRepArray[] = 'sales_rep_contract_ended';
                            } elseif (isUserDismisedOn($checkUser->id, $row->customer_signoff)) {
                                $missingRepArray[] = 'sales_rep_dismissed';
                            } else {
                                $proceedToApprovalCheck = true;
                            }
                        }
                    } else {
                        $proceedToApprovalCheck = true;
                    }

                    if ($proceedToApprovalCheck ?? false) {
                        if (! in_array($row->sales_rep_email, $userEmailArray)) {
                            $missingRepArray[] = 'new_sales_rep|'.$row->sales_rep_email;
                        } else {
                            $closerIdIndex = array_search($row->sales_rep_email, $userEmailArray);
                            if (! empty($closerIdIndex)) {
                                $closerData = $userEmailData[$closerIdIndex];
                                $closerData['sales_data'] = $row;
                                if (@$closerData['sales_data']['customer_signoff']) {
                                    $userOrg = UserOrganizationHistory::where('user_id', $closerData['id'])->where('effective_date', '<=', $closerData['sales_data']['customer_signoff'])->orderBy('effective_date', 'DESC')->first();
                                    if ($userOrg) {
                                        $userOrg = UserOrganizationHistory::where(['user_id' => $closerData['id'], 'effective_date' => $userOrg->effective_date])->first();
                                        if (! $userOrg) {
                                            $missingRepArray[] = 'sales_rep_email_saleapproval';
                                        } else {
                                            if ($userOrg && $userOrg->self_gen_accounts != '1' && $userOrg->position_id == '3') {
                                                $missingRepArray[] = 'sales_rep_email_saleapproval';
                                            }
                                        }
                                    } else {
                                        $missingRepArray[] = 'sales_rep_email_saleapproval';
                                    }
                                }
                            }
                        }
                    }
                }

                if (empty($row->sales_setter_email)) {
                    $missingRepArray[] = 'sales_setter_email';
                } else {
                    $proceedToApprovalCheck = false;
                    $checkUser = User::where(['email' => $row->sales_setter_email])->first();
                    if ($checkUser) {
                        if ($row->customer_signoff) {
                            if ($checkUser->isTerminatedOn($row->customer_signoff)) {
                                $missingRepArray[] = 'sales_setter_terminated';
                            } elseif ($checkUser->contract_ended) {
                                $missingRepArray[] = 'sales_setter_contract_ended';
                            } elseif (isUserDismisedOn($checkUser->id, $row->customer_signoff)) {
                                $missingRepArray[] = 'sales_setter_dismissed';
                            } else {
                                $proceedToApprovalCheck = true;
                            }
                        }
                    } else {
                        $proceedToApprovalCheck = true;
                    }

                    if ($proceedToApprovalCheck ?? false) {
                        if (! in_array($row->sales_setter_email, $userEmailArray)) {
                            $missingRepArray[] = 'new_sales_setter|'.$row->sales_setter_email;
                        } else {
                            $setterIdIndex = array_search($row->sales_setter_email, $userEmailArray);
                            if (! empty($setterIdIndex)) {
                                $setterData = $userEmailData[$setterIdIndex];
                                $setterData['sales_data'] = $row;
                                if (@$setterData['sales_data']['customer_signoff']) {
                                    $userOrg = UserOrganizationHistory::where('user_id', $setterData['id'])->where('effective_date', '<=', $setterData['sales_data']['customer_signoff'])->orderBy('effective_date', 'DESC')->first();
                                    if ($userOrg) {
                                        $userOrg = UserOrganizationHistory::where(['user_id' => $setterData['id'], 'effective_date' => $userOrg->effective_date])->first();
                                        if (! $userOrg) {
                                            $missingRepArray[] = 'sales_setter_email_saleapproval';
                                        } else {
                                            if ($userOrg && $userOrg->self_gen_accounts != '1' && $userOrg->position_id == '2') {
                                                $missingRepArray[] = 'sales_setter_email_saleapproval';
                                            }
                                        }
                                    } else {
                                        $missingRepArray[] = 'sales_setter_email_saleapproval';
                                    }
                                }
                            }
                        }
                    }
                }
                $missingRepAlert = (count($missingRepArray) > 0) ? implode(',', $missingRepArray) : null;
                /******** MISSING REP ALERT  ********/

                /******** LOCATION REDLINE ALERT  ********/
                if (! empty($row->location_code)) {
                    if (! $location = Locations::where('general_code', $row->location_code)->first()) {
                        $locationRedlineArray[] = 'Location';
                    } else {
                        if ($row->customer_signoff) {
                            if (! LocationRedlineHistory::where('location_id', $location->id)->where('effective_date', '<=', $row->customer_signoff)->first()) {
                                $locationRedlineArray[] = 'Location_redline';
                            }
                        }
                    }
                }
                $locationRedlineAlert = (count($locationRedlineArray) > 0) ? implode(',', $locationRedlineArray) : null;
                /******** LOCATION REDLINE ALERT  ********/

                /******** REP REDLINE ALERT  ********/
                $closerIdIndex = array_search($row->sales_rep_email, $userEmailArray);
                if (! empty($closerIdIndex)) {
                    $closerData = $userEmailData[$closerIdIndex];
                    $closerData['sales_data'] = $row;
                    if (@$closerData['sales_data']['customer_signoff']) {
                        if ($closerData['sales_data']['closer_id'] && $closerData['sales_data']['setter_id'] == $closerData['sales_data']['closer_id']) {
                            $userRedlineHistoryData = UserRedlines::where(['user_id' => $closerData['id']])->whereNull('core_position_id')->where('start_date', '<=', $closerData['sales_data']['customer_signoff'])->orderby('start_date', 'desc')->first();
                            if (! $userRedlineHistoryData) {
                                $repRedlineArray[] = 'repredline_closer_selfgenredline_saleapproval';
                            }
                        } else {
                            $userRedlineHistoryData = UserRedlines::where(['user_id' => $closerData['id'], 'core_position_id' => '2'])->where('start_date', '<=', $closerData['sales_data']['customer_signoff'])->orderby('start_date', 'desc')->first();
                            if (! $userRedlineHistoryData) {
                                $repRedlineArray[] = 'repredline_closer_redline_saleapproval';
                            }
                        }
                    }
                }

                $setterIdIndex = array_search($row->sales_setter_email, $userEmailArray);
                if (! empty($setterIdIndex)) {
                    $setterData = $userEmailData[$setterIdIndex];
                    $setterData['sales_data'] = $row;
                    if (@$setterData['sales_data']['customer_signoff']) {
                        if ($setterData['sales_data']['closer_id'] && $setterData['sales_data']['setter_id'] == $setterData['sales_data']['closer_id']) {
                            //
                        } else {
                            $userRedlineHistoryData = UserRedlines::where(['user_id' => $setterData['id'], 'core_position_id' => '3'])->where('start_date', '<=', $setterData['sales_data']['customer_signoff'])->orderby('start_date', 'desc')->first();
                            if (! $userRedlineHistoryData) {
                                $repRedlineArray[] = 'repredline_setter_redline_saleapproval';
                            }
                        }
                    }
                }
                $repRedlineAlert = (count($repRedlineArray) > 0) ? implode(',', $repRedlineArray) : null;
                /******** REP REDLINE ALERT  ********/

                LegacyApiNullData::where('id', $row->id)->update([
                    'sales_alert' => $salesAlert,
                    'missingrep_alert' => $missingRepAlert,
                    'closedpayroll_alert' => null,
                    'locationredline_alert' => $locationRedlineAlert,
                    'repredline_alert' => $repRedlineAlert,
                ]);
            }
        }

        return Command::SUCCESS;
    }
}
