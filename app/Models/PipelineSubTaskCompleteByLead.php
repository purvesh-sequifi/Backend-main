<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use App\Jobs\AutomationMail;
use App\Services\Automation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PipelineSubTaskCompleteByLead extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $fillable = [
        'completed',
        'lead_id',
        'pipeline_sub_task_id', // sub task of bucket(pipeline)
        'pipeline_lead_status_id', // bucket(pipeline) //nullable
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    protected $table = 'pipeline_sub_task_complete_by_leads';

    public function task(): BelongsTo
    {
        return $this->belongsTo(PipelineSubTask::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    protected static function booted()
    {
        // static::created(function ($model) {
        //     if ($model->completed == 1) {
        //         $model->triggerAutomation($model);
        //     }
        // });

        static::updating(function ($model) {
            // if ($model->isDirty('completed') &&$model->completed == 1) {
            //     $model->triggerAutomation($model);
            // }
            // if ($model->isDirty('completed') && $model->completed == 1) {
            //     // $model->triggerAutomation($model);
            // }
        });
    }

    public function triggerAutomation($model)
    {
        Log::info('Automation Check Start');

        // Save data in AutomationActionLog with duplicate prevention
        $log = AutomationActionLog::createSafely([
            'lead_id' => $model->lead_id,
            'status' => 0,
            'category' => AutomationRule::CATEGORY_LEAD,
            'event' => 'Subtask Status',
            // 'new_pipeline_lead_status' => (int)$model->pipeline_status_id
        ]);

        /*
        $automation = new Automation([
            'lead_id' => $model->lead_id,
            'sub_task_id' => $model->pipeline_sub_task_id,
            'old_pipeline_lead_status' => 0, // from bucket
            'new_pipeline_lead_status' => $model->pipeline_lead_status_id, // to bucket
        ]);

        $automation->trigger();
        */

        /*
        Log::info('Automation Check Start');

        $automationRules = AutomationRule::where('status',1)->get();
        $runAutomation = false;
        foreach($automationRules as $automationRule){

            Log::debug('$automationRule->rule[0]->then');
            Log::debug($automationRule->rule[0]['then']);

            $when = '';
            if(isset($automationRule->rule[0]['when'])){

                $andRule = [];
                $orRule = [];
                foreach ($automationRule->rule[0]['when'] as $key => $value) {

                    $when .= json_encode($value) .' #' . strtolower($value['operator']) .'# ';

                }

            }

            Log::info('$when string');
            Log::info($when);


            $newArr = explode('#or#', $when);

            Log::info('$when array');
            Log::info($newArr);


            foreach($newArr as $item){



                $item = str_replace("#and#", ",", $item);
                $item = str_replace("##", "", $item);
                $item = rtrim($item);

                Log::info('$when array item');
                Log::info($item);


                $insArr = explode(' , ',$item);

                $mailMessage = '';
                $triggerAutomation = false;

                foreach($insArr as $insArrItm){
                    // Log::info(json_decode($insArrItm,true));
                    $decodedData = json_decode($insArrItm,true);
                    Log::info('decodedData when data');
                    Log::info($decodedData);

                    if(isset($decodedData['event_type']) && $decodedData['event_type'] == 'Lead Moves'){

                        Log::info('Lead Moves');

                        $history = PipelineLeadStatusHistory::where('lead_id',$model->lead_id)->orderBy('id','desc')->first();

                        //from bucket
                        if($history && isset($decodedData['from_buckets']) && isset($decodedData['to_buckets'])){

                            //lead was in
                            $leadWasIn = $history->old_status_id;
                            $leadNowIn = $history->new_status_id;



                            if(in_array($leadWasIn, $decodedData['from_buckets']) && in_array($leadNowIn, $decodedData['to_buckets'])){

                                $pipeline1 = PipelineLeadStatus::where('id', $leadWasIn)->first();
                                $pipeline2 = PipelineLeadStatus::where('id', $leadNowIn)->first();

                                $mailMessage .= 'This lead has transitioned from '.$pipeline1->status_name.' to '.$pipeline2->status_name.'.';

                                $triggerAutomation = true;

                            }

                        } else {
                            continue;
                        }

                    } else if(isset($decodedData['event_type']) && $decodedData['event_type'] == 'Lead Stays'){

                        Log::info('Lead Stays');

                        if(isset($decodedData['for_days']) && isset($decodedData['in_buckets'])){

                            $days_in_status = DB::table('leads')
                                ->selectRaw('DATEDIFF(NOW(), pipeline_status_date) AS days_in_status')
                                ->where('id', $model->lead_id)
                                ->value('days_in_status');


                            if($decodedData['for_days'] <= $days_in_status && in_array($model->lead_id, $decodedData['in_buckets'])){

                                $pipeline1 = PipelineLeadStatus::where('id', $model->pipeline_status_id)->first();

                                $days_in_status = DB::table('leads')
                                    ->selectRaw('DATEDIFF(NOW(), pipeline_status_date) AS days_in_status')
                                    ->where('id', $model->lead_id)
                                    ->value('days_in_status');

                                $mailMessage .= 'This lead has remained in '.$pipeline1->status_name.' for over '.$days_in_status.' days.';

                                $triggerAutomation = true;
                            } else {
                                continue;
                            }

                        } else {

                            continue;

                        }

                    } else if(isset($decodedData['event_type']) && $decodedData['event_type'] == 'Subtask Status'){

                        Log::info('Subtask Status');

                        if(isset($decodedData['completed']) && isset($decodedData['from_bucket']) && isset($decodedData['sub_task_id']) && $decodedData['completed'] == true){

                            if($model->pipeline_sub_task_id == $decodedData['sub_task_id'] && $model->pipeline_lead_status_id == $decodedData['from_bucket']){

                                $pipeline1 = PipelineLeadStatus::where('id', $model->pipeline_status_id)->first();
                                $subTask = PipelineSubTask::find($model->pipeline_sub_task_id);

                                $mailMessage .= $subTask->description . ' under '.$pipeline1->status_name.' has been marked as completed.';

                                $triggerAutomation = true;
                            }

                        } else {
                            continue;
                        }

                    }

                }

                Log::info('mail msg');
                Log::info($mailMessage);

                //capture then
                if(isset($automationRule->rule[0]['then'])){

                    Log::info('Then part start...........................');
                    foreach($automationRule->rule[0]['then'] as $key => $value){

                        $lead = Lead::find($model->lead_id);

                        if($triggerAutomation && $lead && isset($value['action'])){



                            $delay_by = 0;
                            $delay = 'days';
                            if(isset($value['delay_by'])){
                                $delay_by = $value['delay_by'];
                            }

                            if($value['action'] == 'Email Lead'){

                                if($lead->email){
                                    //Send Mail 2 Lead With Delay or Immidiate
                                    $automationMailData = [
                                        'lead_name' => $lead->first_name .' '.$lead->last_name,
                                        'recipient_name' => $lead->first_name .' '.$lead->last_name,
                                        'send_to' => $lead->email,
                                        'mailMessage' => $mailMessage
                                    ];
                                    AutomationMail::dispatch($automationMailData)->delay(now()->addDays($delay_by));
                                }

                            } else if($value['action'] == 'Email Recruiter'){

                                if($lead->recruiter_id){
                                    $user = User::find($lead->recruiter_id);
                                    if($user && $user->email){
                                        //send mail to lead with delay or immidiate
                                        $automationMailData = [
                                            'lead_name' => $lead->first_name .' '.$lead->last_name,
                                            'recipient_name' => $user->first_name .' '.$user->last_name,
                                            'send_to' => $user->email,
                                            'mailMessage' => $mailMessage
                                        ];
                                        AutomationMail::dispatch($automationMailData)->delay(now()->addDays($delay_by));
                                    }
                                }

                            } else if($value['action'] == 'Email Reporting Manager'){

                                if($lead->reporting_manager_id){
                                    $user = User::find($lead->reporting_manager_id);
                                    if($user && $user->email){
                                        //send mail to lead with delay or immidiate
                                        $automationMailData = [
                                            'lead_name' => $lead->first_name .' '.$lead->last_name,
                                            'recipient_name' => $user->first_name .' '.$user->last_name,
                                            'send_to' => $user->email,
                                            'mailMessage' => $mailMessage
                                        ];
                                        AutomationMail::dispatch($automationMailData)->delay(now()->addDays($delay_by));
                                    }
                                }

                            } else if(isset($value['custom_email']) && $value['action'] == 'Email[custom email]'){

                                //send mail to lead with delay or immidiate
                                $automationMailData = [
                                    'lead_name' => $lead->first_name .' '.$lead->last_name,
                                    'recipient_name' => '',
                                    'send_to' => $value['custom_email'],
                                    'mailMessage' => $mailMessage
                                ];
                                AutomationMail::dispatch($automationMailData)->delay(now()->addDays($delay_by));

                            } else if(isset($value['bgcolor']) && $value['action'] == 'Highlight Lead'){

                                $lead = Lead::find($model->lead_id);
                                $lead->background_color = $value['bgcolor'];
                                $lead->save();

                            }

                        }

                    }

                }

            }

        }
        */

    }
}
