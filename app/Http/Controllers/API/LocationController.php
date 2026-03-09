<?php

namespace App\Http\Controllers\API;

use App\Core\Traits\EvereeTrait;
use App\Exports\LocationExport;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateAlertJob;
use App\Models\AdditionalLocations;
use App\Models\Citis;
use App\Models\CompanyProfile;
use App\Models\Crms;
use App\Models\LocationRedlineHistory;
use App\Models\Locations;
use App\Models\SalesMaster;
use App\Models\State;
use App\Models\User;
use Carbon\Carbon;
use Excel;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use transform;
use Validator;

class LocationController extends Controller
{
    use EvereeTrait;

    public function __construct(Locations $locations)
    {
        $this->locations = $locations;
    }
    // public function index(Request $request)
    // {
    //     //return  $location = Locations::get();
    //     //$locations = Locations::with('State', 'Citis')->paginate(10);
    //     // $location = Locations::with('State', 'Citis')->paginate(10);

    //     // $locations = Locations::with('State', 'Cities','marketing')->orderBy('id', 'DESC')->get();
    //     $filter =$request->filter;
    //     $search =$request->search;
    //     // if($request->order_by_redline_standard){
    //     //     $order_by= $request->order_by_redline_standard;
    //     //     $column= "redline_standard";
    //     // }elseif($request->order_by_state){
    //     //     $order_by= $request->order_by_state;
    //     //     $column= "state_id";
    //     // }
    //     // else{
    //     //     $order_by = 'ASC';
    //     //     $column= "id";
    //     // }
    //     // $type = isset($request->type)?$request->type:null;
    //     // $pagination = 10; // $request->pagination;

    //     //$locations = Locations::orderBy($column,$order_by);

    //     // if ($request->office_status) {

    //     //     $locations->where('office_status',$request->office_status)
    //     //     ->where(function($query) use ($type)  {
    //     //         if($type == "Redline") {
    //     //             $query->where('type', $type);
    //     //         }
    //     //         if($type == "Office") {
    //     //             $query->where('type', $type);
    //     //         }
    //     //      })
    //     //      ->orderBy($column,$order_by)->paginate($pagination);
    //     // } elseif ($request->state_id) {
    //     //     $locations->where('state_id',$request->state_id)
    //     //     ->where(function($query) use ($type)  {
    //     //         if($type == "Redline") {
    //     //             $query->where('type', $type);
    //     //         }
    //     //         if($type == "Office") {
    //     //             $query->where('type', $type);
    //     //         }
    //     //      })
    //     //      ->orderBy($column,$order_by)->paginate($pagination);
    //     //  }
    //     //elseif($request->has('search')){
    //     //     $locations->whereHas(
    //     //         'State' , function ($query) use ($request){
    //     //             $query->where('name', 'LIKE', '%' .$request->search.'%');
    //     //         })
    //     //     ->orderBy($column,$order_by)->paginate($pagination);
    //     // }
    //     // else {

    //     //     $locations->where(function($query) use ($type)  {
    //     //         if($type == "Redline") {
    //     //             $query->where('type', $type);
    //     //         }
    //     //         if($type == "Office") {
    //     //             $query->where('type', $type);
    //     //         }
    //     //      });

    //     // }

    //     $locations = $this->locations->newQuery();
    //    // $locations = $locations->with('State', 'Cities','additionalRedline','createdBy')->paginate(config('app.paginate', 15));
    //     if ($request->has('search') && $request->input('search')!='')
    //     {
    //         $search=$request->input('search');

    //         $locations->orWhere(function($query) use ($request,$search) {
    //             $query->where('general_code', 'LIKE', '%' . $search . '%')
    //             ->orWhere('general_code', 'LIKE', '%' . $search . '%')
    //             ->orWhere('installation_partner', 'LIKE', '%' . $search . '%')
    //             ->orWhere('redline_standard', 'LIKE', '%' . $search . '%');

    //         });
    //         if ($request->has('state') && $request->input('state')==null) {
    //             if ($request->has('filter') && $request->input('filter')!=null)
    //             {
    //                 $filter =$request->input('filter');
    //                 if($filter == 'withInstallers')
    //                 {
    //                     $locations->where(function($query) use ($request,$search,$filter) {
    //                         $query->orWhereHas('State', function ($query) use ($search) {
    //                             $query->where('name' , 'LIKE', '%' .$search. '%')
    //                             ->where('installation_partner','!=','');
    //                             //->orWhere('state_code','LIKE', '%' .$search. '%');
    //                         });
    //                     });
    //                 }
    //                 if($filter == 'withWorkSiteId')
    //                 {

    //                     $locations->where(function($query) use ($request,$search) {
    //                         $query->orWhereHas('State', function ($query) use ($search) {
    //                             $query->where('name' , 'LIKE', '%' .$search. '%')
    //                             ->where('work_site_id','!=','')
    //                             ->where('type','!=','Redline');
    //                             //->orWhere('state_code','LIKE', '%' .$search. '%');
    //                         });
    //                     });
    //                 }
    //                 if($filter == 'withOnlyStandard')
    //                 {
    //                     //return 'Ram';die();
    //                     $locations->where(function($query) use ($request,$search) {
    //                         $query->orWhereHas('State', function ($query) use ($search) {
    //                             $query->where('name' , 'LIKE', '%' .$search. '%')
    //                             ->where('redline_standard','!=','')
    //                             ->where('type','Redline');
    //                             //->orWhere('state_code','LIKE', '%' .$search. '%');
    //                         });
    //                     });
    //                 }
    //             }else{
    //                 $locations->orWhere(function($query) use ($request,$search) {
    //                     $query->orWhereHas('State', function ($query) use ($search) {
    //                         $query->where('name' , 'LIKE', '%' .$search. '%');
    //                         //->orWhere('state_code','LIKE', '%' .$search. '%');
    //                     });
    //                 });
    //             }
    //         }
    //     }

