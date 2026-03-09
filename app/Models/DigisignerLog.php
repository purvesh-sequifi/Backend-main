<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DigisignerLog extends Model
{
    use HasFactory;

    protected $table = 'digisigner_logs';

    protected $fillable = [
        'document_id',
        'response',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
