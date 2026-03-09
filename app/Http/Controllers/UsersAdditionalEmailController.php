<?php

namespace App\Http\Controllers;

use App\Core\Traits\FieldRoutesUserDataTrait;
use App\Core\Traits\SubroutineListTrait;
use App\Jobs\GenerateAlertJob;
use App\Models\User;
use App\Models\UserProfileHistory;
use App\Models\UsersAdditionalEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UsersAdditionalEmailController extends Controller
{
    use FieldRoutesUserDataTrait;
    use SubroutineListTrait;

    public function users_additional_emails_list($user_id): JsonResponse
    {
        $message = 'Data get Successfully.';
        $status_code = 200;
        $data = UsersAdditionalEmail::where('user_id', $user_id)->get();

        return response()->json([
            'ApiName' => 'users_additional_emails_list',
            'status' => true,
            'message' => $message,
            'data' => $data,
        ], $status_code);

    }

    public function add_users_additional_emails(Request $request): JsonResponse
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|min:1',
            'emails' => 'required|array',
            'emails.*' => 'email',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $emails = $request->emails;
        $user_id = $request->user_id;

        Log::info('Phase 1 Email processing started', [
            'user_id' => $user_id,
            'email_count' => count($emails),
        ]);

        try {
            // PHASE 1: OPTIMIZED EMAIL OPERATIONS (Batch processing)
            $conflictingEmails = $this->batch_check_email_uniqueness($emails, $user_id);
            $validEmails = array_diff($emails, $conflictingEmails);

            Log::info('Email uniqueness check completed', [
                'valid_emails' => count($validEmails),
                'conflicting_emails' => count($conflictingEmails),
            ]);

            // Minimal transaction scope - only essential DB operations
            DB::transaction(function () use ($user_id, $validEmails) {
                // Delete existing emails (1 query)
                UsersAdditionalEmail::where('user_id', $user_id)->delete();

                if (! empty($validEmails)) {
                    // Bulk insert new emails (1 query)
                    $this->bulk_insert_additional_emails($user_id, $validEmails);

                    // Bulk insert audit records (1 query)
                    $this->bulk_insert_audit_records($user_id, $validEmails);
                }
            });

            // PHASE 2: LEGACY PROCESSING (Keep exactly as is for safety)
            foreach ($validEmails as $email) {
                Log::info('Processing legacy data for email', ['email' => $email]);
                resolve_sale_and_alert($email); // Keep individual processing - SAFE
            }

            // Resolving alert center (keep as is)
            dispatch(new GenerateAlertJob);

            // PHASE 3: FIELDROUTES PROCESSING (Keep exactly as is for safety)
            $user = User::with('additionalEmails')->find($user_id);
            if ($user) {
                Log::info('Processing FieldRoutes data for user', ['user_id' => $user_id]);
                $this->processFieldRoutesUserData($user); // Keep as is - SAFE
            }

            // Build response array
            $response_array = $this->build_response_array($emails, $validEmails, $conflictingEmails);

            Log::info('Phase 1 Email processing completed successfully', [
                'user_id' => $user_id,
                'processed_emails' => count($validEmails),
            ]);

            return response()->json([
                'ApiName' => 'add_users_additional_emails',
                'status' => true,
                'message' => 'Add Successfully.',
                'error' => $response_array,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Phase 1 Email processing failed', [
                'user_id' => $user_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ApiName' => 'add_users_additional_emails',
                'status' => false,
                'message' => $e->getMessage(),
                'error' => [],
            ], 500);
        }
    }

    public function delete_users_additional_email($id): JsonResponse
    {

        $message = 'Deleted Successfully.';
        $status_code = 200;
        $UsersAdditionalEmail = UsersAdditionalEmail::where('id', $id)->delete();

        return response()->json([
            'ApiName' => 'delete_users_additional_email',
            'status' => true,
            'message' => $message,
        ], $status_code);
    }

    /**
     * PHASE 1 OPTIMIZATION HELPER FUNCTIONS
     * These functions implement batch operations for improved performance
     */

    /**
     * Batch check email uniqueness across both tables
     *
     * @return array Array of conflicting emails
     */
    private function batch_check_email_uniqueness(array $emails, int $user_id): array
    {
        if (empty($emails)) {
            return [];
        }

        // QUERY 1: Check UsersAdditionalEmail table for conflicts
        $conflictingAdditional = UsersAdditionalEmail::whereIn('email', $emails)
            ->where('user_id', '!=', $user_id)
            ->pluck('email')
            ->toArray();

        // QUERY 2: Check User table for conflicts
        $conflictingPrimary = User::whereIn('email', $emails)
            ->pluck('email')
            ->toArray();

        // Return unique list of conflicting emails
        return array_unique(array_merge($conflictingAdditional, $conflictingPrimary));
    }

    /**
     * Bulk insert additional emails
     */
    private function bulk_insert_additional_emails(int $user_id, array $emails): void
    {
        if (empty($emails)) {
            return;
        }

        $timestamp = now();
        $emailData = array_map(function ($email) use ($user_id, $timestamp) {
            return [
                'user_id' => $user_id,
                'email' => $email,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }, $emails);

        // SINGLE BULK INSERT QUERY
        UsersAdditionalEmail::insert($emailData);

        Log::info('Bulk inserted additional emails', [
            'user_id' => $user_id,
            'count' => count($emails),
        ]);
    }

    /**
     * Bulk insert audit records
     */
    private function bulk_insert_audit_records(int $user_id, array $emails): void
    {
        if (empty($emails)) {
            return;
        }

        $timestamp = now();
        $updater_id = Auth()->user()->id;

        $auditData = array_map(function ($email) use ($user_id, $updater_id, $timestamp) {
            return [
                'user_id' => $user_id,
                'updated_by' => $updater_id,
                'field_name' => 'work_email',
                'old_value' => 'Not found.',
                'new_value' => $email,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }, $emails);

        // SINGLE BULK INSERT QUERY
        UserProfileHistory::insert($auditData);

        Log::info('Bulk inserted audit records', [
            'user_id' => $user_id,
            'count' => count($emails),
        ]);
    }

    /**
     * Build response array for API response
     *
     * @param  array  $emails  Original emails from request
     * @param  array  $validEmails  Successfully processed emails
     * @param  array  $conflictingEmails  Emails that conflict with existing records
     * @return array Response array with status for each email
     */
    private function build_response_array(array $emails, array $validEmails, array $conflictingEmails): array
    {
        $response_array = [];

        foreach ($emails as $key => $email) {
            if (in_array($email, $conflictingEmails)) {
                $response_array[$key] = [
                    'status' => false,
                    'message' => "email.$key $email exist for other user",
                ];
            } elseif (in_array($email, $validEmails)) {
                $response_array[$key] = [
                    'status' => true,
                    'message' => "email.$key $email saved",
                ];
            } else {
                // This should not happen, but add for safety
                $response_array[$key] = [
                    'status' => false,
                    'message' => "email.$key $email unknown error",
                ];
            }
        }

        return $response_array;
    }
}
