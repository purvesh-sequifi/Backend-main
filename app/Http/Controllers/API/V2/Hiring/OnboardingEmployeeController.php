<?php

namespace App\Http\Controllers\API\V2\Hiring;

use App\Core\Traits\EvereeTrait;
use App\Core\Traits\FieldRoutesTrait;
use App\Core\Traits\HubspotTrait;
use App\Core\Traits\JobNimbusTrait;
use App\Http\Controllers\Controller;
use App\Jobs\EmploymentPackage\ApplyHistoryOnUsersV2Job;
use App\Models\AdditionalLocations;
use App\Models\AdditionalRecruiters;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\Crms;
use App\Models\CrmSetting;
use App\Models\Documents;
use App\Models\DocumentSigner;
use App\Models\DomainSetting;
use App\Models\EmployeeIdSetting;
use App\Models\EmployeeOnboardingDeduction;
use App\Models\EventCalendar;
use App\Models\GroupPermissions;
use App\Models\Integration;
use App\Models\InterigationTransactionLog;
use App\Models\Lead;
use App\Models\Locations;
use App\Models\ManagementTeam;
use App\Models\ManagementTeamMember;
use App\Models\NewSequiDocsDocument;
use App\Models\NewSequiDocsSignatureRequestLog;
use App\Models\NewSequiDocsTemplate;
use App\Models\NewSequiDocsTemplatePermission;
use App\Models\Notification;
use App\Models\OnboardingAdditionalEmails;
use App\Models\OnboardingCommissionTiersRange;
use App\Models\OnboardingDirectOverrideTiersRange;
use App\Models\OnboardingEmployeeAdditionalOverride;
use App\Models\OnboardingEmployeeLocation;
use App\Models\OnboardingEmployeeLocations;
use App\Models\OnboardingEmployeeOverride;
use App\Models\OnboardingEmployeeRedline;
use App\Models\OnboardingEmployees;
use App\Models\OnboardingEmployeeUpfront;
use App\Models\OnboardingEmployeeWages;
use App\Models\OnboardingEmployeeWithheld;
use App\Models\OnboardingIndirectOverrideTiersRange;
use App\Models\OnboardingOfficeOverrideTiersRange;
use App\Models\OnboardingOverrideOfficeTiersRange;
use App\Models\OnboardingUpfrontsTiersRange;
use App\Models\OnboardingUserRedline;
use App\Models\PositionCommissionDeduction;
use App\Models\PositionProduct;
use App\Models\PositionReconciliations;
use App\Models\Positions;
use App\Models\SClearanceConfiguration;
use App\Models\SClearanceTurnScreeningRequestList;
use App\Models\SentOfferLetter;
use App\Models\SequiDocsEmailSettings;
use App\Models\User;
use App\Models\UserAdditionalOfficeOverrideHistory;
use App\Models\UserAdditionalOfficeOverrideHistoryTiersRange;
use App\Models\UserAgreementHistory;
use App\Models\UserCommissionHistory;
use App\Models\UserCommissionHistoryTiersRange;
use App\Models\UserDeduction;
use App\Models\UserDeductionHistory;
use App\Models\UserDepartmentHistory;
use App\Models\UserDirectOverrideHistoryTiersRange;
use App\Models\UserIndirectOverrideHistoryTiersRange;
use App\Models\UserIsManagerHistory;
use App\Models\UserManagerHistory;
use App\Models\UserOfficeOverrideHistoryTiersRange;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserRedlines;
use App\Models\UsersAdditionalEmail;
use App\Models\UserTransferHistory;
use App\Models\UserUpfrontHistory;
use App\Models\UserUpfrontHistoryTiersRange;
use App\Models\UserWagesHistory;
use App\Models\UserWithheldHistory;
use App\Models\W2UserTransferHistory;
use App\Helpers\CustomSalesFieldHelper;
use App\Traits\EmailNotificationTrait;
use App\Traits\HighLevelTrait;
use App\Traits\IntegrationTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Laravel\Pennant\Feature;
use Pdf;

class OnboardingEmployeeController extends Controller
{
    use EmailNotificationTrait, EvereeTrait, HighLevelTrait, HubspotTrait,IntegrationTrait,JobNimbusTrait;

    protected $url;

    protected $disk;

    protected $signServerURL;

    protected $signServerWorker;

    protected $signServerWorkerPass;

    protected $s3_bucket_public_url;

    protected $stored_bucket = 'public';

    protected $signServerWorkerUserName;

    protected $companySettingtiers;

    use FieldRoutesTrait;

    public function __construct(UrlGenerator $url)
    {
        $this->url = $url;
        $this->companySettingtiers = CompanySetting::where('type', 'tier')->first();
    }

