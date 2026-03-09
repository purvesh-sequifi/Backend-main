<?php

namespace App\Http\Controllers\API\Sales;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserOverrides;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MyOverridesController extends Controller
{
    public function getOverrides(Request $request)
    {

        $filterDataDateWise = $request->input('filter');
        if ($filterDataDateWise == 'this_week') {
            $currentDate = \Carbon\Carbon::now();
            $startDate = date('Y-m-d', strtotime(now()->subDays($currentDate->dayOfWeek)));
            $endDate = date('Y-m-d', strtotime(now()));

        } elseif ($filterDataDateWise == 'last_week') {
            $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
            $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
            $startDate = date('Y-m-d', strtotime($startOfLastWeek));
            $endDate = date('Y-m-d', strtotime($endOfLastWeek));

        } elseif ($filterDataDateWise == 'this_month') {
            $month = \Carbon\Carbon::now()->daysInMonth;
            $startOfLastWeek = Carbon::now()->subDays($month);
            $endOfLastWeek = Carbon::now();
            $startDate = date('Y-m-d', strtotime($startOfLastWeek));
            $endDate = date('Y-m-d', strtotime($endOfLastWeek));

        } elseif ($filterDataDateWise == 'last_month') {
            $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(0)));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(30)));

        } elseif ($filterDataDateWise == 'this_quarter') {

            $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
            $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(30)));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

        } elseif ($filterDataDateWise == 'last_quarter') {

            $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
            $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));

        } elseif ($filterDataDateWise == 'this_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

        } elseif ($filterDataDateWise == 'last_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));

        } elseif ($filterDataDateWise == 'custom') {
            $startDate = $filterDataDateWise = $request->input('start_date');
            $endDate = $filterDataDateWise = $request->input('end_date');

        }
        $startDate = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
        $endDate = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();

        $uid = auth()->user()->id;
        $officeCount = UserOverrides::where('user_id', $uid)->where('type', 'Office')->whereBetween('created_at', [$startDate, $endDate])->where('status', 0)->count();
        $directCount = UserOverrides::where('user_id', $uid)->where('type', 'Direct')->whereBetween('created_at', [$startDate, $endDate])->where('status', 0)->count();
        $inDirectCount = UserOverrides::where('user_id', $uid)->where('type', 'Indirect')->whereBetween('created_at', [$startDate, $endDate])->where('status', 0)->count();
        $Manual = UserOverrides::where('user_id', $uid)->where('type', 'Manual')->whereBetween('created_at', [$startDate, $endDate])->where('status', 0)->count();

        $office = UserOverrides::with('userInfo')->where('user_id', $uid)->where('type', 'Office')->whereBetween('created_at', [$startDate, $endDate])->where('status', 0);
        if ($request->has('office_search') && ! empty($request->input('office_search'))) {
            $office->where(function ($query) use ($request) {
                return $query->where('kw', 'LIKE', '%'.$request->input('office_search').'%')
                    ->orWhere('amount', 'LIKE', '%'.$request->input('office_search').'%')
                    ->orWhere('sale_user_id', 'LIKE', '%'.$request->input('office_search').'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->input('office_search').'%')
                    ->orWhereHas('userInfo', function ($query) use ($request) {
                        $query->where('first_name', 'like', '%'.$request->input('office_search').'%');
                    });
            });
        }
        $office = $office->groupBy('sale_user_id')->get();

        $office->transform(function ($office) use ($startDate, $endDate) {
            $salesUser = User::where('id', $office->sale_user_id)->first();
            $installations = UserOverrides::where('sale_user_id', $office->sale_user_id)->where('type', 'Office')->whereBetween('created_at', [$startDate, $endDate])->groupBy('sale_user_id')->where('status', 0)->count();
            $pid = UserOverrides::where('sale_user_id', $office->sale_user_id)->where('type', 'Office')->whereBetween('created_at', [$startDate, $endDate])->where('status', 0)->pluck('pid');
            $salesKw = UserOverrides::whereIn('pid', $pid)->where('type', 'Office')->whereBetween('created_at', [$startDate, $endDate])->where('status', 0)->sum('kw');
            $salesAmount = UserOverrides::whereIn('pid', $pid)->where('type', 'Office')->whereBetween('created_at', [$startDate, $endDate])->where('status', 0)->sum('amount');
            $amount = isset($salesUser->office_overrides_amount) ? $salesUser->office_overrides_amount : null;
            $kw = isset($salesUser->office_overrides_type) ? $salesUser->office_overrides_type : null;

            return [
                'id' => isset($office->id) ? $office->id : null,
                'name' => isset($salesUser->first_name) ? $salesUser->first_name : null,
                'image' => isset($salesUser->image) ? $salesUser->image : null,
                'overrides' => $amount.'/'.$kw,
                'installation' => $installations,
                'total_kw' => isset($salesKw) ? $salesKw : null,
                'earnings' => isset($salesAmount) ? $salesAmount : null,
            ];
        });

        $direct = UserOverrides::with('userInfo')->where('user_id', $uid)->where('type', 'Direct')->whereBetween('created_at', [$startDate, $endDate])->where('status', 0);
        if ($request->has('direct_search') && ! empty($request->input('direct_search'))) {
            $direct->where(function ($query) use ($request) {
                return $query->where('kw', 'LIKE', '%'.$request->input('direct_search').'%')
                    ->orWhere('amount', 'LIKE', '%'.$request->input('direct_search').'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->input('direct_search').'%')
                    ->orWhere('sale_user_id', 'LIKE', '%'.$request->input('direct_search').'%')
                    ->orWhereHas('userInfo', function ($query) use ($request) {
                        $query->where('first_name', 'like', '%'.$request->input('direct_search').'%');
                    });
            });
        }
        $directs = $direct->groupBy('sale_user_id')->get();

        $directs->transform(function ($directs) use ($startDate, $endDate) {
            $salesUser = User::where('id', $directs->sale_user_id)->first();
            $installations = UserOverrides::where('sale_user_id', $directs->sale_user_id)->where('type', 'Direct')->whereBetween('created_at', [$startDate, $endDate])->groupBy('sale_user_id')->where('status', 0)->count();
            $pid = UserOverrides::where('sale_user_id', $directs->sale_user_id)->whereBetween('created_at', [$startDate, $endDate])->where('type', 'Direct')->where('status', 0)->pluck('pid');
            $salesKw = UserOverrides::whereIn('pid', $pid)->where('type', 'Direct')->whereBetween('created_at', [$startDate, $endDate])->where('status', 0)->sum('kw');
            $salesAmount = UserOverrides::whereIn('pid', $pid)->where('type', 'Direct')->whereBetween('created_at', [$startDate, $endDate])->where('status', 0)->sum('amount');
            $amount = isset($salesUser->direct_overrides_amount) ? $salesUser->direct_overrides_amount : null;
            $kw = isset($salesUser->direct_overrides_type) ? $salesUser->direct_overrides_type : null;

            return [
                'id' => isset($directs->id) ? $directs->id : null,
                'name' => isset($salesUser->first_name) ? $salesUser->first_name : null,
                'image' => isset($salesUser->image) ? $salesUser->image : null,
                'overrides' => $amount.'/'.$kw,
                'installation' => $installations,
                'total_kw' => isset($salesKw) ? $salesKw : null,
                'earnings' => isset($salesAmount) ? $salesAmount : null,
            ];
        });
        // return $uid;

        $inDirect = UserOverrides::with('userInfo')->where('user_id', $uid)->where('type', 'Indirect')->whereBetween('created_at', [$startDate, $endDate])->where('status', 0);

        if ($request->has('inDirect_search') && ! empty($request->input('inDirect_search'))) {
            $inDirect->where(function ($query) use ($request) {
                return $query->where('kw', 'LIKE', '%'.$request->input('inDirect_search').'%')
                    ->orWhere('amount', 'LIKE', '%'.$request->input('inDirect_search').'%')
                    ->orWhere('sale_user_id', 'LIKE', '%'.$request->input('inDirect_search').'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->input('inDirect_search').'%')
                    ->orWhereHas('userInfo', function ($query) use ($request) {
                        $query->where('first_name', 'like', '%'.$request->input('inDirect_search').'%');
                    });
            });
        }

        $inDirect = $inDirect->groupBy('sale_user_id')->get();

        $inDirect->transform(function ($inDirect) use ($startDate, $endDate) {
            $salesUser = User::where('id', $inDirect->sale_user_id)->first();
            $installations = UserOverrides::where('sale_user_id', $inDirect->sale_user_id)->where('type', 'Indirect')->whereBetween('created_at', [$startDate, $endDate])->groupBy('sale_user_id')->where('status', 0)->count();
            $pid = UserOverrides::where('sale_user_id', $inDirect->sale_user_id)->whereBetween('created_at', [$startDate, $endDate])->where('type', 'Indirect')->where('status', 0)->pluck('pid');
            $salesKw = UserOverrides::whereIn('pid', $pid)->where('type', 'Indirect')->whereBetween('created_at', [$startDate, $endDate])->where('status', 0)->sum('kw');
            $salesAmount = UserOverrides::whereIn('pid', $pid)->where('type', 'Indirect')->whereBetween('created_at', [$startDate, $endDate])->where('status', 0)->sum('amount');
            $amount = isset($salesUser->indirect_overrides_amount) ? $salesUser->indirect_overrides_amount : null;
            $kw = isset($salesUser->indirect_overrides_type) ? $salesUser->indirect_overrides_type : null;

            return [
                'id' => isset($inDirect->id) ? $inDirect->id : null,
                'name' => isset($salesUser->first_name) ? $salesUser->first_name : null,
                'image' => isset($salesUser->image) ? $salesUser->image : null,
                'overrides' => $amount.'/'.$kw,
                'installation' => $installations,
                'total_kw' => isset($salesKw) ? $salesKw : null,
                'earnings' => isset($salesAmount) ? $salesAmount : null,
            ];
        });

        // return $uid;

        $manual = UserOverrides::with('userInfo')->where('user_id', $uid)->where('type', 'Manual')->whereBetween('created_at', [$startDate, $endDate])->where('status', 0);

        if ($request->has('manual_search') && ! empty($request->input('manual_search'))) {
            $inDirect->where(function ($query) use ($request) {
                return $query->where('kw', 'LIKE', '%'.$request->input('manual_search').'%')
                    ->orWhere('amount', 'LIKE', '%'.$request->input('manual_search').'%')
                    ->orWhere('sale_user_id', 'LIKE', '%'.$request->input('manual_search').'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->input('manual_search').'%')
                    ->orWhereHas('userInfo', function ($query) use ($request) {
                        $query->where('first_name', 'like', '%'.$request->input('manual_search').'%');
                    });
            });
        }

        $manual = $manual->groupBy('sale_user_id')->get();

        $manual->transform(function ($manual) use ($startDate, $endDate) {
            $salesUser = User::where('id', $manual->sale_user_id)->first();
            $installations = UserOverrides::where('sale_user_id', $manual->sale_user_id)->where('type', 'Manual')->whereBetween('created_at', [$startDate, $endDate])->groupBy('sale_user_id')->where('status', 0)->count();
            $pid = UserOverrides::where('sale_user_id', $manual->sale_user_id)->whereBetween('created_at', [$startDate, $endDate])->where('type', 'Manual')->where('status', 0)->pluck('pid');
            $salesKw = UserOverrides::whereIn('pid', $pid)->where('type', 'Manual')->whereBetween('created_at', [$startDate, $endDate])->where('status', 0)->sum('kw');
            $salesAmount = UserOverrides::whereIn('pid', $pid)->where('type', 'Manual')->whereBetween('created_at', [$startDate, $endDate])->where('status', 0)->sum('amount');
            $amount = isset($salesUser->indirect_overrides_amount) ? $salesUser->indirect_overrides_amount : null;
            $kw = isset($salesUser->indirect_overrides_type) ? $salesUser->indirect_overrides_type : null;

            return [
                'id' => isset($manual->id) ? $manual->id : null,
                'name' => isset($salesUser->first_name) ? $salesUser->first_name : null,
                'image' => isset($salesUser->image) ? $salesUser->image : null,
                'overrides' => $amount.'/'.$kw,
                'installation' => $installations,
                'total_kw' => isset($salesKw) ? $salesKw : null,
                'earnings' => isset($salesAmount) ? $salesAmount : null,
            ];
        });

        $data = [
            'total_office_overrides' => $officeCount,
            'total_direct_overrides' => $directCount,
            'total_indirect_overrides' => $inDirectCount,
            'total_manual_overrides' => $Manual,
            'list_office_overrides' => $office,
            'list_direct_overrides' => $directs,
            'list_indirect_overrides' => $inDirect,
            'list_indirect_manual' => $manual,
        ];

        return response()->json([
            'ApiName' => 'My Orverrides API ',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }
}
