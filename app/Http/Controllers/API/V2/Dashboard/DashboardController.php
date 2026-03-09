<?php

namespace App\Http\Controllers\API\V2\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ApprovalsAndRequest;
use App\Models\CompanyProfile;
use App\Models\FrequencyType;
use App\Models\Lead;
use App\Models\Payroll;
use App\Models\PayrollHistory;
use App\Models\PositionPayFrequency;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserAttendance;
use App\Models\UserAttendanceDetail;
use App\Models\UserSchedule;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private $lead;

    public function __construct(Lead $lead)
    {
        $this->lead = $lead;
    }

    /**
     * Method getFilterDate : This funnction return date as per filter name
     *
     * @param  $filterName  $filterName [explicite description]
     * @return array
     */
    public function getFilterDate($filterName)
    {
        $startDate = '';
        $endDate = '';
        if ($filterName == 'this_week') {
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

    public function arenaLeaderboardData(Request $request)
    {
        // Validate filter input
        // return $request->all();
        $companyProfile = CompanyProfile::first();
        if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE) {
            $KWsoldField = 'kw';
        } else {
            $KWsoldField = 'gross_account_value';
        }

        if ($request->has('filter') && $request->input('filter')) {
            $filterValue = $request->input('filter');
            $filterDate = $this->getFilterDate($filterValue);

            if (! empty($filterDate['startDate']) && ! empty($filterDate['endDate'])) {
                $startDate = $filterDate['startDate'];
                $endDate = $filterDate['endDate'];
            } elseif ($filterValue == 'custom' && $request->input('start_date') && $request->input('end_date')) {
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
            } else {
                return response()->json([
                    'ApiName' => 'Arena Leaderboard API Data',
                    'status' => false,
                    'message' => 'Error. Something went wrong.',
                    'data' => [],
                ], 400);
            }
        }
        $response = [];
        if ($request->has('offices') && ! empty($request->input('offices'))) {

            $office_ids = $request->input('offices');
            foreach ($office_ids as $office) {

                $officeId = $office['officeId'];
                $schedulesData = $this->getUserSchedules($startDate, $endDate, $userId = null, $officeId);
                $totalWorkedHours = (! empty($schedulesData[0]['totalWorkedHours']) ? $schedulesData[0]['totalWorkedHours'] : '00').(! empty($schedulesData[0]['totalWorkedMinutes']) ? ':'.$schedulesData[0]['totalWorkedMinutes'] : ':00').':00';
                if (! empty($schedulesData[0]['schedules'])) {
                    $countIsLateFalse = count(array_filter($schedulesData[0]['schedules'], function ($schedule) {
                        // return !$schedule['isLate'];
                        return ! $schedule['isLate'] && $schedule['schedule_from'] <= date('Y-m-d');
                    }));
                } else {
                    $countIsLateFalse = 0;
                }
                $getLeadData = $this->getLeadData($startDate, $endDate, $userId = 0, $officeId);
                // return $getLeadData;
                if (count($getLeadData) > 0) {
                    $countHired = count(array_filter($getLeadData->toArray(), function ($lead) {
                        return $lead['status'] === 'Hired';
                    }));

                    $countFollowUp = count(array_filter($getLeadData->toArray(), function ($lead) {
                        return $lead['status'] === 'Follow Up';
                    }));
                } else {
                    $countHired = 0;
                    $countFollowUp = 0;
                }

                $userIds = $office['userIds'];

                $salesQuery = SalesMaster::with('salesMasterProcess')
                    ->whereBetween('customer_signoff', [$startDate, $endDate]);

                $selfgenUserIds = User::where('self_gen_accounts', 1)->whereIn('id', $userIds)->pluck('id');
                $selfGenSalesPids = SaleMasterProcess::whereIn('closer1_id', $userIds)
                    ->orWhereIn('closer2_id', $userIds)
                    ->orWhereIn('setter1_id', $userIds)
                    ->orWhereIn('setter2_id', $userIds)
                    ->pluck('pid');

                $closerSalesPids = SaleMasterProcess::whereIn('closer1_id', $userIds)
                    ->orWhereIn('closer2_id', $userIds)
                    ->pluck('pid');

                $setterSalesPids = SaleMasterProcess::WhereIn('setter1_id', $userIds)
                    ->orWhereIn('setter2_id', $userIds)
                    ->pluck('pid');

                $selfgenSalesQuery = clone $salesQuery;
                $selfgenSalesQuery = $selfgenSalesQuery->whereIn('pid', $selfGenSalesPids);

                $closerSalesQuery = clone $salesQuery;
                $closerSalesQuery = $closerSalesQuery->whereIn('pid', $closerSalesPids);

                $setterSalesQuery = clone $salesQuery;
                $setterSalesQuery = $setterSalesQuery->whereIn('pid', $setterSalesPids);
                // dd($closerSalesPids,$setterSalesPids);
                $selfGenSalesData = $selfgenSalesQuery->get();
                $closerSalesData = $closerSalesQuery->get();
                $setterSalesData = $setterSalesQuery->get();

                $selfGenSalesInstalled = $selfGenSalesData->where('date_cancelled', null)->where('m2_date', '!=', null)->count();
                $closerSalesInstalled = $closerSalesData->where('date_cancelled', null)->where('m2_date', '!=', null)->count();
                $setterSalesInstalled = $setterSalesData->where('date_cancelled', null)->where('m2_date', '!=', null)->count();

                $selfGen_kw_installed = $selfGenSalesData->where('m2_date', '!=', null)->where('date_cancelled', null)->sum($KWsoldField);
                $closer_kw_installed = $closerSalesData->where('m2_date', '!=', null)->where('date_cancelled', null)->sum($KWsoldField);
                $setter_kw_installed = $setterSalesData->where('m2_date', '!=', null)->where('date_cancelled', null)->sum($KWsoldField);
                $response[] = [
                    'id' => $officeId,
                    'new_hireing_lead' => $countFollowUp,
                    'hired' => $countHired,
                    // "worked_hrs" => "00:00:00",//$totalWorkedHours,
                    // "days_on_time" => 0,//$countIsLateFalse, // Placeholder, replace with actual value
                    'worked_hrs' => $totalWorkedHours,
                    'days_on_time' => $countIsLateFalse,
                    'soldAs_setter' => $setterSalesData->count(),
                    'soldAs_closer' => $closerSalesData->count(),
                    'soldAs_selfgen' => $selfGenSalesData->count(),
                    'InstallAs_setter' => $setterSalesInstalled,
                    'InstallAs_closer' => $closerSalesInstalled,
                    'InstallAs_selfgen' => $selfGenSalesInstalled,
                    'KWsoldAs_setter' => $setterSalesData->sum($KWsoldField),
                    'KWsoldAs_closer' => $closerSalesData->sum($KWsoldField),
                    'KWsoldAs_selfgen' => $selfGenSalesData->sum($KWsoldField),
                    'KWInstallAs_setter' => $setter_kw_installed,
                    'KWInstallAs_closer' => $closer_kw_installed,
                    'KWInstallAs_selfgen' => $selfGen_kw_installed,
                    'Type' => 'OfficeId',
                ];

            }
            // return $response;
        }
        // Filter by user_ids if provided
        if ($request->has('userIds') && ! empty($request->input('userIds'))) {

            $user_id = $request->input('userIds');

            // $userIds = User::whereIn('office_id', $office_id)->pluck('id');
            $userIds = $user_id;
            // return $userIds;
            $selfgenUserIds = User::where('self_gen_accounts', 1)->whereIn('id', $userIds)->pluck('id');
            $settersUserIds = User::join('positions', 'positions.id', 'users.position_id')
                ->where('positions.id', 3)
                ->whereIn('users.id', $userIds)->pluck('users.id');

            $closerUserIds = User::join('positions', 'positions.id', 'users.position_id')
                ->where('positions.id', 2)
                ->whereIn('users.id', $userIds)->pluck('users.id');
            foreach ($userIds as $userId) {
                $schedulesData = $this->getUserSchedules($startDate, $endDate, $userId, $office_id = null);
                $totalWorkedHours = (! empty($schedulesData[0]['weeklyTotals'][0]['totalWorkedHours']) ? $schedulesData[0]['weeklyTotals'][0]['totalWorkedHours'] : '00').(! empty($schedulesData[0]['weeklyTotals'][0]['totalWorkedMinutes']) ? ':'.$schedulesData[0]['weeklyTotals'][0]['totalWorkedMinutes'] : ':00').':00';
                if (! empty($schedulesData[0]['schedules'])) {
                    $today = date('Y-m-d');
                    $countIsLateFalse = count(array_filter($schedulesData[0]['schedules'], function ($schedule) {
                        return ! $schedule['isLate'] && $schedule['schedule_from'] <= date('Y-m-d');
                    }));
                } else {
                    $countIsLateFalse = 0;
                }
                $getLeadData = $this->getLeadData($startDate, $endDate, $userId, $office_id = 0);
                // return count($getLeadData);
                if (count($getLeadData) > 0) {
                    $countHired = count(array_filter($getLeadData->toArray(), function ($lead) {
                        return $lead['status'] === 'Hired';
                    }));

                    $countFollowUp = count(array_filter($getLeadData->toArray(), function ($lead) {
                        return $lead['status'] === 'Follow Up';
                    }));
                } else {
                    $countHired = 0;
                    $countFollowUp = 0;
                }
                // return $getLeadData;
                // $salesQuery = SalesMaster::with('salesMasterProcess')
                //     ->whereBetween('customer_signoff', [$startDate, $endDate]);
                $setterSalesPids = SaleMasterProcess::where('setter1_id', $userId)
                    ->orWhere('setter2_id', $userId)
                    ->pluck('pid');
                // dd($setterSalesPids);
                $closerSalesPids = SaleMasterProcess::where('closer1_id', $userId)
                    ->orWhere('closer2_id', $userId)
                    ->pluck('pid');
                // dd($closerSalesPids);
                $isSelfgenUser = User::where('self_gen_accounts', 1)
                    ->where('id', $userId)->first();
                $soldAs_setter = 0;
                $soldAs_closer = 0;
                $KWsoldAs_closer = 0;
                $KWsoldAs_setter = 0;
                if ($isSelfgenUser) {
                    $salesQuery = SalesMaster::with('salesMasterProcess')
                        ->whereBetween('customer_signoff', [$startDate, $endDate]);
                    $selfGenSalesPids = SaleMasterProcess::where('closer1_id', $userId)
                        ->orWhere('closer2_id', $userId)
                        ->orWhere('setter1_id', $userId)
                        ->orWhere('setter2_id', $userId)
                        ->pluck('pid');
                    $selfgenSalesQuery = $salesQuery->whereIn('pid', $selfGenSalesPids);
                    $selfGenSalesInstalled = $selfgenSalesQuery->where('date_cancelled', null)->where('m2_date', '!=', null)->count();
                    $selfGen_kw_installed = $selfgenSalesQuery->where('m2_date', '!=', null)->where('date_cancelled', null)->sum($KWsoldField);
                    $soldAs_selfgen = $selfgenSalesQuery->count();
                    $KWsoldAs_selfgen = $selfgenSalesQuery->sum($KWsoldField);

                } else {
                    // $selfGenSalesPids = [];
                    // $selfgenSalesQuery = $salesQuery;
                    $selfGenSalesInstalled = 0;
                    $selfGen_kw_installed = 0;
                    $soldAs_selfgen = 0;
                    $KWsoldAs_selfgen = 0;
                    if (count($closerSalesPids) > 0) {
                        $salesQuery = SalesMaster::with('salesMasterProcess')
                            ->whereBetween('customer_signoff', [$startDate, $endDate]);
                        $closerSalesQuery = $salesQuery->whereIn('pid', $closerSalesPids);
                        $closerSalesData = $closerSalesQuery->get();
                        $soldAs_closer = $closerSalesData->count();
                        $KWsoldAs_closer = $closerSalesData->sum($KWsoldField);
                        $closerSalesInstalled = $closerSalesData->where('date_cancelled', null)->where('m2_date', '!=', null)->count();
                        $closer_kw_installed = $closerSalesData->where('m2_date', '!=', null)->where('date_cancelled', null)->sum($KWsoldField);
                    }
                    if (count($setterSalesPids) > 0) {
                        $salesQuery = SalesMaster::with('salesMasterProcess')
                            ->whereBetween('customer_signoff', [$startDate, $endDate]);
                        $setterSalesQuery = $salesQuery->whereIn('pid', $setterSalesPids);
                        $setterSalesData = $setterSalesQuery->get();
                        $soldAs_setter = $setterSalesData->count();
                        $KWsoldAs_setter = $setterSalesData->sum($KWsoldField);
                        $setterSalesInstalled = $setterSalesData->where('date_cancelled', null)->where('m2_date', '!=', null)->count();
                        $setter_kw_installed = $setterSalesData->where('m2_date', '!=', null)->where('date_cancelled', null)->sum($KWsoldField);
                    }
                }

                // $closerSalesQuery = $salesQuery->whereIn('pid', $closerSalesPids);
                // $setterSalesQuery = $salesQuery->whereIn('pid', $setterSalesPids);
                // $selfGenSalesData = $selfgenSalesQuery->get();
                // $closerSalesData = $closerSalesQuery->get();
                // $setterSalesData = $setterSalesQuery->get();

                // dd(count($selfGenSalesData->where('date_cancelled', null)->where('m2_date', '!=', null)));
                // $closerSalesInstalled = $closerSalesData->where('date_cancelled', null)->where('m2_date', '!=', null)->count();
                // $setterSalesInstalled = $setterSalesData->where('date_cancelled', null)->where('m2_date', '!=', null)->count();
                // $selfGenSalesInstalled = $selfGenSalesData->where('date_cancelled', null)->where('m2_date', '!=', null)->count();
                // $closer_kw_installed = $closerSalesData->where('m2_date', '!=', null)->where('date_cancelled', null)->sum('kw');
                // $setter_kw_installed = $setterSalesData->where('m2_date', '!=', null)->where('date_cancelled', null)->sum('kw');
                // $selfGen_kw_installed = $selfGenSalesData->where('m2_date', '!=', null)->where('date_cancelled', null)->sum('kw');
                // return $setterSalesInstalled;
                $response[] = [
                    'id' => $userId,
                    'new_hireing_lead' => $countFollowUp,
                    'hired' => $countHired,
                    // "worked_hrs" => "00:00:00",//$totalWorkedHours,
                    // "days_on_time" => 0,//$countIsLateFalse, // Placeholder, replace with actual value
                    'worked_hrs' => $totalWorkedHours,
                    'days_on_time' => $countIsLateFalse,
                    'soldAs_setter' => $soldAs_setter,
                    'soldAs_closer' => $soldAs_closer,
                    'soldAs_selfgen' => $soldAs_selfgen,
                    'InstallAs_setter' => $setterSalesInstalled ?? 0,
                    'InstallAs_closer' => $closerSalesInstalled ?? 0,
                    'InstallAs_selfgen' => $selfGenSalesInstalled ?? 0,
                    'KWsoldAs_setter' => $KWsoldAs_setter,
                    'KWsoldAs_closer' => $KWsoldAs_closer,
                    'KWsoldAs_selfgen' => $KWsoldAs_selfgen,
                    'KWInstallAs_setter' => $setter_kw_installed ?? 0,
                    'KWInstallAs_closer' => $closer_kw_installed ?? 0,
                    'KWInstallAs_selfgen' => $selfGen_kw_installed ?? 0,
                    'Type' => 'UserId',
                ];
            }
            // return $response;

        }
        if ($request->has('offices') && ! empty($request->input('offices'))) {
            $office_ids = $request->input('offices');
            // return $office_ids;
            foreach ($office_ids as $office_id) {
                // return $office_id['userIds'];
                $schedulesData = $this->getUserSchedules($startDate, $endDate, $userId = null, $office_id['officeId']);
                $totalWorkedHours = (! empty($schedulesData[0]['totalWorkedHours']) ? $schedulesData[0]['totalWorkedHours'] : '00').(! empty($schedulesData[0]['totalWorkedMinutes']) ? ':'.$schedulesData[0]['totalWorkedMinutes'] : ':00').':00';
                if (! empty($schedulesData[0]['schedules'])) {
                    $countIsLateFalse = count(array_filter($schedulesData[0]['schedules'], function ($schedule) {
                        // return !$schedule['isLate'];
                        return ! $schedule['isLate'] && $schedule['schedule_from'] <= date('Y-m-d');
                    }));
                } else {
                    $countIsLateFalse = 0;
                }
                $getLeadData = $this->getLeadDataWithOfficeUser($startDate, $endDate, $office_id['userIds'], $office_id['officeId']);
                // /return $getLeadData;
                if (count($getLeadData) > 0) {
                    $countHired = count(array_filter($getLeadData->toArray(), function ($lead) {
                        return $lead['status'] === 'Hired';
                    }));

                    $countFollowUp = count(array_filter($getLeadData->toArray(), function ($lead) {
                        return $lead['status'] === 'Follow Up';
                    }));
                } else {
                    $countHired = 0;
                    $countFollowUp = 0;
                }
                // $userIds = User::where('office_id', $office_id)->pluck('id');
                $userIds = $office_id['userIds'];
                // $salesQuery = SalesMaster::with('salesMasterProcess')
                //          ->whereBetween('customer_signoff', [$startDate, $endDate]);
                // $isSelfgenUser = User::where('self_gen_accounts',1)
                //                 ->where('id',$userId)->first();
                $selfgenUserIds = User::where('self_gen_accounts', 1)->whereIn('id', $userIds)->pluck('id');
                $closerSalesPids = SaleMasterProcess::whereIn('closer1_id', $userIds)
                    ->orWhereIn('closer2_id', $userIds)
                    ->pluck('pid');
                // dd($selfgenUserIds);
                $setterSalesPids = SaleMasterProcess::WhereIn('setter1_id', $userIds)
                    ->orWhereIn('setter2_id', $userIds)
                    ->pluck('pid');
                $soldAs_setter = 0;
                $soldAs_closer = 0;
                $KWsoldAs_closer = 0;
                $KWsoldAs_setter = 0;
                if ($selfgenUserIds) {

                    $salesQuery = SalesMaster::with('salesMasterProcess')
                        ->whereBetween('customer_signoff', [$startDate, $endDate]);
                    $selfGenSalesPids = SaleMasterProcess::whereIn('closer1_id', $userIds)
                        ->orWhereIn('closer2_id', $userIds)
                        ->orWhereIn('setter1_id', $userIds)
                        ->orWhereIn('setter2_id', $userIds)
                        ->pluck('pid');
                    $selfgenSalesQuery = $salesQuery->whereIn('pid', $selfGenSalesPids);
                    $selfGenSalesInstalled = $selfgenSalesQuery->where('date_cancelled', null)->where('m2_date', '!=', null)->count();
                    $selfGen_kw_installed = $selfgenSalesQuery->where('m2_date', '!=', null)->where('date_cancelled', null)->sum($KWsoldField);
                    $soldAs_selfgen = $selfgenSalesQuery->count();
                    $KWsoldAs_selfgen = $selfgenSalesQuery->sum($KWsoldField);
                } else {
                    $selfGenSalesInstalled = 0;
                    $selfGen_kw_installed = 0;
                    $soldAs_selfgen = 0;
                    $KWsoldAs_selfgen = 0;
                    if (count($closerSalesPids) > 0) {
                        $salesQuery = SalesMaster::with('salesMasterProcess')
                            ->whereBetween('customer_signoff', [$startDate, $endDate]);
                        $closerSalesQuery = $salesQuery->whereIn('pid', $closerSalesPids);
                        $closerSalesData = $closerSalesQuery->get();
                        $soldAs_closer = $closerSalesData->count();
                        $KWsoldAs_closer = $closerSalesData->sum($KWsoldField);
                        $closerSalesInstalled = $closerSalesData->where('date_cancelled', null)->where('m2_date', '!=', null)->count();
                        $closer_kw_installed = $closerSalesData->where('m2_date', '!=', null)->where('date_cancelled', null)->sum($KWsoldField);
                    }
                    if (count($setterSalesPids) > 0) {
                        $salesQuery = SalesMaster::with('salesMasterProcess')
                            ->whereBetween('customer_signoff', [$startDate, $endDate]);
                        $setterSalesQuery = $salesQuery->whereIn('pid', $setterSalesPids);
                        $setterSalesData = $setterSalesQuery->get();
                        $soldAs_setter = $setterSalesData->count();
                        $KWsoldAs_setter = $setterSalesData->sum($KWsoldField);
                        $setterSalesInstalled = $setterSalesData->where('date_cancelled', null)->where('m2_date', '!=', null)->count();
                        $setter_kw_installed = $setterSalesData->where('m2_date', '!=', null)->where('date_cancelled', null)->sum($KWsoldField);
                    }
                }
                // $selfgenUserIds = User::where('self_gen_accounts',1)->whereIn('id',$userIds)->pluck('id');
                // $selfGenSalesPids = SaleMasterProcess::whereIn('closer1_id', $userIds)
                //     ->orWhereIn('closer2_id', $userIds)
                //     ->orWhereIn('setter1_id', $userIds)
                //     ->orWhereIn('setter2_id', $userIds)
                //     ->pluck('pid');
                // $closerSalesPids = SaleMasterProcess::whereIn('closer1_id', $userIds)
                //     ->orWhereIn('closer2_id', $userIds)
                //     ->pluck('pid');

                // $setterSalesPids = SaleMasterProcess::WhereIn('setter1_id', $userIds)
                // ->orWhereIn('setter2_id', $userIds)
                // ->pluck('pid');
                // $selfgenSalesQuery = $salesQuery->whereIn('pid', $selfGenSalesPids);
                // $closerSalesQuery = $salesQuery->whereIn('pid', $closerSalesPids);
                // $setterSalesQuery = $salesQuery->whereIn('pid', $setterSalesPids);
                // dd($closerSalesPids,$setterSalesPids);
                // $selfGenSalesData = $selfgenSalesQuery->get();
                // $closerSalesData = $closerSalesQuery->get();
                // $setterSalesData = $setterSalesQuery->get();

                // $selfGenSalesInstalled = $selfGenSalesData->where('date_cancelled', null)->where('m2_date', '!=', null)->count();
                // $closerSalesInstalled = $closerSalesData->where('date_cancelled', null)->where('m2_date', '!=', null)->count();
                // $setterSalesInstalled = $setterSalesData->where('date_cancelled', null)->where('m2_date', '!=', null)->count();

                // $selfGen_kw_installed = $selfGenSalesData->where('m2_date', '!=', null)->where('date_cancelled', null)->sum('kw');
                // $closer_kw_installed = $closerSalesData->where('m2_date', '!=', null)->where('date_cancelled', null)->sum('kw');
                // $setter_kw_installed = $setterSalesData->where('m2_date', '!=', null)->where('date_cancelled', null)->sum('kw');
                $response[] = [
                    'id' => $office_id['officeId'],
                    'new_hireing_lead' => $countFollowUp,
                    'hired' => $countHired,
                    // "worked_hrs" => "00:00:00",//$totalWorkedHours,
                    // "days_on_time" => 0,//$countIsLateFalse, // Placeholder, replace with actual value
                    'worked_hrs' => $totalWorkedHours,
                    'days_on_time' => $countIsLateFalse,
                    // "soldAs_setter" => $setterSalesData->count(),
                    // "soldAs_closer" => $closerSalesData->count(),
                    // "soldAs_selfgen" => $selfGenSalesData->count(),
                    // "InstallAs_setter" => $setterSalesInstalled,
                    // "InstallAs_closer" => $closerSalesInstalled,
                    // "InstallAs_selfgen" => $selfGenSalesInstalled,
                    // "KWsoldAs_setter" => $setterSalesData->sum('kw'),
                    // "KWsoldAs_closer" => $closerSalesData->sum('kw'),
                    // "KWsoldAs_selfgen" => $selfGenSalesData->sum('kw'),
                    // "KWInstallAs_setter" => $setter_kw_installed,
                    // "KWInstallAs_closer" => $closer_kw_installed,
                    // "KWInstallAs_selfgen" => $selfGen_kw_installed,
                    'soldAs_setter' => $soldAs_setter,
                    'soldAs_closer' => $soldAs_closer,
                    'soldAs_selfgen' => $soldAs_selfgen,
                    'InstallAs_setter' => $setterSalesInstalled ?? 0,
                    'InstallAs_closer' => $closerSalesInstalled ?? 0,
                    'InstallAs_selfgen' => $selfGenSalesInstalled ?? 0,
                    'KWsoldAs_setter' => $KWsoldAs_setter,
                    'KWsoldAs_closer' => $KWsoldAs_closer,
                    'KWsoldAs_selfgen' => $KWsoldAs_selfgen,
                    'KWInstallAs_setter' => $setter_kw_installed ?? 0,
                    'KWInstallAs_closer' => $closer_kw_installed ?? 0,
                    'KWInstallAs_selfgen' => $selfGen_kw_installed ?? 0,
                    'Type' => 'OfficeId',
                ];

            }
            // return $response;
        }

        return response()->json([
            'ApiName' => 'Arena Leaderboard Data API',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $response,
        ], 200);
    }

    private function getUserSchedules($startDate, $endDate, $userId, $office_id)
    {
        if (! empty($office_id)) {
            $userSchedulesData = UserSchedule::select(
                'users.id as user_id',
                'users.first_name',
                'users.middle_name',
                'users.last_name',
                'user_schedules.is_flexible',
                'user_schedules.is_repeat',
                'user_schedule_details.id',
                'user_schedule_details.lunch_duration',
                'user_schedule_details.schedule_from',
                'user_schedule_details.schedule_to',
                'user_schedule_details.work_days',
                'user_schedule_details.office_id',
                'user_schedule_details.user_attendance_id',
                'user_schedule_details.attendance_status',
                'user_schedule_details.is_flexible as is_flexible_flag',
            )
                ->join('users', 'user_schedules.user_id', '=', 'users.id')
                ->join('user_schedule_details', 'user_schedule_details.schedule_id', '=', 'user_schedules.id')
                ->whereBetween('user_schedule_details.schedule_from', [$startDate, $endDate])
                ->where('user_schedule_details.office_id', $office_id)
                ->orderBy('users.id')
                ->get();
        }
        if (! empty($userId)) {
            $userSchedulesData = UserSchedule::select(
                'users.id as user_id',
                'users.first_name',
                'users.middle_name',
                'users.last_name',
                'user_schedules.is_flexible',
                'user_schedules.is_repeat',
                'user_schedule_details.id',
                'user_schedule_details.lunch_duration',
                'user_schedule_details.schedule_from',
                'user_schedule_details.schedule_to',
                'user_schedule_details.work_days',
                'user_schedule_details.office_id',
                'user_schedule_details.user_attendance_id',
                'user_schedule_details.attendance_status',
                'user_schedule_details.is_flexible as is_flexible_flag',
            )
                ->join('users', 'user_schedules.user_id', '=', 'users.id')
                ->join('user_schedule_details', 'user_schedule_details.schedule_id', '=', 'user_schedules.id')
                ->whereBetween('user_schedule_details.schedule_from', [$startDate, $endDate])
                ->where('user_schedules.user_id', '=', $userId)
                ->orderBy('users.id')
                ->get();
        }
        // return $userSchedulesData;
        $formattedData = [];
        $sumHours = 0;
        $sumMinutes = 0;
        $sumSeconds = 0;
        foreach ($userSchedulesData as $schedule) {
            $getFinalizeStatusData = $this->getFinalizeStatus($schedule);
            // dd($getFinalizeStatusData);
            $dayName = Carbon::parse($schedule->schedule_from)->format('l');
            $dayNumber = Carbon::parse($schedule->schedule_from)->format('N');
            $timeDifference = $this->calculateTimeDifference($schedule->schedule_from, $schedule->schedule_to);
            $total_hours = $this->calculateTotalHours($schedule->schedule_from, $schedule->schedule_to);
            // dd($timeDifference);
            if (! isset($formattedData[$schedule->user_id])) {
                $formattedData[$schedule->user_id]['totalSchedulesHours'] = 0;
                $formattedData[$schedule->user_id]['totalSchedulesMinutes'] = 0;
                $formattedData[$schedule->user_id]['totalWorkedHours'] = 0;
                $formattedData[$schedule->user_id]['totalWorkedMinutes'] = 0;
                $formattedData[$schedule->user_id]['workedHours'] = '00:00:00';

            }
            $formattedData[$schedule->user_id]['totalSchedulesHours'] += $total_hours['hours'];
            $formattedData[$schedule->user_id]['totalSchedulesMinutes'] += $total_hours['minutes'];
            $schedule_from_date = Carbon::parse($schedule->schedule_from)->toDateString();
            $schedule_from_time = $this->getTimeFromDateTime($schedule->schedule_from);
            $is_available = false;
            if ($schedule_from_time == '00:00:00') {
                $is_available = true;
            }
            $req_approvals_pto = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                ->where('start_date', '<=', $schedule_from_date)
                ->where('end_date', '>=', $schedule_from_date)
                ->where('adjustment_type_id', 8)
                ->where('status', 'Approved')
                ->first();

            $req_approvals_leave = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                ->where('start_date', '<=', $schedule_from_date)
                ->where('end_date', '>=', $schedule_from_date)
                ->where('adjustment_type_id', 7)
                ->where('status', 'Approved')
                ->first();
            $user_attendence = UserAttendance::where('user_id', $schedule->user_id)
                ->where('date', $schedule_from_date)
                ->first();
            $user_checkin = null;
            $user_checkout = null;
            $is_present = false;
            $is_late = false;
            $calculatedWorkedHours = '00:00:00';
            $checkInTimeDifference = null;
            $user_attendence_status = false;
            $user_attendence_id = null;
            $lunchBreak = '00:00:00';
            $breakTime = '00:00:00';
            $req_approvals_data_id = null;
            if (! empty($user_attendence)) {
                $lunchBreak = isset($user_attendence->lunch_time) ? $user_attendence->lunch_time : null;
                $breakTime = isset($user_attendence->break_time) ? $user_attendence->break_time : null;
                $user_attendence_status = ($schedule->attendance_status == 1) ? true : false;
                $user_attendence_id = $user_attendence->id;
                $user_attendance_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                    ->whereDate('attendance_date', $schedule_from_date)
                    ->where('adjustment_id', '>', 0)
                    ->first();
                if ($user_attendance_obj) {
                    $get_request = ApprovalsAndRequest::find($user_attendance_obj->adjustment_id);
                    $user_checkin = isset($get_request) ? $get_request->clock_in : null;
                    $user_checkout = isset($get_request) ? $get_request->clock_out : null;
                    $is_present = true;
                    $req_approvals_data_id = isset($get_request) ? $get_request->id : null;
                    $lunchBreak = isset($get_request->lunch_adjustment) ? $get_request->lunch_adjustment : null;
                    $breakTime = isset($get_request->break_adjustment) ? $get_request->break_adjustment : null;
                    if (! is_null($lunchBreak)) {
                        $lunchBreak = gmdate('H:i:s', $lunchBreak * 60);
                    }
                    if (! is_null($breakTime)) {
                        $breakTime = gmdate('H:i:s', $breakTime * 60);
                    }
                } else {
                    $user_checkin_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                        ->whereDate('attendance_date', $schedule_from_date)
                        ->where('type', 'clock in')
                        ->first();
                    if (! empty($user_checkin_obj)) {
                        $is_present = true;
                        $user_checkin = $user_checkin_obj->attendance_date;
                        $user_checkout_obj = UserAttendanceDetail::where('user_attendance_id', $user_attendence->id)
                            ->whereDate('attendance_date', $schedule_from_date)
                            ->where('type', 'clock out')
                            ->first();
                        if (! empty($user_checkout_obj)) {
                            $user_checkout = $user_checkout_obj->attendance_date;
                        } else {
                            $user_checkout = null;
                        }
                    } else {
                        $user_checkin = null;
                        $user_checkout = null;
                    }

                }
                $checkInTimeDifference = $this->calculateTimeDifference($user_checkin, $user_checkout);
                $is_late = $this->compareDateTime($user_checkin, $schedule->schedule_from);
                $total_worked_hours = $this->calculateTotalHours($user_checkin, $user_checkout);
                $current_time = ! empty($user_attendence->current_time) ? $user_attendence->current_time : '00:00:00';
                $getSumWorkedHours = $this->getSumWorkedHours($current_time);
                $sumHours += $getSumWorkedHours['hours'];
                $sumMinutes += $getSumWorkedHours['minutes'];
                $sumSeconds += $getSumWorkedHours['seconds'];
                // echo $sumHours.'----'.$sumMinutes.'-----'.$sumSeconds.'------- '.$user_checkin->attendance_date;
                $calculatedWorkedHours = $this->convertToTimeFormat($sumHours, $sumMinutes, $sumSeconds);
                // echo '=====>  '.$calculatedWorkedHours.'<\n>';
                $formattedData[$schedule->user_id]['workedHours'] = $calculatedWorkedHours ?? '00:00:00';
                if (! empty($total_worked_hours) && ! is_null($total_worked_hours)) {
                    $formattedData[$schedule->user_id]['totalWorkedHours'] += isset($total_worked_hours) ? $total_worked_hours['hours'] : 0;
                    $formattedData[$schedule->user_id]['totalWorkedMinutes'] += isset($total_worked_hours) ? $total_worked_hours['minutes'] : 0;
                } else {
                    $formattedData[$schedule->user_id]['totalWorkedHours'] = 0;
                    $formattedData[$schedule->user_id]['totalWorkedMinutes'] = 0;
                }
            } else {
                $user_attendence_status = ($schedule->attendance_status == 1) ? true : false;
                $req_approvals_data = ApprovalsAndRequest::where('user_id', $schedule->user_id)
                    ->where('adjustment_date', '=', $schedule_from_date)
                    ->where('adjustment_type_id', 9)
                    ->where('status', 'Approved')
                    ->first();
                if (! empty($req_approvals_data)) {
                    $user_checkin = isset($req_approvals_data) ? $req_approvals_data->clock_in : null;
                    $user_checkout = isset($req_approvals_data) ? $req_approvals_data->clock_out : null;
                    $is_late = $this->compareDateTime($user_checkin, $schedule->schedule_from);
                    $is_present = true;
                    $lunchBreak = isset($req_approvals_data->lunch_adjustment) ? $req_approvals_data->lunch_adjustment : null;
                    $breakTime = isset($req_approvals_data->break_adjustment) ? $req_approvals_data->break_adjustment : null;
                    if (! is_null($lunchBreak)) {
                        $lunchBreak = gmdate('H:i:s', $lunchBreak * 60);
                    }
                    if (! is_null($breakTime)) {
                        $breakTime = gmdate('H:i:s', $breakTime * 60);
                    }
                    $req_approvals_data_id = $req_approvals_data->id;
                }
            }

            $formattedData[$schedule->user_id]['user_id'] = $schedule->user_id;
            $formattedData[$schedule->user_id]['user_data'] = ! empty($userData) ? $userData : null;
            $formattedData[$schedule->user_id]['is_flexible'] = $schedule->is_flexible;
            $formattedData[$schedule->user_id]['is_repeat'] = $schedule->is_repeat;
            $formattedData[$schedule->user_id]['user_name'] = $schedule?->first_name.' '.$schedule?->last_name;
            $formattedData[$schedule->user_id]['schedules'][] = [
                'user_schedule_details_id' => $schedule->id,
                'lunch_duration' => $schedule->lunch_duration,
                'schedule_from' => $schedule->schedule_from,
                'schedule_to' => $schedule->schedule_to,
                'work_days' => $dayNumber,
                'day_name' => $dayName,
                'is_available' => $is_available,
                'clock_hours' => $timeDifference,
                'is_flexible' => $schedule->is_flexible_flag,
                'checkPTO' => ! empty($req_approvals_pto) ? $req_approvals_pto->pto_per_day : false,
                'checkLeave' => ! empty($req_approvals_leave) ? true : false,
                'clockIn' => ! empty($user_checkin) ? $user_checkin : null,
                'clockOut' => ! empty($user_checkout) ? $user_checkout : null,
                'checkInClockHours' => ! empty($checkInTimeDifference) ? $checkInTimeDifference : null,
                'isPresent' => $is_present,
                'isLate' => $is_late,
                'user_attendence_status' => $user_attendence_status,
                'user_attendence_id' => $user_attendence_id,
                'user_attendence_approved_status' => $user_attendence_status,
                'payFequency' => $getFinalizeStatusData['frequency'],
                'finalizeStatus' => $getFinalizeStatusData['finalizeStatus'],
                'lunchBreak' => isset($lunchBreak) ? $lunchBreak : '00:00:00',
                'breakTime' => isset($breakTime) ? $breakTime : '00:00:00',
                'executeStatus' => $getFinalizeStatusData['executeStatus'],
            ];
            $weekNumber = Carbon::parse($schedule->schedule_from)->startOfWeek(Carbon::SUNDAY)->format('W'); // Get the week number
            $weekNumber2 = Carbon::parse($schedule->schedule_from)->weekOfMonth; // Get the week number of the month
            $weekStart = Carbon::parse($schedule->schedule_from)->startOfWeek()->toDateString();
            $weekEnd = Carbon::parse($schedule->schedule_from)->endOfWeek()->toDateString();
            if (! isset($formattedData[$schedule->user_id]['weeklyTotals'][$weekNumber])) {
                $formattedData[$schedule->user_id]['weeklyTotals'][$weekNumber] = [
                    'totalSchedulesHours' => 0,
                    'totalSchedulesMinutes' => 0,
                    'totalWorkedHours' => 0,
                    'totalWorkedMinutes' => 0,
                    'weekNumber' => $weekNumber,
                    'startWeek' => $weekStart,
                    'endWeek' => $weekEnd,
                    'user_id' => $userId,
                ];
            }
            $formattedData[$schedule->user_id]['weeklyTotals'][$weekNumber]['totalSchedulesHours'] += $total_hours['hours'];
            $formattedData[$schedule->user_id]['weeklyTotals'][$weekNumber]['totalSchedulesMinutes'] += $total_hours['minutes'];
            $formattedData[$schedule->user_id]['weeklyTotals'][$weekNumber]['totalWorkedHours'] = $total_worked_hours['hours'] ?? 0;
            $formattedData[$schedule->user_id]['weeklyTotals'][$weekNumber]['totalWorkedMinutes'] = $total_worked_hours['minutes'] ?? 0;
        }
        // return $formattedData;
        foreach ($formattedData as &$userData) {
            usort($userData['schedules'], function ($a, $b) {
                return $a['schedule_from'] <=> $b['schedule_from'];
            });

            // calculating total hours, schedules hours
            $totalHours = $userData['totalSchedulesHours'];
            $totalMinutes = $userData['totalSchedulesMinutes'];
            $userData['totalSchedulesHours'] = floor($totalHours + ($totalMinutes / 60));
            $userData['totalSchedulesMinutes'] = $totalMinutes % 60;

            $totalWHours = $userData['totalWorkedHours'];
            $totalWMinutes = $userData['totalWorkedMinutes'];
            $userData['totalWorkedHours'] = floor($totalWHours + ($totalWMinutes / 60));
            $userData['totalWorkedMinutes'] = $totalWMinutes % 60;
            // calculating weekly calculation
            $weeklyTotals = array_values($userData['weeklyTotals']);
            foreach ($weeklyTotals as &$weeklyData) {
                // echo '<pre>'; print_r($weeklyData);
                $totalWeeklyHours = $weeklyData['totalSchedulesHours'];
                $totalWeeklyMinutes = $weeklyData['totalSchedulesMinutes'];
                $weeklyData['totalSchedulesHours'] = floor($totalWeeklyHours + ($totalWeeklyMinutes / 60));
                $weeklyData['totalSchedulesMinutes'] = $totalWeeklyMinutes % 60;
                // echo $totalWeeklyHours."\n".$totalWeeklyMinutes."---".$weeklyData['totalSchedulesHours']."----".$weeklyData['totalSchedulesMinutes'];
                $totalWolyHours = $weeklyData['totalWorkedHours'];
                $totalWoMinutes = $weeklyData['totalWorkedMinutes'];
                $weeklyData['totalWorkedHours'] = floor($totalWolyHours + ($totalWoMinutes / 60));
                $weeklyData['totalWorkedMinutes'] = $totalWoMinutes % 60;
                // echo $totalWolyHours."\n".$totalWoMinutes."---".$weeklyData['totalWorkedHours']."----".$weeklyData['totalWorkedMinutes'];
                $getWeeklyWorkedHourse = $this->weeklyWorkedHourse($weeklyData['user_id'], $weeklyData['startWeek'], $weeklyData['endWeek']);
                $weeklyData['weeklyWorkedHours'] = $getWeeklyWorkedHourse ?? '00:00:00';
                // dd($getWeeklyWorkedHourse);
                $weeklyData['workedHours'] = $this->convertToTimeFormatWeekly($weeklyData['totalWorkedHours'], $weeklyData['totalWorkedMinutes']);
            }
            $userData['weeklyTotals'] = $weeklyTotals;
        }
        // Extract schedules from all users
        $schedules = array_column($formattedData, 'schedules');
        // dd($schedules);
        // Flatten the schedules array
        $flattenedSchedules = array_merge(...$schedules);

        // Extract the user_attendence_status values
        // $userAttendenceStatuses = array_column($flattenedSchedules, 'user_attendence_status');
        $userAttendenceStatuses = array_column($flattenedSchedules, 'user_attendence_approved_status');
        $checkUserAttendenceStatuses = in_array(0, $userAttendenceStatuses);

        // Prepare the response
        // return array_values($formattedData);
        $formattedDataArray = array_values($formattedData);
        $formattedData = $this->getWeeklyCalculationWorkedHous($formattedDataArray);

        return $formattedData;
    }

    public function getWeeklyCalculationWorkedHous($formattedDataArray)
    {
        // return $formattedDataArray ;
        // $formattedDataArray = [];
        if (count($formattedDataArray) > 0) {
            foreach ($formattedDataArray[0]['weeklyTotals'] as &$weeklyTotal) {
                $totalWorkedSeconds = 0;

                foreach ($formattedDataArray[0]['schedules'] as $schedule) {
                    $scheduleDate = Carbon::parse($schedule['schedule_from'])->format('Y-m-d');

                    // Check if the schedule date is within the current weekly total's date range
                    if ($scheduleDate >= $weeklyTotal['startWeek'] && $scheduleDate <= $weeklyTotal['endWeek']) {

                        if ($schedule['clockIn'] && $schedule['clockOut']) {
                            $clockIn = Carbon::parse($schedule['clockIn']);
                            $clockOut = Carbon::parse($schedule['clockOut']);

                            // Calculate worked time in seconds
                            $workedSeconds = $clockOut->diffInSeconds($clockIn);

                            // Subtract lunch and break time in seconds
                            $lunchBreakSeconds = Carbon::parse($schedule['lunchBreak'])->diffInSeconds(Carbon::parse('00:00:00'));
                            $breakTimeSeconds = Carbon::parse($schedule['breakTime'])->diffInSeconds(Carbon::parse('00:00:00'));

                            $workedSeconds -= ($lunchBreakSeconds + $breakTimeSeconds);

                            // Add to total
                            $totalWorkedSeconds += $workedSeconds;
                        }
                    }
                }

                // Convert total seconds to hours, minutes, and seconds
                $weeklyTotal['totalWorkedHours'] = floor($totalWorkedSeconds / 3600);
                $weeklyTotal['totalWorkedMinutes'] = floor(($totalWorkedSeconds % 3600) / 60);
                $weeklyTotal['weeklyWorkedHours'] = gmdate('H:i:s', $totalWorkedSeconds);
            }
        }

        // print_r($formattedDataArray);
        return $formattedDataArray;
    }

    public function calculateTimeDifference($clockIn, $clockOut)
    {
        // Parse the clock_in and clock_out times using Carbon
        $start = Carbon::parse($clockIn);
        $end = Carbon::parse($clockOut);

        // Calculate the difference
        $differenceInMinutes = $start->diffInMinutes($end);

        // Convert the difference to hours and minutes
        $hours = floor($differenceInMinutes / 60);
        $minutes = $differenceInMinutes % 60;

        // Return the result in a human-readable format
        // return sprintf("%d hours and %d minutes", $hours, $minutes);
        return ['hours' => $hours, 'minutes' => $minutes];
        // return $hours.' '.$minutes;
    }

    public function calculateTotalHours($startDatetime, $endDatetime)
    {
        // Parse the start and end datetimes using Carbon
        if (! empty($startDatetime) && ! empty($endDatetime)) {
            $start = Carbon::parse($startDatetime);
            $end = Carbon::parse($endDatetime);

            // Calculate the difference in minutes
            $differenceInMinutes = $start->diffInMinutes($end);

            // Convert the difference to hours and minutes
            $hours = floor($differenceInMinutes / 60);
            $minutes = $differenceInMinutes % 60;

            // Return the result as an array
            return ['hours' => $hours, 'minutes' => $minutes];
        } else {
            return ['hours' => 0, 'minutes' => 0];
        }
    }

    private function getTimeFromDateTime($datetime)
    {
        // Parse the datetime string using Carbon
        $date = Carbon::parse($datetime);

        // Extract the time part
        $time = $date->format('H:i:s'); // This will give you the time in 'HH:MM:SS' format

        return $time;
    }

    public function getFinalizeStatus($schedule)
    {
        // dd($schedule->user_id);
        $userId = $schedule->user_id;
        $userData = User::findOrFail($userId);
        $schedule_from_date = Carbon::parse($schedule->schedule_from)->toDateString();
        // $schedule_from_date = "2024-07-22";
        $positionId = $userData->position_id;
        $sub_positionId = $userData->sub_position_id;
        // dd($positionId,$userId, $sub_positionId);
        // $frequencyTypeData  = null;
        if (! empty($sub_positionId)) {
            $pay_frequency_id = PositionPayFrequency::where('position_id', $sub_positionId)->select('frequency_type_id')->first();
            if (! empty($pay_frequency_id)) {
                $frequencyTypeData = FrequencyType::find($pay_frequency_id->frequency_type_id);
            }
        } else {
            $pay_frequency_id2 = PositionPayFrequency::where('position_id', $positionId)->select('frequency_type_id')->first();
            // dd($pay_frequency_id2->frequency_type_id);
            if (! empty($pay_frequency_id2)) {
                $frequencyTypeData = FrequencyType::find($pay_frequency_id2->frequency_type_id);
            }
        }
        $data['frequency'] = $frequencyTypeData->name ?? null;
        $query = Payroll::with('usersdata', 'positionDetail')
            ->where('status', '=', 2)
            ->where('user_id', $userId)
            ->whereDate('pay_period_from', '<=', $schedule_from_date)
            ->whereDate('pay_period_to', '>=', $schedule_from_date)
            ->first();
        // dd($query);
        $payrollHistory = PayrollHistory::where('status', '=', 3)
            ->where('user_id', $userId)
            ->whereDate('pay_period_from', '<=', $schedule_from_date)
            ->whereDate('pay_period_to', '>=', $schedule_from_date)
            ->first();
        $finalizeStatus = ! empty($query) ? true : false;
        $executeStatus = ! empty($payrollHistory) ? true : false;
        if ($finalizeStatus) {
            $data['finalizeStatus'] = true;
        } else {
            $data['finalizeStatus'] = $executeStatus;
        }

        // $data['finalizeStatus']   = $finalizeStatus;
        $data['executeStatus'] = $executeStatus;

        return $data;

    }

    public function compareDateTime($datetime1, $datetime2)
    {
        // Parse the datetime strings using Carbon
        $date1 = Carbon::parse($datetime1);
        $date2 = Carbon::parse($datetime2);
        $date2 = $this->getTimeFromDateTime($date2);
        // Check if $datetime1 is greater than $datetime2
        if ($date2 != '00:00:00') {
            if ($date1->gt($date2)) {
                return true;
            }
        }

        return false;
    }

    public function getSumWorkedHours($time)
    {
        $time1 = Carbon::createFromFormat('H:i:s', $time);
        $totalSeconds = $time1->secondsSinceMidnight();
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        return ['hours' => $hours, 'minutes' => $minutes, 'seconds' => $seconds];
    }

    private function convertToTimeFormat($hours, $minutes, $seconds)
    {
        // Normalize the time values
        $totalSeconds = ($hours * 3600) + ($minutes * 60) + $seconds;
        $normalizedHours = floor($totalSeconds / 3600);
        $totalSeconds %= 3600;
        $normalizedMinutes = floor($totalSeconds / 60);
        $normalizedSeconds = $totalSeconds % 60;

        // Create a Carbon instance and format it to H:i:s
        if ($hours >= 100) {
            $formattedTime = sprintf('%03d:%02d:%02d', $normalizedHours, $normalizedMinutes, $normalizedSeconds);
        } else {
            $formattedTime = sprintf('%02d:%02d:%02d', $normalizedHours, $normalizedMinutes, $normalizedSeconds);
        }

        return $formattedTime;
    }

    private function weeklyWorkedHourse($user_id, $startWeek, $endWeek)
    {
        // dd($user_id, $startWeek, $endWeek);
        $user_attendence = UserAttendance::where('user_id', $user_id)
            ->whereBetween('date', [$startWeek, $endWeek])
            ->get();
        $sumHours = 0;
        $sumMinutes = 0;
        $sumSeconds = 0;
        $calculatedWorkedHours = '00:00:00';
        $current_time = '00:00:00';
        if (! empty($user_attendence)) {
            foreach ($user_attendence as $attendence) {
                // echo '<pre>';print_r($attendence);
                $current_time = $attendence->current_time ?? '00:00:00';
                $getSumWorkedHours = $this->getSumWorkedHours($current_time);
                // echo '<pre>'; print_r($getSumWorkedHours);
                $sumHours += $getSumWorkedHours['hours'];
                $sumMinutes += $getSumWorkedHours['minutes'];
                $sumSeconds += $getSumWorkedHours['seconds'];
            }
        }
        $calculatedWorkedHours = $this->convertToTimeFormat($sumHours, $sumMinutes, $sumSeconds);

        return $calculatedWorkedHours;

    }

    private function convertToTimeFormatWeekly($hours, $minutes, $seconds = 0)
    {
        $totalMinutes = ($hours * 60) + $minutes + ($seconds / 60);
        $formattedHours = floor($totalMinutes / 60);
        $formattedMinutes = $totalMinutes % 60;

        return sprintf('%02d:%02d:%02d', $formattedHours, $formattedMinutes, $seconds);
    }

    private function getLeadData($startDate, $endDate, $userId, $office_id)
    {
        $lead = $this->lead->with('recruiter', 'reportingManager', 'state', 'comment')->where('type', 'lead');
        if (! empty($office_id)) {
            $userIds = User::where('office_id', $office_id)->pluck('id');
        } else {
            $userIds = [$userId];
        }
        if (! empty($startDate) && ! empty($endDate)) {
            $lead = $lead->whereBetween('created_at', [$startDate, $endDate]);
        }
        // return $userIds;

        $orderBy = 'desc';

        // $superAdmin = Auth::user()->is_super_admin;
        // $user_id = Auth::user()->id;
        // $positionId = Auth::user()->position_id;
        // $recruiterId = Auth::user()->recruiter_id;

        $authUser = User::find(1);
        $superAdmin = $authUser->is_super_admin;
        $user_id = $authUser->id;
        $positionId = $authUser->position_id;
        $recruiterId = $authUser->recruiter_id;

        $lead = $lead->with('recruiter', 'reportingManager', 'state', 'pipelineleadstatus');
        // $data = $lead->whereIn('recruiter_id' , $csid)->where('type','lead')->where('status','!=','Hired')->Orwhere('recruiter_id',$user_id)->where('type','lead')->where('status','!=','Hired')->orderBy('id',$orderBy)->paginate($perpage);
        $data = $lead->where('type', 'lead')->whereIn('recruiter_id', $userIds)->where('type', 'lead')->orderBy('id', $orderBy)->get();

        // end lead display listing  by nikhil
        return $data;

    }

    private function getLeadDataWithOfficeUser($startDate, $endDate, $userId, $office_id)
    {
        $lead = $this->lead->with('recruiter', 'reportingManager', 'state', 'comment', 'pipelineleadstatus')->where('type', 'lead');
        if (! empty($office_id) && ! empty($userId)) {
            // $userIds = User::where('office_id', $office_id)->pluck('id');
            $userIds = $userId;
        }
        if (! empty($startDate) && ! empty($endDate)) {
            $lead = $lead->whereBetween('created_at', [$startDate, $endDate]);
        }
        // return $userIds;

        $orderBy = 'desc';

        // $superAdmin = Auth::user()->is_super_admin;
        // $user_id = Auth::user()->id;
        // $positionId = Auth::user()->position_id;
        // $recruiterId = Auth::user()->recruiter_id;
        $authUser = User::find(1);
        $superAdmin = $authUser->is_super_admin;
        $user_id = $authUser->id;
        $positionId = $authUser->position_id;
        $recruiterId = $authUser->recruiter_id;

        // $lead = $lead->with('recruiter','reportingManager','state', 'pipelineleadstatus');
        // $data = $lead->whereIn('recruiter_id' , $csid)->where('type','lead')->where('status','!=','Hired')->Orwhere('recruiter_id',$user_id)->where('type','lead')->where('status','!=','Hired')->orderBy('id',$orderBy)->paginate($perpage);
        $data = $lead->whereIn('recruiter_id', $userIds)->orderBy('id', $orderBy)->get();

        // end lead display listing  by nikhil
        return $data;

    }

    public function arenaLeaderboardTeamData(Request $request)
    {
        // Validate filter input
        // return $request->all();
        if ($request->has('filter') && $request->input('filter')) {
            $filterValue = $request->input('filter');
            $filterDate = $this->getFilterDate($filterValue);

            if (! empty($filterDate['startDate']) && ! empty($filterDate['endDate'])) {
                $startDate = $filterDate['startDate'];
                $endDate = $filterDate['endDate'];
            } elseif ($filterValue == 'custom' && $request->input('start_date') && $request->input('end_date')) {
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
            } else {
                return response()->json([
                    'ApiName' => 'Arena Leaderboard API Data',
                    'status' => false,
                    'message' => 'Error. Something went wrong.',
                    'data' => [],
                ], 400);
            }
        }
        $response = [];
        if ($request->has('offices') && ! empty($request->input('offices'))) {

            $office_ids = $request->input('offices');
            foreach ($office_ids as $office) {

                $officeId = $office['officeId'];
                $schedulesData = $this->getUserSchedules($startDate, $endDate, $userId = null, $officeId);
                $totalWorkedHours = (! empty($schedulesData[0]['totalWorkedHours']) ? $schedulesData[0]['totalWorkedHours'] : '00').(! empty($schedulesData[0]['totalWorkedMinutes']) ? ':'.$schedulesData[0]['totalWorkedMinutes'] : ':00').':00';
                if (! empty($schedulesData[0]['schedules'])) {
                    $countIsLateFalse = count(array_filter($schedulesData[0]['schedules'], function ($schedule) {
                        return ! $schedule['isLate'] && $schedule['schedule_from'] <= date('Y-m-d');
                    }));
                } else {
                    $countIsLateFalse = 0;
                }
                $getLeadData = $this->getLeadData($startDate, $endDate, $userId = 0, $officeId);
                if (count($getLeadData) > 0) {
                    $countHired = count(array_filter($getLeadData->toArray(), function ($lead) {
                        return $lead['status'] === 'Hired';
                    }));

                    $countFollowUp = count(array_filter($getLeadData->toArray(), function ($lead) {
                        return $lead['status'] === 'Follow Up';
                    }));
                } else {
                    $countHired = 0;
                    $countFollowUp = 0;
                }

                $userIds = $office['userIds'];

                $soldAs_setter = 0;
                $soldAs_closer = 0;
                $soldAs_selfgen = 0;
                $InstallAs_setter = 0;
                $InstallAs_closer = 0;
                $InstallAs_selfgen = 0;
                $KWsoldAs_setter = 0;
                $KWsoldAs_closer = 0;
                $KWsoldAs_selfgen = 0;
                $KWInstallAs_setter = 0;
                $KWInstallAs_closer = 0;
                $KWInstallAs_selfgen = 0;

                foreach ($userIds as $userId) {
                    $points = $this->getArenaLeaderboardUserPoints($userId, $startDate, $endDate);
                    $soldAs_setter += $points['soldAs_setter'];
                    $soldAs_closer += $points['soldAs_closer'];
                    $soldAs_selfgen += $points['soldAs_selfgen'];
                    $InstallAs_setter += $points['InstallAs_setter'];
                    $InstallAs_closer += $points['InstallAs_closer'];
                    $InstallAs_selfgen += $points['InstallAs_selfgen'];
                    $KWsoldAs_setter += $points['KWsoldAs_setter'];
                    $KWsoldAs_closer += $points['KWsoldAs_closer'];
                    $KWsoldAs_selfgen += $points['KWsoldAs_selfgen'];
                    $KWInstallAs_setter += $points['KWInstallAs_setter'];
                    $KWInstallAs_closer += $points['KWInstallAs_closer'];
                    $KWInstallAs_selfgen += $points['KWInstallAs_selfgen'];
                }

                $response[] = [
                    'id' => $officeId,
                    'new_hireing_lead' => $countFollowUp,
                    'hired' => $countHired,
                    'worked_hrs' => $totalWorkedHours,
                    'days_on_time' => $countIsLateFalse,

                    'soldAs_setter' => $soldAs_setter,
                    'soldAs_closer' => $soldAs_closer,
                    'soldAs_selfgen' => $soldAs_selfgen,
                    'InstallAs_setter' => $InstallAs_setter,
                    'InstallAs_closer' => $InstallAs_closer,
                    'InstallAs_selfgen' => $InstallAs_selfgen,
                    'KWsoldAs_setter' => $KWsoldAs_setter,
                    'KWsoldAs_closer' => $KWsoldAs_closer,
                    'KWsoldAs_selfgen' => $KWsoldAs_selfgen,
                    'KWInstallAs_setter' => $KWInstallAs_setter,
                    'KWInstallAs_closer' => $KWInstallAs_closer,
                    'KWInstallAs_selfgen' => $KWInstallAs_selfgen,

                    'Type' => 'OfficeId',
                ];

            }
        }

        return response()->json([
            'ApiName' => 'Arena Leaderboard Data API',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $response,
        ], 200);
    }

    public function getArenaLeaderboardUserPoints($userId, $startDate, $endDate)
    {

        $setterSalesPids = SalesMaster::where(function ($query) use ($userId) {
            $query->where('setter1_id', $userId)
                ->orWhere('setter2_id', $userId);
        })
            ->where('customer_signoff', '>=', $startDate)
            ->where('customer_signoff', '<=', $endDate)
            ->pluck('pid');

        $closerSalesPids = SalesMaster::where(function ($query) use ($userId) {
            $query->where('closer1_id', $userId)
                ->orWhere('closer2_id', $userId);
        })
            ->where('customer_signoff', '>=', $startDate)
            ->where('customer_signoff', '<=', $endDate)
            ->pluck('pid');

        $isSelfgenUser = User::where('self_gen_accounts', 1)->where('id', $userId)->first();

        if ($isSelfgenUser) {

            $selfGenSalesPids = SalesMaster::where(function ($query) use ($userId) {
                $query->where('closer1_id', $userId)
                    ->orWhere('closer2_id', $userId)
                    ->orWhere('setter1_id', $userId)
                    ->orWhere('setter2_id', $userId);
            })
                ->where('customer_signoff', '>=', $startDate)
                ->where('customer_signoff', '<=', $endDate)
                ->pluck('pid');

        }

        if ($isSelfgenUser) {

            $salesQuery = SalesMaster::with(['salesMasterProcess', 'salesProductMaster'])
                ->whereBetween('customer_signoff', [$startDate, $endDate])
                ->whereIn('pid', $selfGenSalesPids);

            $selfGenSalesInstalled = (clone $salesQuery)
                ->whereHas('salesProductMaster', function ($query) use ($startDate, $endDate) {
                    $query->where('type', 'm2');
                    $query->whereBetween('milestone_date', [$startDate, $endDate]);
                })
                ->count();

            $selfGen_kw_installed = (clone $salesQuery)
                ->whereHas('salesProductMaster', function ($query) use ($startDate, $endDate) {
                    $query->where('type', 'm2');
                    $query->whereBetween('milestone_date', [$startDate, $endDate]);
                })
                ->sum('kw');

            $soldAs_selfgen = (clone $salesQuery)->count();
            $KWsoldAs_selfgen = (clone $salesQuery)->sum('kw');

        } else {

            if (count($setterSalesPids) > 0) {

                $salesQuery = SalesMaster::with(['salesMasterProcess', 'salesProductMaster'])
                    ->whereBetween('customer_signoff', [$startDate, $endDate]);

                $setterSalesQuery = $salesQuery->whereIn('pid', $setterSalesPids);

                $soldAs_setter = $setterSalesQuery->count();
                $KWsoldAs_setter = $setterSalesQuery->sum('kw');

                // KW
                $salesQuery = SalesMaster::with(['salesMasterProcess', 'salesProductMaster'])
                    ->whereHas('salesProductMaster', function ($query) use ($startDate, $endDate) {
                        $query->where('type', 'm2');
                        $query->whereBetween('milestone_date', [$startDate, $endDate]);
                    });
                $setterSalesQuery = $salesQuery->whereIn('pid', $setterSalesPids)->whereNull('date_cancelled');
                $setterSalesInstalled = $setterSalesQuery->count();
                $setter_kw_installed = $setterSalesQuery->clone()->sum('kw');

            }

            if (count($closerSalesPids) > 0) {

                $salesQuery = SalesMaster::with(['salesMasterProcess', 'salesProductMaster'])
                    ->whereBetween('customer_signoff', [$startDate, $endDate]);

                // Apply the PID filter and retrieve the data
                $closerSalesQuery = $salesQuery->whereIn('pid', $closerSalesPids);

                // Get the total count and sum of 'kw' for all closer sales
                $soldAs_closer = $closerSalesQuery->count();
                $KWsoldAs_closer = $closerSalesQuery->sum('kw');

                // KW

                $salesQuery = SalesMaster::with(['salesMasterProcess', 'salesProductMaster'])
                    ->whereHas('salesProductMaster', function ($query) use ($startDate, $endDate) {
                        $query->where('type', 'm2');
                        $query->whereBetween('milestone_date', [$startDate, $endDate]);
                    });
                $closerSalesQuery = $salesQuery->whereIn('pid', $closerSalesPids)->whereNull('date_cancelled');

                $closerSalesInstalled = $closerSalesQuery->count();

                $closer_kw_installed = $closerSalesQuery->clone()->sum('kw');

            }

        }

        return [

            'soldAs_setter' => $soldAs_setter ?? 0,
            'soldAs_closer' => $soldAs_closer ?? 0,
            'soldAs_selfgen' => $soldAs_selfgen ?? 0,
            'InstallAs_setter' => $setterSalesInstalled ?? 0,
            'InstallAs_closer' => $closerSalesInstalled ?? 0,
            'InstallAs_selfgen' => $selfGenSalesInstalled ?? 0,
            'KWsoldAs_setter' => $KWsoldAs_setter ?? 0,
            'KWsoldAs_closer' => $KWsoldAs_closer ?? 0,
            'KWsoldAs_selfgen' => $KWsoldAs_selfgen ?? 0,
            'KWInstallAs_setter' => $setter_kw_installed ?? 0,
            'KWInstallAs_closer' => $closer_kw_installed ?? 0,
            'KWInstallAs_selfgen' => $selfGen_kw_installed ?? 0,

        ];
    }
}
