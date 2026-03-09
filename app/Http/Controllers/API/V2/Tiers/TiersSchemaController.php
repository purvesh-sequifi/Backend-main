<?php

namespace App\Http\Controllers\API\V2\Tiers;

use App\Http\Controllers\API\V2\Sales\BaseController;
use App\Models\MilestoneProductAuditLog;
use App\Models\PositionCommission;
use App\Models\PositionCommissionUpfronts;
use App\Models\PositionOverride;
use App\Models\PositionTier;
use App\Models\TierDuration;
use App\Models\TierMetrics;
use App\Models\TiersLevel;
use App\Models\TiersSchema;
use App\Models\TierSystem;
use App\Models\User;
use App\Models\UserCommissionHistory;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserUpfrontHistory;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TiersSchemaController extends BaseController
{
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $tiers = TiersSchema::with(['tier_system', 'tier_metrics', 'tier_duration'])
            ->when($request->filled('tier_metrics_id'), function ($q) use ($request) {
                $q->where('tier_metrics_id', $request->input('tier_metrics_id'));
            })->when($request->filled('tier_duration_id'), function ($q) use ($request) {
                $q->where('tier_duration_id', $request->input('tier_duration_id'));
            })->when($request->filled('nooflevel'), function ($q) use ($request) {
                $q->where('levels', $request->input('nooflevel'));
            })->when($request->filled('position_id'), function ($q) use ($request) {
                $tierId = [];
                foreach ($request->input('position_id') as $position) {
                    $effectiveDate = null;
                    $positionTier = PositionTier::where('position_id', $position)->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ($positionTier) {
                        $effectiveDate = $positionTier->effective_date;
                    }
                    $tierId = array_merge($tierId, PositionTier::where(['position_id' => $position, 'status' => 1, 'effective_date' => $effectiveDate])->pluck('tiers_schema_id')->toArray());
                }
                $q->whereIn('id', $tierId);
            })->when($request->filled('status'), function ($q) use ($request) {
                if ($request->input('status') == '0') {
                    $q->onlyTrashed();
                }
            })->when(! $request->filled('status'), function ($q) {
                $q->withTrashed();
            })->when($request->filled('search'), function ($q) use ($request) {
                $searchTerm = $request->input('search');
                $q->where(function ($query) use ($searchTerm) {
                    $query->where('schema_name', 'LIKE', '%'.$searchTerm.'%')
                        ->orWhere('schema_description', 'LIKE', '%'.$searchTerm.'%');
                });
            })->orderBy('id', 'DESC')->paginate($perPage);

        $tiers->transform(function ($tier) {
            $tiered = $finalPositions = PositionTier::where(['tiers_schema_id' => $tier->id, 'status' => 1])->groupBy('position_id')->pluck('position_id');

            $subQuery = UserOrganizationHistory::select(
                'id',
                'user_id',
                'effective_date',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn')
            )->where('effective_date', '<=', date('Y-m-d'));
            $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);
            $userIdArr = UserOrganizationHistory::whereIn('id', $results->pluck('id'))->whereIn('sub_position_id', $finalPositions)->pluck('user_id')->toArray();
            $people = User::whereIn('id', $userIdArr)->where('dismiss', 0)->count();

            return [
                'id' => $tier->id,
                'prefix' => $tier->prefix,
                'name' => $tier->schema_name,
                'schema_description' => $tier->schema_description,
                'tier_system' => isset($tier->tier_system) ? $tier->tier_system->value : null,
                'tier_metrics' => isset($tier->tier_metrics) ? $tier->tier_metrics->value : null,
                'tier_duration' => isset($tier->tier_duration) ? $tier->tier_duration->value : null,
                'position' => count($tiered),
                'users' => $people,
                'deleted_at' => $tier->deleted_at,
            ];
        });

        $this->successResponse('Successfully.', 'tiers', $tiers);
    }

    public function store(Request $request)
    {
        if ($request->filled('id')) {
            if (! TiersSchema::find($request->id)) {
                $this->errorResponse('Tier not found!!', 'update', '', 400);
            }

            $this->checkValidations($request->all(), [
                'schema_name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('tiers_schema', 'schema_name')->ignore($request->id),
                ],
                'schema_description' => 'required|string',
                'tiers_levels' => 'required|array|min:1',
                'effective_date' => 'required',
                'tiers_levels.*.id' => 'required',
            ]);
        } else {
            $this->checkValidations($request->all(), [
                'schema_name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('tiers_schema', 'schema_name'),
                ],
                'schema_description' => 'required|string',
                'tier_system_id' => 'required|exists:tier_systems,id',
                'tier_metrics_id' => 'required|exists:tier_metrics,id',
                'tiers_levels' => 'required|array|min:1',
            ]);
        }

        if ($request->filled('id')) {
            $schema = TiersSchema::where('id', $request->id)->first();
            $schema->schema_name = $request->schema_name;
            $schema->schema_description = $request->schema_description;
            $schema->save();

            foreach ($request->tiers_levels as $key => $level) {
                TiersLevel::updateOrCreate(['level' => ($key + 1), 'tiers_schema_id' => $request->id, 'effective_date' => $request->effective_date], [
                    'to_value' => isset($level['to_value']) ? $level['to_value'] : null,
                    'from_value' => isset($level['from_value']) ? $level['from_value'] : null,
                ]);
            }

            $this->successResponse('Tiers Updated Successfully.', 'update');
        } else {
            $schema = TiersSchema::create([
                'prefix' => $request->prefix ?? null,
                'schema_name' => $request->schema_name,
                'schema_description' => $request->schema_description,
                'tier_system_id' => $request->tier_system_id ?? 0,
                'tier_metrics_id' => $request->tier_metrics_id ?? 0,
                'tier_type' => $request->tier_type,
                'tier_duration_id' => $request->tier_duration_id ?? 0,
                'start_day' => $request->start_day,
                'end_day' => $request->end_day,
                'start_end_day' => $request->start_end_day,
                'levels' => count($request->tiers_levels),
            ]);

            $durations = config('global_vars.TIER_RESET');
            $tier = TiersSchema::whereHas('tier_duration', function ($q) use ($durations) {
                $q->whereIn('value', $durations);
            })->with('tier_duration')->where(['tier_type' => 'Progressive'])->where('id', $schema->id)->first();
            if ($tier) {
                $nextResetDate = getDurationForTier($tier, null, Carbon::now()->format('Y-m-d'));
                $schema->next_reset_date = Carbon::parse($nextResetDate['end_date'])->addDay()->format('Y-m-d');
                $schema->save();
            }

            foreach ($request->tiers_levels as $key => $level) {
                TiersLevel::create([
                    'tiers_schema_id' => $schema->id,
                    'level' => ($key + 1),
                    'to_value' => isset($level['to_value']) ? $level['to_value'] : null,
                    'from_value' => isset($level['from_value']) ? $level['from_value'] : null,
                    'effective_date' => null,
                ]);
            }

            $this->successResponse('Tiers Created Successfully.', 'store');
        }
    }

    public function show($id)
    {
        $schema = TiersSchema::find($id);
        if (! $schema) {
            $this->errorResponse('Tier not found!!', 'show', '', 400);
        }

        [$level, $levels] = $this->currentTiresLevel($schema);
        $schema->tiers_levels = $levels;
        $schema->effective_date = $level?->effective_date;
        $this->successResponse('Successfully!!', 'show', $schema);
    }

    public function activateDeActive($type, $id)
    {
        $schema = TiersSchema::withTrashed()->find($id);
        if (! $schema) {
            $this->errorResponse('Tier not found!!', 'show', '', 400);
        }

        if ($schema->trashed()) {
            $schema->restore();
            $this->successResponse('Tier restored Successfully!!', 'activateDeActive', $schema);
        } else {
            $schema->delete();
            $this->successResponse('Tired archived Successfully!!', 'activateDeActive', $schema);
        }
    }

    public function getUpdateByUsers(Request $request)
    {
        $users = MilestoneProductAuditLog::select('users.id', 'first_name', 'last_name')
            ->leftJoin('users', 'users.id', 'milestone_product_audiotlogs.user_id')
            ->whereIn('type', [\App\Models\TiersSchema::class, \App\Models\TiersLevel::class])
            ->when($request->filled('tiers_id'), function ($q) {
                $q->where('reference_id', request()->input('tiers_id'));
            })->distinct('users.id')->get();
        $this->successResponse('Successfully.', 'update-by-dropdown', $users);
    }

    public function getAuditLogs(Request $request)
    {
        $auditLogs = MilestoneProductAuditLog::with('users', 'tiersSchema')->whereIn('type', [\App\Models\TiersSchema::class, \App\Models\TiersLevel::class])
            ->when($request->filled('search'), function ($q) use ($request) {
                $searchTerm = $request->input('search');
                $q->where(function ($q) use ($searchTerm) {
                    $q->where('description', 'LIKE', '%'.$searchTerm.'%');
                });
            })->when($request->filled('effective_date'), function ($q) use ($request) {
                $q->whereDate('effective_on_date', $request->input('effective_date'));
            })->when($request->filled('tiers_id'), function ($q) use ($request) {
                $tiers = $request->input('tiers_id');
                $q->whereHas('tiersSchema', function ($query) use ($tiers) {
                    $query->withTrashed()->whereIn('id', (array) $tiers);
                });
            })->when($request->filled('user_id'), function ($q) use ($request) {
                $q->whereIn('user_id', (array) $request->input('user_id'));
            })->when($request->filled('sort_effective_date'), function ($q) use ($request) {
                $q->orderBy('effective_on_date', $request->input('sort_effective_date'));
            })->when($request->filled('sort_date'), function ($q) use ($request) {
                $q->orderBy('updated_at', $request->input('sort_date'));
            })->when($request->filled('sort_user'), function ($q) use ($request) {
                $sort_user = $request->input('sort_user');
                $q->whereHas('users', function ($query) use ($sort_user) {
                    $query->orderBy('users.first_name', $sort_user);
                });
            })->when((! $request->filled('sort_effective_date') && ! $request->filled('sort_date') && ! $request->filled('sort_user')), function ($q) {
                $q->orderBy('id', 'DESC');
            })->get();

        $finalSchema = [];
        $allSchemas = TiersSchema::all();
        foreach ($allSchemas as $allSchema) {
            $finalSchema[$allSchema->id] = $allSchema?->prefix.'-'.$allSchema?->schema_name;
        }

        $finalTrigger = [];
        $allTriggers = TiersLevel::all();
        foreach ($allTriggers as $allTrigger) {
            $finalTrigger[$allTrigger->id] = $allTrigger?->name;
        }

        $finalTierSystems = [];
        $allTierSystem = TierSystem::all();
        foreach ($allTierSystem as $allTrigger) {
            $finalTierSystems[$allTrigger->id] = $allTrigger?->value;
        }
        $finalTierMatrix = [];
        $allTierMatrix = TierMetrics::all();
        foreach ($allTierMatrix as $allTrigger) {
            $finalTierMatrix[$allTrigger->id] = $allTrigger?->value;
        }
        $finalTierDuration = [];
        $allTierDuration = TierDuration::all();
        foreach ($allTierDuration as $allTrigger) {
            $finalTierDuration[$allTrigger->id] = $allTrigger?->value;
        }

        $logs = [];
        $group = [];
        $description = [];
        $effectiveDate = [];
        $dateFlag = false;
        foreach ($auditLogs as $auditLog) {
            $monthYear = Carbon::parse($auditLog->updated_at)->format('F-Y');
            if (! empty($auditLog->description)) {
                $decode = json_decode($auditLog->description, true);
                if ($decode) {
                    $schemaName = '';
                    $schemaDesc = '';
                    if ($auditLog->event == 'created') {
                        $nFlag = true;
                        foreach ($decode as $create) {
                            if ($auditLog->type == \App\Models\TiersSchema::class) {
                                $desc = '';
                                $desc .= 'New Tiers created: <strong>'.$create['schema_name'].'</strong><br>';
                                $desc .= 'Description: <strong>'.$create['schema_description'].'</strong><br>';
                                $desc .= 'Tier System: <strong>'.(@$finalTierSystems[$create['tier_system_id']] ?? '').'</strong><br>';
                                $desc .= 'Tier Metrics: <strong>'.(@$finalTierMatrix[$create['tier_metrics_id']] ?? '').'</strong><br>';
                                $desc .= 'Tier Type: <strong>'.$create['tier_type'].'</strong><br>';
                                $desc .= 'Tier Duration: <strong>'.(@$finalTierDuration[$create['tier_duration_id']] ?? '').'</strong><br>';
                                $desc .= 'Start Day: <strong>'.($this->checkDateAndConvert($create['start_day'])).'</strong><br>';
                                $desc .= 'End Day: <strong>'.($this->checkDateAndConvert($create['end_day'])).'</strong><br>';
                                $description[$monthYear][$auditLog->group][] = $desc;
                                $schemaName = $create['schema_name'];
                                $schemaDesc = $create['schema_description'];
                            } elseif ($auditLog->type == \App\Models\TiersLevel::class) {
                                $dateFlag = true;
                                $levels = '';
                                if ($nFlag) {
                                    $levels .= 'Tier level '.$create['level'].' created = ';
                                }
                                $nFlag = false;
                                if ($create['from_value'] >= $create['to_value']) {
                                    $create['to_value'] = "<i style='font-size:15px' class='fas'>&#xf534;</i>";
                                }
                                $levels .= '<strong>'.$create['from_value'].' ≤ '.$create['to_value'].'</strong>';
                                $levels .= $create['effective_date'] ? '<strong>'.' ('.Carbon::parse($create['effective_date'])->format('m/d/Y').')</strong>' : '';
                                $description[$monthYear][$auditLog->group][] = $levels;
                            }
                        }
                    } elseif ($auditLog->event == 'deleted') {
                        $description[$monthYear][$auditLog->group][] = 'Tiers <strong>'.$auditLog->tiersSchema?->schema_name.'</strong> is archived.';
                    } elseif ($auditLog->event == 'restored') {
                        $description[$monthYear][$auditLog->group][] = 'Tiers <strong>'.$auditLog->tiersSchema?->schema_name.'</strong> is activated.';
                    } else {
                        foreach ($decode as $index => $desc) {
                            if ($auditLog->type == \App\Models\TiersSchema::class) {
                                if ($index != 'updated_at') {
                                    $field = preg_replace('/_/', ' ', $index);
                                    $description[$monthYear][$auditLog->group][] = 'Tiers '.$field.' '.MilestoneProductAuditLog::productFormatChange($desc, $index, $finalSchema, $finalTrigger);
                                    if ($index == 'schema_name') {
                                        $schemaName = $desc['new'];
                                    }
                                    if ($index == 'schema_description') {
                                        $schemaDesc = $desc['new'];
                                    }
                                }
                            } elseif ($auditLog->type == \App\Models\TiersLevel::class) {
                                if ($index != 'updated_at' && $index != 'level' && $index != 'tiers_schema_id') {
                                    $dateFlag = true;
                                    $field = preg_replace('/_/', ' ', $index);
                                    $description[$monthYear][$auditLog->group][] = 'Tier level '.(isset($decode['level']['new']) ? $decode['level']['new'] : null).' '.$field.' '.MilestoneProductAuditLog::productFormatChange($desc, $index, $finalSchema, $finalTrigger);
                                }
                            }
                        }
                    }
                }
            }

            if ($auditLog->effective_on_date && $dateFlag) {
                $effectiveDate[$monthYear][$auditLog->group][] = isset($auditLog->effective_on_date) ? Carbon::parse($auditLog->effective_on_date)->format('Y-m-d') : null;
            }

            $group[$monthYear][$auditLog->group] = [
                'month' => $monthYear,
                'effective_date' => @$effectiveDate[$monthYear][$auditLog->group] ? $effectiveDate[$monthYear][$auditLog->group] : [],
                'product_term' => $auditLog->products->product_id ?? null,
                'description' => @$description[$monthYear][$auditLog->group] ? $description[$monthYear][$auditLog->group] : [],
                'user' => $auditLog->users->first_name.' '.$auditLog->users->last_name,
                'user_info' => $auditLog->users,
                'type' => $auditLog->type,
                'current_name' => $auditLog->tiersSchema?->schema_name,
                'current_description' => $auditLog->tiersSchema?->scema_description,
                'log_name' => $schemaName,
                'log_description' => $schemaDesc,
                'updated_at' => Carbon::parse($auditLog->updated_at)->format('m/d/Y | H:i:s'),
                'event' => $auditLog->event,
            ];
            $logs[$monthYear] = $group[$monthYear];
        }

        $i = 0;
        $response = [];
        $currentEffective = $this->getEffectiveDate($logs);
        foreach ($logs as $key => $log) {
            $response[$i]['month'] = $key;
            $k = 0;
            foreach ($log as $data) {
                sort($data['description']);
                $desc = implode('<br>', $data['description']);

                $effectiveDate = null;
                if (@$data['effective_date'] && count($data['effective_date']) != 0) {
                    $effectiveDate = $data['effective_date'][0];
                }
                $current = 0;
                if ($effectiveDate != null && $effectiveDate == $currentEffective) {
                    $current = 1;
                }

                $newArray = [];
                $newArray['month'] = $data['month'];
                $newArray['effective_date'] = $effectiveDate;
                $newArray['product_term'] = $data['month'];
                $newArray['description'] = $desc;
                $newArray['user'] = $data['user'];
                $newArray['user_info'] = $data['user_info'];
                $newArray['updated_at'] = $data['updated_at'];
                $newArray['current_status'] = $current;
                $response[$i]['logs'][$k] = $newArray;
                $k++;
            }
            $i++;
        }

        $this->successResponse('Successfully.', 'Tiers-audit-logs', $response);
    }

    protected function checkDateAndConvert($value)
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}.*Z$/', $value)) {
            try {
                return Carbon::parse($value)->format('m/d/Y');
            } catch (\Exception $e) {
                return $value;
            }
        } else {
            return $value;
        }
    }

    public function tiersDropdown(Request $request)
    {
        $tiers = TiersSchema::with(['tier_system', 'tier_metrics', 'tier_duration'])
            ->when($request->filled('filter'), function ($q) use ($request) {
                $q->where('schema_name', 'LIKE', '%'.$request->input('filter').'%');
            })->orderBy('id', 'DESC')->get();

        if ($request->all == 1) {
            $tiers->transform(function ($tier) {
                [$_, $levels] = $this->currentTiresLevel($tier);
                $tier->tiers_levels = $levels;
                $tier->tier_system_value = $tier?->tier_system?->value;
                $tier->tier_metrics_value = $tier->tier_metrics?->value;
                $tier->tier_duration_value = $tier->tier_duration?->value;

                return $tier;
            });
        }

        $this->successResponse('Successfully!!', 'tiersDropdown', $tiers);
    }

    public function getTierSystems()
    {
        $this->successResponse('Successfully.', 'getTierSystems', TierSystem::select('id', 'value')->with('metrics:id,tier_system_id,value,symbol')->get());
    }

    public function getTierDurations()
    {
        $this->successResponse('Successfully.', 'getTierDurations', TierDuration::select('id', 'value')->get());
    }

    public function tiersLevelDropdown(): JsonResponse
    {
        try {
            $maxCount = TiersLevel::select('tiers_schema_id', DB::raw('COUNT(*) as count'))
                ->groupBy('tiers_schema_id')
                ->groupBy('effective_date')
                ->orderByDesc('count')
                ->first();
            $count = $maxCount->count ?? 5;
            $noofarray = [];
            for ($i = 1; $i <= $count; $i++) {
                $noofarray[] = ['name' => $i, 'value' => $i];
            }

            return response()->json([
                'ApiName' => 'tiers-level-dropdown',
                'status' => true,
                'data' => $noofarray,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'tiers-level-dropdown',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function tiersUserMapped(Request $request, $id)
    {
        try {
            $finalPositions = PositionTier::where(['tiers_schema_id' => $id, 'status' => 1])->groupBy('position_id')->pluck('position_id');
            $subQuery = UserOrganizationHistory::select(
                'id',
                'user_id',
                'effective_date',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn')
            )->where('effective_date', '<=', date('Y-m-d'));
            $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);
            $userIdArr = UserOrganizationHistory::whereIn('id', $results->pluck('id'))->whereIn('sub_position_id', $finalPositions)->pluck('user_id')->toArray();
            $users = User::with('office')->select('id', 'first_name', 'last_name', 'image', 'office_id', 'position_id', 'sub_position_id', 'is_super_admin', 'is_manager')
                ->when($request->filled('search'), function ($q) {
                    $q->where(function ($q) {
                        $q->where('first_name', 'LIKE', '%'.request()->input('search').'%')
                            ->orWhere('last_name', 'LIKE', '%'.request()->input('search').'%')
                            ->orWhereHas('office', function ($q) {
                                $q->where('office_name', 'LIKE', '%'.request()->input('search').'%');
                            });
                    });
                })->whereIn('id', $userIdArr)->where('dismiss', 0)->orderBy('id', 'DESC')->paginate($request->input('perpage', 10));
            $users->map(function ($user) {
                $s3Image = $user->image;
                if ($user->image) {
                    $s3Image = s3_getTempUrl(config('app.domain_name').'/'.$user->image);
                }
                $user['image'] = $s3Image;

                return $user;
            });

            return response()->json([
                'ApiName' => 'tiers-user-mapped',
                'status' => true,
                'data' => $users,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'tiers-user-mapped',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    protected function tiersCountByPosition($tiersId)
    {
        $tiersId = $tiersId ?? 1;
        $uniqueUsers = PositionCommission::select('position_id')->where('tiers_id', $tiersId)
            ->union(
                PositionCommissionUpfronts::select('position_id')->where('tiers_id', $tiersId)
            )
            ->union(
                PositionOverride::select('position_id')->where('tiers_id', $tiersId)
            )
            ->distinct()
            ->count();

        return $uniqueUsers;
    }

    private function getEffectiveDate($data)
    {
        $effectiveDate = [];
        foreach ($data as $log) {
            foreach ($log as $entry) {
                if (! empty($entry['effective_date'])) {
                    $effectiveDate[] = $entry['effective_date'][0];
                }
            }
        }

        sort($effectiveDate);
        $currentDate = date('Y-m-d');

        // Get the first date >= today or fallback to the last date
        $nextDate = array_values(array_filter($effectiveDate, fn ($date) => $date >= $currentDate))[0] ?? end($effectiveDate);

        return $nextDate;
    }

    protected function currentTiresLevel($schema)
    {
        $level = TiersLevel::where('tiers_schema_id', $schema->id)->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->first();

        return [$level, TiersLevel::where(['tiers_schema_id' => $schema->id, 'effective_date' => $level?->effective_date])->get()];
    }

    protected function tiersCountUsedInUser($tiersId)
    {
        $tiersId = $tiersId ?? 1;

        return UserCommissionHistory::select('user_id')->where('tiers_id', $tiersId)
            ->union(UserUpfrontHistory::select('user_id')->where('tiers_id', $tiersId))
            ->union(UserOverrideHistory::select('user_id')->where('direct_tiers_id', $tiersId))
            ->union(UserOverrideHistory::select('user_id')->where('indirect_tiers_id', $tiersId))
            ->distinct()->count();
    }
}
