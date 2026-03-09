<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentToUpdate extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'document_to_upload';

    protected $fillable = [
        'configuration_id',
        'field_name',
        'field_required',
        'attribute_option',

    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
