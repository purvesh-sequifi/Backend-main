<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'document_types';

    protected $fillable = [
        // 'document_types',
        'configuration_id',
        'field_name',
        'field_required',
        'field_link',
        'is_deleted',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
