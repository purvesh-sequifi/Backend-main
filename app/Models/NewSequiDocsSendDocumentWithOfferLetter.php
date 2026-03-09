<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NewSequiDocsSendDocumentWithOfferLetter extends Model
{
    use HasFactory;

    protected $table = 'new_sequi_docs_send_document_with_offer_letters';

    protected $fillable = [
        'template_id',
        'to_send_template_id', // 'template id for send with offer letter'
        'category_id',
        'is_sign_required_for_hire', // '0 for optional , 1 for Mandatory'
        'is_post_hiring_document', // '1 for post Hiring , 0 for Onboarding Documents'
        'is_document_for_upload', // '0 for Signature , 1 for upload file'
        'manual_doc_type_id',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'id',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the new_sequi_docs_upload_document_types associated with the NewSequiDocsSendDocumentWithOfferLetter
     */
    public function upload_document_types(): HasOne
    {
        // return $this->hasOne(NewSequiDocsUploadDocumentType::class, 'id', 'to_send_template_id');
        return $this->hasOne(NewSequiDocsUploadDocumentType::class, 'id', 'manual_doc_type_id');
    }

    public function template(): HasOne
    {
        return $this->hasOne(NewSequiDocsTemplate::class, 'id', 'to_send_template_id');
    }

    // public function upload_document_types_new(): HasOne
    // {
    //     return $this->hasOne(NewSequiDocsUploadDocumentType::class, 'id', 'manual_doc_type_id');
    // }
}
