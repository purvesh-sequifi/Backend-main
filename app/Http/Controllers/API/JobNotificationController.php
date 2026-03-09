<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\JobNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class JobNotificationController extends Controller
{
    /**
     * Get recent job notifications (fallback for Pusher)
     * Used when Pusher connection fails - frontend polls this endpoint
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recent(Request $request): JsonResponse
    {
        try {
            $companyProfileId = config('app.company_profile_id');
            $userId = auth()->id();
            $sessionKey = $request->input('session_key');

            // Base query
            $query = JobNotification::query()
                ->recent(5) // Last 5 minutes
                ->orderBy('created_at', 'desc')
                ->limit(50);

            // Filter by company
            if ($companyProfileId) {
                $query->forCompany($companyProfileId);
            }

            // Filter by session key if provided (most specific)
            if ($sessionKey) {
                $query->forSession($sessionKey);
            } 
            // Otherwise filter by user
            elseif ($userId) {
                $query->where(function ($q) use ($userId) {
                    $q->whereNull('user_id')
                      ->orWhere('user_id', $userId);
                });
            }

            $notifications = $query->get();

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'count' => $notifications->count(),
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            \Log::error('JobNotificationController::recent failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get specific job status by job_id
     *
     * @param string $jobId
     * @return JsonResponse
     */
    public function status(string $jobId): JsonResponse
    {
        try {
            $companyProfileId = config('app.company_profile_id');
            $userId = auth()->id();

            $notification = JobNotification::query()
                ->where('job_id', $jobId)
                ->where('company_profile_id', $companyProfileId)
                ->where(function ($query) use ($userId) {
                    $query->whereNull('user_id')
                          ->orWhere('user_id', $userId);
                })
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $notification,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            \Log::error('JobNotificationController::status failed', [
                'error' => $e->getMessage(),
                'job_id' => $jobId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch job status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get active jobs for current user
     *
     * @return JsonResponse
     */
    public function active(): JsonResponse
    {
        try {
            $companyProfileId = config('app.company_profile_id');
            $userId = auth()->id();

            $activeJobs = JobNotification::query()
                ->active()
                ->forCompany($companyProfileId)
                ->where(function ($query) use ($userId) {
                    $query->whereNull('user_id')
                          ->orWhere('user_id', $userId);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $activeJobs,
                'count' => $activeJobs->count(),
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            \Log::error('JobNotificationController::active failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active jobs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear completed/failed jobs
     *
     * @return JsonResponse
     */
    public function clear(): JsonResponse
    {
        try {
            $companyProfileId = config('app.company_profile_id');
            $userId = auth()->id();

            $deleted = JobNotification::query()
                ->whereIn('status', ['completed', 'failed'])
                ->forCompany($companyProfileId)
                ->forUser($userId)
                ->where('created_at', '<=', now()->subHours(24))
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notifications cleared',
                'deleted_count' => $deleted,
            ]);

        } catch (\Exception $e) {
            \Log::error('JobNotificationController::clear failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear notifications',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

