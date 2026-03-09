<?php

namespace App\Http\Controllers\API\V2\Sales;

use App\Core\Traits\PayFrequencyTrait;
use App\Core\Traits\PermissionCheckTrait;
use App\Exports\UserOverrideExport;
use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\ManualOverrides;
use App\Models\Products;
use App\Models\ProjectionUserOverrides;
use App\Models\User;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideQueue;
use App\Models\UserOverrides;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class SalesStandardOverridesController extends Controller
{
    use PayFrequencyTrait;
    use PermissionCheckTrait;

    public function generate_user_override(Request $request): JsonResponse
    {
        if ($request->input('id')) {
            $id = $request->input('id');
        } else {
            $id = Auth::user()->id;
        }
        Artisan::call('calculateuseroverrideprojections:create '.$id.' ');

        return response()->json([
            'ApiName' => 'user_override_processing',
            'status' => true,
            'message' => 'Processing Complete.',
        ], 200);
    }

    public function myOverridesList(Request $request)
    {
        $user_id = auth()->user()->id;
        [$startDate, $endDate] = getDateFromFilter($request);
        $process_status = 0;
        // $user_id  = $request->user_id;
        $projected = $request->projected ?? 0;
        $search = $request->search ?? '';
        $sort = $request->sort ?? 'desc';
        $perpage = (! empty($request->input('perpage'))) ? $request->input('perpage') : 10;
        $total = 0;
        $sales = [];
        $companyProfile = CompanyProfile::first();
        if ($projected == 0) { // NORMAL OVERRIDE PART
            $data = UserOverrides::with('userInfo:id,first_name,last_name,image', 'salesDetail:pid,product_id,customer_name,m2_date')->where('user_id', $user_id);
            // Advance search,
            if (! empty($search)) {
                $data->where(function ($query) use ($search) {
                    // Grouped conditions for searching in userInfo and salesDetail
                    $query->whereHas('userInfo', function ($user_qry) use ($search) {
                        $searchTermLike = str_replace(' ', '%', $search);
                        $user_qry->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchTermLike}%"]);
                    })->orWhereHas('salesDetail', function ($sales_qry) use ($search) {
                        $sales_qry->where('customer_name', 'like', '%'.trim($search).'%')
                            ->orWhere('pid', 'like', '%'.trim($search).'%');
                    });
                });
            }
            $data->where('user_id', $user_id);
            if ($request->has('type_filter') && ! empty($request->type_filter) && $request->type_filter != 'all') {
                $data->where(function ($query) use ($request) {
                    return $query->where('type', $request->type_filter);
                });
            }

            if ($request->has('filter') && ! empty($request->filter)) {
                $data->where(function ($query) use ($startDate, $endDate) {
                    return $query->whereBetween('updated_at', [$startDate, $endDate]);
                });
            }
            $sales = $data->orderByRaw('CAST(amount AS DECIMAL(10, 2)) '.$sort)->paginate($perpage);
            $total = UserOverrides::where('user_id', $user_id)->whereBetween('updated_at', [$startDate, $endDate])->sum('amount');

            $sales->getCollection()->transform(function ($d) use ($companyProfile) {
                $customer_name = $d->salesDetail->customer_name ?? '';
                $user_fullname = '';
                $image = null;
                $override_over_uid = null;
                $position_id = null;

                // Check if userInfo relationship exists
                if ($d->userInfo) {
                    $user_fullname = ($d->userInfo->first_name) ? $d->userInfo->first_name.' '.$d->userInfo->last_name : '';
                    $override_over_uid = $d->userInfo->id;

                    if (isset($d->userInfo->image) && $d->userInfo->image != null) {
                        $image = s3_getTempUrl(config('app.domain_name').'/'.$d->userInfo->image);
                    }

                    $approvedDate = date('Y-m-d');
                    $userOrganizationHistory = UserOrganizationHistory::where('user_id', $d->userInfo->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    $position_id = $userOrganizationHistory->position_id ?? null;
                }

                $type = $d->type;
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    if ($type == 'm2 update') {
                        $type = 'Commission Update';
                    }
                }
                $product = null;
                if ($d->salesDetail && $d->salesDetail->product_id) {
                    $product = Products::select('id', 'name', 'product_id')->where('id', $d->salesDetail->product_id)->first();
                }

                return [
                    'pid' => $d->pid,
                    'position_id' => $position_id,
                    'customer_name' => $customer_name,
                    'product' => $product->name ?? '',
                    'override_over' => $user_fullname,
                    'override_over_image' => $image,
                    'override_over_uid' => $override_over_uid,
                    'type' => $type,
                    'kw_installed' => $d->kw,
                    'override' => $d->overrides_amount.' '.$d->overrides_type,
                    'override_amount' => $d->overrides_amount,
                    'override_amount_type' => $d->overrides_type,
                    'total_override' => (! empty($d->amount)) ? $d->amount * 1 : 0,
                    'date' => $d->salesDetail ? date('m/d/Y', strtotime($d->salesDetail->m2_date)) : '',
                ];
            });
        } else { // PROJECTION PART
            $processing = UserOverrideQueue::where(['user_id' => $user_id])->first();
            if ($processing) {
                if ($processing->processing == 1) {
                    $process_status = 1;
                }
            }
            $sales = ProjectionUserOverrides::query()
                ->where(function ($query) use ($search) {
                    $query->whereHas('userInfo', function ($userQuery) use ($search) {
                        $searchTermLike = str_replace(' ', '%', $search);
                        $userQuery->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchTermLike}%"]);
                    })
                        ->orWhere('customer_name', 'like', '%'.trim($search).'%')
                        ->orWhere('pid', 'like', '%'.trim($search).'%');
                });

            if ($request->has('type_filter') && ! empty($request->type_filter) && $request->type_filter != 'all') {
                $sales->where(function ($query) use ($request) {
                    return $query->where('type', $request->type_filter);
                });
            }
            $sales = $sales->where('user_id', $user_id)->orderBy('total_override', $sort)->paginate($perpage);
            $total = ProjectionUserOverrides::where('user_id', $user_id)->sum('total_override');

            $sales->getCollection()->transform(function ($sale) use ($companyProfile) {
                $approvedDate = date('Y-m-d');
                $override_over_user = User::where('id', $sale->sale_user_id)->select('id', 'first_name', 'last_name', 'image')->first();
                if (isset($override_over_user->image) && $override_over_user->image != null) {
                    $image = s3_getTempUrl(config('app.domain_name').'/'.$override_over_user->image);
                } else {
                    $image = null;
                }
                $userOrganizationHistory = UserOrganizationHistory::where('user_id', $sale->sale_user_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();

                $type = $sale->type;
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    if ($type == 'm2 update') {
                        $type = 'Commission Update';
                    }
                }

                return [
                    'pid' => $sale->pid,
                    'customer_name' => $sale->customer_name,
                    'override_over' => $override_over_user->first_name.' '.$override_over_user->last_name,
                    'override_over_image' => $image,
                    'override_over_uid' => $override_over_user->id,
                    'position_id' => $userOrganizationHistory->position_id,
                    'type' => $type,
                    'kw_installed' => $sale->kw,
                    'override' => $sale->overrides_amount.' '.$sale->overrides_type,
                    'override_amount' => $sale->overrides_amount,
                    'override_amount_type' => $sale->overrides_type,
                    'total_override' => $sale->total_override,
                    'date' => '',
                ];
            });
        }

        return response()->json([
            'ApiName' => 'list_overrides',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $sales,
            'total' => $total,
            'process_status' => $process_status,
        ]);
    }

    public function myOverridesCards(Request $request)
    {
        $id = auth()->user()->id;
        [$startDate, $endDate] = getDateFromFilter($request);

        // $id = $request->input('id');
        $is_export = $request->input('is_export');
        $user = User::where('id', $id)->first();

        if ($is_export) {
            return $this->getOverrideDataExport($request, '', $id, $user, $startDate, $endDate, 0, 'range', 'cards');
        } else {
            $data = $this->getOverrideData('', $id, $user, $startDate, $endDate, 0, 'range', 'cards');

            return response()->json([
                'ApiName' => 'my_overrides_cards',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ]);
        }
    }

    public function myOverridesGraph(Request $request)
    {
        $id = auth()->user()->id;
        // $id = $request->input('id');
        $user = User::where('id', $id)->first();

        $filterDataDateWise = $request->input('filter');
        if ($filterDataDateWise == 'this_week') {
            $currentDate = \Carbon\Carbon::now();

            $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
            $endDate = date('Y-m-d', strtotime(now()));

            for ($i = 0; $i < $currentDate->dayOfWeek; $i++) {
                $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));
                $result[] = $this->getOverrideDataGraph($weekDate, $id, $user, $startDate, $endDate, $i, '');
            }
        } elseif ($filterDataDateWise == 'last_week') {
            $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
            $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
            $startDate = date('Y-m-d', strtotime($startOfLastWeek));
            $endDate = date('Y-m-d', strtotime($endOfLastWeek));
            for ($i = 0; $i < 7; $i++) {
                $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));
                $result[] = $this->getOverrideDataGraph($weekDate, $id, $user, $startDate, $endDate, $i, '');
            }
        } elseif ($filterDataDateWise == 'this_month') {
            $month = \Carbon\Carbon::now()->daysInMonth;
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            $startDate = date('Y-m-d', strtotime($startOfMonth));
            $endDate = date('Y-m-d', strtotime(now()));
            $now = strtotime($endDate);
            $your_date = strtotime($startDate);
            $dateDiff = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24));
            for ($i = 0; $i <= $dateDays; $i++) {
                $weekDate = date('Y-m-d', strtotime(Carbon::now()->startOfMonth()->addDays($i)));
                $result[] = $this->getOverrideDataGraph($weekDate, $id, $user, $startDate, $endDate, $i, '');
            }
        } elseif ($filterDataDateWise == 'last_month') {
            $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonth()->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonth()->endOfMonth()));

            for ($i = 0; $i <= $month; $i++) {
                $weekDate = date('Y-m-d', strtotime(Carbon::now()->subMonth()->startOfMonth()->addDays($i)));
                $result[] = $this->getOverrideDataGraph($weekDate, $id, $user, $startDate, $endDate, $i, '');
            }
        } elseif ($filterDataDateWise == 'this_quarter') {
            $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now()->month(02)->daysInMonth;
            $weeks = (int) (($currentMonthDay % 365) / 7);
            $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
            $date = Carbon::parse($startDate = date('Y-m-d', strtotime('-5 days', strtotime($startDate))));
            $eom = Carbon::parse($endDate);

            $dates = [];
            $f = 'm/d/Y';

            for ($i = 0; $i < $weeks; $i++) {
                $currentDate = \Carbon\Carbon::now();
                $startDate = $date->copy();
                // loop to end of the week while not crossing the last date of month
                while ($date->dayOfWeek != Carbon::SUNDAY && $date->lte($eom)) {
                    $date->addDay();
                }

                $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                $sDate = date('Y-m-d', strtotime($startDate->format($f)));
                $eDate = date('Y-m-d', strtotime($date->format($f)));
                if ($date->format($f) < $eom->format($f)) {
                    $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                    $sDate = date('Y-m-d', strtotime($startDate->format($f)));
                    $eDate = date('Y-m-d', strtotime($date->format($f)));
                } else {
                    $dates['w'.$i] = $startDate->format($f).' to '.$eom->format($f);
                    $sDate = date('Y-m-d', strtotime($startDate->format($f)));
                    $eDate = date('Y-m-d', strtotime($eom->format($f)));
                }

                $date->addDay();
                $weekDate = $dates['w'.$i];

                $result[] = $this->getOverrideDataGraph($weekDate, $id, $user, $sDate, $eDate, $i, 'range');
            }
        } elseif ($filterDataDateWise == 'last_quarter') {
            $date = new \Carbon\Carbon('-3 months');
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
            $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now()->month(02)->daysInMonth;
            $weeks = (int) (($currentMonthDay % 365) / 7);

            $now = strtotime($endDate);
            $your_date = strtotime($startDate);
            $dateDiff = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24));
            $date = Carbon::parse($startDate = date('Y/m/d', strtotime('-5 days', strtotime($startDate))));
            $eom = Carbon::parse($endDate);
            $dates = [];
            $f = 'm/d/Y';

            for ($i = 0; $i < $weeks; $i++) {
                $sDate = date('Y-m-d', strtotime(Carbon::today()->subMonths(3 - $i)->addDays(30)));
                $eDate = date('Y-m-d', strtotime('+7 days', strtotime($sDate)));
                $startDate = $date->copy();
                // loop to end of the week while not crossing the last date of month
                // return $date->dayOfWeek;
                while ($date->dayOfWeek != Carbon::SUNDAY && $date->lte($eom)) {
                    $date->addDay();
                }
                $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                $sDate = date('Y-m-d', strtotime($startDate->format($f)));
                $eDate = date('Y-m-d', strtotime($date->format($f)));
                if ($date->format($f) < $eom->format($f)) {
                    $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                    $sDate = date('Y-m-d', strtotime($startDate->format($f)));
                    $eDate = date('Y-m-d', strtotime($date->format($f)));
                } else {
                    $dates['w'.$i] = $startDate->format($f).' to '.$eom->format($f);
                    $sDate = date('Y-m-d', strtotime($startDate->format($f)));
                    $eDate = date('Y-m-d', strtotime($eom->format($f)));
                }

                $date->addDay();

                $weekDate = $dates['w'.$i];

                $result[] = $this->getOverrideDataGraph($weekDate, $id, $user, $sDate, $eDate, $i, 'range');
            }
        } elseif ($filterDataDateWise == 'this_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
            // $endDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
            $now = strtotime($endDate);
            $your_date = strtotime($startDate);
            $dateDiff = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24));
            for ($i = 0; $i < 12; $i++) {

                $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                $eDates = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));
                $eDate = date('Y-m-d', strtotime($eDates.'-1 day'));
                $time = strtotime($sDate);
                $month = date('M', $time);
                $currentMonth = date('m');
                if ($i < $currentMonth) {
                    $result[] = $this->getOverrideDataGraph($month, $id, $user, $sDate, $eDate, $i, 'range');
                }
            }
        } elseif ($filterDataDateWise == 'last_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
            $now = strtotime($endDate);
            $your_date = strtotime($startDate);
            $dateDiff = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24));

            for ($i = 0; $i < 12; $i++) {
                $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                $eDate = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));
                $time = strtotime($sDate);
                $month = date('M', $time);
                $result[] = $this->getOverrideDataGraph($month, $id, $user, $sDate, $eDate, $i, 'range');
            }
        } elseif ($filterDataDateWise == 'last_12_months') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));

            $now = strtotime($endDate);
            $your_date = strtotime($startDate);
            $dateDiff = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24));
            for ($i = 0; $i < 12; $i++) {
                $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                $eDate = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));
                $time = strtotime($sDate);
                $month = date('M', $time);

                $result[] = $this->getOverrideDataGraph($month, $id, $user, $sDate, $eDate, $i, 'range');
            }
        } elseif ($filterDataDateWise == 'custom') {
            $startDate = $filterDataDateWise = $request->input('start_date');
            $endDate = $filterDataDateWise = $request->input('end_date');

            $now = strtotime($endDate);
            $your_date = strtotime($startDate);
            $dateDiff = $now - $your_date;
            $dateDays = floor($dateDiff / (60 * 60 * 24));
            if (isset($startDate) && $startDate != '' && isset($endDate) && $endDate != '') {
                if ($dateDays <= 15) {
                    for ($i = 0; $i < $dateDays; $i++) {
                        $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));
                        $result[] = $this->getOverrideDataGraph($weekDate, $id, $user, $startDate, $endDate, $i, '');
                    }
                } else {
                    $weekCount = round($dateDays / 7);
                    $totalWeekDay = 7 * $weekCount;
                    $extraDay = $dateDays - $totalWeekDay;

                    if ($extraDay > 0) {
                        $weekCount = $weekCount + 1;
                    }

                    for ($i = 0; $i < $weekCount; $i++) {
                        $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                        $dayWeek = 7 * $i;
                        if ($i == 0) {
                            $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                            $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));
                        } else {

                            $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                            $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));
                        }
                        if ($i == $weekCount - 1) {
                            $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                            $eDate = $endDate;
                        }
                        $time = strtotime($sDate);
                        $month = date('M', $time);
                        $WeekStartDate = date('m/d/Y', strtotime($startDate.' + '.$dayWeek.' days'));
                        $weekEndDate = date('m/d/Y', strtotime($endsDate.' + '.$dayWeek.' days'));

                        $result[] = $this->getOverrideDataGraph($WeekStartDate.' to '.$weekEndDate, $id, $user, $sDate, $eDate, $i, 'range');
                    }
                }
            }
        } else {
            for ($i = 0; $i < 7; $i++) {
                $newDateTime = Carbon::now()->subDays(6 - $i);
                $weekDate = date('Y-m-d', strtotime($newDateTime));
                $startDate = '';
                $endDate = '';
                $result[] = $this->getOverrideDataGraph($weekDate, $id, $user, $startDate, $endDate, $i, '');
            }
        }

        $result = $result ?? [];

        return response()->json([
            'ApiName' => 'my_overrides_graph',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $result,
        ], 200);
    }

    public function getOverrideData($weekDate, $id, $user, $startDate, $endDate, $i, $date_type, $view_type = '')
    {
        $directs = [
            'total_count' => 0,
            'total_earning' => 0,
        ];
        $indirects = [
            'total_count' => 0,
            'total_earning' => 0,
        ];
        $offices = [
            'total_count' => 0,
            'total_earning' => 0,
        ];
        $manuals = [
            'total_count' => 0,
            'total_earning' => 0,
        ];
        $stacks = [
            'total_count' => 0,
            'total_earning' => 0,
        ];

        $userOverrides = UserOverrides::select('type as overrideType',
            DB::raw('SUM(amount) AS totalEarning'),
            DB::raw('COUNT(id) AS totalCount'))
            ->where('user_id', $id);

        if ($date_type == 'range') {
            // $userOverrides->whereBetween('pay_period_to', [$startDate, $endDate]);
            $userOverrides->whereBetween('updated_at', [$startDate, $endDate]);
            $dateShow = $weekDate;
        } else {
            // $userOverrides->where('pay_period_to', $weekDate);
            $userOverrides->whereDate('updated_at', $weekDate);
            $dateShow = date('m/d/Y', strtotime($startDate.' + '.$i.' days'));
        }

        $overrides = $userOverrides->groupBy('type')->get()->toArray();

        if (! empty($overrides)) {
            foreach ($overrides as $override) {
                if ($override['overrideType'] == 'Direct') {
                    $directs = [
                        'total_count' => $override['totalCount'],
                        'total_earning' => $override['totalEarning'],
                    ];
                }
                if ($override['overrideType'] == 'Indirect') {
                    $indirects = [
                        'total_count' => $override['totalCount'],
                        'total_earning' => $override['totalEarning'],
                    ];
                }
                if ($override['overrideType'] == 'Office') {
                    $offices = [
                        'total_count' => $override['totalCount'],
                        'total_earning' => $override['totalEarning'],
                    ];
                }
                if ($override['overrideType'] == 'Manual') {
                    $manuals = [
                        'total_count' => $override['totalCount'],
                        'total_earning' => $override['totalEarning'],
                    ];
                }
                if ($override['overrideType'] == 'Stack') {
                    $stacks = [
                        'total_count' => $override['totalCount'],
                        'total_earning' => $override['totalEarning'],
                    ];
                }
            }
        }

        $data = [
            'direct' => $directs,
            'indirect' => $indirects,
            'office' => $offices,
            'manual' => $manuals,
            'stack' => $stacks,
        ];

        if ($view_type == 'cards') {
            return $data;
        } else {
            return $result = [
                'date' => $dateShow,
                'result' => $data,
            ];
        }

    }

    public function getOverrideDataExport($request, $weekDate, $id, $user, $startDate, $endDate, $i, $date_type, $view_type = '')
    {

        $user_id = $request->user_id;
        $projected = $request->projected ?? 0;
        $type_filter = $request->type_filter ?? '';
        $search = $request->search ?? '';
        $sort = $request->sort ?? 'desc';
        $is_export = (! empty($request->input('is_export'))) ? $request->input('is_export') : 0;
        $perpage = (! empty($request->input('perpage'))) ? $request->input('perpage') : 10;
        $page = (! empty($request->input('page'))) ? $request->input('page') : 1;
        $filterDataDateWise = $request->input('filter');
        if ($filterDataDateWise == 'this_week') {
            $currentDate = \Carbon\Carbon::now();
            $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
            $endDate = date('Y-m-d', strtotime(now()));
        } elseif ($filterDataDateWise == 'last_week') {
            $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
            $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
            $startDate = date('Y-m-d', strtotime($startOfLastWeek));
            $endDate = date('Y-m-d', strtotime($endOfLastWeek));
        } elseif ($filterDataDateWise == 'this_month') {
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            $startDate = date('Y-m-d', strtotime($startOfMonth));
            $endDate = date('Y-m-d', strtotime($endOfMonth));
        } elseif ($filterDataDateWise == 'last_month') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonth()->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonth()->endOfMonth()));
        } elseif ($filterDataDateWise == 'this_quarter') {
            $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
            $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
        } elseif ($filterDataDateWise == 'last_quarter') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
        } elseif ($filterDataDateWise == 'this_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
            // $endDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
        } elseif ($filterDataDateWise == 'last_year') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
        } elseif ($filterDataDateWise == 'last_12_months') {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
        } elseif ($filterDataDateWise == 'custom') {
            $startDate = $filterDataDateWise = $request->input('start_date');
            $endDate = $filterDataDateWise = $request->input('end_date');
        }
        $result = [];
        if ($projected == 0) { // NORMAL OVERRIDE PART

            $data = UserOverrides::with('userInfo:id,first_name,last_name,image', 'salesDetail:pid,customer_name');
            // Advance search ,
            if (! empty($search)) {
                $data->where(function ($query) use ($search) {
                    // Grouped conditions for searching in userInfo and salesDetail
                    $query->whereHas('userInfo', function ($user_qry) use ($search) {
                        $searchTermLike = str_replace(' ', '%', $search);
                        $user_qry->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchTermLike}%"]);
                    })->orWhereHas('salesDetail', function ($sales_qry) use ($search) {
                        $sales_qry->where('customer_name', 'like', '%'.trim($search).'%')
                            ->orWhere('pid', 'like', '%'.trim($search).'%');
                    });
                });
            }
            $data->where('user_id', $user_id);
            if ($request->has('type_filter') && ! empty($request->type_filter) && $request->type_filter != 'all') {
                $data->where(function ($query) use ($request) {
                    return $query->where('type', $request->type_filter);
                });
            }

            if ($request->has('filter') && ! empty($request->filter)) {
                $data->where(function ($query) use ($startDate, $endDate) {
                    // return $query->whereBetween('pay_period_to',[$startDate,$endDate]);
                    return $query->whereBetween('updated_at', [$startDate, $endDate]);
                });
            }
            $data = $data->orderBy('amount', $sort)->get();

            if (count($data) > 0) {
                foreach ($data as $key => $d) {
                    $pid = $d->pid;
                    $customer_name = $d->salesDetail->customer_name ?? '';
                    $user_fullname = ($d->userInfo->first_name) ? $d->userInfo->first_name.' '.$d->userInfo->last_name : '';
                    $result[] = [
                        'pid' => $d->pid,
                        'customer_name' => $customer_name,
                        'override_over' => $user_fullname,
                        'type' => $d->type,
                        'kw_installed' => $d->kw,
                        'override' => $d->overrides_amount.' '.$d->overrides_type,
                        'total_override' => (! empty($d->amount)) ? $d->amount * 1 : 0,
                    ];
                }
            }
        } else { // PROJECTION PART
            $processing = UserOverrideQueue::where(['user_id' => $user_id])->first();
            if ($processing) {
                if ($processing->processing == 1) {
                    $process_status = 1;
                }
            }
            $sales = ProjectionUserOverrides::query()
                ->where(function ($query) use ($search) {
                    $query->whereHas('userInfo', function ($userQuery) use ($search) {
                        $searchTermLike = str_replace(' ', '%', $search);
                        $userQuery->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchTermLike}%"]);
                    })
                        ->orWhere('customer_name', 'like', '%'.trim($search).'%')
                        ->orWhere('pid', 'like', '%'.trim($search).'%');
                });

            if ($request->has('type_filter') && ! empty($request->type_filter) && $request->type_filter != 'all') {
                $sales->where(function ($query) use ($request) {
                    return $query->where('type', $request->type_filter);
                });
            }

            $sales = $sales->where('user_id', $user_id)
                ->orderBy('pid', $sort)
                ->get();

            if (count($sales) > 0) {
                foreach ($sales as $key => $sale) {
                    // $approvedDate = isset($sale->customer_signoff)? $sale->customer_signoff : Null;
                    $approvedDate = date('Y-m-d');
                    $override_over_user = User::where('id', $sale->user_id)->select('id', 'first_name', 'last_name', 'image')->first();
                    $userOrganizationHistory = UserOrganizationHistory::where('user_id', $sale->sale_user_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    $result[] = [
                        'pid' => $sale->pid,
                        'customer_name' => $sale->customer_name,
                        'override_over' => $override_over_user->first_name.' '.$override_over_user->last_name,
                        'position_id' => $userOrganizationHistory->position_id,
                        'type' => $sale->type,
                        'kw_installed' => $sale->kw,
                        'override' => $sale->overrides_amount.' '.$sale->overrides_type,
                        'total_override' => $sale->total_override,
                    ];
                }
            }
        }
        $file_name = 'payroll_export_'.date('Y_m_d_H_i_s').'.xlsx';
        Excel::store(new UserOverrideExport($result), 'exports/sales/'.$file_name, 'public', \Maatwebsite\Excel\Excel::XLSX);
        $url = getStoragePath('exports/sales/'.$file_name);

        return response()->json(['url' => $url]);
        // return Excel::download(new UserOverrideExport($result), $file_name);
    }

    public function applicableoverrides(Request $request): JsonResponse
    {
        $user_id = $request->user_id;

        $data = [];
        $data['office_override'] = 0;
        $data['stack_override'] = 0;
        $data['direct_override'] = 0;
        $data['indirect_override'] = 0;
        $data['manual_override'] = 0;

        $record = User::where('id', $user_id)->first();
        if ($record->is_manager == 1 || $record->office_overrides_amount > 0) {
            $data['office_override'] = 1;
        }
        if ($record->office_stack_overrides_amount) {
            $data['stack_override'] = 1;
        }
        if ($record->direct_overrides_amount) {
            $data['direct_override'] = 1;
        }
        if ($record->indirect_overrides_amount) {
            $data['indirect_override'] = 1;
        }
        $manualOverrides = ManualOverrides::where('user_id', $user_id)->whereHas('manualUser', function ($q) {
            $q->where('is_super_admin', '!=', '1');
        })->get();
        if (count($manualOverrides)) {
            $data['manual_override'] = 1;
        }

        return response()->json([
            'ApiName' => 'applicable_override',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function getOverrideDataGraph($weekDate, $id, $user, $startDate, $endDate, $i, $date_type, $view_type = '')
    {
        $directs = [
            'total_count' => 0,
            'total_earning' => 0,
        ];
        $indirects = [
            'total_count' => 0,
            'total_earning' => 0,
        ];
        $offices = [
            'total_count' => 0,
            'total_earning' => 0,
        ];
        $manuals = [
            'total_count' => 0,
            'total_earning' => 0,
        ];
        $stacks = [
            'total_count' => 0,
            'total_earning' => 0,
        ];

        $userOverrides = UserOverrides::select('type as overrideType', DB::raw('SUM(amount) AS totalEarning'), DB::raw('COUNT(id) AS totalCount'))->where(['user_id' => $id]);
        if ($date_type == 'range') {
            $userOverrides->where('updated_at', '>=', $startDate)->where('updated_at', '<=', $endDate);
            $dateShow = $weekDate;
        } else {
            $userOverrides->whereDate('updated_at', $weekDate);
            $dateShow = date('m/d/Y', strtotime($startDate.' + '.$i.' days'));
        }

        $userOverrides->groupBy('overrideType');
        $userOverride = $userOverrides->get()->toArray();

        if (! empty($userOverride)) {
            foreach ($userOverride as $override) {
                if ($override['overrideType'] == 'Direct') {
                    $directs = [
                        'total_count' => $override['totalCount'],
                        'total_earning' => $override['totalEarning'],
                    ];
                }
                if ($override['overrideType'] == 'Indirect') {
                    $indirects = [
                        'total_count' => $override['totalCount'],
                        'total_earning' => $override['totalEarning'],
                    ];
                }
                if ($override['overrideType'] == 'Office') {
                    $offices = [
                        'total_count' => $override['totalCount'],
                        'total_earning' => $override['totalEarning'],
                    ];
                }
                if ($override['overrideType'] == 'Manual') {
                    $manuals = [
                        'total_count' => $override['totalCount'],
                        'total_earning' => $override['totalEarning'],
                    ];
                }
                if ($override['overrideType'] == 'Stack') {
                    $stacks = [
                        'total_count' => $override['totalCount'],
                        'total_earning' => $override['totalEarning'],
                    ];
                }
            }
        }

        $data = [
            'direct' => $directs,
            'indirect' => $indirects,
            'office' => $offices,
            'manual' => $manuals,
            'stack' => $stacks,
        ];

        if ($view_type == 'cards') {
            return $data;
        } else {
            return [
                'date' => $dateShow,
                'result' => $data,
            ];
        }
    }
}
