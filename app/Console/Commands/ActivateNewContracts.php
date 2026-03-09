<?php

namespace App\Console\Commands;

use App\Http\Controllers\API\V2\Hiring\OnboardingEmployeeController;
use App\Models\OnboardingEmployees;
use App\Models\User;
use App\Models\UserAgreementHistory;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActivateNewContracts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'new-contracts:activate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Activate new contracts that start today';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = date('Y-m-d');
        $this->info("🚀 Activating new contracts starting on: {$today}");

        // Instantiate the OnboardingEmployeeController with dependency injection
        $onboardingController = app(OnboardingEmployeeController::class);

        // Find ALL new contracts starting today (not just users)
        $contractsStartingToday = OnboardingEmployees::where('is_new_contract', 1)
            ->where('status_id', 1) // Hired
            ->where('period_of_agreement_start_date', $today)
            ->orderBy('user_id')
            ->orderBy('created_at', 'asc') // Process in order created
            ->get();

        if ($contractsStartingToday->isEmpty()) {
            $this->info('✅ No new contracts starting today');

            return 0;
        }

        $this->info("📋 Found {$contractsStartingToday->count()} new contracts starting today");

        $processed = [];
        $successful = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($contractsStartingToday as $contract) {
            $userId = $contract->user_id;
            $currentContractEndDate = Carbon::parse($contract->period_of_agreement_start_date)->subDay()->format('Y-m-d');
            // HANDLE MULTIPLE CONTRACTS FOR SAME USER ON SAME DATE
            if (isset($processed[$userId])) {
                $this->warn("⚠️  User {$userId} has multiple contracts starting today. Skipping duplicate (Contract ID: {$contract->id})");

                Log::warning('Multiple contracts starting same date for user', [
                    'user_id' => $userId,
                    'date' => $today,
                    'first_contract_id' => $processed[$userId],
                    'duplicate_contract_id' => $contract->id,
                    'action' => 'skipped_duplicate',
                ]);

                $skipped++;

                continue;
            }

            try {
                DB::beginTransaction();
                $currentUser = User::find($userId);
                $currentAgreement = UserAgreementHistory::where('user_id', $userId)
                    ->orderBy('period_of_agreement', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->first();

                $is_all_new_doc_sign = true;

                if ($contract->newOnboardingEmployeesDocuments != null && count($contract->newOnboardingEmployeesDocuments) > 0) {
                    $onboarding_employees_new_documents = $contract->newOnboardingEmployeesDocuments;
                    $onboarding_employees_new_document_status = OnboardingEmployees::onboarding_employees_new_document_status($onboarding_employees_new_documents);
                    $is_all_new_doc_sign = $onboarding_employees_new_document_status['is_all_new_doc_sign'];
                }

                if ($is_all_new_doc_sign == false) {
                    log::info('User '.$userId.' has not signed all new documents. Skipping contract (Contract ID: '.$contract->id.')');
                    // Close current contract immediately
                    $currentUser->update(['end_date' => $currentContractEndDate, 'status_id' => 2, 'contract_ended' => 1]);
                    // Update latest agreement history if it has no end_date
                    if ($currentAgreement && $currentAgreement->end_date === null) {
                        $currentAgreement->update(['end_date' => $currentContractEndDate]);
                    }
                } else {
                    // Apply the contract immediately starting today using controller method
                    $onboardingController->applyContractImmediately($userId, $today);
                    $this->info("✅ User {$userId}: Contract applied successfully");
                }

                $processed[$userId] = $contract->id;
                DB::commit();
                $successful++;

                $this->info("✅ User {$userId}: Contract activated (ID: {$contract->id})");

                Log::info('New contract activated successfully', [
                    'user_id' => $userId,
                    'contract_id' => $contract->id,
                    'start_date' => $today,
                    'cron_job' => 'new-contracts:activate',
                ]);

            } catch (Exception $e) {
                DB::rollBack();
                $failed++;

                $this->error("❌ User {$userId}: Failed to activate contract (ID: {$contract->id}) - {$e->getMessage()}");

                Log::error('Failed to activate new contract', [
                    'user_id' => $userId,
                    'contract_id' => $contract->id,
                    'start_date' => $today,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'cron_job' => 'new-contracts:activate',
                ]);
            }
        }

        $this->info("🎯 Summary: {$successful} successful, {$skipped} skipped, {$failed} failed");

        Log::info('New contract activation cron completed', [
            'date' => $today,
            'total_contracts' => $contractsStartingToday->count(),
            'successful' => $successful,
            'skipped' => $skipped,
            'failed' => $failed,
            'processed_users' => array_keys($processed),
        ]);

        return 0;
    }
}
