<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\Positions;
use App\Models\User;
use App\Models\UserCommissionHistory;
use App\Models\UserDeductionHistory;
use App\Models\UserIsManagerHistory;
use App\Models\UserManagerHistory;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserRedlines;
use App\Models\UserSelfGenCommmissionHistory;
use App\Models\UserTransferHistory;
use App\Models\UserUpfrontHistory;
use App\Models\UserWithheldHistory;
use App\Traits\EmailNotificationTrait;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyHistoryOnUser extends Command
{
    use EmailNotificationTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ApplyHistoryOnUsers:update {user_id? : Optional user id Comma Separated To Sync history!!}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'update users commission, upfornt, withheld, redline and override as per effective date.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $users = User::when($this->argument('user_id') && ! empty($this->argument('user_id')), function ($q) {
            $userId = explode(',', $this->argument('user_id'));
            $q->whereIn('id', $userId);
        })->get();

        $errors = [];
        foreach ($users as $user) {
            try {
                DB::beginTransaction();
                $nonSelfGenCommissions = UserCommissionHistory::where(['user_id' => $user->id, 'self_gen_user' => '0'])->orderBy('commission_effective_date', 'ASC')->get();
                foreach ($nonSelfGenCommissions as $key => $nonSelfGenCommission) {
                    if ($key != 0) {
                        $nonSelfGenCommission->old_self_gen_user = $nonSelfGenCommissions[$key - 1]->self_gen_user;
                        $nonSelfGenCommission->old_commission = $nonSelfGenCommissions[$key - 1]->commission;
                        $nonSelfGenCommission->old_commission_type = $nonSelfGenCommissions[$key - 1]->commission_type;
                        $nonSelfGenCommission->save();
                    }
                }

                $selfGenCommissions = UserCommissionHistory::where(['user_id' => $user->id, 'self_gen_user' => '1'])->orderBy('commission_effective_date', 'ASC')->get();
                foreach ($selfGenCommissions as $key => $selfGenCommission) {
                    if ($key != 0) {
                        $selfGenCommission->old_self_gen_user = $selfGenCommissions[$key - 1]->self_gen_user;
                        $selfGenCommission->old_commission = $selfGenCommissions[$key - 1]->commission;
                        $selfGenCommission->old_commission_type = $selfGenCommissions[$key - 1]->commission_type;
                        $selfGenCommission->save();
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                $errors[$user->id][] = 'UserCommissionHistory '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $selfGenCommissions = UserSelfGenCommmissionHistory::where(['user_id' => $user->id])->orderBy('commission_effective_date', 'ASC')->get();
                foreach ($selfGenCommissions as $key => $selfGenCommission) {
                    if ($key != 0) {
                        $selfGenCommission->old_self_gen_user = $selfGenCommissions[$key - 1]->self_gen_user;
                        $selfGenCommission->old_commission = $selfGenCommissions[$key - 1]->commission;
                        $selfGenCommission->old_commission_type = $selfGenCommissions[$key - 1]->commission_type;
                        $selfGenCommission->save();
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                $errors[$user->id][] = 'UserSelfGenCommissionHistory '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $nonSelfGenUpFronts = UserUpfrontHistory::where(['user_id' => $user->id, 'self_gen_user' => '0'])->orderBy('upfront_effective_date', 'ASC')->get();
                foreach ($nonSelfGenUpFronts as $key => $nonSelfGenUpFront) {
                    if ($key != 0) {
                        $nonSelfGenUpFront->old_self_gen_user = $nonSelfGenUpFronts[$key - 1]->self_gen_user;
                        $nonSelfGenUpFront->old_upfront_pay_amount = $nonSelfGenUpFronts[$key - 1]->upfront_pay_amount;
                        $nonSelfGenUpFront->old_upfront_sale_type = $nonSelfGenUpFronts[$key - 1]->upfront_sale_type;
                        $nonSelfGenUpFront->save();
                    }
                }

                $selfGenUpFronts = UserUpfrontHistory::where(['user_id' => $user->id, 'self_gen_user' => '1'])->orderBy('upfront_effective_date', 'ASC')->get();
                foreach ($selfGenUpFronts as $key => $selfGenUpFront) {
                    if ($key != 0) {
                        $selfGenUpFront->old_self_gen_user = $selfGenUpFronts[$key - 1]->self_gen_user;
                        $selfGenUpFront->old_upfront_pay_amount = $selfGenUpFronts[$key - 1]->upfront_pay_amount;
                        $selfGenUpFront->old_upfront_sale_type = $selfGenUpFronts[$key - 1]->upfront_sale_type;
                        $selfGenUpFront->save();
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                $errors[$user->id][] = 'UserUpfrontHistory '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $nonSelfGenRedLines = UserRedlines::where(['user_id' => $user->id, 'self_gen_user' => '0'])->orderBy('start_date', 'ASC')->get();
                foreach ($nonSelfGenRedLines as $key => $nonSelfGenRedLine) {
                    if ($key != 0) {
                        $nonSelfGenRedLine->old_self_gen_user = $nonSelfGenRedLines[$key - 1]->self_gen_user;
                        $nonSelfGenRedLine->old_redline_amount_type = $nonSelfGenRedLines[$key - 1]->redline_amount_type;
                        $nonSelfGenRedLine->old_redline = $nonSelfGenRedLines[$key - 1]->redline;
                        $nonSelfGenRedLine->old_redline_type = $nonSelfGenRedLines[$key - 1]->redline_type;
                        $nonSelfGenRedLine->save();
                    }
                }

                $selfGenRedLines = UserRedlines::where(['user_id' => $user->id, 'self_gen_user' => '1'])->orderBy('start_date', 'ASC')->get();
                foreach ($selfGenRedLines as $key => $selfGenRedLine) {
                    if ($key != 0) {
                        $selfGenRedLine->old_self_gen_user = $selfGenRedLines[$key - 1]->self_gen_user;
                        $selfGenRedLine->old_redline_amount_type = $selfGenRedLines[$key - 1]->redline_amount_type;
                        $selfGenRedLine->old_redline = $selfGenRedLines[$key - 1]->redline;
                        $selfGenRedLine->old_redline_type = $selfGenRedLines[$key - 1]->redline_type;
                        $selfGenRedLine->save();
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                $errors[$user->id][] = 'UserRedlines '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $nonSelfGenWithHelds = UserWithheldHistory::where(['user_id' => $user->id, 'self_gen_user' => '0'])->orderBy('withheld_effective_date', 'ASC')->get();
                foreach ($nonSelfGenWithHelds as $key => $nonSelfGenWithHeld) {
                    if ($key != 0) {
                        $nonSelfGenWithHeld->old_self_gen_user = $nonSelfGenWithHelds[$key - 1]->self_gen_user;
                        $nonSelfGenWithHeld->old_withheld_amount = $nonSelfGenWithHelds[$key - 1]->withheld_amount;
                        $nonSelfGenWithHeld->old_withheld_type = $nonSelfGenWithHelds[$key - 1]->withheld_type;
                        $nonSelfGenWithHeld->save();
                    }
                }

                $selfGenWithHelds = UserWithheldHistory::where(['user_id' => $user->id, 'self_gen_user' => '1'])->orderBy('withheld_effective_date', 'ASC')->get();
                foreach ($selfGenWithHelds as $key => $selfGenWithHeld) {
                    if ($key != 0) {
                        $selfGenWithHeld->old_self_gen_user = $selfGenWithHelds[$key - 1]->self_gen_user;
                        $selfGenWithHeld->old_withheld_amount = $selfGenWithHelds[$key - 1]->withheld_amount;
                        $selfGenWithHeld->old_withheld_type = $selfGenWithHelds[$key - 1]->withheld_type;
                        $selfGenWithHeld->save();
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                $errors[$user->id][] = 'UserWithheldHistory '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $overrides = UserOverrideHistory::where(['user_id' => $user->id])->orderBy('override_effective_date', 'ASC')->get();
                foreach ($overrides as $key => $override) {
                    if ($key != 0) {
                        $override->old_self_gen_user = $overrides[$key - 1]->self_gen_user;
                        $override->old_direct_overrides_amount = $overrides[$key - 1]->direct_overrides_amount;
                        $override->old_direct_overrides_type = $overrides[$key - 1]->direct_overrides_type;
                        $override->old_indirect_overrides_amount = $overrides[$key - 1]->indirect_overrides_amount;
                        $override->old_indirect_overrides_type = $overrides[$key - 1]->indirect_overrides_type;
                        $override->old_office_overrides_amount = $overrides[$key - 1]->office_overrides_amount;
                        $override->old_office_overrides_type = $overrides[$key - 1]->office_overrides_type;
                        $override->old_office_stack_overrides_amount = $overrides[$key - 1]->office_stack_overrides_amount;
                        $override->save();
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                $errors[$user->id][] = 'UserOverrideHistory '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $organizations = UserOrganizationHistory::where(['user_id' => $user->id])->orderBy('effective_date', 'ASC')->get();
                foreach ($organizations as $key => $organization) {
                    if ($key != 0) {
                        $organization->old_self_gen_accounts = $organizations[$key - 1]->self_gen_accounts;
                        $organization->old_manager_id = $organizations[$key - 1]->manager_id;
                        $organization->old_team_id = $organizations[$key - 1]->team_id;
                        $organization->old_position_id = $organizations[$key - 1]->position_id;
                        $organization->old_sub_position_id = $organizations[$key - 1]->sub_position_id;
                        $organization->old_is_manager = $organizations[$key - 1]->is_manager;
                        $organization->save();
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                $errors[$user->id][] = 'UserOrganizationHistory '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $managers = UserManagerHistory::where(['user_id' => $user->id])->orderBy('effective_date', 'ASC')->get();
                foreach ($managers as $key => $manager) {
                    if ($key != 0) {
                        $manager->old_manager_id = $managers[$key - 1]->manager_id;
                        $manager->old_team_id = $managers[$key - 1]->team_id;
                        $manager->old_position_id = $managers[$key - 1]->position_id;
                        $manager->old_sub_position_id = $managers[$key - 1]->sub_position_id;
                        $manager->save();
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                $errors[$user->id][] = 'UserManagerHistory '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $isManagers = UserIsManagerHistory::where(['user_id' => $user->id])->orderBy('effective_date', 'ASC')->get();
                foreach ($isManagers as $key => $isManager) {
                    if ($key != 0) {
                        $isManager->old_is_manager = $isManagers[$key - 1]->is_manager;
                        $isManager->old_position_id = $isManagers[$key - 1]->position_id;
                        $isManager->old_sub_position_id = $isManagers[$key - 1]->sub_position_id;
                        $isManager->save();
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                $errors[$user->id][] = 'UserIsManagerHistory '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $deductionCosts = UserDeductionHistory::where(['user_id' => $user->id])->groupBy('cost_center_id')->get();
                foreach ($deductionCosts as $deductionCost) {
                    $deductions = UserDeductionHistory::where(['user_id' => $user->id, 'cost_center_id' => $deductionCost->cost_center_id])->orderBy('effective_date', 'ASC')->get();
                    foreach ($deductions as $key => $deduction) {
                        if ($key != 0) {
                            $deduction->old_self_gen_user = $deductions[$key - 1]->self_gen_user;
                            $deduction->old_amount_par_paycheque = $deductions[$key - 1]->amount_par_paycheque;
                            $deduction->save();
                        }
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                $errors[$user->id][] = 'UserDeductionHistory '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $transfers = UserTransferHistory::where(['user_id' => $user->id])->orderBy('transfer_effective_date', 'ASC')->get();
                foreach ($transfers as $key => $transfer) {
                    if ($key != 0) {
                        $transfer->old_state_id = $transfers[$key - 1]->state_id;
                        $transfer->old_office_id = $transfers[$key - 1]->office_id;
                        $transfer->old_department_id = $transfers[$key - 1]->department_id;
                        $transfer->old_position_id = $transfers[$key - 1]->position_id;
                        $transfer->old_sub_position_id = $transfers[$key - 1]->sub_position_id;
                        $transfer->old_is_manager = $transfers[$key - 1]->is_manager;
                        $transfer->old_self_gen_accounts = $transfers[$key - 1]->self_gen_accounts;
                        $transfer->old_manager_id = $transfers[$key - 1]->manager_id;
                        $transfer->old_team_id = $transfers[$key - 1]->team_id;
                        $transfer->old_redline_amount_type = $transfers[$key - 1]->redline_amount_type;
                        $transfer->old_redline = $transfers[$key - 1]->redline;
                        $transfer->old_redline_type = $transfers[$key - 1]->redline_type;
                        $transfer->old_self_gen_redline_amount_type = $transfers[$key - 1]->self_gen_redline_amount_type;
                        $transfer->old_self_gen_redline = $transfers[$key - 1]->self_gen_redline;
                        $transfer->old_self_gen_redline_type = $transfers[$key - 1]->self_gen_redline_type;
                        $transfer->existing_employee_old_manager_id = $transfers[$key - 1]->existing_employee_new_manager_id;
                        $transfer->save();
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                $errors[$user->id][] = 'UserTransferHistory '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $date = date('Y-m-d');
                $organization = UserOrganizationHistory::where('user_id', $user->id)->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                $selfGen = 0;
                if ($organization) {
                    $user->self_gen_accounts = $organization->self_gen_accounts;
                    $user->self_gen_type = ($organization->position_id == 2) ? 3 : 2;
                    $user->position_id = $organization->position_id;
                    $user->position_id_effective_date = $organization->effective_date;
                    $user->sub_position_id = $organization->sub_position_id;

                    // $group_id = Positions::where('id', $organization->sub_position_id)->value('group_id');
                    // if ($group_id) {
                    //     $user->group_id = $group_id;
                    // }

                    $selfGen = $organization->self_gen_accounts;
                } else {
                    $user->self_gen_type = null;
                }

                if ($selfGen == 1) {
                    $selfGenCommission = UserSelfGenCommmissionHistory::where(['user_id' => $user->id])->where('commission_effective_date', '<=', $date)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($selfGenCommission) {
                        $user->self_gen_commission = $selfGenCommission->commission;
                        $user->self_gen_commission_type = $selfGenCommission->commission_type;
                        $user->self_gen_commission_effective_date = $selfGenCommission->commission_effective_date;
                    }

                    $upfront = UserUpfrontHistory::where(['user_id' => $user->id, 'self_gen_user' => '1'])->where('upfront_effective_date', '<=', $date)->orderBy('upfront_effective_date', 'DESC')->first();
                    if ($upfront) {
                        $user->self_gen_upfront_amount = $upfront->upfront_pay_amount;
                        $user->self_gen_upfront_type = $upfront->upfront_sale_type;
                        $user->self_gen_upfront_effective_date = $upfront->upfront_effective_date;
                    }

                    $redline = UserRedlines::where(['user_id' => $user->id, 'self_gen_user' => '1'])->where('start_date', '<=', $date)->orderBy('start_date', 'DESC')->first();
                    if ($redline) {
                        $user->self_gen_redline = $redline->redline;
                        $user->self_gen_redline_amount_type = $redline->redline_amount_type;
                        $user->self_gen_redline_type = $redline->redline_type;
                        $user->self_gen_redline_effective_date = $redline->start_date;
                    }

                    $withheld = UserWithheldHistory::where(['user_id' => $user->id, 'self_gen_user' => '1'])->where('withheld_effective_date', '<=', $date)->orderBy('withheld_effective_date', 'DESC')->first();
                    if ($withheld) {
                        $user->self_gen_withheld_amount = $withheld->withheld_amount;
                        $user->self_gen_withheld_type = $withheld->withheld_type;
                        $user->self_gen_withheld_effective_date = $withheld->withheld_effective_date;
                    }
                }

                $commission = UserCommissionHistory::where(['user_id' => $user->id, 'self_gen_user' => '0'])->where('commission_effective_date', '<=', $date)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commission) {
                    $user->commission = $commission->commission;
                    $user->commission_type = $commission->commission_type;
                    $user->commission_effective_date = $commission->commission_effective_date;
                }

                $upfront = UserUpfrontHistory::where(['user_id' => $user->id, 'self_gen_user' => '0'])->where('upfront_effective_date', '<=', $date)->orderBy('upfront_effective_date', 'DESC')->first();
                if ($upfront) {
                    $user->upfront_pay_amount = $upfront->upfront_pay_amount;
                    $user->upfront_sale_type = $upfront->upfront_sale_type;
                    $user->upfront_effective_date = $upfront->upfront_effective_date;
                }

                $redline = UserRedlines::where(['user_id' => $user->id, 'self_gen_user' => '0'])->where('start_date', '<=', $date)->orderBy('start_date', 'DESC')->first();
                if ($redline) {
                    $user->redline = $redline->redline;
                    $user->redline_amount_type = $redline->redline_amount_type;
                    $user->redline_type = $redline->redline_type;
                    $user->redline_effective_date = $redline->start_date;
                }

                $withheld = UserWithheldHistory::where(['user_id' => $user->id, 'self_gen_user' => '0'])->where('withheld_effective_date', '<=', $date)->orderBy('withheld_effective_date', 'DESC')->first();
                if ($withheld) {
                    $user->withheld_amount = $withheld->withheld_amount;
                    $user->withheld_type = $withheld->withheld_type;
                    $user->withheld_effective_date = $withheld->withheld_effective_date;
                }

                $override = UserOverrideHistory::where(['user_id' => $user->id])->where('override_effective_date', '<=', $date)->orderBy('override_effective_date', 'DESC')->first();
                if ($override) {
                    $user->direct_overrides_amount = $override->direct_overrides_amount;
                    $user->direct_overrides_type = $override->direct_overrides_type;
                    $user->indirect_overrides_amount = $override->indirect_overrides_amount;
                    $user->indirect_overrides_type = $override->indirect_overrides_type;
                    $user->office_overrides_amount = $override->office_overrides_amount;
                    $user->office_overrides_type = $override->office_overrides_type;
                    $user->office_stack_overrides_amount = $override->office_stack_overrides_amount;
                    $user->override_effective_date = $override->override_effective_date;
                }

                $manager = UserManagerHistory::where(['user_id' => $user->id])->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                if ($manager) {
                    $user->manager_id = $manager->manager_id;
                    $user->manager_id_effective_date = $manager->effective_date;
                    $user->team_id = $manager->team_id;
                    $user->team_id_effective_date = $manager->effective_date;
                }

                $isManager = UserIsManagerHistory::where(['user_id' => $user->id])->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                if ($isManager) {
                    $user->is_manager = $isManager->is_manager;
                    $user->is_manager_effective_date = $isManager->effective_date;
                }

                $transfer = UserTransferHistory::where(['user_id' => $user->id])->where('transfer_effective_date', '<=', $date)->orderBy('transfer_effective_date', 'DESC')->first();
                if ($transfer) {
                    $user->state_id = $transfer->state_id;
                    $user->office_id = $transfer->office_id;
                    $user->department_id = $transfer->department_id;
                }
                $user->save();

                $organization = UserOrganizationHistory::where(['user_id' => $user->id, 'effective_date' => $date])->first();
                $transfer = UserTransferHistory::where(['user_id' => $user->id, 'transfer_effective_date' => $date])->first();
                if ($transfer) {
                    if ($transfer->existing_employee_new_manager_id) {
                        $userEmployeeIds = User::where('manager_id', $user->id)->get();
                        foreach ($userEmployeeIds as $userEmployeeId) {
                            $organizationHistory = UserOrganizationHistory::where('user_id', $userEmployeeId->id)->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                            UserManagerHistory::updateOrCreate([
                                'user_id' => $userEmployeeId->id,
                                'effective_date' => $date,
                            ], [
                                'user_id' => $userEmployeeId->id,
                                'updater_id' => Auth()->user()->id,
                                'effective_date' => $date,
                                'manager_id' => $transfer->existing_employee_new_manager_id,
                                'position_id' => @$organizationHistory->position_id ? $organizationHistory->position_id : $userEmployeeId->position_id,
                                'sub_position_id' => @$organizationHistory->sub_position_id ? $organizationHistory->sub_position_id : $userEmployeeId->sub_position_id,
                            ]);
                        }

                        $leadData = Lead::where('recruiter_id', $user->id)->pluck('id')->toArray();
                        if (count($leadData) != 0) {
                            Lead::whereIn('id', $leadData)->update(['reporting_manager_id' => $transfer->existing_employee_new_manager_id]);
                        }
                    }
                } elseif ($organization) {
                    if ($organization->existing_employee_new_manager_id) {
                        $userEmployeeIds = User::where('manager_id', $user->id)->get();
                        foreach ($userEmployeeIds as $userEmployeeId) {
                            $organizationHistory = UserOrganizationHistory::where('user_id', $userEmployeeId->id)->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                            UserManagerHistory::updateOrCreate([
                                'user_id' => $userEmployeeId->id,
                                'effective_date' => $date,
                            ], [
                                'user_id' => $userEmployeeId->id,
                                'updater_id' => Auth()->user()->id,
                                'effective_date' => $date,
                                'manager_id' => $organization->existing_employee_new_manager_id,
                                'position_id' => @$organizationHistory->position_id ? $organizationHistory->position_id : $userEmployeeId->position_id,
                                'sub_position_id' => @$organizationHistory->sub_position_id ? $organizationHistory->sub_position_id : $userEmployeeId->sub_position_id,
                            ]);
                        }

                        $leadData = Lead::where('recruiter_id', $user->id)->pluck('id')->toArray();
                        if (count($leadData) != 0) {
                            Lead::whereIn('id', $leadData)->update(['reporting_manager_id' => $organization->existing_employee_new_manager_id]);
                        }
                    }
                }
                DB::commit();
            } catch (\Exception $e) {
                $errors[$user->id][] = $e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }
        }
    }
}
