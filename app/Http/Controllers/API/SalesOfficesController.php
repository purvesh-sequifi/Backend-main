<?php

namespace App\Http\Controllers\API;

use App\Core\Traits\EvereeTrait;
use App\Http\Controllers\Controller;
use App\Models\SalesOffice;
use App\Models\User;
use App\Models\UserSalesOffice;
use App\Models\UserSalesOfficeHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Validator;

class SalesOfficesController extends Controller
{
    use EvereeTrait;

    public function __construct(SalesOffice $locations)
    {
        $this->locations = $locations;
    }

    public function index(Request $request)
    {
        if (isset($request->type) && $request->type == 'office') {

            $locations = SalesOffice::with('state');
            if ($request->has('search') && $request->search != '') {
                $search = $request->search;
                $locations->orWhere(function ($query) use ($search) {
                    $query->where('office_name', 'LIKE', '%'.$search.'%')
                        // ->orWhere('installation_partner', 'LIKE', '%' . $search . '%')
                        ->orWhereHas('State', function ($query) use ($search) {
                            $query->where('name', 'LIKE', '%'.$search.'%');
                        });
                });
            }

            if ($request->has('filter') && $request->filter != null) {
                $filterData = $request->filter;
                $locations->where(function ($query) use ($filterData) {
                    $query->where('id', $filterData);
                });
            }

            if ($request->has('state') && $request->input('state') != null) {
                $state = $request->input('state');
                $locations->where(function ($query) use ($state) {
                    $query->where('state_id', $state);
                });
            }

            $locations = $locations->paginate();

            $locations->transform(function ($locations) {
                $user_count = UserSalesOffice::where('office_id', $locations->id)->count();

                return [
                    'id' => $locations->id,
                    'user_count' => $user_count,
                    'state_id' => $locations->state_id,
                    'office_name' => $locations->office_name,
                    'people' => $user_count,
                    'state' => $locations->State->name ?? null,
                    'status' => $locations->status,
                ];
            });
        } else {

            $locations = UserSalesOffice::with('userDetail', 'salesOffice');
            if ($request->has('search') && $request->search != '') {
                $search = $request->search;
                $locations->orWhere(function ($query) use ($search) {
                    // ->orWhere('installation_partner', 'LIKE', '%' . $search . '%')
                    $query->orWhereHas('salesOffice', function ($query) use ($search) {
                        $query->where('office_name', 'LIKE', '%'.$search.'%')
                            ->orWhere('state_name', 'LIKE', '%'.$search.'%');
                    })
                        ->orWhereHas('userDetail', function ($query) use ($search) {
                            $query->where('first_name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%')
                                ->orWhereRaw('CONCAT(first_name, " ",last_name) LIKE ?', ['%'.$search.'%']);
                        });
                });
            }

            if ($request->has('filter') && $request->filter != null) {
                $filterData = $request->filter;
                $locations->where(function ($query) use ($filterData) {
                    $query->where('office_id', $filterData);
                });
            }

            if ($request->has('state') && $request->input('state') != null) {
                $state = $request->input('state');
                $locations->where(function ($query) use ($state) {
                    $query->orWhereHas('salesOffice', function ($queries) use ($state) {
                        $queries->where('state_id', $state);
                    });
                });
            }

            $locations = $locations->paginate();
            $locations->transform(function ($locations) {
                return [
                    'id' => $locations->id,
                    'user_id' => $locations->user_id,
                    'user_name' => $locations->userDetail?->first_name.' '.$locations->userDetail?->last_name,
                    'office_id' => $locations->office_id,
                    'office_name' => $locations->salesOffice->office_name,
                    'state_id' => $locations->state_id ?? null,
                    'state' => $locations->salesOffice->state_name ?? null,
                    'terminate' => isset($locations->userDetail->terminate) ? $locations->userDetail->terminate : 0,
                    'dismiss' => isset($locations->userDetail->dismiss) ? $locations->userDetail->dismiss : 0,
                    'status' => $locations->status,
                ];
            });
        }

        return response()->json(['status' => 'success', 'locations' => $locations]);
    }

