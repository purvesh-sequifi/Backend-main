<?php

namespace App\Http\Controllers\API\CalendarEvent;

use App\Http\Controllers\Controller;
use App\Models\EventCalendar;
// use Maatwebsite\Excel\Facades\Excel;
use App\Models\Locations;
use App\Models\Notification;
use App\Models\State;
use App\Traits\PushNotificationTrait;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Validator;

class CalendarEventController extends Controller
{
    use PushNotificationTrait;

    public function index(Request $request)
    {
        $user_id = Auth::user()->id;
        $state_id = Auth::user()->state_id;
        $date = date('Y-m-d');

        if ($request->office_id == 'all') {
            $state = State::where('id', $state_id)->first();
            $today = EventCalendar::where('event_date', $date)->where('type', 'Hired')->count();

            $todayInterView = EventCalendar::where('type', 'Interview')->count();

            if ($request->type == 'InterView' && $request->date == 1) {
                $data = EventCalendar::with('detailForInterview')->where('type', $request->type)->Orwhere('type', 'Hired')->where('event_date', $date)->get();
            }
            if ($request->type != 'InterView' && $request->date == 1) {
                $data = EventCalendar::with('detailForInterview')->where('type', 'Hired')->where('event_date', $date)->get();
            }
            if ($request->type == 'InterView' && $request->date == 0) {
                $data = EventCalendar::with('detailForInterview')->where('type', $request->type)->get();
            }
            if ($request->type != 'InterView' && $request->date == 0) {
                $data = EventCalendar::with('detailForInterview')->get();

            }

            // if($request->type == 'today')
            // {
            //     $data = EventCalendar::
            //     whereDate('created_at', Carbon::today())->where('user_id',$user_id)->get();
            // }elseif($request->type == 'week')
            // {
            //     $data = EventCalendar::whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            //     ->where('user_id',$user_id)
            //     ->get();
            // }elseif($request->type == 'months')
            // {
            //     $data = EventCalendar::whereMonth('created_at', date('m'))
            //         ->whereYear('created_at', date('Y'))->where('user_id',$user_id)
            //         ->get();
            // }
        } else {
            $state = State::where('state_code', $request->location)->first();
            $today = EventCalendar::where('office_id', $request->office_id)->where('event_date', $date)->where('type', 'Hired')->orWhere('office_id', null)->where('event_date', $date)->where('type', 'Hired')->count();
            $todayInterView = EventCalendar::where('office_id', $request->office_id)->where('type', 'Interview')->orWhere('office_id', null)->where('type', 'Interview')->count();

            // if($request->type == 'today')
            // {
            //     $data = EventCalendar::
            //     whereDate('created_at', Carbon::today())->where('user_id',$user_id)->where('state_id',$request->state_id)->get();
            // }elseif($request->type == 'week')
            // {
            //     $data = EventCalendar::whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            //     ->where('user_id',$user_id)->where('state_id',$request->state_id)
            //     ->get();
            // }elseif($request->type == 'months')
            // {
            //     $data = EventCalendar::whereMonth('created_at', date('m'))
            //         ->whereYear('created_at', date('Y'))->where('user_id',$user_id)
            //         ->where('state_id',$request->state_id)
            //         ->get();
            // }else{
            //     $data = EventCalendar::get();
            // }
            if ($request->type != '' && $request->date == 1) {
                $data = EventCalendar::with('detailForInterview')->where('type', $request->type)->Orwhere('type', 'Hired')->where('event_date', $date)->get();
            }
            if ($request->type == '' && $request->date == 1) {
                $data = EventCalendar::with('detailForInterview')->where('type', 'Hired')->where('event_date', $date)->get();
            }
            if ($request->type != '' && $request->date == 0) {
                $data = EventCalendar::with('detailForInterview')->where('type', $request->type)->get();
            }
            if ($request->type == '' && $request->date == 0) {
                $data = EventCalendar::with('detailForInterview')->get();
            }
        }
        // dd($data);
        $data->transform(function ($data) {
            return [
                'id' => isset($data->id) ? $data->id : null,
                'event_date' => isset($data->event_date) ? $data->event_date : null,
                'event_time' => isset($data->event_time) ? $data->event_time : null,
                'event_name' => isset($data->event_name) ? $data->event_name : null,
                'type' => isset($data->type) ? $data->type : null,
                'description' => isset($data->description) ? $data->description : null,
                'leads' => isset($data->detailForInterview) ? $data->detailForInterview : null,

            ];
        });

        return response()->json([
            'ApiName' => 'Event_list',
            'status' => true,
            'message' => 'Successfully.',
            'your_location' => isset($state->name) ? $state->name : 'all',
            'start_date' => $today,
            'inter_view' => $todayInterView,
            'data' => $data,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (isset($request['type']) && ! empty($request['type'])) {

            $data = EventCalendar::create(
                [
                    'event_date' => $request['event_date'],
                    'event_time' => isset($request['event_time']) ? $request['event_time'] : null,
                    'type' => $request['type'],
                    'state_id' => $user->state_id,
                    'user_id' => $user->id,
                    'event_name' => $request['event_name'],
                    'description' => $request['description'],
                    'office_id' => isset($request['office_id']) ? $request['office_id'] : $user->office_id,
                ]
            );

            $data = Notification::create([
                'user_id' => $user->id,
                'type' => 'Add Lead',
                'description' => 'Add Lead Data by'.auth()->user()->first_name,
                'is_read' => 0,
            ]);
            $notificationData = [
                'user_id' => $user->id,
                'device_token' => $user->device_token,
                'title' => 'Add Lead Data.',
                'sound' => 'sound',
                'type' => 'Add Lead',
                'body' => 'Add Lead Data by '.auth()->user()->first_name,
            ];
            $this->sendNotification($notificationData);

            return response()->json([
                'ApiName' => 'add-Event',
                'status' => true,
                'message' => 'add Successfully.',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'add-Event',
                'status' => false,
                'message' => 'type is required.',
            ], 400);
        }
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();

        $data = EventCalendar::where('id', $id)->first();
        if (isset($data)) {
            $officeId = isset($data->office_id) && $data->office_id != null ? $data->office_id : null;
            $data->event_date = isset($request->event_date) ? $request->event_date : null;
            $data->event_time = isset($request->event_time) ? $request->event_time : null;
            $data->type = $request['type'];
            $data->event_name = $request['event_name'];
            $data->state_id = $user->state_id;
            $data->description = $request['description'];
            $data->office_id = isset($request->office_id) ? $request->office_id : $officeId;
            // return $data;
            $data->save();

            return response()->json([
                'ApiName' => 'Update Event',
                'status' => true,
                'message' => 'Update Successfully.',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Update Event',
                'status' => false,
                'message' => 'Event id not found',
            ], 200);
        }
    }

    public function delete($id): JsonResponse
    {
        $user = Auth::user();
        $data = EventCalendar::find($id);
        if ($data) {
            $data->delete();
        } else {
            return response()->json([
                'ApiName' => 'Delete Event',
                'status' => false,
                'message' => 'Bad Request.',
            ], 400);
        }

        return response()->json([
            'ApiName' => 'Delete Event',
            'status' => true,
            'message' => 'Delete Successfully.',
        ], 200);
    }

    public function hiringEventListOld(Request $request)
    {
        $user_id = Auth::user()->id;
        $state_id = Auth::user()->state_id;
        $office_id = isset($request->office_id) ? $request->office_id : null;

        $date = date('Y-m-d');

        if ($request->location == 'all') {

            $state = State::where('id', $state_id)->first();
            if (isset($office_id)) {
                $office = Locations::where('id', $office_id)->first();
            } else {
                $office = '';
            }
            if (isset($office_id)) {
                $today = EventCalendar::where('event_date', $date)->where('office_id', $office_id)->where('type', 'Hired')->count();
                $todayInterView = EventCalendar::where('type', 'Interview')->where('office_id', $office_id)->count();
                if ($request->type == 'InterView' && $request->date == 1) {
                    $data = EventCalendar::with('detailForInterview')->where('office_id', $office_id)->where('type', $request->type)->Orwhere('type', 'Hired')->where('event_date', $date)->get();
                }
                if ($request->type != 'InterView' && $request->date == 1) {
                    $data = EventCalendar::with('detailForInterview')->where('office_id', $office_id)->where('type', 'Hired')->where('event_date', $date)->get();
                }
                if ($request->type == 'InterView' && $request->date == 0) {
                    $data = EventCalendar::with('detailForInterview')->where('office_id', $office_id)->where('type', $request->type)->get();
                }
                if ($request->type != 'InterView' && $request->date == 0) {
                    $data = EventCalendar::with('detailForInterview')->where('office_id', $office_id)->get();
                }
            } else {
                $today = EventCalendar::where('event_date', $date)->where('state_id', $state_id)->where('type', 'Hired')->count();
                $todayInterView = EventCalendar::where('type', 'Interview')->where('state_id', $state_id)->count();
                if ($request->type == 'InterView' && $request->date == 1) {
                    $data = EventCalendar::with('detailForInterview')->where('state_id', $state_id)->where('type', $request->type)->Orwhere('type', 'Hired')->where('event_date', $date)->get();
                }
                if ($request->type != 'InterView' && $request->date == 1) {
                    $data = EventCalendar::with('detailForInterview')->where('state_id', $state_id)->where('type', 'Hired')->where('event_date', $date)->get();
                }
                if ($request->type == 'InterView' && $request->date == 0) {
                    $data = EventCalendar::with('detailForInterview')->where('state_id', $state_id)->where('type', $request->type)->get();
                }
                if ($request->type != 'InterView' && $request->date == 0) {
                    $data = EventCalendar::with('detailForInterview')->where('state_id', $state_id)->get();
                }
            }

            // if($request->type == 'today')
            // {
            //     $data = EventCalendar::
            //     whereDate('created_at', Carbon::today())->where('user_id',$user_id)->get();
            // }elseif($request->type == 'week')
            // {
            //     $data = EventCalendar::whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            //     ->where('user_id',$user_id)
            //     ->get();
            // }elseif($request->type == 'months')
            // {
            //     $data = EventCalendar::whereMonth('created_at', date('m'))
            //         ->whereYear('created_at', date('Y'))->where('user_id',$user_id)
            //         ->get();
            // }
        } else {

            $state = State::where('state_code', $request->location)->first();
            if (isset($request->office_id)) {
                $office = Locations::where('id', $request->office_id)->first();
                $today = EventCalendar::where('event_date', $date)->where('office_id', $request->office_id)->where('type', 'Hired')->count();
                $todayInterView = EventCalendar::where('type', 'Interview')->where('office_id', $request->office_id)->count();
            } else {
                $today = EventCalendar::where('event_date', $date)->where('state_id', $state->id)->where('type', 'Hired')->count();
                $todayInterView = EventCalendar::where('type', 'Interview')->where('state_id', $state->id)->count();
            }

            // if($request->type == 'today')
            // {
            //     $data = EventCalendar::
            //     whereDate('created_at', Carbon::today())->where('user_id',$user_id)->where('state_id',$request->state_id)->get();
            // }elseif($request->type == 'week')
            // {
            //     $data = EventCalendar::whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            //     ->where('user_id',$user_id)->where('state_id',$request->state_id)
            //     ->get();
            // }elseif($request->type == 'months')
            // {
            //     $data = EventCalendar::whereMonth('created_at', date('m'))
            //         ->whereYear('created_at', date('Y'))->where('user_id',$user_id)
            //         ->where('state_id',$request->state_id)
            //         ->get();
            // }else{
            //     $data = EventCalendar::get();
            // }
            if ($request->type != '' && $request->date == 1) {
                $data = EventCalendar::with('detailForInterview')->where('type', $request->type)->Orwhere('type', 'Hired')->where('event_date', $date)->get();
            }
            if ($request->type == '' && $request->date == 1) {
                $data = EventCalendar::with('detailForInterview')->where('type', 'Hired')->where('event_date', $date)->get();
            }
            if ($request->type != '' && $request->date == 0) {
                $data = EventCalendar::with('detailForInterview')->where('type', $request->type)->get();
            }
            if ($request->type == '' && $request->date == 0) {
                $data = EventCalendar::with('detailForInterview')->get();
            }
        }
        $data->transform(function ($data) {
            return [
                'id' => isset($data->id) ? $data->id : null,
                'event_date' => isset($data->event_date) ? $data->event_date : null,
                'event_name' => isset($data->event_name) ? $data->event_name : null,
                'type' => isset($data->type) ? $data->type : null,
                'leads' => isset($data->detailForInterview) ? $data->detailForInterview : null,

            ];
        });

        return response()->json([
            'ApiName' => 'Event_list',
            'status' => true,
            'message' => 'Successfully.',
            'office_name' => isset($office->office_name) ? $office->office_name : 'all',
            'your_location' => isset($state->name) ? $state->name : 'all',
            'start_date' => $today,
            'inter_view' => $todayInterView,
            'data' => $data,
        ], 200);
    }

    public function hiringEventList(Request $request)
    {

        $Validator = Validator::make(
            $request->all(),
            [
                'start_date' => 'required',
                'end_date' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $user_id = Auth::user()->id;
        $state_id = Auth::user()->state_id;
        $office_id = isset($request->office_id) ? $request->office_id : null;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $state = State::where('id', $state_id)->first();
        if (isset($office_id)) {
            $office = Locations::where('id', $office_id)->first();
        } else {
            $office = '';
        }
        if (isset($office_id) && $office_id != 'all') {
            $today = EventCalendar::where('office_id', $office_id)->where('type', 'Hired')->whereBetween('event_date', [$startDate, $endDate])->count();
            $todayInterView = EventCalendar::where('office_id', $office_id)->whereBetween('event_date', [$startDate, $endDate])->count();
            $data = EventCalendar::with('detailForInterview')->where('office_id', $office_id)->whereBetween('event_date', [$startDate, $endDate])->get();

        } else {
            $today = EventCalendar::where('type', 'Hired')->whereBetween('event_date', [$startDate, $endDate])->count();
            $todayInterView = EventCalendar::where('type', 'Interview')->whereBetween('event_date', [$startDate, $endDate])->count();
            $data = EventCalendar::with('detailForInterview')->whereBetween('event_date', [$startDate, $endDate])->get();
        }

        $data->transform(function ($data) {
            return [
                'id' => isset($data->id) ? $data->id : null,
                'event_date' => isset($data->event_date) ? $data->event_date : null,
                'event_name' => isset($data->event_name) ? $data->event_name : null,
                'type' => isset($data->type) ? $data->type : null,
                'leads' => isset($data->detailForInterview) ? $data->detailForInterview : null,

            ];
        });

        return response()->json([
            'ApiName' => 'Event_list',
            'status' => true,
            'message' => 'Successfully.',
            'office_name' => isset($office->office_name) ? $office->office_name : 'all',
            // 'your_location' => isset($state->name)?$state->name:'all',
            'start_date' => $today,
            'inter_view' => $todayInterView,
            'data' => $data,
        ], 200);
    }
}
