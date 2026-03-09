<?php

namespace App\Services;

use App\Jobs\AutomationMail;
use App\Models\AutomationActionLog;
use App\Models\AutomationRule;
use App\Models\Lead;
use App\Models\PipelineLeadStatus;
use App\Models\PipelineLeadStatusHistory;
use App\Models\PipelineSubTask;
use App\Models\PipelineSubTaskCompleteByLead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class Automation
{
    public $automationRules;

    public $lead_id;

    public $sub_task_id;

    public $sub_task_id_is_in_rule;

    public $old_pipeline_lead_status;

    public $new_pipeline_lead_status;

    public $mailMessage;

    public $data;

    public function __construct(array $data)
    {

        if (! isset($data['sub_task_id'])) {
            $data['sub_task_id'] = 0;
        }
        if (! isset($data['old_pipeline_lead_status'])) {
            $data['old_pipeline_lead_status'] = 0;
        }
        if (! isset($data['new_pipeline_lead_status'])) {
            $data['new_pipeline_lead_status'] = 0;
        }
        $this->validate($data);
        // $automationRules = AutomationRule::where('status',1)->get();
        // $this->automationRules = $automationRules;
        $this->data = $data;
        $this->lead_id = $data['lead_id'];
        $this->sub_task_id = $data['sub_task_id'];
        $this->sub_task_id_is_in_rule = false;
        $this->old_pipeline_lead_status = $data['old_pipeline_lead_status'];
        $this->new_pipeline_lead_status = $data['new_pipeline_lead_status'];
        $this->mailMessage = '';
    }

    public function trigger()
    {

        Log::info('message', [
            'automationRules' => $this->automationRules,
        ]);

        Log::info('message', [
            'data' => $this->data,
        ]);

        $automationRule = AutomationRule::find($this->data['automation_rule_id']);

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

            $emailsSent = $this->applyThenRules($automationRule);

            // Only create log if we actually sent emails
            if (! empty($emailsSent)) {
                // Create AutomationActionLog - Lead automation uses simple creation (no context restrictions)
                // Note: Keep lead automation permissive - context-aware system is primarily for onboarding
                $log = new AutomationActionLog;
                $log->lead_id = $this->lead_id;
                $log->automation_rule_id = $automationRule->id;
                $log->status = 1;
                $log->email = is_array($emailsSent) ? implode(',', $emailsSent) : $emailsSent;
                $log->email_sent = 0;
                $log->category = 'EMAIL';
                $log->event = 'Lead Email Automation';
                $log->trigger_context = [
                    'lead_id' => $this->lead_id,
                    'automation_type' => 'lead_automation',
                    'timestamp' => now()->toIso8601String(),
                    'note' => 'Lead automation allows multiple triggers - no context-aware duplicate prevention',
                ];
                $log->save();

                Log::info('Automation: Lead automation processed successfully', [
                    'log_id' => $log->id,
                    'lead_id' => $this->lead_id,
                    'emails_sent' => $emailsSent,
                ]);
            } else {
                Log::warning('Automation: No emails sent', [
                    'lead_id' => $this->lead_id,
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
                $log = new AutomationActionLog;
                $log->lead_id = $this->lead_id;
                $log->automation_rule_id = $automationRule->id;
                $log->status = 1;
                $log->save();
            }

            Log::info('$ruleConditionsAreApplicable', [
                '$ruleConditionsAreApplicable' => $ruleConditionsAreApplicable,
            ]);
            // dd($whenRule);
            if ($ruleConditionsAreApplicable) {
                $this->applyThenRules($automationRule);
            }

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

        // Log::debug('"ruleSet":"'.$ruleSet.'"');
        // Log::debug('"sub_task_id":"'.$this->sub_task_id.'"');
        // // dump((string)$ruleSet);
        // // dd(str_contains((string)$ruleSet, '"sub_task_id":"'.$this->sub_task_id.'"'));
        // if (!$this->sub_task_id_is_in_rule) {
        //     if(str_contains((string)$ruleSet, '"sub_task_id":"'.$this->sub_task_id.'"')){
        //         Log::debug('hereppppp');
        //         Log::debug('"sub_task_id":"'.$this->sub_task_id.'"');
        //         $this->sub_task_id_is_in_rule = true;
        //     } else {
        //         return false;
        //     }
        // }

        $decodedData = json_decode($ruleSet, true);

        if (! isset($decodedData['event_type'])) {
            return false;
        }

        if (isset($decodedData['event_type']) && $decodedData['event_type'] == 'Lead Moves') {

            // dd($this->lead_id);

            $history = PipelineLeadStatusHistory::where('lead_id', $this->lead_id)->orderBy('id', 'desc')->first();
            // dd($history);
            if ($history && isset($decodedData['from_buckets']) && isset($decodedData['to_buckets'])) {
                // dd($decodedData['from_buckets']);

                $leadWasIn = $history->old_status_id;
                $leadNowIn = $history->new_status_id;

                Log::debug($decodedData['from_buckets']);
                Log::debug($decodedData['to_buckets']);

                if ((in_array($leadWasIn, $decodedData['from_buckets']) || in_array(0, $decodedData['from_buckets'])) && (in_array($leadNowIn, $decodedData['to_buckets']) || in_array(0, $decodedData['to_buckets']))) {
                    // dd($decodedData['to_buckets']);
                    $pipeline1 = PipelineLeadStatus::where('id', $leadWasIn)->first();
                    $pipeline2 = PipelineLeadStatus::where('id', $leadNowIn)->first();

                    $this->mailMessage .= 'This lead has transitioned from '.$pipeline1->status_name.' to '.$pipeline2->status_name.'.';

                    return true;

                }

            }

            return false;

        } elseif (isset($decodedData['event_type']) && $decodedData['event_type'] == 'Lead Stays') {

            if (isset($decodedData['for_days']) && isset($decodedData['in_buckets'])) {

                $days_in_status = DB::table('leads')
                    ->selectRaw('DATEDIFF(NOW(), pipeline_status_date) AS days_in_status')
                    ->where('id', $this->lead_id)
                    ->value('days_in_status');

                if ($decodedData['for_days'] <= $days_in_status && in_array($this->lead_id, $decodedData['in_buckets'])) {

                    $pipeline = PipelineLeadStatus::where('id', $this->new_pipeline_lead_status)->first();

                    $days_in_status = DB::table('leads')
                        ->selectRaw('DATEDIFF(NOW(), pipeline_status_date) AS days_in_status')
                        ->where('id', $this->lead_id)
                        ->value('days_in_status');

                    $this->mailMessage .= 'This lead has remained in '.$pipeline->status_name.' for over '.$days_in_status.' days.';

                    return true;
                }

            }

            return false;

        } elseif (isset($decodedData['event_type']) && $decodedData['event_type'] == 'Subtask Status') {

            Log::debug('up to here - 1');

            if (isset($decodedData['completed']) && isset($decodedData['from_bucket']) && isset($decodedData['sub_task_id']) && ($decodedData['completed'] == true || $decodedData['completed'] == 'true')) {

                Log::debug('up to here - 2');
                // get completed sub task if this lead
                $completedTasks = PipelineSubTaskCompleteByLead::where([
                    'lead_id' => $this->lead_id,
                    'completed' => '1',
                    'pipeline_sub_task_id' => $decodedData['sub_task_id'],
                    'pipeline_lead_status_id' => $decodedData['from_bucket'],
                ])->get();

                Log::debug('$completedTask');
                Log::debug($completedTasks);

                if ($completedTasks->isNotEmpty()) {

                    Log::debug('up to here - 3');
                    $pipeline = PipelineLeadStatus::find($decodedData['from_bucket']);
                    $subTask = PipelineSubTask::find($decodedData['sub_task_id']);
                    if ($pipeline && $subTask) {
                        $this->mailMessage .= '<p>'.$subTask->description.' under '.$pipeline->status_name.' has been marked as completed.</p>';
                        Log::debug('up to here - 4');
                    } else {
                        Log::debug('up to here - 5');

                        return false;
                    }
                    Log::debug('up to here - 6');

                    return true;

                }

            } else {
                Log::debug('up to here - 7');

                return false;
            }

        }

        return false;

    }

    public function applyThenRules($automationRule)
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
                $lead = $this->getLeadData($this->lead_id);

                if (isset($value['bgcolor']) && $value['action'] == 'Highlight Lead') {

                    $lead = Lead::find($this->lead_id);
                    $lead->background_color = $value['bgcolor'];
                    $lead->save();

                } elseif ($value['action'] == 'Email Lead') {

                    $automationMailData = [
                        'lead_name' => $lead->first_name.' '.$lead->last_name,
                        'recipient_name' => $lead->first_name.' '.$lead->last_name,
                        'send_to' => $lead->email,
                        'mailMessage' => $this->mailMessage,
                    ];
                    AutomationMail::dispatch($automationMailData)->delay(now()->addDays($delay_by));
                    $emailsSent[] = $lead->email;

                } elseif ($value['action'] == 'Email Recruiter') {

                    if ($lead->recruiter_id) {
                        $user = User::find($lead->recruiter_id);
                        if ($user && $user->email) {
                            // send mail to lead with delay or immidiate
                            $automationMailData = [
                                'lead_name' => $lead->first_name.' '.$lead->last_name,
                                'recipient_name' => $user->first_name.' '.$user->last_name,
                                'send_to' => $user->email,
                                'mailMessage' => $this->mailMessage,
                            ];
                            AutomationMail::dispatch($automationMailData)->delay(now()->addDays($delay_by));
                            $emailsSent[] = $user->email;
                        }
                    }

                } elseif ($value['action'] == 'Email Reporting Manager') {

                    if ($lead->reporting_manager_id) {
                        $user = User::find($lead->reporting_manager_id);
                        if ($user && $user->email) {
                            // send mail to lead with delay or immidiate
                            $automationMailData = [
                                'lead_name' => $lead->first_name.' '.$lead->last_name,
                                'recipient_name' => $user->first_name.' '.$user->last_name,
                                'send_to' => $user->email,
                                'mailMessage' => $this->mailMessage,
                            ];
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

                    // Remove duplicates to prevent same recipient receiving multiple emails
                    $originalCount = count($emails);
                    $emails = array_unique($emails);
                    $uniqueCount = count($emails);

                    if ($originalCount > $uniqueCount) {
                        Log::info('Automation: Duplicate emails removed from custom email list', [
                            'lead_id' => $this->lead_id,
                            'original_count' => $originalCount,
                            'unique_count' => $uniqueCount,
                            'raw_emails' => $value['custom_email'],
                        ]);
                    }

                    if (! empty($emails) && count($emails) > 0) {
                        $emails = array_values($emails); // Re-index array after array_unique
                        $firstEmail = array_shift($emails); // Get the first email and remove it from array
                        $ccEmails = $emails; // Remaining emails become CC

                        // Prepare data for the mail
                        $automationMailData = [
                            'lead_name' => $lead->first_name.' '.$lead->last_name,
                            'recipient_name' => '',
                            'send_to' => $firstEmail,
                            'cc' => $ccEmails, // Pass the CC emails as an array
                            'mailMessage' => $this->mailMessage,
                        ];

                        AutomationMail::dispatch($automationMailData)->delay(now()->addDays($delay_by));
                        $emailsSent[] = $firstEmail;
                        $emailsSent = array_merge($emailsSent, $ccEmails);
                    } else {
                        Log::warning('Automation: No valid emails found for custom email action', [
                            'lead_id' => $this->lead_id,
                            'raw_emails' => $value['custom_email'],
                        ]);
                    }
                }
            }
        }

        return implode(',', $emailsSent);
    }

    protected function validate(array $data): void
    {
        // Fixed: Correct validation logic - check if required keys exist in data array
        $requiredKeys = ['lead_id', 'sub_task_id', 'new_pipeline_lead_status', 'old_pipeline_lead_status'];
        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $data)) {
                throw new InvalidArgumentException("Missing required parameter: $key");
            }
        }

        if (! is_int($data['lead_id'])) {
            throw new InvalidArgumentException('Invalid lead_id: must be a integer.');
        }

        if (! is_int($data['sub_task_id'])) {
            throw new InvalidArgumentException('Invalid sub_task_id: must be a integer.');
        }

        if (! is_int($data['new_pipeline_lead_status'])) {
            throw new InvalidArgumentException('Invalid new_pipeline_lead_status: must be a integer.');
        }

        if (! is_int($data['old_pipeline_lead_status'])) {
            throw new InvalidArgumentException('Invalid old_pipeline_lead_status: must be a integer.');
        }
    }

    public function getLeadData($id)
    {
        return Lead::find($id);
    }

    public function testRule($rule)
    {

        if (! isset($rule['event_type'])) {
            return false;
        }

        if (isset($rule['event_type']) && $rule['event_type'] == 'Lead Moves') {

            $history = PipelineLeadStatusHistory::where('lead_id', $this->lead_id)->orderBy('id', 'desc')->first();
            if ($history && isset($rule['from_buckets']) && isset($rule['to_buckets'])) {

                $leadWasIn = $history->old_status_id;
                $leadNowIn = $history->new_status_id;

                if ((in_array($leadWasIn, $rule['from_buckets']) || in_array(0, $rule['from_buckets'])) && (in_array($leadNowIn, $rule['to_buckets']) || in_array(0, $rule['to_buckets']))) {
                    // dd($rule['to_buckets']);
                    $pipeline1 = PipelineLeadStatus::where('id', $leadWasIn)->first();
                    $pipeline2 = PipelineLeadStatus::where('id', $leadNowIn)->first();

                    $this->mailMessage .= 'This lead has transitioned from '.$pipeline1->status_name.' to '.$pipeline2->status_name.'.';

                    return true;

                }

            }

            return false;

        } elseif (isset($rule['event_type']) && $rule['event_type'] == 'Lead Stays') {

            if (isset($rule['for_days']) && isset($rule['in_buckets'])) {

                $days_in_status = DB::table('leads')
                    ->selectRaw('DATEDIFF(NOW(), pipeline_status_date) AS days_in_status')
                    ->where('id', $this->lead_id)
                    ->value('days_in_status');

                $lead = Lead::find($this->lead_id);
                $pipeline = PipelineLeadStatus::where('id', $lead->pipeline_status_id)->first();

                if ($rule['for_days'] <= $days_in_status && (in_array($lead->pipeline_status_id, $rule['in_buckets']) || in_array(0, $rule['in_buckets']))) {

                    $days_in_status = DB::table('leads')
                        ->selectRaw('DATEDIFF(NOW(), pipeline_status_date) AS days_in_status')
                        ->where('id', $this->lead_id)
                        ->value('days_in_status');

                    $this->mailMessage .= 'This lead has remained in '.$pipeline->status_name.' for over '.$days_in_status.' days.';

                    return true;
                }

            }

            return false;

        } elseif (isset($rule['event_type']) && $rule['event_type'] == 'Subtask Status') {

            if (isset($rule['completed']) && isset($rule['from_bucket']) && isset($rule['sub_task_id']) && ($rule['completed'] == true || $rule['completed'] == 'true')) {

                // get completed sub task if this lead
                $completedTasks = PipelineSubTaskCompleteByLead::where([
                    'lead_id' => $this->lead_id,
                    'completed' => '1',
                    'pipeline_sub_task_id' => $rule['sub_task_id'],
                    'pipeline_lead_status_id' => $rule['from_bucket'],
                ])->get();

                if ($completedTasks->isNotEmpty()) {

                    $pipeline = PipelineLeadStatus::find($rule['from_bucket']);
                    $subTask = PipelineSubTask::find($rule['sub_task_id']);
                    if ($pipeline && $subTask) {
                        $this->mailMessage .= '<p>'.$subTask->description.' under '.$pipeline->status_name.' has been marked as completed.</p>';
                    } else {
                        return false;
                    }

                    return true;

                }

            } else {
                return false;
            }

        }

        return false;

    }
}