    //     if ($request->has('filter') && $request->input('filter')!=null)
    //     {
    //       $filterData = $request->input('filter');
    //       if($filterData == 'withInstallers')
    //       {
    //         $locations->where(function($query) use ($request) {
    //             $query->where('installation_partner','!=','');

    //         });
    //        // $locations->where('installation_partner','!=','');

    //       }else
    //       if($filterData == 'withWorkSiteId')
    //       {
    //         $locations->where(function($query) use ($request) {
    //             $query->where('work_site_id','!=','')->where('type','!=','Redline');

    //         });
    //         //$locations->where('work_site_id','!=','')->where('type','!=','Redline');
    //       }
    //       else
    //       if($filterData == 'withOnlyStandard')
    //       {
    //         $locations->where(function($query) use ($request) {
    //             $query->where('redline_standard','!=','')->where('type','Redline');
    //         });
    //         //$locations->where('redline_standard','!=','')->where('type','Redline');
    //       }
    //     }
    //     if ($request->has('state') && $request->input('state')!=null) {
    //         $state=$request->input('state');
    //         $locations->where(function($query) use ($request,$state) {
    //              $query->orWhereHas('State', function ($queries) use ($state) {
    //                 $queries->where('state_code' , $state);
    //             });
    //         });
    //         //$locations->whereHas('State' , function ($query) use ($state){
    //                 //$query->where('state_code',$state);
    //                 //->Orwhere('state_code' , 'like', '%' . $state . '%');
    //             //});
    //     }

    //     if($request->has('sort') &&  $request->input('sort') =='redline')
    //     {
    //         $val = $request->input('sort_val');
    //        $locations->orderBy('redline_standard',$val);
    //     }
    //    //\DB::enableQueryLog();
    //    $locations = $locations->with('State', 'Cities','additionalRedline','createdBy')->get();
    //     // dd(\DB::getQueryLog());
    //     // echo $locations = $locations->with('State', 'Cities','additionalRedline','createdBy')->toSql();
    //     //die;

    //     $locations->transform(function ($locations) {
    //         $user_count = User::where('office_id', $locations->id)->count();

    //         $redline_sales_count = SalesMaster::where('customer_state', $locations->State->state_code)->count();

    //         if ($locations->type == "Redline") {
    //             $work_site_id = null;
    //         } else {
    //             $work_site_id = $locations->State->state_code . ' ' . $locations->office_name . '|' . $locations->work_site_id;

    //         }
    //        $latest = LocationRedlineHistory::where('location_id',$locations->id)->orderBy('effective_date','desc')->orderBy('id','desc')->first();
    //         return [
    //             // dd($locations->marketing),
    //             'id' => $locations->id,
    //             'user_count' => $user_count,
    //             'state_id' => $locations->state_id,
    //             'general_code' => isset($locations->general_code)?$locations->general_code:null,
    //             'work_site_id' => $work_site_id,
    //             'city_id' => $locations->city_id,
    //             'installation_partner' => $locations->installation_partner,
    //             'redline_min' => $locations->redline_min,
    //             // 'redline_standard' => $locations->redline_standard,
    //             'redline_standard' => isset($latest->redline_standard)?$latest->redline_standard:$locations->redline_standard,
    //             'redline_max' => $locations->redline_max,
    //             'effective_date' => $locations->date_effective,
    //             'created_by' => $locations['createdBy'],
    //             'office_status' => $locations->office_status,
    //             'office_name' => $locations->office_name,
    //             // 'marketing_deal_name' => $locations->marketing->first_name, $locations->marketing->last_name,
    //             //'marketing_deal_name' =>isset($locations->marketing->first_name , $locations->marketing->last_name) ? $locations->marketing->first_name . " " . $locations->marketing->last_name : 'NA',
    //             // 'marketing_deal_image'  => isset($locations->marketing->image) ? $locations->marketing->image : 'NA',
    //             'people' => $user_count,
    //             //'marketing_deal_person_id' => $locations->marketing_deal_person_id,
    //             'type' => $locations->type,
    //             'lat' => $locations->lat,
    //             'long' => $locations->long,
    //             'time_zone' => $locations->time_zone,
    //             'state' => isset($locations->State->name) ? $locations->state->name : null,
    //             'city' => isset($locations->Cities->name) ? $locations->Cities->name : null,
    //             'business_address' => $locations->business_address,
    //             'business_city' => $locations->business_city,
    //             'business_state' => $locations->business_state,
    //             'business_zip' => $locations->business_zip,
    //             'mailing_address' => $locations->mailing_address,
    //             'mailing_state' => $locations->mailing_state,
    //             'mailing_city' => $locations->mailing_city,
    //             'mailing_zip' => $locations->mailing_zip,
    //             'redline_data' => $locations->additionalRedline,
    //             'redline_sales_count' => $redline_sales_count,
    //             // 'additional_redline_min' => isset($locations->additionalRedline->redline_min)?$locations->additionalRedline->redline_min:null,
    //             // 'additional_redline_standard' => isset($locations->additionalRedline->redline_standard)?$locations->additionalRedline->redline_standard:null,
    //             // 'additional_redline_max' => isset($locations->additionalRedline->redline_max)?$locations->additionalRedline->redline_max:null,
    //             // 'additional_effective_date' => isset($locations->additionalRedline->effective_date)?$locations->additionalRedline->effective_date:null,
    //         ];
    //     });

    //     if($request->has('sort') &&  $request->input('sort') =='people')
    //     {
    //         $val = $request->input('sort_val');
    //         $locations = json_decode($locations);
    //         if($request->input('sort_val')=='desc')
    //         {
    //             array_multisort(array_column($locations, 'people'),SORT_DESC, $locations);
    //         } else{
    //             array_multisort(array_column($locations, 'people'),SORT_ASC, $locations);
    //         }

