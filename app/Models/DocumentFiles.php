<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentFiles extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'document_files';

    protected $fillable = [
        'document_id',
        'signature_request_id',
        'signed_document_id',
        'signed_status',
        'document',
        'signed_document',
        'signature_request_id_for_callback',
        'created_at',
    ];
}
