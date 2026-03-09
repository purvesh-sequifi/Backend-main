<?php

namespace App\Services\SequiDocs;

use App\Models\NewSequiDocsDocument;
use App\Models\OnboardingEmployees;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OfferLetterStatusService
{
    /**
     * Status transition mapping for offer letter email opens
     */
    const STATUS_TRANSITIONS = [
        4 => 21,   // "Offer Sent, Unread" → "Offer Sent, Read"
        12 => 24,  // "Offer Resent, Unread" → "Offer Resent, Read"
    ];

    /**
     * Handle status transition when offer letter email is opened
     */
    public static function handleEmailOpenStatusTransition(int $documentId): bool
    {
        try {
            // Get the document
            $document = NewSequiDocsDocument::find($documentId);

            if (! $document) {
                Log::warning('OfferLetterStatusService: Document not found', ['document_id' => $documentId]);

                return false;
            }

            // Only process offer letter documents (category_id = 1)
            if ($document->category_id != 1) {
                Log::info('OfferLetterStatusService: Not an offer letter document', [
                    'document_id' => $documentId,
                    'category_id' => $document->category_id,
                ]);

                return false;
            }

            // Only process onboarding employees
            if ($document->user_id_from !== 'onboarding_employees') {
                Log::info('OfferLetterStatusService: Not an onboarding employee document', [
                    'document_id' => $documentId,
                    'user_id_from' => $document->user_id_from,
                ]);

                return false;
            }

            // Get the onboarding employee
            $employee = OnboardingEmployees::find($document->user_id);

            if (! $employee) {
                Log::warning('OfferLetterStatusService: Onboarding employee not found', [
                    'document_id' => $documentId,
                    'user_id' => $document->user_id,
                ]);

                return false;
            }

            // Check if current status requires transition
            $currentStatusId = $employee->status_id;

            if (! array_key_exists($currentStatusId, self::STATUS_TRANSITIONS)) {
                Log::info('OfferLetterStatusService: Current status does not require transition', [
                    'document_id' => $documentId,
                    'employee_id' => $employee->id,
                    'current_status_id' => $currentStatusId,
                ]);

                return false;
            }

            // Get the new status ID
            $newStatusId = self::STATUS_TRANSITIONS[$currentStatusId];

            // Validate that the new status exists
            if (! self::validateStatusId($newStatusId)) {
                Log::error('OfferLetterStatusService: New status ID does not exist', [
                    'document_id' => $documentId,
                    'employee_id' => $employee->id,
                    'new_status_id' => $newStatusId,
                ]);

                return false;
            }

            // Update the employee status
            $oldStatusId = $employee->status_id;
            $employee->old_status_id = $oldStatusId;
            $employee->status_id = $newStatusId;
            $employee->save();

            Log::info('OfferLetterStatusService: Status transition completed', [
                'document_id' => $documentId,
                'employee_id' => $employee->id,
                'employee_email' => $employee->email,
                'old_status_id' => $oldStatusId,
                'new_status_id' => $newStatusId,
                'old_status_name' => self::getStatusName($oldStatusId),
                'new_status_name' => self::getStatusName($newStatusId),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('OfferLetterStatusService: Error handling email open status transition', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return false;
        }
    }

    /**
     * Validate that a status ID exists in the hiring_status table
     */
    private static function validateStatusId(int $statusId): bool
    {
        return DB::table('hiring_status')->where('id', $statusId)->exists();
    }

    /**
     * Get status name by ID for logging
     */
    private static function getStatusName(int $statusId): string
    {
        $status = DB::table('hiring_status')->where('id', $statusId)->first();

        return $status ? $status->status : 'Unknown Status';
    }

    /**
     * Check if an employee's current status is eligible for email open transition
     */
    public static function getEmployeeStatusTransitionInfo(int $employeeId): array
    {
        try {
            $employee = OnboardingEmployees::find($employeeId);

            if (! $employee) {
                return [
                    'eligible' => false,
                    'reason' => 'Employee not found',
                ];
            }

            $currentStatusId = $employee->status_id;

            if (! array_key_exists($currentStatusId, self::STATUS_TRANSITIONS)) {
                return [
                    'eligible' => false,
                    'current_status_id' => $currentStatusId,
                    'current_status_name' => self::getStatusName($currentStatusId),
                    'reason' => 'Current status does not require transition on email open',
                ];
            }

            $newStatusId = self::STATUS_TRANSITIONS[$currentStatusId];

            return [
                'eligible' => true,
                'current_status_id' => $currentStatusId,
                'current_status_name' => self::getStatusName($currentStatusId),
                'new_status_id' => $newStatusId,
                'new_status_name' => self::getStatusName($newStatusId),
                'transition_rule' => "Email open will change status from '{$currentStatusId}' to '{$newStatusId}'",
            ];

        } catch (\Exception $e) {
            Log::error('OfferLetterStatusService: Error getting transition info', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
            ]);

            return [
                'eligible' => false,
                'reason' => 'Error checking transition eligibility',
            ];
        }
    }

    /**
     * Get all possible status transitions for offer letter email opens
     */
    public static function getAvailableTransitions(): array
    {
        $transitions = [];

        foreach (self::STATUS_TRANSITIONS as $fromStatusId => $toStatusId) {
            $transitions[] = [
                'from_status_id' => $fromStatusId,
                'from_status_name' => self::getStatusName($fromStatusId),
                'to_status_id' => $toStatusId,
                'to_status_name' => self::getStatusName($toStatusId),
                'trigger' => 'Offer letter email opened',
            ];
        }

        return $transitions;
    }

    /**
     * Check if a document is eligible for status transition tracking
     */
    public static function isDocumentEligibleForStatusTransition(int $documentId): bool
    {
        $document = NewSequiDocsDocument::find($documentId);

        if (! $document) {
            return false;
        }

        // Must be offer letter (category_id = 1)
        if ($document->category_id != 1) {
            return false;
        }

        // Must be for onboarding employees
        if ($document->user_id_from !== 'onboarding_employees') {
            return false;
        }

        // Check if employee exists and has eligible status
        $employee = OnboardingEmployees::find($document->user_id);

        if (! $employee) {
            return false;
        }

        // Check if current status is eligible for transition
        return array_key_exists($employee->status_id, self::STATUS_TRANSITIONS);
    }
}