    //     }
    //     if($request->has('sort') &&  $request->input('sort') =='state')
    //     {
    //         $val = $request->input('sort_val');
    //         $locations = json_decode($locations);
    //         if($request->input('sort_val')=='desc')
    //         {
    //             array_multisort(array_column($locations, 'state'),SORT_DESC, $locations);
    //         } else{
    //             array_multisort(array_column($locations, 'state'),SORT_ASC, $locations);
    //         }
    //     }

    //     $locations = $this->paginate($locations,10);
    //     return response()->json(['status' => 'success', 'locations' => $locations], 200);
    // }
    public function index(Request $request)
    {
        $locations = $this->locations->newQuery();

        if (! $request->has('show_archived') || $request->input('show_archived') != 'true') {
            $locations->whereNull('archived_at');
        } else {
            if ($request->has('show_archived') || $request->input('show_archived') == 'true') {
                $locations->whereNotNull('archived_at');
            }
        }

        /*if ($request->has('search') && $request->input('search') != '') {
            $search = $request->input('search');
            $locations->orWhere(function ($query) use ($request, $search) {
                $query->where('general_code', 'LIKE', '%' . $search . '%')
                    ->orWhere('installation_partner', 'LIKE', '%' . $search . '%')
                    ->orWhere('redline_standard', 'LIKE', '%' . $search . '%')
                    ->orWhereHas('State', function ($query) use ($search) {
                        $query->where('name' , 'LIKE', '%' .$search. '%');
                        $query->orWhere('state_code', 'LIKE', '%' . $search . '%');
                    })
                    ->orWhere('office_name', 'LIKE', '%' . $search . '%')
                    ->orWhere('work_site_id', 'LIKE', '%' . $search . '%');
            });
        }*/

        if ($request->has('search') && $request->input('search') != '') {
            $search = trim($request->input('search'));

            $locations->where(function ($query) use ($search) {
                $query->where('general_code', 'LIKE', '%'.$search.'%')
                    ->orWhere('installation_partner', 'LIKE', '%'.$search.'%')
                    ->orWhere('redline_standard', 'LIKE', '%'.$search.'%')
                    ->orWhere('office_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('work_site_id', 'LIKE', '%'.$search.'%')
                    ->orWhereHas('State', function ($stateQuery) use ($search) {
                        $stateQuery->where('name', 'LIKE', '%'.$search.'%')
                            ->orWhere('state_code', 'LIKE', '%'.$search.'%');
                    });
            });
        }

        if ($request->has('filter') && $request->input('filter') != null) {
            $filterData = $request->input('filter');
            if ($filterData == 'withInstallers') {
                $locations->where(function ($query) {
                    $query->where('installation_partner', '!=', '');
                });
            } elseif ($filterData == 'withWorkSiteId') {
                $locations->where(function ($query) {
                    $query->where('work_site_id', '!=', '')->where('type', '!=', 'Redline');
                });
            } elseif ($filterData == 'withOnlyStandard') {
                $locations->where(function ($query) {
                    $query->where('redline_standard', '!=', '')->where('type', 'Redline');
                });
            }
        }

        if ($request->has('state') && $request->input('state') != null) {
            $state = $request->input('state');
            $locations->where(function ($query) use ($state) {
                $query->orWhereHas('State', function ($queries) use ($state) {
                    $queries->where('state_code', $state);
                });
            });
        }

        $locations->with('State', 'Cities', 'additionalRedline', 'createdBy');
        $locations->orderBy('office_name', 'ASC');
        if (($request->has('sort') && $request->input('sort') != '') || $request->input('is_export')) {
            $locations = $locations->get();
        } else {
            $locations = $locations->paginate($request->perpage ?? 10);
        }

        $companyProfile = CompanyProfile::first();
        $companyType = '';
        if ($companyProfile) {
            $companyType = $companyProfile->company_type;
        }

        $locations->transform(function ($locations) use ($companyType) {
            $user_count = User::where('office_id', $locations->id)->count();
            $redline_sales_count = SalesMaster::where('customer_state', $locations->State->state_code)->count();
            $work_site_id = null;
            if ($locations->type != 'Redline') {
                $work_site_id = $locations->State->state_code.' '.$locations->office_name.'|'.$locations->work_site_id;
            }

            if (in_array($companyType, CompanyProfile::PEST_COMPANY_TYPE)) {
                $redLineMin = null;
                $redLineStandard = null;
                $redLineMax = null;
            } else {
                $latest = LocationRedlineHistory::where('location_id', $locations->id)->whereDate('effective_date', '<=', now())->orderBy('effective_date', 'desc')->orderBy('id', 'desc')->first();
                $redLineMin = $latest->redline_min ?? $locations->redline_min;
                $redLineStandard = $latest->redline_standard ?? $locations->redline_standard;
                $redLineMax = $latest->redline_max ?? $locations->redline_max;
            }

            return [
                'id' => $locations->id,
                'user_count' => $user_count,
                'state_id' => $locations->state_id,
                'general_code' => $locations->general_code ?? null,
                'work_site_id' => $work_site_id,
                'city_id' => $locations->city_id,
                'installation_partner' => $locations->installation_partner,
                'redline_min' => $redLineMin,
                'redline_standard' => $redLineStandard,
                'redline_max' => $redLineMax,
                'effective_date' => $locations->date_effective,
                'created_by' => $locations->createdBy,
                'office_status' => $locations->office_status,
                'office_name' => $locations->office_name,
                'people' => $user_count,
                'type' => $locations->type,
                'lat' => $locations->lat,
                'long' => $locations->long,
                'time_zone' => $locations->time_zone,
                'state' => $locations->State->name ?? null,
                'city' => $locations->Cities->name ?? null,
                'business_address' => $locations->business_address,
                'business_city' => $locations->business_city,
                'business_state' => $locations->business_state,
                'business_zip' => $locations->business_zip,
                'mailing_address' => $locations->mailing_address,
                'mailing_state' => $locations->mailing_state,
                'mailing_city' => $locations->mailing_city,
                'mailing_zip' => $locations->mailing_zip,
                'redline_data' => $locations->additionalRedline,
                'redline_sales_count' => $redline_sales_count,
                'archived_at' => $locations->archived_at,
            ];
        });

        if ($request->has('sort') && $request->input('sort') == 'state') {
            $locations = json_decode($locations, true);
            array_multisort(
                array_map('strtolower', array_column($locations, 'state')), $request->input('sort_val') == 'desc' ? SORT_DESC : SORT_ASC,
                array_map('strtolower', array_column($locations, 'office_name')), SORT_ASC,
                $locations
            );
        }

        if ($request->has('sort') && $request->input('sort') == 'people') {
            $locations = json_decode($locations, true);
            array_multisort(
                array_map('strtolower', array_column($locations, 'people')), $request->input('sort_val') == 'desc' ? SORT_DESC : SORT_ASC,
                array_map('strtolower', array_column($locations, 'office_name')), SORT_ASC,
                $locations
            );
        }

        if ($request->has('sort') && $request->input('sort') == 'redline') {
            $locations = json_decode($locations, true);
            array_multisort(
                array_map('strtolower', array_column($locations, 'redline_standard')), $request->input('sort_val') == 'desc' ? SORT_DESC : SORT_ASC,
                array_map('strtolower', array_column($locations, 'office_name')), SORT_ASC,
                $locations
            );
        }

        // $locations = collect($locations)->sortBy('office_name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        if ($request->has('sort') && $request->input('sort') != '') {
            $locations = $this->paginate($locations, $request->perpage ?? 10);
        }

        return response()->json(['status' => 'success', 'locations' => $locations]);
    }

