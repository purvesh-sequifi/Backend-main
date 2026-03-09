<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewSequiDocsSendSmartTempplateWithOfferLetter extends Model
{
    use HasFactory;

    protected $table = 'new_sequi_docs_send_smart_template_with_offer_letters';

    protected $fillable = [
        'user_id',
        'template_content',
        'template_id',
        'to_send_template_id', // 'template id for send with offer letter'
        'category_id',
        'is_sign_required_for_hire', // '0 for optional , 1 for Mandatory'
        'is_post_hiring_document', // '1 for post Hiring , 0 for Onboarding Documents'
        'is_document_for_upload', // '0 for Signature , 1 for upload file'
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
}
