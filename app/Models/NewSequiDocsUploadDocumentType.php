<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewSequiDocsUploadDocumentType extends Model
{
    use HasFactory;

    protected $table = 'new_sequi_docs_upload_document_types';

    protected $fillable = [
        'document_name',
        'is_deleted', //  DEFAULT '0'
        'delete_date',
    ];

    protected $hidden = [
        // 'is_deleted'
    ];
}