    /**
     * Retrieve locations with position-filtered users
     *
     * @param  Request  $request  - Contains sub_position_id (required) and office_id (optional, 0 = all offices)
     */
    public function locations_by_position(Request $request, $subPositionId): \Illuminate\Http\JsonResponse
    {
        try {

            $officeId = $request->input('office_id', 0); // 0 means get all users
            $perpage = $request->perpage ?? 100;

            if (! $subPositionId) {
                return response()->json([
                    'success' => false,
                    'error' => 'sub_position_id is required',
                    'message' => 'Please provide a valid sub_position_id',
                ], 400);
            }

            // Get company profile for type checking
            $companyProfile = CompanyProfile::first();
            $companyType = $companyProfile?->company_type ?? '';

            // Get users with their location relationships filtered by position
            $userQuery = User::select([
                'id',
                'first_name',
                'last_name',
                'image',
                'office_id',
                'position_id',
                'sub_position_id',
                'is_super_admin',
                'is_manager',
            ])
                ->with(['office_selected' => function ($query) {
                    $query->select('id', 'office_name', 'state_id', 'general_code', 'city_id',
                        'installation_partner', 'redline_min', 'redline_standard', 'redline_max',
                        'date_effective', 'created_by', 'office_status', 'type', 'lat', 'long',
                        'time_zone', 'business_address', 'business_city', 'business_state',
                        'business_zip', 'mailing_address', 'mailing_state', 'mailing_city',
                        'mailing_zip', 'archived_at', 'work_site_id')
                        ->with(['State' => function ($stateQuery) {
                            $stateQuery->select('id', 'name', 'state_code');
                        }])
                        ->with(['Cities' => function ($cityQuery) {
                            $cityQuery->select('id', 'name');
                        }])
                        ->with('additionalRedline', 'createdBy');
                }])
                ->where('sub_position_id', $subPositionId)
                ->where('dismiss', 0)
                ->where('terminate', 0);

            // Conditionally filter by office_id if not 0
            if ($officeId != 0) {
                $userQuery->where('office_id', $officeId);
            }

            $users = $userQuery->get();

            // Group users by location (office_id) and restructure the data
            $locationsWithUsers = [];
            $groupedUsers = $users->groupBy('office_id');

            // Sort locations by office_name if needed
            if ($request->has('sort') && $request->input('sort') == 'office_name') {
                $groupedUsers = $groupedUsers->sortBy(function ($locationUsers) {
                    return $locationUsers->first()->office_selected?->office_name ?? '';
                });
            }

            foreach ($groupedUsers as $locationId => $locationUsers) {
                $firstUser = $locationUsers->first();
                $location = $firstUser->office_selected;

                if ($location) {
                    // Calculate location-specific data
                    $user_count = $locationUsers->count();
                    $state_code = $location->State?->state_code;
                    $redline_sales_count = $state_code
                        ? SalesMaster::where('customer_state', $state_code)->count()
                        : 0;

                    $work_site_id = null;
                    if ($location->type != 'Redline' && $state_code) {
                        $work_site_id = $state_code.' '.$location->office_name.'|'.$location->work_site_id;
                    }

                    // Handle redline data based on company type
                    if (in_array($companyType, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $redLineMin = null;
                        $redLineStandard = null;
                        $redLineMax = null;
                    } else {
                        $latest = LocationRedlineHistory::where('location_id', $location->id)
                            ->whereDate('effective_date', '<=', now())
                            ->orderBy('effective_date', 'desc')
                            ->orderBy('id', 'desc')
                            ->first();

                        $redLineMin = $latest?->redline_min ?? $location->redline_min;
                        $redLineStandard = $latest?->redline_standard ?? $location->redline_standard;
                        $redLineMax = $latest?->redline_max ?? $location->redline_max;
                    }

                    // Prepare users array for this location
                    $usersArray = [];
                    foreach ($locationUsers as $user) {
                        $usersArray[] = [
                            'id' => $user->id,
                            'first_name' => $user->first_name,
                            'last_name' => $user->last_name,
                            'image' => $user->image,
                            'office_id' => $user->office_id,
                            'position_id' => $user->position_id,
                            'sub_position_id' => $user->sub_position_id,
                            'is_super_admin' => $user->is_super_admin,
                            'is_manager' => $user->is_manager,
                        ];
                    }

                    // Structure: Direct location objects (no wrapper)
                    $locationsWithUsers[] = [
                        'id' => $location->id,
                        'user_count' => $user_count,
                        'state_id' => $location->state_id,
                        'general_code' => $location->general_code ?? null,
                        'work_site_id' => $work_site_id,
                        'city_id' => $location->city_id,
                        'installation_partner' => $location->installation_partner,
                        'redline_min' => $redLineMin,
                        'redline_standard' => $redLineStandard,
                        'redline_max' => $redLineMax,
                        'effective_date' => $location->date_effective,
                        'created_by' => $location->createdBy,
                        'office_status' => $location->office_status,
                        'office_name' => $location->office_name,
                        'people' => $user_count,
                        'type' => $location->type,
                        'lat' => $location->lat,
                        'long' => $location->long,
                        'time_zone' => $location->time_zone,
                        'state' => $location->State?->name,
                        'city' => $location->Cities?->name,
                        'business_address' => $location->business_address,
                        'business_city' => $location->business_city,
                        'business_state' => $location->business_state,
                        'business_zip' => $location->business_zip,
                        'mailing_address' => $location->mailing_address,
                        'mailing_state' => $location->mailing_state,
                        'mailing_city' => $location->mailing_city,
                        'mailing_zip' => $location->mailing_zip,
                        'redline_data' => $location->additionalRedline,
                        'redline_sales_count' => $redline_sales_count,
                        'archived_at' => $location->archived_at,
                    ];
                }
            }

            // Create pagination structure manually
            $currentPage = $request->page ?? 1;
            $total = count($locationsWithUsers);
            $from = $total > 0 ? 1 : null;
            $to = $total;

            // Create pagination structure to match desired format
            $paginatedLocations = [
                'current_page' => (int) $currentPage,
                'data' => $locationsWithUsers,
                'first_page_url' => request()->fullUrlWithQuery(['page' => 1]),
                'from' => $from,
                'last_page' => 1,
                'last_page_url' => request()->fullUrlWithQuery(['page' => 1]),
                'links' => [
                    [
                        'url' => null,
                        'label' => '&laquo; Previous',
                        'active' => false,
                    ],
                    [
                        'url' => request()->fullUrlWithQuery(['page' => 1]),
                        'label' => '1',
                        'active' => true,
                    ],
                    [
                        'url' => null,
                        'label' => 'Next &raquo;',
                        'active' => false,
                    ],
                ],
                'next_page_url' => null,
                'path' => request()->url(),
                'per_page' => $perpage,
                'prev_page_url' => null,
                'to' => $to,
                'total' => $total,
            ];

            $message = $officeId == 0
                ? 'All locations with position-filtered users retrieved successfully'
                : "Locations filtered by office ID {$officeId} with position-filtered users retrieved successfully";

            return response()->json([
                'success' => true,
                'status' => 'success',
                'locations' => $paginatedLocations,
                'message' => $message,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve locations', [
                'user_id' => auth()->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'operation' => 'locations_fetch',
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Please try again later',
            ], 500);
        }
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

    // add-location
    /**
     * Check if company type has special handling requirements
     *
     * @param  CompanyProfile  $companyProfile  The company profile
     * @return bool Returns true if company type requires special handling
     */
    private function hasSpecialCompanyTypeHandling(CompanyProfile $companyProfile): bool
    {
        return in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) ||
               ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf');
    }

    /**
     * Apply company type specific modifications to request data
     *
     * @param  Request  $request  The request to modify
     * @param  CompanyProfile  $companyProfile  The company profile
     * @return Request The modified request
     */
    private function applyCompanyTypeRules(Request $request, CompanyProfile $companyProfile): Request
    {
        if ($this->hasSpecialCompanyTypeHandling($companyProfile)) {
            $request['type'] = 'Office';
            $request['redline_min'] = null;
            $request['redline_standard'] = null;
            $request['redline_max'] = null;
        }

        return $request;
    }

    /**
     * Validate location data based on location type and company type
     *
     * @param  Request  $request  The request containing location data
     * @param  bool  $isUpdate  Whether this is an update operation
     * @return array|\Illuminate\Http\JsonResponse Returns validation result or error response
     */
    private function validateLocationData(Request $request, bool $isUpdate = false)
    {
        if (! $request->all()) {
            return response()->json([
                'ApiName' => $isUpdate ? 'update-location' : 'Add-location',
                'status' => false,
                'message' => 'Bad Request',
            ], 400);
        }

        $companyProfile = CompanyProfile::first();

        // Apply company type specific rules
        $request = $this->applyCompanyTypeRules($request, $companyProfile);

        // Define validation rules based on location type
        if ($request->type != 'Office') {
            $rules = [
                'state_id' => 'required',
                'redline_standard' => 'required',
                'type' => 'required',
            ];
        } else {
            $rules = [
                'state_id' => 'required',
                'redline_standard' => 'required',
                'office_name' => 'required|unique:locations,office_name,'.$request['id'],
                'type' => 'required',
            ];

            // Modify rules based on company type
            if ($this->hasSpecialCompanyTypeHandling($companyProfile)) {
                $rules['redline_standard'] = 'nullable';
            }
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], $isUpdate ? 404 : 400);
        }

