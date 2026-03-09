<?php

namespace App\Services;

use App\Jobs\AutomationMail;
use App\Models\AutomationActionLog;
use App\Models\AutomationRule;
use App\Models\HiringStatus;
use App\Models\OnboardingEmployees;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutomationOnboarding
{
    public $automationRules;

    public $onboarding_id;

    public $automation_rule_id;

    public $mailMessage;

    public $data;

    public function __construct(array $data)
    {

        // $automationRules = AutomationRule::where('status',1)->get();
        // $this->automationRules = $automationRules;
        $this->data = $data;
        $this->onboarding_id = $data['onboarding_id'];
        $this->automation_rule_id = $data['automation_rule_id'];
        $this->mailMessage = '';
    }

    /**
     * Get candidate name for use in automation messages
     */
    private function getCandidateName()
    {
        $onboarding = OnboardingEmployees::find($this->onboarding_id);

        return $onboarding ? trim($onboarding->first_name.' '.$onboarding->last_name) : 'the candidate';
    }

    /**
     * Check if this is a new contract (re-signing) scenario
     */
    private function isNewContract(): bool
    {
        $onboarding = OnboardingEmployees::find($this->onboarding_id);

        return $onboarding ? (($onboarding->is_new_contract ?? 0) == 1) : false;
    }

    public function trigger()
    {

        Log::info('AutomationOnboarding: Starting automation trigger', [
            'onboarding_id' => $this->onboarding_id,
            'automation_rule_id' => $this->automation_rule_id,
            'data' => $this->data,
        ]);

        $automationRule = AutomationRule::find($this->data['automation_rule_id']);

        Log::info('message', [
            'automationRules' => $automationRule,
        ]);

        $finalTestResult = null;
        if (isset($automationRule->rule[0]['when'])) {

            foreach ($automationRule->rule[0]['when'] as $key => $val) {

                $testResult = $this->testRule($val); // testResult can be true,false

                if (isset($val['operator']) && $val['operator'] == 'OR') {
                    // If operator is OR, any true result makes $finalTestResult true
                    $finalTestResult = $finalTestResult || $testResult;
                } elseif (isset($val['operator']) && $val['operator'] == 'AND') {
                    // If operator is AND, any false result makes $finalTestResult false
                    $finalTestResult = ($finalTestResult === null) ? $testResult : ($finalTestResult && $testResult);
                }
            }

        }

        if ($finalTestResult != null && $finalTestResult == true) {

            // Get status context for intelligent duplicate prevention
            $statusContext = $this->getStatusContext();

            // Create automation log with full context for intelligent duplicate prevention
            $isNewContract = $this->isNewContract();
            $contextType = $isNewContract ? 'contract_renewal' : 'initial_onboarding';

            $log = AutomationActionLog::createSafely([
                'onboarding_id' => $this->onboarding_id,
                'automation_rule_id' => $automationRule->id,
                'from_status_id' => $statusContext['from_status_id'],
                'to_status_id' => $statusContext['to_status_id'],
                'status' => 0, // Set to 0 initially, will be updated when email is sent
                'email_sent' => 0, // Will be updated by AutomationMail job
                'category' => 'EMAIL',
                'event' => 'Onboarding Status Change',
                // Dedicated columns for fast querying
                'is_new_contract' => $isNewContract ? 1 : 0,
                'context_type' => $contextType,
                // JSON context for detailed information (backwards compatibility)
                'trigger_context' => [
                    'from_status_name' => $statusContext['from_status_name'],
                    'to_status_name' => $statusContext['to_status_name'],
                    'candidate_name' => $this->getCandidateName(),
                    'timestamp' => now()->toIso8601String(),
                    'automation_type' => 'status_change',
                ],
            ]);

            // Only process if this is a new context (not a true duplicate)
            if ($log->wasRecentlyCreated || $log->email_sent == 0) {
                // Pass log ID to applyThenRules for email tracking
                $emailsSent = $this->applyThenRules($automationRule, $log->id);

                // Only update email field if we actually sent emails
                if (! empty($emailsSent)) {
                    $log->email = is_array($emailsSent) ? implode(',', $emailsSent) : $emailsSent;
                    $log->status = 1; // Mark as active since we're sending emails
                    $log->save();

                    Log::info('AutomationOnboarding: Automation processed successfully', [
                        'log_id' => $log->id,
                        'onboarding_id' => $this->onboarding_id,
                        'emails_sent' => $emailsSent,
                        'is_new_contract' => $isNewContract,
                        'context_type' => $isNewContract ? 'contract_renewal' : 'initial_onboarding',
                    ]);
                } else {
                    Log::warning('AutomationOnboarding: No emails sent', [
                        'log_id' => $log->id,
                        'onboarding_id' => $this->onboarding_id,
                        'is_new_contract' => $isNewContract,
                        'context_type' => $isNewContract ? 'contract_renewal' : 'initial_onboarding',
                    ]);
                }
            } else {
                Log::info('AutomationOnboarding: Duplicate prevented', [
                    'log_id' => $log->id,
                    'is_new_contract' => $isNewContract,
                    'context_type' => $isNewContract ? 'contract_renewal' : 'initial_onboarding',
                ]);
            }

        }

        return ''; // stop here

        // below code not in use
        $whenRuleString = $this->whenRuleString($automationRule);

        Log::info('whenRuleString');
        Log::info($whenRuleString);

        $whenRules = explode('#or#', $whenRuleString);

        Log::debug('$whenRules');
        Log::debug($whenRules);

        $ruleConditionsAreApplicable = true;
        foreach ($whenRules as $whenRule) {
            $whenRule = $this->formatString($whenRule);
            $ruleSets = explode(' , ', $whenRule);
            foreach ($ruleSets as $ruleSet) {
                $ruleConditionsAreApplicable = $this->checkWhenRuleConditionsAreApplicable($ruleSet);
                if (! $ruleConditionsAreApplicable) {
                    $ruleConditionsAreApplicable = false;
                    break;
                }

                if ($ruleConditionsAreApplicable) {
                    $this->applyThenRules($automationRule);
                }

                $log = new AutomationActionLog;
                $log->onboarding_id = $this->onboarding_id;
                $log->automation_rule_id = $automationRule->id;
                $log->status = 1;
                $log->save();
            }

            Log::info('$ruleConditionsAreApplicable', [
                '$ruleConditionsAreApplicable' => $ruleConditionsAreApplicable,
            ]);
            // dd($whenRule);
            // if($ruleConditionsAreApplicable){
            //     $this->applyThenRules($automationRule);
            // }

        }

    }

    public function whenRuleString($automationRule)
    {

        $when = '';
        if (isset($automationRule->rule[0]['when'])) {
            foreach ($automationRule->rule[0]['when'] as $key => $value) {
                $when .= json_encode($value).' #'.strtolower($value['operator']).'# ';
            }
        }

        return $when;

    }

    public function formatString(string $str)
    {
        $str = str_replace('#and#', ',', $str);
        $str = str_replace('##', '', $str);
        $str = rtrim($str, ' ,');
        $str = rtrim($str, ',');

        return rtrim($str);
    }

    public function checkWhenRuleConditionsAreApplicable(string $ruleSet): bool
    {
        $ruleSetStr = (string) $ruleSet;

        $decodedData = json_decode($ruleSet, true);

        if (! isset($decodedData['event_type'])) {
            return false;
        }

        $history = OnboardingEmployees::where('id', $this->onboarding_id)->orderBy('id', 'desc')->first();
        $statusData = false;
        if (isset($decodedData['event_type']) && $decodedData['event_type'] == 'Candidate Moves') {
            // $history = OnboardingEmployees::where('id',$this->onboarding_id)->orderBy('id','desc')->first();
            // dd($history);
            if ($history && isset($decodedData['from_buckets']) && isset($decodedData['to_buckets'])) {

                $oldStatusId = $history->old_status_id ?? 0;
                $statusId = $history->status_id ?? 0;

                Log::debug($decodedData['from_buckets']);
                Log::debug($decodedData['to_buckets']);

                if ((in_array($oldStatusId, $decodedData['from_buckets']) || in_array(0, $decodedData['from_buckets'])) && (in_array($statusId, $decodedData['to_buckets']) || in_array(0, $decodedData['to_buckets']))) {
                    // dd($decodedData['to_buckets']);
                    // $pipeline1 = HiringStatus::whereIn('id', $decodedData['from_buckets'])->first();
                    // $pipeline2 = HiringStatus::whereIn('id', $decodedData['to_buckets'])->first();
                    $pipeline1 = HiringStatus::where('id', $oldStatusId)->first();
                    $pipeline2 = HiringStatus::where('id', $statusId)->first();

                    $candidateName = $this->getCandidateName();
                    $this->mailMessage .= "{$candidateName}'s status has transitioned from {$pipeline1->status} to {$pipeline2->status}.";
                    $statusData = true;

                }

            }

        } elseif (isset($decodedData['event_type']) && $decodedData['event_type'] == 'Candidate Stays') {

            if (isset($decodedData['for_days']) && isset($decodedData['in_buckets'])) {
                $statusId = $history->status_id ?? 0;
                // Fixed: Use updated_at instead of period_of_agreement_start_date for more accurate status duration
                $days_in_status = DB::table('onboarding_employees')
                    ->selectRaw('DATEDIFF(NOW(), updated_at) AS days_in_status')
                    ->where('id', $this->onboarding_id)
                    ->value('days_in_status');

                if ($decodedData['for_days'] <= $days_in_status && in_array($statusId, $decodedData['in_buckets'])) {

                    $pipeline = HiringStatus::where('id', $statusId)->first();

                    // $days_in_status = DB::table('leads')
                    // ->selectRaw('DATEDIFF(NOW(), pipeline_status_date) AS days_in_status')
                    // ->where('id', $this->onboarding_id)
                    // ->value('days_in_status');

                    $candidateName = $this->getCandidateName();
                    $this->mailMessage .= "{$candidateName}'s status has remained in {$pipeline->status} for over {$days_in_status} days.";
                    $statusData = true;
                }

            }

        }

        return $statusData;
    }

    public function applyThenRules($automationRule, $logId = null)
    {
        $emailsSent = [];

        if (isset($automationRule->rule[0]['then'])) {
            Log::debug('$automationRule->rule[0][then]');
            Log::debug($automationRule->rule[0]['then']);
            foreach ($automationRule->rule[0]['then'] as $key => $value) {

                $delay_by = 0;
                $delay = 'days';
                if (isset($value['delay_by'])) {
                    $delay_by = $value['delay_by'];
                }

                $onboarding = OnboardingEmployees::find($this->onboarding_id);

                if ($value['action'] == 'Email Candidate') {

                    $automationMailData = [
                        'automation_log_id' => $logId, // Include log ID for status tracking
                        'onboarding_id' => $this->onboarding_id, // Include for fallback tracking
                        'onboarding_name' => $onboarding->first_name.' '.$onboarding->last_name,
                        'recipient_name' => $onboarding->first_name.' '.$onboarding->last_name,
                        'send_to' => $onboarding->email,
                        // 'send_to' => 'ashutosh.y@sequifi.com',
                        'mailMessage' => $this->mailMessage,
                    ];

                    // Add flag only for new contracts
                    if ($this->isNewContract()) {
                        $automationMailData['is_new_contract'] = 1;
                    }

                    AutomationMail::dispatch($automationMailData)->delay(now()->addDays($delay_by));
                    $emailsSent[] = $onboarding->email;

                } elseif ($value['action'] == 'Email Recruiter') {

                    if ($onboarding->recruiter_id) {
                        $user = User::find($onboarding->recruiter_id);
                        if ($user && $user->email) {
                            // send mail to lead with delay or immidiate
                            $automationMailData = [
                                'automation_log_id' => $logId, // Include log ID for status tracking
                                'onboarding_id' => $this->onboarding_id, // Include for fallback tracking
                                'onboarding_name' => $onboarding->first_name.' '.$onboarding->last_name,
                                'recipient_name' => $user->first_name.' '.$user->last_name,
                                'send_to' => $user->email,
                                'mailMessage' => $this->mailMessage,
                            ];

                            // Add flag only for new contracts
                            if ($this->isNewContract()) {
                                $automationMailData['is_new_contract'] = 1;
                            }

                            AutomationMail::dispatch($automationMailData)->delay(now()->addDays($delay_by));
                            $emailsSent[] = $user->email;
                        }
                    }

                } elseif ($value['action'] == 'Email Reporting Manager') {

                    if ($onboarding->manager_id) {
                        $user = User::find($onboarding->manager_id);
                        if ($user && $user->email) {
                            // send mail to lead with delay or immidiate
                            $automationMailData = [
                                'automation_log_id' => $logId, // Include log ID for status tracking
                                'onboarding_id' => $this->onboarding_id, // Include for fallback tracking
                                'onboarding_name' => $onboarding->first_name.' '.$onboarding->last_name,
                                'recipient_name' => $user->first_name.' '.$user->last_name,
                                'send_to' => $user->email,
                                'mailMessage' => $this->mailMessage,
                            ];

                            // Add flag only for new contracts
                            if ($this->isNewContract()) {
                                $automationMailData['is_new_contract'] = 1;
                            }

                            AutomationMail::dispatch($automationMailData)->delay(now()->addDays($delay_by));
                            $emailsSent[] = $user->email;
                        }
                    }

                } elseif (isset($value['custom_email']) && $value['action'] == 'Email[custom email]') {
                    // Fixed: Properly trim emails and validate them
                    $emails = array_map('trim', explode(',', $value['custom_email']));
                    $emails = array_filter($emails, function ($email) {
                        return ! empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
                    });

                    if (! empty($emails) && count($emails) > 0) {
                        $firstEmail = array_shift($emails); // Get the first email and remove it from array
                        $ccEmails = $emails; // Remaining emails become CC

                        // Prepare data for the mail
                        $automationMailData = [
                            'automation_log_id' => $logId, // Include log ID for status tracking
                            'onboarding_id' => $this->onboarding_id, // Include for fallback tracking
                            'onboarding_name' => $onboarding->first_name.' '.$onboarding->last_name,
                            'recipient_name' => '',
                            'send_to' => $firstEmail,
                            'cc' => $ccEmails, // Pass the CC emails as an array
                            'mailMessage' => $this->mailMessage,
                        ];

                        // Add flag only for new contracts
                        if ($this->isNewContract()) {
                            $automationMailData['is_new_contract'] = 1;
                        }

                        AutomationMail::dispatch($automationMailData)->delay(now()->addDays($delay_by));
                        $emailsSent[] = $firstEmail;
                        $emailsSent = array_merge($emailsSent, $ccEmails);
                    } else {
                        Log::warning('AutomationOnboarding: No valid emails found for custom email action', [
                            'onboarding_id' => $this->onboarding_id,
                            'raw_emails' => $value['custom_email'],
                        ]);
                    }
                }
            }
        }

        return implode(',', $emailsSent);
    }

    public function testRule($decodedData)
    {

        if (! isset($decodedData['event_type'])) {
            return false;
        }

        $history = OnboardingEmployees::where('id', $this->onboarding_id)->orderBy('id', 'desc')->first();
        $statusData = false;
        if (isset($decodedData['event_type']) && $decodedData['event_type'] == 'Candidate Moves') {
            // $history = OnboardingEmployees::where('id',$this->onboarding_id)->orderBy('id','desc')->first();
            // dd($history);
            if ($history && isset($decodedData['from_buckets']) && isset($decodedData['to_buckets'])) {

                $oldStatusId = $history->old_status_id ?? 0;
                $statusId = $history->status_id ?? 0;

                // Log automation debug info
                Log::debug('Automation Onboarding - Candidate Moves Check', [
                    'onboarding_id' => $this->onboarding_id,
                    'old_status_id' => $oldStatusId,
                    'current_status_id' => $statusId,
                    'from_buckets' => $decodedData['from_buckets'],
                    'to_buckets' => $decodedData['to_buckets'],
                ]);

                // Note: old_status_id must be properly set when status changes occur
                // Consider adding a model observer to track status changes automatically
                if ((in_array($oldStatusId, $decodedData['from_buckets']) || in_array(0, $decodedData['from_buckets'])) && (in_array($statusId, $decodedData['to_buckets']) || in_array(0, $decodedData['to_buckets']))) {

                    $pipeline1 = HiringStatus::where('id', $oldStatusId)->first();
                    $pipeline2 = HiringStatus::where('id', $statusId)->first();

                    if ($pipeline1 && $pipeline2) {
                        $candidateName = $this->getCandidateName();
                        $this->mailMessage .= "{$candidateName}'s status has transitioned from {$pipeline1->status} to {$pipeline2->status}.";
                        $statusData = true;

                        Log::info('Automation triggered for Candidate Moves', [
                            'onboarding_id' => $this->onboarding_id,
                            'candidate_name' => $candidateName,
                            'from_status' => $pipeline1->status,
                            'to_status' => $pipeline2->status,
                        ]);
                    }

                }

            }

        } elseif (isset($decodedData['event_type']) && $decodedData['event_type'] == 'Candidate Stays') {

            if (isset($decodedData['for_days']) && isset($decodedData['in_buckets'])) {
                $statusId = $history->status_id ?? 0;
                // Fixed: Use updated_at instead of period_of_agreement_start_date for more accurate status duration
                $days_in_status = DB::table('onboarding_employees')
                    ->selectRaw('DATEDIFF(NOW(), updated_at) AS days_in_status')
                    ->where('id', $this->onboarding_id)
                    ->value('days_in_status');

                if ($decodedData['for_days'] <= $days_in_status && in_array($statusId, $decodedData['in_buckets'])) {

                    $pipeline = HiringStatus::where('id', $statusId)->first();

                    // $days_in_status = DB::table('leads')
                    // ->selectRaw('DATEDIFF(NOW(), pipeline_status_date) AS days_in_status')
                    // ->where('id', $this->onboarding_id)
                    // ->value('days_in_status');

                    $candidateName = $this->getCandidateName();
                    $this->mailMessage .= "{$candidateName}'s status has remained in {$pipeline->status} for over {$days_in_status} days.";
                    $statusData = true;
                }

            }

        }

        return $statusData;

    }

    /**
     * Get status context for intelligent duplicate prevention
     */
    private function getStatusContext(): array
    {
        // Get onboarding record to extract status information
        $onboarding = OnboardingEmployees::find($this->onboarding_id);

        if (! $onboarding) {
            return [
                'from_status_id' => null,
                'to_status_id' => null,
                'from_status_name' => 'Unknown',
                'to_status_name' => 'Unknown',
            ];
        }

        // Extract status IDs (current status and old status if available)
        $currentStatusId = $onboarding->hiring_status_id ?? $onboarding->status_id ?? null;
        $oldStatusId = $onboarding->old_status_id ?? null;

        // Get status names for logging
        $currentStatusName = 'Unknown';
        $oldStatusName = 'Unknown';

        if ($currentStatusId) {
            $currentStatus = HiringStatus::find($currentStatusId);
            $currentStatusName = $currentStatus ? $currentStatus->status : $currentStatusName;
        }

        if ($oldStatusId) {
            $oldStatus = HiringStatus::find($oldStatusId);
            $oldStatusName = $oldStatus ? $oldStatus->status : $oldStatusName;
        }

        return [
            'from_status_id' => $oldStatusId,
            'to_status_id' => $currentStatusId,
            'from_status_name' => $oldStatusName,
            'to_status_name' => $currentStatusName,
        ];
    }
}
