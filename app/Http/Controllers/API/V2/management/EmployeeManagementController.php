<?php

namespace App\Http\Controllers\API\V2\management;

use App\Http\Controllers\Controller;
use App\Models\AdditionalLocations;
use App\Models\CompanySetting;
use App\Models\ManualOverrides;
use App\Models\ManualOverridesHistory;
use App\Models\OverrideStatus;
use App\Models\overrideSystemSetting;
use App\Models\PositionOverride;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserAdditionalOfficeOverrideHistory;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserOverrides;
use App\Models\UserTransferHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Payroll;
use App\Models\UserCommission;
use App\Jobs\RecalculateSalesJob;
use Illuminate\Support\Facades\Log;
class EmployeeManagementController extends Controller
{
    public function my_overrides(Request $request, $id): JsonResponse
    {
        $productId = (int) $request->input('prodcutId') ? (int) $request->input('prodcutId') : null;
        $search = $request->input('search');
        $user = User::where('id', $id)->first();
        if (! $user) {
            return response()->json([
                'ApiName' => 'my_overrides',
                'status' => false,
                'message' => 'User not found!!',
                'data' => [],
            ], 400);
        }

        $overrideCheck = CompanySetting::where(['type' => 'overrides', 'status' => '1'])->first();
        if (! $overrideCheck) {
            return response()->json([
                'ApiName' => 'my_overrides',
                'status' => false,
                'message' => 'Overrides are disabled!!',
                'data' => [],
            ], 400);
        }

        $date = date('Y-m-d');
        $baseQuery = UserOverrideHistory::where('user_id', $id)->where('override_effective_date', '<=', $date)->orderBy('override_effective_date', 'DESC');
        if ($productId) {
            $baseQuery->where('product_id', $productId);
        }
        $userOverride = $baseQuery->first();

        $directOverrides = [];
        $inDirectOverrides = [];
        $officeOverrides = [];
        $manualOverrides = [];
        $stackOverrides = [];
        if ($userOverride) {
            // DIRECT OVERRIDES START
            if ($userOverride->direct_overrides_amount || $userOverride->direct_overrides_type) {
                $directs = User::select('id', 'position_id', 'recruiter_id', 'sub_position_id', 'first_name', 'last_name', 'image', 'is_super_admin', 'is_manager')
                    ->where(function ($query) use ($user) {
                        $query->where('users.recruiter_id', $user->id)
                            ->orWhere('users.additional_recruiter_id1', $user->id)
                            ->orWhere('users.additional_recruiter_id2', $user->id);
                    })->where(function ($query) use ($search) {
                        $query->where('users.first_name', 'LIKE', '%'.$search.'%')
                            ->orWhere('users.last_name', 'LIKE', '%'.$search.'%')
                            ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$search.'%']);
                    })->where('users.dismiss', 0)->get();

                foreach ($directs as $direct) {
                    $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $direct->id)->where('recruiter_id', $user->id)->where('type', 'Direct')->where('effective_date', '<=', date('Y-m-d'));
                    if ($productId) {
                        $overrideStatus->where('product_id', $productId);
                    }
                    $overrideStatus = $overrideStatus->orderBy('effective_date', 'DESC')->first();
                    $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $direct->id)->where('recruiter_id', $user->id)->where('type', 'Direct');
                    if ($productId) {
                        $lastOverrideStatus->where('product_id', $productId);
                    }
                    $lastOverrideStatus = $lastOverrideStatus->orderBy('effective_date', 'DESC')->first();
                    $overridesTotal = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $direct->id, 'type' => 'Direct'])
                        ->when($productId, function ($q) use ($productId) {
                            $q->where('product_id', $productId);
                        })->sum('amount') ?? 0;
                    $overridesPid = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $direct->id, 'type' => 'Direct'])
                        ->when($productId, function ($q) use ($productId) {
                            $q->where('product_id', $productId);
                        })->pluck('pid');
                    $saleData = SalesMaster::selectRaw('count(id) as count, sum(kw) as kw')->whereIn('pid', $overridesPid)->first();
                    $overrideCount = $saleData->count ?? 0;
                    $kwTotal = $saleData->kw ?? 0;

                    $s3Image = null;
                    if (isset($direct->image) && ! empty($direct->image)) {
                        $s3Image = s3_getTempUrl(config('app.domain_name').'/'.$direct->image);
                    }

                    $organization = UserOrganizationHistory::with('position', 'subPositionId')->where('user_id', $direct->id);
                    if ($productId) {
                        $organization->where('product_id', $productId);
                    }
                    $organization = $organization->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                    if (! $organization) {
                        $organization = UserOrganizationHistory::with('position', 'subPositionId')->where('user_id', $direct->id);
                        if ($productId) {
                            $organization->where('product_id', $productId);
                        }
                        $organization = $organization->where('effective_date', '>=', $date)->orderBy('effective_date', 'DESC')->first();
                    }
                    $directOverrides[] = [
                        'id' => $direct->id,
                        'recruiter_id' => $user->id,
                        'recruiter_name' => isset($user->first_name) ? $user->first_name.' '.$user->last_name : null,
                        'position_id' => isset($organization->position_id) ? $organization->position_id : null,
                        'position' => isset($organization->position->position_name) ? $organization->position->position_name : null,
                        'sub_position_id' => isset($organization->sub_position_id) ? $organization->sub_position_id : null,
                        'sub_position_name' => isset($organization->subPositionId->position_name) ? $organization->subPositionId->position_name : null,
                        'first_name' => $direct->first_name,
                        'last_name' => $direct->last_name,
                        'status' => isset($overrideStatus->status) ? $overrideStatus->status : 0,
                        'override' => $userOverride->direct_overrides_amount,
                        'override_type' => $userOverride->direct_overrides_type,
                        'override_custom_sales_field_id' => $userOverride->direct_custom_sales_field_id ?? null,
                        'totalOverrides' => $overridesTotal,
                        'account' => $overrideCount,
                        'kwInstalled' => $kwTotal,
                        'image' => $direct->image,
                        'image_s3' => $s3Image,
                        'is_super_admin' => isset($direct->is_super_admin) ? $direct->is_super_admin : null,
                        'is_manager' => isset($direct->is_manager) ? $direct->is_manager : null,
                        'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                        'product_id' => $productId,
                    ];
                    // DIRECT OVERRIDES END

                    // IN-DIRECT OVERRIDES START
                    if ($userOverride->indirect_overrides_amount || $userOverride->indirect_overrides_type) {
                        $inDirects = User::select('id', 'position_id', 'recruiter_id', 'sub_position_id', 'first_name', 'last_name', 'image', 'is_super_admin', 'is_manager')
                            ->where(function ($query) use ($direct) {
                                $query->where('recruiter_id', $direct->id)
                                    ->orWhere('additional_recruiter_id1', $direct->id)
                                    ->orWhere('additional_recruiter_id2', $direct->id);
                            })->where(function ($query) use ($search) {
                                $query->where('first_name', 'LIKE', '%'.$search.'%')
                                    ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                                    ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search.'%']);
                            })->where('dismiss', 0)->get();

                        foreach ($inDirects as $inDirect) {
                            $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $inDirect->id)->where('recruiter_id', $user->id)->where('type', 'Indirect')->where('effective_date', '<=', date('Y-m-d'));
                            if ($productId) {
                                $overrideStatus->where('product_id', $productId);
                            }
                            $overrideStatus = $overrideStatus->orderBy('effective_date', 'DESC')->first();
                            $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $inDirect->id)->where('recruiter_id', $user->id)->where('type', 'Indirect');
                            if ($productId) {
                                $lastOverrideStatus->where('product_id', $productId);
                            }
                            $lastOverrideStatus = $lastOverrideStatus->orderBy('effective_date', 'DESC')->first();
                            $overridesTotal = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $inDirect->id, 'type' => 'Indirect'])
                                ->when($productId, function ($q) use ($productId) {
                                    $q->where('product_id', $productId);
                                })->sum('amount') ?? 0;
                            $overridesPid = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $inDirect->id, 'type' => 'Indirect'])
                                ->when($productId, function ($q) use ($productId) {
                                    $q->where('product_id', $productId);
                                })->pluck('pid');
                            $saleData = SalesMaster::selectRaw('count(id) as count, sum(kw) as kw')->whereIn('pid', $overridesPid)->first();
                            $overrideCount = $saleData->count ?? 0;
                            $kwTotal = $saleData->kw ?? 0;

                            $s3Image = null;
                            if (isset($inDirect->image) && ! empty($inDirect->image)) {
                                $s3Image = s3_getTempUrl(config('app.domain_name').'/'.$inDirect->image);
                            }

                            $organization = UserOrganizationHistory::with('position', 'subPositionId')->where('user_id', $inDirect->id);
                            if ($productId) {
                                $organization->where('product_id', $productId);
                            }
                            $organization = $organization->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                            if (! $organization) {
                                $organization = UserOrganizationHistory::with('position', 'subPositionId')->where('user_id', $inDirect->id);
                                if ($productId) {
                                    $organization->where('product_id', $productId);
                                }
                                $organization = $organization->where('effective_date', '>=', $date)->orderBy('effective_date', 'DESC')->first();
                            }
                            $directOverrides[] = [
                                'id' => $inDirect->id,
                                'recruiter_id' => $user->id,
                                'recruiter_name' => isset($user->first_name) ? $user->first_name.' '.$user->last_name : null,
                                'position' => isset($organization->position->position_name) ? $organization->position->position_name : null,
                                'sub_position_id' => isset($organization->sub_position_id) ? $organization->sub_position_id : null,
                                'sub_position_name' => isset($organization->subPositionId->position_name) ? $organization->subPositionId->position_name : null,
                                'first_name' => $inDirect->first_name,
                                'last_name' => $inDirect->last_name,
                                'status' => isset($overrideStatus->status) ? $overrideStatus->status : 0,
                                'override' => $userOverride->direct_overrides_amount,
                                'override_type' => $userOverride->direct_overrides_type,
                                'override_custom_sales_field_id' => $userOverride->indirect_custom_sales_field_id ?? null,
                                'totalOverrides' => $overridesTotal,
                                'account' => $overrideCount,
                                'kwInstalled' => $kwTotal,
                                'image' => $inDirect->image,
                                'image_s3' => $s3Image,
                                'position_id' => isset($organization->position_id) ? $organization->position_id : null,
                                'is_super_admin' => isset($inDirect->is_super_admin) ? $inDirect->is_super_admin : null,
                                'is_manager' => isset($inDirect->is_manager) ? $inDirect->is_manager : null,
                                'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                                'product_id' => $productId,
                            ];
                        }
                        // IN-DIRECT OVERRIDES END
                    }
                }
            }

            // OFFICE OVERRIDES START
            $officeId = $user->office_id;
            $userTransferHistory = UserTransferHistory::where('user_id', $user->id)->where('transfer_effective_date', '<=', $date)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
            if ($userTransferHistory) {
                $officeId = $userTransferHistory->office_id;
            }
            $userIdArr1 = [$officeId];
            $officeOverridesAmount = isset($userOverride->office_overrides_amount) ? $userOverride->office_overrides_amount : '0';
            $officeOverridesType = isset($userOverride->office_overrides_type) ? $userOverride->office_overrides_type : 'per sale';
            if ($officeOverridesAmount || $officeOverridesType) {
                foreach ($userIdArr1 as $officeId) {
                    $offices = User::select('id', 'position_id', 'recruiter_id', 'sub_position_id', 'first_name', 'last_name', 'image', 'is_super_admin', 'is_manager')
                        ->where('office_id', $officeId)->where(function ($query) use ($search) {
                            $query->where('users.first_name', 'LIKE', '%'.$search.'%')
                                ->orWhere('users.last_name', 'LIKE', '%'.$search.'%')
                                ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$search.'%']);
                        })->where('users.dismiss', 0)->get();

                    foreach ($offices as $office) {
                        $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $office->id)->where('recruiter_id', $user->id)->where('type', 'Office')->where('effective_date', '<=', date('Y-m-d'));
                        if ($productId) {
                            $overrideStatus->where('product_id', $productId);
                        }
                        $overrideStatus = $overrideStatus->orderBy('effective_date', 'DESC')->first();
                        $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $office->id)->where('recruiter_id', $user->id)->where('type', 'Office');
                        if ($productId) {
                            $lastOverrideStatus->where('product_id', $productId);
                        }
                        $lastOverrideStatus = $lastOverrideStatus->orderBy('effective_date', 'DESC')->first();
                        $overridesTotal = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $office->id, 'type' => 'Office'])
                            ->when($productId, function ($q) use ($productId) {
                                $q->where('product_id', $productId);
                            })->sum('amount') ?? 0;
                        $overridesPid = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $office->id, 'type' => 'Office'])
                            ->when($productId, function ($q) use ($productId) {
                                $q->where('product_id', $productId);
                            })->pluck('pid');
                        $saleData = SalesMaster::selectRaw('count(id) as count, sum(kw) as kw')->whereIn('pid', $overridesPid)->first();
                        $overrideCount = $saleData->count ?? 0;
                        $kwTotal = $saleData->kw ?? 0;
                        $s3Image = null;
                        if (isset($office->image) && ! empty($office->image)) {
                            $s3Image = s3_getTempUrl(config('app.domain_name').'/'.$office->image);
                        }

                        $organization = UserOrganizationHistory::with('position', 'subPositionId')->where('user_id', $office->id);
                        if ($productId) {
                            $organization->where('product_id', $productId);
                        }
                        $organization = $organization->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                        if (! $organization) {
                            $organization = UserOrganizationHistory::with('position', 'subPositionId')->where('user_id', $office->id);
                            if ($productId) {
                                $organization->where('product_id', $productId);
                            }
                            $organization = $organization->where('effective_date', '>=', $date)->orderBy('effective_date', 'DESC')->first();
                        }
                        $officeOverrides[] = [
                            'id' => $office->id,
                            'recruiter_id' => null,
                            'recruiter_name' => null,
                            'position' => isset($organization->position->position_name) ? $organization->position->position_name : null,
                            'sub_position_id' => isset($organization->sub_position_id) ? $organization->sub_position_id : null,
                            'sub_position_name' => isset($organization->subPositionId->position_name) ? $organization->subPositionId->position_name : null,
                            'first_name' => $office->first_name,
                            'last_name' => $office->last_name,
                            'status' => isset($overrideStatus->status) ? $overrideStatus->status : 0,
                            'override' => $officeOverridesAmount,
                            'override_type' => $officeOverridesType,
                            'override_custom_sales_field_id' => $userOverride->office_custom_sales_field_id ?? null,
                            'totalOverrides' => $overridesTotal,
                            'account' => $overrideCount,
                            'kwInstalled' => $kwTotal,
                            'image' => $office->image,
                            'image_s3' => $s3Image,
                            'position_id' => isset($organization->position_id) ? $organization->position_id : null,
                            'is_super_admin' => isset($office->is_super_admin) ? $office->is_super_admin : null,
                            'is_manager' => isset($office->is_manager) ? $office->is_manager : null,
                            'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                            'product_id' => $productId,
                        ];
                    }
                }

                $userIdArr2 = AdditionalLocations::where('user_id', $user->id)->pluck('office_id')->toArray();
                foreach ($userIdArr2 as $officeId) {
                    $additionalOffices = User::select('id', 'position_id', 'recruiter_id', 'sub_position_id', 'first_name', 'last_name', 'image', 'is_super_admin', 'is_manager')
                        ->where('office_id', $officeId)->where(function ($query) use ($search) {
                            $query->where('users.first_name', 'LIKE', '%'.$search.'%')
                                ->orWhere('users.last_name', 'LIKE', '%'.$search.'%')
                                ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$search.'%']);
                        })->where('users.dismiss', 0)->get();

                    $userAdditionalOverride = UserAdditionalOfficeOverrideHistory::where(['user_id' => $id, 'office_id' => $officeId])->where('override_effective_date', '<=', $date)->orderBy('override_effective_date', 'DESC')->first();
                    $officeOverridesAmount = isset($userAdditionalOverride->office_overrides_amount) ? $userAdditionalOverride->office_overrides_amount : '0';
                    $officeOverridesType = isset($userAdditionalOverride->office_overrides_type) ? $userAdditionalOverride->office_overrides_type : 'per sale';
                    foreach ($additionalOffices as $additionalOffice) {
                        $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $additionalOffice->id)->where('recruiter_id', $user->id)->where('type', 'Office')->where('effective_date', '<=', date('Y-m-d'));
                        if ($productId) {
                            $overrideStatus->where('product_id', $productId);
                        }
                        $overrideStatus = $overrideStatus->orderBy('effective_date', 'DESC')->first();
                        $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $additionalOffice->id)->where('recruiter_id', $user->id)->where('type', 'Office');
                        if ($productId) {
                            $lastOverrideStatus->where('product_id', $productId);
                        }
                        $lastOverrideStatus = $lastOverrideStatus->orderBy('effective_date', 'DESC')->first();
                        $overridesTotal = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $additionalOffice->id, 'type' => 'Office'])
                            ->when($productId, function ($q) use ($productId) {
                                $q->where('product_id', $productId);
                            })->sum('amount') ?? 0;
                        $overridesPid = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $additionalOffice->id, 'type' => 'Office'])
                            ->when($productId, function ($q) use ($productId) {
                                $q->where('product_id', $productId);
                            })->pluck('pid');
                        $saleData = SalesMaster::selectRaw('count(id) as count, sum(kw) as kw')->whereIn('pid', $overridesPid)->first();
                        $overrideCount = $saleData->count ?? 0;
                        $kwTotal = $saleData->kw ?? 0;
                        $s3Image = null;
                        if (isset($additionalOffice->image) && ! empty($additionalOffice->image)) {
                            $s3Image = s3_getTempUrl(config('app.domain_name').'/'.$additionalOffice->image);
                        }

                        $organization = UserOrganizationHistory::with('position', 'subPositionId')->where('user_id', $additionalOffice->id);
                        if ($productId) {
                            $organization->where('product_id', $productId);
                        }
                        $organization = $organization->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                        if (! $organization) {
                            $organization = UserOrganizationHistory::with('position', 'subPositionId')->where('user_id', $additionalOffice->id);
                            if ($productId) {
                                $organization->where('product_id', $productId);
                            }
                            $organization = $organization->where('effective_date', '>=', $date)->orderBy('effective_date', 'DESC')->first();
                        }
                        $officeOverrides[] = [
                            'id' => $additionalOffice->id,
                            'recruiter_id' => null,
                            'recruiter_name' => null,
                            'position' => isset($organization->position->position_name) ? $organization->position->position_name : null,
                            'sub_position_id' => isset($organization->sub_position_id) ? $organization->sub_position_id : null,
                            'sub_position_name' => isset($organization->subPositionId->position_name) ? $organization->subPositionId->position_name : null,
                            'first_name' => $additionalOffice->first_name,
                            'last_name' => $additionalOffice->last_name,
                            'status' => isset($overrideStatus->status) ? $overrideStatus->status : 0,
                            'override' => $officeOverridesAmount,
                            'override_type' => $officeOverridesType,
                            'override_custom_sales_field_id' => $userAdditionalOverride->custom_sales_field_id ?? null,
                            'totalOverrides' => $overridesTotal,
                            'account' => $overrideCount,
                            'kwInstalled' => $kwTotal,
                            'image' => $additionalOffice->image,
                            'image_s3' => $s3Image,
                            'position_id' => isset($organization->position_id) ? $organization->position_id : null,
                            'is_super_admin' => isset($additionalOffice->is_super_admin) ? $additionalOffice->is_super_admin : null,
                            'is_manager' => isset($additionalOffice->is_manager) ? $additionalOffice->is_manager : null,
                            'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                            'product_id' => $productId,
                        ];
                    }
                }
            }
            // OFFICE OVERRIDES END

            // STACK OVERRIDES START
            $stackSystemSetting = overrideSystemSetting::where('allow_office_stack_override_status', 1)->first();
            $userStack = $userOverride->office_stack_overrides_amount;
            if ($userStack !== null && $stackSystemSetting) {
                $officeId = $user->office_id;
                $userTransferHistory = UserTransferHistory::where('user_id', $user->id)->where('transfer_effective_date', '<=', $date)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
                if ($userTransferHistory) {
                    $officeId = $userTransferHistory->office_id;
                }
                $userIdArr1 = [$officeId];
                $userIdArr2 = AdditionalLocations::where('user_id', $user->id)->pluck('office_id')->toArray();
                $userIdArr = array_unique(array_merge($userIdArr1, $userIdArr2));

                foreach ($userIdArr as $officeId) {
                    $stacks = User::select('id', 'position_id', 'recruiter_id', 'sub_position_id', 'first_name', 'last_name', 'image', 'is_super_admin', 'is_manager')
                        ->where('office_id', $officeId)->where(function ($query) use ($search) {
                            $query->where('users.first_name', 'LIKE', '%'.$search.'%')
                                ->orWhere('users.last_name', 'LIKE', '%'.$search.'%')
                                ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$search.'%']);
                        })->where('users.dismiss', 0)->get();

                    foreach ($stacks as $stack) {
                        $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $stack->id)->where('recruiter_id', $user->id)->where('type', 'Stack')->where('effective_date', '<=', date('Y-m-d'));
                        if ($productId) {
                            $overrideStatus->where('product_id', $productId);
                        }
                        $overrideStatus = $overrideStatus->orderBy('effective_date', 'DESC')->first();
                        $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $stack->id)->where('recruiter_id', $user->id)->where('type', 'Stack');
                        if ($productId) {
                            $lastOverrideStatus->where('product_id', $productId);
                        }
                        $lastOverrideStatus = $lastOverrideStatus->orderBy('effective_date', 'DESC')->first();
                        $overridesTotal = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $stack->id, 'type' => 'Stack'])
                            ->when($productId, function ($q) use ($productId) {
                                $q->where('product_id', $productId);
                            })->sum('amount') ?? 0;
                        $overridesPid = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $stack->id, 'type' => 'Stack'])
                            ->when($productId, function ($q) use ($productId) {
                                $q->where('product_id', $productId);
                            })->pluck('pid');
                        $saleData = SalesMaster::selectRaw('count(id) as count, sum(kw) as kw')->whereIn('pid', $overridesPid)->first();
                        $overrideCount = $saleData->count ?? 0;
                        $kwTotal = $saleData->kw ?? 0;
                        $s3Image = null;
                        if (isset($stack->image) && ! empty($stack->image)) {
                            $s3Image = s3_getTempUrl(config('app.domain_name').'/'.$stack->image);
                        }

                        $organization = UserOrganizationHistory::with('position', 'subPositionId')->where('user_id', $stack->id);
                        if ($productId) {
                            $organization->where('product_id', $productId);
                        }
                        $organization = $organization->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                        if (! $organization) {
                            $organization = UserOrganizationHistory::with('position', 'subPositionId')->where('user_id', $stack->id);
                            if ($productId) {
                                $organization->where('product_id', $productId);
                            }
                            $organization = $organization->where('effective_date', '>=', $date)->orderBy('effective_date', 'DESC')->first();
                        }
                        $stackOverrides[] = [
                            'id' => $stack->id,
                            'recruiter_id' => null,
                            'recruiter_name' => null,
                            'position' => isset($organization->position->position_name) ? $organization->position->position_name : null,
                            'sub_position_id' => isset($organization->sub_position_id) ? $organization->sub_position_id : null,
                            'sub_position_name' => isset($organization->subPositionId->position_name) ? $organization->subPositionId->position_name : null,
                            'first_name' => $stack->first_name,
                            'last_name' => $stack->last_name,
                            'status' => isset($overrideStatus->status) ? $overrideStatus->status : 0,
                            'override' => $userStack,
                            'override_type' => 'percent',
                            'totalOverrides' => $overridesTotal,
                            'account' => $overrideCount,
                            'kwInstalled' => $kwTotal,
                            'image' => $stack->image,
                            'image_s3' => $s3Image,
                            'position_id' => isset($organization->position_id) ? $organization->position_id : null,
                            'is_super_admin' => isset($stack->is_super_admin) ? $stack->is_super_admin : null,
                            'is_manager' => isset($stack->is_manager) ? $stack->is_manager : null,
                            'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                            'product_id' => $productId,
                        ];
                    }
                }
            }
            // STACK OVERRIDES END
        }

        // MANUAL OVERRIDES START
        if (overrideSystemSetting::where('allow_manual_override_status', 1)->first()) {
            $manualData = ManualOverrides::where('user_id', $user->id);
            if ($productId) {
                $manualData->where('product_id', $productId);
            }
            $manualData = $manualData->with('ManualOverridesHistory')->get();
            foreach ($manualData as $manual) {
                $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where('user_id', $manual->manual_user_id)->where('recruiter_id', $id)->where('type', 'Manual');
                if ($productId) {
                    $lastOverrideStatus->where('product_id', $productId);
                }
                $lastOverrideStatus = $lastOverrideStatus->orderBy('effective_date', 'DESC')->first();
                $overrideStatus = OverrideStatus::where('user_id', $manual->manual_user_id)->where('recruiter_id', $id)->where('type', 'Manual')->whereNotNull('effective_date')->where('effective_date', '<=', date('Y-m-d'));
                if ($productId) {
                    $overrideStatus->where('product_id', $productId);
                }
                $overrideStatus = $overrideStatus->orderBy('effective_date', 'DESC')->first();
                $manualUser = User::select('id', 'recruiter_id', 'first_name', 'last_name', 'image', 'position_id', 'sub_position_id', 'office_overrides_amount', 'office_overrides_type', 'is_super_admin', 'is_manager')->with('positionDetail', 'recruiter', 'override_status', 'subpositionDetail')
                    ->where('id', $manual->manual_user_id)
                    ->where(function ($query) use ($search) {
                        $query->where('first_name', 'LIKE', '%'.$search.'%')
                            ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                            ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search.'%']);
                    })->first();

                if (! empty($manualUser)) {
                    $totalAccountManual = UserOverrides::where(['user_id' => $user->id, 'sale_user_id' => $manual->manual_user_id])
                        ->when($productId, function ($q) use ($productId) {
                            $q->where('product_id', $productId);
                        })->where('type', 'Manual')->count();

                    $totalOverrideManual = DB::table('user_overrides')
                        ->select('user_id', DB::raw('SUM(user_overrides.amount) AS overridesTotal'), DB::raw('SUM(user_overrides.kw) AS totalKw'))
                        ->where(['user_id' => $user->id, 'sale_user_id' => $manual->manual_user_id, 'type' => 'Manual'])
                        ->when($productId, function ($q) use ($productId) {
                            $q->where('product_id', $productId);
                        })->first();

                    $s3Image = null;
                    if (isset($manualUser->image) && ! empty($manualUser->image)) {
                        $s3Image = s3_getTempUrl(config('app.domain_name').'/'.$manualUser->image);
                    }

                    $organization = UserOrganizationHistory::with('position', 'subPositionId')->where('user_id', $manual->manual_user_id);
                    if ($productId) {
                        $organization->where('product_id', $productId);
                    }
                    $organization = $organization->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                    if (! $organization) {
                        $organization = UserOrganizationHistory::with('position', 'subPositionId')->where('user_id', $manual->manual_user_id);
                        if ($productId) {
                            $organization->where('product_id', $productId);
                        }
                        $organization = $organization->where('effective_date', '>=', $date)->orderBy('effective_date', 'DESC')->first();
                    }
                    $manualOverrides[] = [
                        'manual_overrides_id' => $manual->id,
                        'id' => $manualUser->id,
                        'recruiter_id' => null,
                        'recruiter_name' => null,
                        'manual_user_id' => $manual->manual_user_id,
                        'user_id' => $manual->user_id,
                        'position_id' => isset($organization->position_id) ? $organization->position_id : null,
                        'position' => isset($organization->position->position_name) ? $organization->position->position_name : null,
                        'sub_position_id' => isset($organization->sub_position_id) ? $organization->sub_position_id : null,
                        'sub_position_name' => isset($organization->subPositionId->position_name) ? $organization->subPositionId->position_name : null,
                        'first_name' => $manualUser->first_name,
                        'last_name' => $manualUser->last_name,
                        'status' => isset($overrideStatus) ? $overrideStatus->status : 0,
                        'override' => $manual->overrides_amount,
                        'override_type' => $manual->overrides_type,
                        'totalOverrides' => $totalOverrideManual->overridesTotal,
                        'account' => $totalAccountManual,
                        'kwInstalled' => $totalOverrideManual->totalKw,
                        'overrides_amount' => $manual->overrides_amount,
                        'overrides_type' => $manual->overrides_type,
                        'effective_date' => $manual->effective_date,
                        'image' => $manualUser->image,
                        'image_s3' => $s3Image,
                        'history' => $manual->ManualOverridesHistory,
                        'is_super_admin' => isset($manualUser->is_super_admin) ? $manualUser->is_super_admin : null,
                        'is_manager' => isset($manualUser->is_manager) ? $manualUser->is_manager : null,
                        'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                        'product_id' => $productId,
                    ];
                }
            }
        }
        // MANUAL OVERRIDES END

        $s3Image = null;
        if (isset($user->image) && ! empty($user->image)) {
            $s3Image = s3_getTempUrl(config('app.domain_name').'/'.$user['image']);
        }
        $data['id'] = $user['id'];
        $data['first_name'] = $user['first_name'];
        $data['last_name'] = $user['last_name'];
        $data['image'] = $user['image'];
        $data['image_s3'] = $s3Image;
        $data['totalDirects'] = count($directOverrides);
        $data['totalIndirect'] = count($inDirectOverrides);
        $data['totalOffice'] = count($officeOverrides);
        $data['totalmanual'] = count($manualOverrides);
        $data['totalStack'] = count($stackOverrides);
        $data['direct'] = $directOverrides;
        $data['indirect'] = $inDirectOverrides;
        $data['office'] = $officeOverrides;
        $data['manual'] = $manualOverrides;
        $data['stack'] = $stackOverrides;

        return response()->json([
            'ApiName' => 'my_overrides',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ]);
    }

    public function manual_overrides_from(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'product_id' => 'required|array|min:1',
            'overrides_amount' => 'required',
            'overrides_type' => 'required',
            'manual_user_id' => 'required|array|min:1',
            'effective_date' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $data = [];
        $userId = $request->user_id;
        $manualUserId = $request->manual_user_id;
        $overridesAmount = $request->overrides_amount;
        $overridesType = $request->overrides_type;
        $effectiveDate = isset($request->effective_date) ? $request->effective_date : null;
        $productIds = $request->product_id;
        foreach ($manualUserId as $value) {
            foreach ($productIds as $productId) {
                $manualOverride = ManualOverrides::updateOrCreate(['manual_user_id' => $value, 'user_id' => $userId, 'product_id' => $productId], [
                    'overrides_amount' => $overridesAmount,
                    'overrides_type' => $overridesType,
                    'effective_date' => $effectiveDate,
                ]);

                ManualOverridesHistory::updateOrCreate(['manual_user_id' => $value, 'user_id' => $userId, 'product_id' => $productId], [
                    'updated_by' => Auth()->user()->id,
                    'manual_overrides_id' => $manualOverride->id,
                    'old_overrides_amount' => 0.0,
                    'overrides_amount' => $overridesAmount,
                    'overrides_type' => $overridesType,
                    'effective_date' => $effectiveDate,
                ]);

                OverrideStatus::updateOrCreate(['user_id' => $value, 'recruiter_id' => $userId, 'product_id' => $productId, 'type' => 'Manual'], [
                    'status' => 0,
                    'type' => 'Manual',
                    'effective_date' => $effectiveDate,
                    'updated_by' => Auth::user()->id,
                ]);
            }
        }

        return response()->json([
            'ApiName' => 'manual_overrides',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ]);
    }

    public function mysale_overrides(Request $request, $id): JsonResponse
    {
        $productId = (int) $request->input('prodcutId') ? (int) $request->input('prodcutId') : null;
        $search = $request->input('search');
        $user = User::where('id', $id)->where('dismiss', 0)->first();
        if (! $user) {
            return response()->json([
                'ApiName' => 'my_overrides',
                'status' => false,
                'message' => 'User not found!!',
                'data' => [],
            ], 400);
        }

        $overrideCheck = CompanySetting::where(['type' => 'overrides', 'status' => '1'])->first();
        if (! $overrideCheck) {
            return response()->json([
                'ApiName' => 'my_overrides',
                'status' => false,
                'message' => 'Overrides are disabled!!',
                'data' => [],
            ], 400);
        }

        $office = [];
        $direct = [];
        $indirect = [];
        $manual = [];
        $stack = [];
        $userId = $user->id;
        $date = date('Y-m-d');

        $officeId = $user->office_id;
        $userTransferHistory = UserTransferHistory::where('user_id', $userId)->where('transfer_effective_date', '<=', $date)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
        if ($userTransferHistory) {
            $officeId = $userTransferHistory->office_id;
        }
        if ($officeId) {
            $subQuery = UserTransferHistory::select(
                'id',
                'user_id',
                'transfer_effective_date',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY transfer_effective_date DESC, id DESC) as rn')
            )->where('transfer_effective_date', '<=', $date);

            $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                ->mergeBindings($subQuery->getQuery())
                ->select('id')
                ->where('rn', 1);

            $userIdArr = UserTransferHistory::whereIn('id', $results->pluck('id'))->whereNotNull('office_id')->where('office_id', $officeId)->pluck('user_id')->toArray();
            $userIdArr1 = User::select('id', 'recruiter_id', 'position_id', 'sub_position_id', 'first_name', 'last_name', 'image')
                ->with(['recruiter', 'positionDetail', 'subpositionDetail'])
                ->where(function ($query) use ($search) {
                    $query->where('users.first_name', 'LIKE', '%'.$search.'%')
                        ->orWhere('users.last_name', 'LIKE', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$search.'%']);
                })->whereIn('id', $userIdArr)->where(['dismiss' => '0'])->get();
            foreach ($userIdArr1 as $userData) {
                $positionOverride = PositionOverride::where(['position_id' => $userData->sub_position_id, 'product_id' => $productId, 'override_id' => '3'])->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $positionOverride) {
                    $positionOverride = PositionOverride::where(['position_id' => $userData->sub_position_id, 'product_id' => $productId, 'override_id' => '3'])->whereNull('effective_date')->first();
                }
                if ($positionOverride && $positionOverride->status == 1) {
                    $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where(['user_id' => $userId, 'recruiter_id' => $userData->id, 'type' => 'Office'])->where('effective_date', '<=', $date);
                    if ($productId) {
                        $overrideStatus->where('product_id', $productId);
                    }
                    $overrideStatus = $overrideStatus->orderBy('effective_date', 'DESC')->first();
                    $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where(['user_id' => $userId, 'recruiter_id' => $userData->id, 'type' => 'Office']);
                    if ($productId) {
                        $lastOverrideStatus->where('product_id', $productId);
                    }
                    $lastOverrideStatus = $lastOverrideStatus->orderBy('effective_date', 'DESC')->first();

                    $overridesTotal = UserOverrides::where(['user_id' => $userData->id, 'sale_user_id' => $userId, 'type' => 'Office'])
                        ->when($productId, function ($q) use ($productId) {
                            $q->where('product_id', $productId);
                        })->sum('amount') ?? 0;
                    $overridesPid = UserOverrides::where(['user_id' => $userData->id, 'sale_user_id' => $userId, 'type' => 'Office'])
                        ->when($productId, function ($q) use ($productId) {
                            $q->where('product_id', $productId);
                        })->pluck('pid');
                    $saleData = SalesMaster::selectRaw('count(id) as count, sum(kw) as kw')->whereIn('pid', $overridesPid)->first();
                    $overrideCount = $saleData->count ?? 0;
                    $kwTotal = $saleData->kw ?? 0;

                    $officeOverridesAmount = '0';
                    $officeOverridesType = 'per sale';
                    $overrideHistory = UserOverrideHistory::where(['user_id' => $userData->id, 'product_id' => $productId])->where('override_effective_date', '<=', $date)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ($overrideHistory) {
                        $officeOverridesAmount = isset($overrideHistory->office_overrides_amount) ? $overrideHistory->office_overrides_amount : '0';
                        $officeOverridesType = isset($overrideHistory->office_overrides_type) ? $overrideHistory->office_overrides_type : 'per sale';
                    }

                    $s3Image = null;
                    if (isset($userData->image) && ! empty($userData->image)) {
                        $s3Image = s3_getTempUrl(config('app.domain_name').'/'.$userData->image);
                    }
                    $office[] = [
                        'id' => $userData->id,
                        'recruiter_id' => $userData->recruiter_id,
                        'recruiter_name' => isset($userData->recruiter->first_name) ? $userData->recruiter->first_name : null,
                        'position_id' => isset($userData->position_id) ? $userData->position_id : null,
                        'position' => isset($userData->positionDetail->position_name) ? $userData->positionDetail->position_name : null,
                        'sub_position_id' => isset($userData->sub_position_id) ? $userData->sub_position_id : null,
                        'sub_position_name' => isset($userData->subpositionDetail->position_name) ? $userData->subpositionDetail->position_name : null,
                        'first_name' => $userData->first_name,
                        'last_name' => $userData->last_name,
                        'status' => isset($overrideStatus) ? $overrideStatus->status : 0,
                        'override' => $officeOverridesAmount,
                        'override_type' => $officeOverridesType,
                        'totalOverrides' => $overridesTotal,
                        'account' => $overrideCount,
                        'kwInstalled' => $kwTotal,
                        'image' => $userData->image,
                        'image_s3' => $s3Image,
                        'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                        'product_id' => $productId,
                    ];
                }
            }

            $userIdArr2 = AdditionalLocations::whereHas('user', function ($q) use ($search) {
                $q->where('dismiss', '0')->where(function ($query) use ($search) {
                    $query->where('users.first_name', 'LIKE', '%'.$search.'%')
                        ->orWhere('users.last_name', 'LIKE', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$search.'%']);
                });
            })->with('user:id,recruiter_id,position_id,sub_position_id,first_name,last_name,image')
                ->with(['user.recruiter', 'user.positionDetail', 'user.subpositionDetail'])->where(['office_id' => $officeId])->get();
            foreach ($userIdArr2 as $additionalData) {
                $userData = $additionalData->user;
                $positionOverride = PositionOverride::where(['position_id' => $userData->sub_position_id, 'product_id' => $productId, 'override_id' => '3'])->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $positionOverride) {
                    $positionOverride = PositionOverride::where(['position_id' => $userData->sub_position_id, 'product_id' => $productId, 'override_id' => '3'])->whereNull('effective_date')->first();
                }
                if ($positionOverride && $positionOverride->status == 1) {
                    $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where(['user_id' => $userId, 'recruiter_id' => $userData->id, 'type' => 'Office'])->where('effective_date', '<=', $date);
                    if ($productId) {
                        $overrideStatus->where('product_id', $productId);
                    }
                    $overrideStatus = $overrideStatus->orderBy('effective_date', 'DESC')->first();
                    $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where(['user_id' => $userId, 'recruiter_id' => $userData->id, 'type' => 'Office']);
                    if ($productId) {
                        $lastOverrideStatus->where('product_id', $productId);
                    }
                    $lastOverrideStatus = $lastOverrideStatus->orderBy('effective_date', 'DESC')->first();

                    $overridesTotal = UserOverrides::where(['user_id' => $userData->id, 'sale_user_id' => $userId, 'type' => 'Office'])
                        ->when($productId, function ($q) use ($productId) {
                            $q->where('product_id', $productId);
                        })->sum('amount') ?? 0;
                    $overridesPid = UserOverrides::where(['user_id' => $userData->id, 'sale_user_id' => $userId, 'type' => 'Office'])
                        ->when($productId, function ($q) use ($productId) {
                            $q->where('product_id', $productId);
                        })->pluck('pid');
                    $saleData = SalesMaster::selectRaw('count(id) as count, sum(kw) as kw')->whereIn('pid', $overridesPid)->first();
                    $overrideCount = $saleData->count ?? 0;
                    $kwTotal = $saleData->kw ?? 0;

                    $officeOverridesAmount = '0';
                    $officeOverridesType = 'per sale';
                    $overrideHistory = UserAdditionalOfficeOverrideHistory::where(['user_id' => $userData->id, 'product_id' => $productId, 'office_id' => $additionalData->office_id])->where('override_effective_date', '<=', $date)->orderBy('id', 'DESC')->first();
                    if ($overrideHistory) {
                        $officeOverridesAmount = $overrideHistory->office_overrides_amount;
                        $officeOverridesType = $overrideHistory->office_overrides_type;
                    }

                    $office[] = [
                        'id' => $userData->id,
                        'recruiter_id' => $userData->recruiter_id,
                        'recruiter_name' => isset($userData->recruiter->first_name) ? $userData->recruiter->first_name : null,
                        'position_id' => isset($userData->position_id) ? $userData->position_id : null,
                        'position' => isset($userData->positionDetail->position_name) ? $userData->positionDetail->position_name : null,
                        'sub_position_id' => isset($userData->sub_position_id) ? $userData->sub_position_id : null,
                        'sub_position_name' => isset($userData->subpositionDetail->position_name) ? $userData->subpositionDetail->position_name : null,
                        'first_name' => $userData->first_name,
                        'last_name' => $userData->last_name,
                        'status' => isset($overrideStatus) ? $overrideStatus->status : 0,
                        'override' => $officeOverridesAmount,
                        'override_type' => $officeOverridesType,
                        'totalOverrides' => $overridesTotal,
                        'account' => $overrideCount,
                        'kwInstalled' => $kwTotal,
                        'image' => $userData->image,
                        'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                        'product_id' => $productId,
                    ];
                }
            }
        }

        // DIRECT & INDIRECT OVERRIDES CODE
        if ($user && $user->recruiter_id) {
            $recruiterIds = $user->recruiter_id;
            if (! empty($user->additional_recruiter_id1)) {
                $recruiterIds .= ','.$user->additional_recruiter_id1;
            }
            if (! empty($user->additional_recruiter_id2)) {
                $recruiterIds .= ','.$user->additional_recruiter_id2;
            }

            $idsArr = explode(',', $recruiterIds);
            $directs = User::whereIn('id', $idsArr)
                ->where(function ($query) use ($search) {
                    $query->where('users.first_name', 'LIKE', '%'.$search.'%')
                        ->orWhere('users.last_name', 'LIKE', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$search.'%']);
                })->where('dismiss', 0)->get();
            foreach ($directs as $value) {
                $positionOverride = PositionOverride::where(['position_id' => $value->sub_position_id, 'product_id' => $productId, 'override_id' => '1'])->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $positionOverride) {
                    $positionOverride = PositionOverride::where(['position_id' => $value->sub_position_id, 'product_id' => $productId, 'override_id' => '1'])->whereNull('effective_date')->first();
                }
                if ($positionOverride && $positionOverride->status == 1) {
                    $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where(['user_id' => $userId, 'recruiter_id' => $value->id, 'type' => 'Direct'])->where('effective_date', '<=', $date);
                    if ($productId) {
                        $overrideStatus->where('product_id', $productId);
                    }
                    $overrideStatus = $overrideStatus->orderBy('effective_date', 'DESC')->first();
                    $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where(['user_id' => $userId, 'recruiter_id' => $value->id, 'type' => 'Direct']);
                    if ($productId) {
                        $lastOverrideStatus->where('product_id', $productId);
                    }
                    $lastOverrideStatus = $lastOverrideStatus->orderBy('effective_date', 'DESC')->first();

                    $overridesTotal = UserOverrides::where(['user_id' => $value->id, 'sale_user_id' => $userId, 'type' => 'Direct'])
                        ->when($productId, function ($q) use ($productId) {
                            $q->where('product_id', $productId);
                        })->sum('amount') ?? 0;
                    $overridesPid = UserOverrides::where(['user_id' => $value->id, 'sale_user_id' => $userId, 'type' => 'Direct'])
                        ->when($productId, function ($q) use ($productId) {
                            $q->where('product_id', $productId);
                        })->pluck('pid');
                    $saleData = SalesMaster::selectRaw('count(id) as count, sum(kw) as kw')->whereIn('pid', $overridesPid)->first();
                    $overrideCount = $saleData->count ?? 0;
                    $kwTotal = $saleData->kw ?? 0;

                    $directOverridesAmount = '0';
                    $directOverridesType = 'per sale';
                    $overrideHistory = UserOverrideHistory::where(['user_id' => $value->id, 'product_id' => $productId])->where('override_effective_date', '<=', $date)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ($overrideHistory) {
                        $directOverridesAmount = isset($overrideHistory->direct_overrides_amount) ? $overrideHistory->direct_overrides_amount : '0';
                        $directOverridesType = isset($overrideHistory->direct_overrides_type) ? $overrideHistory->direct_overrides_type : 'per sale';
                    }

                    $s3Image = null;
                    if (isset($value->image) && ! empty($value->image)) {
                        $s3Image = s3_getTempUrl(config('app.domain_name').'/'.$value->image);
                    }
                    $direct[] = [
                        'id' => $value->id,
                        'recruiter_id' => $value->recruiter_id,
                        'recruiter_name' => isset($value->recruiter->first_name) ? $value->recruiter->first_name : null,
                        'position_id' => isset($value->position_id) ? $value->position_id : null,
                        'position' => isset($value->positionDetail->position_name) ? $value->positionDetail->position_name : null,
                        'sub_position_id' => isset($value->sub_position_id) ? $value->sub_position_id : null,
                        'sub_position_name' => isset($value->subpositionDetail->position_name) ? $value->subpositionDetail->position_name : null,
                        'first_name' => $value->first_name,
                        'last_name' => $value->last_name,
                        'status' => isset($overrideStatus) ? $overrideStatus->status : 0,
                        'override' => $directOverridesAmount,
                        'override_type' => $directOverridesType,
                        'totalOverrides' => $overridesTotal,
                        'account' => $overrideCount,
                        'kwInstalled' => $kwTotal,
                        'image' => $value->image,
                        'image_s3' => $s3Image,
                        'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                        'product_id' => $productId,
                    ];
                }

                // INDIRECT
                if ($value && $value->recruiter_id) {
                    $recruiterIds = $value->recruiter_id;
                    if (! empty($value->additional_recruiter_id1)) {
                        $recruiterIds .= ','.$value->additional_recruiter_id1;
                    }
                    if (! empty($value->additional_recruiter_id2)) {
                        $recruiterIds .= ','.$value->additional_recruiter_id2;
                    }
                    $idsArr = explode(',', $recruiterIds);

                    $additional = User::whereIn('id', $idsArr)
                        ->where(function ($query) use ($search) {
                            $query->where('users.first_name', 'LIKE', '%'.$search.'%')
                                ->orWhere('users.last_name', 'LIKE', '%'.$search.'%')
                                ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$search.'%']);
                        })->where('dismiss', 0)->get();
                    foreach ($additional as $val) {
                        $positionOverride = PositionOverride::where(['position_id' => $val->sub_position_id, 'product_id' => $productId, 'override_id' => '2'])->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if (! $positionOverride) {
                            $positionOverride = PositionOverride::where(['position_id' => $val->sub_position_id, 'product_id' => $productId, 'override_id' => '2'])->whereNull('effective_date')->first();
                        }
                        if ($positionOverride && $positionOverride->status == 1) {
                            $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where(['user_id' => $userId, 'recruiter_id' => $val->id, 'type' => 'Indirect'])->where('effective_date', '<=', $date);
                            if ($productId) {
                                $overrideStatus->where('product_id', $productId);
                            }
                            $overrideStatus = $overrideStatus->orderBy('effective_date', 'DESC')->first();
                            $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where(['user_id' => $userId, 'recruiter_id' => $val->id, 'type' => 'Indirect']);
                            if ($productId) {
                                $lastOverrideStatus->where('product_id', $productId);
                            }
                            $lastOverrideStatus = $lastOverrideStatus->orderBy('effective_date', 'DESC')->first();

                            $overridesTotal = UserOverrides::where(['user_id' => $val->id, 'sale_user_id' => $userId, 'type' => 'Indirect'])
                                ->when($productId, function ($q) use ($productId) {
                                    $q->where('product_id', $productId);
                                })->sum('amount') ?? 0;
                            $overridesPid = UserOverrides::where(['user_id' => $val->id, 'sale_user_id' => $userId, 'type' => 'Indirect'])
                                ->when($productId, function ($q) use ($productId) {
                                    $q->where('product_id', $productId);
                                })->pluck('pid');
                            $saleData = SalesMaster::selectRaw('count(id) as count, sum(kw) as kw')->whereIn('pid', $overridesPid)->first();
                            $overrideCount = $saleData->count ?? 0;
                            $kwTotal = $saleData->kw ?? 0;

                            $inDirectOverridesAmount = '0';
                            $inDirectOverridesType = 'per sale';
                            $overrideHistory = UserOverrideHistory::where(['user_id' => $val->id, 'product_id' => $productId])->where('override_effective_date', '<=', $date)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($overrideHistory) {
                                $inDirectOverridesAmount = isset($overrideHistory->indirect_overrides_amount) ? $overrideHistory->indirect_overrides_amount : '0';
                                $inDirectOverridesType = isset($overrideHistory->indirect_overrides_type) ? $overrideHistory->indirect_overrides_type : 'per sale';
                            }

                            $s3Image = null;
                            if (isset($val->image) && ! empty($val->image)) {
                                $s3Image = s3_getTempUrl(config('app.domain_name').'/'.$val->image);
                            }
                            $indirect[] = [
                                'id' => $val->id,
                                'recruiter_id' => $val->recruiter_id,
                                'recruiter_name' => isset($val->recruiter->first_name) ? $val->recruiter->first_name : null,
                                'position_id' => isset($val->position_id) ? $val->position_id : null,
                                'position' => isset($val->positionDetail->position_name) ? $val->positionDetail->position_name : null,
                                'sub_position_id' => isset($val->sub_position_id) ? $val->sub_position_id : null,
                                'sub_position_name' => isset($val->subpositionDetail->position_name) ? $val->subpositionDetail->position_name : null,
                                'first_name' => $val->first_name,
                                'last_name' => $val->last_name,
                                'status' => isset($overrideStatus) ? $overrideStatus->status : 0,
                                'override' => $inDirectOverridesAmount,
                                'override_type' => $inDirectOverridesType,
                                'totalOverrides' => $overridesTotal,
                                'account' => $overrideCount,
                                'kwInstalled' => $kwTotal,
                                'image' => $val->image,
                                'image_s3' => $s3Image,
                                'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                                'product_id' => $productId,
                            ];
                        }
                    }
                }
            }
        }
        // END DIRECT & INDIRECT OVERRIDES CODE

        if (overrideSystemSetting::where('allow_manual_override_status', 1)->first()) {
            $manualData = ManualOverrides::where('manual_user_id', $userId);
            if ($productId) {
                $manualData->where('product_id', $productId);
            }
            $manualOverrides = $manualData->with('ManualOverridesHistory')->get();
            foreach ($manualOverrides as $manualOverride) {
                $value = User::where(['id' => $manualOverride->user_id, 'dismiss' => '0'])
                    ->where(function ($query) use ($search) {
                        $query->where('users.first_name', 'LIKE', '%'.$search.'%')
                            ->orWhere('users.last_name', 'LIKE', '%'.$search.'%')
                            ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$search.'%']);
                    })->first();
                if ($value) {
                    $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where(['user_id' => $userId, 'recruiter_id' => $value->id, 'type' => 'Manual'])->where('effective_date', '<=', $date);
                    if ($productId) {
                        $overrideStatus->where('product_id', $productId);
                    }
                    $overrideStatus = $overrideStatus->orderBy('effective_date', 'DESC')->first();
                    $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where(['user_id' => $userId, 'recruiter_id' => $value->id, 'type' => 'Manual']);
                    if ($productId) {
                        $lastOverrideStatus->where('product_id', $productId);
                    }
                    $lastOverrideStatus = $lastOverrideStatus->orderBy('effective_date', 'DESC')->first();

                    $overridesTotal = UserOverrides::where(['user_id' => $value->id, 'sale_user_id' => $userId, 'type' => 'Manual'])
                        ->when($productId, function ($q) use ($productId) {
                            $q->where('product_id', $productId);
                        })->sum('amount') ?? 0;
                    $overridesPid = UserOverrides::where(['user_id' => $value->id, 'sale_user_id' => $userId, 'type' => 'Manual'])
                        ->when($productId, function ($q) use ($productId) {
                            $q->where('product_id', $productId);
                        })->pluck('pid');
                    $saleData = SalesMaster::selectRaw('count(id) as count, sum(kw) as kw')->whereIn('pid', $overridesPid)->first();
                    $overrideCount = $saleData->count ?? 0;
                    $kwTotal = $saleData->kw ?? 0;

                    $overrideAmount = 0;
                    $overrideType = 'per sale';
                    $overrideHistory = ManualOverridesHistory::where(['user_id' => $value->id, 'manual_user_id' => $userId, 'product_id' => $productId])
                        ->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->first();
                    if ($overrideHistory) {
                        $overrideAmount = $overrideHistory->overrides_amount;
                        $overrideType = $overrideHistory->overrides_type;
                    }

                    $s3Image = null;
                    if (isset($value->image) && ! empty($value->image)) {
                        $s3Image = s3_getTempUrl(config('app.domain_name').'/'.$value->image);
                    }
                    $manual[] = [
                        'manual_overrides_id' => $manualOverride->id,
                        'id' => $value->id,
                        'recruiter_id' => $value->recruiter_id,
                        'recruiter_name' => isset($value->recruiter->first_name) ? $value->recruiter->first_name : null,
                        'position_id' => isset($value->position_id) ? $value->position_id : null,
                        'position' => isset($value->positionDetail->position_name) ? $value->positionDetail->position_name : null,
                        'sub_position_id' => isset($value->sub_position_id) ? $value->sub_position_id : null,
                        'sub_position_name' => isset($value->subpositionDetail->position_name) ? $value->subpositionDetail->position_name : null,
                        'first_name' => $value->first_name,
                        'last_name' => $value->last_name,
                        'status' => isset($overrideStatus) ? $overrideStatus->status : 0,
                        'override' => $overrideAmount,
                        'override_type' => $overrideType,
                        'totalOverrides' => $overridesTotal,
                        'account' => $overrideCount,
                        'kwInstalled' => $kwTotal,
                        'image' => $value->image,
                        'image_s3' => $s3Image,
                        'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                        'product_id' => $productId,
                    ];
                }
            }
        }

        if ($officeId && overrideSystemSetting::where('allow_office_stack_override_status', 1)->first()) {
            $subQuery = UserTransferHistory::select(
                'id',
                'user_id',
                'transfer_effective_date',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY transfer_effective_date DESC, id DESC) as rn')
            )->where('transfer_effective_date', '<=', $date);

            $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                ->mergeBindings($subQuery->getQuery())
                ->select('id')
                ->where('rn', 1);

            $userIdArr = UserTransferHistory::whereIn('id', $results->pluck('id'))->whereNotNull('office_id')->where('office_id', $officeId)->pluck('user_id')->toArray();
            $userIdArr1 = User::where(['dismiss' => '0'])->whereIn('id', $userIdArr)->pluck('id')->toArray();
            $userIdArr2 = AdditionalLocations::where(['office_id' => $officeId])->whereNotIn('user_id', [$userId])->pluck('user_id')->toArray();
            $userIdArr = array_unique(array_merge($userIdArr1, $userIdArr2));
            $userIdArr = User::select('id', 'recruiter_id', 'position_id', 'sub_position_id', 'first_name', 'last_name', 'image')
                ->with(['recruiter', 'positionDetail', 'subpositionDetail'])
                ->where(function ($query) use ($search) {
                    $query->where('users.first_name', 'LIKE', '%'.$search.'%')
                        ->orWhere('users.last_name', 'LIKE', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$search.'%']);
                })->whereIn('id', $userIdArr)->where(['dismiss' => '0'])->get();
            foreach ($userIdArr as $userData) {
                $positionOverride = PositionOverride::where(['position_id' => $userData->sub_position_id, 'product_id' => $productId, 'override_id' => '4'])->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $positionOverride) {
                    $positionOverride = PositionOverride::where(['position_id' => $userData->sub_position_id, 'product_id' => $productId, 'override_id' => '4'])->whereNull('effective_date')->first();
                }
                if ($positionOverride && $positionOverride->status == 1) {
                    $overrideStatus = OverrideStatus::whereNotNull('effective_date')->where(['user_id' => $userId, 'recruiter_id' => $userData->id, 'type' => 'Stack'])->where('effective_date', '<=', $date);
                    if ($productId) {
                        $overrideStatus->where('product_id', $productId);
                    }
                    $overrideStatus = $overrideStatus->orderBy('effective_date', 'DESC')->first();
                    $lastOverrideStatus = OverrideStatus::whereNotNull('effective_date')->where(['user_id' => $userId, 'recruiter_id' => $userData->id, 'type' => 'Stack']);
                    if ($productId) {
                        $lastOverrideStatus->where('product_id', $productId);
                    }
                    $lastOverrideStatus = $lastOverrideStatus->orderBy('effective_date', 'DESC')->first();

                    $overridesTotal = UserOverrides::where(['user_id' => $userData->id, 'sale_user_id' => $userId, 'type' => 'Stack'])
                        ->when($productId, function ($q) use ($productId) {
                            $q->where('product_id', $productId);
                        })->sum('amount') ?? 0;
                    $overridesPid = UserOverrides::where(['user_id' => $userData->id, 'sale_user_id' => $userId, 'type' => 'Stack'])
                        ->when($productId, function ($q) use ($productId) {
                            $q->where('product_id', $productId);
                        })->pluck('pid');
                    $saleData = SalesMaster::selectRaw('count(id) as count, sum(kw) as kw')->whereIn('pid', $overridesPid)->first();
                    $overrideCount = $saleData->count ?? 0;
                    $kwTotal = $saleData->kw ?? 0;

                    $stackOverridesAmount = '0';
                    $overrideHistory = UserOverrideHistory::where(['user_id' => $userData->id, 'product_id' => $productId])->where('override_effective_date', '<=', $date)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ($overrideHistory) {
                        $stackOverridesAmount = isset($overrideHistory->office_stack_overrides_amount) ? $overrideHistory->office_stack_overrides_amount : '0';
                    }

                    $s3Image = null;
                    if (isset($userData->image) && ! empty($userData->image)) {
                        $s3Image = s3_getTempUrl(config('app.domain_name').'/'.$userData->image);
                    }
                    $stack[] = [
                        'id' => $userData->id,
                        'recruiter_id' => $userData->recruiter_id,
                        'recruiter_name' => isset($userData->recruiter->first_name) ? $userData->recruiter->first_name : null,
                        'position_id' => isset($userData->position_id) ? $userData->position_id : null,
                        'position' => isset($userData->positionDetail->position_name) ? $userData->positionDetail->position_name : null,
                        'sub_position_id' => isset($userData->sub_position_id) ? $userData->sub_position_id : null,
                        'sub_position_name' => isset($userData->subpositionDetail->position_name) ? $userData->subpositionDetail->position_name : null,
                        'first_name' => $userData->first_name,
                        'last_name' => $userData->last_name,
                        'status' => isset($overrideStatus) ? $overrideStatus->status : 0,
                        'override' => $stackOverridesAmount,
                        'override_type' => 'percent',
                        'totalOverrides' => $overridesTotal,
                        'account' => $overrideCount,
                        'kwInstalled' => $kwTotal,
                        'image' => $userData->image,
                        'image_s3' => $s3Image,
                        'last_override_status' => isset($lastOverrideStatus->effective_date) ? $lastOverrideStatus->effective_date : null,
                        'product_id' => $productId,
                    ];
                }
            }
        }

        $data['id'] = $user['id'];
        $data['first_name'] = $user['first_name'];
        $data['last_name'] = $user['last_name'];
        $data['image'] = $user['image'];
        $data['direct'] = $direct;
        $data['indirect'] = $indirect;
        $data['office'] = $office;
        $data['manual'] = $manual;
        $data['stack'] = $stack;

        return response()->json([
            'ApiName' => 'my_overrides',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ]);
    }

    public function manual_overrides(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'product_id' => 'required|array|min:1',
            'overrides_amount' => 'required',
            'overrides_type' => 'required',
            'manual_user_id' => 'required|array|min:1',
            'effective_date' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $data = [];
        $userId = $request->user_id;
        $manualUserId = $request->manual_user_id;
        $overridesAmount = $request->overrides_amount;
        $overridesType = $request->overrides_type;
        $effectiveDate = isset($request->effective_date) ? $request->effective_date : null;
        $productIds = $request->product_id;
        foreach ($manualUserId as $value) {
            foreach ($productIds as $productId) {
                $manualOverride = ManualOverrides::updateOrCreate(['manual_user_id' => $userId, 'user_id' => $value, 'product_id' => $productId], [
                    'overrides_amount' => $overridesAmount,
                    'overrides_type' => $overridesType,
                    'effective_date' => $effectiveDate,
                ]);

                ManualOverridesHistory::updateOrCreate(['manual_user_id' => $userId, 'user_id' => $value, 'product_id' => $productId], [
                    'updated_by' => Auth()->user()->id,
                    'manual_overrides_id' => $manualOverride->id,
                    'old_overrides_amount' => 0.0,
                    'overrides_amount' => $overridesAmount,
                    'overrides_type' => $overridesType,
                    'effective_date' => $effectiveDate,
                ]);

                OverrideStatus::updateOrCreate(['user_id' => $userId, 'recruiter_id' => $value, 'product_id' => $productId], [
                    'status' => 0,
                    'type' => 'Manual',
                    'effective_date' => $effectiveDate,
                    'updated_by' => Auth::user()->id,
                ]);
            }
        }

        return response()->json([
            'ApiName' => 'manual_overrides',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ]);
    }

    public function get_mysale_overrides(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'type' => 'required',
            'recruiter_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        if ($request->type == 'Manual') {
            $validator = Validator::make($request->all(), [
                'id' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 400);
            }
        }

        $data = ManualOverrides::with('user', 'manualUser', 'ManualOverridesHistory')->where('id', $request->id)->first();
        $override_status_history = OverrideStatus::with('updated_by:id,first_name,last_name,image,is_manager,is_super_admin,position_id,sub_position_id')->where('user_id', $request->user_id)->where('recruiter_id', $request->recruiter_id)->where('product_id', $request->product_id)->where('type', $request->type)->orderBy('effective_date', 'ASC')->get();
        $override_since = OverrideStatus::where('user_id', $request->user_id)->where('recruiter_id', $request->recruiter_id)->where('type', $request->type)->where('product_id', $request->product_id)->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->first();
        $user = User::select('first_name', 'last_name')->where('id', $request->user_id)->first();
        $recruiter = User::select('first_name', 'last_name')->where('id', $request->recruiter_id)->first();
        $current_status = 'Enabled';
        $status_since = null;
        if ($override_since) {
            if ($override_since->status == 1) {
                $current_status = 'Disabled';
                $status_since = $override_since->effective_date;
            } elseif ($override_since->status == 0) {
                $current_status = 'Enabled';
                $status_since = $override_since->effective_date;
            }
        }

        $override_status_history_with_old_status = [];
        $previous_status = null;
        foreach ($override_status_history as $status) {
            if ($status->status == 1) {
                $status->status = 'Disable';
            } else {
                $status->status = 'Enable';
            }
            $status_with_old = $status->toArray();
            $status_with_old['old_status'] = $previous_status;
            $override_status_history_with_old_status[] = $status_with_old;
            $previous_status = $status->status;
        }

        return response()->json([
            'ApiName' => 'my_overrides',
            'status' => true,
            'message' => 'Successfully.',
            'data' => [
                'status_since' => $status_since,
                'current_status' => $current_status,
                'override_status' => [
                    'user' => $user,
                    'recruiter' => $recruiter,
                ],
                'data' => $data,
                'override_status_history' => $override_status_history_with_old_status,
            ],
        ]);
    }

    public function edit_manual_overrides(Request $request): JsonResponse
    {
        $id = $request->id;
        $overrides_amount = $request->overrides_amount;
        $overrides_type = $request->overrides_type;
        $effective_date = $request->effective_date ?? null;
        $productId = $request->product_id;
        $manual_user_id = $request->manual_user_id;
        $user_id = $request->user_id;

        $manualOverrides = ManualOverrides::where('id', $id)->first();
        if ($manualOverrides) {
            $currentDate = Carbon::now()->format('Y-m-d');
            if ($effective_date && $currentDate >= $effective_date) {
                $manualOverrides->overrides_amount = $overrides_amount;
                $manualOverrides->overrides_type = $overrides_type;
                $manualOverrides->effective_date = $effective_date;
                $manualOverrides->product_id = $productId;
                $manualOverrides->save();
            }
        } else {
            return response()->json(['message' => 'No record found with the provided ID.'], 404);
        }

        $manualOverridesHistory = ManualOverridesHistory::where('manual_overrides_id', $id)->where('effective_date', $effective_date)->first();
        if (! empty($manualOverridesHistory)) {
            $manualOverridesHistory['manual_user_id'] = $manual_user_id;
            $manualOverridesHistory['manual_overrides_id'] = $id;
            $manualOverridesHistory['user_id'] = $user_id;
            $manualOverridesHistory['updated_by'] = Auth()->user()->id;
            $manualOverridesHistory['overrides_amount'] = $overrides_amount;
            $manualOverridesHistory['overrides_type'] = $overrides_type;
            $data['effective_date'] = $effective_date;
            $manualOverridesHistory['product_id'] = $productId;
            $manualOverridesHistory->save();
        } else {
            $oldAmount = ManualOverridesHistory::where('manual_user_id', $manual_user_id)->where('user_id', $user_id)->where('product_id', $productId)->orderBy('id', 'desc')->first();
            $data['manual_user_id'] = $manual_user_id;
            $data['user_id'] = $user_id;
            $data['manual_overrides_id'] = $id;
            $data['updated_by'] = Auth()->user()->id;
            $data['overrides_amount'] = $overrides_amount;
            $data['old_overrides_amount'] = isset($oldAmount->overrides_amount) ? $oldAmount->overrides_amount : 0.0;
            $data['old_overrides_type'] = isset($oldAmount->overrides_type) ? $oldAmount->overrides_type : 'per sale';
            $data['overrides_type'] = $overrides_type;
            $data['effective_date'] = $effective_date;
            $data['product_id'] = $productId;
            ManualOverridesHistory::create($data);
        }

        return response()->json([
            'ApiName' => 'manual_overrides',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ]);
    }

    public function OverridesEnableDisable(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required',
            'recruiter_id' => 'required',
            'user_id' => 'required',
            'type' => 'required',
            'effective_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ApiName' => 'Over ride status',
                'status' => false,
                'message' => $validator->errors(),
            ], 400);
        }

        $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->first();
        if ($payroll) {
            return response()->json(['status' => false, 'Message' => 'At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.'], 400);
        }


        $status = $request->status;
        $recruiter_id = $request->recruiter_id;
        $user_id = $request->user_id;
        $type = $request->type;
        $effective_date = $request->effective_date;
        $productID = $request->product_id??1;
        // $over_ride = OverrideStatus::where('user_id',$user_id)->where('recruiter_id',$recruiter_id)->where('type',$type)->first();
        $over_ride = OverrideStatus::where('user_id', $user_id)->where('recruiter_id', $recruiter_id)->where('product_id', $productID)->where('type', $type)->whereNotNull('effective_date')->orderBy('effective_date', 'DESC')->first();

        if ($over_ride) {

            if ($over_ride->effective_date > date('Y-m-d')) {
                return response()->json([
                    'ApiName' => 'Over ride status',
                    'status' => false,
                    'message' => 'Cannot add more then one override status for future date.',
                ], 400);
            }
            if ($over_ride->effective_date >= $effective_date) {
                return response()->json([
                    'ApiName' => 'Over ride status',
                    'status' => false,
                    'message' => 'effective date must be greater than  '.$over_ride->effective_date,
                ], 400);
            }

            if ($over_ride->status == 1 && $status == 1) {
                return response()->json([
                    'ApiName' => 'Over ride status',
                    'status' => false,
                    'message' => 'Override is already disabled',
                ], 400);
            }
            if ($over_ride->status == 0 && $status == 0) {
                return response()->json([
                    'ApiName' => 'Over ride status',
                    'status' => false,
                    'message' => 'Override is already enabled',
                ], 400);
            }
        }

        $over_ride = OverrideStatus::updateOrCreate([
            'user_id' => $user_id,
            'recruiter_id' => $recruiter_id,
            'type' => $type,
            'product_id' => $productID,
            'effective_date' => $effective_date,
        ], [
            'user_id' => $user_id,
            'recruiter_id' => $recruiter_id,
            'status' => $status,
            'type' => $type,
            'effective_date' => $effective_date,
            'product_id' => isset($productID) ? $productID : 1,
            // 'updated_by' => Auth::user()->id,
            'updated_by' => auth()->user()->id,
        ]);

        // Get all sales from the effective date that match the user and product
        // Uses batch processing to prevent server hanging with large datasets
        $this->recalculateAffectedSales($user_id, $productID, $effective_date);
        
        return response()->json([
            'ApiName' => 'Over ride status',
            'status' => true,
            'message' => 'Removing overrides… Please wait.'
        ], 200);
    }

    /**
     * Recalculate affected sales when override status changes using batch processing
     *
     * @param int $userId
     * @param int $productId
     * @param string $effectiveDate
     * @return void
     */
    private function recalculateAffectedSales($userId, $productId, $effectiveDate)
    {
        try {
            // Get paid sales to exclude them from recalculation
            
            // Find all sales from effective date that match user and product
            $affectedSales = SalesMaster::select('pid')
                ->whereHas('salesMasterProcessInfo', function ($q) use ($userId) {
                    $q->where(function ($q) use ($userId) {
                        $q->where('closer1_id', $userId)
                        ->orWhere('setter1_id', $userId)
                        ->orWhere('closer2_id', $userId)
                        ->orWhere('setter2_id', $userId);
                    });
                })
                ->where('customer_signoff', '>=', $effectiveDate)
                ->when($productId > 0, function ($q) use ($productId) {
                    if ($productId == 1) {
                        $q->where(function ($query) {
                            $query->where('product_id', 1)
                                  ->orWhereNull('product_id');
                        });
                    } else {
                        $q->where('product_id', $productId);
                    }
                })
                ->whereNull('date_cancelled')
                ->pluck('pid')
                ->toArray();

            if (!empty($affectedSales)) {
                // Use batch processing to avoid hanging the server
                $batchSize = 30; // Process in batches of 50 sales
                $batches = array_chunk($affectedSales, $batchSize);
                
                foreach ($batches as $index => $batch) {
                    // Dispatch each batch as a separate job
                    RecalculateSalesJob::dispatch($batch)
                        ->delay(now()->addSeconds($index * 10)); // Stagger jobs by 10 seconds each
                }
                
                Log::info('Override recalculation jobs dispatched', [
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'effective_date' => $effectiveDate,
                    'total_sales' => count($affectedSales),
                    'total_batches' => count($batches),
                    'batch_size' => $batchSize
                ]);
            } else {
                Log::info('No affected sales found for override recalculation', [
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'effective_date' => $effectiveDate
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to dispatch override recalculation jobs', [
                'user_id' => $userId,
                'product_id' => $productId,
                'effective_date' => $effectiveDate,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }
}
