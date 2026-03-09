<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Traits\IntegrationTrait;
use App\Services\EspQuickBaseService;
use App\Services\OnyxRepDataPushService;
use App\Jobs\SyncUserToOnyxJob;
use App\Jobs\SyncUserToEspJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class IntegrationController extends Controller
{
    use IntegrationTrait;

    public function __construct(
        protected EspQuickBaseService $espQuickBaseService,
        protected OnyxRepDataPushService $onyxRepDataPushService
    ) {}

    public function updateAveyoHsIdForUser(Request $request)
    {
        $this->updateUserAveyoHsId();

        return 'Success...';
    }

    /**
     * Push user data to EspQuickBase
     *
     * Supports both single and bulk user sync.
     * - Single sync: Immediate synchronous response
     * - Bulk sync: Processes users in chunks to prevent timeouts
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Universal push endpoint for integrations
     *
     * - Single user: Provides immediate sync response
     * - All users: Dispatches queue jobs for background processing
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function pushUserData(Request $request)
    {
        $payload = $request->all();

        // Validate integration name
        if (!isset($payload['integration_name'])) {
            return response()->json([
                'status' => false,
                'message' => 'Missing required parameter: integration_name',
                'available_integrations' => [
                    'OnyxRepDataPush',
                    'EspQuickBase'
                ]
            ], 400);
        }

        $integrationName = $payload['integration_name'];

        // Validate integration exists and is active
        $integration = \App\Models\Integration::where('name', $integrationName)
            ->where('status', 1)
            ->first();

        if (!$integration) {
            return response()->json([
                'status' => false,
                'message' => "Integration '{$integrationName}' not found or not active.",
                'hint' => 'Check integrations table'
            ], 404);
        }

        // Determine which service to use
        switch ($integrationName) {
            case 'OnyxRepDataPush':
                return $this->handleOnyxPush($payload);
            case 'EspQuickBase':
                return $this->handleEspPush($payload);
            default:
                return response()->json([
                    'status' => false,
                    'message' => "Integration '{$integrationName}' is not implemented yet."
                ], 501);
        }
    }

    /**
     * Handle Onyx push - single or all users
     */
    private function handleOnyxPush(array $payload)
    {
        $eventType = $payload['event_type'] ?? 'rep_update';

        if (!in_array($eventType, ['new_rep', 'rep_update'])) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid event_type. Must be "new_rep" or "rep_update".',
                'provided' => $eventType
            ], 400);
        }

        // Single user sync
        if (isset($payload['user_id'])) {
            $userId = $payload['user_id'];
            $response = $this->onyxRepDataPushService->sendUserData($userId, $eventType);

            return response()->json([
                'mode' => 'single',
                'integration' => 'OnyxRepDataPush',
                'user_id' => $userId,
                'event_type' => $eventType,
                'response' => $response
            ], $response['status'] ? 200 : 500);
        }

        // All users - dispatch jobs
        $users = \App\Models\User::all();
        $totalUsers = $users->count();

        if ($totalUsers === 0) {
            return response()->json([
                'status' => false,
                'message' => 'No active users found to sync.'
            ], 404);
        }

        // Confirm if large batch (safety check to prevent accidental mass syncs)
        if ($totalUsers > 100 && !isset($payload['confirm'])) {
            return response()->json([
                'status' => false,
                'message' => "You are about to sync {$totalUsers} users. Add 'confirm': true to proceed.",
                'total_users' => $totalUsers,
                'warning' => 'This will dispatch background jobs for all users.'
            ], 400);
        }

        // Prepare jobs for batch dispatch
        $jobs = [];
        foreach ($users as $user) {
            $jobs[] = new SyncUserToOnyxJob($user->id, $eventType);
        }

        // Dispatch as a batch with enhanced tracking and callbacks
        $batchName = "Onyx Sync [{$eventType}] - " . now()->format('Y-m-d H:i:s');

        $batch = Bus::batch($jobs)
            ->name($batchName)
            ->allowFailures() // Continue on individual failures
            ->then(function ($batch) use ($eventType, $totalUsers) {
                // Called when all jobs complete successfully
                Log::info('✅ Onyx batch completed successfully', [
                    'batch_id' => $batch->id,
                    'batch_name' => $batch->name,
                    'event_type' => $eventType,
                    'total_users' => $totalUsers,
                    'processed_jobs' => $batch->processedJobs(),
                    'failed_jobs' => $batch->failedJobs
                ]);
            })
            ->catch(function ($batch, \Throwable $e) use ($eventType) {
                // Called when first job failure occurs
                Log::error('❌ Onyx batch encountered error', [
                    'batch_id' => $batch->id,
                    'batch_name' => $batch->name,
                    'event_type' => $eventType,
                    'error' => $e->getMessage(),
                    'failed_jobs' => $batch->failedJobs
                ]);
            })
            ->finally(function ($batch) use ($eventType, $totalUsers) {
                // Always called when batch finishes (success or failure)
                Log::info('🏁 Onyx batch finished', [
                    'batch_id' => $batch->id,
                    'batch_name' => $batch->name,
                    'event_type' => $eventType,
                    'total_jobs' => $totalUsers,
                    'processed' => $batch->processedJobs(),
                    'failed' => $batch->failedJobs,
                    'progress' => $batch->progress() . '%',
                    'status' => $batch->finished() ? 'completed' : 'incomplete'
                ]);
            })
            ->dispatch();

        return response()->json([
            'status' => true,
            'mode' => 'bulk',
            'integration' => 'OnyxRepDataPush',
            'message' => 'Bulk sync batch dispatched to queue',
            'batch_id' => $batch->id,
            'total_users' => $totalUsers,
            'total_jobs' => count($jobs),
            'event_type' => $eventType,
            'tracking' => [
                'Check batch progress: php artisan queue:batches',
                'Monitor queue: php artisan queue:work',
                'View batch details: Bus::findBatch("' . $batch->id . '")',
                'Cancel batch: Bus::findBatch("' . $batch->id . '")->cancel()'
            ],
            'notes' => [
                'Jobs will process in background queue',
                'Check logs: storage/logs/laravel.log',
                'Check database: interigation_transaction_logs table',
                'Failed jobs will not stop the batch'
            ]
        ], 202);
    }

    /**
     * Handle ESP push - single or all users
     */
    private function handleEspPush(array $payload)
    {
        // Single user sync
        if (isset($payload['user_id'])) {
            $userId = $payload['user_id'];
            $response = $this->espQuickBaseService->sendUserData($userId, 'from_api_manual');

            return response()->json([
                'mode' => 'single',
                'integration' => 'EspQuickBase',
                'user_id' => $userId,
                'response' => $response
            ], 200);
        }

        // All users - dispatch jobs
        $users = \App\Models\User::all();
        $totalUsers = $users->count();

        if ($totalUsers === 0) {
            return response()->json([
                'status' => false,
                'message' => 'No users found to sync.'
            ], 404);
        }

        // Confirm if large batch (safety check to prevent accidental mass syncs)
        if ($totalUsers > 100 && !isset($payload['confirm'])) {
            return response()->json([
                'status' => false,
                'message' => "You are about to sync {$totalUsers} users. Add 'confirm': true to proceed.",
                'total_users' => $totalUsers,
                'warning' => 'This will dispatch background jobs for all users.'
            ], 400);
        }

        // Prepare jobs for batch dispatch
        $jobs = [];
        foreach ($users as $user) {
            $jobs[] = new SyncUserToEspJob($user->id);
        }

        // Dispatch as a batch with enhanced tracking and callbacks
        $batchName = "ESP Sync - " . now()->format('Y-m-d H:i:s');

        $batch = Bus::batch($jobs)
            ->name($batchName)
            ->allowFailures() // Continue on individual failures
            ->then(function ($batch) use ($totalUsers) {
                // Called when all jobs complete successfully
                Log::info('✅ ESP batch completed successfully', [
                    'batch_id' => $batch->id,
                    'batch_name' => $batch->name,
                    'total_users' => $totalUsers,
                    'processed_jobs' => $batch->processedJobs(),
                    'failed_jobs' => $batch->failedJobs
                ]);
            })
            ->catch(function ($batch, \Throwable $e) {
                // Called when first job failure occurs
                Log::error('❌ ESP batch encountered error', [
                    'batch_id' => $batch->id,
                    'batch_name' => $batch->name,
                    'error' => $e->getMessage(),
                    'failed_jobs' => $batch->failedJobs
                ]);
            })
            ->finally(function ($batch) use ($totalUsers) {
                // Always called when batch finishes (success or failure)
                Log::info('🏁 ESP batch finished', [
                    'batch_id' => $batch->id,
                    'batch_name' => $batch->name,
                    'total_jobs' => $totalUsers,
                    'processed' => $batch->processedJobs(),
                    'failed' => $batch->failedJobs,
                    'progress' => $batch->progress() . '%',
                    'status' => $batch->finished() ? 'completed' : 'incomplete'
                ]);
            })
            ->dispatch();

        return response()->json([
            'status' => true,
            'mode' => 'bulk',
            'integration' => 'EspQuickBase',
            'message' => 'Bulk sync batch dispatched to queue',
            'batch_id' => $batch->id,
            'total_users' => $totalUsers,
            'total_jobs' => count($jobs),
            'tracking' => [
                'Check batch progress: php artisan queue:batches',
                'Monitor queue: php artisan queue:work',
                'View batch details: Bus::findBatch("' . $batch->id . '")',
                'Cancel batch: Bus::findBatch("' . $batch->id . '")->cancel()'
            ],
            'notes' => [
                'Jobs will process in background queue',
                'Check logs: storage/logs/laravel.log',
                'Check database: Check your ESP logs/database',
                'Failed jobs will not stop the batch'
            ]
        ], 202);
    }

}
