<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use App\Jobs\AutomationMail;
use App\Services\Automation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Lead extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'leads';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'mobile_no',
        'source',
        'state_id',
        'office_id',
        'comments',
        'status',
        'interview_date',
        'interview_time',
        'interview_schedule_by_id',
        'reporting_manager_id',
        'action_status',
        'recruiter_id',
        'assign_by_id',
        'type',
        'pipeline_status_id',
        'pipeline_status_date',
        'last_hired_date',
        'custom_fields_detail',
        'lead_rating',
        'custom_rating',
        'overall_rating',
        'background_color',
    ];

    protected $hidden = [
        // 'created_at',
        'updated_at',
    ];

    public const STATUS_HIRED = 'Hired';

    public const STATUS_REJECTED = 'Rejected';

    public const STATUS_FOLLOWUP = 'Follow Up';

    // lead added email content
    public static function lead_added_email_content($datas)
    {
        $SequiDocsEmailSettings = SequiDocsEmailSettings::where('category_id', '=', '3')->where('unique_email_template_code', '=', '4')->first(); // for welcome mail. unique_email_template_code = 1

        $lead_added_email_content['subject'] = 'Welcome to Sequifi';
        $lead_added_email_content['is_active'] = 0;
        $lead_added_email_content['template'] = '';

        // return $datas;
        $lead_data = $datas['0'];
        if ($SequiDocsEmailSettings != null && $SequiDocsEmailSettings->email_content != null) {
            $email_content = $SequiDocsEmailSettings->email_content;
            $auth_user_data = auth()->user();

            $resolve_key_data['Employee_Name'] = isset($lead_data['first_name']) ? $lead_data['first_name'].' '.$lead_data['last_name'] : '';
            $resolve_key_data['Employee Name'] = isset($lead_data['first_name']) ? $lead_data['first_name'].' '.$lead_data['last_name'] : '';
            $resolve_key_data['Employee_User_Name'] = $lead_data['email'];
            $resolve_key_data['Employee_User_Password'] = 'Flexpwr!';
            $System_Login_Link = config('app.login_link');
            $resolve_key_data['System_Login_Link'] = $System_Login_Link;

            $company = CompanyProfile::first();

            $company_and_other_static_images = SequiDocsEmailSettings::company_and_other_static_images($company);
            $header_image = $company_and_other_static_images['header_image'];
            $Company_Logo = $company_and_other_static_images['Company_Logo'];
            $sequifi_logo_with_name = $company_and_other_static_images['sequifi_logo_with_name'];
            $letter_box = $company_and_other_static_images['letter_box'];
            $sequifiLogo = $company_and_other_static_images['sequifiLogo'];

            $Company_Logo_is = '<img src="'.$Company_Logo.'" style="width: 120px; height: 120px; margin: 0px auto;">';
            $email_content = str_replace('[Company_Logo]', $Company_Logo_is, $email_content);
            $email_content = str_replace('[Company Logo]', $Company_Logo_is, $email_content);

            $resolve_key_data['Company_Name'] = $company->name;
            $resolve_key_data['Company_Address'] = $company->business_address;
            $resolve_key_data['company_address_line2'] = $company->business_address;
            $resolve_key_data['Company_Phone'] = $company->phone_number;
            $resolve_key_data['Company_Email'] = $company->company_email;
            $resolve_key_data['Company_Website'] = $company->company_website;
            $resolve_key_data['Current_Date'] = date('d-m-Y');
            $resolve_key_data['Company Name'] = $company->name;
            $resolve_key_data['Company Address'] = $company->business_address;
            $resolve_key_data['company_address_line2'] = $company->business_address;
            $resolve_key_data['Company Phone'] = $company->phone_number;
            $resolve_key_data['Company Email'] = $company->company_email;
            $resolve_key_data['Company Website'] = $company->company_website;
            $resolve_key_data['Current_Date'] = date('d-m-Y');

            foreach ($resolve_key_data as $key => $value) {
                $email_content = str_replace('['.$key.']', $value, $email_content);
            }
            $email_header_footer = SequiDocsEmailSettings::email_header_footer();
            $email_content = str_replace('[Email_Content]', $email_content, $email_header_footer);
            $lead_added_email_content['is_active'] = $SequiDocsEmailSettings->is_active;
            $lead_added_email_content['template'] = $email_content;
            $lead_added_email_content['subject'] = $SequiDocsEmailSettings->email_subject;
        }

        return $lead_added_email_content;
    }

    public function recruiter(): HasOne
    {
        // return $this->hasOne('App\Models\User','id','recruiter_id')->select('id','first_name','last_name');
        return $this->hasOne(\App\Models\User::class, 'id', 'recruiter_id');
    }

    public function reportingManager(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'reporting_manager_id')->select('id', 'first_name', 'last_name');
    }

    public function state(): HasOne
    {
        return $this->hasOne(\App\Models\State::class, 'id', 'state_id')->select('id', 'name', 'state_code');
    }

    public function pipelineleadstatus(): HasOne
    {
        return $this->hasOne(\App\Models\PipelineLeadStatus::class, 'id', 'pipeline_status_id');
    }

    public function comment(): HasOne
    {
        return $this->hasOne(leadComment::class, 'lead_id', 'id')->orderBy('id', 'DESC');
    }

    public function getLeadRatingAttribute($val)
    {
        // Check if the value is 0.00 and return as integer 0
        if ($val == 0.00) {
            return 0;
        }

        // Otherwise, round to 1 decimal place
        return round($val, 1);
    }

    public function getOverallRatingAttribute($val)
    {
        if ($val == 0.00) {
            return 0;
        }

        return round($val, 1);
    }

    public function getCustomRatingAttribute($val)
    {
        if ($val == 0.00) {
            return 0;
        }

        return round($val, 1);
    }

    /**
     * Automatically calculate overall_rating before saving.
     */
    public static function boot()
    {

        parent::boot();
        static::saving(function ($lead) {

            $lead_rating = $lead->lead_rating;

            if (! empty($lead->custom_fields_detail) && $lead->custom_fields_detail != []) {
                $lead->custom_rating = calculateCustomRating($lead->custom_fields_detail);
            }
            if ($lead->custom_rating == 0.00 || $lead->custom_rating == '0.00' || $lead->custom_rating == '0' || $lead->custom_rating == 0) {
                $lead->overall_rating = $lead_rating;
            } elseif ($lead->lead_rating == 0.00 || $lead->lead_rating == '0.00' || $lead->lead_rating == '0' || $lead->lead_rating == 0) {
                $lead->overall_rating = $lead->custom_rating;
            } else {
                $lead->overall_rating = ($lead->custom_rating / 2) + ($lead_rating / 2);
            }

        });

    }

    /**
     * Define the relationship to the LeadDocument model.
     */
    public function leadDocuments(): HasMany
    {
        return $this->hasMany(LeadDocument::class);
    }

    public function newSequiDocsDocuments(): HasMany
    {
        return $this->hasMany(NewSequiDocsDocument::class, 'external_user_email', 'email')
            ->where('is_external_recipient', 1);
    }

    public function triggerAutomation($model)
    {
        Log::info('Automation Start From Lead Model');

        // Save data in AutomationActionLog with duplicate prevention
        $log = AutomationActionLog::createSafely([
            'lead_id' => $model->id,
            'status' => 0,
            'category' => AutomationRule::CATEGORY_LEAD,
            'event' => 'Lead Moves',
            // 'new_pipeline_lead_status' => (int)$model->pipeline_status_id
        ]);

        /*

        $data = [
            'lead_id' => $model->id,
            // 'sub_task_id' => 0,
            'old_pipeline_lead_status' => 0, // from bucket
            'new_pipeline_lead_status' => (int)$model->pipeline_status_id, // to bucket
        ];

        // dd($data);

        $automation = new Automation($data);

        $automation->trigger();
        */

        /*
        Log::info('Automation Check Start');

        $automationRules = AutomationRule::where('status',1)->get();
        Log::debug($automationRules);
        $runAutomation = false;
        if($automationRules->isNotEmpty()){

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

                            $history = PipelineLeadStatusHistory::where('lead_id',$model->id)->orderBy('id','desc')->first();

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
                                    ->where('id', $model->id)
                                    ->value('days_in_status');


                                if($decodedData['for_days'] <= $days_in_status && in_array($model->id, $decodedData['in_buckets'])){

                                    $pipeline1 = PipelineLeadStatus::where('id', $model->pipeline_status_id)->first();

                                    $days_in_status = DB::table('leads')
                                        ->selectRaw('DATEDIFF(NOW(), pipeline_status_date) AS days_in_status')
                                        ->where('id', $model->id)
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

                                // if($model->pipeline_sub_task_id == $decodedData['sub_task_id'] && $model->pipeline_status_id == $decodedData['from_bucket']){

                                //     $pipeline1 = PipelineLeadStatus::where('id', $model->pipeline_status_id)->first();
                                //     $subTask = PipelineSubTask::find($model->pipeline_sub_task_id);

                                //     $mailMessage .= $subTask->description . ' under '.$pipeline1->status_name.' has been marked as completed.';

                                //     $triggerAutomation = true;
                                // }

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

                            $lead = Lead::find($model->id);

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

                                    $lead = Lead::find($model->id);
                                    $lead->background_color = $value['bgcolor'];
                                    $lead->save();

                                }

                            }

                        }

                    }

                }

            }

        }
        */

    }
}
