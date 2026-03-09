<?php

namespace App\Http\Controllers\API\Reports;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ReportsUsersController extends Controller
{
    /**
     * Generate users list report with filtering, pagination, and search capabilities
     */
    public function users_list_report(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'search' => 'nullable|string|max:255',
                'status' => 'nullable|string|in:active,terminated',
                'position_id' => 'nullable|integer|exists:positions,id',
                'office_id' => 'nullable|integer|exists:locations,id',
                'created_from' => 'nullable|date_format:Y-m-d',
                'created_to' => 'nullable|date_format:Y-m-d',
                'page' => 'nullable|integer|min:1',
                'perpage' => 'nullable|integer|min:1|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Get parameters
            $search = $request->input('search');
            $status = $request->input('status');
            $positionId = $request->input('position_id');
            $officeId = $request->input('office_id');
            $createdFrom = $request->input('created_from');
            $createdTo = $request->input('created_to');
            $page = $request->input('page', 1);
            $perpage = $request->input('perpage', 100);

            // Start building the query
            $query = User::with([
                'positionDetail:id,position_name', // Gets position name from sub_position_id
                'office:id,office_name',
                'recruiter:id,first_name,last_name',
                'additionalRecruiterOne:id,first_name,last_name',
                'additionalRecruiterTwo:id,first_name,last_name',
            ])
                ->select([
                    'id',
                    'employee_id',
                    'first_name',
                    'last_name',
                    'email',
                    'work_email',
                    'mobile_no',
                    'status_id',
                    'sub_position_id',
                    'office_id',
                    'terminate',
                    'stop_payroll',
                    'dismiss',
                    'contract_ended',
                    'disable_login',
                    'dob',
                    'recruiter_id',
                    'additional_recruiter_id1',
                    'additional_recruiter_id2',
                    'additional_info_for_employee_to_get_started',
                    'employee_personal_detail',
                    'period_of_agreement_start_date',
                    'end_date',
                    'created_at',
                    'updated_at',
                ]);

            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$search}%")
                        ->orWhere('first_name', 'LIKE', "%{$search}%")
                        ->orWhere('last_name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%")
                        ->orWhere('employee_id', 'LIKE', "%{$search}%");
                });
            }

            // Apply status filter
            if ($status) {
                switch ($status) {
                    case 'active':
                        $query->where('terminate', 0)
                            ->where('dismiss', 0)
                            ->where('contract_ended', 0)
                            ->where(function ($q) {
                                $q->where('status_id', 1)->orWhereNull('status_id');
                            });
                        break;
                    case 'terminated':
                        $query->where('terminate', 1);
                        break;
                }
            }

            // Apply position filter
            if ($positionId) {
                $query->where('sub_position_id', $positionId);
            }

            // Apply office filter
            if ($officeId) {
                $query->where('office_id', $officeId);
            }

            // Apply date range filter
            if ($createdFrom) {
                $query->whereDate('created_at', '>=', $createdFrom);
            }
            if ($createdTo) {
                $query->whereDate('created_at', '<=', $createdTo);
            }

            // Get total count for pagination
            $totalCount = $query->count();

            // Apply pagination
            $offset = ($page - 1) * $perpage;
            $users = $query->orderBy('id', 'ASC')
                ->offset($offset)
                ->limit($perpage)
                ->get();

            // Transform the data
            $transformedUsers = $users->map(function ($user) {
                // Get recruiter names
                $recruiterName = null;
                if ($user->recruiter) {
                    $recruiterName = trim($user->recruiter->first_name.' '.$user->recruiter->last_name);
                }

                $additionalRecruiterName = null;
                if ($user->additionalRecruiterOne) {
                    $additionalRecruiterName = trim($user->additionalRecruiterOne->first_name.' '.$user->additionalRecruiterOne->last_name);
                } elseif ($user->additionalRecruiterTwo) {
                    $additionalRecruiterName = trim($user->additionalRecruiterTwo->first_name.' '.$user->additionalRecruiterTwo->last_name);
                }

                // Determine overall status based on multiple flags
                $overallStatus = 'Active';
                if ($user->terminate == 1) {
                    $overallStatus = 'Terminated';
                } elseif ($user->dismiss == 1) {
                    $overallStatus = 'Dismissed';
                } elseif ($user->contract_ended == 1) {
                    $overallStatus = 'Contract Ended';
                } elseif ($user->stop_payroll == 1) {
                    $overallStatus = 'Payroll Stopped';
                } elseif ($user->disable_login == 1) {
                    $overallStatus = 'Login Disabled';
                } elseif ($user->status_id == 0) {
                    $overallStatus = 'Inactive';
                }

                return [
                    // 'id' => $user->id,
                    // 'employee_id' => $user->employee_id, // For backward compatibility
                    'sequifi_id' => $user->employee_id,
                    // 'office_location_office_id' => $user->office_id,
                    'office_location_name' => $user->office->office_name ?? null,
                    'position' => $user->positionDetail->position_name ?? null,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    // 'status_id' => $user->status_id,
                    'terminated' => $user->terminate == 1 ? 'Yes' : 'No',
                    'status' => $overallStatus,
                    'status_change_date' => $user->updated_at ? $user->updated_at->format('Y-m-d H:i:s') : null,
                    'phone_number' => $user->mobile_no,
                    'email' => $user->email,
                    'additional_email' => $user->work_email,
                    'dob' => $user->dob,
                    'recruiter' => $recruiterName,
                    'additional_recruiter' => $additionalRecruiterName,
                    'additional_info_for_employee_to_get_started' => $this->beautifyAdditionalInfo($user->additional_info_for_employee_to_get_started),
                    'employee_personal_detail' => $this->beautifyPersonalDetail($user->employee_personal_detail),
                    'hire_date' => $user->created_at ? $user->created_at->format('Y-m-d H:i:s') : null,
                    'start_date' => $user->period_of_agreement_start_date,
                    'end_date' => $user->end_date,
                    'status_flags' => [
                        'terminate' => $user->terminate == 1 ? 'Yes' : 'No',
                        'stop_payroll' => $user->stop_payroll == 1 ? 'Yes' : 'No',
                        'dismiss' => $user->dismiss == 1 ? 'Yes' : 'No',
                        'contract_ended' => $user->contract_ended == 1 ? 'Yes' : 'No',
                        'disable_login' => $user->disable_login == 1 ? 'Yes' : 'No',
                    ],
                ];
            });

            // Calculate pagination metadata
            $totalPages = ceil($totalCount / $perpage);
            $hasNextPage = $page < $totalPages;
            $hasPrevPage = $page > 1;

            return response()->json([
                'ApiName' => 'Users_List_Report',
                'status' => true,
                'message' => 'Successfully retrieved users list.',
                'data' => $transformedUsers,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perpage,
                    'total_records' => $totalCount,
                    'total_pages' => $totalPages,
                    'has_next_page' => $hasNextPage,
                    'has_prev_page' => $hasPrevPage,
                    'next_page' => $hasNextPage ? $page + 1 : null,
                    'prev_page' => $hasPrevPage ? $page - 1 : null,
                ],
                'filters_applied' => [
                    'search' => $search,
                    'status' => $status,
                    'position_id' => $positionId,
                    'office_id' => $officeId,
                    'created_from' => $createdFrom,
                    'created_to' => $createdTo,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Users List Report API Error', [
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while retrieving users list',
                'error' => 'Internal server error',
                'error_code' => 500,
            ], 500);
        }
    }

    /**
     * Beautify additional info JSON field
     */
    private function beautifyAdditionalInfo($jsonData)
    {
        if (empty($jsonData)) {
            return null;
        }

        $data = is_string($jsonData) ? json_decode($jsonData, true) : $jsonData;

        if (! is_array($data)) {
            return null;
        }

        $beautified = [];
        foreach ($data as $field) {
            if (isset($field['field_name'])) {
                $fieldInfo = [
                    'question' => $field['field_name'],
                    'type' => $field['field_type'] ?? 'text',
                    'value' => $field['value'] ?? null,
                    'required' => ($field['field_required'] ?? '') === 'required',
                ];

                // Add options for dropdown/select fields
                if (isset($field['attribute_option']) && is_array($field['attribute_option']) && ! empty($field['attribute_option'])) {
                    $fieldInfo['options'] = $field['attribute_option'];
                }

                $beautified[] = $fieldInfo;
            }
        }

        return $beautified;
    }

    /**
     * Beautify employee personal detail JSON field
     */
    private function beautifyPersonalDetail($jsonData)
    {
        if (empty($jsonData)) {
            return null;
        }

        $data = is_string($jsonData) ? json_decode($jsonData, true) : $jsonData;

        if (! is_array($data)) {
            return null;
        }

        $beautified = [];
        foreach ($data as $field) {
            if (isset($field['field_name'])) {
                $fieldInfo = [
                    'question' => $field['field_name'],
                    'type' => $field['field_type'] ?? 'text',
                    'value' => $field['value'] ?? null,
                    'required' => ($field['field_required'] ?? '') === 'required',
                ];

                // Add options for dropdown/select fields
                if (isset($field['attribute_option']) && is_array($field['attribute_option']) && ! empty($field['attribute_option'])) {
                    $fieldInfo['options'] = $field['attribute_option'];
                }

                $beautified[] = $fieldInfo;
            }
        }

        return $beautified;
    }
}
