<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Documents extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'documents';

    // SELECT `id`, `user_id`, `user_id_from`, `send_by`, `document_send_date`, `document_type_id`, `template_id`, `category_id`, `document_response_status`, `user_request_change_message`, `document_uploaded_type`, `description`, `created_at`, `updated_at` FROM `documents` WHERE 1
    protected $fillable = [
        'user_id',
        'user_id_from', // 'users','onboarding_employees'
        'send_by',
        'document_send_date',
        'is_active', // 0 not active , 1 for active doc
        'document_type_id',
        'template_id',  // template id
        'category_id',
        'signature_request_document_id',  // digisigner document id for other document like W9 and etc.
        'document_response_status',
        'user_request_change_message',
        'document_uploaded_type', // manual_doc , secui_doc_uploaded // secui_doc_uploaded for sequi doc digisiner doc like offer letter , aggrement and other , manual_doc for manual docs like last company pay slip , driving licence and other docs.
        'document_type_id',
        'description',
    ];

    protected $hidden = [
        // 'created_at'
        //  'updated_at'
    ];

    public function documentfile(): HasMany
    {
        return $this->hasMany(\App\Models\DocumentFiles::class, 'document_id', 'document_type_id')->select('id', 'document');
    }

    public function DocumentFileIs(): HasOne
    {
        return $this->hasOne(\App\Models\DocumentFiles::class, 'document_id', 'id');
        // return $this->hasMany('App\Models\DocumentFiles', 'document_id', 'document_type_id')->select('id','document');
    }

    public function documenttype(): HasOne
    {
        return $this->hasOne(\App\Models\DocumentType::class, 'id', 'document_type_id');
    }

    public function categoryType(): HasOne
    {
        return $this->hasOne(\App\Models\SequiDocsTemplateCategories::class, 'id', 'category_id');
    }

    /**
     * Get the sendTo user associated with the Documents
     */
    public function DocSendTo(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function onboarding_employee(): HasOne
    {
        return $this->hasOne(OnboardingEmployees::class, 'id', 'user_id');
    }

    /**
     * Get the sendBy user associated with the Documents
     */
    public function DocSendBy(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'send_by');
    }

    public static function document_with_document_files($digisigner_doc_id)
    {
        return $document_files = Documents::leftjoin('document_files', 'document_files.document_id', 'documents.id')
            ->where('signed_document_id', '=', $digisigner_doc_id)
            ->select('documents.*', 'document_files.signed_status')
            ->first();
    }
}
