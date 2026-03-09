<?php

namespace App\Http\Controllers\API\V2\Hiring;

use App\Http\Controllers\Controller;
use App\Models\PositionHirePermission;
use App\Models\Positions;
use App\Models\User;
use Illuminate\Http\Request;

class PositionHirePermissionController extends Controller
{
    /**
     * GET POSITIONS WITH HIRE PERMISSIONS (UNASSIGNED POSITIONS)
     * API: /api/v2/sequidocs/hire-permissions/unassigned-positions
     */
    public function getUnassignedPositionsWithPermissions()
    {
        try {
            // Get positions that don't have offer letter templates
            $positionIdArray = \App\Models\NewSequiDocsTemplatePermission::with('NewSequiDocsTemplate')
                ->whereHas('NewSequiDocsTemplate')
                ->where('category_id', 1)
                ->where('position_type', 'receipient')
                ->pluck('position_id')
                ->toArray();

            // Get unassigned positions with hire permissions
            $unassignedPositions = Positions::where('id', '<>', 1)
                ->where('setup_status', 1)
                ->where('position_name', '!=', 'Super Admin')
                ->whereNotIn('id', $positionIdArray)
                ->with(['hirePermission.grantedBy:id,first_name,last_name'])
                ->get()
                ->map(function ($position) {
                    // Get users currently in this position
                    $users = User::where('sub_position_id', $position->id)
                        ->where('dismiss', 0)
                        ->select('id', 'first_name', 'last_name', 'email', 'image')
                        ->get()
                        ->map(function ($user) {
                            return [
                                'id' => $user->id,
                                'name' => $user->first_name.' '.$user->last_name,
                                'email' => $user->email,
                                'image' => $user->image,
                            ];
                        });

                    return [
                        'position_id' => $position->id,
                        'position_name' => $position->position_name,
                        'has_hire_permission' => $position->hirePermission ? true : false,
                        'granted_by' => $position->hirePermission?->grantedBy?->first_name.' '.$position->hirePermission?->grantedBy?->last_name,
                        'granted_at' => $position->hirePermission?->created_at,
                        'users_in_position' => $users,
                        'user_count' => $users->count(),
                    ];
                });

            return response()->json([
                'ApiName' => 'unassigned-positions-with-permissions',
                'status' => true,
                'message' => 'Unassigned positions with hire permissions retrieved successfully',
                'data' => $unassignedPositions,
            ]);
        } catch (\Exception $error) {
            return response()->json([
                'ApiName' => 'unassigned-positions-with-permissions',
                'status' => false,
                'message' => $error->getMessage(),
            ], 500);
        }
    }

    /**
     * UPDATE PERMISSIONS - DELETE ALL AND ADD NEW POSITIONS
     * API: /api/v2/sequidocs/hire-permissions/update
     */
    public function updatePositionPermissions(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'position_ids' => 'nullable|array',
            'position_ids.*' => 'integer|exists:positions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ApiName' => 'update-position-permissions',
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $positionIds = $request->position_ids ?? [];
            $authUserId = auth()->user()->id;

            // Delete all existing permissions
            PositionHirePermission::truncate();

            // If position_ids array is not empty, add new permissions
            if (! empty($positionIds)) {
                $permissions = [];
                foreach ($positionIds as $positionId) {
                    $permissions[] = [
                        'position_id' => $positionId,
                        'granted_by' => $authUserId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // Bulk insert
                PositionHirePermission::insert($permissions);
            }

            // Get updated data for response
            $positions = [];
            if (! empty($positionIds)) {
                $positions = Positions::whereIn('id', $positionIds)
                    ->select('id', 'position_name')
                    ->get()
                    ->map(function ($position) {
                        return [
                            'id' => $position->id,
                            'position_name' => $position->position_name,
                        ];
                    });
            }

            $message = empty($positionIds)
                ? 'All position permissions have been removed successfully'
                : 'Position permissions updated successfully';

            return response()->json([
                'ApiName' => 'update-position-permissions',
                'status' => true,
                'message' => $message,
                'data' => [
                    'positions_with_permission' => $positions,
                    'total_count' => count($positions),
                ],
            ]);
        } catch (\Exception $error) {
            return response()->json([
                'ApiName' => 'update-position-permissions',
                'status' => false,
                'message' => $error->getMessage(),
            ], 500);
        }
    }

    /**
     * GET ALL POSITIONS WITH HIRE PERMISSIONS
     * API: /api/v2/sequidocs/hire-permissions/positions
     */
    public function getAllPositionsWithPermissions()
    {
        try {
            $positions = Positions::with(['hirePermission.grantedBy:id,first_name,last_name'])
                ->whereIn('id', function ($query) {
                    $query->select('position_id')
                        ->from('position_hire_permissions');
                })
                ->where('setup_status', 1)
                ->where('position_name', '!=', 'Super Admin')
                ->select('id', 'position_name')
                ->get()
                ->map(function ($position) {
                    return [
                        'id' => $position->id,
                        'position_name' => $position->position_name,
                        'granted_by' => $position->hirePermission?->grantedBy?->first_name.' '.$position->hirePermission?->grantedBy?->last_name,
                        'granted_at' => $position->hirePermission?->created_at,
                    ];
                });

            return response()->json([
                'ApiName' => 'positions-with-permissions',
                'status' => true,
                'message' => 'Positions with hire permissions retrieved successfully',
                'data' => $positions,
            ]);
        } catch (\Exception $error) {
            return response()->json([
                'ApiName' => 'positions-with-permissions',
                'status' => false,
                'message' => $error->getMessage(),
            ], 500);
        }
    }
}
