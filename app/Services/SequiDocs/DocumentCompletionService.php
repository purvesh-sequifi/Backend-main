<?php

namespace App\Services\SequiDocs;

use App\Models\NewSequiDocsDocument;
use App\Models\OnboardingEmployees;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentCompletionService
{
    /**
     * Complete the document signing process
     * Handle all remaining business logic and employee workflow
     */
    public function complete(array $documentData): array
    {
        try {
            DB::beginTransaction();

            // Validate required data
            $this->validateDocumentData($documentData);

            // Update NewSequiDocsDocument with final status and S3 path FIRST
            $document = $this->updateDocument($documentData);

            // Handle guest users (null user_id) - Update OnboardingEmployees status for guest signers
            // This must be called AFTER document status is updated so the status determination logic
            // can see the correct signed_status = 1
            $this->updateEmployeeStatus(data_get($documentData, 'user_id'));

            // Trigger employee hiring workflow ONLY for onboarding employees, not existing users
            if ($document->user_id_from === 'onboarding_employees') {
                $hiringResult = $this->hireEmployeeUponSigningMandatoryDocuments($document->user_id);
            } else {
                // For existing users (user_id_from = 'users'), skip hiring workflow
                Log::info('Skipping hiring workflow - document belongs to existing user', [
                    'document_id' => $document->id,
                    'user_id' => $document->user_id,
                    'user_id_from' => $document->user_id_from,
                ]);
                $hiringResult = ['status' => true, 'message' => 'Existing user - hiring workflow skipped'];
            }

            if (! data_get($hiringResult, 'status')) {
                DB::rollBack();
                Log::error('Document completion failed - hiring process error', [
                    'document_id' => data_get($documentData, 'document_id'),
                    'user_id' => $document->user_id,
                    'error' => data_get($hiringResult, 'message'),
                ]);

                return [
                    'status' => false,
                    'message' => data_get($hiringResult, 'message'),
                    'error_type' => 'hiring_process_failed',
                ];
            }

            DB::commit();

            Log::info('Document completion process finished successfully', [
                'document_id' => data_get($documentData, 'document_id'),
                'user_id' => $document->user_id,
                's3_path' => data_get($documentData, 's3_path'),
            ]);

            return [
                'status' => true,
                'message' => 'Digital Signature Process has been completed!!',
                'data' => [
                    'document_id' => $document->id,
                    'user_id' => $document->user_id,
                    'signed_status' => $document->signed_status,
                    'document_response_status' => $document->document_response_status,
                    'signed_date' => $document->signed_date,
                    'signed_document' => $document->signed_document,
                ],
            ];

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Document completion process failed with exception', [
                'document_id' => $documentData['document_id'] ?? null,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => false,
                'message' => 'Document completion process failed: '.$e->getMessage(),
                'error_type' => 'service_exception',
                'error_details' => [
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ],
            ];
        }
    }

    /**
     * Validate required document data
     * Note: user_id can be null for guest users (not part of organization)
     */
    private function validateDocumentData(array $documentData): void
    {
        $requiredFields = ['document_id', 'envelope_document_id', 's3_path'];

        foreach ($requiredFields as $field) {
            if (! data_get($documentData, $field)) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        // user_id is optional - can be null for guest users
        if (! array_key_exists('user_id', $documentData)) {
            throw new \InvalidArgumentException('Missing required field: user_id');
        }
    }

    /**
     * Update OnboardingEmployees status for guest users (null user_id)
     * Sets status to active (status_id = 1) for guest employees in specific statuses
     * This matches the original API logic from lines 404-406
     */
    private function updateEmployeeStatus(?int $userId): void
    {
        if ($userId === null) {
            Log::info('Skipping employee status update - no user_id provided (guest user scenario)');

            return;
        }

        try {
            // Check if all mandatory documents are signed before updating status
            $employee = OnboardingEmployees::find($userId);
            if (! $employee) {
                Log::warning('Employee not found for status update', ['onboarding_user_id' => $userId]);

                return;
            }

            // Get document signing status and determine new status in one call
            $newStatusId = $this->getDocumentStatusAndDetermineNewStatus($userId, $employee->status_id);

            if ($newStatusId === null) {
                Log::info('No status update needed - documents do not meet transition criteria', [
                    'onboarding_user_id' => $userId,
                    'current_status_id' => $employee->status_id,
                ]);

                return;
            }

            // Check if employee meets the criteria for status update
            $shouldUpdate = false;

            // Check new contract condition: If its a new contract then it has user_id else we check user_id NULL
            if (($employee->is_new_contract == 0 && is_null($employee->user_id)) || $employee->is_new_contract == 1) {
                // Check if current status is in the allowed statuses for transition
                $allowedStatuses = [13, 4, 12, 21, 23, 22, 24];
                if (in_array($employee->status_id, $allowedStatuses)) {
                    $shouldUpdate = true;
                }
            }

            if ($shouldUpdate) {
                // Use Eloquent model update to trigger observer and automation
                $oldStatusId = $employee->status_id;
                $employee->old_status_id = $oldStatusId; // Set old status before change
                $employee->status_id = $newStatusId;
                $employee->save(); // This triggers OnboardingEmployeesObserver and automation

                Log::info('Updated OnboardingEmployees status via Eloquent model (triggers automation)', [
                    'onboarding_user_id' => $userId,
                    'old_status_id' => $oldStatusId,
                    'new_status_id' => $newStatusId,
                    'update_method' => 'Eloquent model (triggers observer)',
                ]);
            } else {
                Log::info('Employee does not meet criteria for status update', [
                    'onboarding_user_id' => $userId,
                    'current_status_id' => $employee->status_id,
                    'is_new_contract' => $employee->is_new_contract,
                    'user_id' => $employee->user_id,
                    'target_status_id' => $newStatusId,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update guest employee status', [
                'onboarding_user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Validate that a status ID exists in the hiring_status table
     */
    private function validateStatusId(int $statusId): bool
    {
        return DB::table('hiring_status')->where('id', $statusId)->exists();
    }

    /**
     * Get document signing status and determine new status in one optimized function
     * Handles all document signing scenarios carefully
     */
    private function getDocumentStatusAndDetermineNewStatus(int $userId, int $currentStatusId): ?int
    {
        // Only process specific statuses (including new statuses 21, 22, 23, 24)
        if (! in_array($currentStatusId, [13, 4, 12, 21, 22, 23, 24])) {
            return null;
        }

        // Get all mandatory documents for this employee
        $mandatoryDocuments = NewSequiDocsDocument::where([
            'user_id' => $userId,
            'user_id_from' => 'onboarding_employees',
            'is_active' => 1,
            'is_post_hiring_document' => 0,
            'is_sign_required_for_hire' => 1,
        ])->get();

        $mandatoryDocumentsCount = $mandatoryDocuments->where('category_id', '!=', 1)->count();

        // Count completed documents using document_response_status for ALL document types
        // This matches the existing system logic that treats document_response_status as the primary completion indicator
        $completedCount = $mandatoryDocuments->where('category_id', '!=', 1)->where('document_response_status', 1)->count();

        // For offer letter (category_id = 1), also use document_response_status for consistency
        $offerLetterSigned = $mandatoryDocuments->where('category_id', 1)->where('document_response_status', 1)->count();

        Log::info('Document status analysis', [
            'user_id' => $userId,
            'current_status_id' => $currentStatusId,
            'total_mandatory_documents' => $mandatoryDocuments->count(),
            'other_documents_total' => $mandatoryDocumentsCount,
            'other_documents_completed' => $completedCount,
            'offer_letter_completed' => $offerLetterSigned,
            'has_offer_letter' => $mandatoryDocuments->where('category_id', 1)->count() > 0,
        ]);

        // Case 1: No documents completed -> no status change
        $totalCompleted = $completedCount + $offerLetterSigned;
        if ($totalCompleted === 0) {
            Log::info('Case 1: No documents completed - no status change');

            return null;
        }

        // Case 2: Only offer letter completed, other documents not completed -> Offer Letter Accepted, Rest Pending
        if ($offerLetterSigned && $completedCount < $mandatoryDocumentsCount) {
            Log::info('Case 2: Offer letter completed, other documents pending - status 22');
            if ($this->validateStatusId(22)) {
                return 22; // "Offer Letter Accepted, Rest Pending"
            } else {
                Log::error('Status ID 22 does not exist in hiring_status table');

                return null;
            }
        }

        // Case 3: All other documents completed, offer letter not completed -> Offer Letter Pending, Rest Completed
        if (! $offerLetterSigned && $completedCount === $mandatoryDocumentsCount && $mandatoryDocumentsCount > 0) {
            Log::info('Case 3: All other documents completed, offer letter pending - status 23');
            if ($this->validateStatusId(23)) {
                return 23; // "Offer Letter Pending, Rest Completed"
            } else {
                Log::error('Status ID 23 does not exist in hiring_status table');

                return null;
            }
        }

        // Case 4: All required documents completed AND offer letter completed -> Active (status 1)
        if ($offerLetterSigned && $completedCount === $mandatoryDocumentsCount) {
            Log::info('Case 4: All documents completed - status 1 (Active)');
            if ($this->validateStatusId(1)) {
                return 1; // Active status
            } else {
                Log::error('Status ID 1 does not exist in hiring_status table');

                return null;
            }
        }

        // Case 5: Some documents completed but not all required documents -> no status change
        if ($completedCount < $mandatoryDocumentsCount && $mandatoryDocumentsCount > 0) {
            Log::info('Case 5: Some documents completed but not all - no status change');

            return null;
        }

        // Default: No status change for other scenarios
        Log::info('Default case: No status change');

        return null;
    }

    /**
     * Update NewSequiDocsDocument with final signing status
     */
    private function updateDocument(array $documentData): NewSequiDocsDocument
    {
        $document = NewSequiDocsDocument::findOrFail(data_get($documentData, 'document_id'));

        // Ensure both signed_status and document_response_status are set to 1 for consistency
        // This prevents the hire button issue where manual documents had mismatched statuses
        $updateData = [
            'signed_status' => 1,
            'document_response_status' => 1,
            'document_response_date' => now(),
            'signed_date' => now(),
        ];

        // Only set signed_document if s3_path is provided (for signature-based documents)
        if (data_get($documentData, 's3_path')) {
            $updateData['signed_document'] = data_get($documentData, 's3_path');
        }

        $document->update($updateData);

        Log::info('Updated NewSequiDocsDocument with final status', [
            'document_id' => $document->id,
            'document_type' => $document->document_uploaded_type,
            'signed_status' => $document->signed_status,
            'document_response_status' => $document->document_response_status,
            'signed_document' => $document->signed_document,
        ]);

        return $document;
    }

    /**
     * Trigger employee hiring workflow upon signing mandatory documents
     * Exact implementation from original hireEmployeeUponSigningMandatoryDocuments

     *
     * @param  int|null  $userId  - Can be null for guest users
     */
    private function hireEmployeeUponSigningMandatoryDocuments(?int $userId): array
    {
        try {
            // Skip hiring workflow for guest users (null user_id)
            if ($userId === null) {
                Log::info('Skipping employee hiring workflow - guest user scenario (null user_id)');

                return [
                    'status' => true,
                    'message' => 'Guest user - hiring workflow skipped',
                ];
            }

            $employeeIdSetting = \App\Models\EmployeeIdSetting::first();
            if ($employeeIdSetting && $employeeIdSetting->automatic_hiring_status) {
                $nonSignedDocument = NewSequiDocsDocument::where([
                    'user_id' => $userId,
                    'user_id_from' => 'onboarding_employees',
                    'is_active' => 1,
                    'is_post_hiring_document' => 0,
                    'is_sign_required_for_hire' => 1,
                    'document_response_status' => 0,
                ])->first();

                if (! $nonSignedDocument) {
                    $authUserId = 1;
                    if (auth()->user()) {
                        $authUserId = auth()->user()->id;
                    }
                    $namespace = app()->getNamespace();
                    $onboardingEmployeeController = app()->make($namespace.'Http\Controllers\API\V2\Hiring\OnboardingEmployeeController');
                    $onboardingEmployeeController->hiredEmployee(new \Illuminate\Http\Request(['employee_id' => $userId]), $authUserId);
                }
            }

            Log::info('Employee hiring workflow completed successfully', ['user_id' => $userId]);

            return ['status' => true, 'message' => 'Employee hired successfully!!'];

        } catch (\Throwable $e) {
            Log::error('Employee hiring workflow failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return ['status' => false, 'message' => $e->getMessage().' '.$e->getLine()];
        }
    }

    /**
     * Get completion service status for monitoring
     */
    public function getStatus(): array
    {
        return [
            'service' => 'DocumentCompletionService',
            'status' => 'active',
            'timestamp' => now(),
            'database_connection' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        ];
    }

    /**
     * Process completion asynchronously (for queue implementation)
     */
    public function processAsync(array $documentData): void
    {
        // This method can be used for queue-based processing
        // Dispatch to a queue job for better performance
        dispatch(function () use ($documentData) {
            $this->complete($documentData);
        });
    }
}
