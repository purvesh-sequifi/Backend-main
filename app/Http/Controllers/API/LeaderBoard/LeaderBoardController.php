<?php

namespace App\Http\Controllers\API\LeaderBoard;

use App\Exports\LeaderBoardExport;
use App\Http\Controllers\Controller;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\Integration;
use App\Models\Locations;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class LeaderBoardController extends Controller
{
    public function leaderboardListByOffice(Request $request)
    {
        $startDate = '';
        $endDate = '';
        $user = Auth::user();
        $loggedUserOfficeId = $user->office_id;
        $companyProfile = CompanyProfile::first();
        if ($request->has('filter') && $request->input('filter')) {
            $filterValue = $request->input('filter');
            $filterDate = $this->getFilterDate($filterValue);

            if (! empty($filterDate['startDate']) && ! empty($filterDate['endDate'])) {
                $startDate = $filterDate['startDate'];
                $endDate = $filterDate['endDate'];
            } elseif ($filterValue == 'custom' && $request->input('start_date') && $request->input('end_date')) {
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
            } elseif ($filterValue == 'all') {
                $startDate = '';
                $endDate = '';
            } else {
                return response()->json([
                    'ApiName' => 'leaderboardListByOffice',
                    'status' => false,
                    'message' => 'Error. in Date filter',
                    'data' => [],
                ], 400);
            }
        }

        $perPage = 10;
        if (isset($request->perpage) && $request->perpage != '') {
            $perPage = $request->perpage;
        }

        $isExport = 0;
        if (isset($request->is_export) && ($request->is_export == 1)) {
            $isExport = 1;
        }

        $keyChange = [];
        if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
            $keyChange['kw_pending'] = 'kwPending';
            $keyChange['kw_installed'] = 'kwInstalled';
            $keyChange['avg_net_epc'] = 'avgNetEPC';
        } elseif ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            $keyChange['sqft_pending'] = 'kwPending';
            $keyChange['sqft_installed'] = 'kwInstalled';
            $keyChange['avg_sqft'] = 'avgNetEPC';
        } elseif (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $keyChange['value_sold'] = 'kwPending';
            $keyChange['value_serviced'] = 'kwInstalled';
            $keyChange['avg_contracted_value'] = 'avgNetEPC';
        }

        // filter by positions
        $positionIds = [];
        if ($request->has('position_id') && $request->input('position_id')) {
            $positionIds = Arr::wrap($request->input('position_id'));
        }

        $offices = Locations::select('id', 'office_name')->where('type', 'Office')->get();
        $offices->transform(function ($office) use ($startDate, $endDate, $isExport, $companyProfile, $keyChange, $positionIds) {
            $userQuery = User::where('office_id', $office->id)->where('is_super_admin', '0')->where('disable_login', 0);
            if (count($positionIds) > 0 && ! in_array('all', $positionIds)) {
                $userQuery->whereIn('sub_position_id', $positionIds);
            }
            $userIds = $userQuery->pluck('id');
            $employeeCount = count($userIds);
            $salesPid = SaleMasterProcess::whereIn('closer1_id', $userIds)
                ->orWhereIn('closer2_id', $userIds)
                ->orWhereIn('setter1_id', $userIds)
                ->orWhereIn('setter2_id', $userIds)
                ->pluck('pid');
            $salesQuery = SalesMaster::whereIn('pid', $salesPid);
            if (! empty($startDate) && ! empty($endDate)) {
                $salesQuery->whereBetween('customer_signoff', [$startDate, $endDate]);
            }

            $clawBackPid = ClawbackSettlement::whereNotNull('pid')->groupBy('pid')->pluck('pid')->toArray();
            $cancelled = SalesMaster::whereIn('pid', $salesPid)
                ->when(! empty($startDate) && ! empty($endDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })
                ->whereNotNull('date_cancelled')
                ->whereNotIn('pid', $clawBackPid)
                ->count();

            $clawBack = SalesMaster::whereIn('pid', $salesPid)
                ->when(! empty($startDate) && ! empty($endDate), function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('customer_signoff', [$startDate, $endDate]);
                })
                ->whereNotNull('date_cancelled')
                ->whereIn('pid', $clawBackPid)
                ->count();

            $avgNetEPC = 0;
            $completionRate = 0;
            $saleData = $this->salesLogic($companyProfile, $salesQuery, $salesPid, $startDate, $endDate);
            $totalSum = $saleData['totalSum'];
            $pendingSale = $saleData['pendingSale'];
            $installedSale = $saleData['installedSale'];
            $kwInstalled = $saleData['kwInstalled'];
            $salesData = $saleData['salesData'];
            $soldSale = $salesData->total_sales ?? 0;
            $kwPending = $salesData->kw_pending ?? 0;
            $unknownSale = $salesData->unknown ?? 0;
            if ($soldSale > 0) {
                $completionRate = ($installedSale / $soldSale) * 100;
                $avgNetEPC = ($totalSum / $soldSale);
            }

            $data = [
                'office_id' => $office->id,
                'office_name' => $office->office_name,
                'employee_count' => (($employeeCount > 0) ? $employeeCount : '0'),
                'sold' => (($soldSale > 0) ? $soldSale : '0'),
                'installed' => (($installedSale > 0) ? $installedSale : '0'),
                'pending' => (($pendingSale > 0) ? $pendingSale : '0'),
                'unknown' => (($unknownSale > 0) ? $unknownSale : '0'),
                'cancelled' => (($cancelled > 0) ? $cancelled : '0'),
                'clawback' => (($clawBack > 0) ? $clawBack : '0'),
                'completion_rate' => (($completionRate > 0) ? round($completionRate, 2) : '0.0'),
            ];

            foreach ($keyChange as $key => $varName) {
                $value = isset($$varName) ? $$varName : 0;
                $data[$key] = ($value > 0) ? round($value, 2) : '0.0';
            }

            if ($isExport == 1) {
                unset($data['office_id']);
            }

            return $data;
        });

        $columnName = 'sold';
        if (isset($request->column_name) && isset($request->column_name)) {
            $columnName = $request->column_name;
        }

        $sortOrder = 'desc';
        if (isset($request->sort_order) && isset($request->sort_order)) {
            $sortOrder = $request->sort_order;
        }

        $offices = $offices->toArray();
        if ($sortOrder == 'desc') {
            array_multisort(array_column($offices, $columnName), SORT_DESC, $offices);
        } else {
            array_multisort(array_column($offices, $columnName), SORT_ASC, $offices);
        }

        $loggedUserRecord = [];
        $topRanked = [];
        foreach ($offices as $key => $office) {
            if ($sortOrder == 'desc') {
                $rank = $key + 1;
            } else {
                $rank = count($offices) - $key;
            }

            if ($isExport == 0) {
                if ($office['office_id'] == $loggedUserOfficeId) {
                    $office['rank'] = $rank;
                    $loggedUserRecord = $office;
                }
            }

            $offices[$key] = array_merge(['rank' => $rank], $office);
            if ($rank >= 1 && $rank <= 3) {
                $office['rank'] = $rank;
                array_push($topRanked, $office);
            }
        }

        if ($request->has('search') && ! empty($request->input('search'))) {
            $offices = collect($offices)->filter(function ($item) use ($request) {
                if (strpos(strtolower($item['office_name']), strtolower($request->search)) !== false) {
                    return true;
                }
            })->toArray();
        }

        if (isset($request->is_export) && ($request->is_export == 1)) {
            $fileName = 'leader_board_list_by_office_'.date('Y_m_d_H_i_s').'.xlsx';
            Excel::store(
                new LeaderBoardExport($offices, 'office', $companyProfile),
                'exports/leaderboard/'.$fileName,
                'public',
                \Maatwebsite\Excel\Excel::XLSX
            );

            $url = getStoragePath('exports/leaderboard/'.$fileName);

            return response()->json(['url' => $url]);
        }

        $finalData = $this->paginate($offices, $perPage);
        $latestLog = SystemSetting::orderBy('updated_at', 'desc')->first() ?? SalesMaster::orderBy('updated_at', 'desc')->first();

        return response()->json([
            'ApiName' => 'leaderboardListByOffice',
            'status' => true,
            'message' => 'Successfully.',
            'last_updated' => $latestLog ? $latestLog->updated_at : null,
            'data' => $finalData,
            'logged_user_record' => $loggedUserRecord,
            'top_ranked_record' => $topRanked,
        ]);
    }

    public function leaderboardListByUsers(Request $request)
    {
        $startDate = '';
        $endDate = '';
        $user = Auth::user();
        $loggedUserId = $user->id;
        $companyProfile = CompanyProfile::first();
        if ($request->has('filter') && $request->input('filter')) {
            $filterValue = $request->input('filter');
            $filterDate = $this->getFilterDate($filterValue);

            if (! empty($filterDate['startDate']) && ! empty($filterDate['endDate'])) {
                $startDate = $filterDate['startDate'];
                $endDate = $filterDate['endDate'];
            } elseif ($filterValue == 'custom' && $request->input('start_date') && $request->input('end_date')) {
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
            } elseif ($filterValue == 'all') {
                $startDate = '';
                $endDate = '';
            } else {
                return response()->json([
                    'ApiName' => 'leaderboardListByUsers',
                    'status' => false,
                    'message' => 'Error. in date filter.',
                    'data' => [],
                ], 400);
            }
        }

        $officeId = '';
        if (isset($request->office_id) && ! empty($request->office_id)) {
            $officeId = $request->office_id;
        }

        $perPage = 10;
        if (isset($request->perpage) && $request->perpage != '') {
            $perPage = $request->perpage;
        }

        $isExport = 0;
        if (isset($request->is_export) && ($request->is_export == 1)) {
            $isExport = 1;
        }

        $keyChange = [];
        if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
            $keyChange['kw_pending'] = 'kwPending';
            $keyChange['kw_installed'] = 'kwInstalled';
            $keyChange['avg_net_epc'] = 'avgNetEPC';
        } elseif ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            $keyChange['sqft_pending'] = 'kwPending';
            $keyChange['sqft_installed'] = 'kwInstalled';
            $keyChange['avg_sqft'] = 'avgNetEPC';
        } elseif (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $keyChange['value_sold'] = 'kwPending';
            $keyChange['value_serviced'] = 'kwInstalled';
            $keyChange['avg_contracted_value'] = 'avgNetEPC';
        }

        $userQuery = User::select('id', 'first_name', 'last_name', 'image', 'position_id', 'sub_position_id', 'is_manager', 'is_super_admin', 'office_id')->where('is_super_admin', '0')->where('disable_login', 0);
        if ($officeId != 'all' && is_numeric($officeId)) {
            $userQuery->where('office_id', $officeId);
        }

        // filter by positions
        if ($request->has('position_id') && $request->input('position_id')) {
            $positionIds = Arr::wrap($request->input('position_id'));
            if (count($positionIds) > 0 && ! in_array('all', $positionIds)) {
                $userQuery->whereIn('sub_position_id', $positionIds);
            }
        }

        $users = $userQuery->get();
        $users->transform(function ($user) use ($startDate, $endDate, $isExport, $companyProfile, $keyChange) {
            $userTerminated = isUserTerminatedOn($user->id, date('Y-m-d')) ? 1 : 0;
            if ($userTerminated == 0) {
                $userId = $user->id;
                $officeData = Locations::select('id', 'office_name')->where('id', $user->office_id)->first();
                $office_name = @$officeData->office_name;

                $salesPid = SaleMasterProcess::where('closer1_id', $userId)
                    ->orWhere('closer2_id', $userId)
                    ->orWhere('setter1_id', $userId)
                    ->orWhere('setter2_id', $userId)
                    ->pluck('pid');

                $salesQuery = SalesMaster::whereIn('pid', $salesPid);
                if (! empty($startDate) && ! empty($endDate)) {
                    $salesQuery->whereBetween('customer_signoff', [$startDate, $endDate]);
                }

                $avgNetEPC = 0;
                $completionRate = 0;
                $saleData = $this->salesLogic($companyProfile, $salesQuery, $salesPid, $startDate, $endDate);
                $totalSum = $saleData['totalSum'];
                $pendingSale = $saleData['pendingSale'];
                $installedSale = $saleData['installedSale'];
                $kwInstalled = $saleData['kwInstalled'];
                $salesData = $saleData['salesData'];
                $soldSale = $salesData->total_sales ?? 0;
                $kwPending = $salesData->kw_pending ?? 0;
                if ($soldSale > 0) {
                    $completionRate = ($installedSale / $soldSale) * 100;
                    $avgNetEPC = ($totalSum / $soldSale);
                }

                $data = [
                    'user_id' => $user->id,
                    'user_name' => $user->first_name.' '.$user->last_name,
                    'user_image' => $user->image,
                    'position_id' => $user->position_id,
                    'sub_position_id' => $user->sub_position_id,
                    'is_manager' => $user->is_manager,
                    'is_super_admin' => $user->is_super_admin,
                    'office_id' => $user->office_id,
                    'office_name' => $office_name,
                    'sold' => (($soldSale > 0) ? $soldSale : '0'),
                    'installed' => (($installedSale > 0) ? $installedSale : '0'),
                    'pending' => (($pendingSale > 0) ? $pendingSale : '0'),

                    'completion_rate' => (($completionRate > 0) ? round($completionRate, 2) : '0.0'),
                ];

                foreach ($keyChange as $key => $varName) {
                    $value = isset($$varName) ? $$varName : 0;
                    $data[$key] = ($value > 0) ? round($value, 2) : '0.0';
                }

                if ($isExport == 1) {
                    unset($data['user_id']);
                    unset($data['office_id']);
                    unset($data['user_image']);
                    unset($data['is_super_admin']);
                    unset($data['is_manager']);
                    unset($data['sub_position_id']);
                    unset($data['position_id']);
                }

                return $data;
            } else {
                return null; // This will result in nulls in the collection
            }
        });

        $users = $users->filter();

        $columnName = 'sold';
        if (isset($request->column_name) && isset($request->column_name)) {
            $columnName = $request->column_name;
        }

        $sortOrder = 'desc';
        if (isset($request->sort_order) && isset($request->sort_order)) {
            $sortOrder = $request->sort_order;
        }

        $users = $users->toArray();
        if ($sortOrder == 'desc') {
            array_multisort(array_column($users, $columnName), SORT_DESC, $users);
        } else {
            array_multisort(array_column($users, $columnName), SORT_ASC, $users);
        }

        $loggedUserRecord = [];
        $topRanked = [];
        foreach ($users as $key => $user) {
            if ($sortOrder == 'desc') {
                $rank = $key + 1;
            } else {
                $rank = count($users) - $key;
            }

            if ($isExport == 0) {
                if ($user['user_id'] == $loggedUserId) {
                    $user['rank'] = $rank;
                    $loggedUserRecord = $user;
                }
            }

            $users[$key] = array_merge(['rank' => $rank], $user);
            if ($rank >= 1 && $rank <= 3) {
                $user['rank'] = $rank;
                if (isset($user['user_image']) && $user['user_image'] != null) {
                    $userImageS3 = s3_getTempUrl(config('app.domain_name').'/'.$user['user_image']);
                } else {
                    $userImageS3 = null;
                }

                $user['user_image'] = $userImageS3;
                array_push($topRanked, $user);
            }
        }

        if ($request->has('search') && ! empty($request->input('search'))) {
            $users = collect($users)->filter(function ($item) use ($request) {
                if (strpos(strtolower($item['user_name']), strtolower($request->search)) !== false) {
                    return true;
                }
            })->toArray();
        }

        if (isset($request->is_export) && ($request->is_export == 1)) {
            $fileName = 'leader_board_list_by_user_'.date('Y_m_d_H_i_s').'.xlsx';
            Excel::store(
                new LeaderBoardExport($users, 'user', $companyProfile),
                'exports/leaderboard/'.$fileName,
                'public',
                \Maatwebsite\Excel\Excel::XLSX
            );

            $url = getStoragePath('exports/leaderboard/'.$fileName);

            return response()->json(['url' => $url]);
        }

        $users = collect($users)->map(function ($item) {
            if (isset($item['user_image']) && $item['user_image'] != null) {
                $item['user_image'] = s3_getTempUrl(config('app.domain_name').'/'.$item['user_image']);
            } else {
                $item['user_image'] = null;
            }

            // Add computed columns
            $item['dismiss'] = isUserDismisedOn($item['user_id'], date('Y-m-d')) ? 1 : 0;
            $item['terminate'] = isUserTerminatedOn($item['user_id'], date('Y-m-d')) ? 1 : 0;
            $item['contract_ended'] = isUserContractEnded($item['user_id']) ? 1 : 0;

            return $item;
        });

        $finalData = $this->paginate($users->toArray(), $perPage);
        $latestLog = SystemSetting::orderBy('updated_at', 'desc')->first() ?? SalesMaster::orderBy('updated_at', 'desc')->first();

        return response()->json([
            'ApiName' => 'leaderboardListByUsers',
            'status' => true,
            'last_updated' => $latestLog ? $latestLog->updated_at : null,
            'message' => 'Successfully.',
            'data' => $finalData,
            'logged_user_record' => $loggedUserRecord,
            'top_ranked_record' => $topRanked,
        ]);
    }

    protected function salesLogic($companyProfile, $salesQuery, $salesPid, $startDate, $endDate)
    {
        $totalSum = 0;
        $pendingSale = 0;
        $installedSale = 0;
        $kwInstalled = 0;
        $unknownSale = 0;
        if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
            $salesData = $salesQuery->selectRaw(
                'COUNT(id) as total_sales,
                    SUM(CASE WHEN date_cancelled IS NULL AND m2_date IS NULL THEN 1 ELSE 0 END) as pending_sales, 
                    SUM(CASE WHEN date_cancelled IS NULL AND m2_date IS NOT NULL THEN 1 ELSE 0 END) as installed_sales, 
                    SUM(kw) as kw_pending,
                    SUM(CASE WHEN date_cancelled IS NULL AND m2_date IS NOT NULL THEN kw ELSE 0 END) as kw_installed'
            )->first();

            $totalSum = $salesQuery->sum('net_epc') ?? 0;
            $pendingSale = $salesData->pending_sales ?? 0;
            $installedSale = $salesData->installed_sales ?? 0;
            $kwInstalled = $salesData->kw_installed ?? 0;
        } elseif ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            $salesData = $salesQuery->selectRaw(
                'COUNT(id) as total_sales,
                    SUM(CASE WHEN date_cancelled IS NULL AND m2_date IS NULL THEN 1 ELSE 0 END) as pending_sales, 
                    SUM(CASE WHEN date_cancelled IS NULL AND m2_date IS NOT NULL THEN 1 ELSE 0 END) as installed_sales, 
                    SUM(gross_account_value) as kw_pending,
                    SUM(CASE WHEN date_cancelled IS NULL AND m2_date IS NOT NULL THEN gross_account_value ELSE 0 END) as kw_installed'
            )->first();

            $totalSum = $salesData->kw_pending ?? 0;
            $pendingSale = $salesData->pending_sales ?? 0;
            $installedSale = $salesData->installed_sales ?? 0;
            $kwInstalled = $salesData->kw_installed ?? 0;
        } elseif (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $fieldRouteCount = Integration::where(['name' => 'FieldRoutes', 'status' => 1])->count();
            if ($fieldRouteCount > 0) {
                $kwInstalled = SalesMaster::selectRaw('COUNT(id) as installed_sales, SUM(gross_account_value) as kw_installed')
                    ->whereNull('date_cancelled')
                    ->whereIn('pid', $salesPid)
                    ->where(function ($query) {
                        $query->where('initialStatusText', 'Completed')
                            ->orWhere(function ($q) {
                                $q->where(function ($subQ) {
                                    $subQ->whereIn('data_source_type', ['excel', 'randcpest2__field_routes', 'HomeTeam'])
                                        ->orWhere('data_source_type', 'like', 'Clark%');
                                })
                                    ->whereRaw("EXISTS (
                    SELECT 1 FROM JSON_TABLE(trigger_date, '$[*]' COLUMNS(value JSON PATH '$')) AS dates
                    WHERE value->>'$.date' IS NOT NULL AND value->>'$.date' != 'null'
                )");
                            })
                            ->orWhere(function ($q) {
                                $q->whereNull('initialStatusText')
                                    ->where(function ($subq) {
                                        $subq->whereNotNull('m1_date')
                                            ->orWhereNotNull('m2_date');
                                    })
                                    ->whereRaw("EXISTS (
                            SELECT 1 FROM JSON_TABLE(trigger_date, '$[*]' COLUMNS(value JSON PATH '$')) AS dates
                            WHERE value->>'$.date' IS NOT NULL AND value->>'$.date' != 'null'
                        )");
                            });
                    })
                    ->when(! empty($startDate) && ! empty($endDate), function ($query) use ($startDate, $endDate) {
                        return $query->whereBetween('customer_signoff', [$startDate, $endDate]);
                    })
                    ->first();

                $pendingSale = SalesMaster::whereNull('date_cancelled')
                    ->whereNotNull('customer_signoff')
                    ->whereIn('pid', $salesPid)
                    ->when(! empty($startDate) && ! empty($endDate), function ($query) use ($startDate, $endDate) {
                        return $query->whereBetween('customer_signoff', [$startDate, $endDate]);
                    })
                    ->where(function ($query) {
                        $query->where(function ($q) {
                            $q->whereNotNull('initialStatusText')
                                ->where('initialStatusText', '!=', 'Completed');
                        })
                            ->orWhere(function ($q) {
                                $q->whereNull('initialStatusText')
                                    ->whereNull('m1_date')
                                    ->whereNull('m2_date');
                            })
                            ->orWhere(function ($q) {
                                $q->where('data_source_type', 'excel')
                                    ->where(function ($q2) {
                                        $q2->whereRaw("JSON_EXTRACT(trigger_date, '$[0].date') IS NULL")
                                            ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = 'null'")
                                            ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) = ''");
                                    });
                            });
                    })
                    ->where(function ($query) {
                        $query->where(function ($q) {
                            // Excel sales with NULL or invalid trigger_date
                            $q->where('data_source_type', 'excel')
                                ->where(function ($subq) {
                                    $subq->whereNull('trigger_date')
                                        ->orWhereRaw("NOT JSON_UNQUOTE(JSON_EXTRACT(trigger_date, '$[0].date')) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
                                });
                        })->orWhere(function ($q) {
                            $q->where('data_source_type', '!=', 'excel');
                        });
                    })
                    ->count();

                $salesData = $salesQuery->selectRaw('COUNT(id) as total_sales, SUM(gross_account_value) as kw_pending')
                    ->whereNull('date_cancelled')
                    ->whereNotNull('customer_signoff')
                    ->when(! empty($startDate) && ! empty($endDate), function ($query) use ($startDate, $endDate) {
                        return $query->whereBetween('customer_signoff', [$startDate, $endDate]);
                    })
                    ->first();
            } else {
                $kwInstalled = SalesMaster::selectRaw('COUNT(id) as installed_sales, SUM(gross_account_value) as kw_installed')
                    ->whereNull('date_cancelled')->whereIn('pid', $salesPid)
                    ->when(! empty($startDate) && ! empty($endDate), function ($query) use ($startDate, $endDate) {
                        return $query->whereBetween('customer_signoff', [$startDate, $endDate]);
                    })->whereHas('salesProductMasterDetails', function ($q) {
                        $q->whereNotNull('milestone_date');
                    })->first();

                $pendingSale = SalesMaster::whereNull('date_cancelled')->whereNotNull('customer_signoff')->whereIn('pid', $salesPid)
                    ->when(! empty($startDate) && ! empty($endDate), function ($query) use ($startDate, $endDate) {
                        return $query->whereBetween('customer_signoff', [$startDate, $endDate]);
                    })->whereDoesntHave('salesProductMasterDetails', function ($q) {
                        $q->whereNotNull('milestone_date');
                    })->count();

                $salesData = $salesQuery->selectRaw('COUNT(id) as total_sales, SUM(gross_account_value) as kw_pending')->first();
            }

            $totalSum = $salesData->kw_pending ?? 0;
            $pendingSale = $pendingSale ?? 0;
            $installedSale = $kwInstalled->installed_sales ?? 0;
            $kwInstalled = $kwInstalled->kw_installed ?? 0;
        }

        return [
            'totalSum' => $totalSum,
            'pendingSale' => $pendingSale,
            'installedSale' => $installedSale,
            'kwInstalled' => $kwInstalled,
            'unknownSale' => $unknownSale,
            'salesData' => $salesData,
        ];
    }

    public function getFilterDate($filterName)
    {
        $startDate = '';
        $endDate = '';
        if ($filterName == 'today') {
            $startDate = date('Y-m-d', strtotime(now()));
            $endDate = date('Y-m-d', strtotime(now()));
        } elseif ($filterName == 'yesterday') {
            $startDate = date('Y-m-d', strtotime(now()->subDay()));
            $endDate = date('Y-m-d', strtotime(now()->subDay()));
        } elseif ($filterName == 'this_week') {
            $startDate = date('Y-m-d', strtotime(now()->startOfWeek()));
            $endDate = date('Y-m-d', strtotime(now()));
        } elseif ($filterName == 'last_week') {
            $startOfLastWeek = \Carbon\Carbon::now()->subDays(7)->startOfWeek();
            $endOfLastWeek = \Carbon\Carbon::now()->subDays(7)->endOfWeek();
            $startDate = date('Y-m-d', strtotime($startOfLastWeek));
            $endDate = date('Y-m-d', strtotime($endOfLastWeek));
        } elseif ($filterName == 'this_month') {
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            $startDate = date('Y-m-d', strtotime($startOfMonth));
            $endDate = date('Y-m-d', strtotime($endOfMonth));
        } elseif ($filterName == 'last_month') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(1)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(1)->endOfMonth()));
        } elseif ($filterName == 'this_quarter') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
        } elseif ($filterName == 'last_quarter') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(5)->addDays(0)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
        } elseif ($filterName == 'this_year') {
            $startDate = Carbon::now()->startOfYear()->format('Y-m-d');
            $endDate = Carbon::now()->endOfYear()->format('Y-m-d');
        } elseif ($filterName == 'last_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
        } elseif ($filterName == 'last_12_months') {
            $startDate = date('Y-m-d', strtotime(\Illuminate\Support\Carbon::now()->subMonths(12)));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
        }

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
    }

    public function paginate($items, $perPage = 10, $page = null)
    {
        $total = count($items);
        $page = Paginator::resolveCurrentPage('page');
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page);
        $start = ($paginator->currentPage() - 1) * $perPage;
        $sliced = array_slice($items, $start, $perPage);

        return new LengthAwarePaginator($sliced, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }
}
