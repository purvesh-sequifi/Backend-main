<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NewSequiDocsTemplate extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'new_sequi_docs_templates';

    protected $fillable = [
        // template data
        'category_id',
        'template_name',
        'template_description',
        'template_content',
        'completed_step', //  DEFAULT '0'
        'is_template_ready', //  DEFAULT '0'
        'recipient_sign_req', //  DEFAULT '1'
        'created_by',

        // pdf data
        'is_pdf', // DEFAULT '0' // COMMENT '0 for no is blank template , 1 for template is pdf'
        'pdf_file_path',
        'pdf_file_other_parameter',

        // email data
        'email_subject',
        'email_content',
        'send_reminder', //  DEFAULT '0'  // COMMENT '0 for no , 1 for yes'
        'reminder_in_days',  //  DEFAULT '0'
        'max_reminder_times', //  DEFAULT '0'
        'is_deleted', //  DEFAULT '0'
        'template_delete_date', //  DEFAULT '0'
        'is_header', //  DEFAULT '1'
        'is_footer', //  DEFAULT '1'
    ];

    protected $hidden = [
        'created_at',
        // ,'updated_at'
    ];

    // SequiDocsTemplateCategories Relation
    public function categories(): HasOne
    {
        return $this->hasOne(\App\Models\SequiDocsTemplateCategories::class, 'id', 'category_id');
    }

    public function created_by(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'created_by')->select('id', 'first_name', 'last_name');
    }

    // get_template_list_for_attach
    public static function get_template_list_for_attach($get_agreements_list)
    {
        $data = [];
        foreach ($get_agreements_list as $key => $row) {
            $data[$key]['id'] = $row['id'];
            $data[$key]['category_id'] = $row['category_id'];
            $data[$key]['template_name'] = $row['template_name'];
            $data[$key]['is_pdf'] = $row['is_pdf'];
            $data[$key]['completed_step'] = $row['completed_step'];
            $data[$key]['is_template_ready'] = $row['is_template_ready'];
            $data[$key]['categories'] = $row['categories'];
            $data[$key]['is_deleted'] = $row['is_deleted'];
        }

        return $data;
    }

    // Define a relationship with NewSequiDocsTemplatePermission
    public function receipient(): HasMany
    {
        return $this->hasMany(\App\Models\NewSequiDocsTemplatePermission::class, 'template_id', 'id')->where('position_type', 'receipient')->where('category_id', '>', 0)->whereHas('positionDetail', function ($query) {
            $query->whereNotNull('position_name');
            $query->where('setup_status', 1);
        })->with(['positionDetail' => function ($query) {
            $query->select('id', 'position_name');
            // $query->where('setup_status', 1);
        }]);
    }

    // Define a relationship with NewSequiDocsTemplatePermission
    public function permission(): HasMany
    {
        return $this->hasMany(\App\Models\NewSequiDocsTemplatePermission::class, 'template_id', 'id')->where('position_type', 'permission')->where('category_id', '>', 0)->whereHas('positionDetail', function ($query) {
            $query->whereNotNull('position_name');
        })->with(['positionDetail' => function ($query) {
            $query->select('id', 'position_name');
        }]);
    }

    // Define a relationship with NewSequiDocsSendDocumentWithOfferLetter for post hiring
    public function post_hiring_document(): HasMany
    {
        return $this->hasMany(\App\Models\NewSequiDocsSendDocumentWithOfferLetter::class, 'template_id', 'id')->where('is_post_hiring_document', '1')->where('is_document_for_upload', '0');
    }

    // onboarding document for send
    public function onboarding_document_for_send(): HasMany
    {
        return $this->hasMany(\App\Models\NewSequiDocsSendDocumentWithOfferLetter::class, 'template_id', 'id')->where('is_post_hiring_document', '0');
    }

    // all doc send with offer letter
    public function document_for_send_with_offer_letter(): HasMany
    {
        return $this->hasMany(\App\Models\NewSequiDocsSendDocumentWithOfferLetter::class, 'template_id', 'id');
    }

    // Define a relationship with NewSequiDocsSendDocumentWithOfferLetter for Onboarding Document Agreement
    public function onboarding_document_agreement(): HasMany
    {
        return $this->hasMany(\App\Models\NewSequiDocsSendDocumentWithOfferLetter::class, 'template_id', 'id')->where('is_post_hiring_document', '0')->where('category_id', '2')->where('is_document_for_upload', '0');
    }

    // relation for on document to upload with offer letter (Onboarding Documents)
    // onboarding_document_to_upload_with_offer_letter
    public function onboarding_document_to_upload_with_offer_letter(): HasMany
    {
        return $this->hasMany(\App\Models\NewSequiDocsSendDocumentWithOfferLetter::class, 'template_id', 'id')->where('is_post_hiring_document', '0')->where('category_id', null)->where('is_document_for_upload', '1')->with('upload_document_types:id,document_name,is_deleted')->select('*', 'id as document_to_upload_id');
    }

    // relation for on document to upload with offer letter (Post-hiring Documents)
    public function post_hiring_document_to_upload_with_offer_letter(): HasMany
    {
        return $this->hasMany(\App\Models\NewSequiDocsSendDocumentWithOfferLetter::class, 'template_id', 'id')->where('is_post_hiring_document', '1')->where('category_id', null)->where('is_document_for_upload', '1')->with('upload_document_types:id,document_name,is_deleted');
    }

    // Define a relationship with NewSequiDocsSendDocumentWithOfferLetter for Onboarding Document AdditionalAgreement
    public function onboarding_document_additional_agreement(): HasMany
    {
        return $this->hasMany(\App\Models\NewSequiDocsSendDocumentWithOfferLetter::class, 'template_id', 'id')->where('is_post_hiring_document', '0')->where('category_id', '>', '2')->where('category_id', '!=', 101);
    }

    public function attachedSmartTextTemplate(): HasMany
    {
        return $this->hasMany(\App\Models\NewSequiDocsSendDocumentWithOfferLetter::class, 'template_id', 'id')->where('is_post_hiring_document', '0')->where('category_id', 101);
    }

    public function postAttachedSmartTextTemplate(): HasMany
    {
        return $this->hasMany(\App\Models\NewSequiDocsSendDocumentWithOfferLetter::class, 'template_id', 'id')->where('is_post_hiring_document', '1')->where('category_id', 101);
    }
}
