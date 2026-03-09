<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewSequiDocsSignatureRequestLog extends Model
{
    use HasFactory;

    protected $table = 'new_sequi_docs_signature_request_logs';

    protected $fillable = [
        'ApiName',
        'user_array',
        'envelope_data',
        'send_document_final_array',
        'signature_request_response',
    ];
}
