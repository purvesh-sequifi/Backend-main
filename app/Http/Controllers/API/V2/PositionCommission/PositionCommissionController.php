<?php

namespace App\Http\Controllers\API\V2\PositionCommission;

use App\Http\Controllers\Controller;
use App\Models\PositionCommission;
use App\Models\PositionCommissionDeductionSetting;
use App\Models\PositionCommissionUpfronts;
use App\Models\PositionOverride;
use App\Models\Positions;
use App\Models\PositionTier;
use App\Models\TiersSchema;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PositionCommissionController extends Controller
{
    public function tiersDropdownPosition(Request $request): JsonResponse
    {
        try {
            $filter = $request->input('filter', '');
            // $tiers = TiersSchema::with('tier_system','tier_metrics','tier_duration')->select('id', 'prefix', 'schema_description', 'tier_metrics_type', 'tier_type', 'start_day', 'end_day', DB::raw("CONCAT(prefix, '-', schema_name) as schema_name"));
            $tiers = TiersSchema::with('tier_system', 'tier_metrics', 'tier_duration', 'tiers_levels');
            if (isset($filter) && $filter != '') {
                $tiers = $tiers->where('schema_name', 'LIKE', '%'.$filter.'%');
            }
            $tiers = $tiers->orderBy('id', 'ASC')->get();

            return response()->json([
                'ApiName' => 'tiers-dropdown',
                'status' => true,
                'data' => $tiers,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'ApiName' => 'tiers-dropdown',
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function positionByTiersId(Request $request, $id): JsonResponse
    {
        $tiered = [];
        $finalPositions = PositionTier::where(['tiers_schema_id' => $id, 'status' => 1])->groupBy('position_id')->pluck('position_id');
        foreach ($finalPositions as $finalPosition) {
            $effectiveDate = null;
            $positionTier = PositionTier::where('position_id', $finalPosition)->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($positionTier) {
                $effectiveDate = $positionTier->effective_date;
            }
            $tierSection = [];
            $positionTierTypes = PositionTier::where(['tiers_schema_id' => $id, 'position_id' => $finalPosition, 'status' => 1, 'effective_date' => $effectiveDate])->get();
            if (count($positionTierTypes) != 0) {
                foreach ($positionTierTypes as $positionTierType) {
                    if ($positionTierType->type == PositionTier::COMMISSION) {
                        $effectiveDate = null;
                        $commission = PositionCommission::where(['position_id' => $finalPosition, 'tiers_id' => $id])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($commission) {
                            $effectiveDate = $commission->effective_date;
                        }
                        if (PositionCommission::where(['position_id' => $finalPosition, 'tiers_id' => $id, 'effective_date' => $effectiveDate])->first()) {
                            $tierSection[] = PositionTier::COMMISSION;
                        }
                    } elseif ($positionTierType->type == PositionTier::UPFRONT) {
                        $effectiveDate = null;
                        $commission = PositionCommissionUpfronts::where(['position_id' => $finalPosition, 'tiers_id' => $id])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($commission) {
                            $effectiveDate = $commission->effective_date;
                        }
                        if ($positionMilestones = PositionCommissionUpfronts::with('milestoneTrigger')->where(['position_id' => $finalPosition, 'tiers_id' => $id, 'effective_date' => $effectiveDate])->groupBy('milestone_schema_trigger_id')->get()) {
                            foreach ($positionMilestones as $positionMilestone) {
                                $tierSection[] = $positionMilestone?->milestoneTrigger?->name;
                            }
                        }
                    } elseif ($positionTierType->type == PositionTier::OVERRIDE) {
                        $effectiveDate = null;
                        $commission = PositionOverride::where(['position_id' => $finalPosition, 'tiers_id' => $id])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($commission) {
                            $effectiveDate = $commission->effective_date;
                        }
                        if ($positionOverrides = PositionOverride::where(['position_id' => $finalPosition, 'tiers_id' => $id, 'effective_date' => $effectiveDate])->groupBy('override_id')->get()) {
                            foreach ($positionOverrides as $positionOverride) {
                                if ($positionOverride->override_id == '1') {
                                    $tierSection[] = 'Direct Overrides';
                                } elseif ($positionOverride->override_id == '2') {
                                    $tierSection[] = 'Indirect Overrides';
                                } elseif ($positionOverride->override_id == '3') {
                                    $tierSection[] = 'Office Overrides';
                                }
                            }
                        }
                    }
                }

                $tiered[] = [
                    'position_id' => $finalPosition,
                    'position_name' => Positions::where('id', $finalPosition)->first()->position_name ?? null,
                    'position_section' => $tierSection,
                ];
            }
        }

        $perPage = $request->perpage ?? 10;
        $page = $request->page ?? 1;
        $offset = ($page * $perPage) - $perPage;
        $paginatedData = new LengthAwarePaginator(array_slice($tiered, $offset, $perPage), count($tiered), $perPage, $page, ['path' => request()->url(), 'query' => request()->query()]);

        return response()->json([
            'ApiName' => 'tiers-position-mapped',
            'status' => true,
            'data' => $paginatedData,
        ]);
    }

    public function positionsStatus(Request $request, $id)
    {
        $productId = $request->input('productId') ? (int) $request->input('productId') : null;
        $dataQuery = Positions::with(['Upfront', 'Override', 'Reconciliation'])->where('id', $id);
        if ($productId) {
            $dataQuery->whereHas('Upfront', function ($query) use ($productId) {
                $query->where('product_id', $productId);
            });
            $dataQuery->whereHas('Override', function ($query) use ($productId) {
                $query->where('product_id', $productId);
            });
            // $dataQuery->whereHas('Reconciliation', function ($query) use ($productId) {
            //     $query->where('product_id', $productId);
            // });
        }
        $data = $dataQuery->first();
        $filteredOverrides = $data && $data->Override
            ? $data->Override->filter(function ($override) use ($productId) {
                return $override->product_id === $productId;
            })
            : collect();

        $deduction = PositionCommissionDeductionSetting::first();
        $newData = [];
        $newData['position_id'] = $data->id ?? null;
        $newData['position_name'] = $data->position_name ?? null;
        $newData['upfront_status'] = optional(optional(@$data->Upfront)->first())->status_id ?? 0;
        $newData['deduction_status'] = $deduction->first()?->status;
        foreach ($filteredOverrides as $val) {
            $journalName = strtolower(str_replace(' ', '_', $val->overridesDetail?->overrides_type));
            $newData[$journalName.'_status'] = $val->status ?? null;
        }

        $newData['reconciliation_status'] = optional(optional(@$data->Reconciliation)->first())->status ?? 0;

        return response()->json([
            'ApiName' => 'status api',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $newData,
        ], 200);
    }
}