    public function onBoardingEmployeeListing(Request $request)
    {
        if (isset($request->perpage) && $request->perpage != '') {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }
        $status_id_filter = '';
        $other_status_filter = isset($request->other_status_filter) ? $request->other_status_filter : '';

        $id = Auth()->user()->id;
        $user = User::select('id', 'sub_position_id', 'group_id')->where('id', $id)->firstOrFail();
        $groupId = $user->group_id;
        $subPosition = $user->sub_position_id;
        $getPermission = $this->getPermission($groupId);

        $userId = User::where('dismiss', '1')->orWhere('terminate', '1')->orWhere('contract_ended', '1')->pluck('id');
        $user = OnboardingEmployees::with([
            'departmentDetail',
            'positionDetail',
            'managerDetail',
            'statusDetail',
            'state',
            'teamsDetail',
            'subpositionDetail',
            'office',
            'OnboardingAdditionalEmails',
        ])->where(function ($query) {
            $query->whereHas('mainUserData', function ($subQuery) {
                $subQuery->where('terminate', 0);
            })->orWhereNull('user_id');
        })
            ->where(function ($query) use ($userId) {
                $query->where(function ($q) use ($userId) {
                    $q->whereIn('user_id', $userId)
                        ->whereIn('status_id', [4, 1, 22, 23, 24, 21]); // Include only if status_id is 4 or 1
                })
                    ->orWhere(function ($q) use ($userId) {
                        $q->whereNotIn('user_id', $userId)
                            ->orWhereNull('user_id'); // Include all others
                    });
            });

        if ($request->has('filter') && ! empty($request->input('filter'))) {
            $user->where(function ($query) use ($request) {
                $query->where('first_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('last_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$request->input('filter').'%'])
                    ->orWhere('email', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('mobile_no', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhereHas('OnboardingAdditionalEmails', function ($query) use ($request) {
                        $query->where('email', 'like', '%'.$request->input('filter').'%');
                    });
            });
        }

        if ($request->has('position_filter') && ! empty($request->input('position_filter'))) {
            $user->where(function ($query) use ($request) {
                $query->where('sub_position_id', $request->input('position_filter'));
            });
        }

        if ($request->has('manager_filter') && ! empty($request->input('manager_filter'))) {
            $user->where(function ($query) use ($request) {
                $query->where('manager_id', $request->input('manager_filter'));
            });
        }
        if ($request->has('department_id') && ! empty($request->input('department_id'))) {
            $user->where(function ($query) use ($request) {
                $query->where('department_id', $request->input('department_id'));
            });
        }
        if ($request->has('updated_at') && ! empty($request->updated_at)) {
            $user->where(function ($query) use ($request) {
                $query->wheredate('updated_at', $request->input('updated_at'));
            });
        }

        if ($request->has('office_id') && ! empty($request->input('office_id'))) {
            if ($request->input('office_id') !== 'all') {
                $data = $user->where('office_id', $request->input('office_id'));
            }
        }

        $hire_now_filter = '';
        $offer_letter_accepted_filter = '';
        if ($request->has('status_filter') && ! empty($request->input('status_filter')) && $other_status_filter == '') {
            $status_id_filter = $request->query('status_filter');
            if ($status_id_filter == 13) {
                $user->where(function ($query) {
                    $query->where('status_id', 1);
                });
                $offer_letter_accepted_filter = 1;
            } elseif ($status_id_filter == 1) {
                $user->where(function ($query) {
                    $query->where('status_id', 1);
                });
                $hire_now_filter = 1;
            } else {
                $user->where(function ($query) use ($status_id_filter) {
                    $query->where('status_id', $status_id_filter);
                });
            }
        }
        $user->with('OnboardingEmployeesDocuments')
            ->orderBy('id', 'DESC')->where('status_id', '!=', 14);

        // Get regular filtered data (existing logic unchanged)
        $regular_data = $user->get();

        // Separately get new contract records that might have been filtered out by status_id != 14
        // Apply the same base conditions but allow any status for new contracts
        $newContractQuery = OnboardingEmployees::with([
            'departmentDetail',
            'positionDetail',
            'managerDetail',
            'statusDetail',
            'state',
            'teamsDetail',
            'subpositionDetail',
            'office',
            'OnboardingAdditionalEmails',
            'OnboardingEmployeesDocuments',
        ])->where('is_new_contract', 1) // Only new contracts
            ->where(function ($query) {
                $query->whereHas('mainUserData', function ($subQuery) {
                    $subQuery->where('terminate', 0);
                })->orWhereNull('user_id');
            });

        // Apply the same user filtering logic
        $newContractQuery->where(function ($query) use ($userId) {
            $query->where(function ($q) use ($userId) {
                $q->whereIn('user_id', $userId)
                    ->whereIn('status_id', [4, 1, 22, 23, 24, 21]); // Include only if status_id is 4 or 1
            })
                ->orWhere(function ($q) use ($userId) {
                    $q->whereNotIn('user_id', $userId)
                        ->orWhereNull('user_id'); // Include all others
                });
        });

        // Apply additional filters if they exist
        if (isset($request) && $request->has('filter') && ! empty($request->input('filter'))) {
            $newContractQuery->where(function ($query) use ($request) {
                $query->where('first_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('last_name', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$request->input('filter').'%'])
                    ->orWhere('email', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhere('mobile_no', 'LIKE', '%'.$request->input('filter').'%')
                    ->orWhereHas('OnboardingAdditionalEmails', function ($query) use ($request) {
                        $query->where('email', 'like', '%'.$request->input('filter').'%');
                    });
            });
        }

        // Apply status_filter to new contract query as well
        if ($request->has('status_filter') && ! empty($request->input('status_filter')) && $other_status_filter == '') {
            $status_id_filter = $request->query('status_filter');
            if ($status_id_filter == 13) {
                $newContractQuery->where(function ($query) {
                    $query->where('status_id', 1);
                });
            } elseif ($status_id_filter == 1) {
                $newContractQuery->where(function ($query) {
                    $query->where('status_id', 1);
                });
            } else {
                $newContractQuery->where(function ($query) use ($status_id_filter) {
                    $query->where('status_id', $status_id_filter);
                });
            }
        }

        // Apply position_filter to new contract query
        if ($request->has('position_filter') && ! empty($request->input('position_filter'))) {
            $newContractQuery->where(function ($query) use ($request) {
                $query->where('sub_position_id', $request->input('position_filter'));
            });
        }

        // Apply manager_filter to new contract query
        if ($request->has('manager_filter') && ! empty($request->input('manager_filter'))) {
            $newContractQuery->where(function ($query) use ($request) {
                $query->where('manager_id', $request->input('manager_filter'));
            });
        }

        // Apply department_id filter to new contract query
        if ($request->has('department_id') && ! empty($request->input('department_id'))) {
            $newContractQuery->where(function ($query) use ($request) {
                $query->where('department_id', $request->input('department_id'));
            });
        }

        // Apply updated_at filter to new contract query
        if ($request->has('updated_at') && ! empty($request->updated_at)) {
            $newContractQuery->where(function ($query) use ($request) {
                $query->wheredate('updated_at', $request->input('updated_at'));
            });
        }

        // Apply office_id filter to new contract query
        if ($request->has('office_id') && ! empty($request->input('office_id'))) {
            if ($request->input('office_id') !== 'all') {
                $newContractQuery->where('office_id', $request->input('office_id'));
            }
        }

        $additional_new_contracts = $newContractQuery->orderBy('id', 'DESC')->get();

        // Merge regular data with new contract data
        $all_data = $regular_data->concat($additional_new_contracts)->unique('id')->sortByDesc('id');

        // Separate new contracts and regular onboarding
        $newContracts = $all_data->where('is_new_contract', 1);
        // Keep ALL regular onboarding (is_new_contract = 0 OR NULL - covers existing records)
        $regularOnboarding = $all_data->where(function ($item) {
            return $item->is_new_contract === 0 || $item->is_new_contract === null;
        });

        // For new contracts (is_new_contract = 1) with same user_id, keep only the one with upcoming start date
        $filteredNewContracts = collect();
        if ($newContracts->isNotEmpty()) {
            $groupedByUser = $newContracts->groupBy('user_id');

            foreach ($groupedByUser as $userId => $userContracts) {
                if ($userContracts->count() > 1) {
                    // Multiple new contracts for same user - get the one with upcoming start date
                    $upcomingContract = $userContracts
                        ->whereNotNull('period_of_agreement_start_date')
                        ->where('period_of_agreement_start_date', '>=', date('Y-m-d'))
                        ->sortBy('period_of_agreement_start_date')
                        ->first();

                    // If no upcoming contract found, get the latest one
                    if (! $upcomingContract) {
                        $upcomingContract = $userContracts
                            ->sortByDesc('period_of_agreement_start_date')
                            ->first();
                    }

                    if ($upcomingContract) {
                        $filteredNewContracts->push($upcomingContract);
                    }
                } else {
                    // Only one new contract for this user - include it
                    $filteredNewContracts->push($userContracts->first());
                }
            }
        }

        // Merge: Keep ALL regular onboarding + filtered new contracts
        // This allows same user to appear twice: once for regular onboarding, once for new contract
        $final_filtered_data = $regularOnboarding->concat($filteredNewContracts)->sortByDesc('id');

        // Apply pagination if needed
        if ($hire_now_filter == 1 || $offer_letter_accepted_filter == 1) {
            $user_data = $final_filtered_data->values();
        } else {
            // Convert to paginated result
            $total = $final_filtered_data->count();
            $perPage = $request->perpage ?? 10;
            $currentPage = $request->page ?? 1;
            $offset = ($currentPage - 1) * $perPage;

            $paginatedData = $final_filtered_data->slice($offset, $perPage)->values();

            // Create pagination structure similar to Laravel's paginate
            $user_data = new \Illuminate\Pagination\LengthAwarePaginator(
                $paginatedData,
                $total,
                $perPage,
                $currentPage,
                [
                    'path' => $request->url(),
                    'pageName' => 'page',
                ]
            );
        }

        $final_data = [];
        foreach ($user_data as $user_row) {
            $specialReviewStatus = false;
            if (in_array(config('app.domain_name'), ['hawx', 'hawxw2', 'milestone'])) {
                $empIdStatus = EmployeeIdSetting::orderBy('id', 'asc')->first();
                $employeeUpfront = OnboardingEmployeeUpfront::where(['user_id' => $user_row->id, 'upfront_sale_type' => 'per sale'])->sum('upfront_pay_amount');
                // $customField = NewSequiDocsDocument::where(['user_id'=> $user_row->id, 'category_id'=> 101, 'user_id_from'=> 'onboarding_employees'])->first();
                // $customFieldVal = $customField->smart_text_template_fied_keyval ?? null;
                $customFieldVal = $user_row->custom_fields ?? null;
                $hiringBonus = $user_row->hiring_bonus_amount ?? 0;
                $specialApprovalStatus = $empIdStatus->special_approval_status ?? 0;
                if ($specialApprovalStatus == 1 && ($employeeUpfront > 100 || $hiringBonus > 10000 || ! empty($customFieldVal))) {
                    $specialReviewStatus = true;
                } else {
                    $specialReviewStatus = false;
                }
            }

            $onboarding_employees_documents = [];
            // Logic for all docs sign or not
            $onboarding_employees_documents = $user_row->OnboardingEmployeesDocuments;
            $onboarding_employees_document_status = OnboardingEmployees::onboarding_employees_document_status($onboarding_employees_documents);
            $other_doc_status = $onboarding_employees_document_status['other_doc_status'];
            $is_all_doc_sign = $onboarding_employees_document_status['is_all_doc_sign'];

            // Hire button show hide as per new tables of sequidoc
            $onboarding_employees_new_documents = $user_row->newOnboardingEmployeesDocuments;
            $onboarding_employees_new_document_status = OnboardingEmployees::onboarding_employees_new_document_status($onboarding_employees_new_documents);
            $is_all_new_doc_sign = $onboarding_employees_new_document_status['is_all_new_doc_sign'];

            $data = [
                'id' => $user_row->id,
                'is_all_doc_sign' => $is_all_doc_sign,
                'is_all_new_doc_sign' => $is_all_new_doc_sign,
                'other_doc_status' => $other_doc_status,
                'onboarding_employees_documents' => $onboarding_employees_documents,
                'onboarding_employees_new_documents' => $onboarding_employees_new_documents,
                'first_name' => isset($user_row->first_name) ? $user_row->first_name : null,
                'last_name' => isset($user_row->last_name) ? $user_row->last_name : null,
                'mobile_no' => $user_row->mobile_no,
                'email' => $user_row->email,
                'state_id' => $user_row->state_id,
                'state_name' => isset($user_row->state->name) ? $user_row->state->name : null,
                'department_id' => $user_row->department_id,
                'team_id' => isset($user_row->teamsDetail->id) ? $user_row->teamsDetail->id : null,
                'team_name' => isset($user_row->teamsDetail->team_name) ? $user_row->teamsDetail->team_name : null,
                'department_name' => isset($user_row->departmentDetail->name) ? $user_row->departmentDetail->name : null,
                'manager_id' => $user_row->manager_id,
                'manager_name' => isset($user_row->managerDetail->id) ? $user_row->managerDetail->name : null,
                'office_id' => isset($user_row->office_id) ? $user_row->office_id : null,
                'office_name' => isset($user_row->office->office_name) ? $user_row->office->office_name : null,
                'status_id' => $user_row->status_id,
                'hiring_type' => $user_row->hiring_type,
                'status_name' => isset($user_row->statusDetail->status) ? $user_row->statusDetail->status : null,
                'position_id' => $user_row->position_id,
                'position_name' => isset($user_row->positionDetail->position_name) ? $user_row->positionDetail->position_name : null,
                'sub_position_id' => isset($user_row->sub_position_id) ? $user_row->sub_position_id : null,
                'sub_position_name' => isset($user_row->subpositionDetail->position_name) ? $user_row->subpositionDetail->position_name : null,
                'progress' => '1/18',
                'onboardProcess' => ! empty($user_row->mainUserData->onboardProcess) ? $user_row->mainUserData->onboardProcess : 0,
                'last_update' => Carbon::parse($user_row->updated_at)->format('m/d/Y'),
                'last_update_ts' => strtotime($user_row->updated_at),
                'work_email' => $user_row->OnboardingAdditionalEmails,
                'is_background_verificaton' => $user_row->is_background_verificaton,
                'background_verification_status' => '',
                'background_verification_approval_required' => 0,
                'special_review_status' => $specialReviewStatus,
                'rehire' => ! empty($user_row->mainUserData->rehire) ? $user_row->mainUserData->rehire : 0,
                'is_new_contract' => $user_row->is_new_contract ?? 0, // 0 = Regular onboarding, 1 = New contract/rehire
            ];

            $push_data = true;
            if ($user_row->is_background_verificaton == 1) {
                $position_id = $user_row->position_id;
                $user_id = $user_row->id;
                $user_type = 'Onboarding';
                $configurationDetails = SClearanceConfiguration::where(['position_id' => $position_id, 'hiring_status' => 1])->first();
                if (empty($configurationDetails)) { // get default
                    $configurationDetails = SClearanceConfiguration::where(['id' => 1])->first();
                }

                $reportData = SClearanceTurnScreeningRequestList::where(['user_type_id' => $user_id, 'user_type' => $user_type])
                    ->first();
                if (! empty($reportData)) {
                    $data['turn_id'] = $reportData->turn_id;
                    $data['worker_id'] = $reportData->worker_id;
                    $data['is_report_generated'] = $reportData->is_report_generated;
                    $data['background_verification_status'] = $reportData->status;
                    $data['background_verification_approval_required'] = $configurationDetails->is_approval_required ?? 0;
                    $data['approved_declined_by'] = $reportData->approved_declined_by;
                }
            }

            if ($hire_now_filter == 1) {
                $push_data = false;
                /*
                S Clearace
                is_background_verificaton = 1

                background_verification_status = "Approval Pending" && background_verification_approval_required = 1
                Show View Report button

                background_verification_status = "Approval Pending" && background_verification_approval_required = 0
                Show Hire Now Button

                background_verification_status = "Approved" && background_verification_approval_required = 1
                Show Hire Now Button

                background_verification_status = "Approved" && background_verification_approval_required = 0
                Show Hire Now Button

                is_background_verificaton = 0
                Show Hire Now Button
                */

                if ($data['is_background_verificaton'] == 1) {

                    if ($data['background_verification_status'] == 'approved' || $data['background_verification_status'] == 'pending') {
                        if ($data['background_verification_approval_required'] == 0) {
                            $push_data = true;
                        } elseif ($data['background_verification_approval_required'] == 1 && $data['approved_declined_by'] != null) {
                            $push_data = true;
                        } else {
                            $push_data = false;
                        }
                    }

                    if (! $is_all_new_doc_sign) {
                        $push_data = false;
                    } else {
                        $push_data = true;
                    }

                    if ($is_all_new_doc_sign && ($other_doc_status['backgroundVerification'] == '0' || $other_doc_status['w9'] == '0')) {
                        $push_data = false;
                    }
                } else {
                    if ($is_all_new_doc_sign) {
                        $push_data = true;
                    }

                    if ($is_all_new_doc_sign && ($other_doc_status['backgroundVerification'] == '0' || $other_doc_status['w9'] == '0')) {
                        $push_data = false;
                    }
                }
            } elseif ($offer_letter_accepted_filter == 1) {
                $push_data = false;
                if (! $is_all_new_doc_sign) {
                    $push_data = true;
                }
                if ($is_all_new_doc_sign) {
                    // filter send now
                    // for send now
                    // either other doc not send
                    // or send and signed
                    // for filter $push_data = false;
                    if (($other_doc_status['backgroundVerification'] == 1 || $other_doc_status['backgroundVerification'] == 2) && ($other_doc_status['w9'] == 1 || $other_doc_status['w9'] == 2)) {
                        $push_data = false;
                    } elseif (($other_doc_status['backgroundVerification'] == 0 || $other_doc_status['backgroundVerification'] == 2) || ($other_doc_status['w9'] == 0 || $other_doc_status['w9'] == 2)) {
                        $push_data = true;
                    }
                }
            }

            if ($push_data) {
                $final_data[] = $data;
            }
        }

        if ($hire_now_filter == 1 || $offer_letter_accepted_filter == 1) {
            $data = paginate($final_data, $perpage);
        } else {
            $data = $user_data->toArray();
            $data['data'] = $final_data;
        }

        return response()->json([
            'ApiName' => 'onboarding_employee_list ',
            'status' => true,
            'message' => 'Successfully.',
            'offer_letter_accepted_filter' => $offer_letter_accepted_filter,
            'hire_now_filter' => $hire_now_filter,
            'data' => $data,
        ]);
    }

    public function getPermission($groupId)
    {
        // $roledata = GroupPermissions::with('permissions')->where(['group_id'=> $groupId, 'role_id'=> 2, 'group_policies_id'=> 14, 'policies_tabs_id'=> 71])->get();
        $roledata = GroupPermissions::with('permissions')->where(['group_id' => $groupId, 'role_id' => 2, 'group_policies_id' => 12, 'policies_tabs_id' => 27])->get();
        $permissiondata = [];
        foreach ($roledata as $val) {
            if (! empty($val->permissions)) {
                $permissiondata[] = $val->permissions->name ?? '';
            }
        }

        return $permissiondata;
    }

    public function getOnboardingEmployee(Request $request, $id)
    {
        $product_id = $request->product_id ?? '';
        $user = OnboardingEmployees::with([
            'departmentDetail',
            'positionDetail',
            'state',
            'city',
            'managerDetail',
            'statusDetail',
            'recruiter',
            'additionalDetail',
            'subpositionDetail',
            'office',
            'teamsDetail',
            'OnboardingAdditionalEmails',
            'wage',
            'hiredby',
        ])->where('id', $id)->first();
        // echo "<pre>";print_r($user);die();
        if ($user) {

            $sentOfferLetter = SentOfferLetter::where('onboarding_employee_id', $user->id)->first();

            $additionalData = User::select('id', 'first_name', 'last_name', 'recruiter_id')
                ->where('id', $user->additional_recruiter_id1)
                ->orWhere('id', $user->additional_recruiter_id2)->get();

            $additional = $additionalData->map(function ($item) {
                return [
                    'id' => $item->id,
                    'recruiter_id' => $item->recruiter_id,
                    'full_name' => ($item->first_name ?? '').' '.($item->last_name ?? ''),
                    'recruiter_first_name' => $item->first_name,
                    'recruiter_last_name' => $item->last_name,
                    'system_per_kw_amount' => $item->system_per_kw_amount ?? null,
                ];
            });

            $additional_location = OnboardingEmployeeLocation::with(['state', 'city', 'office'])
                ->where('user_id', $id)->get();

            $additional_locations = $additional_location->map(function ($d) {
                return [
                    'id' => $d->id ?? 'NA',
                    'state_id' => $d->state_id ?? 'NA',
                    'state_name' => $d->state->name ?? 'NA',
                    'city_id' => $d->city_id ?? 'NA',
                    'city_name' => $d->city->name ?? 'NA',
                    'office_id' => $d->office_id ?? null,
                    'office_name' => $d->office->office_name ?? null,
                    'overrides_amount' => $d->overrides_amount ?? null,
                    'overrides_type' => $d->overrides_type ?? null,
                ];
            });

            $overrideresult = OnboardingEmployeeOverride::where('user_id', $id)->first();
            $user->override_effective_date = $overrideresult->override_effective_date ?? null;
            $user->direct_overrides_amount = $overrideresult->direct_overrides_amount ?? null;
            $user->direct_overrides_type = $overrideresult->direct_overrides_type ?? null;
            $user->direct_custom_sales_field_id = $overrideresult->direct_custom_sales_field_id ?? null;
            $user->indirect_overrides_amount = $overrideresult->indirect_overrides_amount ?? null;
            $user->indirect_overrides_type = $overrideresult->indirect_overrides_type ?? null;
            $user->indirect_custom_sales_field_id = $overrideresult->indirect_custom_sales_field_id ?? null;
            $user->office_overrides_amount = $overrideresult->office_overrides_amount ?? null;
            $user->office_overrides_type = $overrideresult->office_overrides_type ?? null;
            $user->office_custom_sales_field_id = $overrideresult->office_custom_sales_field_id ?? null;

            // Logic for all docs sign or not
            $onboarding_employees_documents = $user->OnboardingEmployeesDocuments;
            $onboarding_employees_document_status = OnboardingEmployees::onboarding_employees_document_status($onboarding_employees_documents);
            $other_doc_status = $onboarding_employees_document_status['other_doc_status'];
            $is_all_doc_sign = $onboarding_employees_document_status['is_all_doc_sign'];

            // Hire button show hide as per new tables of sequidoc
            $onboarding_employees_new_documents = $user->newOnboardingEmployeesDocuments;
            $onboarding_employees_new_document_status = OnboardingEmployees::onboarding_employees_new_document_status($onboarding_employees_new_documents);
            $is_all_new_doc_sign = $onboarding_employees_new_document_status['is_all_new_doc_sign'];
            $agreement = [
                'probation_period' => $user->probation_period,
                'hiring_bonus_amount' => $user->hiring_bonus_amount,
                'date_to_be_paid' => $user->date_to_be_paid,
                'period_of_agreement' => $user->period_of_agreement_start_date,
                'end_date' => $user->end_date,
                'offer_include_bonus' => $user->offer_include_bonus,
                'offer_expiry_date' => $user->offer_expiry_date,
                'is_background_verificaton' => $user->is_background_verificaton,
            ];
            $details = [
                'sex' => $user->sex,
                'dob' => dateToYMD($user->dob),
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'work_email' => $user->OnboardingAdditionalEmails,
                'mobile_no' => $user->mobile_no,
                'state_id' => $user->state_id,
                'state_name' => isset($user->state) ? $user->state->name : '',
                'city_id' => $user->city_id,
                'city_name' => isset($user->city) ? $user->city->name : '',
                'office_id' => isset($user->office_id) ? $user->office_id : null,
                'office' => isset($user->office) ? $user->office : null,
            ];
            $org_details = [
                'is_manager' => $user->is_manager,
                'is_manager_effective_date' => $user->is_manager_effective_date,
                'manager_name' => isset($user->managerDetail) ? $user->managerDetail->name : '',
                'position_id' => $user->position_id,
                'position_name' => isset($user->positionDetail) ? $user->positionDetail->position_name : '',
                'sub_position_id' => isset($user->sub_position_id) ? $user->sub_position_id : null,
                'sub_position_name' => isset($user->subpositionDetail->position_name) ? $user->subpositionDetail->position_name : null,
                'department_id' => $user->department_id,
                'department_name' => isset($user->departmentDetail) ? $user->departmentDetail->name : '',
                'manager_id' => $user->manager_id,
                'team_id' => $user->team_id,
                'team_name' => isset($user->teamsDetail->team_name) ? $user->teamsDetail->team_name : null,
                'recruiter_id' => $user->recruiter_id,
                'recruiter_name' => ($user->recruiter->first_name ?? '').' '.($user->recruiter->last_name ?? ''),
                'additional_recruter' => $additional,
                'additional_locations' => $additional_locations,
                'template_id' => isset($sentOfferLetter->template_id) ? $sentOfferLetter->template_id : null,
                'experience_level' => isset($user->experience_level) ? $user->experience_level : null,
            ];
            $wages = [
                'pay_type' => isset($user->pay_type) ? $user->pay_type : '',
                'pay_rate' => $user->pay_rate,
                'expected_weekly_hours' => $user->expected_weekly_hours,
                'overtime_rate' => $user->overtime_rate,
                'pay_rate_type' => $user->pay_rate_type,
                'worker_type' => $user->worker_type,
                'pto_hours' => $user->pto_hours,
                'unused_pto_expires' => $user->unused_pto_expires,
            ];

            $user_redlinedata = OnboardingUserRedline::where('user_id', $id)->groupBy('core_position_id')->get();

            $employee_compensation_result = [];
            if ($user_redlinedata && $product_id != '') {
                foreach ($user_redlinedata as $index => $user_redlined) {
                    $emp_result = []; // Initialize emp_result for each user redline

                    $core_position_id = ! empty($user_redlined['core_position_id']) ? $user_redlined['core_position_id'] : '0';
                    $emp_result['core_position_id'] = $core_position_id;

                    // Retrieve readline data
                    $readline = $this->getReadline($user->id, $product_id, $core_position_id);
                    $emp_result['redline'] = ! empty($readline && isset($readline[0])) ? $readline[0] : null;

                    // Retrieve commission data
                    $comm = $this->getCommission($user->id, $product_id, $core_position_id);
                    $emp_result['commission'] = ! empty($comm[0]['commission_data']) ? $comm[0]['commission_data'][0] : null;

                    // Retrieve upfront data
                    $upfronts = $this->getUpFronts($user->id, $product_id, $core_position_id);
                    $emp_result['upfront'] = ! empty($upfronts) && ! empty($upfronts[0]['data']) ? $upfronts[0]['data'][0] : null;

                    $emp_result['core_position_id'] = $emp_result['core_position_id'] == 0 ? null : $emp_result['core_position_id'];
                    // Append the result for this user redline
                    $employee_compensation_result[] = $emp_result;
                }
            }
            $overrides = $this->getOverrides($id, $product_id);
            if ($product_id != '') {
                $overrides = $overrides[0] ?? [];
            }

            $specialReviewStatus = false;
            if (in_array(config('app.domain_name'), ['hawx', 'hawxw2', 'milestone'])) {
                $empIdStatus = EmployeeIdSetting::orderBy('id', 'asc')->first();
                $employeeUpfront = OnboardingEmployeeUpfront::where(['user_id' => $user->id, 'upfront_sale_type' => 'per sale'])->sum('upfront_pay_amount');
                // $customField = NewSequiDocsDocument::where(['user_id'=> $user->id, 'category_id'=> 101, 'user_id_from'=> 'onboarding_employees'])->first();
                // $customFieldVal = $customField->smart_text_template_fied_keyval ?? null;
                $customFieldVal = $user->custom_fields ?? null;
                $hiringBonus = $user->hiring_bonus_amount ?? 0;
                $specialApprovalStatus = $empIdStatus->special_approval_status ?? 0;
                if ($specialApprovalStatus == 1 && ($employeeUpfront > 100 || $hiringBonus > 10000 || ! empty($customFieldVal))) {
                    $specialReviewStatus = true;
                } else {
                    $specialReviewStatus = false;
                }
            }

            $data = [
                'id' => $user->id,
                'user_id' => $user->user_id ?? null,
                'status_id' => isset($user->status_id) ? $user->status_id : null,
                'status_name' => isset($user->statusDetail->status) ? $user->statusDetail->status : null,
                'main_role' => $user?->subpositionDetail?->is_selfgen,
                'is_all_doc_sign' => $is_all_doc_sign,
                'other_doc_status' => $other_doc_status,
                'onboarding_employees_documents' => $onboarding_employees_documents,
                'onboarding_employees_new_documents' => $onboarding_employees_new_documents,
                'details' => $details,
                'organization' => $org_details,
                'wages' => $wages,
                'employee_commision' => $this->getCommission($id, $product_id),
                'employee_redline' => $this->getReadline($id),
                'employee_withheld' => $this->getWithheld($id, $product_id),
                'employee_upfronts' => $this->getUpFronts($id, $product_id),
                'employee_overrides' => $overrides,
                'employee_agreement' => $agreement,
                'employee_compensation' => $employee_compensation_result,
                'products' => OnboardingEmployees::getProductIds($id),
                'hiring_signature' => $user->hiring_signature,
                'hiring_by_uid' => $user->hired_by_uid,
                'hiring_by' => isset($user->hiredby->first_name) ? $user->hiredby->first_name.' '.$user->hiredby->last_name : '',
                'special_review_status' => $specialReviewStatus,
                'custom_fields' => $user->custom_fields ?? null,
                'is_new_contract' => $user->is_new_contract ?? 0, // 0 = Regular onboarding, 1 = New contract/rehire
                // 'custom_fields' => isset($user->custom_fields) ? json_decode($user->custom_fields) : null,
                // Other fields can be added here
                'employee_admin_only_fields' => $user->employee_admin_only_fields ?? null,
            ];

            return response()->json([
                'ApiName' => 'update-onboarding-employee',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'show-onboarding-employee',
                'status' => false,
                'message' => 'Invalid user id',
            ], 400);
        }
    }

    /**
     * Parse custom field type from frontend format (custom_field_X) to database format
     * 
     * @param string|null $type The commission/upfront/override type
     * @param int|null $customFieldId The custom sales field ID (if already parsed)
     * @return array ['type' => string, 'custom_sales_field_id' => int|null]
     */
    private function parseCustomFieldType(?string $type, ?int $customFieldId = null): array
    {
        // If type starts with 'custom_field_', extract the ID
        if ($type && str_starts_with($type, 'custom_field_')) {
            $extractedId = (int) str_replace('custom_field_', '', $type);
            return [
                'type' => 'custom field',
                'custom_sales_field_id' => $extractedId,
            ];
        }

        // If type is 'custom field' and we have an ID, use it
        if ($type === 'custom field' && $customFieldId) {
            return [
                'type' => 'custom field',
                'custom_sales_field_id' => $customFieldId,
            ];
        }

        // Otherwise, return the type as-is with no custom field ID
        return [
            'type' => $type,
            'custom_sales_field_id' => null,
        ];
    }

    private function getCommission($id, $product_id = null, $core_position_id = null)
    {
        return OnboardingUserRedline::select([
            'id',
            'position_id',
            'self_gen_user',
            'product_id',
            'commission',
            'commission_type',
            'custom_sales_field_id',
            'core_position_id',
            'commission_effective_date',
            'updater_id',
            'tiers_id',
        ])
            ->where('user_id', $id)
            ->when($product_id, function ($query, $product_id) {
                // Filter by product_id if it's provided
                return $query->where('product_id', $product_id);
            })
            ->when($core_position_id !== null, function ($query) use ($core_position_id) {
                $query->where('core_position_id', $core_position_id == 0 ? null : $core_position_id);
            })
            ->get()
            ->map(function ($res) {
                $startDate = null;
                $commission = $this->getrange($res->id, 'commission');
                if ($commission && is_array($commission) && count($commission) != 0) {
                    $startDate = collect($commission)->first()?->tiersSchema->first()?->start_end_day;
                }

                return [
                    'product_id' => $res->product_id,
                    'self_gen_user' => $res->self_gen_user,
                    'position_id' => $res->position_id,
                    'core_position_id' => $res->core_position_id,
                    'updater_id' => $res->updater_id,
                    'commission' => $res->commission,
                    'commission_effective_date' => $res->commission_effective_date <= now() ? $res->commission_effective_date : null,
                    'commission_type' => $this->transformCustomFieldType($res->commission_type, $res->custom_sales_field_id),
                    'custom_sales_field_id' => $this->getCustomFieldIdForDisplay($res->commission_type, $res->custom_sales_field_id),
                    'commission_tiers_status' => @$res->tiers_id ? 1 : 0,
                    'tiers_id' => $res->tiers_id,
                    'start_end_day' => $startDate,
                    'tiers_range' => $commission,
                ];
            })
            ->groupBy('product_id')
            ->map(function ($items, $productId) {
                return [
                    'product_id' => $productId,
                    'commission_data' => $items,
                ];
            })
            ->values(); // Re-index the array numerically
    }

    private function getrange($id, $type)
    {
        if ($this->companySettingtiers?->status) {
            if ($type == 'commission') {
                return OnboardingCommissionTiersRange::with('tiersSchema')->where('onboarding_commission_id', $id)->get();
            } elseif ($type == 'upfront') {
                return OnboardingUpfrontsTiersRange::with('tiersSchema')->where('onboarding_upfront_id', $id)->get();
            } elseif ($type == 'direct') {
                return OnboardingDirectOverrideTiersRange::with('tiersSchema')->where('onboarding_direct_override_id', $id)->get();
            } elseif ($type == 'indirect') {
                return OnboardingIndirectOverrideTiersRange::with('tiersSchema')->where('onboarding_indirect_override_id', $id)->get();
            } elseif ($type == 'overrideoffice') {
                return OnboardingOverrideOfficeTiersRange::with('tiersSchema')->where('onboarding_override_office_id', $id)->get();
            } elseif ($type == 'office') {
                return OnboardingOfficeOverrideTiersRange::with('tiersSchema')->where('onboarding_office_override_id', $id)->get();
            }
        } else {
            return [];
        }
    }

    private function getWithheld($id, $product_id = null, $core_position_id = null)
    {
        return OnboardingEmployeeWithheld::select([
            'position_id',
            'product_id',
            'updater_id',
            'withheld_amount',
            'withheld_type',
            'withheld_effective_date',
        ])
            ->where('user_id', $id)
            ->when($product_id, function ($query, $product_id) {
                // Filter by product_id if it's provided
                return $query->where('product_id', $product_id);
            })
            ->when($core_position_id, function ($query, $core_position_id) {
                return $query->where('core_position_id', $core_position_id);
            })
            ->get()
            ->map(function ($res) {
                return [
                    'product_id' => $res->product_id,
                    'position_id' => $res->position_id,
                    'updater_id' => $res->updater_id,
                    'withheld_amount' => $res->withheld_amount,
                    'withheld_type' => $res->withheld_type,
                    'withheld_effective_date' => $res->withheld_effective_date <= now() ? $res->withheld_effective_date : null,

                ];
            })
            ->values(); // Re-index the array numerically
    }

    private function getReadline($id, $product_id = null, $core_position_id = null)
    {
        return OnboardingEmployeeRedline::select([
            'position_id',
            'self_gen_user',
            'core_position_id',
            'updater_id',
            'redline_amount_type',
            'redline',
            'redline_type',
            'redline_effective_date',
        ])
            ->where('user_id', $id)
            ->when($core_position_id !== null, function ($query) use ($core_position_id) {
                $query->where('core_position_id', $core_position_id == 0 ? null : $core_position_id);
            })
            ->get()
            ->map(function ($res) {
                return [
                    'self_gen_user' => $res->self_gen_user,
                    'position_id' => $res->position_id,
                    'core_position_id' => $res->core_position_id,
                    'updater_id' => $res->updater_id,
                    'redline' => $res->redline,
                    'redline_type' => $res->redline_type,
                    'redline_amount_type' => $res->redline_amount_type,
                    'redline_effective_date' => $res->redline_effective_date <= now() ? $res->redline_effective_date : null,

                ];
            })
            ->values(); // Re-index the array numerically
    }

    private function getUpFronts($id, $product_id = null, $core_position_id = null)
    {
        // Fetch upfronts based on user_id and optional filters
        $upfronts = OnboardingEmployeeUpfront::select([
            'id',
            'position_id',
            'product_id',
            'core_position_id',
            'milestone_schema_id',
            'milestone_schema_trigger_id',
            'self_gen_user',
            'updater_id',
            'tiers_id',
            'upfront_pay_amount',
            'upfront_sale_type',
            'custom_sales_field_id',
            'upfront_effective_date',
        ])
            ->where('user_id', $id)
            ->when($product_id, function ($query) use ($product_id) {
                // Filter by product_id if it's provided
                return $query->where('product_id', $product_id);
            })
            ->when($core_position_id !== null, function ($query) use ($core_position_id) {
                $query->where('core_position_id', $core_position_id == 0 ? null : $core_position_id);
            })
            ->get()
            ->toArray(); // Convert the result to an array

        // Initialize response with fetched upfronts
        $response = collect($upfronts)->groupBy('product_id')->map(function ($group) {
            return [
                'product_id' => $group->first()['product_id'], // Get the product ID
                'data' => $group->groupBy('core_position_id')->map(function ($coreGroup) {
                    return [
                        'milestone_id' => $coreGroup->first()['milestone_schema_id'], // Assuming the milestone ID is the same for the group
                        'core_position_id' => $coreGroup->first()['core_position_id'] == 0 ? null : $coreGroup->first()['core_position_id'],
                        'self_gen_user' => $coreGroup->first()['self_gen_user'],
                        'schemas' => $coreGroup->map(function ($item) {
                            $startDate = null;
                            $upFront = $this->getrange($item['id'], 'upfront');
                            if ($upFront && is_array($upFront) && count($upFront) != 0) {
                                $startDate = collect($upFront)->first()?->tiersSchema->first()?->start_end_day;
                            }

                            return [
                                'milestone_schema_trigger_id' => $item['milestone_schema_trigger_id'],
                                'upfront_pay_amount' => $item['upfront_pay_amount'],
                                'upfront_sale_type' => $this->transformCustomFieldType($item['upfront_sale_type'], $item['custom_sales_field_id']),
                                'custom_sales_field_id' => $this->getCustomFieldIdForDisplay($item['upfront_sale_type'], $item['custom_sales_field_id']),
                                'upfront_tiers_status' => @$item->tiers_id ? 1 : 0,
                                'tiers_id' => $item['tiers_id'],
                                'start_end_day' => $startDate,
                                'tiers_range' => $upFront,
                            ];
                        })->values()->all(), // Collect schema data
                    ];
                })->values()->all(), // Collect all core_position_id entries
            ];
        })->values()->all(); // Collect all product entries

        return $response; // Return the transformed upfronts
    }

    private function getOverrides($id, $product_id = null)
    {
        return OnboardingEmployeeOverride::select([
            'id',
            'user_id',
            'product_id',
            'override_effective_date',
            'direct_overrides_amount',
            'direct_overrides_type',
            'updater_id',
            'direct_tiers_id',
            'indirect_tiers_id',
            'office_tiers_id',
            'indirect_overrides_amount',
            'indirect_overrides_type',
            'office_overrides_amount',
            'office_overrides_type',
            'office_stack_overrides_amount',
            // Custom Sales Field IDs
            'direct_custom_sales_field_id',
            'indirect_custom_sales_field_id',
            'office_custom_sales_field_id',
        ])
            ->where('user_id', $id)
            ->when($product_id, function ($query) use ($product_id) {
                // Filter by product_id if it's provided
                return $query->where('product_id', $product_id);
            })
            ->get()
            ->map(function ($res) {
                $directStartDate = null;
                $direct = $this->getrange($res->id, 'direct');
                if ($direct && is_array($direct) && count($direct) != 0) {
                    $directStartDate = collect($direct)->first()?->tiersSchema->first()?->start_end_day;
                }
                $inDirectStartDate = null;
                $inDirect = $this->getrange($res->id, 'indirect');
                if ($inDirect && is_array($inDirect) && count($inDirect) != 0) {
                    $inDirectStartDate = collect($inDirect)->first()?->tiersSchema->first()?->start_end_day;
                }
                $officeStartDate = null;
                $office = $this->getrange($res->id, 'overrideoffice');
                if ($office && is_array($office) && count($office) != 0) {
                    $officeStartDate = collect($office)->first()?->tiersSchema->first()?->start_end_day;
                }

                return [
                    'product_id' => $res->product_id,
                    'override_effective_date' => $res->override_effective_date,
                    'updater_id' => $res->updater_id,
                    'direct_overrides_amount' => $res->direct_overrides_amount ?? '',
                    'direct_overrides_type' => $this->transformCustomFieldType($res->direct_overrides_type, $res->direct_custom_sales_field_id),
                    'direct_custom_sales_field_id' => $this->getCustomFieldIdForDisplay($res->direct_overrides_type, $res->direct_custom_sales_field_id),
                    'indirect_overrides_amount' => $res->indirect_overrides_amount ?? '',
                    'indirect_overrides_type' => $this->transformCustomFieldType($res->indirect_overrides_type, $res->indirect_custom_sales_field_id),
                    'indirect_custom_sales_field_id' => $this->getCustomFieldIdForDisplay($res->indirect_overrides_type, $res->indirect_custom_sales_field_id),
                    'office_overrides_amount' => $res->office_overrides_amount ?? '',
                    'office_overrides_type' => $this->transformCustomFieldType($res->office_overrides_type, $res->office_custom_sales_field_id),
                    'office_custom_sales_field_id' => $this->getCustomFieldIdForDisplay($res->office_overrides_type, $res->office_custom_sales_field_id),
                    'office_stack_overrides_amount' => $res->office_stack_overrides_amount ?? '',
                    'direct_tiers_status' => @$res->direct_tiers_id ? 1 : 0,
                    'direct_tiers_id' => $res->direct_tiers_id,
                    'direct_tiers_range' => $direct,
                    'direct_tiers_start_end_day' => $directStartDate,
                    'indirect_tiers_status' => @$res->indirect_tiers_id ? 1 : 0,
                    'indirect_tiers_id' => $res->indirect_tiers_id,
                    'indirect_tiers_range' => $inDirect,
                    'indirect_tiers_start_end_day' => $inDirectStartDate,
                    'office_tiers_status' => @$res->office_tiers_id ? 1 : 0,
                    'office_tiers_id' => $res->office_tiers_id,
                    'office_tiers_range' => $office,
                    'office_tiers_start_end_day' => $officeStartDate,
                    'additional_office_override' => $this->additionalOfficeOverride($res->user_id, $res->product_id),
                ];
            })
            ->values(); // Re-index the array numerically
    }

    private function additionalOfficeOverride($user_id, $product_id)
    {
        $additional_location = OnboardingEmployeeAdditionalOverride::with('OnboardingEmployeeLocation')
            ->where('user_id', $user_id)->where('product_id', $product_id)->get();

        return $additional_location->map(function ($d) {
            $dd = $d->onboardingEmployeeLocation ?? [];

            return [
                'onboarding_location_id' => $d->onboarding_location_id ?? null,
                'state_id' => $dd->state_id ?? 'NA',
                'state_name' => $dd->state->name ?? 'NA',
                'city_id' => $dd->city_id ?? 'NA',
                'city_name' => $dd->city->name ?? 'NA',
                'office_id' => $dd->office_id ?? null,
                'office_name' => $dd->office->office_name ?? null,
                'overrides_amount' => $d->overrides_amount ?? null,
                'overrides_type' => $this->transformCustomFieldType($d->overrides_type, $d->custom_sales_field_id),
                'custom_sales_field_id' => $this->getCustomFieldIdForDisplay($d->overrides_type, $d->custom_sales_field_id),
                'tiers_id' => $d->tiers_id ?? null,
                'tiers_range' => $this->getrange($d->id, 'office'),
            ];
        });
    }

    public function onboardingEmployeeDetails(Request $request): JsonResponse
    {
        $userId = $request->user_id;
        $onboardingEmployee = OnboardingEmployees::where('id', $userId)->first();

        $validator = Validator::make($request->all(), [
            'employee_deatils.first_name' => 'required',
            'employee_deatils.last_name' => 'required',
            'employee_deatils.email' => 'required|email',
            'employee_deatils.mobile_no' => 'required|min:10',
        ], [
            'employee_deatils.first_name.required' => 'The first name is required.',
            'employee_deatils.last_name.required' => 'The last name is required.',
            'employee_deatils.email.required' => 'The email address is required.',
            'employee_deatils.email.email' => 'Please enter a valid email address.',
            'employee_deatils.email.unique' => 'The email address is already in use.',
            'employee_deatils.*mobile_no.required' => 'The mobile number is required.',
            'employee_deatils.*mobile_no.min' => 'The mobile number must be at least :min characters.',
            'employee_deatils.*mobile_no.unique' => 'The mobile number is already in use.',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $data = $request->employee_deatils;
        foreach ($data['work_email'] as $work) {
            if ($work['email'] == $data['email']) {
                return response()->json([
                    'ApiName' => 'update-onboarding_employee',
                    'status' => false,
                    'message' => 'Additional email could not include personal email.',
                ], 400);
            }
        }

        if (! $userId) {
            $userEmail = User::where('email', $data['email'])->first();
            if ($userEmail && ! $userEmail->isTodayTerminated()) {
                return response()->json([
                    'ApiName' => 'update-onboarding_employee',
                    'status' => false,
                    'message' => 'This email id already exist in Users List',
                ], 400);
            }

            $userMobileNumber = User::where('mobile_no', $data['mobile_no'])->first();
            if ($userMobileNumber && ! $userMobileNumber->isTodayTerminated()) {
                $fullName = trim($userMobileNumber->first_name . ' ' . $userMobileNumber->last_name);
                $userEmail = $userMobileNumber->email;
                return response()->json([
                    'ApiName' => 'update-onboarding_employee',
                    'status' => false,
                    'message' => "This mobile number is being used by {$fullName} ({$userEmail}). Please use a different mobile number or update this current user with their correct mobile number.",
                ], 400);
            }

            $onBoardingEmail = OnboardingEmployees::where('email', $data['email'])->first();
            if ($onBoardingEmail && ! isUserTerminatedOn($onBoardingEmail->user_id, date('Y-m-d'))) {
                return response()->json([
                    'ApiName' => 'update-onboarding_employee',
                    'status' => false,
                    'message' => 'This email id already in onboarding exist',
                ], 400);
            }

            $onBoardingMobileNumber = OnboardingEmployees::where('mobile_no', $data['mobile_no'])->first();
            if ($onBoardingMobileNumber && ! isUserTerminatedOn($onBoardingMobileNumber->user_id, date('Y-m-d'))) {
                return response()->json([
                    'ApiName' => 'update-onboarding_employee',
                    'status' => false,
                    'message' => 'This mobile no already exist in onboarding List',
                ], 400);
            }

            $leadEmail = Lead::where('email', $data['email'])->where('id', '!=', $data['lead_id'])->first();
            if ($leadEmail) {
                return response()->json([
                    'ApiName' => 'update-onboarding_employee',
                    'status' => false,
                    'message' => 'This email id already in Leads exist',
                ], 400);
            }

            $leadMobileNumber = Lead::where('mobile_no', $data['mobile_no'])->where('id', '!=', $data['lead_id'])->first();
            if ($leadMobileNumber != '') {
                return response()->json([
                    'ApiName' => 'update-onboarding_employee',
                    'status' => false,
                    'message' => 'This mobile no already exist in Leads List',
                ], 400);
            }

            $workEmail = $data['work_email'];
            if (count($workEmail) > 0) {
                $additionalEmails = [];
                foreach ($data['work_email'] as $work_email) {
                    $additionalEmails[] = $work_email['email'];
                }
                $additionalEmail = OnboardingAdditionalEmails::whereIn('email', $additionalEmails)->count();
                if ($additionalEmail) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'Additional email id already exist',
                    ], 400);
                }

                $onboardingEmail = OnboardingEmployees::whereIn('email', $additionalEmails)->count();
                if ($onboardingEmail) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'Additional email id already exist in onboarding list',
                    ], 400);
                }

                $userEmail = User::whereIn('email', $additionalEmails)->count();
                if ($userEmail) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'Additional email id already exist in user list',
                    ], 400);
                }

                $userAdditionalEmail = UsersAdditionalEmail::whereIn('email', $additionalEmails)->count();
                if ($userAdditionalEmail) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'Additional email id already exist in user additional email list',
                    ], 400);
                }

                $leadEmail = Lead::whereIn('email', $additionalEmails)->count();
                if ($leadEmail) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'Additional email id already exist in lead list',
                    ], 400);
                }
            }

            $officeId = @$data['office_id'] ? $data['office_id'] : null;
            $array = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'mobile_no' => $data['mobile_no'],
                'state_id' => $data['state_id'],
                'office_id' => $officeId,
                'lead_id' => $data['lead_id'],
                'recruiter_id' => isset($data['recruiter_id']) ? $data['recruiter_id'] : null,
                'status_id' => 8,
            ];
            if (isset($data['recruiter_id']) && $data['recruiter_id']) {
                $array['recruiter_id'] = $data['recruiter_id'];
            }

            $companyProfile = CompanyProfile::first();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $array['redline_type'] = 'per sale';
                $array['upfront_sale_type'] = 'per sale';
                $array['direct_overrides_type'] = 'per sale';
                $array['indirect_overrides_type'] = 'per sale';
                $array['office_overrides_type'] = 'per sale';
            }
            $onboardingEmployee = OnboardingEmployees::create($array);

            $workEmail = $data['work_email'];
            if (count($workEmail) > 0) {
                foreach ($workEmail as $workEmails) {
                    OnboardingAdditionalEmails::create(['onboarding_user_id' => $onboardingEmployee->id, 'email' => $workEmails['email']]);
                }
            }

            if (isset($data['lead_id'])) {
                Lead::where('id', $data['lead_id'])->update(['status' => 'Hired']);
            }

            try {
                $proposedEmployeeId = DB::transaction(function () use ($onboardingEmployee) {
                    $empIdCode = EmployeeIdSetting::orderBy('id', 'asc')->first();
                    $idCode = !empty($empIdCode) ? $empIdCode->onbording_id_code : 'ONB';
                    
                    // Lock the table to prevent concurrent access
                    OnboardingEmployees::where('employee_id', 'like', $idCode.'%')
                        ->whereNotNull('employee_id')
                        ->lockForUpdate()
                        ->get();
                    
                    // Get the highest existing employee_id numeric value
                    $maxNumericValue = OnboardingEmployees::where('employee_id', 'like', $idCode.'%')
                        ->whereNotNull('employee_id')
                        ->where('id', '!=', $onboardingEmployee->id)
                        ->selectRaw('CAST(SUBSTRING(employee_id, ?) AS UNSIGNED) as num', [strlen($idCode) + 1])
                        ->orderByRaw('CAST(SUBSTRING(employee_id, ?) AS UNSIGNED) DESC', [strlen($idCode) + 1])
                        ->value('num');
                    
                    // Get the maximum padding length using SQL (much more efficient)
                    $maxPaddingLength = OnboardingEmployees::where('employee_id', 'like', $idCode.'%')
                        ->whereNotNull('employee_id')
                        ->where('id', '!=', $onboardingEmployee->id)
                        ->selectRaw('MAX(LENGTH(employee_id) - ?) as max_len', [strlen($idCode)])
                        ->value('max_len');
                    
                    // Determine numeric count: use max padding found (preserve existing format) or default to 4
                    $numericCount = $maxPaddingLength ?: 4;
                    
                    // Get next available number (max + 1)
                    $val = ($maxNumericValue ?? 0) + 1;
                    
                    // Check if this value already exists (edge case for concurrent requests)
                    // Add max iteration limit to prevent infinite loops
                    $maxIterations = 100;
                    $iterationCount = 0;
                    while (OnboardingEmployees::where('employee_id', $idCode.str_pad($val, $numericCount, '0', STR_PAD_LEFT))
                        ->where('id', '!=', $onboardingEmployee->id)
                        ->exists()) {
                        $val++;
                        $iterationCount++;
                        if ($iterationCount >= $maxIterations) {
                            Log::error('Employee ID generation: Max iterations reached', [
                                'onboarding_employee_id' => $onboardingEmployee->id,
                                'id_code' => $idCode,
                                'last_attempted_value' => $val,
                            ]);
                            throw new Exception('Unable to generate unique employee ID after '.$maxIterations.' attempts');
                        }
                    }
                    
                    $EmpId = str_pad($val, $numericCount, '0', STR_PAD_LEFT);
                    $proposedEmployeeId = $idCode.$EmpId;
                    
                    OnboardingEmployees::where('id', $onboardingEmployee->id)->update(['employee_id' => $proposedEmployeeId]);
                    
                    return $proposedEmployeeId;
                });
            } catch (Exception $e) {
                Log::error('Failed to generate employee ID for onboarding employee', [
                    'onboarding_employee_id' => $onboardingEmployee->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                return response()->json([
                    'ApiName' => 'add-onboarding_employee',
                    'status' => false,
                    'message' => 'Failed to generate employee ID. Please try again.',
                    'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                ], 500);
            }

            return response()->json([
                'ApiName' => 'add-onboarding_employee',
                'status' => true,
                'message' => 'add Successfully.',
                'data' => OnboardingEmployees::where('id', $onboardingEmployee->id)->with('OnboardingAdditionalEmails')->first(),
            ]);
        } else {
            if ($data['email'] != $onboardingEmployee->email) {
                $userEmail = User::where('email', $data['email'])->first();
                if ($userEmail) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'This email id already exist in Users List',
                    ], 400);
                }

                $onBoardingEmail = OnboardingEmployees::where('email', $data['email'])->where('id', '!=', $onboardingEmployee->id)->first();
                if ($onBoardingEmail) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'This email id already exist in Users List',
                    ], 400);
                }

                $leadEmail = Lead::where('email', $data['email'])->where('id', '!=', $onboardingEmployee->lead_id)->first();
                if ($leadEmail) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'This email id already exist in Users List',
                    ], 400);
                }
            }

            if ($data['mobile_no'] != $onboardingEmployee->mobile_no) {
                $userMobileNumber = User::where('mobile_no', $data['mobile_no'])->first();
                if ($userMobileNumber) {
                    $fullName = trim($userMobileNumber->first_name . ' ' . $userMobileNumber->last_name);
                    $userEmail = $userMobileNumber->email;
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => "This mobile number is being used by {$fullName} ({$userEmail}). Please use a different mobile number or update this current user with their correct mobile number.",
                    ], 400);
                }

                $onBoardingMobileNumber = OnboardingEmployees::where('mobile_no', $data['mobile_no'])->where('id', '!=', $onboardingEmployee->id)->first();
                if ($onBoardingMobileNumber) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'This mobile no already exist in Onboarding List',
                    ], 400);
                }

                $leadMobileNumber = Lead::where('mobile_no', $data['mobile_no'])->where('id', '!=', $onboardingEmployee->lead_id)->first();
                if ($leadMobileNumber) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'This mobile no already exist in Lead List',
                    ], 400);
                }
            }

            $workEmail = $data['work_email'];
            if (count($workEmail) > 0) {
                $additionalEmails = [];
                foreach ($data['work_email'] as $work_email) {
                    $additionalEmails[] = $work_email['email'];
                }
                $additionalEmail = OnboardingAdditionalEmails::whereIn('email', $additionalEmails)->where('onboarding_user_id', '!=', $onboardingEmployee->id)->count();
                if ($additionalEmail) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'Additional email id already exist',
                    ], 400);
                }

                $onboardingEmail = OnboardingEmployees::whereIn('email', $additionalEmails)->count();
                if ($onboardingEmail) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'Additional email id already exist in onboarding list',
                    ], 400);
                }

                $userEmail = User::whereIn('email', $additionalEmails)->count();
                if ($userEmail) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'Additional email id already exist in user list',
                    ], 400);
                }

                $leadEmail = Lead::whereIn('email', $additionalEmails)->where('id', '!=', $onboardingEmployee->lead_id)->count();
                if ($leadEmail) {
                    return response()->json([
                        'ApiName' => 'update-onboarding_employee',
                        'status' => false,
                        'message' => 'Additional email id already exist in lead list',
                    ], 400);
                }
            }

            $onboardingEmployee = OnboardingEmployees::where('id', $userId)->first();
            DocumentSigner::where('signer_email', $onboardingEmployee->email)->update([
                'signer_name' => $onboardingEmployee->first_name.' '.$onboardingEmployee->last_name,
                'signer_email' => $onboardingEmployee->email,
            ]);

            $onboardingEmployee->first_name = $data['first_name'];
            $onboardingEmployee->last_name = $data['last_name'];
            $onboardingEmployee->email = $data['email'];
            $onboardingEmployee->mobile_no = $data['mobile_no'];
            $onboardingEmployee->state_id = $data['state_id'];
            $onboardingEmployee->office_id = $data['office_id'];
            if (isset($data['recruiter_id']) && $data['recruiter_id']) {
                $onboardingEmployee->recruiter_id = $data['recruiter_id'];
            }
            $onboardingEmployee->save();

            /* Update data in sclearance table if exists */
            $sClearanceIds = SClearanceTurnScreeningRequestList::where(['user_type_id' => $userId, 'user_type' => 'Onboarding'])->pluck('id')->toArray();
            if (! empty($sClearanceIds)) {
                SClearanceTurnScreeningRequestList::whereIn('id', $sClearanceIds)->update([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                ]);
            }
            /* Update data in sclearance table if exists */

            OnboardingAdditionalEmails::where('onboarding_user_id', $userId)->delete();
            if (count($workEmail) > 0) {
                foreach ($workEmail as $workEmails) {
                    OnboardingAdditionalEmails::create(['onboarding_user_id' => $onboardingEmployee->id, 'email' => $workEmails['email']]);
                }
            }

            // adding re-hire flag
            if (isset($request->rehire)) {
                User::where('email', $onboardingEmployee->email)->update(['rehire' => 1]);
            }

            return response()->json([
                'ApiName' => 'update-onboarding_employee',
                'status' => true,
                'message' => 'add Successfully.',
                'data' => OnboardingEmployees::where('id', $userId)->with('OnboardingAdditionalEmails')->first(),
            ]);
        }
    }

    public function employeeOrganization(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:onboarding_employees,id',
            'employee_originization.department_id' => 'required',
            'employee_originization.position_id' => 'required',
            'employee_originization.sub_position_id' => 'required',
            // 'employee_originization.is_manager' => 'required|in:0,1',
            'employee_originization.manager_id' => 'required_if:is_manager,0',
            'employee_originization.additional_locations' => 'array',
            'employee_originization.additional_locations.*.state_id' => 'required',
            'employee_originization.additional_locations.*.office_id' => 'required',
            'employee_originization.additional_recruiter_id' => 'array',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $authUser = Auth()->user();
        $userId = $request->user_id;
        $data = $request->employee_originization;
        $onboardingEmployee = OnboardingEmployees::find($userId);
        $subPositionId = $onboardingEmployee->sub_position_id;
        if (! $onboardingEmployee) {
            return response()->json([
                'ApiName' => 'add-onboarding_employee_organization',
                'status' => false,
                'message' => 'Employee not found.',
            ], 400);
        }

        $onboardingEmployee->department_id = $data['department_id'];
        $onboardingEmployee->position_id = $data['position_id'];
        $onboardingEmployee->sub_position_id = $data['sub_position_id'];
        $onboardingEmployee->is_manager = @$data['is_manager'] ? (string) $data['is_manager'] : '0';
        $onboardingEmployee->manager_id = $data['manager_id'];
        $onboardingEmployee->team_id = $data['team_id'];
        $onboardingEmployee->hired_by_uid = $authUser->id;
        $onboardingEmployee->recruiter_id = isset($data['recruiter_id']) ? $data['recruiter_id'] : null;
        if (in_array(config('app.domain_name'), ['hawx', 'hawxw2', 'sstage', 'milestone'])) {
            $onboardingEmployee->experience_level = isset($data['experience_level']) ? $data['experience_level'] : null;
        }

        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $onboardingEmployee->self_gen_accounts = null;
            $onboardingEmployee->self_gen_type = null;
        } else {
            $subPosition = $data['sub_position_id'];
            $findPosition = Positions::where('id', $subPosition)->first();
            if ($findPosition) {
                $selfGen = 0;
                if ($findPosition->is_selfgen == 1) {
                    $selfGen = 1;
                }
                $onboardingEmployee->self_gen_accounts = $selfGen;
                if ($selfGen) {
                    $onboardingEmployee->self_gen_type = 3;
                }
            }
        }

        $additionalRecruiter = $data['additional_recruiter_id'];
        $onboardingEmployee->additional_recruiter_id1 = isset($additionalRecruiter[0]) ? $additionalRecruiter[0] : null;
        $onboardingEmployee->additional_recruiter_id2 = isset($additionalRecruiter[1]) ? $additionalRecruiter[1] : null;
        if ($subPositionId != $data['sub_position_id']) {
            $this->clearData($userId);
        }
        $onboardingEmployee->save();

        $employee = OnboardingEmployees::with('positionDetail', 'positionWages')->find($userId);
        if ($employee) {
            $employee->update(array_merge(['overtime_rate' => isset($employee->positionWages) ? $employee->positionWages->overtime_rate : 0]));
        }

        AdditionalRecruiters::where('hiring_id', $onboardingEmployee->id)->delete();
        foreach ($additionalRecruiter as $recruiter) {
            AdditionalRecruiters::create([
                'hiring_id' => $onboardingEmployee->id,
                'recruiter_id' => $recruiter,
            ]);
        }

        $additionalLocations = $data['additional_locations'];
        OnboardingEmployeeLocations::where('user_id', $onboardingEmployee->id)->delete();
        foreach ($additionalLocations as $additionalLocation) {
            OnboardingEmployeeLocations::create([
                'user_id' => $onboardingEmployee->id,
                'state_id' => $additionalLocation['state_id'],
                'office_id' => $additionalLocation['office_id'] ?? null,
            ]);
        }

        OnboardingEmployeeWithheld::where('user_id', $userId)->delete();
        $withHeld = PositionReconciliations::where(['position_id' => $data['sub_position_id']])->get();
        foreach ($withHeld as $withHeld) {
            OnboardingEmployeeWithheld::create([
                'user_id' => $userId,
                'product_id' => $withHeld->product_id ?? 0,
                'position_id' => $withHeld->position_id ?? null,
                'updater_id' => auth()->user()->id,
                'withheld_type' => $withHeld->commission_type ?? null,
                'withheld_amount' => $withHeld->commission_withheld ?? 0,
            ]);
        }

        EmployeeOnboardingDeduction::where('user_id', $userId)->delete();
        $deductions = PositionCommissionDeduction::with('costcenter')->where(['position_id' => $data['sub_position_id']])->get();
        foreach ($deductions as $deduction) {
            EmployeeOnboardingDeduction::create([
                'user_id' => $userId,
                'position_id' => $data['sub_position_id'],
                'deduction_type' => $deduction->deduction_type,
                'cost_center_name' => $deduction?->costcenter?->cost_center_name,
                'cost_center_id' => $deduction->cost_center_id,
                'ammount_par_paycheck' => $deduction->ammount_par_paycheck,
                'deduction_setting_id' => isset($deduction->deduction_setting_id) ? $deduction->deduction_setting_id : null,
                'pay_period_from' => $deduction->pay_period_from,
                'pay_period_to' => $deduction->pay_period_to,
            ]);
        }

        if (isset($request->employee_originization['template_id']) && ! empty($request->employee_originization['template_id'])) {
            // dd($userId);
            SentOfferLetter::updateOrCreate(
                ['onboarding_employee_id' => $userId],
                ['template_id' => $request->employee_originization['template_id']]
            );
        }

        return response()->json([
            'ApiName' => 'add-onboarding_employee_organization',
            'status' => true,
            'message' => 'add Successfully.',
        ]);
    }

    private function clearData($id)
    {
        OnboardingEmployeeWages::where('user_id', $id)->delete();
        OnboardingEmployeeOverride::where('user_id', $id)->delete();
        OnboardingEmployeeUpfront::where('user_id', $id)->delete();
        OnboardingUserRedline::where('user_id', $id)->delete();
        OnboardingEmployeeWithheld::where('user_id', $id)->delete();
        OnboardingEmployeeRedline::where('user_id', $id)->delete();
        OnboardingCommissionTiersRange::where('user_id', $id)->delete();
        OnboardingUpfrontsTiersRange::where('user_id', $id)->delete();
        OnboardingEmployeeAdditionalOverride::where('user_id', $id)->delete();
        OnboardingDirectOverrideTiersRange::where('user_id', $id)->delete();
        OnboardingIndirectOverrideTiersRange::where('user_id', $id)->delete();
        OnboardingOverrideOfficeTiersRange::where('user_id', $id)->delete();
        OnboardingOfficeOverrideTiersRange::where('user_id', $id)->delete();
        EmployeeOnboardingDeduction::where('user_id', $id)->delete();
    }

    public function deleteOnboardingLocation($id): JsonResponse
    {
        OnboardingEmployeeLocations::find($id)->delete();
        OnboardingEmployeeAdditionalOverride::where('onboarding_location_id', $id)->delete();

        return response()->json([
            'ApiName' => 'delete Onboarding Location',
            'status' => true,
            'message' => 'delete Successfully.',
        ], 200);
    }

    public function wages(Request $request): JsonResponse
    {
        $employee = OnboardingEmployees::with('positionDetail', 'positionWages')->find($request->user_id);

        if (! $employee) {
            return response()->json([
                'ApiName' => 'add-onboarding_employee_wages',
                'status' => false,
                'message' => 'User Not Found',
            ], 400);
        }

        $data = array_merge([
            'updater_id' => auth()->id(),
            'pay_type' => $request->employee_wages['pay_type'],
            'pay_rate' => $request->employee_wages['pay_rate'],
            'expected_weekly_hours' => $request->employee_wages['expected_weekly_hours'],
            'overtime_rate' => $employee->positionWages->overtime_rate ?? 0,
            'pay_rate_type' => $request->employee_wages['pay_rate_type'] ?? 'Weekly',
            'worker_type' => $employee->positionDetail->worker_type ?? null,
        ], array_filter([
            'pto_hours' => $request->employee_wages['pto_hours'] ?? null,
            'unused_pto_expires' => $request->employee_wages['unused_pto_expires'] ?? null,
        ]));
        $employee->update($data);
        $data['unused_pto'] = $data['unused_pto_expires'];
        unset($data['unused_pto_expires']);
        OnboardingEmployeeWages::updateOrCreate(
            ['user_id' => $request->user_id], // Condition to check if the record exists
            $data // Data to update or create with
        );

        return response()->json([
            'ApiName' => 'add-onboarding_employee_wages',
            'status' => true,
            'message' => 'Added Successfully.',
        ]);
    }

    public function employeeReadline(Request $request): JsonResponse
    {
        $companyProfile = CompanyProfile::first();
        $isPestCompany = in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE);

        if ($isPestCompany) {
            return response()->json([
                'ApiName' => 'add-onboarding_employee_redline',
                'status' => true,
                'message' => 'Redline is not available for your company type!!',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:onboarding_employees,id',
            'employee_redline' => 'required|array|min:1',
            'employee_redline.*.self_gen_user' => 'required|in:0,1',
            'employee_redline.*.redline' => 'required',
            'employee_redline.*.redline_amount_type' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $userId = $request->user_id;
        $onBoardingEmployee = OnboardingEmployees::find($userId);
        if (! $onBoardingEmployee) {
            return response()->json([
                'ApiName' => 'add-onboarding_employee_redline',
                'status' => false,
                'message' => 'User Not found!!',
            ], 400);
        }

        $redLines = $request->employee_redline;
        $subPositionId = $onBoardingEmployee->sub_position_id;
        OnboardingEmployeeRedline::where('user_id', $userId)->delete();
        foreach ($redLines as $redLine) {
            OnboardingEmployeeRedline::create([
                'user_id' => $userId,
                'core_position_id' => $redLine['core_position_id'] ?? null,
                'position_id' => $subPositionId,
                'self_gen_user' => $redLine['self_gen_user'] ?? 0,
                'updater_id' => auth()->user()->id,
                'redline' => $redLine['redline'] ?? null,
                'redline_type' => $redLine['redline_type'] ?? 'per watt',
                'redline_amount_type' => $redLine['redline_amount_type'] ?? 'Fixed',
            ]);
        }

        return response()->json([
            'ApiName' => 'add-onboarding_employee_redline',
            'status' => true,
            'message' => 'Added Successfully.',
            'data' => $onBoardingEmployee,
        ]);
    }

    public function employeeCompensation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:onboarding_employees,id',
            'employee_compensation' => 'required|array|min:1',
            'employee_compensation.*.product_id' => 'required',
            'employee_compensation.*.data' => 'required|array|min:1',
            'employee_compensation.*.data.*.self_gen_user' => 'required|in:0,1',
            'employee_compensation.*.data.*.commission' => 'required',
            'employee_compensation.*.data.*.commission_type' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $userId = $request->user_id;
        $onBoardingEmployee = OnboardingEmployees::find($userId);
        if (! $onBoardingEmployee) {
            return response()->json([
                'ApiName' => 'add-onboarding_employee_compensation',
                'status' => false,
                'message' => 'User Not found!!',
            ], 400);
        }
        $productCommissions = $request->employee_compensation;
        $subPositionId = $onBoardingEmployee->sub_position_id;
        OnboardingUserRedline::where('user_id', $userId)->delete();
        OnboardingCommissionTiersRange::where('user_id', $userId)->delete();

        $onboardingCommissionTiersRange = [];
        foreach ($productCommissions as $productCommission) {
            $productId = $productCommission['product_id'];
            foreach ($productCommission['data'] as $commission) {
                // Custom Sales Field support: Parse custom_field_X format
                $commissionParsed = $this->parseCustomFieldType(
                    $commission['commission_type'] ?? null,
                    $commission['custom_sales_field_id'] ?? null
                );

                $onboardingcommission = OnboardingUserRedline::create([
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'core_position_id' => $commission['core_position_id'] ?? null,
                    'position_id' => $subPositionId,
                    'self_gen_user' => $commission['self_gen_user'] ?? 0,
                    'updater_id' => auth()->user()->id,
                    'commission' => $commission['commission'] ?? 0,
                    'commission_type' => $commissionParsed['type'],
                    'custom_sales_field_id' => $commissionParsed['custom_sales_field_id'],
                    'tiers_id' => $commission['tiers_id'] ?? 0,
                ]);
                $lastid = $onboardingcommission->id;
                if ($this->companySettingtiers?->status) {
                    $tiers_id = isset($commission['tiers_id']) && $commission['tiers_id'] != '' ? $commission['tiers_id'] : 0;
                    $range = isset($commission['tiers_range']) && $commission['tiers_range'] != '' ? $commission['tiers_range'] : '';
                    if ($tiers_id > 0) {
                        if (is_array($range) && ! empty($range)) {
                            foreach ($range as $rang) {
                                /*OnboardingCommissionTiersRange::create([
                                    "user_id" => $userId,
                                    'onboarding_commission_id' => $lastid,
                                    'tiers_schema_id' => $commission['tiers_id'] ?? 0,
                                    'tiers_levels_id' => $rang['id'] ?? null,
                                    'value' => $rang['value'] ?? null
                                ]);*/

                                $onboardingCommissionTiersRange[] = [
                                    'user_id' => $userId,
                                    'onboarding_commission_id' => $lastid,
                                    'tiers_levels_id' => $rang['id'] ?? null,
                                    'value' => $rang['value'] ?? null,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ];
                            }
                        }
                    }
                }
            }
        }

        if (! empty($onboardingCommissionTiersRange)) {
            OnboardingCommissionTiersRange::insert($onboardingCommissionTiersRange);
        }

        return response()->json([
            'ApiName' => 'add-onboarding_employee_compensation',
            'status' => true,
            'message' => 'Added Successfully.',
        ]);
    }

    public function employeeUpFronts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:onboarding_employees,id',
            'employee_upfronts' => 'required|array|min:1',
            'employee_upfronts.*.product_id' => 'required',
            'employee_upfronts.*.data' => 'required|array|min:1',
            'employee_upfronts.*.data.*.milestone_id' => 'required',
            'employee_upfronts.*.data.*.self_gen_user' => 'required|in:0,1',
            'employee_upfronts.*.data.*.schemas' => 'required|array|min:1',
            'employee_upfronts.*.data.*.schemas.*.milestone_schema_trigger_id' => 'required',
            'employee_upfronts.*.data.*.schemas.*.upfront_pay_amount' => 'required',
            'employee_upfronts.*.data.*.schemas.*.upfront_sale_type' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $userId = $request->user_id;
        $onBoardingEmployee = OnboardingEmployees::find($userId);
        if (! $onBoardingEmployee) {
            return response()->json([
                'ApiName' => 'add-onboarding_employee_compensation',
                'status' => false,
                'message' => 'User Not found!!',
            ], 400);
        }

        $productUpFronts = $request->employee_upfronts;
        $subPositionId = $onBoardingEmployee->sub_position_id;
        OnboardingEmployeeUpfront::where('user_id', $userId)->delete();
        OnboardingUpfrontsTiersRange::where('user_id', $userId)->delete();
        foreach ($productUpFronts as $productUpFront) {
            $productId = $productUpFront['product_id'];
            foreach ($productUpFront['data'] as $upFronts) {
                $milestoneId = $upFronts['milestone_id'];
                foreach ($upFronts['schemas'] as $upFront) {
                    $milestoneSchemaId = $upFront['milestone_schema_trigger_id'];
                    
                    // Custom Sales Field support: Parse custom_field_X format for upfront
                    $upfrontParsed = $this->parseCustomFieldType(
                        $upFront['upfront_sale_type'] ?? 'per sale',
                        $upFront['custom_sales_field_id'] ?? null
                    );

                    $onboardingupfront = OnboardingEmployeeUpfront::create([
                        'user_id' => $userId,
                        'product_id' => $productId,
                        'milestone_schema_id' => $milestoneId,
                        'milestone_schema_trigger_id' => $milestoneSchemaId,
                        'core_position_id' => $upFronts['core_position_id'] ?? null,
                        'position_id' => $subPositionId,
                        'self_gen_user' => $upFronts['self_gen_user'] ?? 0,
                        'updater_id' => auth()->user()->id,
                        'upfront_pay_amount' => $upFront['upfront_pay_amount'] ?? 0,
                        'upfront_sale_type' => $upfrontParsed['type'] ?? 'per sale',
                        'custom_sales_field_id' => $upfrontParsed['custom_sales_field_id'],
                        'tiers_id' => $upFront['tiers_id'] ?? 0,
                    ]);
                    $upfront_lastid = $onboardingupfront->id;
                    if ($this->companySettingtiers?->status) {
                        $tiers_id = isset($upFront['tiers_id']) && $upFront['tiers_id'] != '' ? $upFront['tiers_id'] : 0;
                        $range = isset($upFront['tiers_range']) && $upFront['tiers_range'] != '' ? $upFront['tiers_range'] : '';
                        if ($tiers_id > 0) {
                            if (is_array($range) && ! empty($range)) {
                                foreach ($range as $rang) {
                                    OnboardingUpfrontsTiersRange::create([
                                        'user_id' => $userId,
                                        'onboarding_upfront_id' => $upfront_lastid,
                                        'tiers_schema_id' => $upFront['tiers_id'] ?? 0,
                                        'tiers_levels_id' => $rang['id'] ?? null,
                                        'value' => $rang['value'] ?? null,
                                        'value_type' => @$upFront['upfront_sale_type'] ?? 'per sale',
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }

        return response()->json([
            'ApiName' => 'add-onboarding_employee_upfronts',
            'status' => true,
            'message' => 'Added Successfully.',
        ]);
    }

    public function employeeOverride(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:onboarding_employees,id',
            'employee_override' => 'required|array|min:1',
            'employee_override.*.product_id' => 'required',
            'employee_override.*.additional_office_override.*.onboarding_location_id' => 'required',
            'employee_override.*.additional_office_override.*.overrides_amount' => 'required',
            'employee_override.*.additional_office_override.*.overrides_type' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $userId = $request->user_id;
        $onBoardingEmployee = OnboardingEmployees::find($userId);
        if (! $onBoardingEmployee) {
            return response()->json([
                'ApiName' => 'add-onboarding_employee_compensation',
                'status' => false,
                'message' => 'User Not found!!',
            ], 400);
        }

        $overrides = $request->employee_override;
        $subPositionId = $onBoardingEmployee->sub_position_id;
        OnboardingEmployeeOverride::where('user_id', $userId)->delete();
        OnboardingEmployeeAdditionalOverride::where('user_id', $userId)->delete();
        OnboardingDirectOverrideTiersRange::where('user_id', $userId)->delete();
        OnboardingIndirectOverrideTiersRange::where('user_id', $userId)->delete();
        OnboardingOverrideOfficeTiersRange::where('user_id', $userId)->delete();
        OnboardingOfficeOverrideTiersRange::where('user_id', $userId)->delete();
        // Check if Custom Sales Fields feature is enabled (using cached helper)
        $isCustomFieldsEnabled = CustomSalesFieldHelper::isFeatureEnabled();
        
        foreach ($overrides as $override) {
            // Custom Sales Field support: Parse custom_field_X format for override types
            $directOverridesType = @$override['direct_overrides_type'] ? $override['direct_overrides_type'] : null;
            $directCustomSalesFieldId = $override['direct_custom_sales_field_id'] ?? null;
            if ($isCustomFieldsEnabled && $directOverridesType && preg_match('/^custom_field_(\d+)$/', $directOverridesType, $matches)) {
                $directOverridesType = 'custom field';
                $directCustomSalesFieldId = (int) $matches[1];
            }

            $indirectOverridesType = @$override['indirect_overrides_type'] ? $override['indirect_overrides_type'] : null;
            $indirectCustomSalesFieldId = $override['indirect_custom_sales_field_id'] ?? null;
            if ($isCustomFieldsEnabled && $indirectOverridesType && preg_match('/^custom_field_(\d+)$/', $indirectOverridesType, $matches)) {
                $indirectOverridesType = 'custom field';
                $indirectCustomSalesFieldId = (int) $matches[1];
            }

            $officeOverridesType = @$override['office_overrides_type'] ? $override['office_overrides_type'] : null;
            $officeCustomSalesFieldId = $override['office_custom_sales_field_id'] ?? null;
            if ($isCustomFieldsEnabled && $officeOverridesType && preg_match('/^custom_field_(\d+)$/', $officeOverridesType, $matches)) {
                $officeOverridesType = 'custom field';
                $officeCustomSalesFieldId = (int) $matches[1];
            }

            $onboardingoverride = OnboardingEmployeeOverride::create([
                'user_id' => $userId,
                'product_id' => $override['product_id'],
                'position_id' => $subPositionId,
                'updater_id' => auth()->user()->id,
                'direct_overrides_amount' => @$override['direct_overrides_amount'] ? $override['direct_overrides_amount'] : 0,
                'direct_overrides_type' => $directOverridesType,
                'indirect_overrides_amount' => @$override['indirect_overrides_amount'] ? $override['indirect_overrides_amount'] : 0,
                'indirect_overrides_type' => $indirectOverridesType,
                'office_overrides_amount' => @$override['office_overrides_amount'] ? $override['office_overrides_amount'] : 0,
                'office_overrides_type' => $officeOverridesType,
                'office_stack_overrides_amount' => @$override['office_stack_overrides_amount'] ? $override['office_stack_overrides_amount'] : 0,
                'direct_tiers_id' => @$override['direct_tiers_id'] ? $override['direct_tiers_id'] : null,
                'indirect_tiers_id' => @$override['indirect_tiers_id'] ? $override['indirect_tiers_id'] : null,
                'office_tiers_id' => @$override['office_tiers_id'] ? $override['office_tiers_id'] : null,
                'direct_custom_sales_field_id' => $directCustomSalesFieldId,
                'indirect_custom_sales_field_id' => $indirectCustomSalesFieldId,
                'office_custom_sales_field_id' => $officeCustomSalesFieldId,
            ]);
            $override_lastid = $onboardingoverride->id;
            if ($this->companySettingtiers?->status) {
                $direct_tiers_id = isset($override['direct_tiers_id']) && $override['direct_tiers_id'] != '' ? $override['direct_tiers_id'] : 0;
                $direct_range = isset($override['direct_tiers_range']) && $override['direct_tiers_range'] != '' ? $override['direct_tiers_range'] : '';
                if ($direct_tiers_id > 0) {
                    if (is_array($direct_range) && ! empty($direct_range)) {
                        foreach ($direct_range as $rang) {
                            OnboardingDirectOverrideTiersRange::create([
                                'user_id' => $userId,
                                'onboarding_direct_override_id' => $override_lastid,
                                'tiers_schema_id' => @$override['direct_tiers_id'] ? $override['direct_tiers_id'] : null,
                                'tiers_levels_id' => $rang['id'] ?? null,
                                'value' => $rang['value'] ?? null,
                                'value_type' => @$override['direct_overrides_type'] ? $override['direct_overrides_type'] : null,

                            ]);
                        }
                    }
                }

                $indirect_tiers_id = isset($override['indirect_tiers_id']) && $override['indirect_tiers_id'] != '' ? $override['indirect_tiers_id'] : 0;
                $indirect_range = isset($override['indirect_tiers_range']) && $override['indirect_tiers_range'] != '' ? $override['indirect_tiers_range'] : '';
                if ($indirect_tiers_id > 0) {
                    if (is_array($indirect_range) && ! empty($indirect_range)) {
                        foreach ($indirect_range as $rang) {
                            OnboardingIndirectOverrideTiersRange::create([
                                'user_id' => $userId,
                                'onboarding_indirect_override_id' => $override_lastid,
                                'tiers_schema_id' => @$override['indirect_tiers_id'] ? $override['indirect_tiers_id'] : null,
                                'tiers_levels_id' => $rang['id'] ?? null,
                                'value' => $rang['value'] ?? null,
                                'value_type' => @$override['indirect_overrides_type'] ? $override['indirect_overrides_type'] : null,
                            ]);
                        }
                    }
                }

                $office_tiers_id = isset($override['office_tiers_id']) && $override['office_tiers_id'] != '' ? $override['office_tiers_id'] : 0;
                $office_tiers_range = isset($override['office_tiers_range']) && $override['office_tiers_range'] != '' ? $override['office_tiers_range'] : '';
                if ($office_tiers_id > 0) {
                    if (is_array($office_tiers_range) && ! empty($office_tiers_range)) {
                        foreach ($office_tiers_range as $rang) {
                            OnboardingOverrideOfficeTiersRange::create([
                                'user_id' => $userId,
                                'onboarding_override_office_id' => $override_lastid,
                                'tiers_schema_id' => @$override['office_tiers_id'] ? $override['office_tiers_id'] : null,
                                'tiers_levels_id' => $rang['id'] ?? null,
                                'value' => $rang['value'] ?? null,
                                'value_type' => @$override['office_overrides_type'] ? $override['office_overrides_type'] : 0,
                            ]);
                        }
                    }
                }
            }
            if (@$override['additional_office_override'] && is_array($override['additional_office_override'])) {
                foreach ($override['additional_office_override'] as $additional) {
                    $officeonboardingoverride = OnboardingEmployeeAdditionalOverride::create([
                        'user_id' => $userId,
                        'tiers_id' => $additional['tiers_id'] ?? null,
                        'onboarding_location_id' => $additional['onboarding_location_id'],
                        'product_id' => $override['product_id'],
                        'overrides_amount' => $additional['overrides_amount'],
                        'overrides_type' => $additional['overrides_type'],
                    ]);

                    $officeoverride_lastid = $officeonboardingoverride->id;
                    if ($this->companySettingtiers?->status) {
                        $tiers_id = isset($additional['tiers_id']) && $additional['tiers_id'] != '' ? $additional['tiers_id'] : 0;
                        $range = isset($additional['tiers_range']) && $additional['tiers_range'] != '' ? $additional['tiers_range'] : '';
                        if ($tiers_id > 0) {
                            if (is_array($range) && ! empty($range)) {
                                foreach ($range as $rang) {
                                    OnboardingOfficeOverrideTiersRange::create([
                                        'user_id' => $userId,
                                        'onboarding_office_override_id' => $officeoverride_lastid,
                                        'tiers_schema_id' => $additional['tiers_id'] ?? null,
                                        'tiers_levels_id' => $rang['id'] ?? null,
                                        'value' => $rang['value'] ?? null,
                                        'value_type' => $additional['overrides_type'] ?? null,
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }

        return response()->json([
            'ApiName' => 'add-onboarding_employee_override',
            'status' => true,
            'message' => 'Added Successfully.',
        ]);
    }

    public function employeeAgreement(Request $request): JsonResponse
    {
        try {
            $employee = OnboardingEmployees::find($request->user_id);
            if ($employee) {
                $agreement = $request->employee_agreement;
                $employee->probation_period = $agreement['probation_period'] ?? null;
                $employee->hiring_bonus_amount = $agreement['hiring_bonus_amount'] ?? null;
                $employee->date_to_be_paid = $agreement['date_to_be_paid'] ?? null;

                if (in_array(config('app.domain_name'), ['hawx', 'hawxw2', 'sstage', 'milestone'])) {

                    $startDate = isset($agreement['period_of_agreement']) ? $agreement['period_of_agreement'] : null;
                    $endDate = isset($agreement['end_date']) ? $agreement['end_date'] : null;

                    if (! empty($startDate) && ! empty($endDate)) {

                        $inSeason = seasonValidator($startDate, $endDate);

                        // Check if the start date and end date fall within the season range
                        if ($inSeason) {
                            // Dates are valid, proceed with setting the values
                            $employee->period_of_agreement_start_date = $startDate;
                            $employee->end_date = $endDate;
                        } else {

                            // Handle the case when dates are outside the allowed range
                            return response()->json([
                                'ApiName' => 'add-onboarding_employee_override',
                                'status' => false,
                                'message' => 'The dates must lie between October 1st and September 30th.',
                            ], 422);
                        }
                    } else {

                        return response()->json([
                            'ApiName' => 'add-onboarding_employee_override',
                            'status' => false,
                            'message' => 'Period of Agreement dates must lie between October 1st and September 30th.',
                        ], 422);
                    }
                } else {

                    $employee->period_of_agreement_start_date = $agreement['period_of_agreement'] ?? null;
                    $employee->end_date = $agreement['end_date'] ?? null;
                }

                if ((isset($request->is_new_contract) && $request->is_new_contract == 1)) {
                    // Check if there's already a new contract for the same date
                    $existingNewContract = OnboardingEmployees::where('user_id', $employee->user_id)
                        ->where('is_new_contract', 1)
                        ->where('period_of_agreement_start_date', $agreement['period_of_agreement'])
                        ->whereNotIn('status_id', [2, 3, 6, 11, 12, 13]) // Exclude cancelled/rejected/declined statuses
                        ->first();

                    if ($existingNewContract && ($request->user_id != $existingNewContract->id)) {
                        return response()->json([
                            'ApiName' => 'check-contract-overwrite',
                            'status' => false,
                            'message' => 'You cannot add a new contract for the same date. There is already a new contract scheduled for '.Carbon::parse($agreement['period_of_agreement'])->format('Y-m-d').'.',
                            'data' => [
                                'existing_contract_id' => $existingNewContract->id,
                                'existing_contract_status' => $existingNewContract->statusDetail->status ?? 'N/A',
                                'requested_date' => $agreement['period_of_agreement'],
                            ],
                        ], 400); // 409 Conflict status code
                    }
                }

                $employee->offer_include_bonus = $agreement['offer_include_bonus'] ? 1 : 0;
                $employee->offer_expiry_date = $agreement['offer_expiry_date'] ?? null;
                $employee->is_background_verificaton = ! empty($agreement['is_background_verificaton']) ? 1 : 0;
                $employee->save();

                user_activity_log('Employee hiring', 'Employee create', "Probation Period =>{$employee->probation_period}, Hiring Bonus Amount =>{$employee->hiring_bonus_amount}, Date to be paid =>{$employee->date_to_be_paid}, Period of agreement =>{$employee->period_of_agreement}, End date =>{$employee->end_date}, Offer expiry date =>{$employee->offer_expiry_date}, User Id =>{$employee->user_id}");

                $ViewData = OnboardingEmployees::select('id', 'first_name', 'last_name', 'email', 'mobile_no', 'state_id')
                    ->where('id', $request->user_id)
                    ->first();
                EventCalendar::where('user_id', $ViewData->id)->delete();
                EventCalendar::create([
                    'event_date' => $agreement['period_of_agreement'],
                    'type' => 'Hired',
                    'state_id' => $ViewData->state_id,
                    'user_id' => $ViewData->id,
                    'event_name' => 'Joining',
                    'description' => null,
                ]);

                $pdf = PDF::loadView('mail.pdf', [
                    'title' => "{$ViewData->first_name} {$ViewData->last_name}",
                    'email' => $ViewData->email,
                    'mobile_no' => $ViewData->mobile_no,
                ]);

                // Upload to S3 instead of local file system
                $fileName = "{$ViewData->first_name}-{$ViewData->last_name}_offer_letter.pdf";
                $filePath = config('app.domain_name').'/template/'.$fileName;
                $stored_bucket = 'private';
                
                $s3_return = s3_upload($filePath, $pdf->output(), false, $stored_bucket);
                
                if (isset($s3_return['status']) && $s3_return['status'] == true) {
                    $pdfPath = $s3_return['ObjectURL'];
                    
                    return response()->json([
                        'ApiName' => 'add-onboarding_employee_agreement',
                        'status' => true,
                        'message' => 'add Successfully.',
                        'pdf' => $pdfPath,
                    ], 200);
                } else {
                    return response()->json([
                        'ApiName' => 'add-onboarding_employee_agreement',
                        'status' => false,
                        'message' => 'Failed to upload PDF to S3.',
                    ], 500);
                }
            } else {
                return response()->json([
                    'ApiName' => 'add-onboarding_employee_agreement',
                    'status' => false,
                    'message' => 'User Not Found',
                ], 400);
            }
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'add-onboarding_employee_agreement',
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function sendOfferLetterToOnboardingEmployee(Request $request)
    {
        $ApiName = Route::currentRouteName();
        $status_code = 400;
        $status = false;
        $message = 'User not found invailid user id';
        $user_data = [];
        $response_array = [];
        $serverIP = URL::to('/');
        $response = [];
        $pdf_send_count = 0;
        $Document_Access_Password = '';
        $Document_list_is = '';
        $api_call_for = 'use';

        $Validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            // 'sub_position_id' => 'required|integer',
            'signing_screeen_url' => 'required',
            'name' => 'required',
        ]);

        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $all_request_data = $request->all();

        // return $all_request_data;

        try {
            $signing_screeen_url = $request->signing_screeen_url ?? '';
            $Onboarding_user_id = $request->user_id;
            $name = $request->name;
            $category_id = 1;

            $request_type = ($request->type ?? '') === 'resend' ? 'Resend' : 'Send';
            $send_documents_to_user = ($request->documents ?? '') !== 'all' ? 'Offer Letter' : 'All';

            $is_document_resend = $request_type === 'Resend' ? 1 : 0;

            if ($api_call_for === 'use') {
                $new_sequi_docs_signature_request_logs = NewSequiDocsSignatureRequestLog::create(['ApiName' => $ApiName]);
            }

            if (isset($new_sequi_docs_signature_request_logs->id)) {
                $new_sequi_docs_signature_request_logs->user_array = $all_request_data;
                $new_sequi_docs_signature_request_logs->save();
            }
            $Onboarding_Employees_data = OnboardingEmployees::where('id', $Onboarding_user_id)->get();

            OnboardingEmployees::where('id', $Onboarding_user_id)->update([
                'hiring_signature' => $name, // Replace 'field_name' and '$newValue' with actual column and value
            ]);
            $Onboarding_Employees_query = OnboardingEmployees::where('id', $Onboarding_user_id);
            $Onboarding_Employees_count = $Onboarding_Employees_query->count();
            $Onboarding_Employee_data_row = $Onboarding_Employees_query->first();

            if ($Onboarding_Employees_count != 0) {

                if ($Onboarding_Employee_data_row->status_id == 5) {

                    $offerExpiryDate = Carbon::parse($Onboarding_Employee_data_row->offer_expiry_date);
                    $tomorrow = Carbon::tomorrow();

                    if ($offerExpiryDate->lessThan($tomorrow)) {
                        $message = 'Offer Expiry Date should be in the future';
                    }

                    return response()->json([
                        'ApiName' => $ApiName,
                        'status' => $status,
                        'message' => $message,
                    ], $status_code);
                }

                $message = 'Offer letter not created for selected postion or offer letter deleted';

                $Onboarding_Employees_data = $Onboarding_Employees_query->get();
                $Onboarding_Employees_data_array = $Onboarding_Employees_data->toArray();
                $sub_position_id_array = array_column($Onboarding_Employees_data_array, 'sub_position_id');

                $newSequiDocsTemplatePermissionQuery = NewSequiDocsTemplatePermission::with('positionDetail:id,position_name')->with('NewSequiDocsTemplate:id,is_deleted,template_name,template_description')->whereIn('position_id', $sub_position_id_array)->wherehas('NewSequiDocsTemplate')->where('position_type', 'receipient')->where('category_id', $category_id);

                if ($is_document_resend == 1) {
                    $sentOfferLetter = SentOfferLetter::where('onboarding_employee_id', $Onboarding_Employee_data_row->id)->first();
                    if ($sentOfferLetter) {
                        $newSequiDocsTemplatePermissionQuery->where('template_id', $sentOfferLetter->template_id);
                    }
                } else {
                    $newSequiDocsTemplatePermissionQuery->when($request->has('template_id'), function ($query) use ($request) {
                        $query->where('template_id', $request->template_id);
                    });
                }

                $receipient_postion_template_id = $newSequiDocsTemplatePermissionQuery->get()->toArray();

                $offer_letter_templates = [];

                foreach ($receipient_postion_template_id as $receipient_postion_template_row) {
                    $template_id = $receipient_postion_template_row['template_id'];
                    $receipient_position_name = $receipient_postion_template_row['position_detail']['position_name'];
                    $SequiDocsTemplate = NewSequiDocsTemplate::with(['permission', 'receipient', 'categories', 'document_for_send_with_offer_letter'])->orderBy('id', 'asc')->where('id', $template_id)->first();

                    if ($SequiDocsTemplate != null && ! empty($SequiDocsTemplate)) {
                        $offer_letter_templates[$receipient_position_name] = $SequiDocsTemplate;
                    }
                }

                // return $offer_letter_templates;

                if (count($offer_letter_templates) > 0) {
                    $auth_user_data = Auth::user();
                    $Company_Profile_data = CompanyProfile::first();

                    // company data reslove key
                    $company_data_reslove_key = NewSequiDocsTemplate::company_data_reslove_key($Company_Profile_data, $auth_user_data);
                    $stored_bucket = $this->stored_bucket;

                    $pdf_send_count = 0;
                    foreach ($Onboarding_Employees_data as $user_index => $users_row) {
                        // $user_old_document =  NewSequiDocsDocument::where('user_id',$users_row['id'])->where('category_id', $category_id)->where('user_id_from','onboarding_employees')->where('is_active',1)->first();
                        // return $user_old_document;

                        $position_name = $users_row['positionDetail']['position_name'];
                        $user_email = $users_row['email'];
                        $offer_expiry_date = $users_row['offer_expiry_date'];

                        $email_arr = [];
                        $signer_user = [
                            // "user_id" => $users_row['id'],
                            'email' => $user_email,
                            'user_name' => $users_row['first_name'].' '.$users_row['last_name'],
                            'role' => 'employee',
                        ];

                        $response = [
                            'id' => $users_row['id'],
                            'user_ame' => $users_row['first_name'].' '.$users_row['last_name'],
                            'position_name' => $position_name,
                            'message' => 'Template not created for '.$position_name,
                            'status' => false,
                        ];

                        if (array_key_exists($position_name, $offer_letter_templates)) {
                            $Sequi_Docs_Template_data = $offer_letter_templates[$position_name];

                            $is_template_ready = $Sequi_Docs_Template_data->is_template_ready;
                            $category_id = $Sequi_Docs_Template_data->category_id;
                            $completed_step = $Sequi_Docs_Template_data->completed_step;
                            $message = "offer letter is not ready for send!! can't send it";
                            $response['message'] = $message;

                            if ($category_id == 1 && ($completed_step == 4 || $is_template_ready == 1)) {
                                $domain_setting = false;
                                $domain_error_on_email = [];
                                $message = "Domain setting isn't allowed to send e-mail on this domain.";
                                $response['message'] = $message;

                                $email_arr[] = $signer_user; // email array for check domain setting
                                $final_email_array_for_send_mail = [];  // email array for send document
                                unset($final_email_array_for_send_mail);  // to delete all:
                                foreach ($email_arr as $email_row) {
                                    $email = $email_row['email'];
                                    $emailId = explode('@', $email);
                                    $user_email_for_send_email = $email;
                                    $check_domain_setting = DomainSetting::check_domain_setting($user_email_for_send_email);
                                    if ($check_domain_setting['status'] == true) {
                                        $final_email_array_for_send_mail[] = $email_row;
                                        $domain_setting = true;
                                    } else {
                                        array_push($domain_error_on_email, $email);
                                    }
                                }

                                $send_document_final_array = [];
                                if ($domain_setting) {
                                    $send_document_array = [
                                        'status' => false,
                                        'dcument_other_details' => [],
                                        'pdf_detail_arr' => [],
                                    ];

                                    $template_name = ucwords($Sequi_Docs_Template_data->template_name);
                                    $template_description = $Sequi_Docs_Template_data->template_description;
                                    $template_content = $Sequi_Docs_Template_data->template_content;
                                    $is_pdf = $Sequi_Docs_Template_data->is_pdf;

                                    $send_reminder = $Sequi_Docs_Template_data->send_reminder;
                                    $reminder_in_days = $Sequi_Docs_Template_data->reminder_in_days;
                                    $reminder_in_days = $Sequi_Docs_Template_data->reminder_in_days;
                                    $max_reminder_times = $Sequi_Docs_Template_data->max_reminder_times;
                                    $is_sign_required_for_hire = $Sequi_Docs_Template_data->recipient_sign_req;

                                    $email_subject = $Sequi_Docs_Template_data->email_subject;
                                    $email_content = $Sequi_Docs_Template_data->email_content;
                                    $pdf_file_other_parameter = $Sequi_Docs_Template_data->pdf_file_other_parameter;

                                    $categories = $Sequi_Docs_Template_data->categories;
                                    $to_send_template_id = $Sequi_Docs_Template_data->id;

                                    $category_array = [];
                                    $category_array['id'] = $categories->id;
                                    $category_array['categories'] = $categories->categories;
                                    $category_array['category_type'] = $categories->category_type;

                                    // offer letter pdf details
                                    $pdf_detail_arr = [
                                        'pdf_path' => '',
                                        'is_pdf' => 0,
                                        'pdf_file_other_parameter' => $pdf_file_other_parameter,
                                        'is_sign_required_for_hire' => $is_sign_required_for_hire,
                                        'template_name' => $template_name,
                                        'offer_expiry_date' => $offer_expiry_date,
                                        'is_post_hiring_document' => 0,
                                        'is_document_for_upload' => 0,
                                        'category_id' => $category_array['id'],
                                        'category' => $category_array['categories'],
                                        'category_type' => $category_array['category_type'],
                                        'upload_by_user' => 0,
                                        'signer_array' => [],
                                    ];

                                    // offer letter document other details
                                    $dcument_other_details = [
                                        'template_id' => $to_send_template_id,
                                        'send_reminder' => $send_reminder,
                                        'offer_expiry_date' => $offer_expiry_date,
                                        'reminder_in_days' => $reminder_in_days,
                                        'max_reminder_times' => $max_reminder_times,
                                        'is_sign_required_for_hire' => $is_sign_required_for_hire,
                                        'is_document_for_upload' => 0,
                                        'category_array' => $category_array,
                                    ];

                                    // other required data for send document with offer letter document_for_send_with_offer_letter
                                    $other_required_data = [
                                        'users_row' => $users_row,
                                        'offer_expiry_date' => $offer_expiry_date,
                                        'auth_user_data' => $auth_user_data,
                                        'signer_array' => $final_email_array_for_send_mail,
                                        'Company_Profile_data' => $Company_Profile_data,
                                        'api_call_for' => $api_call_for,
                                    ];

                                    // Document for send with offer letter
                                    $document_for_send_with_offer_letter = $Sequi_Docs_Template_data->document_for_send_with_offer_letter;

                                    // $send_document_with_offer_letter_response = $this->send_document_with_offer_letter($document_for_send_with_offer_letter , $other_required_data);
                                    // return $send_document_with_offer_letter_response;

                                    $send_mail_is_true = false;

                                    $message = 'something went wrong!!! Template pdf not found for send';
                                    $response['message'] = $message;

                                    $Document_Type = $Sequi_Docs_Template_data->categories->categories;
                                    $Document_Type = rtrim($Document_Type, 's');
                                    $html = (isset($template_content)) ? $template_content : null;
                                    $user_data_reslove_key = NewSequiDocsTemplate::user_data_reslove_key($users_row, $auth_user_data);

                                    // resloving Document Html content
                                    $template_name_is = isset($template_name) ? str_replace(' ', '_', $template_name) : 'Template';
                                    $string = NewSequiDocsTemplate::resolve_documents_content($html, $users_row, $auth_user_data, $Company_Profile_data);
                                    $generateTemplate = $template_name_is.'_'.date('m_d_Y').'_'.time().'.pdf';
                                    $template_document_is = 'template/'.$generateTemplate;

                                    $pdf = Pdf::loadHTML($string, 'UTF-8');
                                    // file_put_contents($template_document_is, $pdf->setPaper('A4','portrait')->output());
                                    $filePath = config('app.domain_name').'/'.$template_document_is;
                                    $file_link = $serverIP.'/'.$template_document_is;

                                    $s3_return = s3_upload($filePath, $pdf->setPaper('A4', 'portrait')->output(), false, $stored_bucket);
                                    if (isset($s3_return['status']) && $s3_return['status'] == true) {
                                        $file_link = $s3_return['ObjectURL'];
                                        $send_mail_is_true = true;
                                    }

                                    if ($send_mail_is_true == true) {
                                        $send_document_array['status'] = true;
                                        $pdf_detail_arr['pdf_path'] = $file_link;
                                        $pdf_detail_arr['signer_array'] = $final_email_array_for_send_mail;

                                        $send_document_array['dcument_other_details'] = $dcument_other_details;
                                        $send_document_array['pdf_detail_arr'] = $pdf_detail_arr;

                                        // getting other documents with offer letter if offer letter send 1st time or resend with all docs
                                        if (($send_documents_to_user == 'All' && $is_document_resend == 1) || $is_document_resend == 0) {
                                            $send_document_with_offer_letter_response = $this->send_document_with_offer_letter($document_for_send_with_offer_letter, $other_required_data);
                                        } else {
                                            $send_document_with_offer_letter_response = ['response' => [], 'status_code' => 200];
                                        }

                                        if ($send_document_with_offer_letter_response['status_code'] == 200) {
                                            $response_data = $send_document_with_offer_letter_response['response'];

                                            if (count($response_data) > 0) {
                                                array_unshift($response_data, $send_document_array);
                                                $send_document_final_array = $response_data;
                                            } else {
                                                $send_document_final_array[] = $send_document_array;
                                            }
                                        } else {
                                            // Error while adding additional Docs.
                                            $message = 'something went wrong!!';
                                            $error = $send_document_with_offer_letter_response['error'];
                                            $errorDetail = $send_document_with_offer_letter_response['errorDetail'];

                                            return response()->json(['message' => $message, 'error' => $error,  'errorDetail' => $errorDetail], 400);
                                        }

                                        // dd($is_document_resend);
                                        if ($api_call_for != 'test' && $is_document_resend == 0) {
                                            $envelope_data = $this->createEnvelope();
                                            if (isset($new_sequi_docs_signature_request_logs->id)) {
                                                $new_sequi_docs_signature_request_logs->envelope_data = $envelope_data;
                                                $new_sequi_docs_signature_request_logs->save();
                                            }
                                        } elseif ($api_call_for != 'test' && $is_document_resend == 1) {
                                            $user_old_document = NewSequiDocsDocument::where('user_id', $users_row['id'])->where('category_id', $category_id)->where('user_id_from', 'onboarding_employees')->where('is_active', 1)
                                                ->first();

                                            // dd($user_old_document);

                                            // dump('user_id'.$users_row['id']);
                                            // dump('category_id'.$category_id);

                                            // dd($user_old_document);

                                            // dump($user_old_document);

                                            if ($user_old_document) {

                                                $envelope_data = Envelope::find($user_old_document->envelope_id);

                                                // dd($envelope_data);

                                                // dd($user_old_document->imported_from_olasdasd);

                                                // dd($user_old_document->imported_from_old);
                                                if (! $envelope_data && $user_old_document->imported_from_old == 1) {

                                                    // create envelope to resennd from new sequidoc signing system
                                                    $envelope_data = $this->createEnvelope();
                                                }

                                                if (isset($new_sequi_docs_signature_request_logs->id)) {
                                                    $new_sequi_docs_signature_request_logs->envelope_data = $envelope_data;
                                                    $new_sequi_docs_signature_request_logs->save();
                                                }
                                            }
                                        }

                                        // dd($user_old_document);

                                        $message = 'signature request not send!!';
                                        $response['message'] = $message;

                                        $Document_list_is = '';
                                        $allow_mail_send = false;

                                        if (isset($new_sequi_docs_signature_request_logs->id)) {
                                            $new_sequi_docs_signature_request_logs->send_document_final_array = $send_document_final_array;
                                            $new_sequi_docs_signature_request_logs->save();
                                        }
                                        $signature_request_response = [];
                                        $false_signature_request_response = [];
                                        // dd($send_document_final_array);
                                        foreach ($send_document_final_array as $send_document_row) {
                                            if ($send_document_row['status'] == true) {
                                                $dcument_other_details = $send_document_row['dcument_other_details'];
                                                $pdf_detail_arr = $send_document_row['pdf_detail_arr'];
                                                $pdf_detail_arr['categories'] = $dcument_other_details['category_array'];

                                                $template_id = $dcument_other_details['template_id'];
                                                $send_reminder = $dcument_other_details['send_reminder'];
                                                $reminder_in_days = $dcument_other_details['reminder_in_days'];
                                                $max_reminder_times = $dcument_other_details['max_reminder_times'];
                                                $is_sign_required_for_hire = $dcument_other_details['is_sign_required_for_hire'];
                                                $categories_array = $dcument_other_details['category_array'];

                                                $category_id = isset($categories_array['id']) ? $categories_array['id'] : null;
                                                // $categories = isset($categories_array['categories']) ? $categories_array['categories'] : null;

                                                $template_name = $pdf_detail_arr['template_name'];
                                                $is_post_hiring_document = $pdf_detail_arr['is_post_hiring_document'];
                                                $pdf_path = $pdf_detail_arr['pdf_path'];

                                                $is_document_for_upload = $pdf_detail_arr['is_document_for_upload'];
                                                $upload_by_user = $pdf_detail_arr['upload_by_user'];

                                                $document_uploaded_type = 'secui_doc_uploaded';
                                                $upload_document_type_id = null;
                                                if ($is_document_for_upload == 1) {
                                                    $document_uploaded_type = 'manual_doc';
                                                    $manualDocType = NewSequiDocsUploadDocumentType::where([
                                                        'is_deleted' => 0,
                                                        'document_name' => $pdf_detail_arr['template_name'],
                                                    ])->first();
                                                    // for manula doc
                                                    if ($manualDocType) {
                                                        $upload_document_type_id = $manualDocType->id;
                                                    }
                                                }

                                                if ($signing_screeen_url == '') {
                                                    $signing_screeen_url = $pdf_path;
                                                }

                                                if ($api_call_for != 'test') {
                                                    // dd($envelope_data);
                                                    $response['message'] = 'signature request Envelop not created!! signature request not send!!';
                                                    if (! empty($envelope_data) && $envelope_data != null) {

                                                        $envelope_id = $envelope_data->id;
                                                        $envelope_name = $envelope_data->envelope_name;
                                                        $envelope_password = $Document_Access_Password = $envelope_data->plain_password;

                                                        $signing_screeen_url = config('signserver.signScreenUrl').'/'.$Document_Access_Password;
                                                        $Review_Document_Link = $signing_screeen_url;

                                                        // sending only offer letter with same envelope_id and envelope_password at the time of resend
                                                        // dump($send_documents_to_user);
                                                        // dd($is_document_resend);
                                                        if ($send_documents_to_user != 'All' && $is_document_resend == 1) {
                                                            // dd('insie');
                                                            $user_old_document = NewSequiDocsDocument::where('user_id', $users_row['id'])->where('category_id', $category_id)->where('user_id_from', 'onboarding_employees')->where('is_active', 1)
                                                                ->first();

                                                            // dd($user_old_document);

                                                            if ($user_old_document != null && $user_old_document != '') {
                                                                // dd('here overwrite');
                                                                if ($user_old_document->imported_from_old == 0) {

                                                                    $envelope_id = $user_old_document->envelope_id;
                                                                    $envelope_password = $Document_Access_Password = $user_old_document->envelope_password;
                                                                }
                                                            }
                                                        }

                                                        // dd($envelope_id);

                                                        if ($is_document_for_upload == 0) {
                                                            $addDocumentsInToEnvelope = $this->addDocumentsInToEnvelope($envelope_id, [$pdf_detail_arr]);
                                                        } else {
                                                            $addDocumentsInToEnvelope = ['status' => true, 'is_document_for_upload' => $is_document_for_upload];
                                                        }

                                                        // $addDocumentsInToEnvelope =  $this->addDocumentsInToEnvelope($envelope_id,[$pdf_detail_arr]);
                                                        $signature_request_response[] = ['template_name' => $template_name, 'pdf_detail_arr' => [$pdf_detail_arr], 'addDocumentsInToEnvelope' => $addDocumentsInToEnvelope];

                                                        // dd($signature_request_response);

                                                        if (isset($new_sequi_docs_signature_request_logs->id)) {
                                                            $new_sequi_docs_signature_request_logs->signature_request_response = $signature_request_response;
                                                            $new_sequi_docs_signature_request_logs->save();
                                                        }

                                                        if ($addDocumentsInToEnvelope['status'] == false) {
                                                            $false_signature_request_response[] = [
                                                                'pdf_detail_arr' => [$pdf_detail_arr],
                                                                'addDocumentsInToEnvelope' => $addDocumentsInToEnvelope,
                                                            ];
                                                            $response['false_signature_request_response'] = $false_signature_request_response;
                                                        }

                                                        if ((isset($addDocumentsInToEnvelope['status']) && $addDocumentsInToEnvelope['status'] == true) || $is_document_for_upload == 1) {

                                                            $signature_request_id = isset($addDocumentsInToEnvelope['signature_request_id']) ? $addDocumentsInToEnvelope['signature_request_id'] : null;
                                                            $documnet = isset($addDocumentsInToEnvelope['documnet']) ? $addDocumentsInToEnvelope['documnet'] : null;
                                                            $signature_request_document_id = isset($documnet[0]['signature_request_document_id']) ? $documnet[0]['signature_request_document_id'] : null;

                                                            // inactive old document
                                                            // NewSequiDocsDocument::where('user_id',$users_row['id'])->where('document_uploaded_type',$document_uploaded_type)->where('user_id_from','users')->where('is_active',1)->where('template_id',$template_id)->where('category_id',$category_id)->update(['is_active' => 0, 'document_inactive_date' => NOW()]);
                                                            // dd('ins');
                                                            NewSequiDocsDocument::where('user_id', $users_row['id'])->where('document_uploaded_type', $document_uploaded_type)->where('user_id_from', 'onboarding_employees')->where('is_active', 1)
                                                                ->where(function ($query) use ($template_id, $category_id) {
                                                                    $query->where(function ($query) use ($template_id, $category_id) {
                                                                        $query->where('template_id', $template_id)
                                                                            ->where('category_id', $category_id)
                                                                            ->whereNull('upload_document_type_id');
                                                                    })
                                                                        ->orWhere(function ($query) use ($template_id) {
                                                                            $query->where('upload_document_type_id', $template_id)
                                                                                ->where(function ($query) {
                                                                                    $query->where('category_id', 0)
                                                                                        ->orWhereNull('category_id');
                                                                                });
                                                                        });
                                                                })
                                                                ->update(['is_active' => 0, 'document_inactive_date' => NOW()]);

                                                            $signed_status = 0;
                                                            $create_NewSequiDocsDocument = new NewSequiDocsDocument;

                                                            $create_NewSequiDocsDocument->user_id = $users_row['id'];
                                                            $create_NewSequiDocsDocument->user_id_from = 'onboarding_employees';
                                                            $create_NewSequiDocsDocument->template_id = $template_id;
                                                            $create_NewSequiDocsDocument->category_id = $category_id;
                                                            $create_NewSequiDocsDocument->description = $template_name;
                                                            $create_NewSequiDocsDocument->is_active = 1;
                                                            $create_NewSequiDocsDocument->send_by = $auth_user_data['id'];
                                                            $create_NewSequiDocsDocument->upload_document_type_id = $upload_document_type_id;
                                                            $create_NewSequiDocsDocument->is_document_resend = $is_document_resend;

                                                            $create_NewSequiDocsDocument->un_signed_document = $pdf_path;
                                                            $create_NewSequiDocsDocument->document_send_date = now();
                                                            $create_NewSequiDocsDocument->document_response_status = 0;
                                                            $create_NewSequiDocsDocument->document_uploaded_type = $document_uploaded_type;

                                                            $create_NewSequiDocsDocument->envelope_id = $envelope_id;
                                                            $create_NewSequiDocsDocument->envelope_password = $envelope_password;
                                                            $create_NewSequiDocsDocument->signature_request_id = $signature_request_id;
                                                            $create_NewSequiDocsDocument->signature_request_document_id = $signature_request_document_id;
                                                            $create_NewSequiDocsDocument->signed_status = $signed_status;
                                                            $create_NewSequiDocsDocument->is_post_hiring_document = $is_post_hiring_document;
                                                            $create_NewSequiDocsDocument->is_sign_required_for_hire = $is_sign_required_for_hire;

                                                            // reminder data is_post_hiring_document
                                                            $create_NewSequiDocsDocument->send_reminder = $send_reminder;
                                                            $create_NewSequiDocsDocument->reminder_in_days = $reminder_in_days;
                                                            $create_NewSequiDocsDocument->max_reminder_times = $max_reminder_times;
                                                            $is_new_document_created = $create_NewSequiDocsDocument->save();

                                                            // dd($is_new_document_created);

                                                            $message = 'something went wrong!! Document not saved';
                                                            if ($category_id == 1) {
                                                                $allow_mail_send = true;
                                                                $status_id = $is_document_resend == 1 ? 12 : 4;
                                                                $update_OnboardingEmployees = OnboardingEmployees::find($users_row['id']);
                                                                $update_OnboardingEmployees->status_id = $status_id;
                                                                $update_OnboardingEmployees->document_id = $signature_request_document_id;
                                                                $update_OnboardingEmployees->save();
                                                            }
                                                            if ($is_new_document_created && $is_post_hiring_document != 1) {
                                                                if ($is_sign_required_for_hire == 1) {

                                                                    $Document_list_is .= '<li>'.$template_name.'<span style="color:red"> * </span></li>';
                                                                } else {
                                                                    $Document_list_is .= '<li>'.$template_name.'</li>';
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        // Update Pipeline-onboarding status after Offer Letter Send
                                        $sendOfferLetterStatus = HiringStatus::where('id', 4)->first();
                                        $onboardingEmployeesStatus = OnboardingEmployees::find($users_row['id']);
                                        if ($sendOfferLetterStatus != null && $onboardingEmployeesStatus != null) {
                                            $onboardingEmployeesStatus->status_id = $sendOfferLetterStatus->id ?? $onboardingEmployeesStatus->status_id;
                                            $onboardingEmployeesStatus->save();
                                        }

                                        // dd($allow_mail_send);
                                        // return $Document_list_is;
                                        if ($allow_mail_send == true) {
                                            // $signing_screeen_url = $signing_screeen_url
                                            foreach ($final_email_array_for_send_mail as $signer) {
                                                $email_template_data['email'] = $signer['email'];
                                                $Review_Document_Link = $signing_screeen_url;

                                                $document_email_format = NewSequiDocsTemplate::resolve_email_content($email_content, $users_row, $auth_user_data, $Company_Profile_data);
                                                $document_email_format = str_replace('[Review_Document_Link]', $Review_Document_Link, $document_email_format);
                                                $document_email_format = str_replace('[Document_list_is]', $Document_list_is, $document_email_format);
                                                $document_email_format = str_replace('[Document_Access_Password]', $Document_Access_Password, $document_email_format);

                                                if ($is_document_resend == 1) {
                                                    $document_email_format = str_replace('has sent an Offer', 'has re-sent an Offer', $document_email_format);
                                                }

                                                $email_template_data['subject'] = $email_subject;
                                                $email_template_data['template'] = $document_email_format;
                                                $email_response = $this->sendEmailNotification($email_template_data);
                                                // dd($email_response);

                                                /** Send background verification mail */
                                                // $sClearanceConfiguration = SClearanceConfiguration::where('position_id', $users_row['position_id'])->first();
                                                // $isPostHiring = 0;
                                                // if($sClearanceConfiguration!=NULL && isset($sClearanceConfiguration->hiring_status) && $sClearanceConfiguration->hiring_status==2){
                                                //     $isPostHiring = 1;
                                                // }
                                                // if($users_row['is_background_verificaton'] == 1 && $isPostHiring==0){
                                                if ($users_row['is_background_verificaton'] == 1) {
                                                    $configurationDetails = SClearanceConfiguration::where('position_id', $users_row['position_id'])->where('hiring_status', 1)->orWhere('position_id', $users_row['sub_position_id'])->first();
                                                    if (empty($configurationDetails)) {
                                                        $configurationDetails = SClearanceConfiguration::where(['position_id' => null])->first();
                                                    }
                                                    if (! empty($configurationDetails)) {
                                                        if ($configurationDetails->hiring_status == 1) {
                                                            $parsedUrl = parse_url($request->signing_screeen_url);
                                                            $frontendUrl = $parsedUrl['scheme'].'://'.$parsedUrl['host'];
                                                            $screeningRequest = SClearanceTurnScreeningRequestList::where(['email' => $users_row['email']])->first();
                                                            if (! $screeningRequest) {
                                                                $package_id = $configurationDetails->package_id;
                                                                $srRequestSave = SClearanceTurnScreeningRequestList::create([
                                                                    'email' => $users_row['email'],
                                                                    'user_type' => 'Onboarding',
                                                                    'user_type_id' => $users_row['id'],
                                                                    'position_id' => $users_row['position_id'],
                                                                    'office_id' => $users_row['office_id'],
                                                                    'first_name' => $users_row['first_name'],
                                                                    'middle_name' => @$users_row['middle_name'],
                                                                    'last_name' => $users_row['last_name'],
                                                                    'package_id' => $package_id,
                                                                    'description' => 'Background Check',
                                                                    'status' => 'emailed',
                                                                ]);
                                                                $srRequestSave->save();
                                                                $request_id = $srRequestSave->id;
                                                            } else {
                                                                $request_id = $screeningRequest->id;
                                                            }

                                                            $mailData['subject'] = 'Request for Background Check';
                                                            $mailData['email'] = $users_row['email'];
                                                            $mailData['request_id'] = $request_id;
                                                            $encryptedRequestId = encryptData($request_id);
                                                            $mailData['encrypted_request_id'] = $encryptedRequestId;
                                                            $mailData['url'] = $frontendUrl;
                                                            $mailData['template'] = view('mail.backgroundCheckMail', compact('mailData'));
                                                            $this->sendEmailNotification($mailData);
                                                        }
                                                    }
                                                }

                                                $response['email_response'] = $email_response;
                                                $response['Document_Access_Password'] = $Document_Access_Password;
                                                // $response['document_email_format'] = $document_email_format;
                                                if (gettype($email_response) == 'string') {
                                                    $email_response = json_decode($email_response, true);
                                                }

                                                // object
                                                // dump($email_response);
                                                // dd($email_response->headers);

                                                if (gettype($email_response) == 'array' && isset($email_response['errors'])) {
                                                    $response['message'] = $email_response['errors'][0];
                                                } else {
                                                    $status_code = 200;
                                                    $status = true;
                                                    $response['status'] = true;
                                                    $pdf_send_count++;
                                                    $message = 'pdf send in Mail';
                                                    $response['message'] = $message;
                                                }

                                                /************  hubspot code starts here **************** */
                                                if (isset($Onboarding_Employees_data_array[0]['id'])) {

                                                    // $OnboardingEmployees_status = $template_name_is." ".$request_type;
                                                    $OnboardingEmployees_status = 'Offer Letter sent';
                                                    $onboardingEmpID = $Onboarding_Employees_data_array[0]['id'];
                                                    $onboardingEmployee = OnboardingEmployees::find($onboardingEmpID);
                                                    $userId = Auth()->user();
                                                    $recruiter_id = ($userId->is_super_admin == 0) ? $userId->id : null;
                                                    $CrmData = Crms::where('id', 2)->where('status', 1)->first();
                                                    $CrmSetting = CrmSetting::where('crm_id', 2)->first();
                                                    if (! empty($CrmData) && ! empty($CrmSetting)) {
                                                        $val = json_decode($CrmSetting['value']);
                                                        $token = $val->api_key;
                                                        $onboardingEmployee->status = $OnboardingEmployees_status;
                                                        $hubspotSaleDataCreate = $this->hubspotOnboardemployee($onboardingEmployee, $recruiter_id, $token);
                                                    }
                                                }
                                            }
                                        }
                                        // return $response;
                                    }
                                } else {
                                    /* Send Notification to SA about Domain Configurations */
                                    $errorDetails = [
                                        'message' => "The email couldn't be sent due to Domain settings not allowing to send email, Please check the domain configurations and try to send email again.",
                                        'domain_name' => '',
                                        'recipient_email' => $domain_error_on_email,
                                    ];
                                    $this->sendEmailErrorNotificationToSA($errorDetails, 'domain_error');
                                }
                            }
                        }
                        // return $response;
                        $response_array[$user_index] = $response;
                    }
                }
            }
        } catch (Exception $error) {
            Log::debug($error);
            $message = 'something went wrong!!';
            $error_message = $error->getMessage();
            $File = $error->getFile();
            $Line = $error->getLine();
            $Code = $error->getCode();
            $Trace = $error->getTraceAsString();
            $errorDetail = [
                'error_message' => $error_message,
                'File' => $File,
                'Line' => $Line,
                'Code' => $Code,
            ];

            return response()->json(['error' => $error, 'message' => $message, 'errorDetail' => $errorDetail], 400);
        }

        if ($status == true) {
            // $message = "mail send to $pdf_send_count out of ".count($users_data)." users";
        }

        return response()->json([
            'ApiName' => $ApiName,
            'status' => $status,
            'message' => $message,
            'response_array' => $response_array,
            'other_data' => [
                'pdf_send_count' => $pdf_send_count,
                'Document_list_is' => $Document_list_is,
            ],
        ], $status_code);
    }

    public function directHiredEmployee(Request $request, $authUserId = null, $rehire = false): JsonResponse
    {

        try {
            DB::beginTransaction();
            $randPassForUsers = randPassForUsers();
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:onboarding_employees,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            if (isset($request->rehire)) {
                $rehire = true;
            }

            $name = $request->name;
            $onbardingUserId = $request->user_id;
            $onBoardingEmployee = OnboardingEmployees::with('positionDetail')->where('id', $onbardingUserId)->first();
            if (! $onBoardingEmployee) {
                return response()->json([
                    'ApiName' => 'directHiredEmployee',
                    'status' => false,
                    'message' => 'User Not found',
                ], 400);
            }

            OnboardingEmployees::where('id', $onbardingUserId)->update([
                'hiring_signature' => $name ?? '',
                'is_background_verificaton' => 0, // BV is not required for direct hiring
            ]);
            $uid = null;
            $groupId = $onBoardingEmployee->positionDetail->group_id;
            if ($authUserId && $authUserId > 0) {
                $userId = User::find($authUserId);
                $uid = ($userId->is_super_admin == 0) ? $userId->id : null;
            } else {
                $userId = Auth()->user();
                if ($userId) {
                    $uid = ($userId->is_super_admin == 0) ? $userId->id : null;
                }
            }
            if (! $authUserId) {
                $auth = Auth()->user();
                if ($auth) {
                    $authUserId = $auth->id;
                }
            }
            $substr = 0;
            if ($onBoardingEmployee) {
                $usereEmail = User::where('email', $onBoardingEmployee->email)->where('rehire', '!=', 1)->first();
                if (! $usereEmail) {
                    $additionalUserId = UsersAdditionalEmail::where('email', $onBoardingEmployee->email)->value('user_id');
                    if (! empty($additionalUserId)) {
                        $usereEmail = User::where('id', $additionalUserId)->where('rehire', '!=', 1)->first();
                    }
                }
                if ($usereEmail && ! $usereEmail->isTodayTerminated()) {
                    return response()->json([
                        'ApiName' => 'directHiredEmployee',
                        'status' => false,
                        'message' => 'Email is already exist!!',
                    ], 400);
                }

                $userMobileNo = User::where('mobile_no', $onBoardingEmployee->mobile_no)->where('rehire', '!=', 1)->first();
                if ($userMobileNo && ! $userMobileNo->isTodayTerminated()) {
                    return response()->json([
                        'ApiName' => 'directHiredEmployee',
                        'status' => false,
                        'message' => 'Mobile no is already exist!!',
                    ], 400);
                }

                $companyProfile = CompanyProfile::first();
                $effectiveDate = $onBoardingEmployee->period_of_agreement_start_date;
                $userDataToCreate = [
                    'aveyo_hs_id' => $onBoardingEmployee->aveyo_hs_id,
                    'first_name' => $onBoardingEmployee->first_name,
                    'last_name' => $onBoardingEmployee->last_name,
                    'email' => $onBoardingEmployee->email,
                    'mobile_no' => $onBoardingEmployee->mobile_no,
                    'state_id' => $onBoardingEmployee->state_id,
                    'city_id' => $onBoardingEmployee->city_id,
                    'self_gen_accounts' => $onBoardingEmployee->self_gen_accounts,
                    'self_gen_type' => $onBoardingEmployee->self_gen_type,
                    'department_id' => isset($onBoardingEmployee->department_id) ? $onBoardingEmployee->department_id : null,
                    'position_id' => $onBoardingEmployee->position_id,
                    'sub_position_id' => $onBoardingEmployee->sub_position_id,
                    'is_manager' => $onBoardingEmployee->is_manager,
                    'is_manager_effective_date' => ($onBoardingEmployee->is_manager == 1) ? $effectiveDate : null,
                    'manager_id' => $onBoardingEmployee->manager_id,
                    'manager_id_effective_date' => $effectiveDate,
                    'team_id' => $onBoardingEmployee->team_id,
                    'team_id_effective_date' => (! empty($onBoardingEmployee->team_id)) ? $effectiveDate : null,
                    'recruiter_id' => isset($onBoardingEmployee->recruiter_id) ? $onBoardingEmployee->recruiter_id : $uid,
                    'group_id' => $groupId,
                    'probation_period' => $onBoardingEmployee->probation_period,
                    'hiring_bonus_amount' => $onBoardingEmployee->hiring_bonus_amount,
                    'date_to_be_paid' => $onBoardingEmployee->date_to_be_paid,
                    'period_of_agreement_start_date' => $effectiveDate,
                    'end_date' => $onBoardingEmployee->end_date,
                    'offer_include_bonus' => $onBoardingEmployee->offer_include_bonus,
                    'offer_expiry_date' => $onBoardingEmployee->offer_expiry_date,
                    'office_id' => $onBoardingEmployee->office_id,
                    // 'password' => Hash::make('Newuser#123'),
                    'password' => $randPassForUsers['password'],
                    'status_id' => 1,
                    'commission_effective_date' => $effectiveDate,
                    'self_gen_commission_effective_date' => $effectiveDate,
                    'upfront_effective_date' => $effectiveDate,
                    'self_gen_upfront_effective_date' => $effectiveDate,
                    'withheld_effective_date' => $effectiveDate,
                    'self_gen_withheld_effective_date' => $effectiveDate,
                    'override_effective_date' => $effectiveDate,
                    'position_id_effective_date' => $effectiveDate,
                    'worker_type' => $onBoardingEmployee->positionDetail->worker_type,
                    'pay_type' => $onBoardingEmployee->pay_type,
                    'pay_rate' => $onBoardingEmployee->pay_rate,
                    'pay_rate_type' => $onBoardingEmployee->pay_rate_type,
                    'expected_weekly_hours' => $onBoardingEmployee->expected_weekly_hours,
                    'overtime_rate' => $onBoardingEmployee->overtime_rate,
                    'pto_hours' => $onBoardingEmployee->pto_hours,
                    'unused_pto_expires' => $onBoardingEmployee->unused_pto_expires,
                    'onboardProcess' => 0,
                    'rehire' => 0,
                    'employee_admin_only_fields' => $onBoardingEmployee->employee_admin_only_fields ?? null,
                ];

                // for re-hiring employee
                if ($rehire) {
                    $oldUser = User::where('id', $onBoardingEmployee->user_id)->first();

                    if ($oldUser) {
                        $userDataToCreate['contract_ended'] = 0;
                        $oldUser->update($userDataToCreate);
                        $oldUser->refresh();
                        $data = $oldUser;
                    }
                } else {
                    $data = User::create($userDataToCreate);
                }

                // Check if HighLevel integration is enabled
                $integration = Integration::where(['name' => 'GoHighLevel', 'status' => 1])->first();
                if ($integration) {
                    if (config('services.highlevel.token')) {
                        // Push employee data to HighLevel
                        $highLevelResponse = $this->saveEmployeeToHighLevel($data);

                        // The saveEmployeeToHighLevel method now handles updating the aveyo_hs_id field
                        // We just need to log the result for monitoring
                        if ($highLevelResponse) {
                            $contactId = $highLevelResponse['contact']['id'] ?? null;
                            Log::info('Employee successfully synced to HighLevel', [
                                'employee_id' => $data->id,
                                'highlevel_contact_id' => $contactId,
                                'is_new_contact' => $highLevelResponse['new'] ?? false,
                            ]);
                        } else {
                            Log::error('Failed to sync employee to HighLevel', [
                                'employee_id' => $data->id,
                            ]);
                        }
                    }
                }
                // End HighLevel integration

                $userId = $data->id;

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {

                    // Implements the field routes API for sending onboarding employee data
                    $integration = Integration::where(['name' => 'FieldRoutes', 'status' => 1])->first();

                    if (! empty($integration)) {

                        $enc_value = openssl_decrypt(
                            $integration->value,
                            config('app.encryption_cipher_algo'),
                            config('app.encryption_key'),
                            0,
                            config('app.encryption_iv')
                        );
                        $dnc_value = json_decode($enc_value);

                        if (!$dnc_value || !isset($dnc_value->authenticationKey) || !isset($dnc_value->authenticationToken)) {
                            Log::error('FieldRoutes configuration is invalid or incomplete in V2 directHiredEmployee', [
                                'onboarding_employee_id' => $onbardingUserId,
                                'user_id' => $userId ?? null,
                                'integration_id' => $integration->id ?? null,
                                'decryption_success' => $enc_value !== false,
                            ]);
                        } else {
                            $authenticationKey = $dnc_value->authenticationKey;
                            $authenticationToken = $dnc_value->authenticationToken;
                            $baseURL = $dnc_value->base_url;
                            $api_office = $dnc_value->office;
                            $checkStatus = 'Onboarding';
                            $this->fieldRoutesCreateEmployee($data, $checkStatus, $uid, $authenticationKey, $authenticationToken, $baseURL);
                        }
                    }
                    // End here

                }

                if (! $rehire) {
                    try {
                        $proposedEmployeeId = DB::transaction(function () use ($userId) {
                            $empId = EmployeeIdSetting::orderBy('id', 'asc')->first();
                            $idCode = !empty($empId) ? $empId->id_code : 'EMP';
                            
                            // Lock the table to prevent concurrent access
                            User::where('employee_id', 'like', $idCode.'%')
                                ->whereNotNull('employee_id')
                                ->lockForUpdate()
                                ->get();
                            
                            // Get the highest existing employee_id numeric value
                            $maxNumericValue = User::where('employee_id', 'like', $idCode.'%')
                                ->whereNotNull('employee_id')
                                ->where('id', '!=', $userId)
                                ->selectRaw('CAST(SUBSTRING(employee_id, ?) AS UNSIGNED) as num', [strlen($idCode) + 1])
                                ->orderByRaw('CAST(SUBSTRING(employee_id, ?) AS UNSIGNED) DESC', [strlen($idCode) + 1])
                                ->value('num');
                            
                            // Get the maximum padding length using SQL (much more efficient)
                            $maxPaddingLength = User::where('employee_id', 'like', $idCode.'%')
                                ->whereNotNull('employee_id')
                                ->where('id', '!=', $userId)
                                ->selectRaw('MAX(LENGTH(employee_id) - ?) as max_len', [strlen($idCode)])
                                ->value('max_len');
                            
                            // Determine numeric count: use max padding found (preserve existing format) or default to 6
                            $numericCount = $maxPaddingLength ?: 6;
                            
                            // Get next available number (max + 1)
                            $val = ($maxNumericValue ?? 0) + 1;
                            
                            // Check if this value already exists (edge case for concurrent requests)
                            // Add max iteration limit to prevent infinite loops
                            $maxIterations = 1000;
                            $iterationCount = 0;
                            while (User::where('employee_id', $idCode.str_pad($val, $numericCount, '0', STR_PAD_LEFT))
                                ->where('id', '!=', $userId)
                                ->exists()) {
                                $val++;
                                $iterationCount++;
                                if ($iterationCount >= $maxIterations) {
                                    Log::error('Employee ID generation: Max iterations reached', [
                                        'user_id' => $userId,
                                        'id_code' => $idCode,
                                        'last_attempted_value' => $val,
                                    ]);
                                    throw new Exception('Unable to generate unique employee ID after '.$maxIterations.' attempts');
                                }
                            }
                            
                            $EmpId = str_pad($val, $numericCount, '0', STR_PAD_LEFT);
                            $proposedEmployeeId = $idCode.$EmpId;
                            
                            User::where('id', $userId)->update(['employee_id' => $proposedEmployeeId]);
                            
                            return $proposedEmployeeId;
                        });
                    } catch (Exception $e) {
                        Log::error('Failed to generate employee ID for user', [
                            'user_id' => $userId,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        
                        // Continue execution but log the error - don't fail the entire onboarding process
                        // The employee_id will remain null and can be manually assigned later
                    }
                }

                $workEmail = OnboardingAdditionalEmails::where('onboarding_user_id', $onbardingUserId)->get();
                foreach ($workEmail as $workEmails) {
                    $userAddiemail = UsersAdditionalEmail::where('email', $workEmails->email)->first();
                    if ($userAddiemail == '') {
                        UsersAdditionalEmail::create(['user_id' => $userId, 'email' => $workEmails->email]);
                    }
                }

                if (! empty($request->custom_fields) && is_array($request->custom_fields)) {
                    foreach ($request->custom_fields as $customField) {
                        if (! empty($customField) && isset($customField['id'], $customField['category_id'], $customField['template_name'])) {
                            $existingDocument = NewSequiDocsDocument::where([
                                'template_id' => $customField['id'],
                                'category_id' => $customField['category_id'],
                                'user_id' => $request->user_id,
                                'user_id_from' => 'onboarding_employees',
                            ])->first();

                            if ($existingDocument) {
                                $existingDocument->update([
                                    'smart_text_template_fied_keyval' => json_encode($customField),
                                    'updated_at' => now(), // update the timestamp
                                ]);
                            } else {
                                NewSequiDocsDocument::create([
                                    'user_id' => $request->user_id,
                                    'user_id_from' => 'onboarding_employees',
                                    'is_active' => 1,
                                    'smart_text_template_fied_keyval' => json_encode($customField),
                                    'template_id' => $customField['id'],
                                    'category_id' => $customField['category_id'],
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                        }
                    }
                }

                NewSequiDocsDocument::where('user_id', '=', $onbardingUserId)->where('user_id_from', '=', 'onboarding_employees')->where('is_active', 1)->Update(['user_id' => $data->id, 'user_id_from' => 'users']);
                Documents::where('user_id', '=', $onbardingUserId)->where('user_id_from', '=', 'onboarding_employees')->Update(['user_id' => $userId, 'user_id_from' => 'users']);

                if (! empty($data->team_id)) {
                    $teamLeadId = ManagementTeam::where('id', $data->team_id)->first();
                    if ($teamLeadId) {
                        ManagementTeamMember::Create([
                            'team_id' => $teamLeadId->id,
                            'team_lead_id' => $teamLeadId->team_lead_id,
                            'team_member_id' => $userId,
                        ]);
                    }
                }
                OnboardingEmployees::where('email', $data->email)->update(['user_id' => $userId]);

                $userData = User::where('id', $userId)->first();
                // if ($userData->manager_id) {
                UserManagerHistory::create([
                    'user_id' => $userId,
                    'updater_id' => $authUserId ?? 0,
                    'effective_date' => $effectiveDate,
                    'manager_id' => $userData->manager_id,
                    'team_id' => $userData->team_id,
                    'position_id' => $userData->position_id,
                    'sub_position_id' => $userData->sub_position_id,
                ]);
                // }

                UserIsManagerHistory::create([
                    'user_id' => $userId,
                    'updater_id' => $authUserId ?? 0,
                    'effective_date' => $effectiveDate,
                    'is_manager' => $userData->is_manager,
                    'position_id' => $userData->position_id,
                    'sub_position_id' => $userData->sub_position_id,
                ]);

                $transfer = [
                    'user_id' => $userId,
                    'transfer_effective_date' => $effectiveDate,
                    'updater_id' => $authUserId ?? 0,
                    'state_id' => $userData->state_id,
                    'office_id' => $userData->office_id,
                    'department_id' => $userData->department_id,
                    'position_id' => $userData->position_id,
                    'sub_position_id' => $userData->sub_position_id,
                    'is_manager' => $userData->is_manager,
                    'manager_id' => $userData->manager_id,
                    'team_id' => $userData->team_id,
                ];
                UserTransferHistory::create($transfer);

                $department = [
                    'user_id' => $userId,
                    'updater_id' => $authUserId ?? 0,
                    'effective_date' => $effectiveDate,
                    'department_id' => $userData->department_id,
                ];
                UserDepartmentHistory::create($department);

                if (! empty($userId)) {
                    if (isset($userData->office_id)) {
                        Locations::where('id', $userData->office_id)->update(['archived_at' => null]);
                    }
                }

                $CrmData = Crms::where('id', 2)->where('status', 1)->first();
                $CrmSetting = CrmSetting::where('crm_id', 2)->first();
                if (! empty($CrmData) && ! empty($CrmSetting)) {
                    $decreptedValue = openssl_decrypt($CrmSetting['value'], config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
                    $val = json_decode($decreptedValue);
                    $token = $val->api_key;
                    $onBoardingEmployee->status = 'Onboarding';
                    $this->hubspotSaleDataCreate($data, $onBoardingEmployee, $uid, $token);
                }

                // Push Rep Data to Hubspot Current Energy
                $integration = Integration::where(['name' => 'Hubspot Current Energy', 'status' => 1])->first();
                $hubspotCurrentEnergyToken = config('services.hubspot_current_energy.api_key');
                if (! empty($integration) && ! empty($hubspotCurrentEnergyToken)) {
                    // $hubspotCurrentEnergyToken = config('services.hubspot_current_energy.api_key');
                    $this->pushRepDataToHubspotCurrentEnergy($data, $onBoardingEmployee, $uid, $hubspotCurrentEnergyToken);
                }

                $jobNimbusCrmData = Crms::whereHas('crmSetting')->with('crmSetting')->where('id', 4)->where('status', 1)->first();
                if (! empty($jobNimbusCrmData)) {
                    $decreptedValue = openssl_decrypt($jobNimbusCrmData->crmSetting->value, config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
                    $jobNimbusCrmSetting = json_decode($decreptedValue);
                    $jobNimbusToken = $jobNimbusCrmSetting->api_key;
                    $postDataToJobNimbus = [
                        'display_name' => $userData['first_name'].' '.$userData['last_name'],
                        'email' => $userData['email'],
                        'home_phone' => $userData['mobile_no'],
                        'first_name' => $userData['first_name'],
                        'last_name' => $userData['last_name'],
                        'record_type_name' => 'Subcontractor',
                        'status_name' => 'Solar Reps',
                        'external_id' => $userData['employee_id'],
                    ];
                    $responseJobNimbuscontats = $this->storeJobNimbuscontats($postDataToJobNimbus, $jobNimbusToken);
                    if ($responseJobNimbuscontats['status'] === true) {
                        User::where('id', $userId)->update([
                            'jobnimbus_jnid' => $responseJobNimbuscontats['data']['jnid'],
                            'jobnimbus_number' => $responseJobNimbuscontats['data']['number'],
                        ]);
                    }
                }

                $additionalRecruters = AdditionalRecruiters::where('hiring_id', $onbardingUserId)->whereNotNull('recruiter_id')->get();
                AdditionalRecruiters::where('hiring_id', $onbardingUserId)->whereNotNull('recruiter_id')->update(['user_id' => $userId]);
                foreach ($additionalRecruters as $key => $value) {
                    if ($key == 0) {
                        User::where('id', $userId)->update([
                            'additional_recruiter_id1' => $value->recruiter_id,
                            'additional_recruiter1_per_kw_amount' => $value->system_per_kw_amount,
                        ]);
                    } else {
                        User::where('id', $userId)->update([
                            'additional_recruiter_id2' => $value->recruiter_id,
                            'additional_recruiter2_per_kw_amount' => $value->system_per_kw_amount,
                        ]);
                    }
                }

                $additionalLocations = OnboardingEmployeeLocations::where('user_id', $onbardingUserId)->get();
                foreach ($additionalLocations as $additionalLocation) {
                    AdditionalLocations::updateOrCreate([
                        'user_id' => $userId,
                        'state_id' => $additionalLocation->state_id,
                        'office_id' => $additionalLocation->office_id,
                    ], [
                        'updater_id' => $authUserId ?? 0,
                        'effective_date' => $effectiveDate,
                        'overrides_amount' => isset($additionalLocation->overrides_amount) ? $additionalLocation->overrides_amount : 0,
                        'overrides_type' => isset($additionalLocation->overrides_type) ? $additionalLocation->overrides_type : null,
                    ]);
                }

                $additionalLocationsOverrides = OnboardingEmployeeAdditionalOverride::with('OnboardingEmployeeLocations')->where('user_id', $onbardingUserId)->get();
                foreach ($additionalLocationsOverrides as $additionalLocationsOverride) {
                    $info = $additionalLocationsOverride->OnboardingEmployeeLocations;
                    $useraddofficeoverhist = UserAdditionalOfficeOverrideHistory::updateOrCreate([
                        'user_id' => $userId,
                        'state_id' => $info->state_id ?? 0,
                        'office_id' => $info->office_id ?? 0,
                        'product_id' => $additionalLocationsOverride->product_id,
                    ], [
                        'updater_id' => $authUserId ?? 0,
                        'override_effective_date' => $effectiveDate,
                        'office_overrides_amount' => isset($additionalLocationsOverride->overrides_amount) ? $additionalLocationsOverride->overrides_amount : 0,
                        'office_overrides_type' => isset($additionalLocationsOverride->overrides_type) ? $additionalLocationsOverride->overrides_type : null,
                        'tiers_id' => isset($additionalLocationsOverride->tiers_id) ? $additionalLocationsOverride->tiers_id : null,
                    ]);
                    $range = OnboardingOfficeOverrideTiersRange::where('onboarding_office_override_id', $additionalLocationsOverride->id)->get();
                    if ($additionalLocationsOverride->tiers_id > 0) {
                        if ($range->isNotEmpty()) {
                            foreach ($range as $rang) {
                                UserAdditionalOfficeOverrideHistoryTiersRange::create([
                                    'user_id' => $userId,
                                    'user_add_office_override_history_id' => $useraddofficeoverhist->id ?? null,
                                    'tiers_schema_id' => $rang->tiers_schema_id ?? null,
                                    'tiers_levels_id' => $rang->tiers_levels_id ?? null,
                                    'value' => $rang->value ?? null,
                                    'value_type' => $rang->value_type ?? null,
                                ]);
                            }
                        }
                    }
                }
            }

            $statusUpdate = OnboardingEmployees::find($onbardingUserId);
            $statusUpdate->status_id = 7;
            if ($request->hiring_type == 'Directly') {
                $statusUpdate->hiring_type = 'Directly';
                $this->saveDataToSourceMarketing($onbardingUserId, 'sales_rep_signup');
            }
            $statusUpdate->save();

            $positionProduct = PositionProduct::where(['position_id' => $onBoardingEmployee['sub_position_id']])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($positionProduct) {
                $porudcts = PositionProduct::where(['position_id' => $onBoardingEmployee['sub_position_id'], 'effective_date' => $positionProduct->effective_date])->get();
            } else {
                $porudcts = PositionProduct::where(['position_id' => $onBoardingEmployee['sub_position_id']])->whereNull('effective_date')->get();
            }
            foreach ($porudcts as $porudct) {
                UserOrganizationHistory::create([
                    'user_id' => $userId,
                    'updater_id' => $authUserId ?? 0,
                    'product_id' => $porudct->product_id,
                    'position_id' => $userData->position_id,
                    'sub_position_id' => $userData->sub_position_id,
                    'effective_date' => $effectiveDate,
                    'self_gen_accounts' => $onBoardingEmployee->self_gen_accounts,
                ]);
            }

            $deductions = EmployeeOnboardingDeduction::where('user_id', $onbardingUserId)->get();
            UserDeduction::where('user_id', $userId)->delete();
            foreach ($deductions as $deduction) {
                UserDeduction::create([
                    'deduction_type' => $deduction->deduction_type,
                    'cost_center_name' => $deduction->cost_center_name,
                    'cost_center_id' => $deduction->cost_center_id,
                    'ammount_par_paycheck' => $deduction->ammount_par_paycheck,
                    'deduction_setting_id' => isset($deduction->deduction_setting_id) ? $deduction->deduction_setting_id : null,
                    'position_id' => $deduction->position_id,
                    'sub_position_id' => $userData->sub_position_id,
                    'user_id' => $userId,
                    'effective_date' => $effectiveDate,
                ]);

                UserDeductionHistory::create([
                    'user_id' => $userId,
                    'updater_id' => $authUserId ?? 0,
                    'cost_center_id' => $deduction->cost_center_id,
                    'amount_par_paycheque' => $deduction->ammount_par_paycheck,
                    'pay_period_from' => $deduction->pay_period_from,
                    'pay_period_to' => $deduction->pay_period_to,
                    'effective_date' => $effectiveDate,
                ]);
            }

            $commissions = OnboardingUserRedline::where('user_id', $onbardingUserId)->get();
            foreach ($commissions as $commission) {
                $usercommissiondata = UserCommissionHistory::create([
                    'user_id' => $userId,
                    'commission_effective_date' => $effectiveDate,
                    'product_id' => $commission->product_id,
                    'position_id' => $userData->position_id,
                    'core_position_id' => $commission->core_position_id,
                    'sub_position_id' => $commission->position_id,
                    'updater_id' => $authUserId ?? 0,
                    'self_gen_user' => $commission->self_gen_user,
                    'commission' => $commission->commission,
                    'commission_type' => $commission->commission_type,
                    'custom_sales_field_id' => $commission->custom_sales_field_id ?? null,
                    'tiers_id' => $commission->tiers_id,
                ]);
                if ($this->companySettingtiers?->status) {
                    $range = OnboardingCommissionTiersRange::where('onboarding_commission_id', $commission->id)->get();
                    if ($commission->tiers_id > 0) {
                        if ($range->isNotEmpty()) {
                            foreach ($range as $rang) {
                                UserCommissionHistoryTiersRange::create([
                                    'user_id' => $userId,
                                    'user_commission_history_id' => $usercommissiondata->id ?? null,
                                    'tiers_schema_id' => $rang['tiers_schema_id'] ?? null,
                                    'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                                    'value' => $rang['value'] ?? null,
                                    'value_type' => $rang['value_type'] ?? null,
                                ]);
                            }
                        }
                    }
                }
            }

            $redLines = OnboardingEmployeeRedline::where('user_id', $onbardingUserId)->get();
            foreach ($redLines as $redLine) {
                UserRedlines::create([
                    'user_id' => $userId,
                    'start_date' => $effectiveDate,
                    'position_type' => $userData->position_id,
                    'core_position_id' => $redLine->core_position_id,
                    'sub_position_type' => $redLine->position_id,
                    'updater_id' => $authUserId ?? 0,
                    'redline_amount_type' => $redLine->redline_amount_type,
                    'redline' => $redLine->redline,
                    'redline_type' => $redLine->redline_type,
                    'self_gen_user' => $redLine->self_gen_user,
                ]);
            }

            $withHeld = OnboardingEmployeeWithheld::where('user_id', $onbardingUserId)->get();
            foreach ($withHeld as $value) {
                UserWithheldHistory::create([
                    'user_id' => $userId,
                    'updater_id' => $authUserId ?? 0,
                    'position_id' => $userData->position_id,
                    'product_id' => $value->product_id,
                    'sub_position_id' => $value->position_id,
                    'withheld_type' => $value->withheld_type,
                    'withheld_amount' => $value->withheld_amount,
                    'withheld_effective_date' => $effectiveDate,
                ]);
            }

            $upfronts = OnboardingEmployeeUpfront::where('user_id', $onbardingUserId)->get();
            foreach ($upfronts as $upfront) {
                $userupfrontdata = UserUpfrontHistory::create([
                    'user_id' => $userId,
                    'upfront_effective_date' => $effectiveDate,
                    'position_id' => $userData->position_id,
                    'core_position_id' => $upfront->core_position_id,
                    'product_id' => $upfront->product_id,
                    'milestone_schema_id' => $upfront->milestone_schema_id,
                    'milestone_schema_trigger_id' => $upfront->milestone_schema_trigger_id,
                    'sub_position_id' => $upfront->position_id,
                    'updater_id' => $authUserId ?? 0,
                    'self_gen_user' => $upfront->self_gen_user,
                    'upfront_pay_amount' => $upfront->upfront_pay_amount,
                    'upfront_sale_type' => $upfront->upfront_sale_type,
                    'tiers_id' => $upfront->tiers_id,
                    'custom_sales_field_id' => $upfront->custom_sales_field_id,
                ]);
                if ($this->companySettingtiers?->status) {
                    $range = OnboardingUpfrontsTiersRange::where('onboarding_upfront_id', $upfront->id)->get();
                    if ($upfront->tiers_id > 0) {
                        if ($range->isNotEmpty()) {
                            foreach ($range as $rang) {
                                UserUpfrontHistoryTiersRange::create([
                                    'user_id' => $userId,
                                    'user_upfront_history_id' => $userupfrontdata->id ?? null,
                                    'tiers_schema_id' => $rang['tiers_schema_id'] ?? null,
                                    'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                                    'value' => $rang['value'] ?? null,
                                    'value_type' => $rang['value_type'] ?? null,
                                ]);
                            }
                        }
                    }
                }
            }

            $overrides = OnboardingEmployeeOverride::where('user_id', $onbardingUserId)->get();
            foreach ($overrides as $override) {
                $useroverridedata = UserOverrideHistory::create([
                    'user_id' => $userId,
                    'override_effective_date' => $effectiveDate,
                    'updater_id' => $authUserId ?? 0,
                    'product_id' => $override->product_id,
                    'direct_overrides_amount' => $override->direct_overrides_amount,
                    'direct_overrides_type' => $override->direct_overrides_type,
                    'indirect_overrides_amount' => $override->indirect_overrides_amount,
                    'indirect_overrides_type' => $override->indirect_overrides_type,
                    'office_overrides_amount' => $override->office_overrides_amount,
                    'office_overrides_type' => $override->office_overrides_type,
                    'office_stack_overrides_amount' => $override->office_stack_overrides_amount,
                    'direct_tiers_id' => $override->direct_tiers_id ?? null,
                    'indirect_tiers_id' => $override->indirect_tiers_id ?? null,
                    'office_tiers_id' => $override->office_tiers_id ?? null,
                    // Custom Sales Field IDs
                    'direct_custom_sales_field_id' => $override->direct_custom_sales_field_id ?? null,
                    'indirect_custom_sales_field_id' => $override->indirect_custom_sales_field_id ?? null,
                    'office_custom_sales_field_id' => $override->office_custom_sales_field_id ?? null,
                ]);
                if ($this->companySettingtiers?->status) {
                    $range = OnboardingDirectOverrideTiersRange::where('onboarding_direct_override_id', $override->id)->get();
                    if ($override->direct_tiers_id > 0) {
                        if ($range->isNotEmpty()) {
                            foreach ($range as $rang) {
                                UserDirectOverrideHistoryTiersRange::create([
                                    'user_id' => $userId,
                                    'user_override_history_id' => $useroverridedata->id ?? null,
                                    'tiers_schema_id' => $rang['tiers_schema_id'] ?? null,
                                    'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                                    'value' => $rang['value'] ?? null,
                                    'value_type' => $rang['value_type'] ?? null,
                                ]);
                            }
                        }
                    }
                    $ind_range = OnboardingIndirectOverrideTiersRange::where('onboarding_indirect_override_id', $override->id)->get();
                    if ($override->indirect_tiers_id > 0) {
                        if ($ind_range->isNotEmpty()) {
                            foreach ($ind_range as $rang) {
                                UserIndirectOverrideHistoryTiersRange::create([
                                    'user_id' => $userId,
                                    'user_override_history_id' => $useroverridedata->id ?? null,
                                    'tiers_schema_id' => $rang['tiers_schema_id'] ?? null,
                                    'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                                    'value' => $rang['value'] ?? null,
                                    'value_type' => $rang['value_type'] ?? null,
                                ]);
                            }
                        }
                    }
                    $overoff_range = OnboardingOverrideOfficeTiersRange::where('onboarding_override_office_id', $override->id)->get();
                    if ($override->office_tiers_id > 0) {
                        if ($overoff_range->isNotEmpty()) {
                            foreach ($overoff_range as $rang) {
                                UserOfficeOverrideHistoryTiersRange::create([
                                    'user_id' => $userId,
                                    'user_office_override_history_id' => $useroverridedata->id ?? null,
                                    'tiers_schema_id' => $rang['tiers_schema_id'] ?? null,
                                    'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                                    'value' => $rang['value'] ?? null,
                                    'value_type' => $rang['value_type'] ?? null,
                                ]);
                            }
                        }
                    }
                }
            }

            UserWagesHistory::create([
                'user_id' => $userId,
                'updater_id' => $authUserId ?? 0,
                'effective_date' => isset($effectiveDate) ? $effectiveDate : null,
                'pay_type' => $onBoardingEmployee->pay_type,
                'pay_rate' => $onBoardingEmployee->pay_rate,
                'pay_rate_type' => $onBoardingEmployee->pay_rate_type,
                'expected_weekly_hours' => $onBoardingEmployee->expected_weekly_hours,
                'overtime_rate' => $onBoardingEmployee->overtime_rate,
                'pto_hours' => $onBoardingEmployee->pto_hours,
                'unused_pto_expires' => $onBoardingEmployee->unused_pto_expires,
                'pto_hours_effective_date' => $effectiveDate,
            ]);

            if ($rehire) {
                UserAgreementHistory::where('user_id', $userId)->delete();
            }
            UserAgreementHistory::create([
                'user_id' => $userId,
                'updater_id' => $authUserId ?? 0,
                'probation_period' => $onBoardingEmployee->probation_period,
                'offer_include_bonus' => $onBoardingEmployee->offer_include_bonus,
                'hiring_bonus_amount' => $onBoardingEmployee->hiring_bonus_amount,
                'date_to_be_paid' => $onBoardingEmployee->date_to_be_paid,
                'period_of_agreement' => $effectiveDate,
                'end_date' => $onBoardingEmployee->end_date,
                'offer_expiry_date' => $onBoardingEmployee->offer_expiry_date,
                'hired_by_uid' => $onBoardingEmployee->hired_by_uid,
                'hiring_signature' => $onBoardingEmployee->hiring_signature,
            ]);

            if (in_array(config('app.domain_name'), ['hawxw2', 'sstage'])) {
                W2UserTransferHistory::create([
                    'user_id' => $userId,
                    'updater_id' => $authUserId ?? 0,
                    'period_of_agreement' => $effectiveDate,
                    'type' => 'w2',
                ]);
            }

            // $userData['new_password'] = 'Newuser#123';
            $userData['new_password'] = $randPassForUsers['plain_password'];
            $otherData = [];
            // $otherData['new_password'] = 'Newuser#123';
            $otherData['new_password'] = $randPassForUsers['plain_password'];
            $welcomeEmailContent = SequiDocsEmailSettings::welcome_email_content($userData, $otherData);
            $emailContent['email'] = $userData->email;
            $emailContent['subject'] = $welcomeEmailContent['subject'];
            $emailContent['template'] = $welcomeEmailContent['template'];
            $message = 'Employee Hired Credentials Send Successfully.';
            $checkDomainSetting = DomainSetting::check_domain_setting($userData->email);
            if ($checkDomainSetting['status'] == true) {
                if ($welcomeEmailContent['is_active'] == 1 && $welcomeEmailContent['template'] != '') {
                    $this->sendEmailNotification($emailContent);
                } else {
                    $salesData = [];
                    $salesData['email'] = $userData->email;
                    $salesData['subject'] = 'Login Credentials';
                    $salesData['template'] = view('mail.credentials', compact('userData'));
                    $this->sendEmailNotification($salesData);
                }
                $this->saveDataToSourceMarketing($onbardingUserId, 'creds_sent');
            } else {
                $message = 'Employee Hired but '.$checkDomainSetting['message'];
            }

            Notification::create([
                'user_id' => $userData->id,
                'type' => 'Employee Hired',
                'description' => 'Employee Hired by'.@$userId->first_name,
                'is_read' => 0,
            ]);

            $notificationData = [
                'user_id' => $userData->id,
                'device_token' => $userData->device_token,
                'title' => 'Employee Hired.',
                'sound' => 'sound',
                'type' => 'Employee Hired',
                'body' => 'Employee Hired by '.@$userId->first_name,
            ];
            $this->sendNotification($notificationData);

            DB::commit();

            if ($rehire) {
                // SYNC USER HISTORY DATA
                ApplyHistoryOnUsersV2Job::dispatch($userId, auth()->user()->id)->afterCommit();
            }

            return response()->json([
                'ApiName' => 'directHiredEmployee',
                'status' => true,
                'message' => $message,
                'welcome_email_content' => $welcomeEmailContent,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'directHiredEmployee',
                'status' => false,
                'message' => $e->getMessage().' '.$e->getLine(),
                'welcome_email_content' => '',
            ], 500);
        }
    }

    public function hiredEmployee(Request $request, $authUserId = null)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:onboarding_employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $onboardingEmployee = OnboardingEmployees::find($request->employee_id);
        $request['user_id'] = $request->employee_id;
        $request['name'] = $onboardingEmployee->hiring_signature;
        $request['custom_fields'] = json_decode($onboardingEmployee->custom_fields, true);
        $rehire = ! empty($onboardingEmployee->mainUserData->rehire) ? true : false;

        return $this->directHiredEmployee($request, $authUserId, rehire: $rehire);
    }

    public function reHiredEmployee(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:onboarding_employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $onboardingEmployee = OnboardingEmployees::find($request->employee_id);
        $request['user_id'] = $request->employee_id;
        $request['name'] = $onboardingEmployee->hiring_signature;

        return $this->directHiredEmployee(request: $request, rehire: true);
    }

    public function hiringEmployeeCompensation(Request $request): JsonResponse
    {
        $companyProfile = CompanyProfile::first();
        $isPestCompany = $companyProfile->company_type == CompanyProfile::PEST_COMPANY_TYPE &&
            in_array(config('app.domain_name'), config('global_vars.PEST_TYPE_COMPANY_DOMAIN_CHECK'));

        // Validation rules
        $rules = [
            'user_id' => 'required|exists:onboarding_employees,id',
            'employee_compensation.*.commission_type' => $isPestCompany ? 'nullable|in:percent' : 'nullable',
            // 'employee_compensation.*.redline_type' => $isPestCompany ? 'nullable|in:per sale' : 'nullable',
        ];

        $validator = Validator::make($request->all(), $rules, [
            'employee_compensation.*.commission_type.in' => 'Invalid Commission Type.',
            // 'employee_compensation.*.redline_type.in' => 'Invalid Readline Type.',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $employee = OnboardingEmployees::find($request->user_id);
        if (! $employee) {
            return response()->json([
                'ApiName' => 'add-onboarding_employee_compensation',
                'status' => false,
                'message' => 'User Not found',
            ], 400);
        }
        $position_id = $employee['sub_position_id'] ?? 0;
        $user_id = $request->user_id;
        $product_id = $request->product_id;
        $core_position_id = $request->core_position_id != '' ? $request->core_position_id : null;
        $updater_id = auth()->user()->id;
        if ($compensation = $request->data) {
            if (isset($compensation['commission']) && ! empty($compensation['commission'])) {
                $comm = $compensation['commission'];
                
                // Custom Sales Field support: Parse custom_field_X format
                $commissionParsed = $this->parseCustomFieldType(
                    $comm['commission_type'] ?? null,
                    $comm['custom_sales_field_id'] ?? null
                );

                OnboardingUserRedline::updateOrCreate(
                    ['user_id' => $user_id, 'product_id' => $product_id, 'core_position_id' => $core_position_id],
                    array_merge(
                        [
                            'position_id' => $position_id ?? 0,
                            'updater_id' => $updater_id,
                            'commission' => $comm['commission'] ?? 0,
                            'commission_type' => $commissionParsed['type'],
                            'custom_sales_field_id' => $commissionParsed['custom_sales_field_id'],
                            'commission_effective_date' => isset($comm['commission_effective_date']) ? date('Y-m-d', strtotime($comm['commission_effective_date'])) : date('Y-m-d'),
                        ]

                    )
                );
            }
            if (isset($compensation['redline']) && ! empty($compensation['redline'])) {
                $redline = $compensation['redline'];
                OnboardingEmployeeRedline::updateOrCreate(
                    ['user_id' => $request->user_id, 'core_position_id' => $core_position_id],
                    array_merge(
                        [
                            'position_id' => $position_id,
                            'updater_id' => $updater_id,
                            'redline' => $redline['redline'] ?? null,
                            'redline_type' => $redline['redline_type'] ?? 'per watt',
                            'redline_amount_type' => $redline['redline_amount_type'] ?? 'Fixed',
                            'redline_effective_date' => isset($redline['redline_effective_date']) ? date('Y-m-d', strtotime($redline['redline_effective_date'])) : date('Y-m-d'),
                        ]

                    )
                );
            }
            if (isset($compensation['upfront']) && ! empty($compensation['upfront'])) {
                $upfront = $compensation['upfront'];
                $milestone_id = $upfront['milestone_id'] ?? 0;
                if (! empty($upfront['schemas'])) {
                    foreach ($upfront['schemas'] as $res_val) {
                        OnboardingEmployeeUpfront::updateOrCreate(
                            ['user_id' => $request->user_id, 'product_id' => $product_id, 'core_position_id' => $core_position_id, 'milestone_schema_id' => $milestone_id, 'milestone_schema_trigger_id' => $res_val['milestone_schema_trigger_id']],
                            array_merge(
                                [
                                    'position_id' => $position_id ?? 0,
                                    'updater_id' => $updater_id,
                                    'upfront_pay_amount' => $res_val['upfront_pay_amount'] ?? '',
                                    'upfront_sale_type' => $res_val['upfront_sale_type'] ?? '',
                                    'upfront_effective_date' => isset($res_val['upfront_effective_date']) ? date('Y-m-d', strtotime($res_val['upfront_effective_date'])) : date('Y-m-d'),
                                ]
                            )
                        );
                    }
                }
            }
        }

        return response()->json([
            'ApiName' => 'add-onboarding_employee_compensation',
            'status' => true,
            'message' => 'Added Successfully.',
        ]);
    }

    public function employeeWithheld(Request $request): JsonResponse
    {
        $companyProfile = CompanyProfile::first();
        $isPestCompany = $companyProfile->company_type == CompanyProfile::PEST_COMPANY_TYPE &&
            in_array(config('app.domain_name'), config('global_vars.PEST_TYPE_COMPANY_DOMAIN_CHECK'));

        // Validation rules
        $rules = [
            'user_id' => 'required|exists:onboarding_employees,id',
            'employee_withheld.*.withheld_type' => $isPestCompany ? 'nullable|in:per watt' : 'nullable',
        ];

        $validator = Validator::make($request->all(), $rules, [
            'employee_withheld.*.withheld_type.in' => 'Invalid redline Type.',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $employee = OnboardingEmployees::find($request->user_id);
        if (! $employee) {
            return response()->json([
                'ApiName' => 'add-onboarding_employee_withheld',
                'status' => false,
                'message' => 'User Not found',
            ], 400);
        }
        $position_id = $employee['sub_position_id'] ?? 0;
        if ($withheld_data = $request->employee_withheld) {
            $updater_id = auth()->user()->id;
            foreach ($withheld_data as $value) {
                OnboardingEmployeeWithheld::updateOrCreate(
                    ['user_id' => $request->user_id, 'product_id' => $value['product_id'], 'position_id' => $position_id],
                    array_merge(
                        [
                            'updater_id' => $updater_id,
                            'withheld_amount' => $value['withheld_amount'] ?? null,
                            'withheld_type' => $value['withheld_type'] ?? null,
                            'withheld_effective_date' => isset($value['withheld_effective_date']) ? date('Y-m-d', strtotime($value['withheld_effective_date'])) : date('Y-m-d'),
                        ]

                    )
                );
            }
        }

        return response()->json([
            'ApiName' => 'add-onboarding_employee_withheld',
            'status' => true,
            'message' => 'Added Successfully.',
        ]);
    }

    public function deleteOnboardingEmployee($id): JsonResponse
    {
        OnboardingEmployees::find($id)->delete();
        EmployeeOnboardingDeduction::where('user_id', $id)->delete();
        OnboardingUserRedline::where('user_id', $id)->delete();
        OnboardingEmployeeLocation::where('user_id', $id)->delete();
        OnboardingEmployeeOverride::where('user_id', $id)->delete();
        OnboardingEmployeeRedline::where('user_id', $id)->delete();
        OnboardingEmployeeWithheld::where('user_id', $id)->delete();
        OnboardingEmployeeUpfront::where('user_id', $id)->delete();
        OnboardingEmployeeWages::where('user_id', $id)->delete();
        OnboardingAdditionalEmails::where('onboarding_user_id', $id)->delete();
        OnboardingCommissionTiersRange::where('user_id', $id)->delete();
        OnboardingUpfrontsTiersRange::where('user_id', $id)->delete();
        OnboardingEmployeeAdditionalOverride::where('user_id', $id)->delete();
        OnboardingDirectOverrideTiersRange::where('user_id', $id)->delete();
        OnboardingIndirectOverrideTiersRange::where('user_id', $id)->delete();
        OnboardingOverrideOfficeTiersRange::where('user_id', $id)->delete();
        OnboardingOfficeOverrideTiersRange::where('user_id', $id)->delete();

        return response()->json([
            'ApiName' => 'delete Onboarding Employee',
            'status' => true,
            'message' => 'delete Successfully.',
        ]);
    }

    /**
     * Initiate new contract/rehire process for existing active user
     * Creates new onboarding record for wizard process
     */
    public function initiateNewContract(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'existing_user_id' => 'required|exists:users,id',
                'office_id' => 'nullable|exists:locations,id',
                'state_id' => 'nullable|exists:states,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ApiName' => 'initiate-new-contract',
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $existingUser = User::find($request->existing_user_id);

            // Validate 1099 only
            if ($existingUser->worker_type === 'w2') {
                return response()->json([
                    'ApiName' => 'initiate-new-contract',
                    'status' => false,
                    'message' => 'W2 employees cannot use this flow. Only available for 1099 contractors.',
                    'error_code' => 'W2_NOT_ALLOWED',
                ], 400);
            }

            // Check if user is already terminated or dismissed
            if ($existingUser->terminate == 1 || $existingUser->dismiss == 1) {
                return response()->json([
                    'ApiName' => 'initiate-new-contract',
                    'status' => false,
                    'message' => 'Cannot create new contract for terminated or dismissed users.',
                    'error_code' => 'USER_INACTIVE',
                ], 400);
            }

            // Check if there's already an incomplete new contract entry for this user
            // Only reuse INCOMPLETE entries (Draft, In-Progress, etc.)
            // If previous entries are completed, allow creating new ones (for contract renewal after end)
            $existingContract = OnboardingEmployees::where('user_id', $existingUser->id)
                ->where('is_new_contract', 1)
                ->whereIn('status_id', [4, 8, 22, 23, 24]) // Draft, In-Progress, Pending statuses (not hired/completed)
                ->orderBy('created_at', 'desc')
                ->first();

            // Check user's contract status for better messaging
            $userContractEnded = ($existingUser->contract_ended == 1);
            $isRenewalAfterEnd = false;

            // If no incomplete entries and user's contract has ended, this is a renewal after contract end
            if (! $existingContract && $userContractEnded) {
                $isRenewalAfterEnd = true;
            }

            // Use override values if provided, otherwise use existing user values
            $officeId = $request->office_id ?? $existingUser->office_id;
            $stateId = $request->state_id ?? $existingUser->state_id;

            if ($existingContract) {
                // Update existing record with any new override values
                // $existingContract->update([
                //     'office_id' => $officeId,
                //     'state_id' => $stateId,
                // ]);

                $newContract = $existingContract;
                $isExisting = true;
            } else {
                // Create new onboarding record for wizard process
                $newContract = OnboardingEmployees::create([
                    'user_id' => $existingUser->id, // Link to existing user
                    'first_name' => $existingUser->first_name,
                    'last_name' => $existingUser->last_name,
                    'email' => $existingUser->email,
                    'mobile_no' => $existingUser->mobile_no,
                    'state_id' => $stateId,
                    'city_id' => $existingUser->city_id,
                    'department_id' => $existingUser->department_id,
                    'position_id' => $existingUser->position_id,
                    'sub_position_id' => $existingUser->sub_position_id,
                    'is_manager' => $existingUser->is_manager,
                    'manager_id' => $existingUser->manager_id,
                    'team_id' => $existingUser->team_id,
                    'recruiter_id' => $existingUser->recruiter_id,
                    'office_id' => $officeId,
                    'status_id' => 8, // Draft status
                    'is_new_contract' => 1, // Flag as new contract process
                    // Pre-fill compensation data
                    'commission' => $existingUser->commission,
                    'commission_type' => $existingUser->commission_type,
                    'redline' => $existingUser->redline,
                    'redline_amount_type' => $existingUser->redline_amount_type,
                    'redline_type' => $existingUser->redline_type ?? '',
                    'upfront_pay_amount' => $existingUser->upfront_pay_amount,
                    'upfront_sale_type' => $existingUser->upfront_sale_type,
                    'direct_overrides_amount' => $existingUser->direct_overrides_amount,
                    'direct_overrides_type' => $existingUser->direct_overrides_type,
                    'indirect_overrides_amount' => $existingUser->indirect_overrides_amount,
                    'indirect_overrides_type' => $existingUser->indirect_overrides_type,
                    'office_overrides_amount' => $existingUser->office_overrides_amount,
                    'office_overrides_type' => $existingUser->office_overrides_type,
                    'withheld_amount' => $existingUser->withheld_amount,
                    'withheld_type' => $existingUser->withheld_type,
                    'probation_period' => $existingUser->probation_period,
                    'hiring_bonus_amount' => $existingUser->hiring_bonus_amount,
                    'pay_type' => $existingUser->pay_type,
                    'pay_rate' => $existingUser->pay_rate,
                    'pay_rate_type' => $existingUser->pay_rate_type,
                    'expected_weekly_hours' => $existingUser->expected_weekly_hours,
                    'overtime_rate' => $existingUser->overtime_rate,
                    'pto_hours' => $existingUser->pto_hours,
                ]);

                $isExisting = false;
            }

            // Determine the appropriate message based on scenario
            $message = '';
            if ($isExisting) {
                $message = 'Existing new contract entry found and updated';
            } elseif ($isRenewalAfterEnd) {
                $message = 'New contract wizard initiated for contract renewal (previous contract ended)';
            } else {
                $message = 'New contract wizard initiated successfully';
            }

            return response()->json([
                'ApiName' => 'initiate-new-contract',
                'status' => true,
                'data' => [
                    'onboarding_employee_id' => $newContract->id,
                    'existing_user_id' => $existingUser->id,
                    'pre_filled_data' => $this->getPreFilledData($existingUser, $officeId, $stateId),
                    'is_existing_entry' => $isExisting,
                    'is_renewal_after_end' => $isRenewalAfterEnd,
                    'user_contract_ended' => $userContractEnded,
                    'last_updated' => $newContract->updated_at->format('Y-m-d H:i:s'),
                    'contract_scenario' => $isExisting ? 'resume_incomplete' : ($isRenewalAfterEnd ? 'renewal_after_end' : 'new_parallel'),
                ],
                'message' => $message,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ApiName' => 'initiate-new-contract',
                'status' => false,
                'message' => 'Failed to initiate new contract: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get pre-filled data for existing user with override values
     */
    private function getPreFilledData($user, $officeId = null, $stateId = null)
    {
        return [
            'employee_details' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'mobile_no' => $user->mobile_no,
                'state_id' => $stateId ?? $user->state_id,
                'city_id' => $user->city_id,
            ],
            'organization' => [
                'department_id' => $user->department_id,
                'position_id' => $user->position_id,
                'sub_position_id' => $user->sub_position_id,
                'is_manager' => $user->is_manager,
                'manager_id' => $user->manager_id,
                'team_id' => $user->team_id,
                'office_id' => $officeId ?? $user->office_id,
            ],
            'compensation' => [
                'commission' => $user->commission,
                'commission_type' => $user->commission_type,
                'redline' => $user->redline,
                'redline_amount_type' => $user->redline_amount_type,
                'redline_type' => $user->redline_type,
                'upfront_pay_amount' => $user->upfront_pay_amount,
                'upfront_sale_type' => $user->upfront_sale_type,
                'direct_overrides_amount' => $user->direct_overrides_amount,
                'direct_overrides_type' => $user->direct_overrides_type,
                'indirect_overrides_amount' => $user->indirect_overrides_amount,
                'indirect_overrides_type' => $user->indirect_overrides_type,
                'office_overrides_amount' => $user->office_overrides_amount,
                'office_overrides_type' => $user->office_overrides_type,
                'withheld_amount' => $user->withheld_amount,
                'withheld_type' => $user->withheld_type,
            ],
            'agreement' => [
                'probation_period' => $user->probation_period,
                'hiring_bonus_amount' => $user->hiring_bonus_amount,
                'period_of_agreement_start_date' => $user->period_of_agreement_start_date,
                'end_date' => $user->end_date,
            ],
            'wages' => [
                'pay_type' => $user->pay_type,
                'pay_rate' => $user->pay_rate,
                'pay_rate_type' => $user->pay_rate_type,
                'expected_weekly_hours' => $user->expected_weekly_hours,
                'overtime_rate' => $user->overtime_rate,
                'pto_hours' => $user->pto_hours,
            ],
        ];
    }

    /**
     * Complete new contract process and create history records
     *
     * Contract Activation System:
     * - Immediate: Uses ApplyHistoryOnUsersV2Job for same-day effective dates
     * - Future: Automatic activation via existing daily cron job at 00:45 (ApplyHistoryOnUsersV2:update)
     * - No new cron jobs needed - leverages existing infrastructure
     */
    public function completeNewContract(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'onboarding_employee_id' => 'required|exists:onboarding_employees,id',
                'skip_documents' => 'boolean',
                'signing_screeen_url' => 'nullable|string',
                // Optional contract terms
                'commission' => 'nullable|numeric',
                'commission_type' => 'nullable|string',
                'redline' => 'nullable|numeric',
                'redline_amount_type' => 'nullable|string',
                'redline_type' => 'nullable|string',
                'upfront_pay_amount' => 'nullable|numeric',
                'upfront_sale_type' => 'nullable|string',
                'direct_overrides_amount' => 'nullable|numeric',
                'direct_overrides_type' => 'nullable|string',
                'indirect_overrides_amount' => 'nullable|numeric',
                'indirect_overrides_type' => 'nullable|string',
                'office_overrides_amount' => 'nullable|numeric',
                'office_overrides_type' => 'nullable|string',
                'withheld_amount' => 'nullable|numeric',
                'withheld_type' => 'nullable|string',
                'name' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ApiName' => 'complete-new-contract',
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $onboardingEmployee = OnboardingEmployees::find($request->onboarding_employee_id);
            $userId = $onboardingEmployee->user_id;
            $effectiveDate = $onboardingEmployee->period_of_agreement_start_date;
            $endDate = $onboardingEmployee->end_date;
            $skipDocuments = $request->skip_documents ?? false;

            if (! $skipDocuments && $request->send_documents) {
                $positionTemplate = NewSequiDocsTemplatePermission::where(['position_id' => $onboardingEmployee->sub_position_id, 'position_type' => 'receipient', 'category_id' => 1])->whereHas('NewSequiDocsTemplate');
                $positionTemplate = $positionTemplate->first();

                if (! $positionTemplate) {
                    return response()->json([
                        'ApiName' => 'complete-new-contract',
                        'status' => false,
                        'message' => 'Template not found for position '.$onboardingEmployee?->positionDetail?->name.'!!',
                    ], 400);
                }

                $template = NewSequiDocsTemplate::with(['categories', 'document_for_send_with_offer_letter.template.categories', 'document_for_send_with_offer_letter.upload_document_types' => function ($q) {
                    $q->where('is_deleted', '0');
                }])->find($positionTemplate->template_id);

                if (! $template) {
                    return response()->json([
                        'ApiName' => 'complete-new-contract',
                        'status' => false,
                        'message' => 'Template does not exists!!',
                    ], 400);
                }

                // CHECK IF TEMPLATE IS DELETED
                if ($template->is_deleted) {
                    return response()->json([
                        'ApiName' => 'complete-new-contract',
                        'status' => false,
                        'message' => '"Template is deleted!!',

                    ], 400);
                }

                // CHECK IF TEMPLATE IS READY
                if (! $template->is_template_ready) {
                    return response()->json([
                        'ApiName' => 'complete-new-contract',
                        'status' => false,
                        'message' => 'Template is not ready!!',

                    ], 400);

                }
            }
            // Validate that dates are set in onboarding employee record
            if (! $effectiveDate) {
                return response()->json([
                    'ApiName' => 'complete-new-contract',
                    'status' => false,
                    'message' => 'Contract start date not set in onboarding employee record. Please set contract dates first.',
                    'error_code' => 'MISSING_START_DATE',
                ], 400);
            }

            // Update onboarding employee record with new contract terms
            $updateData = [];

            // Add any new contract terms if provided
            $contractFields = [
                'commission', 'commission_type', 'redline', 'redline_amount_type', 'redline_type',
                'upfront_pay_amount', 'upfront_sale_type', 'direct_overrides_amount', 'direct_overrides_type',
                'indirect_overrides_amount', 'indirect_overrides_type', 'office_overrides_amount',
                'office_overrides_type', 'withheld_amount', 'withheld_type',
            ];

            foreach ($contractFields as $field) {
                if ($request->has($field) && $request->$field !== null) {
                    $updateData[$field] = $request->$field;
                }
            }

            // Update hiring_signature from name field if provided
            if ($request->has('name') && $request->name !== null) {
                $updateData['hiring_signature'] = $request->name;
            }

            $onboardingEmployee->update($updateData);

            // Refresh onboarding employee to get updated dates
            $onboardingEmployee->refresh();

            // Handle indefinite contract closure when new contract starts
            $currentUser = User::find($userId);
            $currentAgreement = UserAgreementHistory::where('user_id', $userId)
                ->orderBy('period_of_agreement', 'desc')
                ->orderBy('created_at', 'desc')
                ->first();

            $contractClosureInfo = [];
            $isCurrentContractIndefinite = ($currentUser->end_date === null);
            $currentContractEndDate = Carbon::parse($effectiveDate)->subDay()->format('Y-m-d');

            // Check if we need to close an indefinite contract

            // Calculate end date for current contract (day before new contract starts)
            if (Carbon::parse($effectiveDate)->isPast() || Carbon::parse($effectiveDate)->isToday()) {
                $needsDocuments = ! $skipDocuments && $request->send_documents;

                // Close current contract immediately

                if ($isCurrentContractIndefinite) {
                    $currentUser->update(['end_date' => $currentContractEndDate]);
                }

                if ($currentAgreement && $currentAgreement->end_date === null) {
                    $currentAgreement->update(['end_date' => $currentContractEndDate]);
                }

                // Update latest agreement history if it has no end_date

                if ($needsDocuments) {
                    // Close current but wait for documents before activating new contract
                    $currentUser->status_id = 2;
                    $currentUser->contract_ended = 1;
                    $currentUser->save();
                    $contractClosureInfo = [
                        'action' => 'immediate_closure_pending_documents',
                        'closed_contract_end_date' => $currentContractEndDate,
                        'message' => 'Current contract closed immediately, new contract pending document completion',
                    ];

                    Log::info('Closed current contract, new contract pending documents', [
                        'user_id' => $userId,
                        'old_end_date' => null,
                        'new_end_date' => $currentContractEndDate,
                        'new_contract_start' => $effectiveDate,
                        'waiting_for_documents' => true,
                    ]);
                } else {
                    // Close current and activate new immediately
                    $contractClosureInfo = [
                        'action' => 'immediate_closure_and_activation',
                        'closed_contract_end_date' => $currentContractEndDate,
                        'message' => 'Indefinite contract closed and new contract activated immediately',
                    ];

                    Log::info('Closed indefinite contract immediately', [
                        'user_id' => $userId,
                        'old_end_date' => null,
                        'new_end_date' => $currentContractEndDate,
                        'new_contract_start' => $effectiveDate,
                    ]);
                }
            } else {
                // New contract starts in future - only close current contract, don't activate new one yet
                if ($currentUser && $currentUser->end_date === null) {
                    $currentUser->update(['end_date' => $currentContractEndDate]);
                }

                if ($currentAgreement && $currentAgreement->end_date === null) {
                    $currentAgreement->update(['end_date' => $currentContractEndDate]);
                }

                $contractClosureInfo = [
                    'action' => 'scheduled_closure',
                    'closed_contract_end_date' => $currentContractEndDate,
                    'message' => 'Indefinite contract will be closed when new contract starts',
                ];

                Log::info('Scheduled closure for indefinite contract', [
                    'user_id' => $userId,
                    'old_end_date' => null,
                    'scheduled_end_date' => $currentContractEndDate,
                    'new_contract_start' => $effectiveDate,
                ]);
            }

            // Create contract history using Option B approach (preserve history)
            $this->createContractHistory($onboardingEmployee, $effectiveDate);

            // Handle document process
            Log::info('Document sending check', [
                'skip_documents' => $skipDocuments,
                'send_documents' => $request->send_documents,
                'condition_met' => (! $skipDocuments && $request->send_documents),
            ]);

            if (! $skipDocuments && $request->send_documents) {
                try {
                    Log::info('Starting document sending process', [
                        'user_id' => $userId,
                        'onboarding_id' => $onboardingEmployee->id,
                    ]);

                    // Use V2 SequiDocs method for document sending
                    $documentRequest = new Request([
                        'user_id' => $onboardingEmployee->id,
                        'name' => $request->name ?? $onboardingEmployee->hiring_signature ?? $onboardingEmployee->full_name,
                        'signing_screeen_url' => $request->signing_screeen_url ?? config('app.url').'/sign-document',
                        'documents' => 'all', // Send all documents
                        'type' => 'send', // New document sending
                        'custom_fields' => $request->custom_fields ?? [],
                    ]);

                    Log::info('Document request prepared', [
                        'request_data' => $documentRequest->all(),
                    ]);

                    // Call the V2 SequiDocs controller method
                    $sequiDocsController = new \App\Http\Controllers\API\V2\SequiDocs\SequiDocsUserDocumentsV2Controller;
                    $documentResponse = $sequiDocsController->sendOfferLetterToOnboardingEmployee($documentRequest);
                    $documentData = $documentResponse->getData(true);
                    $onboardingEmployee->old_status_id = $onboardingEmployee->status_id;
                    $onboardingEmployee->status_id = 4; // Send Documents status
                    $onboardingEmployee->save();

                    Log::info('Document sending result for new contract', [
                        'user_id' => $userId,
                        'onboarding_id' => $onboardingEmployee->id,
                        'document_status' => $documentData['status'] ?? false,
                        'document_message' => $documentData['message'] ?? 'No message',
                        'full_response' => $documentData,
                        'response_code' => $documentResponse->getStatusCode(),
                    ]);

                } catch (\Exception $docError) {
                    Log::error('Document sending failed', [
                        'user_id' => $userId,
                        'onboarding_id' => $onboardingEmployee->id,
                        'error' => $docError->getMessage(),
                        'trace' => $docError->getTraceAsString(),
                    ]);
                }
            }

            // Apply contract immediately if effective date is today/past AND no documents need to be sent
            // If documents need to be sent, wait for document completion before activating
            // Future contracts will be automatically activated by daily cron at 00:45 (ApplyHistoryOnUsersV2:update)
            $activationStatus = 'scheduled';
            $isImmediate = Carbon::parse($effectiveDate)->isPast() || Carbon::parse($effectiveDate)->isToday();
            $needsDocuments = ! $skipDocuments && $request->send_documents;

            if ($isImmediate && ! $needsDocuments) {
                // Contract starts today/past and no documents needed - activate immediately
                $this->applyContractImmediately($userId, $effectiveDate);
                $activationStatus = 'immediate';
            } elseif ($isImmediate && $needsDocuments) {
                $onboardingEmployee->old_status_id = $onboardingEmployee->status_id;
                $onboardingEmployee->status_id = 4; // Send Documents status
                $onboardingEmployee->save();
                // Contract starts today/past but documents need to be signed - wait for completion
                $activationStatus = 'pending_documents';
                Log::info('Contract activation deferred pending document completion', [
                    'user_id' => $userId,
                    'effective_date' => $effectiveDate,
                    'needs_documents' => $needsDocuments,
                ]);
            } else {
                $onboardingEmployee->old_status_id = $onboardingEmployee->status_id;
                $onboardingEmployee->status_id = 1; // Send Documents status
                $onboardingEmployee->save();
            }

            // Update onboarding status to completed

            DB::commit();

            $documentsStatus = false;
            $documentsMessage = '';
            if (! $skipDocuments && $request->send_documents && isset($documentData)) {
                $documentsStatus = $documentData['status'] ?? false;
                $documentsMessage = $documentData['message'] ?? '';
            }

            return response()->json([
                'ApiName' => 'complete-new-contract',
                'status' => true,
                'data' => [
                    'user_id' => $userId,
                    'effective_date' => $effectiveDate,
                    'end_date' => $endDate,
                    'activation_status' => $activationStatus,
                    'contract_id' => $onboardingEmployee->id,
                    'documents_sent' => $documentsStatus,
                    'documents_message' => $documentsMessage,
                    'indefinite_contract_closed' => $isCurrentContractIndefinite ?? false,
                    'contract_closure_info' => $contractClosureInfo ?? [],
                ],
                'message' => match ($activationStatus) {
                    'immediate' => 'The new contract has been applied successfully.',
                    'pending_documents' => 'The current contract has been closed. The new contract will become active once all required documents are signed.',
                    'scheduled' => "The new contract is scheduled to start on {$effectiveDate} and will be activated automatically.",
                    default => 'New contract created successfully'
                },
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'ApiName' => 'complete-new-contract',
                'status' => false,
                'message' => 'Failed to complete new contract: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create contract history records (following directHiredEmployee pattern)
     * NO comparison needed - create history for all entries in related tables
     */
    private function createContractHistory($onboardingEmployee, $effectiveDate = null)
    {
        $userId = $onboardingEmployee->user_id;
        $onbardingUserId = $onboardingEmployee->id; // OnboardingEmployee ID for related tables
        $authUserId = auth()->id();
        $effectiveDate = $effectiveDate ?? $onboardingEmployee->period_of_agreement_start_date;

        // Get current user data for old_* fields in agreement history
        $currentUser = User::find($userId);
        $currentAgreement = UserAgreementHistory::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first();

        // Create UserAgreementHistory record (always create for new contracts)
        UserAgreementHistory::create([
            'user_id' => $userId,
            'updater_id' => $authUserId,
            'probation_period' => $onboardingEmployee->probation_period,
            'old_probation_period' => $currentUser->probation_period,
            'hiring_bonus_amount' => $onboardingEmployee->hiring_bonus_amount,
            'old_hiring_bonus_amount' => $currentUser->hiring_bonus_amount,
            'date_to_be_paid' => $onboardingEmployee->date_to_be_paid,
            'old_date_to_be_paid' => $currentUser->date_to_be_paid,
            'period_of_agreement' => $effectiveDate,
            'old_period_of_agreement' => $currentAgreement ? $currentAgreement->period_of_agreement : $currentUser->period_of_agreement_start_date,
            'end_date' => $onboardingEmployee->end_date,
            'old_end_date' => $currentAgreement ? $currentAgreement->end_date : $currentUser->end_date,
            'offer_expiry_date' => $onboardingEmployee->offer_expiry_date,
            'old_offer_expiry_date' => $currentUser->offer_expiry_date,
            'hired_by_uid' => $authUserId,
        ]);

        // Create UserOrganizationHistory records for products (following directHiredEmployee pattern)
        $positionProduct = PositionProduct::where(['position_id' => $onboardingEmployee->sub_position_id])
            ->where('effective_date', '<=', date('Y-m-d'))
            ->orderBy('effective_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();

        if ($positionProduct) {
            $products = PositionProduct::where([
                'position_id' => $onboardingEmployee->sub_position_id,
                'effective_date' => $positionProduct->effective_date,
            ])->get();
        } else {
            $products = PositionProduct::where(['position_id' => $onboardingEmployee->sub_position_id])
                ->whereNull('effective_date')
                ->get();
        }

        foreach ($products as $product) {
            UserOrganizationHistory::create([
                'user_id' => $userId,
                'updater_id' => $authUserId ?? 0,
                'product_id' => $product->product_id,
                'position_id' => $onboardingEmployee->position_id,
                'sub_position_id' => $onboardingEmployee->sub_position_id,
                'effective_date' => $effectiveDate,
                'self_gen_accounts' => $onboardingEmployee->self_gen_accounts,
            ]);
        }

        // Commission History: Get ALL entries and create history for each (like directHiredEmployee)
        $commissions = OnboardingUserRedline::where('user_id', $onbardingUserId)->get();
        foreach ($commissions as $commission) {
            $usercommissiondata = UserCommissionHistory::create([
                'user_id' => $userId,
                'commission_effective_date' => $effectiveDate,
                'product_id' => $commission->product_id,
                'position_id' => $onboardingEmployee->position_id,
                'core_position_id' => $commission->core_position_id,
                'sub_position_id' => $onboardingEmployee->sub_position_id,
                'updater_id' => $authUserId ?? 0,
                'self_gen_user' => $commission->self_gen_user,
                'commission' => $commission->commission,
                'commission_type' => $commission->commission_type,
                'custom_sales_field_id' => $commission->custom_sales_field_id ?? null,
                'tiers_id' => $commission->tiers_id,
            ]);

            // Handle tiers if enabled
            if ($this->companySettingtiers?->status) {
                $range = OnboardingCommissionTiersRange::where('onboarding_commission_id', $commission->id)->get();
                if ($commission->tiers_id > 0) {
                    if ($range->isNotEmpty()) {
                        foreach ($range as $rang) {
                            UserCommissionHistoryTiersRange::create([
                                'user_id' => $userId,
                                'user_commission_history_id' => $usercommissiondata->id ?? null,
                                'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                                'value' => $rang['value'] ?? null,
                            ]);
                        }
                    }
                }
            }
        }

        // Redline History: Get ALL entries and create history for each (like directHiredEmployee)
        $redLines = OnboardingEmployeeRedline::where('user_id', $onbardingUserId)->get();
        foreach ($redLines as $redLine) {
            UserRedlines::create([
                'user_id' => $userId,
                'start_date' => $effectiveDate,
                'position_type' => $onboardingEmployee->position_id,
                'core_position_id' => $redLine->core_position_id,
                'sub_position_type' => $redLine->position_id,
                'updater_id' => $authUserId ?? 0,
                'redline_amount_type' => $redLine->redline_amount_type,
                'redline' => $redLine->redline,
                'redline_type' => $redLine->redline_type,
                'self_gen_user' => $redLine->self_gen_user,
            ]);
        }

        // Upfront History: Get ALL entries and create history for each (like directHiredEmployee)
        $upfronts = OnboardingEmployeeUpfront::where('user_id', $onbardingUserId)->get();
        foreach ($upfronts as $upfront) {
            $userupfrontdata = UserUpfrontHistory::create([
                'user_id' => $userId,
                'upfront_effective_date' => $effectiveDate,
                'position_id' => $onboardingEmployee->position_id,
                'core_position_id' => $upfront->core_position_id,
                'product_id' => $upfront->product_id,
                'milestone_schema_id' => $upfront->milestone_schema_id,
                'milestone_schema_trigger_id' => $upfront->milestone_schema_trigger_id,
                'sub_position_id' => $upfront->position_id,
                'updater_id' => $authUserId ?? 0,
                'upfront_pay_amount' => $upfront->upfront_pay_amount,
                'upfront_sale_type' => $upfront->upfront_sale_type,
                'self_gen_user' => $upfront->self_gen_user,
                'custom_sales_field_id' => $upfront->custom_sales_field_id,
            ]);
        }

        // Override History: Get ALL entries and create history for each (like directHiredEmployee)
        $overrides = OnboardingEmployeeOverride::where('user_id', $onbardingUserId)->get();
        foreach ($overrides as $override) {
            $useroverridedata = UserOverrideHistory::create([
                'user_id' => $userId,
                'override_effective_date' => $effectiveDate,
                'updater_id' => $authUserId ?? 0,
                'product_id' => $override->product_id,
                'direct_overrides_amount' => $override->direct_overrides_amount,
                'direct_overrides_type' => $override->direct_overrides_type,
                'indirect_overrides_amount' => $override->indirect_overrides_amount,
                'indirect_overrides_type' => $override->indirect_overrides_type,
                'office_overrides_amount' => $override->office_overrides_amount,
                'office_overrides_type' => $override->office_overrides_type,
                'office_stack_overrides_amount' => $override->office_stack_overrides_amount,
                'direct_tiers_id' => $override->direct_tiers_id ?? null,
                'indirect_tiers_id' => $override->indirect_tiers_id ?? null,
                'office_tiers_id' => $override->office_tiers_id ?? null,
                'self_gen_user' => $override->self_gen_user,
                // Custom Sales Field IDs
                'direct_custom_sales_field_id' => $override->direct_custom_sales_field_id ?? null,
                'indirect_custom_sales_field_id' => $override->indirect_custom_sales_field_id ?? null,
                'office_custom_sales_field_id' => $override->office_custom_sales_field_id ?? null,
            ]);

            if ($this->companySettingtiers?->status) {
                $range = OnboardingDirectOverrideTiersRange::where('onboarding_direct_override_id', $override->id)->get();
                if ($override->direct_tiers_id > 0) {
                    if ($range->isNotEmpty()) {
                        foreach ($range as $rang) {
                            UserDirectOverrideHistoryTiersRange::create([
                                'user_id' => $userId,
                                'user_override_history_id' => $useroverridedata->id ?? null,
                                'tiers_schema_id' => $rang['tiers_schema_id'] ?? null,
                                'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                                'value' => $rang['value'] ?? null,
                                'value_type' => $rang['value_type'] ?? null,
                            ]);
                        }
                    }
                }
                $ind_range = OnboardingIndirectOverrideTiersRange::where('onboarding_indirect_override_id', $override->id)->get();
                if ($override->indirect_tiers_id > 0) {
                    if ($ind_range->isNotEmpty()) {
                        foreach ($ind_range as $rang) {
                            UserIndirectOverrideHistoryTiersRange::create([
                                'user_id' => $userId,
                                'user_override_history_id' => $useroverridedata->id ?? null,
                                'tiers_schema_id' => $rang['tiers_schema_id'] ?? null,
                                'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                                'value' => $rang['value'] ?? null,
                                'value_type' => $rang['value_type'] ?? null,
                            ]);
                        }
                    }
                }
                $overoff_range = OnboardingOverrideOfficeTiersRange::where('onboarding_override_office_id', $override->id)->get();
                if ($override->office_tiers_id > 0) {
                    if ($overoff_range->isNotEmpty()) {
                        foreach ($overoff_range as $rang) {
                            UserOfficeOverrideHistoryTiersRange::create([
                                'user_id' => $userId,
                                'user_office_override_history_id' => $useroverridedata->id ?? null,
                                'tiers_schema_id' => $rang['tiers_schema_id'] ?? null,
                                'tiers_levels_id' => $rang['tiers_levels_id'] ?? null,
                                'value' => $rang['value'] ?? null,
                                'value_type' => $rang['value_type'] ?? null,
                            ]);
                        }
                    }
                }
            }
        }

        // Wages History: Create wage history for the employee (following directHiredEmployee pattern)
        $wages = OnboardingEmployeeWages::where('user_id', $onbardingUserId)->first();
        if ($wages) {
            UserWagesHistory::create([
                'user_id' => $userId,
                'updater_id' => $authUserId ?? 0,
                'effective_date' => $effectiveDate,
                'pay_type' => $wages->pay_type,
                'pay_rate' => $wages->pay_rate,
                'pay_rate_type' => $wages->pay_rate_type,
                'expected_weekly_hours' => $wages->expected_weekly_hours,
                'overtime_rate' => $wages->overtime_rate,
                'pto_hours' => $wages->pto_hours,
                'unused_pto_expires' => $wages->unused_pto_expires,
                'pto_hours_effective_date' => $effectiveDate,
            ]);
        }

        // Withheld History: Get ALL entries and create history for each (following directHiredEmployee pattern)
        $withHeld = OnboardingEmployeeWithheld::where('user_id', $onbardingUserId)->get();
        foreach ($withHeld as $value) {
            UserWithheldHistory::create([
                'user_id' => $userId,
                'updater_id' => $authUserId ?? 0,
                'position_id' => $onboardingEmployee->position_id,
                'product_id' => $value->product_id,
                'sub_position_id' => $value->position_id,
                'withheld_effective_date' => $effectiveDate,
                'withheld_amount' => $value->withheld_amount,
                'withheld_type' => $value->withheld_type,
                'self_gen_withheld_amount' => $value->self_gen_withheld_amount,
                'self_gen_withheld_type' => $value->self_gen_withheld_type,
            ]);
        }

        // Deduction History: Get ALL entries and create history for each (following directHiredEmployee pattern)
        $deductions = EmployeeOnboardingDeduction::where('user_id', $onbardingUserId)->get();
        foreach ($deductions as $deduction) {

            UserDeduction::create([
                'deduction_type' => $deduction->deduction_type,
                'cost_center_name' => $deduction->cost_center_name,
                'cost_center_id' => $deduction->cost_center_id,
                'ammount_par_paycheck' => $deduction->ammount_par_paycheck,
                'deduction_setting_id' => isset($deduction->deduction_setting_id) ? $deduction->deduction_setting_id : null,
                'position_id' => $deduction->position_id,
                'sub_position_id' => $onboardingEmployee->sub_position_id,
                'user_id' => $userId,
                'effective_date' => $effectiveDate,
            ]);

            UserDeductionHistory::create([
                'user_id' => $userId,
                'updater_id' => $authUserId ?? 0,
                'cost_center_id' => $deduction->cost_center_id,
                'amount_par_paycheque' => $deduction->ammount_par_paycheck,
                'pay_period_from' => $deduction->pay_period_from,
                'pay_period_to' => $deduction->pay_period_to,
                'effective_date' => $effectiveDate,
            ]);
        }
    }

    // copyCompensationToMainRecord method removed - now using directHiredEmployee pattern

    /**
     * Apply contract changes immediately to user record
     * Updates user table directly for immediate effect contracts
     */
    public function applyContractImmediately($userId, $effectiveDate)
    {
        $user = User::find($userId);
        if (! $user) {
            throw new \Exception("User not found: $userId");
        }

        // Get the onboarding employee record to access organizational data
        $onboardingEmployee = OnboardingEmployees::where('user_id', $userId)
            ->where('is_new_contract', 1)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $onboardingEmployee) {
            throw new \Exception("Onboarding employee record not found for user: $userId");
        }

        // Get the latest history records to apply to user
        $latestAgreement = UserAgreementHistory::where('user_id', $userId)
            ->where('period_of_agreement', '<=', $effectiveDate)
            ->orderBy('created_at', 'desc')
            ->first();

        $latestCommission = UserCommissionHistory::where('user_id', $userId)
            ->where('commission_effective_date', '<=', $effectiveDate)
            ->orderBy('created_at', 'desc')
            ->first();

        $latestRedline = UserRedlines::where('user_id', $userId)
            ->where('start_date', '<=', $effectiveDate)
            ->orderBy('created_at', 'desc')
            ->first();

        $latestUpfront = UserUpfrontHistory::where('user_id', $userId)
            ->where('upfront_effective_date', '<=', $effectiveDate)
            ->orderBy('created_at', 'desc')
            ->first();

        $latestOverride = UserOverrideHistory::where('user_id', $userId)
            ->where('override_effective_date', '<=', $effectiveDate)
            ->orderBy('created_at', 'desc')
            ->first();

        $latestWithheld = UserWithheldHistory::where('user_id', $userId)
            ->where('withheld_effective_date', '<=', $effectiveDate)
            ->orderBy('created_at', 'desc')
            ->first();

        // Update user record with new contract data - following directHiredEmployee pattern
        $updateData = [];

        // Organizational/Position data from onboarding record (like directHiredEmployee)
        $updateData['position_id'] = $onboardingEmployee->position_id;
        $updateData['sub_position_id'] = $onboardingEmployee->sub_position_id;
        $updateData['office_id'] = $onboardingEmployee->office_id;
        $updateData['state_id'] = $onboardingEmployee->state_id;
        $updateData['self_gen_accounts'] = $onboardingEmployee->self_gen_accounts;
        $updateData['self_gen_type'] = $onboardingEmployee->self_gen_type;
        $updateData['department_id'] = $onboardingEmployee->department_id;
        $updateData['is_manager'] = $onboardingEmployee->is_manager;
        $updateData['manager_id'] = $onboardingEmployee->manager_id;
        $updateData['team_id'] = $onboardingEmployee->team_id;
        $updateData['recruiter_id'] = $onboardingEmployee->recruiter_id;

        // Effective dates for organizational changes
        $updateData['position_id_effective_date'] = $effectiveDate;
        $updateData['is_manager_effective_date'] = ($onboardingEmployee->is_manager == 1) ? $effectiveDate : null;
        $updateData['manager_id_effective_date'] = $effectiveDate;
        $updateData['team_id_effective_date'] = (! empty($onboardingEmployee->team_id)) ? $effectiveDate : null;

        // Agreement data
        if ($latestAgreement) {
            $updateData['period_of_agreement_start_date'] = $latestAgreement->period_of_agreement;
            $updateData['end_date'] = $latestAgreement->end_date;
            $updateData['probation_period'] = $latestAgreement->probation_period;
            $updateData['hiring_bonus_amount'] = $latestAgreement->hiring_bonus_amount;
            $updateData['date_to_be_paid'] = $latestAgreement->date_to_be_paid;
            $updateData['offer_expiry_date'] = $latestAgreement->offer_expiry_date;
        }

        // Commission data
        if ($latestCommission) {
            $updateData['commission'] = $latestCommission->commission;
            $updateData['commission_type'] = $latestCommission->commission_type;
            $updateData['commission_effective_date'] = $latestCommission->commission_effective_date;
            $updateData['commission_custom_sales_field_id'] = $latestCommission->custom_sales_field_id ?? null;
        }

        // Redline data
        if ($latestRedline) {
            $updateData['redline'] = $latestRedline->redline;
            $updateData['redline_type'] = $latestRedline->redline_type;
            $updateData['redline_amount_type'] = $latestRedline->redline_amount_type;
        }

        // Upfront data
        if ($latestUpfront) {
            $updateData['upfront_pay_amount'] = $latestUpfront->upfront_pay_amount;
            $updateData['upfront_sale_type'] = $latestUpfront->upfront_sale_type;
            $updateData['upfront_effective_date'] = $latestUpfront->upfront_effective_date;
        }

        // Override data
        if ($latestOverride) {
            $updateData['direct_overrides_amount'] = $latestOverride->direct_overrides_amount;
            $updateData['direct_overrides_type'] = $latestOverride->direct_overrides_type;
            $updateData['direct_custom_sales_field_id'] = $latestOverride->direct_custom_sales_field_id;
            $updateData['indirect_overrides_amount'] = $latestOverride->indirect_overrides_amount;
            $updateData['indirect_overrides_type'] = $latestOverride->indirect_overrides_type;
            $updateData['indirect_custom_sales_field_id'] = $latestOverride->indirect_custom_sales_field_id;
            $updateData['office_overrides_amount'] = $latestOverride->office_overrides_amount;
            $updateData['office_overrides_type'] = $latestOverride->office_overrides_type;
            $updateData['office_custom_sales_field_id'] = $latestOverride->office_custom_sales_field_id;
            $updateData['override_effective_date'] = $latestOverride->override_effective_date;
        }

        // Withheld data
        if ($latestWithheld) {
            $updateData['withheld_amount'] = $latestWithheld->withheld_amount;
            $updateData['withheld_type'] = $latestWithheld->withheld_type;
            $updateData['withheld_effective_date'] = $latestWithheld->withheld_effective_date;
        }

        // Set contract as active and not ended
        $updateData['contract_ended'] = 0;
        $updateData['status_id'] = 1; // Active status

        // Update the user record
        $user->update($updateData);

        $onboardingEmployee->old_status_id = $onboardingEmployee->status_id;
        $onboardingEmployee->status_id = 7;
        $onboardingEmployee->save();

        NewSequiDocsDocument::where('user_id', '=', $onboardingEmployee->id)->where('user_id_from', '=', 'onboarding_employees')->where('is_active', 1)->Update(['user_id' => $userId, 'user_id_from' => 'users']);
        Documents::where('user_id', '=', $onboardingEmployee->id)->where('user_id_from', '=', 'onboarding_employees')->Update(['user_id' => $userId, 'user_id_from' => 'users']);
        // Recalculate all sales from effective date forward with new contract terms
        $recalculationResult = recalculateUserSalesFromEffectiveDate($userId, $effectiveDate);

        Log::info('New contract applied immediately to user record', [
            'user_id' => $userId,
            'effective_date' => $effectiveDate,
            'updated_fields' => array_keys($updateData),
            'applied_by' => auth()->id(),
            'sales_recalculation' => $recalculationResult,
        ]);
    }

    /**
     * Activate new contract after document completion
     * Called when all required documents are signed
     */
    public function activateContractAfterDocuments($onboardingEmployeeId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $onboardingEmployee = OnboardingEmployees::find($onboardingEmployeeId);
            if (! $onboardingEmployee) {
                throw new \Exception('Onboarding employee not found');
            }

            $userId = $onboardingEmployee->user_id;
            $effectiveDate = $onboardingEmployee->period_of_agreement_start_date;
            $userData = User::find($userId);
            // Check if this is a contract that was waiting for document completion
            $isImmediate = Carbon::parse($effectiveDate)->isPast() || Carbon::parse($effectiveDate)->isToday();

            if ($isImmediate) {
                // Apply the contract now that documents are completed
                $this->applyContractImmediately($userId, $effectiveDate);

                // Update onboarding status to hired

                Log::info('Contract activated after document completion', [
                    'user_id' => $userId,
                    'onboarding_id' => $onboardingEmployeeId,
                    'effective_date' => $effectiveDate,
                ]);

                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => 'All documents signed. Contract activated successfully.',
                    'data' => [
                        'user_id' => $userId,
                        'effective_date' => $effectiveDate,
                        'activation_status' => 'activated_after_documents',
                    ],
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'All documents signed, Contract will be activated on scheduled date',
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Failed to activate contract after documents', [
                'onboarding_id' => $onboardingEmployeeId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to activate contract: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Skip documents and complete contract without signing
     */
    public function skipDocumentsContract(Request $request)
    {
        // Validate basic requirements
        $validator = Validator::make($request->all(), [
            'onboarding_employee_id' => 'required|exists:onboarding_employees,id',
            // Dates should already be set by employeeAgreement API
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Set skip_documents flag and call completeNewContract
        $request->merge(['skip_documents' => true]);

        return $this->completeNewContract($request);
    }

    // Old helper methods removed - now using directHiredEmployee pattern with foreach loops

    /**
     * Check if a contract start date will override existing contract
     * Simple check - returns override status and message only
     */
    public function checkContractOverride(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'contract_start_date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'ApiName' => 'check-contract-overwrite',
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $userId = $request->user_id;
            $contractStartDate = $request->contract_start_date;
            $user = User::find($userId);

            // Validate 1099 only
            if ($user->worker_type === 'w2') {
                return response()->json([
                    'ApiName' => 'check-contract-overwrite',
                    'status' => false,
                    'message' => 'Cannot check contract overwrite for W2 employees. Only 1099 contractors are allowed.',
                ], 403);
            }

            if ((isset($request->is_new_contract) && $request->is_new_contract == 1)) {
                // Check if there's already a new contract for the same date
                $existingNewContract = OnboardingEmployees::where('user_id', $userId)
                    ->where('is_new_contract', 1)
                    ->where('period_of_agreement_start_date', $contractStartDate)
                    ->whereNotIn('status_id', [2, 3, 6, 11, 12, 13]) // Exclude cancelled/rejected/declined statuses
                    ->first();

                if ($existingNewContract && ($request->onboarding_id != $existingNewContract->id)) {
                    return response()->json([
                        'ApiName' => 'check-contract-overwrite',
                        'status' => false,
                        'message' => 'You cannot add a new contract for the same date. There is already a new contract scheduled for '.Carbon::parse($contractStartDate)->format('Y-m-d').'.',
                        'data' => [
                            'existing_contract_id' => $existingNewContract->id,
                            'existing_contract_status' => $existingNewContract->statusDetail->status ?? 'N/A',
                            'requested_date' => $contractStartDate,
                        ],
                    ], 400); // 409 Conflict status code
                }
            }

            $isImmediate = Carbon::parse($contractStartDate)->isPast() || Carbon::parse($contractStartDate)->isToday();
            $willOverride = false;
            $message = '';
            $specialHandling = [];

            if ($isImmediate) {
                $willOverride = true;
                $message = 'This contract will immediately overwrite the current active contract and update the user\'s employment terms.';

                // Check if current contract is indefinite (no end_date)
                if ($user->end_date === null) {
                    $specialHandling[] = 'indefinite_closure';
                    $message .= ' The current indefinite contract will be automatically closed.';
                }
            } else {
                // Check for various future contract conflicts

                // 1. Check for exact same start date
                $exactDateContract = UserAgreementHistory::where('user_id', $userId)
                    ->where('period_of_agreement', $contractStartDate)
                    ->first();

                // 2. Check if current contract is indefinite (no end date)
                $hasIndefiniteContract = ($user->end_date === null);

                // 3. Check for overlapping contracts
                $overlappingContract = UserAgreementHistory::where('user_id', $userId)
                    ->where('period_of_agreement', '<=', $contractStartDate)
                    ->where(function ($q) use ($contractStartDate) {
                        $q->where('end_date', '>=', $contractStartDate)
                            ->orWhereNull('end_date'); // Include indefinite contracts
                    })
                    ->orderBy('period_of_agreement', 'desc')
                    ->first();

                if ($exactDateContract) {
                    $willOverride = true;
                    $message = 'This contract will replace an existing scheduled contract with the same start date.';
                } elseif ($hasIndefiniteContract) {
                    $willOverride = true;
                    $specialHandling[] = 'indefinite_closure';
                    $message = 'This contract will close the current indefinite contract and start on the specified date.';
                } elseif ($overlappingContract) {
                    $willOverride = true;
                    $message = 'This contract will overwrite an existing contract that extends past the proposed start date.';
                } else {
                    $willOverride = false;
                    $message = 'This contract will be scheduled for future activation without overwriting any existing contracts.';
                }
            }

            return response()->json([
                'ApiName' => 'check-contract-overwrite',
                'status' => true,
                'message' => 'Contract overwrite check completed successfully',
                'data' => [
                    'user_id' => $userId,
                    'contract_start_date' => $contractStartDate,
                    'will_override' => $willOverride,
                    'override_message' => $message,
                    'is_immediate' => $isImmediate,
                    'special_handling' => $specialHandling,
                    'current_contract_indefinite' => $user->end_date === null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error checking contract overwrite', [
                'user_id' => $request->user_id ?? 'N/A',
                'contract_start_date' => $request->contract_start_date ?? 'N/A',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ApiName' => 'check-contract-overwrite',
                'status' => false,
                'message' => 'An error occurred while checking contract overwrite',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save employee data to HighLevel
     *
     * @param  object  $user  User object with employee data
     */
    protected function saveEmployeeToHighLevel(object $user): ?array
    {
        try {
            // Format user data for HighLevel
            $data = User::with('office', 'managerDetail')->where('id', $user->id)->first();
            $office = null;
            $manager = null;
            if ($data) {
                $office = $data->office;
                $manager = $data->managerDetail;
            }

            $contactData = [
                'locationId' => config('services.highlevel.location_id'),
                'email' => $user->email ?? null,
                'firstName' => $user->first_name ?? null,
                'lastName' => $user->last_name ?? null,
                'phone' => $user->mobile_no ?? null,
                'address1' => $user->home_address ?? null,
                'city' => $user->home_address_city ?? null,
                'state' => $user->home_address_state ?? null,
                'postalCode' => $user->home_address_zip ?? null,
                'dateOfBirth' => $user->dob ?? null,
                // Add custom fields if needed
                'customFields' => [
                    ['key' => 'sequifi_id', 'value' => $user->employee_id ?? null],
                    ['key' => 'status', 'value' => 'Active'],
                    ['key' => 'office_id', 'value' => isset($office) ? $office->id : null],
                    ['key' => 'office_name', 'value' => isset($office) ? $office->office_name : null],
                    ['key' => 'manager_id', 'value' => isset($manager) ? $manager->id : null],
                    ['key' => 'manager_name', 'value' => isset($manager) ? ($manager->first_name ?? '').' '.($manager->last_name ?? '') : null],
                    ['key' => 'manager_email', 'value' => isset($manager) ? $manager->email ?? '' : null],
                ],
            ];

            // Log the attempt
            \Illuminate\Support\Facades\Log::info('Pushing employee data to HighLevel', [
                'employee_id' => $user->id,
                'email' => $user->email,
            ]);

            // Send to HighLevel
            $response = $this->upsertHighLevelContact($contactData);

            try {
                InterigationTransactionLog::create([
                    'interigation_name' => 'HighLevelRepPush Push',
                    'api_name' => 'Push Rep Data',
                    'payload' => json_encode($contactData),
                    'response' => json_encode($response),
                    'url' => 'https://services.leadconnectorhq.com/contacts/upsert',
                ]);
            } catch (\Exception $e) {
                // Log::error('Error upserting HighLevel contact: ' . $e->getMessage());
            }

            // If we got a successful response with a contact ID, save it to the user record
            if ($response && isset($response['contact']['id'])) {
                $contactId = $response['contact']['id'];

                // Update the user record with the HighLevel contact ID
                \App\Models\User::where('id', $user->id)->update([
                    'aveyo_hs_id' => $contactId,
                ]);

                \Illuminate\Support\Facades\Log::info('Updated user with HighLevel contact ID', [
                    'user_id' => $user->id,
                    'aveyo_hs_id' => $contactId,
                ]);
            }

            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error pushing employee data to HighLevel', [
                'error' => $e->getMessage(),
                'employee_id' => $user->id ?? null,
            ]);

            return null;
        }
    }

    /**
     * Transform custom field type for display - only when Custom Sales Fields feature is enabled.
     * Uses cached helper to avoid repeated CompanyProfile::first() calls.
     * 
     * @param string|null $type The type field (e.g., 'custom field', 'percent', 'per kw')
     * @param int|null $customFieldId The custom_sales_field_id
     * @return string|null Returns 'custom_field_X' if feature enabled and valid, otherwise original type
     */
    private function transformCustomFieldType(?string $type, ?int $customFieldId): ?string
    {
        // Only transform if Custom Sales Fields feature is enabled (using cached helper)
        if (!CustomSalesFieldHelper::isFeatureEnabled()) {
            return $type;
        }

        // Handle custom field: only transform if we have both type AND ID (data integrity check)
        if ($type === 'custom field' && $customFieldId) {
            return 'custom_field_' . $customFieldId;
        }

        // Data without custom field ID: return original type so it displays as "(custom field)"
        // User should re-save to properly link the custom field
        if ($type === 'custom field' && !$customFieldId) {
            return 'custom field';
        }

        return $type;
    }

    /**
     * Get custom_sales_field_id for display - returns null when using custom_field_X format or feature disabled.
     * Uses cached helper to avoid repeated CompanyProfile::first() calls.
     * 
     * @param string|null $type The type field
     * @param int|null $customFieldId The custom_sales_field_id
     * @return int|null
     */
    private function getCustomFieldIdForDisplay(?string $type, ?int $customFieldId): ?int
    {
        // If feature disabled, return the original ID (using cached helper)
        if (!CustomSalesFieldHelper::isFeatureEnabled()) {
            return $customFieldId;
        }

        // If type is 'custom field', we're using custom_field_X format, so don't return redundant ID
        return ($type === 'custom field') ? null : $customFieldId;
    }
}
