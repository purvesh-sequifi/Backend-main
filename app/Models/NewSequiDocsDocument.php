<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NewSequiDocsDocument extends Model
{
    use HasFactory;

    protected $table = 'new_sequi_docs_documents';

    const EMAIL_CONTENT_KEY_ARRAY = [
        'Employee_Name',
        'Employee_Position',
        'Office_Location',
        'Business_Name',
        'Company_Name',
        'Company_Email',
        'Company_Website',
        'Company_Address',
        'Company_Logo',
        'Letter_Box',
        'Document_Type',
        'Business_Name_With_Other_Details',
        'sequifi_logo_with_name',
        'Office_Name',
    ];

    const DOCUMENT_CONTENT_KEY_ARRAY = [
        'Employee_Manager_Name',
        'Employee_Manager_Position',
        'Employee_Manager_Department',
        'Sender_Name',
        'Current_Date',
        'Employee_Name',
        'Employee_ID',
        'Employee_first_name',
        'Employee_Address',
        'Employee_Position',
        'Employee_SSN',
        'Office_Name',
        'Office_Location',
        'Employee_Is_Manager',
        'Employee_Team',
        'Recruiter_Name',
        'Additional_Recruiter1_Name',
        'Additional_Recruiter2_Name',
        'Bonus_amount',
        'Bonus_Pay_Date',
        'start_date',
        'end_date',
        'probation_period',
        'Wage_Type',
        'Pay_Rate',
        'PTO_Hours',
        'Unused_PTO',
        'Overtime_Rate',
        'Expected_Weekly_Hours',
        'redline',
        'commission',
        'upfront_amount',
        'Withholding_Amount',
        'Direct_Override_Value',
        'InDirect_Override_Value',
        'Office_Override_Value',
        'deductions',
    ];

    const COMPANY_CONTENT_KEY_ARRAY = [
        'Business_Name',
        'Business_Name_With_Other_Details',
        'Company_Name',
        'Company_Address',
        'Company_Email',
        'Company_Phone',
        'Company_Website',
        'Company_Logo',
        'Letter_Box',
        'sequifi_logo_with_name',
        'Document_Type',
    ];

    protected $fillable = [
        // user and template data
        'id',
        'user_id',
        'user_id_from',  // 'users','onboarding_employees'
        'is_external_recipient', // '0 no , 1 for Yes'
        'external_user_name',
        'external_user_email',
        'template_id',
        'category_id',
        'description',
        'is_active', // '0 not active , 1 for active doc'
        'document_inactive_date',
        'doc_version',
        'send_by',  // 'Document send by user id'
        'is_document_resend',
        'upload_document_type_id',

        'un_signed_document',  // 'doc before sign'
        'document_send_date',  // '0 for no action'

        // Email tracking fields
        'email_sent_at',
        'email_opened_at',
        'email_open_count',
        'email_tracking_token',
        'email_open_details',

        'document_response_status', // check hiring_status  table for possible document_response_status
        'document_response_date',
        'user_request_change_message',
        'document_uploaded_type', // 'manual_doc','secui_doc_uploaded'

        'envelope_id',
        'envelope_password',
        'signature_request_id',
        'signature_request_document_id', // 'signature requested document id'
        'signed_status',  // '0=not signed,1=signed'
        'signed_document', // 'doc after sign'
        'signed_date',

        'send_reminder', //  DEFAULT '0'  // COMMENT '0 for no , 1 for yes'
        'reminder_in_days',  //  DEFAULT '0'
        'max_reminder_times', //  DEFAULT '0'
        'reminder_done_times', //  DEFAULT '0'
        'last_reminder_sent_at', // nullable
        'is_post_hiring_document', //  DEFAULT '0'  // comment 0 for no 1 for yes
        'is_sign_required_for_hire', //  DEFAULT '0'  // comment 0 for no 1 for yes
        'signing_attemp_at',
        'smart_text_template_fied_keyval',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
    ];

    protected $casts = [
        'last_reminder_sent_at' => 'datetime',
    ];

    /**
     * Get the sendBy user associated with the Documents
     */
    public function DocSendBy(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'send_by');
    }

    /**
     * Get the user associated with the NewSequiDocsDocument
     */
    public function Template(): HasOne
    {
        return $this->hasOne(NewSequiDocsTemplate::class, 'id', 'template_id')->where('is_deleted', '<>', 1);
    }

    /**
     * Get the user associated with the NewSequiDocsDocument
     */
    public function Category(): HasOne
    {
        return $this->hasOne(SequiDocsTemplateCategories::class, 'id', 'category_id');
    }

    /**
     * Get the sendTo user associated with the Documents
     */
    public function DocSendTo(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    // Doc send to onboarding employees
    public function doc_send_to_onboarding_employees(): HasOne
    {
        return $this->hasOne(OnboardingEmployees::class, 'id', 'user_id');
    }

    /**
     * Get the new_sequi_docs_upload_document_types associated with the NewSequiDocsSendDocumentWithOfferLetter
     */
    public function upload_document_types(): HasOne
    {
        return $this->hasOne(NewSequiDocsUploadDocumentType::class, 'id', 'upload_document_type_id');
    }

    /**
     * Get all of the comments for the NewSequiDocsDocument
     */
    public function upload_document_file(): HasMany
    {
        return $this->hasMany(NewSequiDocsUploadDocumentFile::class, 'document_id', 'id');
    }

    public function document_comments(): HasMany
    {
        return $this->hasMany(NewSequiDocsDocumentComment::class, 'document_id', 'id');
    }

    public function scopeReminderableDocs($query)
    {

        $query->whereNotNull('category_id')
            ->whereNotNull('document_send_date')
            ->whereNotNull('reminder_in_days')
            ->where([
                'is_active' => '1',
                'send_reminder' => '1',
                'document_response_status' => '0',
                'is_external_recipient' => '0',
                'user_id_from' => 'onboarding_employees',
                'is_post_hiring_documento' => 0,
            ])
            ->where('max_reminder_times', '>', $query->raw('reminder_done_times'));

    }

    /**
     * method for getting list  of attached docs with an offer letter
     * and attached doc sign status
     */
    public static function getAttachedDocsListWithSignStatus(int $envelope_id)
    {

        $attached_documents = '';

        $documents = NewSequiDocsDocument::where('envelope_id', $envelope_id)->get();

        foreach ($documents as $document) {
            $attached_documents .= '<li>';
            $attached_documents .= $document->description;
            if ($document->signed_status == 1) {
                $attached_documents .= ' <span style="color:green"> signed </span>';
            }
            $attached_documents .= '</li>';
        }

        return $attached_documents;

    }

    public function setUnSignedDocumentAttribute($value)
    {
        if (! empty($value) && strpos($value, config('app.aws_s3bucket_old_url')) !== false) {
            // Replace the domain with the new domain
            $value = str_replace(config('app.aws_s3bucket_old_url'), config('app.aws_s3bucket_url'), $value);
        }
        $this->attributes['un_signed_document'] = $value;
    }
}
