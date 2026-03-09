<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewSequiDocsUploadDocumentFile extends Model
{
    use HasFactory;

    protected $table = 'new_sequi_docs_upload_document_files';

    protected $fillable = [
        'document_id',
        'document_file_path',
        's3_document_file_path',
        'file_version',
        'is_deleted', //  DEFAULT '0'
        'delete_date',
    ];

    protected $hidden = [
        // 'is_deleted'
    ];
}
