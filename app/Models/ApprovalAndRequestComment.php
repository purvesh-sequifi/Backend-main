<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalAndRequestComment extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'approval_and_request_comments';

    protected $fillable = [
        'request_id',
        'user_id',
        'type',
        'comment',
        'image',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
