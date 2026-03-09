<?php

namespace App\Http\Controllers\API\V2\Tiers;

use App\Http\Controllers\Controller;
use App\Models\AdditionalLocations;
use App\Models\CompanySetting;
use App\Models\PositionCommission;
use App\Models\PositionCommissionUpfronts;
use App\Models\PositionOverride;
use App\Models\PositionTier;
use App\Models\ProductMilestoneHistories;
use App\Models\TiersLevel;
use App\Models\TiersResetHistory;
use App\Models\TiersSchema;
use App\Models\TierSystem;
use App\Models\UserAdditionalOfficeOverrideHistory;
use App\Models\UserOrganizationHistory;
use App\Models\UsersCurrentTierLevel;
use App\Models\UsersTiersHistory;
use App\Traits\EmailNotificationTrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TierResetController extends Controller
{
    use EmailNotificationTrait;

    public function resetTiers()
    {
        if (! CompanySetting::where(['type' => 'tier', 'status' => '1'])->first()) {
            return ['success' => false, 'message' => 'Tiers not enabled!!'];
        }

        $tiersReport = [];
        try {
            DB::beginTransaction();
            $date = Carbon::now()->subDay();
            $durations = config('global_vars.TIER_RESET');
            $tiers = TiersSchema::whereHas('tier_duration', function ($q) use ($durations) {
                $q->whereIn('value', $durations);
            })->with('tier_duration')->where(['tier_type' => 'Progressive'])->whereDate('next_reset_date', date('Y-m-d'))->get();
            foreach ($tiers as $tier) {
                try {
                    DB::beginTransaction();
                    $duration = getDurationForTier($tier, null, $date);
                    $startDate = $duration['start_date'];
                    $endDate = $duration['end_date'];
                    $nextResetDate = getDurationForTier($tier, null, Carbon::parse($endDate)->addDay()->format('Y-m-d'));
                    $nextResetDate = Carbon::parse($nextResetDate['end_date'])->addDay()->format('Y-m-d');
                    if ($startDate && $endDate && $nextResetDate) {
                        $tiersResetHistory = TiersResetHistory::create([
                            'tier_schema_id' => $tier->id,
                            'tiers_type' => $tier->tier_type,
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'reset_date_time' => Carbon::now(),
                        ]);

                        $users = [];
                        $subQuery = UserOrganizationHistory::select(
                            'id',
                            'user_id',
                            'effective_date',
                            DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn')
                        )->where('effective_date', '<=', $endDate);
                        $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1)->pluck('id');
                        $positionTiers = [];
                        $positions = PositionTier::groupBy('position_id')->get();
                        foreach ($positions as $position) {
                            $effectiveDate = null;
                            $positionTier = PositionTier::where('position_id', $position->position_id)->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($positionTier) {
                                $effectiveDate = $positionTier->effective_date;
                            }

                            $activeTiers = PositionTier::where(['tiers_schema_id' => $tier->id, 'position_id' => $position->position_id, 'status' => '1', 'effective_date' => $effectiveDate])->get();
                            foreach ($activeTiers as $activeTier) {
                                $positionTiers[] = $activeTier;
                            }
                        }

                        foreach ($positionTiers as $positionTier) {
                            $userOrganizations = UserOrganizationHistory::with('user')->whereIn('id', $results)->where('sub_position_id', $positionTier->position_id)->get();
                            foreach ($userOrganizations as $userOrganization) {
                                $userCurrentTiers = UsersCurrentTierLevel::where(['user_id' => $userOrganization->user_id, 'tier_schema_id' => $tier->id, 'tiers_type' => $tier->tier_type, 'type' => $positionTier->type])->get();
                                foreach ($userCurrentTiers as $userCurrentTier) {
                                    $userCurrentTier = $userCurrentTier->toArray();
                                    $userCurrentTier['tiers_history_id'] = $tiersResetHistory->id;
                                    $userCurrentTier['reset_date_time'] = Carbon::now();

                                    $users[] = [
                                        'user_id' => $userOrganization->user_id,
                                        'user_name' => isset($userOrganization->user->first_name) ? $userOrganization->user->first_name.' '.$userOrganization->user->last_name : null,
                                        'tier' => $userCurrentTier,
                                    ];
                                    UsersTiersHistory::create($userCurrentTier);
                                }
                            }
                        }

                        $tier->next_reset_date = $nextResetDate;
                        $tier->save();

                        $tiersReport[] = [
                            'tier_name' => $tier->schema_name,
                            'tier_type' => $tier->tier_type,
                            'users' => $users,
                            'next_reset_date' => $nextResetDate,
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'is_error' => false,
                            'line' => '-',
                            'file' => '-',
                            'message' => '-',
                        ];
                    } else {
                        $tiersReport[] = [
                            'tier_name' => $tier->schema_name,
                            'tier_type' => $tier->tier_type,
                            'users' => [],
                            'next_reset_date' => $nextResetDate,
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'is_error' => true,
                            'line' => '-',
                            'file' => '-',
                            'message' => 'Start date, End date or Next reset date not found!!',
                        ];
                    }
                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $tiersReport[] = [
                        'tier_name' => $tier->schema_name,
                        'tier_type' => $tier->tier_type,
                        'users' => [],
                        'next_reset_date' => '-',
                        'start_date' => '-',
                        'end_date' => '-',
                        'is_error' => true,
                        'line' => $e->getLine(),
                        'file' => $e->getFile(),
                        'message' => $e->getMessage(),
                    ];
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $tiersReport[] = [
                'tier_name' => '-',
                'tier_type' => '-',
                'users' => [],
                'next_reset_date' => '-',
                'start_date' => '-',
                'end_date' => '-',
                'is_error' => true,
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'message' => $e->getMessage(),
            ];
        }

        return ['success' => true, 'message' => 'Tiers reset successfully!!', 'data' => ['tiers' => $tiersReport]];
    }

    public function tiersSync($userIds)
    {
        if (! CompanySetting::where(['type' => 'tier', 'status' => '1'])->first()) {
            return ['success' => false, 'message' => 'Tiers not enabled!!'];
        }

        $syncErrorReport = [];
        if ($userIds) {
            $userIds = explode(',', $userIds);
        } else {
            $userIds = [];
        }
        try {
            $date = date('Y-m-d');
            DB::beginTransaction();
            UsersCurrentTierLevel::when((count($userIds) != 0), function ($q) use ($userIds) {
                $q->whereIn('user_id', $userIds);
            })->delete();
            $overrideTypes = ['Office', 'Additional Office', 'Direct', 'InDirect'];

            $positionTiers = [];
            $positions = PositionTier::groupBy('position_id')->get();
            $subQuery = UserOrganizationHistory::select(
                'id',
                'user_id',
                'effective_date',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY effective_date DESC, id DESC) as rn')
            )->where('effective_date', '<=', $date);
            $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1)->pluck('id');
            foreach ($positions as $position) {
                $effectiveDate = null;
                $positionTier = PositionTier::where('position_id', $position->position_id)->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($positionTier) {
                    $effectiveDate = $positionTier->effective_date;
                }

                $activeTiers = PositionTier::with(['tiersSchema.tier_system' => function ($q) {
                    $q->whereNotIn('value', [TierSystem::TIERED_BASED_ON_JOB_METRICS_PERFORMANCE, TierSystem::TIERED_BASED_ON_JOB_METRICS_EXACT_MATCH_PERFORMANCE]);
                }, 'tiersSchema.tier_metrics', 'tiersSchema.tier_duration'])->whereHas('tiersSchema.tier_system', function ($q) {
                    $q->whereNotIn('value', [TierSystem::TIERED_BASED_ON_JOB_METRICS_PERFORMANCE, TierSystem::TIERED_BASED_ON_JOB_METRICS_EXACT_MATCH_PERFORMANCE]);
                })->where(['position_id' => $position->position_id, 'status' => '1', 'effective_date' => $effectiveDate])->get();

                foreach ($activeTiers as $activeTier) {
                    $positionTiers[] = $activeTier;
                }
            }
            foreach ($positionTiers as $positionTier) {
                $positionId = $positionTier->position_id;
                $userOrganizations = UserOrganizationHistory::select('user_id', 'effective_date', 'sub_position_id')->whereIn('id', $results)
                    ->when((count($userIds) != 0), function ($q) use ($userIds) {
                        $q->whereIn('user_id', $userIds);
                    })->where('sub_position_id', $positionId)->get();
                foreach ($userOrganizations as $userOrganization) {
                    $userId = $userOrganization->user_id;
                    $tierSchema = $positionTier->tiersSchema;
                    $tierSystem = $positionTier->tiersSchema->tier_system;
                    $tierMetric = $positionTier->tiersSchema->tier_metrics;
                    $duration = getDurationForTier($tierSchema, $userOrganization, $date);
                    $tierLevel = TiersLevel::where('tiers_schema_id', $tierSchema->id)->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ($tierLevel) {
                        $tierLevels = TiersLevel::where(['tiers_schema_id' => $tierSchema->id, 'effective_date' => $tierLevel->effective_date])->orderBy('level')->get();
                    } else {
                        $tierLevels = TiersLevel::where('tiers_schema_id', $tierSchema->id)->whereNUll('effective_date')->orderBy('level')->get();
                    }
                    $organizations = UserOrganizationHistory::where(['user_id' => $userOrganization->user_id, 'effective_date' => $userOrganization->effective_date])->groupBy('product_id', 'effective_date')->get();
                    if ($positionTier->type == PositionTier::UPFRONT) {
                        try {
                            DB::beginTransaction();
                            if ($positionTier->tier_advancement == PositionTier::ALL_PRODUCTS) {
                                $nextLevel = null;
                                [$level, $_, $other] = getCurrentTier($duration, $tierSystem->value, $tierMetric->value, 'all', $tierLevels, $userId, $date);
                                foreach ($tierLevels as $tierLevel) {
                                    if ($tierLevel->level == $level->level) {
                                        $nextLevel = $tierLevel;
                                    }

                                    if ($tierLevel->level > $level->level) {
                                        $nextLevel = $tierLevel;
                                        break;
                                    }
                                }

                                $currentDescription = tiersCurrentDescription($other);
                                [$maxed, $remainingDescription] = tiersRemainingDescription($other, $nextLevel);
                                foreach ($organizations as $organization) {
                                    $milestones = ProductMilestoneHistories::with('milestone.milestone_trigger')->where('product_id', $organization->product_id)->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                    $milestones = isset($milestones->milestone->milestone_trigger) ? $milestones->milestone->milestone_trigger->slice(0, $milestones->milestone->milestone_trigger->count() - 1) : [];
                                    $upfront = PositionCommissionUpfronts::where(['position_id' => $positionId, 'product_id' => $organization->product_id])->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                    if (! $upfront) {
                                        $upfront = PositionCommissionUpfronts::where(['position_id' => $positionId, 'product_id' => $organization->product_id])->whereNull('effective_date')->first();
                                    }
                                    foreach ($milestones as $key => $milestone) {
                                        UsersCurrentTierLevel::create([
                                            'user_id' => $userId,
                                            'product_id' => $organization->product_id,
                                            'tier_schema_id' => $tierSchema->id,
                                            'tier_schema_level_id' => $level->id,
                                            'next_tier_schema_level_id' => $nextLevel->id,
                                            'tiers_type' => $tierSchema->tier_type,
                                            'type' => 'Upfront',
                                            'sub_type' => 'm'.($key + 1),
                                            'current_value' => $currentDescription,
                                            'remaining_value' => $remainingDescription,
                                            'current_level' => isset($level->level) ? $level->level : null,
                                            'remaining_level' => isset($nextLevel->level) ? $nextLevel->level : null,
                                            'maxed' => $maxed,
                                            'status' => $upfront ? $upfront->upfront_status : 0,
                                        ]);
                                    }
                                }
                            } elseif ($positionTier->tier_advancement == PositionTier::SELECTED_PRODUCTS) {
                                foreach ($organizations as $organization) {
                                    $nextLevel = null;
                                    [$level, $_, $other] = getCurrentTier($duration, $tierSystem->value, $tierMetric->value, $organization->product_id, $tierLevels, $userId, $date);
                                    foreach ($tierLevels as $tierLevel) {
                                        if ($tierLevel->level == $level->level) {
                                            $nextLevel = $tierLevel;
                                        }

                                        if ($tierLevel->level > $level->level) {
                                            $nextLevel = $tierLevel;
                                            break;
                                        }
                                    }

                                    $currentDescription = tiersCurrentDescription($other);
                                    [$maxed, $remainingDescription] = tiersRemainingDescription($other, $nextLevel);
                                    $milestones = ProductMilestoneHistories::with('milestone.milestone_trigger')->where('product_id', $organization->product_id)->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                    $milestones = isset($milestones->milestone->milestone_trigger) ? $milestones->milestone->milestone_trigger->slice(0, $milestones->milestone->milestone_trigger->count() - 1) : [];
                                    $upfront = PositionCommissionUpfronts::where(['position_id' => $positionId, 'product_id' => $organization->product_id])->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                    if (! $upfront) {
                                        $upfront = PositionCommissionUpfronts::where(['position_id' => $positionId, 'product_id' => $organization->product_id])->whereNull('effective_date')->first();
                                    }
                                    foreach ($milestones as $key => $milestone) {
                                        UsersCurrentTierLevel::create([
                                            'user_id' => $userId,
                                            'product_id' => $organization->product_id,
                                            'tier_schema_id' => $tierSchema->id,
                                            'tier_schema_level_id' => $level->id,
                                            'next_tier_schema_level_id' => $nextLevel->id,
                                            'tiers_type' => $tierSchema->tier_type,
                                            'type' => 'Upfront',
                                            'sub_type' => 'm'.($key + 1),
                                            'current_value' => $currentDescription,
                                            'remaining_value' => $remainingDescription,
                                            'current_level' => isset($level->level) ? $level->level : null,
                                            'remaining_level' => isset($nextLevel->level) ? $nextLevel->level : null,
                                            'maxed' => $maxed,
                                            'status' => $upfront ? $upfront->upfront_status : 0,
                                        ]);
                                    }
                                }
                            }
                            DB::commit();
                        } catch (\Throwable $e) {
                            DB::rollBack();
                            $syncErrorReport[] = [
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'message' => $e->getMessage(),
                                'user_id' => $userOrganization->user_id,
                            ];
                        }
                    } elseif ($positionTier->type == PositionTier::COMMISSION) {
                        try {
                            DB::beginTransaction();
                            if ($positionTier->tier_advancement == PositionTier::ALL_PRODUCTS) {
                                $nextLevel = null;
                                [$level, $_, $other] = getCurrentTier($duration, $tierSystem->value, $tierMetric->value, 'all', $tierLevels, $userId, $date);
                                foreach ($tierLevels as $tierLevel) {
                                    if ($tierLevel->level == $level->level) {
                                        $nextLevel = $tierLevel;
                                    }

                                    if ($tierLevel->level > $level->level) {
                                        $nextLevel = $tierLevel;
                                        break;
                                    }
                                }

                                $currentDescription = tiersCurrentDescription($other);
                                [$maxed, $remainingDescription] = tiersRemainingDescription($other, $nextLevel);
                                foreach ($organizations as $organization) {
                                    $commission = PositionCommission::where(['position_id' => $positionId, 'product_id' => $organization->product_id])->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                    if (! $commission) {
                                        $commission = PositionCommission::where(['position_id' => $positionId, 'product_id' => $organization->product_id])->whereNull('effective_date')->first();
                                    }
                                    UsersCurrentTierLevel::create([
                                        'user_id' => $userId,
                                        'product_id' => $organization->product_id,
                                        'tier_schema_id' => $tierSchema->id,
                                        'tier_schema_level_id' => $level->id,
                                        'next_tier_schema_level_id' => $nextLevel->id,
                                        'tiers_type' => $tierSchema->tier_type,
                                        'type' => 'Commission',
                                        'sub_type' => 'Commission',
                                        'current_value' => $currentDescription,
                                        'remaining_value' => $remainingDescription,
                                        'current_level' => isset($level->level) ? $level->level : null,
                                        'remaining_level' => isset($nextLevel->level) ? $nextLevel->level : null,
                                        'maxed' => $maxed,
                                        'status' => $commission ? $commission->commission_status : 0,
                                    ]);
                                }
                            } elseif ($positionTier->tier_advancement == PositionTier::SELECTED_PRODUCTS) {
                                foreach ($organizations as $organization) {
                                    $nextLevel = null;
                                    [$level, $_, $other] = getCurrentTier($duration, $tierSystem->value, $tierMetric->value, $organization->product_id, $tierLevels, $userId, $date);
                                    foreach ($tierLevels as $tierLevel) {
                                        if ($tierLevel->level == $level->level) {
                                            $nextLevel = $tierLevel;
                                        }

                                        if ($tierLevel->level > $level->level) {
                                            $nextLevel = $tierLevel;
                                            break;
                                        }
                                    }

                                    $currentDescription = tiersCurrentDescription($other);
                                    [$maxed, $remainingDescription] = tiersRemainingDescription($other, $nextLevel);
                                    $commission = PositionCommission::where(['position_id' => $positionId, 'product_id' => $organization->product_id])->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                    if (! $commission) {
                                        $commission = PositionCommission::where(['position_id' => $positionId, 'product_id' => $organization->product_id])->whereNull('effective_date')->first();
                                    }
                                    UsersCurrentTierLevel::create([
                                        'user_id' => $userId,
                                        'product_id' => $organization->product_id,
                                        'tier_schema_id' => $tierSchema->id,
                                        'tier_schema_level_id' => $level->id,
                                        'next_tier_schema_level_id' => $nextLevel->id,
                                        'tiers_type' => $tierSchema->tier_type,
                                        'type' => 'Commission',
                                        'sub_type' => 'Commission',
                                        'current_value' => $currentDescription,
                                        'remaining_value' => $remainingDescription,
                                        'current_level' => isset($level->level) ? $level->level : null,
                                        'remaining_level' => isset($nextLevel->level) ? $nextLevel->level : null,
                                        'maxed' => $maxed,
                                        'status' => $commission ? $commission->commission_status : 0,
                                    ]);
                                }
                            }
                            DB::commit();
                        } catch (\Throwable $e) {
                            DB::rollBack();
                            $syncErrorReport[] = [
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'message' => $e->getMessage(),
                                'user_id' => $userOrganization->user_id,
                            ];
                        }
                    } elseif ($positionTier->type == PositionTier::OVERRIDE) {
                        try {
                            DB::beginTransaction();
                            foreach ($overrideTypes as $overrideType) {
                                $id = null;
                                if ($overrideType == 'Direct') {
                                    $id = 1;
                                } elseif ($overrideType == 'InDirect') {
                                    $id = 2;
                                } elseif ($overrideType == 'Office' || $overrideType == 'Additional Office') {
                                    $id = 3;
                                }
                                if ($positionTier->tier_advancement == PositionTier::ALL_PRODUCTS) {
                                    $nextLevel = null;
                                    [$level, $_, $other] = getCurrentTier($duration, $tierSystem->value, $tierMetric->value, 'all', $tierLevels, $userId, $date);
                                    foreach ($tierLevels as $tierLevel) {
                                        if ($tierLevel->level == $level->level) {
                                            $nextLevel = $tierLevel;
                                        }

                                        if ($tierLevel->level > $level->level) {
                                            $nextLevel = $tierLevel;
                                            break;
                                        }
                                    }

                                    $currentDescription = tiersCurrentDescription($other);
                                    [$maxed, $remainingDescription] = tiersRemainingDescription($other, $nextLevel);
                                    foreach ($organizations as $organization) {
                                        $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $organization->product_id, 'override_id' => $id])->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                        if (! $positionOverride) {
                                            $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $organization->product_id, 'override_id' => $id])->whereNull('effective_date')->first();
                                        }

                                        if ($overrideType == 'Additional Office') {
                                            $currentAdditional = AdditionalLocations::where(['user_id' => $userId])->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                            if ($currentAdditional) {
                                                $additionalLocations = AdditionalLocations::with('office')->where(['user_id' => $userId, 'effective_date' => $currentAdditional?->effective_date])->get();
                                                foreach ($additionalLocations as $additionalLocation) {
                                                    $officeId = $additionalLocation?->office?->id;
                                                    $additionalOverride = UserAdditionalOfficeOverrideHistory::where(['user_id' => $userId, 'product_id' => $organization->product_id, 'office_id' => $officeId])->where('override_effective_date', '<=', $date)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();

                                                    if ($additionalOverride && $additionalOverride->tiers_id) {
                                                        UsersCurrentTierLevel::create([
                                                            'user_id' => $userId,
                                                            'product_id' => $organization->product_id,
                                                            'tier_schema_id' => $tierSchema->id,
                                                            'tier_schema_level_id' => $level->id,
                                                            'next_tier_schema_level_id' => $nextLevel->id,
                                                            'office_id' => $officeId,
                                                            'tiers_type' => $tierSchema->tier_type,
                                                            'type' => 'Override',
                                                            'sub_type' => $overrideType,
                                                            'current_value' => $currentDescription,
                                                            'remaining_value' => $remainingDescription,
                                                            'current_level' => isset($level->level) ? $level->level : null,
                                                            'remaining_level' => isset($nextLevel->level) ? $nextLevel->level : null,
                                                            'maxed' => $maxed,
                                                            'status' => $positionOverride ? $positionOverride->status : 0,
                                                        ]);
                                                    }
                                                }
                                            }
                                        } else {
                                            UsersCurrentTierLevel::create([
                                                'user_id' => $userId,
                                                'product_id' => $organization->product_id,
                                                'tier_schema_id' => $tierSchema->id,
                                                'tier_schema_level_id' => $level->id,
                                                'next_tier_schema_level_id' => $nextLevel->id,
                                                'tiers_type' => $tierSchema->tier_type,
                                                'type' => 'Override',
                                                'sub_type' => $overrideType,
                                                'current_value' => $currentDescription,
                                                'remaining_value' => $remainingDescription,
                                                'current_level' => isset($level->level) ? $level->level : null,
                                                'remaining_level' => isset($nextLevel->level) ? $nextLevel->level : null,
                                                'maxed' => $maxed,
                                                'status' => $positionOverride ? $positionOverride->status : 0,
                                            ]);
                                        }
                                    }
                                } elseif ($positionTier->tier_advancement == PositionTier::SELECTED_PRODUCTS) {
                                    foreach ($organizations as $organization) {
                                        $nextLevel = null;
                                        [$level, $_, $other] = getCurrentTier($duration, $tierSystem->value, $tierMetric->value, $organization->product_id, $tierLevels, $userId, $date);
                                        foreach ($tierLevels as $tierLevel) {
                                            if ($tierLevel->level == $level->level) {
                                                $nextLevel = $tierLevel;
                                            }

                                            if ($tierLevel->level > $level->level) {
                                                $nextLevel = $tierLevel;
                                                break;
                                            }
                                        }

                                        $currentDescription = tiersCurrentDescription($other);
                                        [$maxed, $remainingDescription] = tiersRemainingDescription($other, $nextLevel);
                                        $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $organization->product_id, 'override_id' => $id])->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                        if (! $positionOverride) {
                                            $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $organization->product_id, 'override_id' => $id])->whereNull('effective_date')->first();
                                        }

                                        if ($overrideType == 'Additional Office') {
                                            $currentAdditional = AdditionalLocations::where(['user_id' => $userId])->where('effective_date', '<=', $date)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                            if ($currentAdditional) {
                                                $additionalLocations = AdditionalLocations::with('office')->where(['user_id' => $userId, 'effective_date' => $currentAdditional?->effective_date])->get();
                                                foreach ($additionalLocations as $additionalLocation) {
                                                    $officeId = $additionalLocation?->office?->id;
                                                    $additionalOverride = UserAdditionalOfficeOverrideHistory::where(['user_id' => $userId, 'product_id' => $organization->product_id, 'office_id' => $officeId])->where('override_effective_date', '<=', $date)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();

                                                    if ($additionalOverride && $additionalOverride->tiers_id) {
                                                        UsersCurrentTierLevel::create([
                                                            'user_id' => $userId,
                                                            'product_id' => $organization->product_id,
                                                            'tier_schema_id' => $tierSchema->id,
                                                            'tier_schema_level_id' => $level->id,
                                                            'next_tier_schema_level_id' => $nextLevel->id,
                                                            'office_id' => $officeId,
                                                            'tiers_type' => $tierSchema->tier_type,
                                                            'type' => 'Override',
                                                            'sub_type' => $overrideType,
                                                            'current_value' => $currentDescription,
                                                            'remaining_value' => $remainingDescription,
                                                            'current_level' => isset($level->level) ? $level->level : null,
                                                            'remaining_level' => isset($nextLevel->level) ? $nextLevel->level : null,
                                                            'maxed' => $maxed,
                                                            'status' => $positionOverride ? $positionOverride->status : 0,
                                                        ]);
                                                    }
                                                }
                                            }
                                        } else {
                                            UsersCurrentTierLevel::create([
                                                'user_id' => $userId,
                                                'product_id' => $organization->product_id,
                                                'tier_schema_id' => $tierSchema->id,
                                                'tier_schema_level_id' => $level->id,
                                                'next_tier_schema_level_id' => $nextLevel->id,
                                                'tiers_type' => $tierSchema->tier_type,
                                                'type' => 'Override',
                                                'sub_type' => $overrideType,
                                                'current_value' => $currentDescription,
                                                'remaining_value' => $remainingDescription,
                                                'current_level' => isset($level->level) ? $level->level : null,
                                                'remaining_level' => isset($nextLevel->level) ? $nextLevel->level : null,
                                                'maxed' => $maxed,
                                                'status' => $positionOverride ? $positionOverride->status : 0,
                                            ]);
                                        }
                                    }
                                }
                            }
                            DB::commit();
                        } catch (\Throwable $e) {
                            DB::rollBack();
                            $syncErrorReport[] = [
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'message' => $e->getMessage(),
                                'user_id' => $userOrganization->user_id,
                            ];
                        }
                    }
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $syncErrorReport[] = [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'message' => $e->getMessage(),
                'user_id' => '',
            ];
        }

        if (count($syncErrorReport) != 0) {
            return ['success' => false, 'message' => 'something went wrong!!', 'data' => $syncErrorReport];
        }

        return ['success' => true, 'message' => 'Tiers sync successfully!!'];
    }
}
