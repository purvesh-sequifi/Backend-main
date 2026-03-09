<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\OnboardingEmployees;
use App\Models\User;
use App\Models\UserTerminateHistory;
use App\Models\W2UserTransferHistory;
use App\Traits\EmailNotificationTrait;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HawxUserEmailSendAndTerminate extends Command
{
    use EmailNotificationTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hawxUserTerminate:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to update hawx w2 user status when user copleted 180 days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {

        /* Applicant expired status update */
        $usersData = User::whereNotNull('period_of_agreement_start_date')
            ->where('id', '!=', 1)
            ->where('terminate', 0)
            ->get();

        if ($usersData->isNotEmpty() && in_array(config('app.domain_name'), ['hawxw2'])) {

            $superAdmin = User::select('id', 'email')->where('id', 1)->first();
            $errors = [];
            foreach ($usersData as $user) {
                Log::info($user->id);
                $userId = $user->id;

                try {
                    DB::beginTransaction();
                    // $hireDate = $user->period_of_agreement_start_date;
                    $userName = $user->first_name.' '.$user->last_name;
                    $transferData = W2UserTransferHistory::where(['user_id' => $userId])->first();

                    if ($transferData && ! empty($transferData->contractor_transfer_date)) {

                        $dateSent = strtotime($transferData->contractor_transfer_date);
                        $currentDate = strtotime(date('Y-m-d'));
                        $diffInSeconds = $currentDate - $dateSent; // seconds
                        $daysDifference = (int) round($diffInSeconds / (60 * 60 * 24)); // days

                        if ($daysDifference == 90) {
                            Log::info($daysDifference);
                            $mailData['subject'] = 'Immediate Action Taken: Worker Terminated at 90-Day Threshold';
                            $mailData['email'] = $superAdmin['email'];
                            $mailData['name'] = $userName;
                            $mailData['employee_id'] = $user->employee_id ?? '';
                            $mailData['days'] = '90';
                            $mailData['template'] = view('mail.hawxUserMailToAdmin90Days', compact('mailData'));
                            $mailResponse = $this->sendEmailNotification($mailData);

                            $this->terminateUser($userId);
                        } elseif ($daysDifference == 75) {
                            Log::info($daysDifference);
                            $mailData['subject'] = 'Reminder: Worker Nearing 90-Day Termination Deadline';
                            $mailData['email'] = $superAdmin['email'];
                            $mailData['name'] = $userName;
                            $mailData['days'] = '75';
                            $mailData['template'] = view('mail.hawxUserMailToAdmin75Days', compact('mailData'));
                            $mailResponse = $this->sendEmailNotification($mailData);
                        } elseif ($daysDifference == 60) {
                            Log::info($daysDifference);
                            $mailData['subject'] = 'Action Required: Worker Approaching 90-Day Threshold ';
                            $mailData['email'] = $superAdmin['email'];
                            $mailData['name'] = $userName;
                            $mailData['days'] = '60';
                            $mailData['template'] = view('mail.hawxUserMailToAdmin60Days', compact('mailData'));
                            $mailResponse = $this->sendEmailNotification($mailData);
                        }
                    } else {

                        $dateSent = strtotime($user->period_of_agreement_start_date);
                        $currentDate = strtotime(date('Y-m-d'));
                        $diffInSeconds = $currentDate - $dateSent; // seconds
                        $daysDifference = (int) round($diffInSeconds / (60 * 60 * 24)); // days

                        if ($daysDifference == 180) {
                            Log::info($daysDifference);
                            $mailData['subject'] = 'Final Action: Worker Terminated at 180-Day Threshold';
                            $mailData['email'] = $superAdmin['email'];
                            $mailData['name'] = $userName;
                            $mailData['employee_id'] = $user->employee_id ?? '';
                            $mailData['days'] = '180';
                            $mailData['template'] = view('mail.hawxUserMailToAdmin180Days', compact('mailData'));
                            $mailResponse = $this->sendEmailNotification($mailData);

                            $this->terminateUser($userId);
                        } elseif ($daysDifference == 165) {
                            Log::info($daysDifference);
                            $mailData['subject'] = 'Reminder: Worker Nearing 180-Day Termination Deadline';
                            $mailData['email'] = $superAdmin['email'];
                            $mailData['name'] = $userName;
                            $mailData['days'] = '165';
                            $mailData['template'] = view('mail.hawxUserMailToAdmin165Days', compact('mailData'));
                            $mailResponse = $this->sendEmailNotification($mailData);
                        } elseif ($daysDifference == 150) {
                            Log::info($daysDifference);
                            $mailData['subject'] = ' Alert: Worker Reached 150-Day Milestone';
                            $mailData['email'] = $superAdmin['email'];
                            $mailData['name'] = $userName;
                            $mailData['days'] = '150';
                            $mailData['template'] = view('mail.hawxUserMailToAdmin150Days', compact('mailData'));
                            $mailResponse = $this->sendEmailNotification($mailData);
                        }

                    }
                    DB::commit();
                } catch (Exception $e) {
                    $errors[$userId][] = $e->getMessage().' Line No. :- '.$e->getLine();
                    DB::rollBack();
                }
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Terminate user using standardized termination flow for HAWX W2
     * HAWX-specific: Replaces both mobile number and email for compliance
     * Mobile number is handled by ApplyHistoryOnUsersV2:update
     * Email is handled separately by this method for HAWX W2 compliance requirements
     * 
     * Transaction-safe: All operations are wrapped in a single transaction to ensure
     * atomicity - either all changes succeed or all changes are rolled back
     *
     * @param int $userId
     * @return void
     */
    public function terminateUser($userId)
    {
        $user = User::find($userId);
        
        if (!$user) {
            Log::error("HawxUserEmailSendAndTerminate: User not found", ['user_id' => $userId]);
            return;
        }

        // Wrap entire termination process in a single transaction
        // This ensures data consistency - either ALL changes succeed or ALL are rolled back
        // Prevents scenario where mobile is changed but email isn't (or vice versa)
        DB::transaction(function () use ($userId, $user) {
            // Create termination history record with today's date as effective date
            // This triggers the standardized termination logic including mobile number replacement
            UserTerminateHistory::updateOrCreate(
                ['user_id' => $userId, 'terminate_effective_date' => date('Y-m-d')],
                ['is_terminate' => 1]
            );

            // Apply history updates - this will handle:
            // 1. Setting terminate flag to 1
            // 2. Setting disable_login to 1
            // 3. Replacing mobile number with unique random 10-digit number
            // 4. Updating onboarding_employees mobile number for consistency
            // 5. Deleting associated lead record
            // 6. Setting office_id to null
            $exitCode = Artisan::call('ApplyHistoryOnUsersV2:update', ['user_id' => $userId]);
            
            // Check if ApplyHistoryOnUsersV2 command executed successfully
            if ($exitCode !== 0) {
                Log::error("ApplyHistoryOnUsersV2:update failed", [
                    'user_id' => $userId,
                    'exit_code' => $exitCode
                ]);
                throw new \Exception("Failed to apply history updates for user {$userId}");
            }

            // Refresh user data after ApplyHistoryOnUsersV2 updates
            $user->refresh();

            // HAWX W2 Specific: Replace email address to free it for new users
            // This is required for HAWX W2 compliance and worker re-hiring scenarios
            if (!empty($user->email) && $user->email !== null) {
                $randomEmail = $this->generateUniqueRandomEmail($user->email);
                
                // Update email in users table
                $user->email = $randomEmail;
                $user->save();

                // Update email in onboarding_employees table for consistency
                $onboardingEmployee = OnboardingEmployees::where('user_id', $userId)->first();
                if ($onboardingEmployee) {
                    $onboardingEmployee->email = $randomEmail;
                    $onboardingEmployee->save();
                }

                Log::info("HawxUserEmailSendAndTerminate: Email replaced", [
                    'user_id' => $userId,
                    'new_email_format' => 'terminated.{uuid}@domain',
                ]);
            }

            // Update status_id for HAWX W2 domain compliance tracking
            // This is specific to HAWX W2 worker termination requirements
            User::where('id', $userId)->update(['status_id' => 2]);
        });

        // Log success after transaction commits
        Log::info("HawxUserEmailSendAndTerminate: User terminated successfully", [
            'user_id' => $userId,
            'effective_date' => date('Y-m-d'),
        ]);
    }

    /**
     * Generate a unique random email address for terminated HAWX W2 users
     * Preserves original email domain for potential recovery/audit purposes
     * Format: terminated.{uuid}@{original_domain}
     * 
     * Uses UUID (Universally Unique Identifier) to guarantee uniqueness
     * with extremely low collision probability (~0% for practical purposes)
     *
     * @param string $originalEmail
     * @return string
     */
    private function generateUniqueRandomEmail(string $originalEmail): string
    {
        // Extract domain from original email
        $emailParts = explode('@', $originalEmail);
        $domain = count($emailParts) > 1 ? $emailParts[1] : 'terminated.local';
        
        $maxAttempts = 5;
        $attempts = 0;

        do {
            // Generate unique email using UUID: terminated.{uuid}@domain
            // UUID v4 provides 122 random bits with collision probability of ~10^-37
            $uuid = (string) Str::uuid();
            $randomEmail = "terminated.{$uuid}@{$domain}";
            $attempts++;

            // Check if this email already exists in any of the tables
            // Note: Given UUID's uniqueness guarantee, collision is statistically impossible
            // but we check for data integrity and edge cases
            $existsInUsers = User::where('email', $randomEmail)->exists();
            $existsInOnboarding = OnboardingEmployees::where('email', $randomEmail)->exists();
            $existsInLeads = Lead::where('email', $randomEmail)->exists();

            if (!$existsInUsers && !$existsInOnboarding && !$existsInLeads) {
                return $randomEmail;
            }

            // If collision occurs (extremely unlikely), try again
            usleep(500); // 0.5ms delay
        } while ($attempts < $maxAttempts);

        // Fallback: use UUID + microtime suffix for absolute uniqueness
        // This scenario should never occur in practice
        $uuid = (string) Str::uuid();
        $microtime = str_replace('.', '', (string) microtime(true));
        $fallbackEmail = "terminated.{$uuid}.{$microtime}@{$domain}";
        
        return $fallbackEmail;
    }
}
