<?php

namespace App\Http\Controllers\API\management;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\ManagementTeam;
use App\Models\ManagementTeamMember;
use App\Models\Positions;
use App\Models\User;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    // public function index(Request $request)
    // {
    //         $user = auth('api')->user();
    //         $your_location = $user->location;
    //         // dd($your_location);
    //         $data = User::with('positionDetail', 'state', 'city')->where('manager_id', $user->id)->paginate(10);
    //         $position = Positions::get();
    //         $data3 = array();
    //         foreach ($position as $key => $value) {
    //             $data2 = User::where('position_id', $value->id)->where('manager_id', $user->id)->count();
    //             $data3[$value->position_name] = $data2;
    //         }
    //         //dd($data3);
    //         $data->transform(function ($data) {
    //             return [
    //                 'id' => $data->id,
    //                 'image' => isset($data->image) ? $data->image : 'NA',
    //                 'name' => isset($data->first_name, $data->last_name) ? $data->first_name . " " . $data->last_name : 'NA',
    //                 // 'last_name'      => isset($data->last_name) ? $data->last_name: 'NA',
    //                 'position' => isset($data->positionDetail->name) ? $data->positionDetail->name : 'NA',
    //                 'location' => isset($data->city->name, $data->state->name) ? $data->city->name . ',' . $data->state->name  : 'NA', #isset($data->city->name) ? $data->city->name : 'NA',
    //                 'phone' => isset($data->mobile_no) ? $data->mobile_no  : 'NA',
    //                 'email' => isset($data->email) ? $data->email : 'NA',
    //             ];
    //         });
    //         return response()->json([
    //             'ApiName' => 'list-management-employee',
    //             'status' => true,
    //             'message' => 'Successfully.',
    //             'your_location' => $your_location,
    //             'position' => $data3,
    //             'data' =>  $data,
    //         ], 200);
    //     }

    // list all team and members
    public function indexOLd(Request $request): JsonResponse
    {

        if ($request->has('filter') && ! empty($request->input('filter')) && $request->input('location') != 'all' && empty($request->input('office_id'))) {
            $data = ManagementTeam::query()
                ->when($request->filter, function ($query, $search) {
                    $query->where('team_name', 'like', '%'.$search.'%')
                        ->orWhere('type', 'like', '%'.$search.'%')
                        ->orWhere('location_id', 'like', '%'.$search.'%');
                })
                ->where('location_id', 'like', '%'.$request->location.'%')
            // ->orWhereHas('State', function ($query) use ($request) {
            //     $query->where('name' , 'like', '%' . $request->location . '%');
            // })
                ->orWhereHas('user', function ($query) use ($request) {
                    $query->where('first_name', 'like', '%'.$request->filter.'%');
                })
                ->with(['Team', 'State', 'user'])
                ->orderBy('id', 'DESC')
                ->get();

            $total_team = ManagementTeam::query()
                ->when($request->filter, function ($query, $search) {
                    $query->where('team_name', 'like', '%'.$search.'%')
                        ->orWhere('type', 'like', '%'.$search.'%')
                        ->orWhere('location_id', 'like', '%'.$search.'%');
                })
                ->where('location_id', 'like', '%'.$request->location.'%')
            // ->orWhereHas('State', function ($query) use ($request) {
            //     $query->where('name' , 'like', '%' . $request->location . '%');
            // })
                ->orWhereHas('user', function ($query) use ($request) {
                    $query->where('first_name', 'like', '%'.$request->filter.'%');
                })
                ->with(['Team', 'State', 'user'])
                ->count();

            $teams = [];
            $members = [];
            $closer = [];
            $setter = [];
            foreach ($data as $key => $value) {
                $teams = $value->team;
                foreach ($teams as $team) {
                    $members[] = $team->member;
                }
            }
            foreach ($members as $key => $seterCloser) {

                foreach ($seterCloser as $key => $value) {

                    if ($value->position_id == 2) {
                        $closer[] = $value->position_id;
                    }
                    if ($value->position_id == 3) {
                        $setter[] = $value->position_id;
                    }
                }
            }
        } elseif ($request->has('filter') && ! empty($request->input('filter')) && $request->input('location') == 'all') {
            $data = ManagementTeam::query()
                ->when($request->filter, function ($query, $search) {
                    $query->where('team_name', 'like', '%'.$search.'%')
                        ->orWhere('type', 'like', '%'.$search.'%');
                })
                ->orWhereHas('user', function ($query) use ($request) {
                    $query->where('first_name', 'like', '%'.$request->filter.'%');
                })
                ->with(['Team', 'State', 'user'])
                ->orderBy('id', 'DESC')
                ->get();
            $total_team = ManagementTeam::query()
                ->when($request->filter, function ($query, $search) {
                    $query->where('team_name', 'like', '%'.$search.'%')
                        ->orWhere('type', 'like', '%'.$search.'%');
                })
                ->orWhereHas('user', function ($query) use ($request) {
                    $query->where('first_name', 'like', '%'.$request->filter.'%');
                })
                ->with(['Team', 'State', 'user'])->count();

            $teams = [];
            $members = [];
            $closer = [];
            $setter = [];
            foreach ($data as $key => $value) {
                $teams = $value->team;
                foreach ($teams as $team) {
                    $members[] = $team->member;
                }
            }
            foreach ($members as $key => $seterCloser) {

                foreach ($seterCloser as $key => $value) {
                    if ($value->position_id == 2) {
                        $closer[] = $value->position_id;
                    }
                    if ($value->position_id == 3) {
                        $setter[] = $value->position_id;
                    }
                }
            }
        } elseif ($request->has('filter') && empty($request->input('filter')) && $request->input('location') == 'all' && empty($request->input('office_id'))) {
            $data = ManagementTeam::query()
                ->with(['Team', 'State', 'user'])
                ->orderBy('id', 'DESC')
                ->get();

            $teams = [];
            $members = [];
            $closer = [];
            $setter = [];
            foreach ($data as $key => $value) {
                $teams = $value->team;
                foreach ($teams as $team) {
                    $members[] = $team->member;
                }
            }
            foreach ($members as $key => $seterCloser) {

                foreach ($seterCloser as $key => $value) {
                    if ($value->position_id == 2) {
                        $closer[] = $value->position_id;
                    }
                    if ($value->position_id == 3) {
                        $setter[] = $value->position_id;
                    }
                }
            }
            $total_team = ManagementTeam::count();
        } elseif ($request->has('filter') && empty($request->input('filter')) && $request->input('location') != 'all' && ! empty($request->input('office_id'))) {
            $data = ManagementTeam::query()
                ->where('location_id', 'like', '%'.$request->location.'%')
                ->where('office_id', 'like', '%'.$request->office_id.'%')
                ->with(['Team', 'State', 'user'])
                ->get();
            // print_r($data); die();
            $total_team = ManagementTeam::where('location_id', 'like', '%'.$request->location.'%')->where('office_id', 'like', '%'.$request->office_id.'%')->count();

            $teams = [];
            $members = [];
            $closer = [];
            $setter = [];
            foreach ($data as $key => $value) {
                $teams = $value->team;
                foreach ($teams as $team) {
                    $members[] = $team->member;
                }
            }
            foreach ($members as $key => $seterCloser) {

                foreach ($seterCloser as $key => $value) {
                    if ($value->position_id == 2) {
                        $closer[] = $value->position_id;
                    }
                    if ($value->position_id == 3) {
                        $setter[] = $value->position_id;
                    }
                }
            }

        }
        if ($request->has('filter') && ! empty($request->input('filter')) && $request->input('location') != 'all' && ! empty($request->input('office_id'))) {
            $data = ManagementTeam::query()
                ->when($request->filter, function ($query, $search) {
                    $query->where('team_name', 'like', '%'.$search.'%')
                        ->orWhere('type', 'like', '%'.$search.'%')
                        ->orWhere('office_id', 'like', '%'.$search.'%')
                        ->orWhere('location_id', 'like', '%'.$search.'%');
                })
                ->where('location_id', 'like', '%'.$request->location.'%')
                ->where('office_id', 'like', '%'.$request->office_id.'%')
            // ->orWhereHas('State', function ($query) use ($request) {
            //     $query->where('name' , 'like', '%' . $request->location . '%');
            // })
                ->orWhereHas('user', function ($query) use ($request) {
                    $query->where('first_name', 'like', '%'.$request->filter.'%');
                })
                ->with(['Team', 'State', 'user', 'office'])
                ->get();

            $total_team = ManagementTeam::query()
                ->when($request->filter, function ($query, $search) {
                    $query->where('team_name', 'like', '%'.$search.'%')
                        ->orWhere('type', 'like', '%'.$search.'%')
                        ->orWhere('office_id', 'like', '%'.$search.'%')
                        ->orWhere('location_id', 'like', '%'.$search.'%');
                })
                ->where('location_id', 'like', '%'.$request->location.'%')
            // ->orWhereHas('State', function ($query) use ($request) {
            //     $query->where('name' , 'like', '%' . $request->location . '%');
            // })
                ->orWhereHas('user', function ($query) use ($request) {
                    $query->where('first_name', 'like', '%'.$request->filter.'%');
                })
                ->with(['Team', 'State', 'user', 'office'])
                ->count();

            $teams = [];
            $members = [];
            $closer = [];
            $setter = [];
            foreach ($data as $key => $value) {
                $teams = $value->team;
                foreach ($teams as $team) {
                    $members[] = $team->member;
                }
            }
            foreach ($members as $key => $seterCloser) {

                foreach ($seterCloser as $key => $value) {
                    if ($value->position_id == 2) {
                        $closer[] = $value->position_id;
                    }
                    if ($value->position_id == 3) {
                        $setter[] = $value->position_id;
                    }
                }
            }
        } elseif ($request->has('filter') && ! empty($request->input('filter')) && $request->input('location') == 'all' && ! empty($request->input('office_id'))) {
            $data = ManagementTeam::query()
                ->when($request->filter, function ($query, $search) {
                    $query->where('team_name', 'like', '%'.$search.'%')
                        ->orWhere('type', 'like', '%'.$search.'%')
                        ->orWhere('office_id', 'like', '%'.$search.'%');
                })
                ->orWhereHas('user', function ($query) use ($request) {
                    $query->where('first_name', 'like', '%'.$request->filter.'%');
                })
                ->with(['Team', 'State', 'user'])
                ->get();
            $total_team = ManagementTeam::query()
                ->when($request->filter, function ($query, $search) {
                    $query->where('team_name', 'like', '%'.$search.'%')
                        ->orWhere('type', 'like', '%'.$search.'%')
                        ->orWhere('office_id', 'like', '%'.$search.'%');
                })
                ->orWhereHas('user', function ($query) use ($request) {
                    $query->where('first_name', 'like', '%'.$request->filter.'%');
                })
                ->with(['Team', 'State', 'user'])->count();

            $teams = [];
            $members = [];
            $closer = [];
            $setter = [];
            foreach ($data as $key => $value) {
                $teams = $value->team;
                foreach ($teams as $team) {
                    $members[] = $team->member;
                }
            }
            foreach ($members as $key => $seterCloser) {

                foreach ($seterCloser as $key => $value) {
                    if ($value->position_id == 2) {
                        $closer[] = $value->position_id;
                    }
                    if ($value->position_id == 3) {
                        $setter[] = $value->position_id;
                    }
                }
            }
        } elseif ($request->has('filter') && empty($request->input('filter')) && $request->input('location') == 'all' && empty($request->input('office_id'))) {
            $data = ManagementTeam::query()
                ->with(['Team', 'State', 'user'])
                ->get();

            $teams = [];
            $members = [];
            $closer = [];
            $setter = [];
            foreach ($data as $key => $value) {
                $teams = $value->team;
                foreach ($teams as $team) {
                    $members[] = $team->member;
                }
            }
            foreach ($members as $key => $seterCloser) {

                foreach ($seterCloser as $key => $value) {
                    if ($value->position_id == 2) {
                        $closer[] = $value->position_id;
                    }
                    if ($value->position_id == 3) {
                        $setter[] = $value->position_id;
                    }
                }
            }
            $total_team = ManagementTeam::count();
        } elseif ($request->has('filter') && empty($request->input('filter')) && $request->input('location') != 'all' && empty($request->input('office_id'))) {

            $data = ManagementTeam::query()
                ->where('location_id', 'like', '%'.$request->location.'%')
                ->where('office_id', 'like', '%'.$request->location.'%')
                ->with(['Team', 'State', 'user'])
                ->orderBy('id', 'DESC')
                ->get();
            $total_team = ManagementTeam::where('location_id', 'like', '%'.$request->location.'%')->where('office_id', 'like', '%'.$request->office_id.'%')->count();

            $teams = [];
            $members = [];
            $closer = [];
            $setter = [];
            foreach ($data as $key => $value) {
                $teams = $value->team;
                foreach ($teams as $team) {
                    $members[] = $team->member;
                }
            }
            foreach ($members as $key => $seterCloser) {

                foreach ($seterCloser as $key => $value) {
                    if ($value->position_id == 2) {
                        $closer[] = $value->position_id;
                    }
                    if ($value->position_id == 3) {
                        $setter[] = $value->position_id;
                    }
                }
            }

        } else {
            $officeId = Auth::user()->office_id;
            $data = ManagementTeam::with('Team', 'State', 'user')
                ->where('location_id', 'like', '%'.$request->location.'%')
        //    ->where('office_id',$officeId)
                ->orderBy('id', 'DESC')->get();
            $total_team = ManagementTeam::where('location_id', 'like', '%'.$request->location.'%')->count();

            $closer = [];
            $setter = [];
        }
        $position = Positions::whereIn('id', [2, 3])->get();
        $closerSetterCount = [];
        $ration = [];

        foreach ($position as $key => $value) {
            $closerSetter = User::where('position_id', $value->id)->whereNot('team_id', null)->count();
            $closerSetterCount[$value->position_name] = $closerSetter;
            $ration[] = $closerSetter;
        }
        $newData = [];
        foreach ($data as $key => $value) {
            $newData[$key] = [
                'id' => isset($value->id) ? $value->id : null,
                'team_name' => isset($value->team_name) ? $value->team_name : null,
                'location_id' => isset($value->State->id) ? $value->State->id : null,
                'location_name' => isset($value->State->name) ? $value->State->name : null,
                'team_lead_id' => isset($value->team_lead_id) ? $value->team_lead_id : null,
                'team_lead_name' => isset($value->user[0]->first_name) ? $value->user[0]->first_name : null,
                'people' => $value->team->count(),
            ];

            $teamData = [];
            foreach ($value->Team as $key1 => $team) {
                if ($team->team_lead_id == $team->team_member_id) {
                    $user_id = $team->member[0]->id;
                    $position = User::with('positionDetail')->where('id', $user_id)->first();
                    $teamData[$key1] = $team->member[0];
                    $teamData[$key1]['position'] = $position->positionDetail->position_name;

                    // $teamData[$key1]['type'] = $value->type;
                } else {
                    $user_id = $team->member[0]->id;
                    $position = User::with('positionDetail')->where('id', $user_id)->first();
                    $teamData[$key1] = $team->member[0];
                    $teamData[$key1]['position'] = $position->positionDetail->position_name;
                }

                if ($position->positionDetail->position_name == 'Closer') {
                    $closer[] = $key1;
                }
                if ($position->positionDetail->position_name == 'Setter') {
                    $setter[] = $key1;
                }

            }

            $newData[$key]['members'] = $teamData;

        }
        $num1 = count($closer);
        $num2 = count($setter);

        // $setter = isset($closerSetterCount['Setter']) ? $closerSetterCount['Setter'] :0;
        // $closer = isset($closerSetterCount['Closer']) ? $closerSetterCount['Closer'] :0;

        for ($i = $num2; $i > 1; $i--) {
            if (($num1 % $i) == 0 && ($num2 % $i) == 0) {
                $num1 = $num1 / $i;
                $num2 = $num2 / $i;
            }
        }
        $closer1 = count($closer);
        $setter1 = count($setter);

        $_clo = $closer1;
        $_set = $setter1;
        while ($_set != 0) {

            $remainder = $_clo % $_set;
            $_clo = $_set;
            $_set = $remainder;
        }
        $abstract = abs($_clo);

        $ratio = ($setter1 / $abstract).':'.($closer1 / $abstract);

        return response()->json([
            'status' => true,
            'message' => 'Successfully.',
            'total_team' => $total_team,
            // 'closer' => count($closer),
            // 'setter' => count($setter),
            // 'ratio' => $num1.":".$num2,
            'closer' => count($closer),
            'setter' => count($setter),
            'ratio' => $ratio,
            'data' => $newData,
        ], 200);
    }

    public function index(Request $request)
    {
        $managementTeam = ManagementTeam::where('type', 'lead');
        if ($request->has('office_id') && $request->office_id != 'all') {
            $managementTeam->where(function ($query) use ($request) {
                return $query->where('office_id', $request->office_id);
            });
        }

        if ($request->has('filter')) {
            $managementTeam->where(function ($query) use ($request) {
                return $query->where('team_name', 'LIKE', '%'.$request->filter.'%')
                    ->orWhere('type', 'LIKE', '%'.$request->filter.'%');
            });
        }
        $data = $managementTeam->with(['Team.member' => function ($q) {
            $q->where(['dismiss' => 0, 'terminate' => 0, 'contract_ended' => 0]);
        }, 'State', 'user' => function ($q) {
            $q->where(['dismiss' => 0, 'terminate' => 0, 'contract_ended' => 0]);
        }, 'Team.member.positionDetailTeam', 'Team.member.subpositionDetail'])->orderBy('id', 'DESC')->get();
        $totalTeam = $managementTeam->count();

        $closer = [];
        $setter = [];
        $manager = [];
        $closerCount = [];
        $setterCount = [];
        $managerCount = [];
        $newData = [];
        foreach ($data as $key => $value) {
            if (isset($value->user[0]->sub_position_id)) {
                $position = Positions::where('id', $value->user[0]->sub_position_id)->first();
            }
            $leadCount = 0;
            if (isset($value->user[0]->id)) {
                $leadCount = 1;
            }
            $positionName = null;
            if (isset($position->parent_id) && $position->parent_id == 2) {
                $positionName = 'Closer';
            } elseif (isset($position->parent_id) && $position->parent_id == 3) {
                $positionName = 'Setter';
            }
            $newData[$key] = [
                'id' => isset($value->id) ? $value->id : null,
                'team_name' => isset($value->team_name) ? $value->team_name : null,
                'office_id' => isset($value->office_id) ? $value->office_id : null,
                'team_lead_id' => isset($value->team_lead_id) ? $value->team_lead_id : null,
                'team_lead_name' => isset($value->user[0]->first_name) ? $value->user[0]->first_name : null,
                'position_id' => isset($value->user[0]->position_id) ? $value->user[0]->position_id : null,
                'sub_position_id' => isset($value->user[0]->sub_position_id) ? $value->user[0]->sub_position_id : null,
                'position' => isset($position->parent_id) && ($position->parent_id == null) ? $position->position_name : $positionName,
                'sub_position' => isset($position->position_name) ? $position->position_name : null,
            ];

            if (isset($position->position_name) && $position->position_name == 'Closer') {
                $closerCount[] = $key;
            }
            if (isset($position->position_name) && $position->position_name == 'Setter') {
                $setterCount[] = $key;
            }
            if (isset($value->user[0]->is_manager)) {
                $managerCount[] = $key;
            }

            $teamKey = 0;
            $teamData = [];
            foreach ($value->Team as $key1 => $team) {
                if (isset($team->member[0]->id)) {
                    $position = isset($team->member[0]) ? $team->member[0] : null;
                    if (isset($position) && $position != null) {
                        $teamS3image = null;
                        if (isset($team->member[0]->image) && $team->member[0]->image != null) {
                            $teamS3image = s3_getTempUrl(config('app.domain_name').'/'.$team->member[0]->image);
                        }
                        $teamData[$teamKey] = $team->member[0];
                        $teamData[$teamKey]['image_s3'] = $teamS3image;
                        $teamData[$teamKey]['position'] = $position?->positionDetailTeam?->position_name;
                        $teamData[$teamKey]['sub_position'] = $position?->subpositionDetail?->position_name;
                        $teamKey++;
                    }
                }

                if (isset($position->subpositionDetail->parent_id) && $position->subpositionDetail->parent_id == '2') {
                    $closer[] = $key1;
                }
                if (isset($position->subpositionDetail->parent_id) && $position->subpositionDetail->parent_id == '3') {
                    $setter[] = $key1;
                }
                if (isset($position->is_manager)) {
                    $manager[] = $key1;
                }
            }
            $newData[$key]['members'] = $teamData;
            $newData[$key]['people'] = count($teamData) + $leadCount;
        }

        $num1 = count($closer);
        $num2 = count($setter);
        $num3 = count($closerCount);
        $num4 = count($setterCount);
        $num5 = count($manager);
        $num6 = count($managerCount);
        if (empty($newData)) {
            return response()->json([
                'status' => true,
                'message' => 'Successfully.',
                'total_team' => isset($totalTeam) ? $totalTeam : 0,
                'closer' => $num1 + $num3,
                'setter' => $num2 + $num4,
                'ratio' => isset($ratio) ? $ratio : 0,
                'manager' => $num5 + $num6,
                'data' => [],
            ]);
        }

        $closer1 = $num1 + $num3;
        $setter1 = $num2 + $num4;
        $manager1 = $num5 + $num6;
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $_set = $manager1;
            $_clo = $closer1;
            while ($_set != 0) {
                $remainder = $_clo % $_set;
                $_clo = $_set;
                $_set = $remainder;
            }

            $abstract = abs($_clo);
            if ($closer1 != 0 && $manager1 != 0) {
                $ratio = ($manager1 / $abstract).':'.($closer1 / $abstract);
            }
        } else {
            $_clo = $closer1;
            $_set = $setter1;
            while ($_set != 0) {
                $remainder = $_clo % $_set;
                $_clo = $_set;
                $_set = $remainder;
            }

            $abstract = abs($_clo);
            if ($closer1 != 0 && $setter1 != 0) {
                $ratio = ($setter1 / $abstract).':'.($closer1 / $abstract);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Successfully.',
            'total_team' => isset($totalTeam) ? $totalTeam : 0,
            'closer' => $closer1,
            'setter' => $setter1,
            'ratio' => isset($ratio) ? $ratio : 0,
            'manager' => $manager1,
            'data' => $newData,
        ]);
    }

    // create team and store member
    public function store(Request $request)
    {
        // return $request;
        if (! empty($request->team_name)) {
            //   $value = ManagementTeam::where('team_lead_id',$request->team_lead_id)->first();
            $member = ManagementTeamMember::select('team_member_id')->where('team_member_id', '!=', null)->get();

            //   foreach($member as $members)
            //   {
            //     if(in_array($members->team_member_id, $request->team_members) == true){
            //         return response()->json([
            //             'ApiName' => 'update-management-team-member',
            //             'status' => false,
            //             'message' => 'Team member id '.$members->team_member_id.' already exit'
            //         ], 400);
            //     }
            //   }

            //  $team_lead_id = $request->team_lead_id;
            //  $team_members =$request->team_members;
            // if(in_array($team_lead_id, $team_members) == true){
            //     return response()->json([
            //         'ApiName' => 'update-management-team-member',
            //         'status' => false,
            //         'message' => 'the Team lead id is not same as team member.'
            //     ], 400);
            // }

            //  if($value == Null)
            //  {
            $data = ManagementTeam::create(

                [
                    'team_lead_id' => $request->team_lead_id,
                    // 'location_id'   => $request->location_id,
                    'office_id' => $request->office_id,
                    'team_name' => $request->team_name,
                ]
            );
            // ManagementTeamMember::create(
            //     [
            //         'team_id' => $data->id,
            //         'team_lead_id'  => $request->team_lead_id,
            //         'team_member_id'  => $request->team_lead_id,
            //     ]
            //     );
            $user = User::where('id', $request->team_lead_id)->first();
            if (! empty($user)) {
                $user->team_id = $data->id;
                $user->team_lead_status = 1;
                $user->save();
            }

            foreach ($request->team_members as $value) {
                if ($value != $request->team_lead_id) {
                    $values = $value;
                } else {
                    $values = null;
                }
                ManagementTeamMember::create(
                    [
                        'team_id' => $data->id,
                        'team_lead_id' => $request->team_lead_id,
                        'team_member_id' => $values,
                    ]
                );
            }
            $user1 = User::whereIn('id', $request->team_members)->update(['team_id' => $data->id]);
            // }else{

            //     return response()->json([
            //         'ApiName' => 'store-management-team',
            //         'status' => false,
            //         'message' => 'team lead id already exist.',
            //     ], 400);

            // }
            return response()->json([
                'ApiName' => 'store-management-team',
                'status' => true,
                'message' => 'Successfully.',
            ], 200);
        }
    }

    // public function edit_old(Request $request)
    // {
    //     // return $request;
    //     $data = ManagementTeam::where('id', $request->id)->first();
    //     $team_lead_id = $request->team_lead_id;
    //     $team_members =$request->team_members;

    //     if(in_array($team_lead_id, $team_members) == true){
    //         return response()->json([
    //             'ApiName' => 'update-management-team-member',
    //             'status' => false,
    //             'message' => 'the Team lead id is not same as team member.'
    //         ], 400);
    //     }

    //     if(!$data == NULL && !$request == NULL)
    //     {

    //         $data->team_name   = $request['team_name'];
    //         $data->location_id   = $request['location_id'];
    //         $data->team_lead_id   = $request['team_lead_id'];
    //         $data->save();

    //         foreach($request->team_members as $value)
    //         {
    //             $data1 = ManagementTeamMember::where('team_member_id',$value)->where('team_lead_id',$team_lead_id)->first();
    //             if($data1 == null){
    //                 ManagementTeamMember::create(
    //                     [
    //                         'team_id'  =>$data->id,
    //                         'team_lead_id' => $request->team_lead_id,
    //                         'team_member_id'  => $value,
    //                         'office_id' => $request->location_id
    //                     ]
    //                     );
    //             }
    //         }
    //         return response()->json([
    //             'ApiName' => 'update-management-team-member',
    //             'status' => true,
    //             'message' => 'Successfully.',
    //             'data'=>$data->id
    //         ], 200);
    //     }
    // }

    // team update start
    public function edit(Request $request): JsonResponse
    {
        $data = ManagementTeam::where('id', $request->id)->first();

        if (! $data) {
            return response()->json([
                'ApiName' => 'update-management-team-member',
                'status' => false,
                'message' => 'Team Id Not Found',
            ], 400);
        }

        $existingTeamMembers = ManagementTeamMember::where('team_id', $data->id)->count();

        if ($existingTeamMembers > 0 && $data->office_id != $request->office_id) {
            return response()->json([
                'ApiName' => 'update-management-team-member',
                'status' => false,
                'message' => 'Office cannot be changed because the team has members.',
            ], 400);
        }

        if ($data->team_lead_id != 0 && $data->office_id != $request->office_id) {
            return response()->json([
                'ApiName' => 'update-management-team-member',
                'status' => false,
                'message' => 'Office cannot be changed because the team lead is assigned.',
            ], 400);
        }

        if ($request) {
            $data->team_name = $request['team_name'];
            $data->location_id = $request['location_id'];

            $data->team_lead_id = $request['team_lead_id'] ?? 0;

            if ($existingTeamMembers == 0 && $data->team_lead_id == 0) {
                $data->office_id = $request['office_id'];
            }

            $data->save();

            ManagementTeamMember::where('team_id', $data->id)->delete();

            foreach ($request->team_members as $value) {
                $values = ($value != $request->team_lead_id) ? $value : null;
                ManagementTeamMember::create([
                    'team_id' => $data->id,
                    'team_lead_id' => $request->team_lead_id,
                    'team_member_id' => $values,
                ]);
            }

            User::whereIn('id', $request->team_members)->update(['team_id' => $data->id]);

            return response()->json([
                'ApiName' => 'update-management-team-member',
                'status' => true,
                'message' => 'Successfully updated team and members.',
            ], 200);
        }
    }
    // team update end

    // delete emplyoee by team
    public function delete(Request $request): JsonResponse
    {
        $management = ManagementTeamMember::where('team_member_id', $request->member_id)->where('team_id', $request->team_id)->first();
        if (! empty($management)) {
            $management = ManagementTeamMember::find($management->id)->delete();

            return response()->json([
                'ApiName' => 'delete-management-team-member',
                'status' => true,
                'message' => 'Successfully.',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'delete-management-team-member',
                'status' => false,
                'message' => 'Id not found.',
            ], 400);
        }
    }

    // / drop down team name
    public function dropdown()
    {
        $data = ManagementTeam::get();
        $data->transform(function ($data) {
            return [
                'id' => $data->id,
                'team_name' => isset($data->team_name) ? $data->team_name : 'NA',
            ];
        });

        // return $data;
        return response()->json([
            'ApiName' => 'Team list for dropdown',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    // search by team name
    public function selectTeam($id)
    {
        // return $id;
        $data = ManagementTeam::with('Team', 'State', 'user')->where('id', $id)->first();
        $total_team = ManagementTeam::where('id', $id)->count();

        $members = [];
        foreach ($data->team as $team) {
            // dd($team->team_lead_id);
            $position = User::with('positionDetail')->where('id', $team->member[0]->id)->first();
            // dd($position->positionDetail->name);
            if ($team->team_lead_id == $team->team_member_id) {
                $type = ManagementTeam::where('team_lead_id', $team->team_member_id)->first();
                // dd($type->type);
                $members[] =
                [
                    // dd($override->overrides_detail),
                    'id' => $team->member[0]->id,
                    'name' => isset($team->member[0]->first_name , $team->member[0]->last_name) ? $team->member[0]->first_name.' '.$team->member[0]->last_name : 'NA',
                    'positon' => $position->positionDetail->position_name,
                    'type' => $type->type,
                ];
            } else {
                $members[] =
                    [
                        // dd($override->overrides_detail),
                        'id' => $team->member[0]->id,
                        'name' => isset($team->member[0]->first_name , $team->member[0]->last_name) ? $team->member[0]->first_name.' '.$team->member[0]->last_name : 'NA',
                        'positon' => $position->positionDetail->position_name,
                    ];
            }
        }

        $data1[] =
        [
            // dd($data->team)->count(),
            'id' => $data->id,
            'team_name' => isset($data->team_name) ? $data->team_name : 'NA',
            'people' => $data->team = ManagementTeamMember::where('team_id', $data->id)->count(),
            'member' => $members,
        ];

        return response()->json([
            'status' => true,
            'message' => 'Successfully.',
            'total_team' => $total_team,
            'data' => $data1,
        ], 200);
    }

    // search by emplyoee name

    public function search($name)
    {
        //    return $name;
        $result = user::where('first_name', 'LIKE', '%'.$name.'%')->orWhere('last_name', 'LIKE', '%'.$name.'%')
            ->get();
        // dd($result);

        foreach ($result as $value) {
            if (! $value == null) {
                // dd('rakesh');
                $data6 = ManagementTeamMember::where('team_member_id', $result[0]->id)->first();
                $data7 = ManagementTeam::where('id', $data6->team_id)->first();

                if ($data6->team_member_id == $data7->team_lead_id) {
                    $members[] = ['id' => $result[0]->id, 'name' => $result[0]->first_name.' '.$result[0]->last_name, 'image' => $result[0]->image, 'type' => $data7->type];
                } else {
                    $members[] = ['id' => $result[0]->id, 'name' => $result[0]->first_name.' '.$result[0]->last_name, 'image' => $result[0]->image];
                }
                // dd($data6);
                $data1[] =
                [
                    'id' => $data7->id,
                    'team_name' => isset($data7->team_name) ? $data7->team_name : 'NA',
                    'people' => $data6->team = ManagementTeamMember::where('id', $data6->id)->count(),
                    'member' => $members,
                ];

                return response()->json([
                    'ApiName' => 'search-management-employee',
                    'status' => true,
                    'message' => 'search Successfully.',
                    // 'total_team' =>  $total_team,
                    'data' => $data1,
                ], 200);

            }

        }

        return response()->json([
            'ApiName' => 'search-management-employee',
            'status' => false,
            'message' => '',
            'data' => null,
        ], 400);

    }

    public function deleteTeamWhenMemeberNotadded($id): JsonResponse
    {
        $data = ManagementTeam::where('id', $id)
            ->first();
        if ($data == null && empty($data)) {
            return response()->json([
                'ApiName' => 'Delete team when team member not added',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        } else {
            $teamData = ManagementTeamMember::where('team_id', $data->id)
                ->get();
            $teamCount = $teamData->count();
            foreach ($teamData as $team) {
                if (($team->team_lead_id == $team->team_member_id) && $teamCount == 1) {
                    $data->delete();
                    $teamData = ManagementTeamMember::where('team_id', $data->id)->delete();

                    return response()->json([
                        'ApiName' => 'Delete team when team member not added',
                        'status' => true,
                        'message' => 'Team Deleted Successfully.',
                        'data' => [],
                    ], 200);
                } else {
                    return response()->json([
                        'ApiName' => 'Delete team when team member not added',
                        'status' => true,
                        'message' => 'Team Memeber are available',
                        'data' => [],
                    ], 400);

                }
            }
        }
    }

    public function deleteTeam($id): JsonResponse
    {
        $data = ManagementTeam::find($id)->delete();
        $teamData = ManagementTeamMember::where('team_id', $id)->delete();

        return response()->json([
            'ApiName' => 'Delete team ',
            'status' => true,
            'message' => 'Delete team  successfully',
        ], 200);

    }
}

// public function getLocation()
// {
//     $data = User::get();

//     return $data;

// }

// if(!$value == NULL  )
// {
//      dd('rakesh');
//     $data = ManagementTeamMember::where('team_member_id',$result[0]->id)->first();
// if (count($result)) {
// return response()->json([
//     'ApiName' => 'search-management-employee',
//     'status' => true,
//     'message' => 'search Successfully.',
//     'data' => $data,
// ], 200);
// }} else {
// return response()->json([
//     'ApiName' => 'search-management-employee',
//     'status' => false,
//     'message' => '',
//     'data' => null,
// ], 400);
