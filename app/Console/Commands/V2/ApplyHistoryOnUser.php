<?php

namespace App\Console\Commands\V2;

use App\Models\Lead;
use App\Models\OnboardingEmployees;
use App\Models\Positions;
use App\Models\Products;
use App\Models\User;
use App\Models\UserAgreementHistory;
use App\Models\UserCommissionHistory;
use App\Models\UserDeductionHistory;
use App\Models\UserDepartmentHistory;
use App\Models\UserDismissHistory;
use App\Models\UserIsManagerHistory;
use App\Models\UserManagerHistory;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserRedlines;
use App\Models\UserTerminateHistory;
use App\Models\UserTransferHistory;
use App\Models\UserUpfrontHistory;
use App\Models\UserWithheldHistory;
use App\Traits\EmailNotificationTrait;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ApplyHistoryOnUser extends Command
{
    use EmailNotificationTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ApplyHistoryOnUsersV2:update {user_id? : Optional user id Comma Separated To Sync history!!} {auth_user_id=1 : Auth user ID (default is 1)}';

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
        $authUserId = $this->argument('auth_user_id');

        $users = User::when($this->argument('user_id') && ! empty($this->argument('user_id')), function ($q) {
            $userId = explode(',', $this->argument('user_id'));
            $q->whereIn('id', $userId);
        })->get();

        $errors = [];
        foreach ($users as $user) {
            $userId = $user->id;

            try {
                DB::beginTransaction();
                $commissionProducts = UserCommissionHistory::where(['user_id' => $userId])->groupBy('product_id')->pluck('product_id');
                foreach ($commissionProducts as $commissionProduct) {
                    $closerCommissions = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $commissionProduct, 'core_position_id' => '2'])->orderBy('commission_effective_date', 'ASC')->get();
                    foreach ($closerCommissions as $key => $closerCommission) {
                        if ($key != 0) {
                            $closerCommission->old_self_gen_user = $closerCommissions[$key - 1]->self_gen_user;
                            $closerCommission->old_commission = $closerCommissions[$key - 1]->commission;
                            $closerCommission->old_commission_type = $closerCommissions[$key - 1]->commission_type;
                            $closerCommission->old_tiers_id = $closerCommissions[$key - 1]->tiers_id;
                            $closerCommission->save();
                        }
                    }

                    $setterCommissions = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $commissionProduct, 'core_position_id' => '3'])->orderBy('commission_effective_date', 'ASC')->get();
                    foreach ($setterCommissions as $key => $setterCommission) {
                        if ($key != 0) {
                            $setterCommission->old_self_gen_user = $setterCommissions[$key - 1]->self_gen_user;
                            $setterCommission->old_commission = $setterCommissions[$key - 1]->commission;
                            $setterCommission->old_commission_type = $setterCommissions[$key - 1]->commission_type;
                            $setterCommission->old_tiers_id = $setterCommissions[$key - 1]->tiers_id;
                            $setterCommission->save();
                        }
                    }

                    $selfGenCommissions = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $commissionProduct])->whereNull('core_position_id')->orderBy('commission_effective_date', 'ASC')->get();
                    foreach ($selfGenCommissions as $key => $selfGenCommission) {
                        if ($key != 0) {
                            $selfGenCommission->old_self_gen_user = $selfGenCommissions[$key - 1]->self_gen_user;
                            $selfGenCommission->old_commission = $selfGenCommissions[$key - 1]->commission;
                            $selfGenCommission->old_commission_type = $selfGenCommissions[$key - 1]->commission_type;
                            $selfGenCommission->old_tiers_id = $selfGenCommissions[$key - 1]->tiers_id;
                            $selfGenCommission->save();
                        }
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                $errors[$userId][] = 'UserCommissionHistory '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $upFrontProducts = UserUpfrontHistory::select('product_id', 'milestone_schema_trigger_id')->where(['user_id' => $userId])->groupBy('product_id', 'milestone_schema_trigger_id')->get();
                foreach ($upFrontProducts as $upFrontProduct) {
                    $closerUpFronts = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $upFrontProduct->product_id, 'milestone_schema_trigger_id' => $upFrontProduct->milestone_schema_trigger_id, 'core_position_id' => '2'])->orderBy('upfront_effective_date', 'ASC')->get();
                    foreach ($closerUpFronts as $key => $closerUpFront) {
                        if ($key != 0) {
                            $closerUpFront->old_self_gen_user = $closerUpFronts[$key - 1]->self_gen_user;
                            $closerUpFront->old_upfront_pay_amount = $closerUpFronts[$key - 1]->upfront_pay_amount;
                            $closerUpFront->old_upfront_sale_type = $closerUpFronts[$key - 1]->upfront_sale_type;
                            $closerUpFront->old_tiers_id = $closerUpFronts[$key - 1]->tiers_id;
                            $closerUpFront->save();
                        }
                    }

                    $setterUpFronts = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $upFrontProduct->product_id, 'milestone_schema_trigger_id' => $upFrontProduct->milestone_schema_trigger_id, 'core_position_id' => '3'])->orderBy('upfront_effective_date', 'ASC')->get();
                    foreach ($setterUpFronts as $key => $setterUpFront) {
                        if ($key != 0) {
                            $setterUpFront->old_self_gen_user = $setterUpFronts[$key - 1]->self_gen_user;
                            $setterUpFront->old_upfront_pay_amount = $setterUpFronts[$key - 1]->upfront_pay_amount;
                            $setterUpFront->old_upfront_sale_type = $setterUpFronts[$key - 1]->upfront_sale_type;
                            $setterUpFront->old_tiers_id = $setterUpFronts[$key - 1]->tiers_id;
                            $setterUpFront->save();
                        }
                    }

                    $selfGenUpFronts = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $upFrontProduct->product_id, 'milestone_schema_trigger_id' => $upFrontProduct->milestone_schema_trigger_id])->whereNull('core_position_id')->orderBy('upfront_effective_date', 'ASC')->get();
                    foreach ($selfGenUpFronts as $key => $selfGenUpFront) {
                        if ($key != 0) {
                            $selfGenUpFront->old_self_gen_user = $selfGenUpFronts[$key - 1]->self_gen_user;
                            $selfGenUpFront->old_upfront_pay_amount = $selfGenUpFronts[$key - 1]->upfront_pay_amount;
                            $selfGenUpFront->old_upfront_sale_type = $selfGenUpFronts[$key - 1]->upfront_sale_type;
                            $selfGenUpFront->old_tiers_id = $selfGenUpFronts[$key - 1]->tiers_id;
                            $selfGenUpFront->save();
                        }
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                $errors[$userId][] = 'UserUpfrontHistory '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $redLineGroups = UserRedlines::where(['user_id' => $userId])->groupBy('core_position_id')->pluck('core_position_id');
                foreach ($redLineGroups as $redLineGroup) {
                    $userRedLines = UserRedlines::where(['user_id' => $userId, 'core_position_id' => $redLineGroup])->orderBy('start_date', 'ASC')->get();
                    foreach ($userRedLines as $key => $userRedLine) {
                        if ($key != 0) {
                            $userRedLine->old_self_gen_user = $userRedLines[$key - 1]->self_gen_user;
                            $userRedLine->old_redline_amount_type = $userRedLines[$key - 1]->redline_amount_type;
                            $userRedLine->old_redline = $userRedLines[$key - 1]->redline;
                            $userRedLine->old_redline_type = $userRedLines[$key - 1]->redline_type;
                            $userRedLine->save();
                        }
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                $errors[$userId][] = 'UserRedlines '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $withHeldProducts = UserWithheldHistory::where(['user_id' => $userId])->groupBy('product_id')->pluck('product_id');
                foreach ($withHeldProducts as $withHeldProduct) {
                    $userWithHelds = UserWithheldHistory::where(['user_id' => $userId, 'product_id' => $withHeldProduct])->orderBy('withheld_effective_date', 'ASC')->get();
                    foreach ($userWithHelds as $key => $userWithHeld) {
                        if ($key != 0) {
                            // $userWithHeld->old_self_gen_user = $userWithHelds[$key - 1]->self_gen_user;
                            $userWithHeld->old_withheld_amount = $userWithHelds[$key - 1]->withheld_amount;
                            $userWithHeld->old_withheld_type = $userWithHelds[$key - 1]->withheld_type;
                            $userWithHeld->save();
                        }
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                $errors[$userId][] = 'UserWithheldHistory '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $overrideProducts = UserOverrideHistory::where(['user_id' => $userId])->groupBy('product_id')->pluck('product_id');
                foreach ($overrideProducts as $overrideProduct) {
                    $userOverrides = UserOverrideHistory::where(['user_id' => $userId, 'product_id' => $overrideProduct])->orderBy('override_effective_date', 'ASC')->get();
                    foreach ($userOverrides as $key => $userOverride) {
                        if ($key != 0) {
                            // $userOverride->old_self_gen_user = $userOverrides[$key - 1]->self_gen_user;
                            $userOverride->old_direct_overrides_amount = $userOverrides[$key - 1]->direct_overrides_amount;
                            $userOverride->old_direct_overrides_type = $userOverrides[$key - 1]->direct_overrides_type;
                            $userOverride->old_indirect_overrides_amount = $userOverrides[$key - 1]->indirect_overrides_amount;
                            $userOverride->old_indirect_overrides_type = $userOverrides[$key - 1]->indirect_overrides_type;
                            $userOverride->old_office_overrides_amount = $userOverrides[$key - 1]->office_overrides_amount;
                            $userOverride->old_office_overrides_type = $userOverrides[$key - 1]->office_overrides_type;
                            $userOverride->old_office_stack_overrides_amount = $userOverrides[$key - 1]->office_stack_overrides_amount;
                            $userOverride->save();
                        }
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                $errors[$userId][] = 'UserOverrideHistory '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $organizations = UserOrganizationHistory::where(['user_id' => $userId])->groupBy('product_id')->pluck('product_id');
                foreach ($organizations as $organization) {
                    $userOrganizations = UserOrganizationHistory::where(['user_id' => $userId, 'product_id' => $organization])->orderBy('effective_date', 'ASC')->get();
                    foreach ($userOrganizations as $key => $userOrganization) {
                        if ($key != 0) {
                            $userOrganization->old_self_gen_accounts = $userOrganizations[$key - 1]->self_gen_accounts;
                            $userOrganization->old_manager_id = $userOrganizations[$key - 1]->manager_id;
                            $userOrganization->old_team_id = $userOrganizations[$key - 1]->team_id;
                            $userOrganization->old_position_id = $userOrganizations[$key - 1]->position_id;
                            $userOrganization->old_sub_position_id = $userOrganizations[$key - 1]->sub_position_id;
                            $userOrganization->old_is_manager = $userOrganizations[$key - 1]->is_manager;
                            $userOrganization->save();
                        }
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                $errors[$userId][] = 'UserOrganizationHistory '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $managers = UserManagerHistory::where(['user_id' => $userId])->orderBy('effective_date', 'ASC')->get();
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
                $errors[$userId][] = 'UserManagerHistory '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $isManagers = UserIsManagerHistory::where(['user_id' => $userId])->orderBy('effective_date', 'ASC')->get();
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
                $errors[$userId][] = 'UserIsManagerHistory '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $deductionCosts = UserDeductionHistory::where(['user_id' => $userId])->groupBy('cost_center_id')->get();
                foreach ($deductionCosts as $deductionCost) {
                    $deductions = UserDeductionHistory::where(['user_id' => $userId, 'cost_center_id' => $deductionCost->cost_center_id])->orderBy('effective_date', 'ASC')->get();
                    foreach ($deductions as $key => $deduction) {
                        if ($key != 0) {
                            // $deduction->old_self_gen_user = $deductions[$key - 1]->self_gen_user;
                            $deduction->old_amount_par_paycheque = $deductions[$key - 1]->amount_par_paycheque;
                            $deduction->save();
                        }
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                $errors[$userId][] = 'UserDeductionHistory '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $transfers = UserTransferHistory::where(['user_id' => $userId])->orderBy('transfer_effective_date', 'ASC')->get();
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
                $errors[$userId][] = 'UserTransferHistory '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $transfers = UserDepartmentHistory::where(['user_id' => $userId])->orderBy('effective_date', 'ASC')->get();
                foreach ($transfers as $key => $transfer) {
                    if ($key != 0) {
                        $transfer->old_department_id = $transfers[$key - 1]->department_id;
                        $transfer->save();
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                $errors[$userId][] = 'UserDepartmentHistory '.$e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }

            try {
                DB::beginTransaction();
                $date = date('Y-m-d');
                $organization = UserOrganizationHistory::where(['user_id' => $userId])->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                if ($organization) {

                    /* START - commenting bellow code as its seems unnecessary

                    $subPositionId = $organization?->sub_position_id;
                    $position = Positions::where('id', $subPositionId)->first();
                    $product = Products::where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                    $selfGen = $position->is_selfgen;
                    $corePositionId = 2;
                    if ($selfGen == 3) {
                        $corePositionId = 2;
                    }

                    END - commenting bellow code as its seems unnecessary */

                    if ($organization) {
                        $user->self_gen_accounts = $organization->self_gen_accounts;
                        $user->self_gen_type = $organization->position_id;
                        $user->position_id = $organization->position_id;
                        $user->position_id_effective_date = $organization->effective_date;
                        $user->sub_position_id = $organization->sub_position_id;
                    } else {
                        $user->self_gen_type = null;
                    }

                    $manager = UserManagerHistory::where(['user_id' => $userId])->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                    if ($manager) {
                        $user->manager_id = $manager->manager_id;
                        $user->manager_id_effective_date = $manager->effective_date;
                        $user->team_id = $manager->team_id;
                        $user->team_id_effective_date = $manager->effective_date;
                    }

                    $isManager = UserIsManagerHistory::where(['user_id' => $userId])->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                    if ($isManager) {
                        $user->is_manager = $isManager->is_manager;
                        $user->is_manager_effective_date = $isManager->effective_date;
                    }

                    $transfer = UserTransferHistory::where(['user_id' => $userId])->where('transfer_effective_date', '<=', $date)->orderBy('transfer_effective_date', 'DESC')->first();
                    if ($transfer) {
                        $user->state_id = $transfer->state_id;
                        $user->office_id = $transfer->office_id;
                    }

                    $department = UserDepartmentHistory::where(['user_id' => $userId])->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                    if ($department) {
                        $user->department_id = $department->department_id;
                    }
                    $user->save();
                }

                $organization = UserOrganizationHistory::where(['user_id' => $userId, 'effective_date' => $date])->first();
                $transfer = UserTransferHistory::where(['user_id' => $userId, 'transfer_effective_date' => $date])->first();
                if ($transfer) {
                    if ($transfer->existing_employee_new_manager_id) {
                        $userEmployeeIds = User::where('manager_id', $userId)->get();
                        foreach ($userEmployeeIds as $userEmployeeId) {
                            $organizationHistory = UserOrganizationHistory::where('user_id', $userEmployeeId->id)->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                            UserManagerHistory::updateOrCreate([
                                'user_id' => $userEmployeeId->id,
                                'effective_date' => $date,
                            ], [
                                'user_id' => $userEmployeeId->id,
                                'updater_id' => $authUserId,
                                'effective_date' => $date,
                                'manager_id' => $transfer->existing_employee_new_manager_id,
                                'position_id' => @$organizationHistory->position_id ? $organizationHistory->position_id : $userEmployeeId->position_id,
                                'sub_position_id' => @$organizationHistory->sub_position_id ? $organizationHistory->sub_position_id : $userEmployeeId->sub_position_id,
                            ]);
                        }

                        $leadData = Lead::where('recruiter_id', $userId)->pluck('id')->toArray();
                        if (count($leadData) != 0) {
                            Lead::whereIn('id', $leadData)->update(['reporting_manager_id' => $transfer->existing_employee_new_manager_id]);
                        }
                    }
                } elseif ($organization) {
                    if ($organization->existing_employee_new_manager_id) {
                        $userEmployeeIds = User::where('manager_id', $userId)->get();
                        foreach ($userEmployeeIds as $userEmployeeId) {
                            $organizationHistory = UserOrganizationHistory::where('user_id', $userEmployeeId->id)->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                            UserManagerHistory::updateOrCreate([
                                'user_id' => $userEmployeeId->id,
                                'effective_date' => $date,
                            ], [
                                'user_id' => $userEmployeeId->id,
                                'updater_id' => $authUserId,
                                'effective_date' => $date,
                                'manager_id' => $organization->existing_employee_new_manager_id,
                                'position_id' => @$organizationHistory->position_id ? $organizationHistory->position_id : $userEmployeeId->position_id,
                                'sub_position_id' => @$organizationHistory->sub_position_id ? $organizationHistory->sub_position_id : $userEmployeeId->sub_position_id,
                            ]);
                        }

                        $leadData = Lead::where('recruiter_id', $userId)->pluck('id')->toArray();
                        if (count($leadData) != 0) {
                            Lead::whereIn('id', $leadData)->update(['reporting_manager_id' => $organization->existing_employee_new_manager_id]);
                        }
                    }
                }

                $terminated = UserTerminateHistory::where('user_id', $userId)->where('terminate_effective_date', '<=', $date)->orderBy('terminate_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                $wasTerminated = $user->terminate == 1;
                $randomMobileNo = null;
                
                if ($terminated) {
                    $user->terminate = $terminated->is_terminate;
                    $user->disable_login = 1;

                    // set office_id to null for terminated user
                    $user->office_id = null;

                    // Replace mobile number with random generated number when user is newly terminated
                    // This ensures the mobile number becomes available for new users
                    // Only replace if: user is being terminated, wasn't terminated before, and mobile number is not empty/null
                    if ($terminated->is_terminate == 1 && !$wasTerminated && !empty($user->mobile_no) && $user->mobile_no !== null) {
                        $randomMobileNo = $this->generateUniqueRandomMobileNumber();
                        $user->mobile_no = $randomMobileNo;
                    }

                    $leadId = null;
                    $onboardingEmployees = OnboardingEmployees::where('user_id', $userId)->first();
                    if ($onboardingEmployees) {
                        $leadId = $onboardingEmployees->id;
                        
                        // Also update mobile number in onboarding_employees table when user is newly terminated
                        // Ensure consistency: if user mobile was replaced, also replace onboarding_employees mobile
                        // (even if onboarding_employees mobile was empty/null, to maintain consistency)
                        if ($terminated->is_terminate == 1 && !$wasTerminated && $randomMobileNo !== null) {
                            // User mobile was replaced, ensure onboarding_employees is also updated with same number
                            $onboardingEmployees->mobile_no = $randomMobileNo;
                            $onboardingEmployees->save();
                        }
                    }
                    Lead::where('id', $leadId)->delete();
                } else {
                    $user->terminate = 0;
                }

                $dismissed = UserDismissHistory::where('user_id', $userId)->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($dismissed) {
                    $user->dismiss = $dismissed->dismiss;
                    // $user->disable_login = $dismissed->dismiss ? 1 : 0;
                    $user->tokens()->delete();
                } else {
                    $user->dismiss = 0;
                    $user->disable_login = 0;
                }

                // Find the most recent contract for this user
                $contractEnded = UserAgreementHistory::where('user_id', $userId)
                    ->whereNotNull('period_of_agreement')
                    ->orderBy('period_of_agreement', 'DESC')
                    ->orderBy('id', 'DESC')
                    ->first();

                if ($contractEnded && $contractEnded->end_date && Carbon::parse($contractEnded->end_date) <= Carbon::now()) {
                    $user->contract_ended = 1;
                    $user->rehire = 1;
                }
                $user->save();
                DB::commit();
            } catch (\Exception $e) {
                $errors[$userId][] = $e->getMessage().' Line No. :- '.$e->getLine();
                DB::rollBack();
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Generate a unique random 10-digit mobile number
     * Ensures the number doesn't exist in users, onboarding_employees, or leads tables
     * Uses microtime-based generation to prevent race conditions in concurrent scenarios
     *
     * @return string
     */
    private function generateUniqueRandomMobileNumber(): string
    {
        $maxAttempts = 10; // Reduced attempts since we use microtime for better uniqueness
        $attempts = 0;

        do {
            // Generate a random 10-digit mobile number
            // Starting with 1-9 to avoid leading zeros, then 9 more random digits
            $randomMobileNo = (string) mt_rand(1000000000, 9999999999);
            $attempts++;

            // Check if this mobile number already exists in any of the tables
            // Note: Transaction isolation prevents seeing uncommitted changes from other transactions
            $existsInUsers = User::where('mobile_no', $randomMobileNo)->exists();
            $existsInOnboarding = OnboardingEmployees::where('mobile_no', $randomMobileNo)->exists();
            $existsInLeads = Lead::where('mobile_no', $randomMobileNo)->exists();

            if (!$existsInUsers && !$existsInOnboarding && !$existsInLeads) {
                return $randomMobileNo;
            }
        } while ($attempts < $maxAttempts);

        // Fallback: Use microtime-based generation to ensure absolute uniqueness
        // This prevents race conditions even in high-concurrency scenarios
        // Format: Random prefix (6 digits) + microtime suffix (4 digits) = 10 digits total
        $microtime = str_replace('.', '', (string) microtime(true));
        $microtimeSuffix = substr($microtime, -4);
        $prefix = mt_rand(100000, 999999);
        $fallbackMobileNo = $prefix . $microtimeSuffix;
        
        // Double-check uniqueness of fallback (should be unique due to microtime, but verify)
        $existsInUsers = User::where('mobile_no', $fallbackMobileNo)->exists();
        $existsInOnboarding = OnboardingEmployees::where('mobile_no', $fallbackMobileNo)->exists();
        $existsInLeads = Lead::where('mobile_no', $fallbackMobileNo)->exists();
        
        if (!$existsInUsers && !$existsInOnboarding && !$existsInLeads) {
            return $fallbackMobileNo;
        }
        
        // Last resort: use microtime + random suffix to guarantee uniqueness
        // This ensures uniqueness even if microtime suffix collides (extremely rare)
        $microtimeFull = str_replace('.', '', (string) microtime(true));
        $randomSuffix = mt_rand(1000, 9999);
        $lastResort = substr($microtimeFull . $randomSuffix, -10);
        
        // Ensure exactly 10 digits (should always be 10, but pad for safety)
        return str_pad($lastResort, 10, '0', STR_PAD_LEFT);
    }

}