        return [
            'validator' => $validator,
            'companyProfile' => $companyProfile,
        ];
    }

    public function store(Request $request)
    {
        $validationResult = $this->validateLocationData($request);

        // Check if validation returned a JsonResponse (error response)
        if ($validationResult instanceof \Illuminate\Http\JsonResponse) {
            return $validationResult;
        }

        $companyProfile = $validationResult['companyProfile'];

        if ($request['office_status'] == 1) {
            $request['office_status'] = 1;
        } else {
            $request['office_status'] = 0;
        }

        if (! empty($request['general_code']) && isset($request['general_code']) && Locations::where('general_code', $request['general_code'])->first()) {
            return response()->json([
                'ApiName' => 'add-location',
                'status' => false,
                'message' => 'This location general code already exist.',
            ], 400);
        }

        if (! empty($request['general_code']) && isset($request['general_code']) && Locations::where(['state_id' => $request['state_id'], 'type' => $request['type'], 'general_code' => $request['general_code']])->first()) {
            $message = 'This location redline already exist.';
            if (! $this->hasSpecialCompanyTypeHandling($companyProfile)) {
                $message = 'This location is already exist.';
            }

            return response()->json([
                'ApiName' => 'add-location',
                'status' => false,
                'message' => $message,
            ], 400);
        }

        $data = Locations::create([
            'installation_partner' => $request['installation_partner'],
            'state_id' => $request['state_id'],
            'created_by' => Auth()->user()->id,
            'city_id' => $request['city_id'],
            'redline_min' => $request['redline_min'],
            'redline_standard' => $request['redline_standard'],
            'redline_max' => $request['redline_max'],
            'date_effective' => $request['effective_date'],
            // 'marketing_deal_person_id' => $request['marketing_deal_person_id'],
            'type' => $request['type'],
            'lat' => $request['lat'],
            'long' => $request['long'],
            'office_name' => $request['office_name'],
            'office_status' => $request['office_status'],
            'business_address' => $request['business_address'],
            'business_city' => $request['business_city'],
            'business_state' => $request['business_state'],
            'business_zip' => $request['business_zip'],
            'mailing_address' => $request['mailing_address'],
            'mailing_state' => $request['mailing_state'],
            'mailing_city' => $request['mailing_city'],
            'mailing_zip' => $request['mailing_zip'],
            'time_zone' => $request['time_zone'],
            'general_code' => isset($request['general_code']) ? $request['general_code'] : null,
        ]);

        $additionalRedlineMin = $request->redline_min;
        $additionalRedlineStander = $request->redline_standard;
        $additionalRedlineMax = $request->redline_max;
        $effectiveDate = $request->effective_date;
        if (isset($additionalRedlineStander) && $additionalRedlineStander != '') {
            LocationRedlineHistory::create([
                'location_id' => $data->id,
                'redline_min' => $additionalRedlineMin,
                'redline_standard' => $additionalRedlineStander,
                'redline_max' => $additionalRedlineMax,
                'created_by' => Auth()->user()->id,
                'updated_by' => Auth()->user()->id,
                'effective_date' => $effectiveDate,
            ]);
        }

        $work_site_id = Locations::with('state')->where('id', $data->id)->update(['work_site_id' => $data->state->state_code.'_'.$data->id]);
        if ($work_site_id == 1) {
            $CrmData = Crms::where('id', 3)->where('status', 1)->first();
            if ($CrmData) {
                $this->add_location($data);  // add everee location
            }
        }

        dispatch(new GenerateAlertJob);

        // Artisan::call('generate:alert');
        return response()->json([
            'ApiName' => 'add-location',
            'status' => true,
            'message' => 'add Successfully.',
            'data' => $data,
        ]);
    }

    public function updateOld(Request $request): JsonResponse
    {
        if (! null == $request->all()) {
            $Validator = Validator::make(
                $request->all(),
                [
                    // 'installation_partner' => 'required',
                    'state_id' => 'required',
                    // 'city_id' => 'required',
                    // 'redline_min' => 'required',
                    'redline_standard' => 'required',
                    // 'redline_max' => 'required',
                    // 'marketing_deal_person_id' => 'required',
                    'type' => 'required',
                    'general_code' => 'required',
                ]
            );
            if ($Validator->fails()) {
                return response()->json(['error' => $Validator->errors()], 404);
            }

            if (! empty($request['general_code']) && isset($request['general_code'])) {
                $general_code = $request['general_code'];

                $loc = Locations::where('general_code', $general_code)->where('id', '!=', $request['id'])->first();
                if (isset($loc) && $loc != '') {
                    return response()->json([
                        'ApiName' => 'update-location',
                        'status' => false,
                        'message' => 'This location general code already exist.',

                    ], 400);
                }
            }

            // if(!empty($request['city_id']) && isset($request['city_id'])){
            //     $city_id =$request['city_id'];

            //     $loc = Locations::where('city_id',$city_id)->where('id','!=',$request['id'])->first();
            //       if(isset($loc) && $loc != ''){
            //         return response()->json([
            //             'ApiName' => 'update-location',
            //             'status' => false,
            //             'message' => 'This location city id already exist.',

            //         ], 400);
            //       }
            // }

            if (! empty($request['state_id']) && isset($request['state_id'])) {
                $state_id = $request['state_id'];

                $loc = Locations::where('state_id', $state_id)->where('id', '!=', $request['id'])->first();
                if (isset($loc) && $loc != '') {
                    return response()->json([
                        'ApiName' => 'update-location',
                        'status' => false,
                        'message' => 'This location state id already exist.',

                    ], 400);
                }
            }

            $location = Locations::find($request['id']);
            if (isset($location) && $location != '') {
                $post = Locations::find($request['id']);
                $post->installation_partner = $request['installation_partner'];
                if ($location->state_id != $request['state_id']) {
                    $post->state_id = $request['state_id'];
                }
                if ($location->city != $request['city_id']) {
                    $post->city_id = $request['city_id'];
                }
                $post->general_code = isset($request['general_code']) ? $request['general_code'] : null;
                $post->redline_min = $request['redline_min'];
                $post->redline_standard = $request['redline_standard'];
                $post->redline_max = $request['redline_max'];
                // $post->marketing_deal_person_id = $request['marketing_deal_person_id'];
                $post->type = $request['type'];
                $post->lat = $request['lat'];
                $post->long = $request['long'];
                $post->office_name = $request['office_name'];
                $post->business_address = $request['business_address'];
                $post->business_city = $request['business_city'];
                $post->business_state = $request['business_state'];
                $post->business_zip = $request['business_zip'];
                $post->mailing_address = $request['mailing_address'];
                $post->mailing_state = $request['mailing_state'];
                $post->mailing_city = $request['mailing_city'];
                $post->mailing_zip = $request['mailing_zip'];
                $post->save();

                $work_site_id = Locations::with('state')->where('id', $post->id)->update(['work_site_id' => $post->state->state_code.'_'.$post->id]);

                return response()->json([
                    'ApiName' => 'update-location',
                    'status' => true,
                    'message' => 'update Successfully.',
                    // 'data' => $post,
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'update-location',
                    'status' => false,
                    'message' => 'Location is not exists!',
                ], 400);
            }
        } else {
            return response()->json([
                'ApiName' => 'update-location',
                'status' => false,
                'message' => 'Bad Request',
            ], 400);
        }
    }

    public function update(Request $request)
    {
        $validationResult = $this->validateLocationData($request, true);

        // Check if validation returned an error response
        if ($validationResult instanceof \Illuminate\Http\JsonResponse) {
            return $validationResult;
        }

        $companyProfile = $validationResult['companyProfile'];

        $location = Locations::with('State')->where('id', $request->id)->first();
        if (! $location) {
            return response()->json([
                'ApiName' => 'update-location',
                'status' => false,
                'message' => 'Location is not exists!',
            ], 400);
        }

        if (! empty($request['general_code']) && isset($request['general_code']) && Locations::where('general_code', $request['general_code'])->where('id', '!=', $request['id'])->first()) {
            return response()->json([
                'ApiName' => 'update-location',
                'status' => false,
                'message' => 'This location general code already exist.',
            ], 400);
        }

        if (! empty($request['general_code']) && isset($request['general_code']) && Locations::where(['state_id' => $request['state_id'], 'type' => $request['type'], 'general_code' => $request['general_code']])->where('id', '!=', $request['id'])->first()) {
            $message = 'This location redline already exist.';
            if (! $this->hasSpecialCompanyTypeHandling($companyProfile)) {
                $message = 'This location is already exist.';
            }

            return response()->json([
                'ApiName' => 'add-location',
                'status' => false,
                'message' => $message,
            ], 400);
        }

        $location->installation_partner = $request['installation_partner'];
        $location->state_id = $request['state_id'];
        $location->city_id = $request['city_id'];
        $location->general_code = isset($request['general_code']) ? $request['general_code'] : null;
        $location->redline_min = $request['redline_min'];
        $location->redline_standard = $request['redline_standard'];
        $location->redline_max = $request['redline_max'];
        $location->date_effective = $request['effective_date'];
        // $post->marketing_deal_person_id = $request['marketing_deal_person_id'];
        $location->type = $request['type'];
        $location->lat = $request['lat'];
        $location->long = $request['long'];
        $location->office_name = $request['office_name'];
        $location->business_address = $request['business_address'];
        $location->business_city = $request['business_city'];
        $location->business_state = $request['business_state'];
        $location->business_zip = $request['business_zip'];
        $location->mailing_address = $request['mailing_address'];
        $location->mailing_state = $request['mailing_state'];
        $location->mailing_city = $request['mailing_city'];
        $location->mailing_zip = $request['mailing_zip'];
        $location->time_zone = $request['time_zone'];
        $location->save();

        if ($this->hasSpecialCompanyTypeHandling($companyProfile)) {
            // No Need To Create RedLine History
        } else {
            $redlineData = $request->redline_data;
            $effectiveForm = LocationRedlineHistory::where('location_id', $location->id)->first();
            if (! $effectiveForm) {
                foreach ($redlineData as $val) {
                    LocationRedlineHistory::create([
                        'location_id' => $request['id'],
                        'redline_min' => $val['redline_min'],
                        'redline_standard' => $val['redline_standard'],
                        'redline_max' => $val['redline_max'],
                        'created_by' => Auth()->user()->id,
                        'updated_by' => Auth()->user()->id,
                        'effective_date' => $val['effective_date'],
                    ]);
                }
            } else {
                foreach ($redlineData as $val) {
                    LocationRedlineHistory::updateOrCreate([
                        'effective_date' => $val['effective_date'],
                        'location_id' => $request['id'],
                    ], [
                        'redline_min' => $val['redline_min'],
                        'redline_standard' => $val['redline_standard'],
                        'redline_max' => $val['redline_max'],
                        'created_by' => Auth()->user()->id,
                        'updated_by' => Auth()->user()->id,
                    ]);
                }
            }
        }

        $additionalLocation = AdditionalLocations::where('office_id', $request->id)->first();
        if ($additionalLocation) {
            AdditionalLocations::where('office_id', $request->id)->update(['state_id' => $request['state_id']]);
        }

        $work_site_id = Locations::where('id', $location->id)->update(['work_site_id' => $location->state->state_code.'_'.$location->id]);
        if ($work_site_id == 1) {
            $CrmData = Crms::where('id', 3)->where('status', 1)->first();
            if ($CrmData) {
                $this->add_location($location);  // add/update everee location
            }
        }

        dispatch(new GenerateAlertJob);

        // Artisan::call('generate:alert');
        return response()->json([
            'ApiName' => 'update-location',
            'status' => true,
            'message' => 'update Successfully.',
        ]);
    }

    public function destroy($id)
    {
        // return 0;
        if (! null == $id) {
            $location = $id = Locations::find($id);
            if ($id) {
                $user = user::where('office_id', $id->id)->first();
                if (! empty($user)) {
                    return response()->json([
                        'ApiName' => 'delete-location',
                        'status' => false,
                        'message' => 'You can not archive location, because some users already map with location. ',
                        'data' => null,
                    ], 400);
                }

                // $id->delete();
                $active_location = Locations::where('id', $id->id)->whereNull('archived_at')->first();
                if ($active_location) {
                    Locations::where('id', $id->id)->update(['archived_at' => Carbon::now()->format('Y-m-d')]);
                    AdditionalLocations::where('office_id', $location->id)->update(['archived_at' => Carbon::now()->format('Y-m-d'), 'deleted_at' => Carbon::now()]);

                    return response()->json([
                        'ApiName' => 'delete-location',
                        'status' => true,
                        'message' => 'Unarchived Successfully.',
                        'data' => $location,
                    ], 200);
                } else {
                    Locations::where('id', $id->id)->update(['archived_at' => null]);
                    AdditionalLocations::withTrashed()->where('office_id', $location->id)->update(['archived_at' => null, 'deleted_at' => null]);

                    return response()->json([
                        'ApiName' => 'delete-location',
                        'status' => true,
                        'message' => 'Archived Successfully.',
                        'data' => $location,
                    ], 200);
                }
            } else {
                return response()->json([
                    'ApiName' => 'delete-location',
                    'status' => false,
                    'message' => 'No records founds',
                    'data' => null,
                ], 400);
            }

        } else {
            return response()->json([
                'ApiName' => 'delete-location',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }
    }

    public function search(Request $request): JsonResponse
    {
        $result = Locations::where('installation_partner', 'LIKE', '%'.$request->name.'%')->get();
        if (count($result)) {
            return response()->json([
                'ApiName' => 'search-location',
                'status' => true,
                'message' => 'search Successfully.',
                'data' => $result,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'search-location',
                'status' => false,
                'message' => '',
                'data' => null,
            ], 400);
        }
    }

    public function locationFilter(Request $request, Locations $Locations)
    {

        $filter = $request->filter;
        $search = $request->search;

        $Locations = $Locations->newQuery();

        if ($request->has('filter')) {
            $filterData = $request->input('filter');
            if ($filterData == 'withInstallers') {
                $Locations->where('installation_partner', '!=', '')->get();

            } elseif ($filterData == 'withWorkSiteId') {
                $Locations->where('work_site_id', '!=', '')->where('type', '!=', 'Redline')->get();

            } elseif ($filterData == 'withOnlyStandard') {
                $Locations->where('redline_standard', '!=', '')->get();

            }
        }

        if ($request->has('search')) {
            $Locations->whereHas(
                'State', function ($query) use ($request) {
                    $query->where('state_code', 'LIKE', '%'.$request->search.'%');
                });
        }

        $result = $Locations->with('State', 'Cities')->orderBy('id', 'desc')
            ->paginate(config('app.paginate', 15));
        $result->transform(function ($location) {
            $user_count = User::where('office_id', $location->id)->count();
            if ($location->type == 'Redline') {
                $work_site_id = null;
            } else {
                $work_site_id = $location->State->state_code.' '.$location->office_name.'|'.$location->work_site_id;

            }

            return [
                // dd($location->marketing),
                'id' => $location->id,
                'user_count' => $user_count,
                'state_id' => $location->state_id,
                'general_code' => isset($location->general_code) ? $location->general_code : null,
                'work_site_id' => $work_site_id,
                'city_id' => $location->city_id,
                'installation_partner' => $location->installation_partner,
                'redline_min' => $location->redline_min,
                'redline_standard' => $location->redline_standard,
                'redline_max' => $location->redline_max,
                'office_status' => $location->office_status,
                'office_name' => $location->office_name,
                // 'marketing_deal_name' => $location->marketing->first_name, $location->marketing->last_name,
                // 'marketing_deal_name' =>isset($location->marketing->first_name , $location->marketing->last_name) ? $location->marketing->first_name . " " . $location->marketing->last_name : 'NA',
                // 'marketing_deal_image'  => isset($location->marketing->image) ? $location->marketing->image : 'NA',
                'people' => $user_count,
                // 'marketing_deal_person_id' => $location->marketing_deal_person_id,
                'type' => $location->type,
                'lat' => $location->lat,
                'long' => $location->long,
                'time_zone' => $location->time_zone,
                'state' => isset($location->State->name) ? $location->state->name : null,
                'city' => isset($location->Cities->name) ? $location->Cities->name : null,
                'business_address' => $location->business_address,
                'business_city' => $location->business_city,
                'business_state' => $location->business_state,
                'business_zip' => $location->business_zip,
                'mailing_address' => $location->mailing_address,
                'mailing_state' => $location->mailing_state,
                'mailing_city' => $location->mailing_city,
                'mailing_zip' => $location->mailing_zip,
            ];
        });

        if (count($result)) {
            return response()->json([
                'ApiName' => 'location filter',
                'status' => true,
                'message' => 'search Successfully.',
                'data' => $result,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'location filter',
                'status' => true,
                'message' => '',
                'data' => [],
            ], 200);
        }
    }

    public function additionalLocationDelete($id): JsonResponse
    {
        $loc = LocationRedlineHistory::find($id);

        if ($loc) {
            $loc->delete();

            return response()->json([
                'ApiName' => 'Delete Additional Location ',
                'status' => true,
                'message' => 'Deleted Successfully',
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Delete Additional Location ',
                'status' => false,
                'message' => 'Bad Request',
            ], 400);
        }

    }

    public function getFutureRedLineByLocation($id): JsonResponse
    {
        $location = LocationRedlineHistory::with('createdBy', 'updatedBy')->where('location_id', $id)->orderBy('id', 'desc')->get();
        if ($location) {
            return response()->json([
                'ApiName' => 'Get Additional Location History',
                'status' => true,
                'message' => 'Successfully',
                'data' => $location,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'Get Additional Location History',
                'status' => false,
                'message' => 'Bad Request',
            ], 400);
        }

    }

    public function exportLocationData(Request $request)
    {
        $file_name = 'locations_'.date('Y_m_d_H_i_s').'.csv';

        return Excel::download(new LocationExport($request), $file_name);

    }
}
// }
