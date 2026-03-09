<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OnboardingEmployeeStatusMigrationSeeder extends Seeder
{
    /**
     * Update status for onboarding employees with completed mandatory documents.
     * Data from migration 2025_09_29_023151_update_status_for_completed_mandatory_documents.php
     * 
     * This seeder is idempotent - checks if migration already done.
     */
    public function run(): void
    {
        // Check if migration already completed
        if ($this->isMigrationCompleted()) {
            Log::info('Onboarding employee status migration already completed, skipping');
            return;
        }

        DB::beginTransaction();

        try {
            Log::info('Starting onboarding employee status migration');

            // Get all onboarding employees with "Offer Accepted, Docs Pending" status (ID 22)
            $employeesWithDocsPending = DB::table('onboarding_employees')
                ->where('status_id', 22)
                ->get(['id', 'first_name', 'last_name', 'email', 'status_id']);

            $totalEmployees = $employeesWithDocsPending->count();
            $updatedCount = 0;
            $skippedCount = 0;

            Log::info("Found {$totalEmployees} employees with 'Offer Accepted, Docs Pending' status");

            foreach ($employeesWithDocsPending as $employee) {
                $shouldUpdate = $this->checkMandatoryDocumentsCompleted($employee->id);

                if ($shouldUpdate) {
                    // Update status to Accepted (ID 1) - which represents "Review & Hire"
                    DB::table('onboarding_employees')
                        ->where('id', $employee->id)
                        ->update([
                            'old_status_id' => 22,
                            'status_id' => 1,
                            'updated_at' => now(),
                        ]);

                    $updatedCount++;

                    Log::info('Updated employee status', [
                        'employee_id' => $employee->id,
                        'name' => $employee->first_name.' '.$employee->last_name,
                        'email' => $employee->email,
                        'from_status' => 22,
                        'to_status' => 1,
                    ]);
                } else {
                    $skippedCount++;
                }
            }

            // Mark migration as completed
            $this->markMigrationCompleted();

            DB::commit();

            Log::info('Onboarding employee status migration completed successfully', [
                'total_employees_checked' => $totalEmployees,
                'employees_updated' => $updatedCount,
                'employees_skipped' => $skippedCount,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Onboarding employee status migration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function isMigrationCompleted(): bool
    {
        return DB::table('system_settings')
            ->where('key', 'onboarding_status_migrated')
            ->where('value', '1')
            ->exists();
    }

    private function markMigrationCompleted(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'onboarding_status_migrated'],
            [
                'value' => '1',
                'description' => 'Onboarding employee status migration completed',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /**
     * Check if all mandatory documents and manual documents are completed for an employee
     */
    private function checkMandatoryDocumentsCompleted(int $employeeId): bool
    {
        // Get all mandatory documents for this employee
        // Mandatory documents are those with is_sign_required_for_hire = 1
        $mandatoryDocuments = DB::table('new_sequi_docs_documents')
            ->where('user_id', $employeeId)
            ->where('user_id_from', 'onboarding_employees')
            ->where('is_active', 1)
            ->where('is_post_hiring_document', 0)
            ->where('is_sign_required_for_hire', 1)
            ->get(['id', 'description', 'document_response_status', 'signed_status', 'document_uploaded_type', 'category_id']);

        if ($mandatoryDocuments->isEmpty()) {
            return false;
        }

        $totalMandatory = $mandatoryDocuments->count();
        $completedCount = 0;

        foreach ($mandatoryDocuments as $document) {
            // A document is considered completed if document_response_status = 1
            // This works for both signed documents and manual upload documents
            if ($document->document_response_status == 1) {
                $completedCount++;
            }
        }

        return $completedCount === $totalMandatory;
    }
}