    public function usersByOfficeID(Request $request, $id): JsonResponse
    {
        $userIds = UserSalesOffice::where('office_id', $id)->pluck('user_id')->toArray();
        $office = User::whereIn('id', $userIds)->with('positionDetail');

        if ($request->has('search') && ! empty($request->input('search'))) {
            $search = $request->input('search');
            $office->where(function ($query) use ($search) {
                $query->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhereRaw('CONCAT(first_name, " ",last_name) LIKE ?', ['%'.$search.'%']);
            });
        }

        $data = $office->orderBy('first_name', 'ASC')->paginate(config('app.paginate', 15))->toArray();

        foreach ($data['data'] as $key => $user_img) {
            if (isset($user_img['image']) && $user_img['image'] != null) {
                $data['data'][$key]['image_s3'] = s3_getTempUrl(config('app.domain_name').'/'.$user_img['image']);
            } else {
                $data['data'][$key]['image_s3'] = null;
            }

            if (isset($data['data'][$key]['position_detail'])) {
                $data['data'][$key]['sub_position_name'] = $user_img['position_detail']['position_name'];
                unset($data['data'][$key]['position_detail']);
            } else {
                $data['data'][$key]['sub_position_name'] = '';
            }
        }

        return response()->json([
            'ApiName' => 'sales-offices-users',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    // create-effective-date-wise-history
    public function createMoveReps(Request $request): JsonResponse
    {
        if (! $request->all()) {
            return response()->json([
                'ApiName' => 'move-reps',
                'status' => false,
                'message' => 'Bad Request',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'user_ids' => 'required',
            'office_data' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $officeData = $request->office_data;
        $userIds = $request->user_ids;

        foreach ($officeData as $x => $value) {

            foreach ($userIds as $y => $userId) {
                $officedata = SalesOffice::where('id', $value['office_id'])->first();
                $useroffice = UserSalesOffice::where(['user_id' => $userId, 'office_id' => $value['office_id']])->first();
                $userhistory = UserSalesOfficeHistory::where(['user_id' => $userId, 'office_id' => $value['office_id'], 'effective_date' => $value['effective_date']])->first();
                if (! $useroffice) {

                    $data = UserSalesOffice::create([
                        'office_id' => $value['office_id'],
                        'state_id' => $officedata->state_id ?? null,
                        'user_id' => $userId,
                        'effective_date' => $value['effective_date'],
                        'status' => 1,
                    ]);

                }
                if (! $userhistory) {

                    $data = UserSalesOfficeHistory::create([
                        'office_id' => $value['office_id'],
                        'state_id' => $officedata->state_id ?? null,
                        'user_id' => $userId,
                        'effective_date' => $value['effective_date'],
                        'status' => 1,
                        // 'office_name' => $officedata->office_name ?? null,
                        // 'general_code' => isset($request['general_code']) ? $request['general_code'] : null
                    ]);

                }

            }

        }

        return response()->json([
            'ApiName' => 'move-reps',
            'status' => true,
            'message' => 'add Successfully.',
        ]);
    }

    public function userHistory($userId)
    {
        if ($userId) {

            $data = UserSalesOfficeHistory::with('salesOffice')->where('user_id', $userId)->get();
            $data->transform(function ($location) {
                return [
                    'id' => $location->id,
                    'user_id' => $location->user_id,
                    // 'user_name' => $location->userDetail?->first_name .' '. $location->userDetail?->last_name,
                    'office_id' => $location->office_id,
                    'office_name' => $location->salesOffice->office_name,
                    'state_id' => $location->state_id,
                    'state' => $location->salesOffice->state_name ?? null,
                    'effective_date' => $location->effective_date,
                ];
            });

        }

        return response()->json([
            'ApiName' => 'user-history',
            'status' => true,
            'data' => $data,
        ]);
    }

    public function getLocation(Request $request)
    {
        $data = SalesOffice::where('status', 1)->get();
        $data->transform(function ($locations) {

            return [
                'id' => $locations->id,
                'office_name' => $locations->office_name,
                'status' => $locations->status,
            ];
        });

        return response()->json([
            'ApiName' => 'office-location-list',
            'status' => true,
            'data' => $data,
        ]);
    }

    public function userList(Request $request, $id = null)
    {
        if ($id == 'all') {
            $data = UserSalesOffice::with('userDetail')->get();
        } else {
            $data = UserSalesOffice::with('userDetail')->where('office_id', $id)->get();
        }

        // $office = User::withoutGlobalScope('notTerminated')->whereIn('id', $userIds)->with('positionDetail');
        // $data = $office->orderBy('first_name', 'ASC')->get()->toArray();

        $data->transform(function ($location) {

            return [
                'id' => $location->id,
                'sale_office_id' => $location->office_id,
                'user_id' => $location->user_id,
                'user_detail' => $location->userDetail ?? null,
            ];
        });

        return response()->json([
            'ApiName' => 'sales-offices-user-list',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function paginate($items, $perPage = null, $page = null)
    {
        $total = count($items);
        $page = Paginator::resolveCurrentPage('page');
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page);
        $start = ($paginator->currentPage() - 1) * $perPage;
        $sliced = array_slice($items, $start, $perPage);

        return new LengthAwarePaginator($sliced, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }
}
